<?php
// Module 29 — Data Control / Governance. The integrity self-test now runs on a daily cron (writing
// the §7.11 pass/fail evidence that was starved), the money tables gained orphan detectors, and the
// sealed audit chain is verified where the trail is read. First coverage of the integrity registry.
t_section('Module 29 — data-integrity self-test + orphan detectors');

$cron  = file_get_contents(__DIR__ . '/../cron.php');
$auditV = file_get_contents(__DIR__ . '/../views/ops/idems/audit.php');

t_ok(function_exists('integrity_checks'), 'integrity_checks() exists');
t_ok(function_exists('integrity_run'), 'integrity_run() exists');

// The two new money orphan detectors are in the registry.
$keys = array_column(integrity_checks(), 'key');
t_ok(in_array('ventry_voucher', $keys, true), 'a voucher-line → voucher orphan detector was added');
t_ok(in_array('invline_invoice', $keys, true), 'an invoice-line → invoice orphan detector was added');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('books_migrate')) books_migrate();
    $pdo = db();

    $byKey = function () { $m = []; foreach (integrity_checks() as $c) $m[$c['key']] = $c; return $m; };

    // Clean baseline for our two checks (whatever the seed holds, orphan count is what matters).
    $before = $byKey();
    $ventryBefore = (int)($before['ventry_voucher']['found'] ?? 0);
    $invBefore    = (int)($before['invline_invoice']['found'] ?? 0);

    // Introduce one orphan of each.
    $pdo->prepare("INSERT INTO voucher_entries (voucher_id, entry_date, day_type, hours) VALUES (999999, '2026-07-01', 'WORK', 8)")->execute();
    $pdo->prepare("INSERT INTO invoice_lines (invoice_id, description, amount) VALUES (999999, 'orphan line', 100)")->execute();

    $after = $byKey();
    t_ok((int)$after['ventry_voucher']['found'] === $ventryBefore + 1, 'the voucher-line orphan is detected');
    t_ok($after['ventry_voucher']['ok'] === false, 'the check fails when an orphan voucher line exists');
    t_ok((int)$after['invline_invoice']['found'] === $invBefore + 1, 'the invoice-line orphan is detected');
    t_ok($after['invline_invoice']['ok'] === false, 'the check fails when an orphan invoice line exists');

    // integrity_run() writes ONE dated evidence row to data_check_runs.
    $runsBefore = (int)ops_val("SELECT COUNT(*) FROM data_check_runs");
    $s = integrity_run();
    $runsAfter = (int)ops_val("SELECT COUNT(*) FROM data_check_runs");
    t_ok($runsAfter === $runsBefore + 1, 'integrity_run() records one pass/fail evidence row');
    t_ok($s['total'] >= count($keys) && $s['failed'] >= 2, 'the recorded summary counts the checks and the failures');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring ----
t_ok(strpos($cron, 'integrity_run()') !== false, 'the integrity self-test is wired into the daily cron');
t_ok(strpos($cron, "setting_get('integrity_run_day'") !== false, 'the cron runner is guarded once per day (like audit_trim)');
t_ok(strpos($auditV, 'Chain intact') !== false && strpos($auditV, 'Chain broken') !== false,
     'the audit-log console now shows the sealed-chain integrity signal');
t_ok(function_exists('idems_audit_verify'), 'the chain verifier the banner reads exists');
