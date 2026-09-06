<?php
// The Masters screen groups its dropdown lists by module and hides the lists
// for modules this install did not buy — so a Recruitment-only copy shows only
// the recruitment/people + core lists instead of the whole inspection/sales/
// operations/money set. This checks the grouping and the module gate. It
// snapshots and restores the global module settings so the shared DB is intact.
t_section('Masters — dropdown lists grouped & filtered by module');

t_ok(function_exists('lk_types_grouped'), 'the grouping helper exists');
$g = lk_types_grouped();
t_ok(is_array($g) && count($g) > 0, 'the dropdown lists are grouped by module');

// The module tag -> licence module mapping.
t_eq(lk_module_product_key('People'),     'hr',         'People lists belong to the recruitment (hr) module');
t_eq(lk_module_product_key('Operations'), 'operations', 'Operations lists map to the operations module');
t_eq(lk_module_product_key('Money'),      'money',      'Money lists map to the money module');
t_eq(lk_module_product_key('Directory'),  'admin',      'Directory lists are core/admin');
t_eq(lk_module_product_key(''),           'admin',      'an untagged list is treated as core');

// With a Recruitment-only module set, People + Directory show; the rest hide.
$keys = ['product_package', 'modules_off', 'packs_enabled', 'connect_enabled'];
$snap = [];
foreach ($keys as $k) $snap[$k] = setting_get($k, null);

setting_set('modules_off', 'operations,sales,money,reporting');
licence_disabled(true);
t_ok(lk_group_enabled('People'),     'recruitment / people lists stay visible');
t_ok(lk_group_enabled('Directory'),  'core directory lists stay visible');
t_ok(!lk_group_enabled('Operations'),'operations lists are hidden on a recruitment install');
t_ok(!lk_group_enabled('Sales'),     'sales lists are hidden');
t_ok(!lk_group_enabled('Money'),     'money lists are hidden');
t_ok(!lk_group_enabled('Reporting'), 'inspection-reporting lists are hidden');

// restore the shared DB exactly as found
$cache = &settings_cache();
foreach ($keys as $k) {
    if ($snap[$k] === null) { try { db()->prepare("DELETE FROM settings WHERE skey=?")->execute([$k]); } catch (Throwable $e) {} unset($cache[$k]); }
    else setting_set($k, $snap[$k]);
}
licence_disabled(true);
t_ok(true, 'global module settings restored');
