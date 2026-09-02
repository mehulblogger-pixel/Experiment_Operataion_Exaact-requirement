<?php
// ===========================================================================
//  Cost reconciliation — the cost-side twin of lib/revrecon.php  (Revamp P8).
//
//  A job's sub-contractor cost is written in TWO places that are meant to agree:
//
//    1. the LEGACY figure typed on the job itself — jobs.subcon_cost — which the
//       per-job P&L (lib/ops.php) and the order roll-up read directly; and
//    2. the LEDGER figure — the SUBCON row(s) that a committed monthly cost run
//       (costing_run, lib/costing.php) writes into cost_allocations for that job.
//
//  costing_run DERIVES the ledger row FROM subcon_cost, so at the moment a run
//  commits they are equal. They drift afterwards: the job's subcon_cost is edited
//  once the run is closed (legacy moves, ledger stale), or a job carries a
//  subcon_cost but no run was ever committed for its month (legacy-only), or a
//  ledger SUBCON row survives after the job's figure was cleared (ledger-only).
//
//  This is a read-only DETECTOR. Exactly like revrecon it changes no figure and
//  switches no reader — it only surfaces WHERE the two costs disagree and by how
//  much, so finance can drive the drift to green. Only the sub-contractor cost is
//  reconciled here: it is the one job-cost figure that is genuinely written on
//  both sides. Salary, idle time and office overheads are computed into the ledger
//  and were never typed on the job, so there is no second copy to disagree with —
//  reconciling them would invent drift, not find it.
// ===========================================================================

// Same tolerance idea as revenue: rounding of a rupee or two is not a real drift.
function costrecon_tolerance() { return (float)setting_get('costrecon_tolerance', 1); }

// Reconcile one job's sub-contractor cost: the legacy figure on the job vs the
// SUBCON total committed to the cost ledger for it. Read-only.
function costrecon_job($jobId) {
    $jobId = (int)$jobId;
    $one = function ($sql, $a = []) { try { return ops_one($sql, $a); } catch (Throwable $e) { return null; } };
    $j = $one("SELECT id, COALESCE(subcon_cost,0) subcon_cost FROM jobs WHERE id=?", [$jobId]);
    $legacy = (float)($j['subcon_cost'] ?? 0);
    // Ledger total for this job — the sub-contractor cost a committed cost run
    // actually allocated to it.
    $led = $one("SELECT COALESCE(SUM(amount),0) ledger
                   FROM cost_allocations WHERE job_id=? AND source_kind='SUBCON'", [$jobId]);
    $ledger = (float)($led['ledger'] ?? 0);
    $tol = costrecon_tolerance();
    $match = abs($legacy - $ledger) <= $tol;
    // Only meaningful once there is something on at least one side.
    $has = ($legacy > 0) || ($ledger > 0);
    return [
        'job_id' => $jobId, 'legacy' => round($legacy, 2), 'ledger' => round($ledger, 2),
        'diverges'    => $has && !$match,
        'legacy_only' => $legacy > 0 && $ledger == 0,
        'ledger_only' => $legacy == 0 && $ledger > 0,
    ];
}

// The set of jobs whose legacy sub-contractor figure reconciles against the cost
// ledger — either side may carry a figure. Read-only, capped.
function costrecon_scan($limit = 100) {
    $all = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };
    $ids = [];
    foreach ($all("SELECT id FROM jobs WHERE COALESCE(subcon_cost,0) <> 0") as $r) $ids[(int)$r['id']] = 1;
    foreach ($all("SELECT DISTINCT job_id FROM cost_allocations WHERE source_kind='SUBCON' AND COALESCE(job_id,0) > 0") as $r) $ids[(int)$r['job_id']] = 1;
    $out = [];
    foreach (array_keys($ids) as $jid) {
        $rc = costrecon_job($jid);
        if ($rc['diverges']) { $out[] = $rc; if (count($out) >= $limit) break; }
    }
    return $out;
}

// How many jobs diverge (for a health/advisory count). Reuses the scan with a high cap.
function costrecon_count() { return count(costrecon_scan(100000)); }

// The full candidate set — every job that carries a sub-contractor figure on
// either side.
function costrecon_candidate_ids() {
    $all = function ($sql, $a = []) { try { return ops_all($sql, $a) ?: []; } catch (Throwable $e) { return []; } };
    $ids = [];
    foreach ($all("SELECT id FROM jobs WHERE COALESCE(subcon_cost,0) <> 0") as $r) $ids[(int)$r['id']] = 1;
    foreach ($all("SELECT DISTINCT job_id FROM cost_allocations WHERE source_kind='SUBCON' AND COALESCE(job_id,0) > 0") as $r) $ids[(int)$r['job_id']] = 1;
    return array_keys($ids);
}

// Health summary across all candidate jobs. `green` is true when nothing diverges
// — the signal that the two cost copies agree everywhere and a reader could safely
// be pointed at either.
function costrecon_summary($limit = 100000) {
    $s = ['candidates' => 0, 'reconciled' => 0, 'diverging' => 0, 'legacy_only' => 0, 'ledger_only' => 0,
          'legacy_total' => 0.0, 'ledger_total' => 0.0];
    $n = 0;
    foreach (costrecon_candidate_ids() as $jid) {
        if (++$n > $limit) break;
        $rc = costrecon_job($jid);
        $s['candidates']++;
        $s['legacy_total'] += $rc['legacy'];
        $s['ledger_total'] += $rc['ledger'];
        if ($rc['diverges']) $s['diverging']++; else $s['reconciled']++;
        if ($rc['legacy_only']) $s['legacy_only']++;
        if ($rc['ledger_only']) $s['ledger_only']++;
    }
    $s['legacy_total'] = round($s['legacy_total'], 2);
    $s['ledger_total'] = round($s['ledger_total'], 2);
    $s['green'] = ($s['diverging'] === 0);
    return $s;
}

// The diverging jobs, enriched with a code / client / plain-language reason, for
// the worklist screen. Read-only.
function costrecon_list($limit = 200) {
    $rows = costrecon_scan($limit);
    foreach ($rows as &$r) {
        $j = null;
        try {
            $j = ops_one("SELECT j.job_code, COALESCE(NULLIF(bp.display_name,''), bp.legal_name) client_name
                          FROM jobs j LEFT JOIN calls c ON c.id=j.call_id
                          LEFT JOIN business_partners bp ON bp.id=c.client_id WHERE j.id=?", [(int)$r['job_id']]);
        } catch (Throwable $e) {}
        $r['job_code']    = (string)($j['job_code'] ?? ('#' . $r['job_id']));
        $r['client_name'] = (string)($j['client_name'] ?? '');
        $r['reason'] = $r['legacy_only'] ? 'Sub-contractor cost on the job, but no cost run has committed it to the ledger'
                     : ($r['ledger_only'] ? 'Committed to the cost ledger, but the job\'s figure is now blank'
                     : 'The job\'s figure and the committed ledger figure disagree — the job was edited after its cost run');
    }
    unset($r);
    return $rows;
}

// The read-only worklist screen. Gated the same way as revenue reconciliation —
// only figure-holders (finance / salary-visible / master) may open it.
// ---------------------------------------------------------------------------
//  The cost reader switch (P10) — the exact twin of P9's revenue_reader_mode().
//  One setting decides which figure the cost readers use for a job's
//  sub-contractor cost as the committed cost ledger becomes the source of truth,
//  so the move is deliberate and reversible and the legacy field is never destroyed.
//
//    'legacy'     — the per-job field (jobs.subcon_cost). Pre-switch behaviour.
//    'reconciled' — (DEFAULT) the committed cost-ledger figure WHERE it agrees with
//                   the field within tolerance, else the field. Moves no unproven
//                   figure, so it ships on without changing a number on screen.
//    'ledger'     — the committed cost-run figure wherever a run has committed the
//                   job, else the field (a job never cost-run keeps its field, so
//                   cost is never silently zeroed). The full switch — turn on once
//                   the cost reconciliation reads green.
// ---------------------------------------------------------------------------
function cost_reader_modes() {
    return [
        'legacy'     => 'Legacy job field (pre-switch)',
        'reconciled' => 'Ledger where it reconciles (safe default)',
        'ledger'     => 'Committed cost ledger (full switch)',
    ];
}
function cost_reader_mode() {
    $m = strtolower((string)setting_get('cost_reader_mode', 'reconciled'));
    return isset(cost_reader_modes()[$m]) ? $m : 'reconciled';
}
function cost_reader_set_mode($mode) {
    $mode = strtolower((string)$mode);
    if (!isset(cost_reader_modes()[$mode])) return false;
    if (function_exists('setting_set')) setting_set('cost_reader_mode', $mode);
    return true;
}

// The committed SUBCON cost ledger for EVERY job, in one query, cached for the
// request (pass $rebuild=true after the ledger changes — used by tests). job_profit
// is called per-job in tight loops, so this keeps the per-job reader O(1) with a
// single query for the whole page rather than one query per job.
function costrecon_ledger_all($rebuild = false) {
    static $map = null;
    if ($map !== null && !$rebuild) return $map;
    $map = [];
    try {
        foreach (ops_all("SELECT job_id, COALESCE(SUM(amount),0) c FROM cost_allocations
                          WHERE source_kind='SUBCON' AND COALESCE(job_id,0) > 0 GROUP BY job_id") ?: [] as $r)
            $map[(int)$r['job_id']] = (float)$r['c'];
    } catch (Throwable $e) {}
    return $map;
}
function costrecon_ledger($jobId) { $m = costrecon_ledger_all(); return (float)($m[(int)$jobId] ?? 0); }

// The canonical sub-contractor-cost figure for a job, honouring cost_reader_mode().
// $ledger may be passed precomputed; else it is read from the request-cached map.
// NEVER destroys the legacy field — it only chooses which agreeing source to show.
function job_subcon_cost($job, $ledger = null) {
    $legacy = (float)($job['subcon_cost'] ?? 0);
    $mode = cost_reader_mode();
    if ($mode === 'legacy') return $legacy;
    if ($ledger === null) $ledger = costrecon_ledger((int)($job['id'] ?? 0));
    $ledger = (float)$ledger;
    if ($mode === 'ledger') return $ledger != 0.0 ? $ledger : $legacy; // committed run where it exists
    // 'reconciled' (default): trust the ledger only where it agrees with the field.
    $tol = function_exists('costrecon_tolerance') ? costrecon_tolerance() : 1.0;
    return (abs($legacy - $ledger) <= $tol) ? $ledger : $legacy;
}

function ops_costrecon($method) {
    ops_require((function_exists('can_see_salary') && can_see_salary())
        || (function_exists('can') && (can('finance.reconcile') || can('data.revenue'))) || is_master(),
        'You cannot open the cost reconciliation.');
    if ($method === 'POST' && isset($_POST['cost_reader_mode'])) {
        if (cost_reader_set_mode($_POST['cost_reader_mode']))
            flash('Cost reader mode set to “' . (cost_reader_modes()[cost_reader_mode()] ?? cost_reader_mode()) . '”.');
        else flash('That is not a valid cost reader mode.', 'error');
        redirect('/cost-reconciliation'); return true;
    }
    view('ops/cost_reconciliation', [
        'summary' => costrecon_summary(),
        'rows'    => costrecon_list(200),
        'tol'     => costrecon_tolerance(),
        'mode'    => cost_reader_mode(),
        'modes'   => cost_reader_modes(),
    ]);
    return true;
}
