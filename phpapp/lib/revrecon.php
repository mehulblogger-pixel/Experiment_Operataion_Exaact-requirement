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

// ---------------------------------------------------------------------------
//  §28 — the revenue reader switch (P9). Mirrors finance_truth_unified() for the
//  cost side: one setting decides which figure the revenue readers show for a
//  job's invoiced amount, so the move onto the books ledger is a deliberate,
//  reversible step — never a table change, never a destroyed legacy figure.
//
//    'legacy'     — the per-job snapshot (jobs.invoice_amount). Pre-switch behaviour.
//    'reconciled' — (DEFAULT) the books-ledger net WHERE it agrees with the snapshot
//                   within tolerance, else the snapshot. Guaranteed to move no figure
//                   that has not been proven equal — the safe switch, so it can ship
//                   on by default without changing a single number on screen.
//    'ledger'     — the books-ledger net wherever the books carry the job, else the
//                   snapshot (a job not yet invoiced in the books keeps its snapshot,
//                   so revenue is never silently zeroed). The full switch — turn it on
//                   once the reconciliation worklist reads green.
// ---------------------------------------------------------------------------
function revenue_reader_modes() {
    return [
        'legacy'     => 'Legacy snapshot (pre-switch)',
        'reconciled' => 'Ledger where it reconciles (safe default)',
        'ledger'     => 'Books ledger (full switch)',
    ];
}
function revenue_reader_mode() {
    $m = strtolower((string)setting_get('revenue_reader_mode', 'reconciled'));
    return isset(revenue_reader_modes()[$m]) ? $m : 'reconciled';
}
function revenue_reader_set_mode($mode) {
    $mode = strtolower((string)$mode);
    if (!isset(revenue_reader_modes()[$mode])) return false;
    if (function_exists('setting_set')) setting_set('revenue_reader_mode', $mode);
    return true;
}

// The books-ledger net for one job (non-cancelled invoices). Cheap single read.
function revrecon_ledger_net($jobId) {
    try {
        $r = ops_one("SELECT COALESCE(SUM(il.amount),0) net FROM invoice_lines il
                      JOIN invoices i ON i.id = il.invoice_id
                      WHERE il.job_id=? AND COALESCE(i.status,'') <> 'CANCELLED'", [(int)$jobId]);
    } catch (Throwable $e) { return 0.0; }
    return (float)($r['net'] ?? 0);
}

// A bulk {job_id => ledger_net} map in ONE query, for readers that loop many jobs.
function revrecon_ledger_net_map(array $jobIds) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $jobIds))));
    $out = array_fill_keys($ids, 0.0);
    if (!$ids) return $out;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    try {
        foreach (ops_all("SELECT il.job_id, COALESCE(SUM(il.amount),0) net FROM invoice_lines il
                          JOIN invoices i ON i.id = il.invoice_id
                          WHERE il.job_id IN ($ph) AND COALESCE(i.status,'') <> 'CANCELLED'
                          GROUP BY il.job_id", $ids) ?: [] as $r)
            $out[(int)$r['job_id']] = (float)$r['net'];
    } catch (Throwable $e) {}
    return $out;
}

// The canonical invoiced-revenue figure for a job, honouring revenue_reader_mode().
// $ledgerNet may be passed precomputed (bulk readers, via revrecon_ledger_net_map)
// to avoid a per-row query; pass null to read it on demand. NEVER destroys the
// legacy figure — it only chooses which of the two agreeing sources to show.
function job_invoiced_amount($job, $ledgerNet = null) {
    $legacy = (float)($job['invoice_amount'] ?? 0);
    $mode = revenue_reader_mode();
    if ($mode === 'legacy') return $legacy;
    if ($ledgerNet === null) $ledgerNet = revrecon_ledger_net((int)($job['id'] ?? 0));
    $ledgerNet = (float)$ledgerNet;
    if ($mode === 'ledger') return $ledgerNet != 0.0 ? $ledgerNet : $legacy; // books where they carry it
    // 'reconciled' (default): trust the ledger only where it agrees with the snapshot.
    $tol = function_exists('revrecon_tolerance') ? revrecon_tolerance() : 1.0;
    return (abs($legacy - $ledgerNet) <= $tol) ? $ledgerNet : $legacy;
}

// Mode-aware invoiced total PER CALL, for the contract-360 and order-360 readers
// whose legacy form was a `SUM(invoice_amount) WHERE invoice_raised=1` sub-select.
// It preserves that same invoice_raised gate, so under 'legacy'/'reconciled' it
// reproduces the old figure and under 'ledger' it moves onto the books. One pair of
// queries for the whole set → {call_id => invoiced}.
function call_invoiced_map(array $callIds) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $callIds))));
    $out = array_fill_keys($ids, 0.0);
    if (!$ids) return $out;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    try {
        $jobs = ops_all("SELECT id, call_id, invoice_amount FROM jobs
                         WHERE call_id IN ($ph) AND COALESCE(invoice_raised,0)=1", $ids) ?: [];
    } catch (Throwable $e) { return $out; }
    $ledger = revrecon_ledger_net_map(array_map(fn($j) => (int)$j['id'], $jobs));
    foreach ($jobs as $j)
        $out[(int)$j['call_id']] = ($out[(int)$j['call_id']] ?? 0) + job_invoiced_amount($j, $ledger[(int)$j['id']] ?? 0);
    return $out;
}

// The read-only worklist screen (+ the §28 mode control). Gated to finance / figure-holders.
function ops_revrecon($method) {
    ops_require((function_exists('can_see_salary') && can_see_salary())
        || (function_exists('can') && (can('finance.reconcile') || can('data.revenue'))) || is_master(),
        'You cannot open the revenue reconciliation.');
    // Setting the reader mode is a deliberate finance action; only when green may it
    // safely go to full 'ledger', but the control never blocks — the mode itself is safe.
    if ($method === 'POST' && isset($_POST['revenue_reader_mode'])) {
        if (revenue_reader_set_mode($_POST['revenue_reader_mode']))
            flash('Revenue reader mode set to “' . (revenue_reader_modes()[revenue_reader_mode()] ?? revenue_reader_mode()) . '”.');
        else flash('That is not a valid revenue reader mode.', 'error');
        redirect('/revenue-reconciliation'); return true;
    }
    view('ops/revenue_reconciliation', [
        'summary' => revrecon_summary(),
        'rows'    => revrecon_list(200),
        'tol'     => revrecon_tolerance(),
        'mode'    => revenue_reader_mode(),
        'modes'   => revenue_reader_modes(),
    ]);
    return true;
}
