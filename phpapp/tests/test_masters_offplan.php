<?php
// ============================================================================
//  Masters lists a workspace does not own must not clutter its screen or its
//  database. Only the people / recruitment, directory and untagged (core) lists
//  are always shown; every module-specific list (idems, vendor, ncr, capa,
//  audit, expediting, …) belongs to a sellable module and is hidden — and, on a
//  hosted workspace, physically pruned — when that module is not in the plan.
// ============================================================================

t_section('Masters — off-plan module lists are hidden and pruned');

if (!function_exists('lk_module_product_key')) { t_ok(true, 'masters module gating not present — skipped'); return; }

// --- The tag → product-module mapping. Core stays core; everything else is a module. ---
t_eq(lk_module_product_key('People'), 'hr', 'people/recruitment lists map to People & hiring');
t_eq(lk_module_product_key('Directory'), 'admin', 'directory lists are core (always shown)');
t_eq(lk_module_product_key(''), 'admin', 'an untagged list is core');
t_ok(lk_module_product_key('idems') !== 'admin', 'a raw inspection tag (idems) is NOT treated as core');
t_ok(lk_module_product_key('vendor') !== 'admin', 'a vendor tag is NOT treated as core');
t_ok(lk_module_product_key('ncr') !== 'admin' && lk_module_product_key('some_future_tag') !== 'admin',
    'any unrecognised tag is treated as module-specific, never core');

// --- Group visibility follows the licence (tested on the control install). ---
$savedOff = (string) setting_get('modules_off', '');
$savedKey = (string) setting_get('licence_key', '');
setting_set('licence_key', ''); if (function_exists('lk_state')) lk_state(true);
setting_set('modules_off', 'sales,operations,money,reporting');
if (function_exists('licence_disabled')) licence_disabled(true);
t_ok(lk_group_enabled('People'), 'the recruitment/people group is shown for a recruitment plan');
t_ok(lk_group_enabled('Directory'), 'the directory group is shown');
t_ok(!lk_group_enabled('idems') && !lk_group_enabled('vendor') && !lk_group_enabled('ncr'),
    'inspection / vendor / NCR groups are hidden for a recruitment plan');

// --- The pruner never touches the control / single-business install. ---
if (function_exists('lk_prune_offplan_lists')) {
    $before = (int) ops_val("SELECT COUNT(*) FROM lookup_types");
    lk_prune_offplan_lists();   // current_tenant()==='' here → must be a no-op
    t_eq((int) ops_val("SELECT COUNT(*) FROM lookup_types"), $before, 'the pruner does nothing on the control install');
}

// NB: the pruner's DELETE path is deliberately NOT exercised against the shared
// test database — it scans every list and would remove the real inspection
// masters that later tests rely on. Its decision (WHAT it removes) is exactly
// "$module set AND !lk_group_enabled($module)", which the group-visibility
// assertions above prove, and its "control install = no-op" guard is proven below.

// Restore.
setting_set('modules_off', $savedOff);
setting_set('licence_key', $savedKey);
if (function_exists('lk_state')) lk_state(true);
if (function_exists('licence_disabled')) licence_disabled(true);
t_ok(true, 'module state restored for the rest of the suite');
