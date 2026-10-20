<?php
// A person FOLLOWS their role unless somebody deliberately gave them their own
// access (owner's video SAL_002, 20 Oct 2026). Before: a new person's screen
// arrived pre-ticked with the Coordinator's access whatever role was chosen,
// and every save stored the ticks as a frozen per-user set — a Marketing
// Executive saved untouched held a Coordinator's invoicing, vendors and credit
// figures, and lacked "Create / edit quotations".
t_section('user form: ticks follow the role; only a real exception is stored');

$all = array_keys(assignable_permissions(true));
$mx  = role_perm_set('MARKETING_EXECUTIVE');
$co  = role_perm_set('COORDINATOR');

// --- what a role gives -------------------------------------------------------
t_ok(in_array('crm.quote.create', $mx, true) && in_array('crm.followup.manage', $mx, true),
    'a Marketing Executive gets Create quotations and follow-ups (permission matrix)');
t_ok(!in_array('data.credit', $mx, true) && !in_array('mod.invoicing.view', $mx, true) && !in_array('mod.vendors.view', $mx, true),
    'a Marketing Executive gets no credit figures, invoicing or vendors');
$sorted = function ($s) { $s = array_values(array_unique($s)); sort($s); return $s; };
t_ok($sorted(perm_close_implications($mx)) === $sorted($mx), 'the role set is already closed under the verb implications');

// --- what the save stores ----------------------------------------------------
t_ok(user_perms_to_store($mx, 'MARKETING_EXECUTIVE', $all, '') === '',
    'ticks equal to the role are stored as "follows the role"');
t_ok(user_perms_to_store(array_reverse($mx), 'MARKETING_EXECUTIVE', $all, '') === '',
    'order of ticks does not matter');
t_ok(user_perms_to_store($mx, 'MARKETING_EXECUTIVE', $all, implode(',', $mx)) === '',
    'a frozen copy of the role, saved untouched, goes back to following the role');
$legacy = user_perms_to_store($co, 'MARKETING_EXECUTIVE', $all, '');
t_ok($legacy !== '' && in_array('data.credit', explode(',', $legacy), true),
    'Coordinator ticks kept on a Marketing Executive are stored as their own access');
$extra = explode(',', user_perms_to_store(array_merge($mx, ['dash.operations']), 'MARKETING_EXECUTIVE', $all, ''));
t_ok(in_array('dash.operations', $extra, true) && in_array('crm.quote.create', $extra, true),
    'one deliberate extra tick: own access = the role plus that tick');
t_ok(user_perms_to_store([], 'MARKETING_EXECUTIVE', $all, implode(',', $co)) === '',
    'nothing ticked means the role\'s access (an empty set cannot be stored) — the screen says so');

// A branch-level editor sees a subset; what they cannot see is never taken away.
$sub = array_keys(assignable_permissions(false));
$bmSet = role_perm_set('BRANCH_MANAGER');
t_ok(user_perms_to_store(array_intersect($bmSet, $sub), 'BRANCH_MANAGER', $sub, '') === '',
    'a branch editor saving the role\'s visible part leaves the person following the role');
$visible1 = array_slice(array_values(array_intersect($bmSet, $sub)), 0, 1);
$narrow = explode(',', user_perms_to_store($visible1, 'BRANCH_MANAGER', $sub, ''));
$unseen = array_diff($bmSet, $sub);
t_ok($unseen === [] || !array_diff($unseen, $narrow),
    'a branch editor who narrows what they see keeps what they cannot see (R3)');

// --- who is shown as having their own access --------------------------------
t_ok(!user_has_own_access(['role' => 'MARKETING_EXECUTIVE', 'permissions' => '']), 'a role follower is not flagged');
t_ok(!user_has_own_access(['role' => 'MARKETING_EXECUTIVE', 'permissions' => implode(',', $mx)]), 'a stored exact copy of the role is not flagged');
t_ok(user_has_own_access(['role' => 'MARKETING_EXECUTIVE', 'permissions' => implode(',', $co)]), 'Coordinator access on a Marketing Executive is flagged');
t_ok(!user_has_own_access(['role' => 'MARKETING_EXECUTIVE', 'is_superuser' => 1, 'permissions' => 'x']), 'a master is never flagged');

// --- the screen --------------------------------------------------------------
$form = file_get_contents(__DIR__ . '/../views/ops/user_form.php');
t_ok(strpos($form, "role_perm_set(\$curRole)") !== false, 'a new person starts on the chosen role\'s set, not a fixed one');
t_ok(strpos($form, 'id="role_perm_sets"') !== false && strpos($form, 'id="perm_status"') !== false,
    'the screen knows every role\'s set and says whether the person follows it');
t_ok(strpos($form, "leave all unticked to use the role's defaults") === false,
    'the old, untrue instruction is gone from the Permissions tab');
$reg = file_get_contents(__DIR__ . '/../views/ops/users.php');
t_ok(strpos($reg, 'user_has_own_access($u)') !== false, 'the user register marks people with their own access');
