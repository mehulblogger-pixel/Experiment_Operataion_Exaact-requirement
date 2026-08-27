<?php
// Phase 2 §29 — recognised-revenue reconciliation, READ-ONLY, between the two money truths that
// already coexist (see §80 legacy register). A job carries a legacy single-invoice snapshot
// (jobs.invoice_amount / invoice_raised / payment_received) that MIS, boss_profit and some dashboards
// still read; the books ledger (invoices -> invoice_lines.job_id) is the real bill. These can drift —
// a job re-invoiced, a credit note, a correction booked only in the ledger. This surfaces WHERE they
// disagree so someone can look; it changes NO displayed number and touches no row, which is exactly
// why it needs no §28 sign-off. (Converging the readers onto one engine is §28 and is sign-off-gated.)

// How far apart (in currency units) the two figures may sit before it counts as a divergence. The
// legacy field's tax basis is ambiguous, so we accept a match against EITHER the net or the gross
// ledger total; a divergence means it matches neither.
function revrecon_tolerance() { $t = (float)setting_get('revrecon_tolerance', 1); return $t > 0 ? $t : 1; }

// Reconcile one job. Returns the legacy figure, the ledger net (pre-tax) and gross (incl-tax) totals
// attributed to the job through non-cancelled invoices, which basis the legacy figure matches, and
// whether it diverges from both.
function revrecon_job($jobId) {
    $jobId = (int)$jobId;
    $one = function ($sql, $a = []) { try { return ops_one($sql, $a); } catch (Throwable $e) { return null; } };
    $j = $one("SELECT id, invoice_amount, invoice_raised, payment_received FROM jobs WHERE id=?", [$jobId]);
    $legacy = (float)($j['invoice_amount'] ?? 0);
    // Ledger totals for this job, excluding cancelled invoices.
    $led = $one(
        "SELECT COALESCE(SUM(il.amount),0) net, COALESCE(SUM(il.line_total),0) gross
           FROM invoice_lines il JOIN invoices i ON i.id = il.invoice_id
          WHERE il.job_id=? AND COALESCE(i.status,'') <> 'CANCELLED'", [$jobId]);
    $net = (float)($led['net'] ?? 0); $gross = (float)($led['gross'] ?? 0);
    $tol = revrecon_tolerance();
    $matchNet   = abs($legacy - $net)   <= $tol;
    $matchGross = abs($legacy - $gross) <= $tol;
    $basis = $matchGross ? 'gross' : ($matchNet ? 'net' : 'none');
    // Only meaningful once there is something on at least one side.
    $has = ($legacy > 0) || ($net > 0) || ($gross > 0);
    return [
        'job_id' => $jobId, 'legacy' => round($legacy, 2), 'ledger_net' => round($net, 2),
        'ledger_gross' => round($gross, 2), 'basis' => $basis,
        'diverges' => $has && !$matchNet && !$matchGross,
        'legacy_only' => $legacy > 0 && $net == 0 && $gross == 0,
        'ledger_only' => $legacy == 0 && ($net > 0 || $gross > 0),
    ];
}

// The set of jobs whose legacy invoice figure reconciles against neither ledger basis. Only jobs that
// carry a legacy figure or a ledger line are considered. Read-only, capped.
function revrecon_scan($limit = 100) {
    $all = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };
    // Candidate jobs: those with a legacy amount, OR those that appear on an invoice line.
    $ids = [];
    foreach ($all("SELECT id FROM jobs WHERE COALESCE(invoice_amount,0) <> 0 OR COALESCE(invoice_raised,0)=1") as $r) $ids[(int)$r['id']] = 1;
    foreach ($all("SELECT DISTINCT job_id FROM invoice_lines WHERE COALESCE(job_id,0) > 0") as $r) $ids[(int)$r['job_id']] = 1;
    $out = [];
    foreach (array_keys($ids) as $jid) {
        $rc = revrecon_job($jid);
        if ($rc['diverges']) { $out[] = $rc; if (count($out) >= $limit) break; }
    }
    return $out;
}

// How many jobs diverge (for a health/advisory count). Cheap-ish: reuses the scan with a high cap.
function revrecon_count() { return count(revrecon_scan(100000)); }

// ---------------------------------------------------------------------------
//  Revamp — the reconciliation worklist & health summary (read-only)
//  §29 already surfaces a divergence COUNT on the attention band. To actually
//  reach "green" (the prerequisite before §28 switches any revenue reader onto
//  the ledger) finance needs to see WHICH jobs disagree and by how much. This
//  adds that drill-down. It changes no figure and switches no reader.
// ---------------------------------------------------------------------------
function revrecon_candidate_ids() {
    $all = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };
    $ids = [];
    foreach ($all("SELECT id FROM jobs WHERE COALESCE(invoice_amount,0) <> 0 OR COALESCE(invoice_raised,0)=1") as $r) $ids[(int)$r['id']] = 1;
    foreach ($all("SELECT DISTINCT job_id FROM invoice_lines WHERE COALESCE(job_id,0) > 0") as $r) $ids[(int)$r['job_id']] = 1;
    return array_keys($ids);
}

// Health summary across all candidate jobs: how many reconcile vs diverge, the
// legacy-only / ledger-only splits, and the two running totals. `green` is true
// when nothing diverges — the signal that readers could safely be switched (§28).
function revrecon_summary($limit = 100000) {
    $s = ['candidates' => 0, 'reconciled' => 0, 'diverging' => 0, 'legacy_only' => 0, 'ledger_only' => 0,
          'legacy_total' => 0.0, 'ledger_net_total' => 0.0];
    $n = 0;
    foreach (revrecon_candidate_ids() as $jid) {
        if (++$n > $limit) break;
        $rc = revrecon_job($jid);
        $s['candidates']++;
        $s['legacy_total']     += $rc['legacy'];
        $s['ledger_net_total'] += $rc['ledger_net'];
        if ($rc['diverges']) $s['diverging']++; else $s['reconciled']++;
        if ($rc['legacy_only']) $s['legacy_only']++;
        if ($rc['ledger_only']) $s['ledger_only']++;
    }
    $s['legacy_total']     = round($s['legacy_total'], 2);
    $s['ledger_net_total'] = round($s['ledger_net_total'], 2);
    $s['green'] = ($s['diverging'] === 0);
    return $s;
}

// The diverging jobs, enriched with a code / client / plain-language reason, for
// the worklist screen. Read-only.
function revrecon_list($limit = 200) {
    $rows = revrecon_scan($limit);
    foreach ($rows as &$r) {
        $j = null;
        try {
            $j = ops_one("SELECT j.job_code, COALESCE(NULLIF(bp.display_name,''), bp.legal_name) client_name
                          FROM jobs j LEFT JOIN calls c ON c.id=j.call_id
                          LEFT JOIN business_partners bp ON bp.id=c.client_id WHERE j.id=?", [(int)$r['job_id']]);
        } catch (Throwable $e) {}
        $r['job_code']    = (string)($j['job_code'] ?? ('#' . $r['job_id']));
        $r['client_name'] = (string)($j['client_name'] ?? '');
        $r['reason'] = $r['legacy_only'] ? 'Legacy figure recorded, but no ledger invoice exists'
                     : ($r['ledger_only'] ? 'Invoiced in the ledger, but the legacy figure is blank'
                     : 'Legacy figure matches neither the net nor the gross ledger total');
    }
    unset($r);
    return $rows;
}

// The read-only worklist screen. Gated to finance / figure-holders.
function ops_revrecon($method) {
    ops_require((function_exists('can_see_salary') && can_see_salary())
        || (function_exists('can') && (can('finance.reconcile') || can('data.revenue'))) || is_master(),
        'You cannot open the revenue reconciliation.');
    view('ops/revenue_reconciliation', [
        'summary' => revrecon_summary(),
        'rows'    => revrecon_list(200),
        'tol'     => revrecon_tolerance(),
    ]);
    return true;
}
