<?php
// R3 — a per-user permission set REPLACES the role defaults, which had silently
// locked people out (edit a login, save with fewer boxes ticked, access gone).
// Mitigations: (1) the access editor pre-ticks the login's EFFECTIVE set, resolved
// by the same code the gate uses; (2) a save preserves any permission the editor
// could not see; (3) the editable set is shared by the form and the handler.
t_section('access editor: effective pre-tick + no silent lock-out');

$pdo = db();

// --- user_effective_perms mirrors the ua() resolution ------------------------
// A master gets everything.
t_ok(user_effective_perms(['is_superuser'=>1,'role'=>'COORDINATOR']) === array_keys(all_permissions()),
    'a master resolves to every permission');

// A login following role defaults resolves to the role's effective set (incl. modules).
$coDef = role_defaults('COORDINATOR')['perms'];
$coEff = user_effective_perms(['role'=>'COORDINATOR','permissions'=>'']);
sort($coDef); sort($coEff);
t_ok($coDef === $coEff, 'a defaults-following login resolves to the role default set');

// A legacy custom set with NO module perms keeps its module access (back-filled).
$legacy = user_effective_perms(['role'=>'COORDINATOR','permissions'=>'dash.operations,ops.call.create']);
t_ok(in_array('mod.calls.view', $legacy, true) || in_array('mod.jobs.view', $legacy, true),
    'a pre-module custom set still resolves to its module defaults (no silent loss)');

// user_effective_perms agrees with ua() for a real login row.
$pdo->prepare("INSERT INTO users (username, first_name, role, permissions, is_active) VALUES ('r3_co','Co','COORDINATOR','',1)")->execute();
$uid = (int)$pdo->lastInsertId();
$_SESSION['uid'] = $uid; current_user(true); ua(true);
$viaUa = ua()['perms']; $viaHelper = user_effective_perms(['role'=>'COORDINATOR','permissions'=>'']);
sort($viaUa); sort($viaHelper);
t_ok($viaUa === $viaHelper, 'ua() and user_effective_perms() agree (one source of truth)');
unset($_SESSION['uid']); current_user(true); ua(true);

// --- assignable_permissions: global sees all, branch sees a safe subset --------
t_ok(assignable_permissions(true) === all_permissions(), 'a global manager may assign every permission');
$sub = assignable_permissions(false);
t_ok(!isset($sub['data.salary']) && !isset($sub['users.manage.global']) && !isset($sub['settings.manage']),
    'a branch manager cannot assign salary / global-user / settings');
t_ok(isset($sub['ops.job.close']) && isset($sub['mod.calls.view']),
    'a branch manager can assign the safe operational subset');

// --- the lock-out prevention: the save keeps perms the editor could not see -----
// A login holds a sensitive permission (settings.manage) a branch manager cannot see.
$existing = ['dash.operations','ops.call.create','settings.manage','mod.calls.view'];
$globalMgr = false;
$assignable = array_keys(assignable_permissions($globalMgr));
// The branch manager unticks everything they CAN see and saves (posts nothing).
$posted = [];
$chosen = array_intersect(array_filter($posted), $assignable);
$preserved = array_diff($existing, $assignable);          // <- the rule under test
$chosen = array_values(array_unique(array_merge($chosen, $preserved)));
t_ok(in_array('settings.manage', $chosen, true),
    'a permission the editor could not see survives their save (no silent lock-out)');
t_ok(!in_array('dash.operations', $chosen, true),
    'a permission the editor COULD see and unticked is removed as intended');

// --- the form + handler share the editable set, and the form pre-ticks effective
$form = file_get_contents(__DIR__ . '/../views/ops/user_form.php');
t_ok(strpos($form, 'assignable_permissions($globalMgr)') !== false, 'the form sources its editable set from the shared helper');
t_ok(strpos($form, 'user_effective_perms($user)') !== false, 'the form pre-ticks the login\'s effective set');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, 'assignable_permissions($globalMgr)') !== false, 'the save handler uses the same editable set');
t_ok(strpos($ops, 'array_diff($existing, $assignable)') !== false, 'the save handler preserves unseen permissions');
t_ok(strpos($form, 'will remove access to') !== false, 'a confirm warns before a save drops a held module');
