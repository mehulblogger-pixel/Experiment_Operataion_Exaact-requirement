<?php
// People brought in through the Excel register used to land in `users` only —
// they showed under People but were absent from the inspection-allocate list,
// which reads the team-member (`inspectors`) table. The register is meant to be
// a single source that flows through to allocation, so an imported login must
// gain a linked team-member row. Two guards: the import links on the way in,
// and a self-heal links any inspector-role login that slipped through.
t_section('imported people flow through to inspection allocation');

// --- The import links every non-master login to a team member ---------------
$rows = [
    ['skip' => 0, 'user_id' => 0, 'username' => 'ravi.field', 'first_name' => 'Ravi', 'last_name' => 'Kumar',
     'email' => 'ravi@example.com', 'role' => 'INSPECTOR', 'is_superuser' => 0, 'active' => 1, 'office_id' => 0,
     'position_title' => 'Inspector', 'sbu' => '', 'weekly_working_days' => 5, 'password' => '', 'reports_to_raw' => ''],
    ['skip' => 0, 'user_id' => 0, 'username' => 'meena.coord', 'first_name' => 'Meena', 'last_name' => 'Rao',
     'email' => 'meena@example.com', 'role' => 'COORDINATOR', 'is_superuser' => 0, 'active' => 1, 'office_id' => 0,
     'position_title' => 'Coordinator', 'sbu' => '', 'weekly_working_days' => 5, 'password' => '', 'reports_to_raw' => ''],
];
$res = org_import_apply($rows);
t_eq((int)$res['created'], 2, 'both imported people are created');

$ravi = ops_one("SELECT id, inspector_id FROM users WHERE username='ravi.field'");
t_ok(is_array($ravi) && (int)$ravi['inspector_id'] > 0, 'the imported inspector is linked to a team-member row');
$ins = ops_one("SELECT name, team_role FROM inspectors WHERE id=?", [(int)$ravi['inspector_id']]);
t_ok(is_array($ins), 'the linked team-member row exists');
t_eq((string)$ins['name'], 'Ravi Kumar', 'the team member carries the imported name');
t_eq((string)$ins['team_role'], 'FIELD', 'an inspector sits in the field pool');

$meena = ops_one("SELECT inspector_id FROM users WHERE username='meena.coord'");
$mins = ops_one("SELECT team_role FROM inspectors WHERE id=?", [(int)$meena['inspector_id']]);
t_eq((string)$mins['team_role'], 'COORD', 'a coordinator sits in the coordinator pool');

// The imported inspector is now offered by the allocate list (its single source).
$names = array_column(inspectors_list(true), 'name');
t_ok(in_array('Ravi Kumar', $names, true), 'the imported inspector appears in the allocate list');

// --- The self-heal covers a login that slipped in without a link ------------
db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active)
    VALUES (?,?,?,?,?,?,0,1)")
    ->execute(['stray.insp', password_hash('x', PASSWORD_DEFAULT), 'Stray', 'Inspector', 'stray@example.com', 'INSPECTOR']);
$strayId = (int) db()->lastInsertId();
t_eq((int)ops_val("SELECT COALESCE(inspector_id,0) FROM users WHERE id=?", [$strayId]), 0, 'the stray login starts unlinked');

link_inspector_users();
$linked = (int) ops_val("SELECT COALESCE(inspector_id,0) FROM users WHERE id=?", [$strayId]);
t_ok($linked > 0, 'the self-heal links the stray inspector login');
$sname = (string) ops_val("SELECT name FROM inspectors WHERE id=?", [$linked]);
t_eq($sname, 'Stray Inspector', 'the healed team member carries the login name');

// Idempotent — a second pass links nobody new and creates no duplicate.
$before = (int) ops_val("SELECT COUNT(*) FROM inspectors");
link_inspector_users();
$after = (int) ops_val("SELECT COUNT(*) FROM inspectors");
t_eq($after, $before, 'running the heal again is a no-op (single source, no duplicates)');
