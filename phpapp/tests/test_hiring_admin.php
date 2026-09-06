<?php
// Recruitment Manager — the module-scoped `hiring.admin` permission lets a
// customer appoint someone who configures ONLY recruitment, with no system-wide
// admin powers. Full admins keep access without the extra grant. Additive.
t_section('recruitment-only admin (hiring.admin)');

$pdo = db();

// The permission exists and is offered in the Access editor.
t_ok(array_key_exists('hiring.admin', PERMISSIONS), 'hiring.admin is a defined permission');
$inGroup = false; foreach (permission_groups() as $g) if (in_array('hiring.admin', $g, true)) $inGroup = true;
t_ok($inGroup, 'hiring.admin is shown in the Access (roles & permissions) editor');

// A user granted ONLY hiring.admin (a Recruitment Manager) — not a system admin.
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,permissions) VALUES ('rec_mgr','RM','INSPECTOR',1,'mod.hiring.view,hiring.admin')")->execute();
$rm = (int)$pdo->lastInsertId();
$_SESSION['uid'] = $rm; current_user(true); ua(true);
t_ok(hiring_admin_can(), 'a Recruitment Manager may configure the recruitment module');
t_ok(!is_admin_level(), 'a Recruitment Manager is NOT a full system administrator');
t_ok(!can('settings.manage'), 'a Recruitment Manager cannot manage system settings');
t_ok(!can('users.manage.global'), 'a Recruitment Manager cannot manage users or access');
t_ok(!is_master(), 'a Recruitment Manager is not a master');

// A full administrator keeps access without needing the extra permission.
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active) VALUES ('full_bm','BM','BRANCH_MANAGER',1)")->execute();
$bm = (int)$pdo->lastInsertId();
$_SESSION['uid'] = $bm; current_user(true); ua(true);
t_ok(is_admin_level(), 'a branch manager is a full admin-level user');
t_ok(hiring_admin_can(), 'a full admin can configure recruitment without the extra permission');

// A coordinator WITHOUT the permission cannot configure recruitment.
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active) VALUES ('plain_co','CO','COORDINATOR',1)")->execute();
$co = (int)$pdo->lastInsertId();
$_SESSION['uid'] = $co; current_user(true); ua(true);
t_ok(!hiring_admin_can(), 'a coordinator without hiring.admin cannot configure recruitment');
t_ok(is_coordinator_level(), 'that coordinator can still do day-to-day recruitment work');

// Clean up.
$pdo->prepare("DELETE FROM users WHERE id IN (?,?,?)")->execute([$rm, $bm, $co]);
unset($_SESSION['uid']); current_user(true); ua(true);
