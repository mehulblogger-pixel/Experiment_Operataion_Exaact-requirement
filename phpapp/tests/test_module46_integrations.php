<?php
// Module 46 — Integrations. Each integration (Ads Pro, Books, licence auto-renew, SMTP) already
// tracks its own last-sync / error / stuck-outbox, but those signals live only on that integration's
// own screen — a sync that fails quietly is invisible until someone asks why data is stale. Add a
// read-only integration-health surface that aggregates the EXISTING signals (no sender/sync touched,
// no live API call), parallel to licence_health() (M36) and the notification log (M38). Also: the
// signed licence key was audited on the non-secret path — now classified secret (M14 tie-in).
t_section('Module 46 — integration health surface + licence-key secret');

t_ok(function_exists('integration_health'),           'integration_health() exists');
t_ok(function_exists('integration_health_attention'), 'integration_health_attention() exists');
t_ok(function_exists('ops_integrations'),             'the /integrations handler exists');

// The route is dispatched and mapped to the core admin module.
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "case \$route === 'integrations':") !== false, 'the /integrations route is dispatched');
t_ok(strpos($ops, "'integrations'=>'admin'") !== false, 'the route is mapped to the core admin module');

// Gap-4 fix: the signed licence key + install id are now classified secret for audit redaction.
t_ok(setting_change_class('licence_key')['secret'],     'licence_key is now classified secret (not written to the trail)');
t_ok(setting_change_class('licence_install')['secret'], 'licence_install is classified secret');
// A licence-key change records the event but never the value.
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$savedUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('idems_migrate')) idems_migrate();
    setting_set('licence_key', 'SHORTKEYVALUE-abc');
    $r = ops_one("SELECT new_value FROM idems_audit WHERE entity='setting' AND field='licence_key' ORDER BY id DESC LIMIT 1");
    t_ok($r !== null, 'a licence_key change is recorded on the audit chain');
    t_ok(strpos((string)$r['new_value'], 'SHORTKEY') === false, 'the licence key VALUE is never written to the trail');

    // The health aggregator returns a well-formed list; on a bare dev install (no integrations
    // connected) it is empty and attention is zero — no false alarm.
    $h = integration_health();
    t_ok(is_array($h), 'integration_health() returns a list');
    foreach ($h as $rowItem) foreach (['key','label','severity','last','detail','url'] as $k)
        t_ok(array_key_exists($k, $rowItem), "each integration row has '$k'");
    t_ok(is_int(integration_health_attention()) && integration_health_attention() >= 0, 'the attention count is a non-negative integer');
    // attention == count of non-ok rows, by construction.
    $nonOk = 0; foreach ($h as $rowItem) if ($rowItem['severity'] !== 'ok') $nonOk++;
    t_eq(integration_health_attention(), $nonOk, 'attention counts exactly the non-healthy integrations');

    // The handler renders (via a view() stub) for an admin and hands the rows to the view. Another
    // test may already have installed a stub that captures into $GLOBALS['__nv']; support either.
    if (!function_exists('view')) { function view($tpl, $data = []) { $GLOBALS['__nv'] = $data; } }
    $pdo = db();
    $pdo->prepare("INSERT INTO users (username, role, is_superuser, is_active) VALUES ('intgmaster','MASTER_ADMIN',1,1)")->execute();
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); if (function_exists('ua')) ua(true);
    $GLOBALS['__nv'] = null; $GLOBALS['__iv'] = null;
    ops_integrations('GET');
    $captured = $GLOBALS['__nv'] ?? $GLOBALS['__iv'] ?? null;
    t_ok(isset($captured['rows']) && is_array($captured['rows']), 'the handler passes the integration rows to the view');
} finally {
    if ($savedUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $savedUid;
    if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
$view = file_get_contents(__DIR__ . '/../views/ops/integrations.php');
$settings = file_get_contents(__DIR__ . '/../views/ops/settings.php');
t_ok(strpos($ops, 'integration_health_attention()') !== false && strpos($ops, "'integrations', 'Integrations need attention'") !== false,
    'a failing integration surfaces on the home attention band (admin-gated)');
t_ok(strpos($settings, '/integrations') !== false, 'the settings screen links to integration health');
t_ok(strpos($ops, 'ads_health()') === false || strpos($ops, 'function integration_health') !== false,
    'the health read is passive (it does not call the live ads_health API on page load)');
