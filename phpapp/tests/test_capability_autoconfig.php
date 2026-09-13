<?php
// Choosing "What does your company do?" now configures the workspace: the sellable
// modules switch to match the chosen activities (within the plan), so a recruitment
// company becomes a recruitment-only workspace and an inspection company keeps its
// modules. Reversible; core always on.

t_section('Business activities auto-configure the modules');

if (!function_exists('cockpit_apply_capability_modules')) {
    t_ok(true, 'auto-config not present — skipped'); return;
}

$savedTenant = $GLOBALS['__tenant'] ?? null;
$savedOff    = (string) setting_get('modules_off', '');
$savedKey    = (string) setting_get('licence_key', '');
$fakeTenant  = function () { $GLOBALS['__tenant'] = ['key' => 'acme', 'company' => 'Acme', 'error' => '', 'saas' => true, 'base' => 'x']; };

$fakeTenant();
setting_set('licence_key', ''); if (function_exists('lk_state')) lk_state(true); $fakeTenant();
setting_set('saas_entitled_modules', ''); $fakeTenant();   // empty ceiling = everything allowed (the buggy default)

// A recruitment-only company: only recruitment activities ticked.
$left = cockpit_apply_capability_modules(['TECH_RECRUITMENT', 'PERMANENT_PLACEMENT']);
$fakeTenant();
if (function_exists('licence_disabled')) licence_disabled(true);
t_ok(is_array($left), 'the auto-config ran inside a hosted workspace');
t_ok(in_array('hr', (array) $left, true), 'the Recruitment (hr) module is kept ON');
t_ok(!in_array('operations', (array) $left, true) && !in_array('reporting', (array) $left, true)
    && !in_array('sales', (array) $left, true) && !in_array('money', (array) $left, true),
    'Operations / Reporting / Sales / Money are switched OFF for a recruitment company');
t_ok(licence_enabled('hr'), 'hr reads as enabled after auto-config');
t_ok(!licence_enabled('operations') && !licence_enabled('reporting') && !licence_enabled('money'),
    'the inspection/finance modules read as disabled — the nav collapses to recruitment');

// A company that also does inspection keeps those modules.
$left2 = cockpit_apply_capability_modules(['TECH_RECRUITMENT', 'TPIA']);
$fakeTenant();
if (function_exists('licence_disabled')) licence_disabled(true);
t_ok(in_array('hr', (array) $left2, true) && in_array('operations', (array) $left2, true) && in_array('reporting', (array) $left2, true),
    'an inspection + recruitment company keeps hr, operations and reporting on');

// Saving with NOTHING chosen must not nuke the workspace.
$left3 = cockpit_apply_capability_modules([]);
t_ok($left3 === null, 'saving with no activities chosen changes nothing (never turns everything off)');

// Restore.
setting_set('modules_off', $savedOff); $fakeTenant();
setting_set('licence_key', $savedKey); if (function_exists('lk_state')) lk_state(true);
setting_set('saas_entitled_modules', '');
$GLOBALS['__tenant'] = $savedTenant;
if (function_exists('licence_disabled')) licence_disabled(true);
t_ok(true, 'state restored');
