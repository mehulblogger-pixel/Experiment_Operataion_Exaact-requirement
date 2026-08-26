<?php
// Phase 2 §47 — settings governance. Module 14 already audits WHO changed WHICH setting; this adds the
// WHY / WHAT-IT-AFFECTS an approver needs. setting_meta_all() is a curated read-only registry over the
// behavioural settings that already exist (gates, financial norms, lifecycle timers) — it defines no
// new setting and changes no behaviour.
t_section('Phase 2 §47 — settings governance registry (read-only)');

t_ok(function_exists('setting_meta') && function_exists('setting_meta_all') && function_exists('setting_meta_render'),
     'the governance helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/settingmeta.php'") !== false, 'the settingmeta lib is loaded by the front controller');

$all = setting_meta_all();
t_ok(count($all) >= 15, 'the registry covers a meaningful set of behavioural settings');

// Each entry is well-formed: [label, purpose, affects[], scope, impact].
$scopes = ['forward', 'live']; $impacts = ['high', 'medium', 'low']; $wellFormed = true;
foreach ($all as $key => $m) {
    if (!is_string($m[0]) || $m[0] === '') $wellFormed = false;
    if (!is_string($m[1]) || $m[1] === '') $wellFormed = false;
    if (!is_array($m[2]) || !$m[2]) $wellFormed = false;
    if (!in_array($m[3], $scopes, true)) $wellFormed = false;
    if (!in_array($m[4], $impacts, true)) $wellFormed = false;
}
t_ok($wellFormed, 'every registry entry has a label, purpose, non-empty affects list, valid scope and impact');

// The high-impact gates and financial norms are covered.
foreach (['issue_gate_strict', 'fy_start_month', 'manmonth_basis', 'contract_idle_close_days', 'geofence_on', 'licence_enforce'] as $k) {
    t_ok(setting_meta($k) !== null, "the registry documents $k");
}
t_ok(setting_meta('logo_data') === null, 'cosmetic / non-behavioural settings are not in the governance registry');
t_ok(setting_meta('this_key_does_not_exist') === null, 'an unknown key returns null');

// The FY start month is correctly marked as applying live (it re-buckets existing figures).
t_eq(setting_meta('fy_start_month')[3], 'live', 'fy_start_month is marked as applying immediately');
// The idle-close timer applies going forward.
t_eq(setting_meta('contract_idle_close_days')[3], 'forward', 'the idle-close timer is marked forward-only');

// The panel renders without error and names a governed setting.
ob_start(); setting_meta_render(); $html = ob_get_clean();
t_ok(strpos($html, 'issue_gate_strict') !== false, 'the governance panel renders the settings');

// Wired onto the settings screen.
$v = file_get_contents(__DIR__ . '/../views/ops/settings.php');
t_ok(strpos($v, 'setting_meta_render') !== false, 'the settings screen shows the governance panel');
