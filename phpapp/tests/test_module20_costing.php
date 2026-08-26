<?php
// Module 20 — Project costing. Estimate-vs-actual reconciliation: link each bid to its contract
// and compare the pre-award costing (pc_rollup / exp_*) against the delivered margin (boss_profit,
// the one canonical actuals engine). Plus the first unit coverage of the estimate math.
t_section('Module 20 — project costing estimate-vs-actual');

$lib = file_get_contents(__DIR__ . '/../lib/projcosting.php');

t_ok(function_exists('pc_line_calc'), 'pc_line_calc() exists');
t_ok(function_exists('pc_for_boss'), 'pc_for_boss() exists');
t_ok(function_exists('pc_estimate_vs_actual'), 'pc_estimate_vs_actual() exists');

// ---- the estimate math (pure; no DB) ----
// direct 100000, overhead 10% → loaded 110000; target margin 20% → rate 137500;
// revenue 137500, cost 110000, profit 27500, margin 20%.
$header = ['overhead_pct' => 10, 'insurance_pct' => 0, 'baddebt_pct' => 0, 'contingency_pct' => 0,
           'negotiation_pct' => 0, 'load_on' => 'COST', 'basis' => 'LONG'];
$line = ['heads' => json_encode(['PERSONNEL' => 100000]), 'target_margin' => 20, 'boq_qty' => 1, 'oneoff' => 0];
$c = pc_line_calc($line, $header);
t_eq((int)round($c['loaded']), 110000, 'loaded cost = direct + overhead');
t_eq((int)round($c['revenue']), 137500, 'revenue derives the rate from the target margin');
t_eq((int)round($c['cost']), 110000, 'cost is the loaded cost × quantity');
t_eq((int)round($c['profit']), 27500, 'profit = revenue − cost');
t_eq((int)round($c['margin']), 20, 'the achieved margin equals the target margin');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('pc_migrate')) pc_migrate();
    if (function_exists('crm_ensure_schema')) crm_ensure_schema();
    $pdo = db();

    // A client, a contract (boss), a quotation carrying that contract number, and a costing on it.
    $pdo->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, status) VALUES ('Bid Co','Bid Co Ltd',1,'ACTIVE')")->execute();
    $cli = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO boss_numbers (client_id, boss_number, status) VALUES (?, 'CN-2001', 'ACTIVE')")->execute([$cli]);
    $bossId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, contract_number, client_id) VALUES ('Q-1',0,1,'ACCEPTED','CN-2001',?)")->execute([$cli]);
    $qid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO project_costings (code, title, quote_id, status, exp_revenue, exp_cost, exp_profit, updated_at) VALUES ('PC-1','Bid', ?, 'APPROVED', 137500, 110000, 27500, ?)")
        ->execute([$qid, date('c')]);

    $pc = pc_for_boss($bossId);
    t_ok($pc !== null && (int)$pc['quote_id'] === $qid, 'pc_for_boss links the contract to its bid costing via the contract number');

    // A boss with no matching quote/costing → null.
    $pdo->prepare("INSERT INTO boss_numbers (client_id, boss_number, status) VALUES (?, 'CN-9999', 'ACTIVE')")->execute([$cli]);
    $bossNone = (int)$pdo->lastInsertId();
    t_ok(pc_for_boss($bossNone) === null, 'a contract with no linked bid costing returns null (no fabricated estimate)');

    // estimate-vs-actual: estimate from exp_*, actual from boss_profit (0 jobs → 0 actual revenue).
    $eva = pc_estimate_vs_actual($bossId);
    t_ok($eva !== null, 'the reconciliation is produced when a bid is linked');
    t_eq((int)round($eva['est']['revenue']), 137500, 'the estimate side reads the bid rollup, unchanged');
    t_eq((int)round($eva['est']['profit']), 27500, 'the estimate profit is the bid profit');
    t_ok(array_key_exists('act', $eva) && array_key_exists('var', $eva), 'the actual side + variance are present');
    t_ok(pc_estimate_vs_actual($bossNone) === null, 'no reconciliation when no bid is linked');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- preservation ----
t_ok(strpos($lib, 'function boss_profit') === false, 'project costing invents no actuals engine — it reuses boss_profit');
t_ok(strpos($lib, 'boss_profit((int)$bossId)') !== false, 'the reconciliation calls the one canonical actuals engine');
t_ok(!preg_match("/can\('data\.(profitability|salary)'\)/", $lib) || strpos($lib, 'pc_can') !== false,
     'Module 20 introduces no new permission constant');
$view = file_get_contents(__DIR__ . '/../views/ops/profitability_detail.php');
t_ok(strpos($view, 'Estimated vs actual') !== false, 'the profitability detail shows the estimate-vs-actual panel');
t_ok(strpos($view, '$seeSal ? fmoney_short($eva[\'act\'][\'cost\'])') !== false, 'actual cost/profit stay behind can_see_salary, like the rest of the screen');
