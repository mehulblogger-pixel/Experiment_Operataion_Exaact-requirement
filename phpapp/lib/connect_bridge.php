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
