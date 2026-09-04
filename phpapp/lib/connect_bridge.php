<?php
// ============================================================================
//  CONNECT — Award → Engagement → Invoice bridge  (additive)
//
//  Scenario 3's keystone: when a marketplace requirement is AWARDED, turn it into
//  a billable engagement that flows through the invoicing chain EXAACT already
//  has — the P4 Billable Event ledger → finance attestation → books invoice.
//  We do NOT build a new invoicing engine; we hand the award to the existing one.
//
//  Deliberate by design: this is an explicit staff action ("Send to billing"),
//  and it creates a PENDING billable event — finance still approves and attests
//  the real invoice, so the books ledger stays the single money truth (a BILLED
//  event always has an invoice behind it, exactly as P4 guarantees).
// ============================================================================

/** The per-unit rate to bill from — the awarded bid, else the requirement band. */
function connect_bridge_rate($req, $awardedApp) {
    $bid = (float)($awardedApp['proposed_rate'] ?? 0);
    if ($bid > 0) return $bid;
    $min = (float)($req['rate_min'] ?? 0); $max = (float)($req['rate_max'] ?? 0);
    if ($min > 0 && $max > 0) return round(($min + $max) / 2, 2);
    return $max > 0 ? $max : $min;
}

/** Unit label from the requirement's work type. */
function connect_bridge_unit($workType) {
    switch (strtolower((string)$workType)) {
        case 'per_visit':   return 'visit';
        case 'day_rate':
        case 'manday':
        case 'shutdown':
        case 'long_deployment': return 'day';
        default: return 'engagement';
    }
}

/**
 * Create (idempotently) the PENDING billable event for an AWARDED requirement.
 * Returns the billable-event id, or 0 if not awarded / no rate. Reuses
 * billable_event_upsert — source ('connect','MARKETPLACE_AWARD', requirement_id).
 */
function connect_engagement_billable($requirementId) {
    if (!function_exists('billable_event_upsert') || !function_exists('cx_requirement_get')) return 0;
    $req = cx_requirement_get($requirementId);
    if (!$req || strtoupper((string)$req['status']) !== 'AWARDED') return 0;
    // A CREW requirement (M10) bills the whole position manifest; a single-role
    // requirement bills the winning bid × positions.
    if (function_exists('cx_is_crew') && cx_is_crew((int)$req['id'])) {
        $c = cx_crew_summary((int)$req['id']);
        if (($c['value'] ?? 0) <= 0) return 0;
        return billable_event_upsert('connect', 'MARKETPLACE_AWARD', (int)$req['id'], [
            'party_id'        => (int)($req['poster_party_id'] ?? 0),
            'contract_number' => (string)($req['ref_code'] ?? ''),
            'service_type'    => trim((string)($req['title'] ?? 'Crew engagement')),
            'qty'             => (int)$c['headcount'],
            'unit'            => 'crew',
            'rate'            => $c['headcount'] > 0 ? round($c['value'] / $c['headcount'], 2) : 0,
            'amount'          => (float)$c['value'],
            'calc_rule'       => 'Marketplace crew award ' . (string)($req['ref_code'] ?? '') . ' — ' . (int)$c['positions'] . ' positions, ' . (int)$c['headcount'] . ' people (estimate; finance attests)',
        ]);
    }
    $app = function_exists('cx_application_get') ? cx_application_get((int)($req['awarded_application_id'] ?? 0)) : null;
    $rate = connect_bridge_rate($req, $app ?: []);
    if ($rate <= 0) return 0;
    $positions = max(1, (int)($req['positions'] ?? 1));
    $amount = round($positions * $rate, 2);
    return billable_event_upsert('connect', 'MARKETPLACE_AWARD', (int)$req['id'], [
        'party_id'        => (int)($req['poster_party_id'] ?? 0),
        'contract_number' => (string)($req['ref_code'] ?? ''),
        'service_type'    => trim((string)($req['title'] ?? 'Marketplace engagement')),
        'qty'             => $positions,
        'unit'            => connect_bridge_unit($req['work_type'] ?? ''),
        'rate'            => $rate,
        'amount'          => $amount,
        'calc_rule'       => 'Marketplace award ' . (string)($req['ref_code'] ?? '') . ' (estimate; finance attests the invoice)',
    ]);
}

/** The billable-event row for a requirement's engagement, or null. */
function connect_engagement_billable_row($requirementId) {
    try { return ops_one("SELECT * FROM billable_events WHERE source_module='connect' AND source_kind='MARKETPLACE_AWARD' AND source_id=?", [(int)$requirementId]) ?: null; }
    catch (Throwable $e) { return null; }
}

/**
 * VOUCHER → INVOICE. Turn an approved engagement voucher into a DRAFT tax invoice
 * in the books engine EXAACT already has — fee and reimbursables as lines, the
 * client's SAC, GST% and TDS% carried across from the posting. We do NOT build a
 * new invoice: we call books_invoice_create + books_line_add, so the books ledger
 * stays the single money truth and finance still reviews/issues the draft.
 *
 * Idempotent (one invoice per voucher, keyed by invoices.voucher_id) and
 * best-effort: any failure returns 0 without throwing, so approving a voucher can
 * never break because billing is mid-setup. Returns the invoice id, or 0.
 */
function connect_voucher_invoice($voucherId) {
    if (!function_exists('connect_engv_get') || !function_exists('books_invoice_create')) return 0;
    try {
        $existing = function_exists('books_invoice_for_voucher') ? books_invoice_for_voucher($voucherId) : 0;
        if ($existing) return $existing;

        $v = connect_engv_get((int)$voucherId);
        if (!$v) return 0;
        if (!in_array(strtoupper((string)$v['status']), ['APPROVED', 'PAID'], true)) return 0; // only a settled voucher bills

        $eng = ops_one("SELECT * FROM cx_engagements WHERE id=?", [(int)($v['engagement_id'] ?? 0)]);
        $req = ($eng && function_exists('cx_requirement_get')) ? cx_requirement_get((int)($eng['requirement_id'] ?? 0)) : null;
        $party = (int)($eng['poster_party_id'] ?? ($req['poster_party_id'] ?? 0));
        if ($party <= 0) return 0;                       // no client to bill

        $fee   = round((float)($v['fee_total'] ?? 0), 2);
        $reimb = round((float)($v['reimb_total'] ?? 0), 2);
        if ($fee <= 0 && $reimb <= 0) return 0;          // nothing to invoice

        $gst = (float)($req['est_tax_pct'] ?? 0); if ($gst <= 0) $gst = function_exists('books_default_gst') ? books_default_gst() : 18.0;
        $tds = (float)($req['est_tds_pct'] ?? 0);
        $sac = trim((string)($req['est_sac'] ?? '')); if ($sac === '') $sac = (function_exists('books_default_sac') ? books_default_sac() : '') ?: '998519';
        $who = trim((string)($v['subject_name'] ?? ($eng['subject_name'] ?? 'professional')));
        $ref = trim((string)($req['ref_code'] ?? ''));
        $period = trim((string)($v['period_label'] ?? ''));

        $res = books_invoice_create([
            'partner_id'      => $party,
            'contract_number' => $ref,
            'tds_pct'         => $tds,
            'voucher_id'      => (int)$voucherId,
            'notes'           => 'Raised from marketplace voucher #' . (int)$voucherId . ($ref ? ' · ' . $ref : ''),
        ]);
        if (!is_array($res) || empty($res['id'])) return 0;
        $invId = (int)$res['id'];

        if ($fee > 0) books_line_add($invId, [
            'description' => 'Technical manpower — ' . $who . ($period ? ' · ' . $period : ''),
            'hsn_sac' => $sac, 'qty' => 1, 'unit' => 'lot', 'rate' => $fee, 'gst_pct' => $gst, 'contract_number' => $ref,
        ]);
        if ($reimb > 0) books_line_add($invId, [
            'description' => 'Reimbursable expenses (as per approved voucher)',
            'hsn_sac' => $sac, 'qty' => 1, 'unit' => 'lot', 'rate' => $reimb, 'gst_pct' => $gst, 'contract_number' => $ref,
        ]);
        return $invId;
    } catch (Throwable $e) { return 0; }
}

/** The invoice id raised from a voucher, or 0 — for showing a link on the voucher. */
function connect_voucher_invoice_id($voucherId) {
    return function_exists('books_invoice_for_voucher') ? books_invoice_for_voucher((int)$voucherId) : 0;
}
