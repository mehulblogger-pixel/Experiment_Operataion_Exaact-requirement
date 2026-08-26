<?php
// Module 14 — Settings. Every settings surface (the /settings screen, company profile, AI keys,
// module toggles, role_access) funnels through setting_set(), and none of them recorded WHO changed
// WHAT — even though the sealed audit chain already supports a 'setting' entity. setting_set() now
// logs SETTING_CHANGED to the chain: additive, skips internal/bootstrap keys, redacts secrets, and
// never blocks the save. Read/write of settings is otherwise unchanged.
t_section('Module 14 — settings changes are recorded on the audit chain');

t_ok(function_exists('setting_change_class'), 'setting_change_class() exists');

// The classifier: user config is auditable; bootstrap/system markers are not; secrets are redacted.
t_ok(setting_change_class('pwd_min_len')['audit'],            'a security setting is auditable');
t_ok(setting_change_class('app_name')['audit'],              'a branding setting is auditable');
t_ok(!setting_change_class('schema_sig')['audit'],           'a schema signature is NOT audited (system marker)');
t_ok(!setting_change_class('partners_seeded')['audit'],      'a seed marker is NOT audited');
t_ok(!setting_change_class('foo_seeded_v1')['audit'],        'a *_seeded_v1 bootstrap flag is NOT audited');
t_ok(!setting_change_class('licence_checked_at')['audit'],   'a *_checked_at system timestamp is NOT audited');
t_ok(setting_change_class('smtp_pass')['secret'],            'smtp_pass is flagged secret');
t_ok(setting_change_class('rzp_key_secret')['secret'],       'a payment secret is flagged secret');
t_ok(setting_change_class('ai_config')['secret'],            'the AI config blob (holds keys) is flagged secret');
t_ok(!setting_change_class('pwd_min_len')['secret'],         'an ordinary setting is not treated as a secret');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('idems_migrate')) idems_migrate();

    $count = function ($k) {
        return (int)ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity='setting' AND action='SETTING_CHANGED' AND field=?", [$k]);
    };
    $last = function ($k) {
        return ops_one("SELECT old_value, new_value FROM idems_audit WHERE entity='setting' AND action='SETTING_CHANGED' AND field=? ORDER BY id DESC LIMIT 1", [$k]);
    };

    // 1) A user setting change is recorded with old → new.
    setting_set('tat_threshold_days', '7');
    setting_set('tat_threshold_days', '10');
    t_ok($count('tat_threshold_days') >= 1, 'changing a setting writes a SETTING_CHANGED audit entry');
    $r = $last('tat_threshold_days');
    t_eq($r['old_value'], '7',  'the audit entry keeps the OLD value');
    t_eq($r['new_value'], '10', 'the audit entry keeps the NEW value');

    // 2) A no-op write (same value) is NOT logged again.
    $before = $count('tat_threshold_days');
    setting_set('tat_threshold_days', '10');
    t_eq($count('tat_threshold_days'), $before, 'writing the same value again logs nothing (no noise)');

    // 3) A system/bootstrap key is never audited.
    setting_set('schema_sig', 'abc123');
    setting_set('schema_sig', 'def456');
    t_eq($count('schema_sig'), 0, 'a system marker change is not written to the chain');

    // 4) A secret records the EVENT but never the value.
    setting_set('smtp_pass', 'super-secret-1');
    setting_set('smtp_pass', 'super-secret-2');
    t_ok($count('smtp_pass') >= 1, 'changing a secret is still recorded as an event');
    $s = $last('smtp_pass');
    t_ok(strpos((string)$s['new_value'], 'secret') === false, 'the secret VALUE is never written to the trail');
    t_eq($s['new_value'], '(updated)', 'a changed secret is recorded as "(updated)", not its value');

    // 5) A very large non-secret value is summarised, not copied wholesale.
    setting_set('logo_data', str_repeat('A', 5000));
    $l = $last('logo_data');
    t_ok(strpos((string)$l['new_value'], 'chars changed') !== false, 'a large value is summarised on the trail');

    // 6) The write itself still works — the setting reads back its new value.
    t_eq(setting_get('tat_threshold_days'), '10', 'the setting write is unaffected (value reads back)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
$lib  = file_get_contents(__DIR__ . '/../lib/access.php');
$view = file_get_contents(__DIR__ . '/../views/ops/settings.php');
t_ok(strpos($lib, "idems_log('setting', 0, 'SETTING_CHANGED'") !== false, 'setting_set logs to the sealed chain');
t_ok(strpos($lib, 'ON CONFLICT(skey)') !== false, 'the original setting write is preserved (additive wrap)');
t_ok(strpos($view, 'action=SETTING_CHANGED') !== false, 'the settings screen links to the change trail');
