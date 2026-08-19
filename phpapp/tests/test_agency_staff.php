<?php
// Agency staff — a freelancer / sub-contractor recorded under a real agency,
// with their KYC documents and a completeness check. Built in three gaps; this
// file grows with them.
t_section('agency link on a person (gap 1)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO agencies (name, agency_type, active, created_at) VALUES (?,?,?,?)")
        ->execute(['Patel Manpower Services', 'MANPOWER', 1, 'x']);
    $agId = (int)db()->lastInsertId();

    t_ok($agId > 0, 'an agency can be created in the agencies master');
    $ag = agency_get($agId);
    t_ok($ag && $ag['name'] === 'Patel Manpower Services', 'agency_get() returns the agency by id');
    $listed = false; foreach (agencies_list() as $a) if ((int)$a['id'] === $agId) $listed = true;
    t_ok($listed, 'the agency appears in agencies_list() for the picker');

    // The person carries a real agency_id (link), not just a typed name.
    $p = team_member_create('Freelance Person One', 'FIELD');
    db()->prepare("UPDATE inspectors SET staff_kind='FREELANCER', agency_id=?, agency_name=? WHERE id=?")
        ->execute([$agId, $ag['name'], $p]);
    $row = ops_one("SELECT staff_kind, agency_id, agency_name FROM inspectors WHERE id=?", [$p]);
    t_ok((int)$row['agency_id'] === $agId, 'a freelancer stores the agency as a structured link (agency_id)');
    t_eq($row['agency_name'], 'Patel Manpower Services', 'the agency name is kept alongside for display / costing');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The save handler writes the link, and the form offers the agency picker.
$ops = (string)file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, 'agency_id=?,agency_name=?') !== false, 'the inspector save handler updates agency_id + agency_name');
t_ok(strpos($ops, '$ag = agency_get($agencyId)') !== false, 'the handler syncs the agency name from the chosen agency');
$form = (string)file_get_contents(__DIR__ . '/../views/ops/inspector_form.php');
t_ok(strpos($form, 'name="agency_id"') !== false, 'the inspector form offers an agency dropdown');
t_ok(strpos($form, 'name="agency_name"') === false, 'the free-text agency box is replaced by the dropdown');
