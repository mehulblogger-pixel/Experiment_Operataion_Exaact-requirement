<?php
// The Roles & access editor must only show the roles and permissions this
// workspace's plan uses — a recruitment company sees no Inspector / Marketing /
// Finance roles, and no inspection / sales / money / CRM permissions.
t_section('Roles & access — filtered by the plan');
if (!function_exists('perm_licensed') || !function_exists('roles_for_licence')) { t_ok(true, 'access gating not present — skipped'); return; }

$savedOff = (string) setting_get('modules_off', '');
$savedKey = (string) setting_get('licence_key', '');
setting_set('licence_key', ''); if (function_exists('lk_state')) lk_state(true);
setting_set('modules_off', 'sales,operations,money,reporting');
if (function_exists('licence_disabled')) licence_disabled(true);

// Roles: recruitment/admin only.
$roles = roles_for_licence(); unset($roles['MASTER_ADMIN']);
t_ok(isset($roles['COORDINATOR']), 'a generic role (Coordinator) is offered');
t_ok(!isset($roles['INSPECTOR']) && !isset($roles['SR_INSPECTOR']), 'inspection roles are hidden');
t_ok(!isset($roles['MARKETING_MANAGER']) && !isset($roles['BUSINESS_DEV_MANAGER']), 'sales/marketing roles are hidden');
t_ok(!isset($roles['FINANCE']), 'the Finance role is hidden');

// Permissions: hiring + admin kept; sales/money/reporting dropped.
t_ok(perm_licensed('hiring.admin'), 'recruitment permission is kept');
t_ok(perm_licensed('settings.manage') && perm_licensed('master.manage'), 'core admin permissions are kept');
t_ok(!perm_licensed('crm.quote.create'), 'CRM permission is dropped');
t_ok(!perm_licensed('finance.reconcile'), 'finance permission is dropped');
t_ok(!perm_licensed('idems.finalize'), 'inspection-reporting permission is dropped');

// Module matrix: hiring kept; inspection/sales modules dropped.
t_ok(module_key_licensed('hiring'), 'the Hiring module is shown');
t_ok(module_key_licensed('masters'), 'the Masters (core) module is shown');
t_ok(!module_key_licensed('calls') && !module_key_licensed('jobs'), 'inspection call/job modules are hidden');
t_ok(!module_key_licensed('idems'), 'the inspection-reporting module is hidden');
t_ok(!module_key_licensed('leads') && !module_key_licensed('quotes'), 'sales modules are hidden');

// The filtered groups drop empty sections.
$pg = permission_groups_licensed();
t_ok(!isset($pg['Marketing & Sales (CRM)']) && !isset($pg['Money']) && !isset($pg['Inspection documentation (IDEMS)']), 'sales/money/inspection permission groups are gone');
t_ok(isset($pg['Recruitment']) || isset($pg['Administration']), 'recruitment / admin permission groups remain');

setting_set('modules_off', $savedOff);
setting_set('licence_key', $savedKey);
if (function_exists('lk_state')) lk_state(true);
if (function_exists('licence_disabled')) licence_disabled(true);
t_ok(true, 'state restored');
