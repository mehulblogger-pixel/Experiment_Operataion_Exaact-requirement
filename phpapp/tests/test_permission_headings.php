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
