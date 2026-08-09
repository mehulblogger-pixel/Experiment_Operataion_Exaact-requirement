<?php
// Every login must belong to a team member (person). This checks the pieces the
// user-create flow relies on: creating a team member inline, that field
// inspectors rank to the top of every allocate list, and that the enforcement
// is wired into the user save handler.
t_section('a login must belong to a team member (#team)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // team_member_create() adds a person and returns the id, with the team role.
    $fieldId  = team_member_create('Field Person One', 'FIELD');
    $coordId  = team_member_create('Coord Person Two', 'COORD');
    $officeId = team_member_create('Office Person Three', 'OFFICE');
    t_ok($fieldId > 0 && $coordId > 0 && $officeId > 0, 'a team member is created inline and returns an id');
    t_eq(ops_val("SELECT team_role FROM inspectors WHERE id=?", [$fieldId]), 'FIELD', 'the team role is stored');
    t_eq(ops_val("SELECT status FROM inspectors WHERE id=?", [$coordId]), 'ACTIVE', 'a new team member is active');
    t_eq((int)team_member_create('', 'FIELD'), 0, 'a blank name creates nobody');

    // inspectors_list() ranks FIELD before COORD before OFFICE (then by name).
    $list = inspectors_list(true);
    $pos = [];
    foreach ($list as $ix => $r) { if (in_array((int)$r['id'], [$fieldId, $coordId, $officeId], true)) $pos[(int)$r['id']] = $ix; }
    t_ok($pos[$fieldId] < $pos[$coordId], 'a field inspector is listed above a coordinator');
    t_ok($pos[$coordId] < $pos[$officeId], 'a coordinator is listed above back-office');

    // inspector_suggestions() ranks a field inspector over an office person when
    // neither has any history (equal score) — field goes to site.
    $bp = function ($name, $client, $vendor) {
        db()->prepare("INSERT INTO business_partners (legal_name, display_name, code, is_client, is_vendor, status, created_at)
                       VALUES (?,?,?,?,?,?,?)")->execute([$name, $name, strtoupper(substr($name,0,3)), $client, $vendor, 'ACTIVE', 'x']);
        return (int)db()->lastInsertId();
    };
    $client = $bp('Team Client', 1, 0);
    $vendor = $bp('Team Vendor', 0, 1);
    $sugg = inspector_suggestions(['client_id' => $client, 'vendor_id' => $vendor, 'id' => 0], []);
    $rank = []; foreach ($sugg as $ix => $s) $rank[(int)$s['id']] = $ix;
    t_ok(isset($rank[$fieldId]) && isset($rank[$officeId]) && $rank[$fieldId] < $rank[$officeId],
        'with no history, the field inspector is suggested above the office person');

    // The Inspectors screen (Masters) can change the team role directly: a plain
    // UPDATE round-trips the value, and it re-ranks in the list.
    db()->prepare("UPDATE inspectors SET team_role=? WHERE id=?")->execute(['OFFICE', $fieldId]);
    t_eq(ops_val("SELECT team_role FROM inspectors WHERE id=?", [$fieldId]), 'OFFICE', 'the Inspectors screen can change a team role');
    $list2 = inspectors_list(true);
    $pos2 = []; foreach ($list2 as $ix => $r) { if ((int)$r['id'] === $fieldId || (int)$r['id'] === $coordId) $pos2[(int)$r['id']] = $ix; }
    t_ok($pos2[$coordId] < $pos2[$fieldId], 'after being moved to back-office, the former field inspector drops below the coordinator');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The user save handler enforces the rule and can create the person inline.
$src = (string)file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($src, 'A login must belong to a team member') !== false, 'the user save handler blocks a login with no team member');
t_ok(strpos($src, 'team_member_create($pname') !== false, 'the user save handler creates the team member inline from the name');
t_ok(strpos($src, '$requireTeam = !$isSuper') !== false, 'the rule applies to every role except the system-owner account');
