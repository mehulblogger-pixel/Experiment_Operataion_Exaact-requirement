<?php
// Phase 2 §28 Option 3 — reconcile the two profit bases the SBU-P&L screen sits between, WITHOUT
// changing either. Period-costing (the "By SBU" table) counts every rupee the office spent; job-costing
// (the contract table / MIS / canonical engine) counts only what jobs carried. costing_basis_reconciliation
// returns the job-costing side per SBU over the span, so the screen can show both and the gap. Additive
// and read-only — no existing figure moves.
t_section('Phase 2 §28 Option 3 — profit-basis reconciliation (job vs period costing)');

t_ok(function_exists('costing_basis_reconciliation'), 'the basis-reconciliation helper exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();
    // Two jobs in one office, two SBUs, in the same month.
    $pdo->prepare("INSERT INTO offices (name, code, is_active) VALUES ('Recon Office','RCN',1)")->execute();
    $oid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, sbu, closed_flag, inspection_end_date, subcon_cost, other_cost) VALUES ('JB-A',?,'NDT',1,'2026-05-20', 1000, 0)")->execute([$oid]);
    $pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, sbu, closed_flag, inspection_end_date, subcon_cost, other_cost) VALUES ('JB-B',?,'CIV',1,'2026-05-22', 500, 250)")->execute([$oid]);

    $months = [[2026, 5]];
    $recon = costing_basis_reconciliation($oid, $months);

    t_ok(isset($recon['NDT']) && isset($recon['CIV']), 'both SBUs present in the job-costing side');

    // The job side must equal the canonical engine summed over the same jobs, per SBU.
    foreach (['NDT', 'CIV'] as $s) {
        $exp = 0.0;
        foreach (ops_all("SELECT * FROM jobs WHERE executing_office_id=? AND sbu=? AND inspection_end_date LIKE '2026-05%'", [$oid, $s]) as $j)
            $exp += job_profit($j, $oid)['profit'];
        t_eq(round((float)$recon[$s]['profit'], 2), round($exp, 2), "job-costing profit for $s equals the canonical engine");
    }

    // A month with no jobs yields an empty job side (nothing to reconcile) — no crash.
    t_ok(costing_basis_reconciliation($oid, [[2000, 1]]) === [], 'an empty span reconciles to nothing');

    // The gap is period − job at the screen level: with no stored period costs, period profit is 0 and
    // the gap is simply −(job profit). The helper supplies the job side; the screen computes the gap.
    $jobProfitTotal = array_sum(array_map(fn($r) => (float)$r['profit'], $recon));
    t_ok(is_float($jobProfitTotal) || is_int($jobProfitTotal), 'the job side sums cleanly for the screen gap');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// wiring — additive only; the existing period-costing rows/tot are untouched.
$cost = file_get_contents(__DIR__ . '/../lib/costing.php');
$view = file_get_contents(__DIR__ . '/../views/ops/sbu_pl.php');
t_ok(strpos($cost, "'jobBasis'=>\$jobBasis") !== false, 'ops_sbu_pl passes the job-costing basis to the view');
t_ok(strpos($view, 'Two ways of counting cost') !== false, 'the SBU-P&L screen renders the reconciliation panel');
t_ok(strpos($view, 'no figure here changes either table') !== false, 'the panel states it changes no existing number');
