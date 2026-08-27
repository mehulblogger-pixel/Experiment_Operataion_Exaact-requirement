<?php
// Phase 2 §28 — the financial-truth switch. OFF (default) leaves every dashboard on its historical
// partial formula, byte-identical to before. ON makes the MIS dashboard, the SBU-P&L contract table
// and the owner/boss view all read the ONE canonical job_profit engine (frozen per §30), so a job
// shows the same profit everywhere. Self-contained: it seeds its own office + jobs so it proves the
// invariant in isolation as well as in the full suite.
t_section('Phase 2 §28 — unified financial truth (default OFF; converges MIS/SBU-PL/boss)');

t_ok(function_exists('finance_truth_unified'), 'the switch helper exists');
t_ok(setting_meta('finance_truth_unified') !== null, 'the switch is documented in the §47 governance registry');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$prevUid = $_SESSION['uid'] ?? null;
$savedSwitch = setting_get('finance_truth_unified', '');
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();
    // Master session so the scope is ALL and salary-derived cost is visible. Inserted inside the
    // transaction so it rolls back with everything else.
    $pdo->prepare("INSERT INTO users (username, is_superuser, is_active) VALUES ('p28_master',1,1)")->execute();
    $_SESSION['uid'] = (int)$pdo->lastInsertId();
    if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true);

    t_ok(finance_truth_unified() === false, 'the switch defaults OFF (no key set)');

    // Our own office + two closed jobs with a cost the partial formula drops (other_cost), so the two
    // bases genuinely differ and the switch has something to move.
    $pdo->prepare("INSERT INTO offices (name, code, is_active) VALUES ('P28 Office','P28',1)")->execute();
    $oid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, sbu, closed_flag, inspection_end_date, invoice_amount, invoice_value, other_cost, subcon_cost) VALUES ('P28-A',?,'NDT',1,'2026-05-20', 50000, 50000, 4000, 1000)")->execute([$oid]);
    $pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, sbu, closed_flag, inspection_end_date, invoice_amount, invoice_value, other_cost, subcon_cost) VALUES ('P28-B',?,'CIV',1,'2026-05-22', 30000, 30000, 2500, 0)")->execute([$oid]);

    // The population MIS sums (scoped to our office so the test is deterministic) and the two expected totals.
    $F = ['from'=>'2026-01-01','to'=>'2026-12-31','sbu'=>'','activity'=>'','office'=>$oid,'inspector'=>0,'ibo'=>0,'client'=>0];
    $jobs = mis_jobs($F);
    t_ok(count($jobs) === 2, 'our two seeded jobs are the measured population');
    $canonProfit = 0.0; $canonCost = 0.0; $partialProfit = 0.0;
    foreach ($jobs as $j) {
        $p = job_profit($j, $oid);
        $canonProfit   += $p['profit'];
        $canonCost     += $p['cost'];
        $partialProfit += $p['credit'] - $p['labour'] - $p['expenses'] - $p['subcon'];
    }
    t_ok(round($canonProfit, 2) !== round($partialProfit, 2), 'the two bases genuinely differ (other_cost is dropped by the partial formula)');

    // OFF — MIS shows the historical partial figure, unchanged. (The totals live under ['tot'].)
    setting_set('finance_truth_unified', '');
    $off = mis_summary($F)['tot'];
    t_eq(round((float)$off['profit'], 2), round($partialProfit, 2), 'OFF: MIS total profit is the historical partial formula');

    // ON — MIS shows the canonical engine's figure.
    setting_set('finance_truth_unified', '1');
    t_ok(finance_truth_unified() === true, 'the switch reads ON when set');
    $on = mis_summary($F)['tot'];
    t_eq(round((float)$on['profit'], 2), round($canonProfit, 2), 'ON: MIS total profit equals the canonical engine');
    t_eq(round((float)$on['cost'], 2),   round($canonCost, 2),   'ON: MIS total cost equals the canonical engine');
    t_ok((float)$on['profit'] < (float)$off['profit'], 'unifying moves profit DOWN to the true figure (partial overstated)');

    // Owner/boss view (§28 Option 2): ON, its margin equals the canonical engine over that boss's jobs.
    $pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, sbu, closed_flag, inspection_end_date, boss_id, invoice_amount, invoice_value, other_cost) VALUES ('P28-BOSS',?,'NDT',1,'2026-05-25', 777001, 20000, 20000, 1500)")->execute([$oid]);
    $bexp = 0.0;
    foreach (ops_all("SELECT * FROM jobs WHERE boss_id=777001") as $j) $bexp += job_profit($j, null)['profit'];
    setting_set('finance_truth_unified', '1');
    $bp = boss_profit(777001);
    t_eq(round((float)$bp['margin'], 2), round($bexp, 2), 'ON: the owner/boss margin equals the canonical engine');

    // SBU-PL contract table (§28 Option 1): ON, its per-line profit (revenue−labour−expenses−subcon)
    // sums to the canonical engine over the same office+month population.
    setting_set('finance_truth_unified', '1');
    $lines = costing_boss_lines($oid, 2026, 5);
    $lineProfit = 0.0;
    foreach ($lines as $l) $lineProfit += (float)$l['revenue'] - (float)$l['labour'] - (float)$l['expenses'] - (float)$l['subcon'];
    $cexp = 0.0;
    foreach (ops_all("SELECT * FROM jobs WHERE executing_office_id=? AND inspection_end_date LIKE '2026-05%'", [$oid]) as $j) $cexp += job_profit($j, $oid)['profit'];
    t_eq(round($lineProfit, 2), round($cexp, 2), 'ON: the SBU-P&L contract table sums to the canonical engine');
} finally {
    setting_set('finance_truth_unified', (string)$savedSwitch);
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}

// wiring
$mis = file_get_contents(__DIR__ . '/../lib/mis.php');
$cost = file_get_contents(__DIR__ . '/../lib/costing.php');
$view = file_get_contents(__DIR__ . '/../views/ops/profitability_list.php');
t_ok(strpos($mis, 'finance_truth_unified()') !== false, 'MIS is gated on the switch');
t_ok(strpos($cost, 'finance_truth_unified()') !== false, 'the SBU-P&L contract table is gated on the switch');
t_ok(strpos($view, 'before/after preview') !== false, 'the profitability screen shows the before/after preview');
