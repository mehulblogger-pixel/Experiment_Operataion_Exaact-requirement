<?php
// The Add-a-person role list only offers roles a company can actually use — a
// Recruitment company (Sales / Operations / Money off) is not shown Inspector,
// Marketing or Finance roles. The person's current role is always kept, so
// editing an existing user never drops their setting.
t_section('Roles offered follow the licence');

t_ok(function_exists('roles_for_licence'), 'the role-by-licence filter exists');

$savedOff = (string) setting_get('modules_off', '');

// Recruitment-shaped install: only Administration + People & hiring on.
setting_set('modules_off', 'sales,operations,reporting,money');
licence_disabled(true);
$r = roles_for_licence();
t_ok(!isset($r['INSPECTOR']) && !isset($r['SR_INSPECTOR']), 'Inspector roles are hidden when Operations is off');
t_ok(!isset($r['BUSINESS_DEV_MANAGER']) && !isset($r['MARKETING_MANAGER']) && !isset($r['KEY_ACCOUNTS_MANAGER']),
    'Sales / Marketing roles are hidden when Sales is off');
t_ok(!isset($r['FINANCE']), 'the Finance role is hidden when Money is off');
t_ok(isset($r['MASTER_ADMIN']) && isset($r['COORDINATOR']) && isset($r['ASST_MANAGER']),
    'core management + coordinator roles always remain');

// Editing an existing Inspector must still show Inspector as the current role.
$rk = roles_for_licence(null, 'INSPECTOR');
t_ok(isset($rk['INSPECTOR']), 'the person\'s current role is always kept, even if its module is off');

// A full install shows every role again.
setting_set('modules_off', '');
licence_disabled(true);
$all = roles_for_licence();
t_ok(isset($all['INSPECTOR']) && isset($all['FINANCE']) && isset($all['MARKETING_MANAGER']),
    'with every module on, all roles are offered');

// Restore.
setting_set('modules_off', $savedOff);
licence_disabled(true);
t_ok(true, 'modules restored for the rest of the suite');
