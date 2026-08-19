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

// ---------------------------------------------------------------------------
t_section('KYC document kinds on a person (gap 2)');
identity_migrate();

$kinds = iddoc_kind_options();
t_ok(isset($kinds['TAX_ID'], $kinds['DECLARATION'], $kinds['EDUCATION']),
    'PAN (tax id), declaration and education are available document kinds');
t_ok(iddoc_kind_expires('PASSPORT') === true && iddoc_kind_expires('TAX_ID') === false,
    'a passport carries an expiry; a PAN does not');
t_ok(iddoc_kind_has_number('TAX_ID') === true && iddoc_kind_has_number('DECLARATION') === false,
    'a PAN carries a number; a signed declaration does not');

$own2 = !db()->inTransaction();
if ($own2) db()->beginTransaction();
try {
    $kp = team_member_create('KYC Person One', 'FIELD');
    t_eq(iddoc_add($kp, ['doc_kind' => 'DECLARATION', 'consent_on' => date('Y-m-d')], null), '',
        'a signed declaration files with no number and no expiry');
    t_eq(iddoc_add($kp, ['doc_kind' => 'TAX_ID', 'doc_number' => 'ABCDE1234F', 'consent_on' => date('Y-m-d')], null), '',
        'a PAN files with a number and no expiry');
    t_ok(iddoc_add($kp, ['doc_kind' => 'PASSPORT', 'doc_number' => 'P1234567', 'consent_on' => date('Y-m-d')], null) !== '',
        'a passport still demands an expiry date (the old discipline is intact)');
    t_ok(count(iddoc_list($kp)) === 2, 'the two KYC documents are held against the person');
} finally {
    if ($own2 && db()->inTransaction()) db()->rollBack();
}

t_ok(strpos($form, '/identity?i=') !== false, 'the inspector record links to that person’s documents');

// ---------------------------------------------------------------------------
t_section('document completeness checklist (gap 3)');
identity_migrate();

$def = person_req_docs();
t_ok(in_array('TAX_ID', $def, true) && in_array('DECLARATION', $def, true),
    'the default required set includes PAN and a declaration');

$own3 = !db()->inTransaction();
if ($own3) db()->beginTransaction();
try {
    $fp = team_member_create('Roster Person One', 'FIELD');
    // Nothing on file yet → everything missing, not complete.
    $s0 = person_docs_summary($fp);
    t_ok($s0['total'] === count($def) && $s0['have'] === 0 && !$s0['complete'],
        'a person with no papers is flagged incomplete, everything missing');

    // File a PAN and a declaration → those two show present.
    iddoc_add($fp, ['doc_kind' => 'TAX_ID', 'doc_number' => 'ABCDE1234F', 'consent_on' => date('Y-m-d')], null);
    iddoc_add($fp, ['doc_kind' => 'DECLARATION', 'consent_on' => date('Y-m-d')], null);
    $s1 = person_docs_summary($fp);
    t_ok($s1['rows']['TAX_ID']['ok'] === true && $s1['rows']['DECLARATION']['ok'] === true,
        'the filed PAN and declaration count as present');
    t_ok($s1['have'] === 2 && $s1['need'] === ($s1['total'] - 2),
        'the checklist counts two on file and the rest missing');

    // An expired expiring-doc does not count as present.
    iddoc_add($fp, ['doc_kind' => 'NATIONAL_ID', 'doc_number' => 'X1', 'expires_on' => '2000-01-01', 'consent_on' => date('Y-m-d')], null);
    $s2 = person_docs_summary($fp);
    t_ok($s2['rows']['NATIONAL_ID']['ok'] === false, 'an expired national ID does not satisfy the checklist');
} finally {
    if ($own3 && db()->inTransaction()) db()->rollBack();
}

$ops2 = (string)file_get_contents(__DIR__ . '/../lib/identity.php');
t_ok(strpos($ops2, "\$route === 'agency-staff'") !== false, 'the agency-staff roster route exists');
t_ok(is_file(__DIR__ . '/../views/ops/agency_staff.php'), 'the agency-staff roster view exists');
t_ok(strpos($form, 'Document checklist') !== false, 'the inspector form shows the document checklist');
