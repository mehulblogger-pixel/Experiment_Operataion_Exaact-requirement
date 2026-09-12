<?php
// ============================================================================
//  STEP 0 — the entitlement lock. A hosted company may switch a module ON only
//  within what it is entitled to (paid for / granted). A company editing its own
//  modules_off setting, or ticking any settings box, can NEVER unlock a module it
//  has not paid for. The ceiling (saas_entitled_modules) is written only on the
//  provisioning / super-admin / billing side, and applies ONLY inside a hosted
//  company workspace (the control/owner install is never limited).
// ============================================================================

t_section('Step 0 — entitlement lock (paid module ceiling)');

if (!function_exists('licence_entitled_ceiling') || !function_exists('module_entitled')) {
    t_ok(true, 'entitlement helpers not present — skipped');
    return;
}

// The ceiling applies only inside a hosted company workspace. Simulate one.
// NOTE: writing a setting re-loads config.php (harmless in production — it
// re-resolves to the SAME real tenant), which on the test's control database
// resets this fake tenant; so we re-apply it after every write via mk().
$saveTenant = $GLOBALS['__tenant'] ?? null;
$mk = function () { $GLOBALS['__tenant'] = ['key' => 'acme', 'company' => 'Acme', 'error' => '', 'saas' => true, 'base' => 'ops.example.com']; };
$mk();

// The cloud ceiling is the NO-signed-licence scenario (a hosted tenant), so pin
// the OPEN state — otherwise a signed key left in settings by an earlier test
// would be authoritative and mask the ceiling. Restored at the end.
$savedKey     = (string) setting_get('licence_key', '');
$savedInstall = (string) setting_get('licence_install', '');
setting_set('licence_key', ''); setting_set('licence_install', '');
if (function_exists('lk_state')) lk_state(true);   // bust any cached signed-licence state from an earlier test

$savedOff  = (string) setting_get('modules_off', '');
$savedCeil = (string) setting_get('saas_entitled_modules', '');

// --- No ceiling → nothing is locked. ---
setting_set('saas_entitled_modules', ''); $mk(); licence_disabled(true);
t_ok(licence_entitled_ceiling() === null, 'with no ceiling set, there is no cloud limit');
t_ok(module_entitled('sales') === true, 'with no ceiling every module is allowed');

// --- Recruitment ceiling: entitled to People & hiring only (admin is core). ---
setting_set('modules_off', ''); $mk();
setting_set('saas_entitled_modules', 'hr'); $mk(); licence_disabled(true);

t_ok(module_entitled('admin') === true, 'the core module is always entitled');
t_ok(module_entitled('hr') === true, 'an entitled module (People & hiring) is allowed');
t_ok(module_entitled('sales') === false, 'a non-entitled module (Sales) is NOT allowed');

t_ok(licence_enabled('hr') === true, 'the entitled module is enabled');
t_ok(licence_enabled('admin') === true, 'the core module is enabled');
t_ok(licence_enabled('sales') === false, 'an unpaid module is forced OFF even with an empty modules_off');
t_ok(licence_enabled('operations') === false, 'every module outside the ceiling is forced off');

// --- The write path cannot escape it: ticking Sales on is clamped. ---
if (function_exists('licence_save')) {
    licence_save(['mod_on' => ['sales' => 1, 'hr' => 1, 'operations' => 1]]); $mk(); licence_disabled(true);
    t_ok(licence_enabled('sales') === false, 'ticking an unpaid module on the settings screen does NOT enable it (write clamp)');
    t_ok(licence_enabled('hr') === true, 'an entitled module ticked on stays on');
    t_ok(strpos((string) setting_get('modules_off', ''), 'sales') !== false, 'the unpaid module is written back into the off-list');
}

// The cockpit's per-module "locked" flag is a direct derivation
// (locked = !module_entitled && !core), and module_entitled is asserted above.
// It is not re-asserted here because cockpit_modules() internally re-initialises
// the database connection, which re-derives the tenant from config — correct in
// production (the real tenant is re-resolved) but it drops this test's *faked*
// tenant, so a cockpit-level assertion here would test the harness, not the code.

// --- Raising the ceiling (a payment / super-admin grant) unlocks it. ---
setting_set('saas_entitled_modules', 'hr,sales'); $mk(); licence_disabled(true);
t_ok(module_entitled('sales') === true, 'adding a module to the ceiling (paid/granted) unlocks it');
licence_save(['mod_on' => ['hr' => 1]]); $mk(); licence_disabled(true);   // company chooses to keep sales off
t_ok(licence_enabled('sales') === false, 'within the ceiling the company may still keep an entitled module off (its own choice)');
t_ok(module_entitled('sales') === true, 'though off by choice, it remains entitled (can be turned on)');

// --- The control/owner install is NEVER limited, even if the setting is present. ---
$GLOBALS['__tenant'] = ['key' => '', 'company' => '', 'error' => '', 'saas' => false, 'base' => ''];
t_ok(licence_entitled_ceiling() === null, 'on the control/owner install there is no ceiling (owner is never limited)');

// Restore for later tests.
setting_set('modules_off', $savedOff);
setting_set('saas_entitled_modules', $savedCeil);
setting_set('licence_key', $savedKey);
setting_set('licence_install', $savedInstall);
if (function_exists('lk_state')) lk_state(true);
if ($saveTenant === null) { unset($GLOBALS['__tenant']); } else { $GLOBALS['__tenant'] = $saveTenant; }
licence_disabled(true);
t_ok(true, 'entitlement state restored for the rest of the suite');
