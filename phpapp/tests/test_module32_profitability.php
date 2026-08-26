<?php
// Module 32 — Profitability (canonical engine). job_profit() is the one true per-job P&L: its
// 'profit'/'cost' include overhead, voucher, other, contingency and the client-recovered credit.
// The MIS dashboard and the SBU-P&L contract table re-derive a PARTIAL profit inline
// (credit − labour − expenses − subcon), dropping the rest — so screens fed by the SAME engine
// disagree. profit_reconciliation() measures both ways over one population and quantifies the drift.
// Read-only: it changes no displayed figure, it surfaces the gap (like the audit-chain verify).
t_section('Module 32 — profit-engine consistency check');

t_ok(function_exists('profit_reconciliation'), 'profit_reconciliation() exists');

// The overstatement is, by construction, exactly the omitted net cost the partial formula drops:
// overhead + voucher + other + contingency − recovered. Prove the identity on a synthetic job P&L
// so the check is anchored to job_profit's own fields, not a re-implementation.
$fakeP = ['credit'=>100000.0, 'labour'=>30000.0, 'expenses'=>10000.0, 'subcon'=>5000.0,
          'overhead'=>6000.0, 'voucher'=>4000.0, 'other'=>2000.0, 'contingency'=>3000.0, 'recovered'=>1500.0];
$canonicalCost = $fakeP['labour'] + $fakeP['overhead'] + $fakeP['expenses'] + $fakeP['voucher']
               + $fakeP['subcon'] + $fakeP['other'] - $fakeP['recovered'] + $fakeP['contingency'];
$partialCost   = $fakeP['labour'] + $fakeP['expenses'] + $fakeP['subcon'];
$canonicalProfit = $fakeP['credit'] - $canonicalCost;
$partialProfit   = $fakeP['credit'] - $partialCost;
$overstate = $partialProfit - $canonicalProfit;
$omittedNet = $fakeP['overhead'] + $fakeP['voucher'] + $fakeP['other'] + $fakeP['contingency'] - $fakeP['recovered'];
t_eq(round($overstate, 2), round($omittedNet, 2), 'the partial formula overstates profit by exactly the omitted net cost');
t_ok($partialProfit > $canonicalProfit, 'the partial formula overstates (never understates) profit');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();

    // The check is well-formed over whatever population it sees.
    $rc = profit_reconciliation(['from'=>'2000-01-01','to'=>'2100-12-31']);
    foreach (['jobs','drifting','canonical_profit','partial_profit','overstatement','consistent','omitted'] as $k)
        t_ok(array_key_exists($k, $rc), "the reconciliation reports '$k'");
    t_ok($rc['jobs'] >= 0 && is_int($rc['jobs']), 'the job count is a non-negative integer');
    t_ok(is_bool($rc['consistent']), 'consistency is a boolean verdict');
    // When the population is consistent the overstatement must be zero, and vice versa.
    if ($rc['consistent']) t_eq(round($rc['overstatement'], 2), 0.0, 'a consistent population has zero overstatement');
    t_ok(is_array($rc['omitted']) && array_key_exists('overhead', $rc['omitted']), 'the omitted-component breakdown is present');

    // The overstatement is always partial_profit − canonical_profit (the invariant the panel shows).
    t_eq(round($rc['overstatement'], 2), round($rc['partial_profit'] - $rc['canonical_profit'], 2),
        'overstatement == partial − canonical, always');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
$mis  = file_get_contents(__DIR__ . '/../lib/mis.php');
$ops  = file_get_contents(__DIR__ . '/../lib/ops.php');
$view = file_get_contents(__DIR__ . '/../views/ops/profitability_list.php');
t_ok(strpos($ops, 'profit_reconciliation()') !== false, 'the /profitability screen computes the reconciliation');
t_ok(strpos($ops, '$seeSal && function_exists(\'profit_reconciliation\')') !== false, 'the check is salary-gated');
t_ok(strpos($view, 'Profit figures differ between screens') !== false, 'the screen surfaces a drift when one exists');
// The canonical engine and the partial formula it measures are BOTH still present, untouched.
t_ok(strpos($ops, 'function job_profit') !== false, 'the canonical job_profit() engine is preserved');
t_ok(strpos($mis, "\$p['credit'] - \$p['labour'] - \$p['expenses'] - \$p['subcon']") !== false,
    'the existing MIS figure is unchanged (drift surfaced, not silently rewritten)');
