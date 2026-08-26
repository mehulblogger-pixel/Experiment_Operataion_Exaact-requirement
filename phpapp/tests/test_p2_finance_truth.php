<?php
// Phase 2 §28 — the financial-truth switch. OFF (default) leaves every dashboard on its historical
// partial formula, byte-identical to before. ON makes the MIS dashboard, the SBU-P&L contract table
// and the owner/boss view all read the ONE canonical job_profit engine (frozen per §30), so a job
// shows the same profit everywhere. This proves both states over the seeded job population — no data
// is crafted; the invariant is asserted against whatever jobs exist.
t_section('Phase 2 §28 — unified financial truth (default OFF; converges MIS/SBU-PL/boss)');

t_ok(function_exists('finance_truth_unified'), 'the switch helper exists');
t_ok(setting_meta('finance_truth_unified') !== null, 'the switch is documented in the §47 governance registry');

// master session so can_see_salary() is true and the scope is ALL
$suid = null;
if (function_exists('db')) {
    db()->prepare("INSERT INTO users (username, first_name, last_name, is_superuser, is_active) VALUES ('p28_master','P28','Master',1,1)")->execute();
    $suid = (int)db()->lastInsertId();
}
$prevUid = $_SESSION['uid'] ?? null;
$savedSwitch = setting_get('finance_truth_unified', '');
try {
    if ($suid) { $_SESSION['uid'] = $suid; if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true); }

    t_ok(finance_truth_unified() === false, 'the switch defaults OFF (no key set)');

    // The whole job population MIS would sum, and the two expected totals.
    // A realistic window covering the seeded jobs — a 100-year span would build 1,200 month OR-clauses
    // in mis_shared_by_sbu and hit SQLite's expression-tree depth limit (unrelated to §28).
    $F = ['from'=>'2020-01-01','to'=>'2027-12-31','sbu'=>'','activity'=>'','office'=>0,'inspector'=>0,'ibo'=>0,'client'=>0];
    $jobs = mis_jobs($F);
    $canonProfit = 0.0; $canonCost = 0.0; $partialProfit = 0.0;
    foreach ($jobs as $j) {
        $p = job_profit($j, null);
        $canonProfit   += $p['profit'];
        $canonCost     += $p['cost'];
        $partialProfit += $p['credit'] - $p['labour'] - $p['expenses'] - $p['subcon'];
    }
    t_ok(count($jobs) > 0, 'there are seeded jobs to measure (' . count($jobs) . ')');

    // OFF — MIS shows the historical partial figure, unchanged.
    setting_set('finance_truth_unified', '');
    $off = mis_summary($F);
    t_eq(round((float)$off['profit'], 2), round($partialProfit, 2), 'OFF: MIS total profit is the historical partial formula');

    // ON — MIS shows the canonical engine's figure.
    setting_set('finance_truth_unified', '1');
    t_ok(finance_truth_unified() === true, 'the switch reads ON when set');
    $on = mis_summary($F);
    t_eq(round((float)$on['profit'], 2), round($canonProfit, 2), 'ON: MIS total profit equals the canonical engine');
    t_eq(round((float)$on['cost'], 2),   round($canonCost, 2),   'ON: MIS total cost equals the canonical engine');

    // The switch actually moves the number where the population drifts (not a vacuous test).
    if (round($canonProfit, 2) !== round($partialProfit, 2)) {
        t_ok(round((float)$off['profit'], 2) !== round((float)$on['profit'], 2), 'the switch changes the displayed profit (drift present)');
        t_ok((float)$on['profit'] <= (float)$off['profit'] + 0.01, 'unifying moves profit DOWN to the true figure (partial overstated)');
    }

    // Owner/boss view (§28 Option 2): ON, its margin equals the canonical engine over that boss's jobs.
    $bid = (int)(ops_val("SELECT boss_id FROM jobs WHERE COALESCE(boss_id,0)>0 LIMIT 1") ?: 0);
    if ($bid) {
        $bexp = 0.0;
        foreach (ops_all("SELECT * FROM jobs WHERE boss_id=?", [$bid]) as $j) $bexp += job_profit($j, null)['profit'];
        setting_set('finance_truth_unified', '1');
        $bp = boss_profit($bid);
        t_eq(round((float)$bp['margin'], 2), round($bexp, 2), 'ON: the owner/boss margin equals the canonical engine');
    }
} finally {
    setting_set('finance_truth_unified', (string)$savedSwitch);
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true);
}

// wiring
$mis = file_get_contents(__DIR__ . '/../lib/mis.php');
$cost = file_get_contents(__DIR__ . '/../lib/costing.php');
$view = file_get_contents(__DIR__ . '/../views/ops/profitability_list.php');
t_ok(strpos($mis, 'finance_truth_unified()') !== false, 'MIS is gated on the switch');
t_ok(strpos($cost, 'finance_truth_unified()') !== false, 'the SBU-P&L contract table is gated on the switch');
t_ok(strpos($view, 'before/after preview') !== false, 'the profitability screen shows the before/after preview');
