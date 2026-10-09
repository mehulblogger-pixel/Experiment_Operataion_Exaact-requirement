<?php
// ============================================================================
//  ONE NAME, ONE PLACE — on BOTH permission screens                   (R-14)
// ============================================================================
//  There are two grouping maps, and the first fix only changed one:
//    module_groups()          -> /access, the ROLE editor
//    permission_nav_groups()  -> /user-edit, the PER-USER editor
//  Recruitment got its own heading on the role screen while the per-user screen
//  still filed the module under Operations and dropped its three permissions
//  into "Other". A business UAT screenshot showed it. This file asks both maps
//  the same questions, so a heading can never again be right on one screen and
//  wrong on the other.
// ============================================================================
t_section('Recruitment is its own heading on BOTH screens');

$role = module_groups();
$user = permission_nav_groups();

t_ok(isset($role['Recruitment']), '*** the role screen has a Recruitment heading');
t_ok(isset($user['Recruitment']), '*** the per-user screen has a Recruitment heading');

t_ok(in_array('hiring', $role['Recruitment'] ?? [], true),
     '*** the role screen files the recruitment module under Recruitment');
t_ok(in_array('mod.hiring.view', $user['Recruitment'] ?? [], true),
     '*** the per-user screen files the recruitment module under Recruitment');

t_section('It is no longer filed under Operations on either screen');
t_ok(!in_array('hiring', $role['Operations'] ?? [], true),
     '*** the role screen no longer hides recruitment under Operations');
t_ok(!in_array('mod.hiring.view', $user['Operations'] ?? [], true),
     '*** the per-user screen no longer hides recruitment under Operations');

t_section('Every recruitment permission is claimed by a heading — none fall into "Other"');
//  The view puts anything no group claims under "Other". That is a safety net,
//  not a home: a permission landing there is invisible to somebody looking for
//  it by name, which is what the tester reported.
$claimed = [];
foreach ($user as $keys) foreach ($keys as $k) $claimed[$k] = true;
foreach (['hiring.admin', 'hiring.material_change.propose', 'hiring.review.clear'] as $k) {
    t_ok(isset(PERMISSIONS[$k]), "the permission $k exists in the catalogue");
    t_ok(isset($claimed[$k]), "*** $k is claimed by a heading, so it cannot fall into Other");
}

t_section('No permission in the catalogue is orphaned on the per-user screen');
//  Stated as the current count rather than "none", because some permissions are
//  deliberately ungrouped. If this number moves, somebody added a permission and
//  did not say where it belongs — which is how the recruitment three got lost.
$orphans = array_values(array_filter(array_keys(PERMISSIONS), fn($k) => !isset($claimed[$k])));
sort($orphans);
echo '  ungrouped today (' . count($orphans) . '): ' . (implode(', ', $orphans) ?: 'none') . "\n";
t_ok(count($orphans) <= 12, 'the ungrouped set has not grown unnoticed (' . count($orphans) . ')');

t_section('Both permission screens draw the grid from ONE partial');

// R-14 happened because two permission screens drifted apart until recruitment
// had one name on one and another on the other, and the owner read them side by
// side and concluded the system held two different objects. The six-verb grid is
// a single partial for exactly that reason. If a future change inlines a copy
// into either screen, the drift can start again — so this asserts both screens
// include the shared file and neither carries its own table.
$role = file_get_contents(__DIR__ . '/../views/ops/access.php');
$user = file_get_contents(__DIR__ . '/../views/ops/user_form.php');

t_ok(strpos($role, "_perm_verb_grid.php") !== false,
     '*** the role editor includes the shared verb grid');
t_ok(strpos($user, "_perm_verb_grid.php") !== false,
     '*** the per-user editor includes the shared verb grid');
foreach (['access.php' => $role, 'user_form.php' => $user] as $name => $src)
    t_ok(strpos($src, '<table class="dt vgrid">') === false,
         "*** $name has no inlined copy of the grid table");

// The partial must cover every verb the vocabulary defines, or a right would
// exist that no screen can grant — invisible, and impossible to diagnose.
$partial = file_get_contents(__DIR__ . '/../views/ops/_perm_verb_grid.php');
t_ok(strpos($partial, 'foreach (PERM_VERBS as $v)') !== false,
     '*** the grid draws a column for every verb in the vocabulary, not a fixed list');
t_ok(strpos($partial, 'perm_verb_key($k, $v)') !== false,
     '*** each cell posts the right perm_verb_key() resolves, never a hand-built string');
