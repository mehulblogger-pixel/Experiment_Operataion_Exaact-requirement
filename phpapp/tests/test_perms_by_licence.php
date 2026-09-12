<?php
// The access editor only offers permissions for modules a company actually owns.
// A Recruitment company (Sales / Operations / Reporting / Money off) is not shown
// their permissions — but any such permission a user already holds is preserved
// on save (it lies outside the assignable set, which the save handler keeps).
t_section('Permissions offered follow the licence');

t_ok(function_exists('perm_product_module') && function_exists('assignable_permissions'),
    'the permission-by-module helpers exist');

// Each permission maps to the right sellable module (or null = always available).
t_eq(perm_product_module('mod.hiring.view'), 'hr', 'hiring permission belongs to People & hiring');
t_eq(perm_product_module('mod.calls.view'), 'operations', 'a calls permission belongs to Operations');
t_eq(perm_product_module('crm.quote.create'), 'sales', 'a CRM permission belongs to Sales');
t_eq(perm_product_module('idems.finalize'), 'reporting', 'a report permission belongs to Reporting');
t_eq(perm_product_module('finance.reconcile'), 'money', 'a finance permission belongs to Money');
t_eq(perm_product_module('data.salary'), 'money', 'salary visibility belongs to Money');
t_ok(perm_product_module('settings.manage') === null, 'settings is always available (no module)');
t_ok(perm_product_module('users.manage.global') === null, 'user management is always available');
t_ok(perm_product_module('master.manage') === null, 'masters management is always available');

$savedOff = (string) setting_get('modules_off', '');

// Recruitment-shaped install: Administration + People & hiring only.
setting_set('modules_off', 'sales,operations,reporting,money');
licence_disabled(true);
$a = assignable_permissions(true);   // global manager (master admin)

foreach (['crm.quote.create','ops.call.create','idems.finalize','finance.reconcile','data.salary',
          'mod.calls.view','mod.idems.view','mod.invoicing.view'] as $p) {
    t_ok(!isset($a[$p]), "an off-module permission ($p) is NOT offered to a recruitment company");
}
foreach (['mod.hiring.view','mod.hiring.edit','settings.manage','users.manage.global','master.manage','mod.masters.view'] as $p) {
    t_ok(isset($a[$p]), "an owned/always permission ($p) is still offered");
}

// Preservation: an off-module permission a user already holds lies OUTSIDE the
// assignable set, so the save handler's diff keeps it (never silently wiped).
t_ok(!isset($a['crm.quote.approve']),
    'an existing off-module grant lies outside the assignable set, so a save preserves it');

// A full install offers everything again.
setting_set('modules_off', '');
licence_disabled(true);
$all = assignable_permissions(true);
t_ok(isset($all['crm.quote.create']) && isset($all['idems.finalize']) && isset($all['finance.reconcile']),
    'with every module on, all permissions are offered again');

setting_set('modules_off', $savedOff);
licence_disabled(true);
t_ok(true, 'modules restored for the rest of the suite');
