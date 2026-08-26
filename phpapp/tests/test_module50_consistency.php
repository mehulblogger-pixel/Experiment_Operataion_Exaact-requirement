<?php
// Module 50 — Cross-module consistency & regression (capstone). Two parts:
//  A) /system-status — one read-only board aggregating every health verdict the programme built
//     (audit chain, data integrity, compliance readiness, licence, integrations, email, profit drift).
//  B) cross-module invariants — the canonical single-source engines are defined exactly once, and the
//     health/verdict helpers return their documented shape. Modeled on test_no_dead_permission_gates.
t_section('Module 50 — system-status aggregator + cross-module invariants');

// ---- A) the aggregator ----
t_ok(function_exists('system_status'),        'system_status() aggregator exists');
t_ok(function_exists('system_status_worst'),  'system_status_worst() exists');
t_ok(function_exists('ops_system_status'),    'the /system-status handler exists');

$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "case \$route === 'system-status':") !== false, 'the /system-status route is dispatched');
t_ok(strpos($ops, "'system-status'=>'admin'") !== false, 'the route is mapped to the core admin module');
// It fans in each subsystem's own helper (aggregation, not re-implementation).
foreach (['idems_audit_verify', 'integrity_summary', 'compliance_status', 'licence_health', 'integration_health', 'email_failed_count', 'profit_reconciliation'] as $fn)
    t_ok(strpos($ops, $fn . '(') !== false, "system_status fans in $fn()");

$rows = system_status();
t_ok(is_array($rows), 'system_status() returns a list');
foreach ($rows as $r) {
    foreach (['key','label','severity','headline','detail','url'] as $k) t_ok(array_key_exists($k, $r), "each status row has '$k'");
    t_ok(in_array($r['severity'], ['ok','warn','bad'], true), 'every row severity is ok/warn/bad');
}
t_ok(in_array(system_status_worst(), ['ok','warn','bad'], true), 'the worst-severity rollup is a valid severity');

// ---- B) cross-module invariants ----
// Each canonical single-source engine / verdict is defined EXACTLY ONCE across lib/ (no shadow copy).
$libFiles = glob(__DIR__ . '/../lib/*.php');
$defCount = function ($name) use ($libFiles) {
    $n = 0;
    foreach ($libFiles as $f) $n += preg_match_all('/function\s+' . preg_quote($name, '/') . '\s*\(/', (string)file_get_contents($f));
    return $n;
};
foreach (['job_profit','boss_profit','contract_state','contract_classify','profit_reconciliation',
          'idems_audit_verify','compliance_status','licence_health','integration_health',
          'attention_summary','system_status'] as $engine)
    t_eq($defCount($engine), 1, "the canonical '$engine' is defined exactly once (no duplicate/shadow)");

// The health/verdict helpers return their documented shapes.
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('idems_migrate')) idems_migrate();

    if (function_exists('idems_audit_verify')) { $c = idems_audit_verify(); t_ok(array_key_exists('ok', $c), 'idems_audit_verify() reports ok'); }
    if (function_exists('licence_health'))     { $h = licence_health(); t_ok(array_key_exists('needs_attention', $h) && array_key_exists('severity', $h), 'licence_health() has needs_attention + severity'); }
    if (function_exists('integration_health')) { foreach (integration_health() as $ir) t_ok(isset($ir['severity']) && isset($ir['url']), 'each integration_health row has severity + url'); }

    // The Module-32 identity still holds: overstatement == partial − canonical.
    if (function_exists('profit_reconciliation')) {
        $pr = profit_reconciliation();
        t_eq(round($pr['overstatement'], 2), round($pr['partial_profit'] - $pr['canonical_profit'], 2),
            'profit_reconciliation invariant: overstatement == partial − canonical');
    }
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The settings screen links to the new board.
$settings = file_get_contents(__DIR__ . '/../views/ops/settings.php');
t_ok(strpos($settings, '/system-status') !== false, 'the settings screen links to system status');
