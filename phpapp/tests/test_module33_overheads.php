<?php
// Module 33 — Overheads. Reconcile the actual office overhead pool (office_expenses) against the
// overhead RECOVERED through the per-job oh% (the one canonical engine, job_profit) — the
// estimate-vs-actual pattern, one level up. Read-only; recovered is salary-gated. First coverage
// of the overhead engine.
t_section('Module 33 — overhead recovery reconciliation');

$lib = file_get_contents(__DIR__ . '/../lib/costing.php');

t_ok(function_exists('overhead_recovery'), 'overhead_recovery() exists');
t_ok(function_exists('office_expense_total'), 'office_expense_total() (the pool) exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$savedUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('costing_migrate')) costing_migrate();
    $pdo = db();

    $pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('Cost Office', 1)")->execute();
    $oid = (int)$pdo->lastInsertId();
    // The overhead pool actually entered for the month.
    $pdo->prepare("INSERT INTO office_expenses (office_id, yr, mon, head_id, amount) VALUES (?, 2026, 7, 1, 10000)")->execute([$oid]);
    $pdo->prepare("INSERT INTO office_expenses (office_id, yr, mon, head_id, amount) VALUES (?, 2026, 7, 2, 5000)")->execute([$oid]);

    // A master, so cost figures (salary-derived) are visible.
    $pdo->prepare("INSERT INTO users (username, role, is_superuser, is_active) VALUES ('ovhmaster','MASTER_ADMIN',1,1)")->execute();
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); if (function_exists('ua')) ua(true);

    $r = overhead_recovery($oid, 2026, 7);
    t_eq((int)round($r['pool']), 15000, 'the pool is the office_expenses total for the month');
    t_ok($r['salary_visible'] === true, 'a cost-permitted viewer sees the recovered figure');
    t_ok($r['recovered'] !== null, 'the recovered overhead is computed (not withheld) for this viewer');
    t_eq((int)round($r['variance']), (int)round($r['recovered'] - $r['pool']), 'variance = recovered − pool (pure subtraction, no new formula)');
    t_ok($r['recovered'] <= 0.01, 'with no jobs in the month, nothing is recovered (under-recovered by the whole pool)');

    // A job in the month is counted.
    $pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, inspection_start_date) VALUES ('J-OVH', ?, '2026-07-10')")->execute([$oid]);
    $r2 = overhead_recovery($oid, 2026, 7);
    t_ok((int)$r2['jobs'] === 1, 'a job whose work fell in the month is included in the recovery calc');

    // A job in a different month is not counted.
    $r3 = overhead_recovery($oid, 2026, 8);
    t_ok((int)$r3['jobs'] === 0 && (int)round($r3['pool']) === 0, 'a different month has its own pool and jobs');

    // Salary gating: a guest with no cost permission gets the pool but not the recovered figure.
    unset($_SESSION['uid']); current_user(true); if (function_exists('ua')) ua(true);
    $rg = overhead_recovery($oid, 2026, 7);
    t_ok($rg['recovered'] === null && $rg['variance'] === null, 'a viewer without cost access never sees the salary-derived recovered figure');
    t_eq((int)round($rg['pool']), 15000, 'the office overhead pool (not salary-derived) is still shown');
} finally {
    if ($savedUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $savedUid;
    current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- preservation ----
t_ok(strpos($lib, 'job_profit') !== false, 'overhead recovery reuses the canonical job_profit engine');
t_ok(!preg_match('/function overhead_recovery[\s\S]{0,900}salary_ctc\s*\//', $lib), 'it invents no second cost formula (no salary maths of its own)');
$view = file_get_contents(__DIR__ . '/../views/ops/cost_run.php');
t_ok(strpos($view, 'Overhead recovery') !== false, 'the cost-run screen shows the overhead-recovery panel');
