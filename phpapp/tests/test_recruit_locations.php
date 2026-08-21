<?php
// Recruitment requirement: multiple locations, CV tagging to a location, and a
// quick "add client" when there is no PO yet.
t_section('requirement multi-location + CV tagging');

if (function_exists('req_migrate')) req_migrate();   // ensure the locations column exists
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO requisitions (req_code, designation, locations, status, created_at) VALUES (?,?,?,?,?)")
        ->execute(['REQ-TEST-1', 'WELDING_INSPECTOR', "Dahej — 3\nHazira — 2\nMundra — 1", 'OPEN', 'x']);
    $rid = (int)db()->lastInsertId();
    $loc = (string)ops_val("SELECT locations FROM requisitions WHERE id=?", [$rid]);
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $loc))));
    t_ok(count($lines) === 3 && $lines[0] === 'Dahej — 3',
        'a requirement stores multiple locations, one per line');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

$ops = (string)file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "'project_site','locations'") !== false, 'locations is a saved requisition field');
t_ok(strpos($ops, "'reqLocations' => \$reqLocations") !== false, 'the candidate form is given each requirement’s locations');

$rf = (string)file_get_contents(__DIR__ . '/../views/ops/requisition_form.php');
t_ok(strpos($rf, 'name="locations"') !== false, 'the requirement form takes multiple locations');
t_ok(strpos($rf, 'data-qa="client"') !== false, 'the requirement form can add a new client on the spot (no PO needed)');

$cf = (string)file_get_contents(__DIR__ . '/../views/ops/candidate_form.php');
t_ok(strpos($cf, 'reqLocList') !== false && strpos($cf, 'REQ_LOCS') !== false,
    'a received CV can be tagged to the requirement’s location');

$rec = (string)file_get_contents(__DIR__ . '/../lib/recruit.php');
t_ok(strpos($rec, "['locations',\"TEXT\"]") !== false, 'the requisitions table gains a locations column');
