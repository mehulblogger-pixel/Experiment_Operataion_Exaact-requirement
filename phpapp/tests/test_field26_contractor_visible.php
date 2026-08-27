<?php
// Field-finding #26 — a contractor added as a (second) team member did not show in the allocate picker or
// the operational dashboard. Root cause: the team-member INSERT used `$b['status'] ?? 'ACTIVE'`, which does
// NOT coalesce an empty string — so a form posting status='' created a blank-status inspector, and every
// deputable surface filters on status='ACTIVE'. Fix: the insert coalesces empty → ACTIVE, and the lists
// treat a blank status as ACTIVE (a person with no explicit status is deputable). Genuinely-inactive
// (a real non-empty status) stays hidden.
t_section('Field #26 — a contractor / second team member shows for allocation & the dashboard');

$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
// The insert never creates a blank status.
t_ok(strpos($ops, "(\$b['status'] ?? '') ?: 'ACTIVE'") !== false, 'the team-member insert coalesces an empty status to ACTIVE');
// The deputable surfaces treat a blank status as ACTIVE.
t_ok(substr_count($ops, "COALESCE(NULLIF(status,''),'ACTIVE')='ACTIVE'") >= 3,
     'the inspector list and the dashboard capacity queries treat a blank status as active');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();
    $base = count(inspectors_list(true));

    // A contractor (SUBCON) with an ACTIVE status is deputable.
    $pdo->prepare("INSERT INTO inspectors (name, staff_kind, status) VALUES ('Con Active','SUBCON','ACTIVE')")->execute();
    // A contractor created with a BLANK status (the pre-fix data) must now surface too.
    $pdo->prepare("INSERT INTO inspectors (name, staff_kind, status) VALUES ('Ravi Contractor','SUBCON','')")->execute();
    // A genuinely INACTIVE person must stay hidden.
    $pdo->prepare("INSERT INTO inspectors (name, staff_kind, status) VALUES ('Left Us','ASSET','INACTIVE')")->execute();

    $names = array_column(inspectors_list(true), 'name');
    t_ok(in_array('Con Active', $names, true), 'an active contractor is in the allocate list');
    t_ok(in_array('Ravi Contractor', $names, true), 'a blank-status contractor is now in the allocate list (was hidden)');
    t_ok(!in_array('Left Us', $names, true), 'a genuinely inactive person stays hidden');
    t_eq(count(inspectors_list(true)), $base + 2, 'exactly the two deputable people were added to the list');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
