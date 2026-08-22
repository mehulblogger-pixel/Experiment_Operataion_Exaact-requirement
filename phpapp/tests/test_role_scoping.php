<?php
// Sales (BDM/KAM/Marketing) and Finance are NOT operations management: they must
// not be is_admin_level()/is_coordinator_level() (which is what leaked ops widgets
// and actions to them), yet must keep their own CRM / finance access via module &
// permission checks. Guards the MGMT_ROLES separation.
t_section('sales & finance are not operations management');

$pdo = db();
$mk = function ($role) use ($pdo) {
    $u = 'rs_' . strtolower($role);
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active) VALUES (?,?,?,1)")->execute([$u, $role, $role]);
    return (int)$pdo->lastInsertId();
};
$as = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };

// FINANCE — not ops management, but keeps the books.
$as($mk('FINANCE'));
t_ok(is_admin_level() === false, 'FINANCE is not admin-level');
t_ok(is_coordinator_level() === false, 'FINANCE is not coordinator-level');
t_ok(can('finance.reconcile'), 'FINANCE keeps finance.reconcile (billing)');
t_ok(!function_exists('books_can') || books_can(), 'FINANCE can still open the books');
t_ok(!can('ops.call.create'), 'FINANCE cannot raise inspection calls');

// BDM — not management, but keeps CRM.
$as($mk('BUSINESS_DEV_MANAGER'));
t_ok(is_admin_level() === false && is_coordinator_level() === false, 'BDM is not management/coordinator');
t_ok(can('crm.quote.create'), 'BDM keeps crm.quote.create');
t_ok(can('mod.quotes.view'), 'BDM keeps quote access');
t_ok(!can('ops.call.create'), 'BDM cannot raise inspection calls');

// MARKETING_MANAGER — senior sales, still not ops management.
$as($mk('MARKETING_MANAGER'));
t_ok(is_admin_level() === false, 'MARKETING_MANAGER is not admin-level');
t_ok(can('crm.quote.approve'), 'MARKETING_MANAGER keeps quote approval');

// OPERATION_MANAGER — genuine operations management, unchanged.
$as($mk('OPERATION_MANAGER'));
t_ok(is_admin_level() === true, 'OPERATION_MANAGER stays admin-level');
t_ok(is_coordinator_level() === true, 'OPERATION_MANAGER stays coordinator-level');
t_ok(can('ops.call.create'), 'OPERATION_MANAGER can raise calls');

unset($_SESSION['uid']); current_user(true); ua(true);
