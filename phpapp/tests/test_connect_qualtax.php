<?php
// Connect K13 / backlog #2 — the qualification & role taxonomy (additive masters,
// cx_*). The ITI→MBA ladder that generalises the marketplace beyond inspection.
// Asserts the master tables seed to the expected counts, reseeding is idempotent
// (insert-if-empty), inspection is present as one vertical among many, the ladder
// spans blue-collar to postgraduate, and the additive profile columns exist.
t_section('connect qualification taxonomy (#2)');

// boot() already migrated + seeded on the fresh test DB; make it explicit and
// prove a second call is a harmless no-op.
connect_qualtax_seed();
$s = connect_qualtax_summary();

t_eq(20, $s['families'],       '20 job families seeded');
t_eq(52, $s['roles'],          '52 roles seeded');
t_eq(20, $s['levels'],         '20 qualification levels seeded');
t_eq(28, $s['iti_trades'],     '28 ITI trades seeded');
t_eq(30, $s['certifications'], '30 professional certifications seeded');
t_ok($s['version'] !== '',     'a qualification-taxonomy version is recorded');

// Idempotency — seeding again must not duplicate.
connect_qualtax_seed();
connect_qualtax_seed();
$s2 = connect_qualtax_summary();
t_eq(20, $s2['families'], 're-seed does not duplicate job families');
t_eq(52, $s2['roles'],    're-seed does not duplicate roles');
t_eq(30, $s2['certifications'], 're-seed does not duplicate certifications');

// The ladder is real and spans the full spectrum ITI → MBA → doctorate.
$bands = db()->query("SELECT DISTINCT band FROM cx_qualification_levels")->fetchAll(PDO::FETCH_COLUMN);
foreach (['ITI', 'DIPLOMA', 'DEGREE', 'PG', 'DOCTORATE'] as $b)
    t_ok(in_array($b, $bands, true), "the ladder includes the $b band");
$mba = db()->query("SELECT nsqf_level FROM cx_qualification_levels WHERE code='MBA'")->fetchColumn();
t_eq(8, (int)$mba, 'MBA sits at NSQF level 8');
$iti = db()->query("SELECT nsqf_level FROM cx_qualification_levels WHERE code='ITI_NCVT'")->fetchColumn();
t_ok((int)$iti > 0 && (int)$iti < (int)$mba, 'ITI sits below MBA on the same scale');

// Inspection is present as ONE vertical (not the whole ontology), and other
// families the platform never had before are there too.
$fam = db()->query("SELECT code FROM cx_job_families")->fetchAll(PDO::FETCH_COLUMN);
t_ok(in_array('INSP', $fam, true),  'inspection is a job family (the founding vertical)');
t_ok(in_array('MGMT', $fam, true),  'management/MBA roles are a job family (beyond inspection)');
t_ok(in_array('HSE',  $fam, true),  'HSE is a job family');
t_ok(count($fam) > 10, 'the taxonomy spans many families, not just inspection');

// Roles resolve under their family, blue-collar to white-collar.
$inspRoles = connect_qtx_roles_for_family('INSP');
t_ok(count($inspRoles) >= 4, 'the inspection family carries several roles');
$welder = db()->query("SELECT min_qual_band FROM cx_roles WHERE code='WELDER_R'")->fetchColumn();
t_eq('ITI', $welder, 'a welder enters at ITI level');
$pm = db()->query("SELECT min_qual_band FROM cx_roles WHERE code='PROJ_MGR'")->fetchColumn();
t_eq('DEGREE', $pm, 'a project manager enters at degree level');

// ITI trades and certifications are concrete, spanning trades and bodies.
$fitter = db()->query("SELECT category FROM cx_iti_trades WHERE code='FITTER'")->fetchColumn();
t_eq('Mechanical', $fitter, 'the Fitter ITI trade is categorised Mechanical');
$pmp = db()->query("SELECT body FROM cx_prof_certifications WHERE code='PMP'")->fetchColumn();
t_ok(stripos((string)$pmp, 'PMI') !== false, 'PMP maps to PMI (a non-inspection certification)');
$cswip = db()->query("SELECT domain FROM cx_prof_certifications WHERE code='CSWIP_3_1'")->fetchColumn();
t_ok(stripos((string)$cswip, 'Welding') !== false, 'CSWIP is a welding-inspection certification');

// The additive profile-capture columns exist on the professional (no new table).
$cols = [];
foreach (db()->query("PRAGMA table_info(cx_professionals)")->fetchAll(PDO::FETCH_ASSOC) as $c) $cols[$c['name']] = true;
foreach (['job_family_code', 'role_code', 'qual_level_code', 'iti_trade_code', 'cert_codes', 'years_experience'] as $col)
    t_ok(isset($cols[$col]), "cx_professionals gained the additive column $col");

// The options helper returns every layer for the profile/requirement selects.
$opt = connect_qualtax_options();
t_ok(!empty($opt['families']) && !empty($opt['roles']) && !empty($opt['levels'])
     && !empty($opt['iti_trades']) && !empty($opt['certs']), 'options helper returns all five layers');

// --- Configurability: the taxonomy is runtime data, not hard-coded -----------
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // Seeded rows are marked built-in (is_system=1), so only Super Admin deletes them.
    $sys = (int)db()->query("SELECT is_system FROM cx_job_families WHERE code='INSP'")->fetchColumn();
    t_eq(1, $sys, 'a seeded job family is marked built-in (is_system=1)');

    // Add a brand-new family (admin-created → is_system=0, freely deletable).
    [$ok, $msg] = connect_qualtax_save('family', 0, ['code' => 'robotics', 'name' => 'Robotics & Automation', 'detail' => 'Industrial robots', 'nsqf_min' => 5, 'nsqf_max' => 8]);
    t_ok($ok, 'an admin can add a new job family');
    $newId = (int)db()->query("SELECT id FROM cx_job_families WHERE code='ROBOTICS'")->fetchColumn();
    t_ok($newId > 0, 'the new family is stored with a normalised UPPER_SNAKE code');
    t_eq(0, (int)db()->query("SELECT is_system FROM cx_job_families WHERE id=$newId")->fetchColumn(), 'an admin-added family is not built-in');

    // Duplicate code is rejected.
    [$dup] = connect_qualtax_save('family', 0, ['code' => 'ROBOTICS', 'name' => 'Dupe']);
    t_ok(!$dup, 'a duplicate code is rejected');

    // Missing required field is rejected.
    [$bad, $badmsg] = connect_qualtax_save('family', 0, ['code' => '', 'name' => '']);
    t_ok(!$bad, 'a missing required field is rejected');

    // Edit the new family.
    [$eok] = connect_qualtax_save('family', $newId, ['code' => 'ROBOTICS', 'name' => 'Robotics, Automation & Mechatronics', 'detail' => '', 'nsqf_min' => 5, 'nsqf_max' => 8]);
    t_ok($eok, 'an admin can edit a family');
    t_eq('Robotics, Automation & Mechatronics', (string)db()->query("SELECT name FROM cx_job_families WHERE id=$newId")->fetchColumn(), 'the edit persisted');

    // Add a role under the new family; unknown family is rejected.
    [$rok] = connect_qualtax_save('role', 0, ['code' => 'robo_tech', 'family_code' => 'ROBOTICS', 'name' => 'Robotics Technician', 'min_qual_band' => 'ITI']);
    t_ok($rok, 'an admin can add a role under a family');
    [$rbad] = connect_qualtax_save('role', 0, ['code' => 'ghost', 'family_code' => 'NOPE', 'name' => 'Ghost']);
    t_ok(!$rbad, 'a role pointing at a non-existent family is rejected');

    // Switch off (soft) — drops from the live options but stays in the table.
    $beforeActive = count(connect_qtx_rows('cx_job_families'));
    [$tok, $tmsg] = connect_qualtax_toggle('family', $newId);
    t_ok($tok, 'an admin can switch a family off');
    t_eq(0, (int)db()->query("SELECT is_active FROM cx_job_families WHERE id=$newId")->fetchColumn(), 'the family is now inactive');
    $afterActive = count(connect_qtx_rows('cx_job_families'));
    t_eq($beforeActive - 1, $afterActive, 'a switched-off family drops from the live (active-only) list');
    t_ok(count(connect_qtx_rows('cx_job_families', 'sort_order, id', false)) > $afterActive, 'but it is still present for the admin editor (all rows)');

    // A family with roles is switched off rather than deleted (no orphans).
    connect_qualtax_toggle('family', $newId); // back on
    [$dok, $dmsg] = connect_qualtax_delete('family', $newId);
    t_ok($dok && stripos($dmsg, 'switched off') !== false, 'deleting a family that still has roles switches it off instead');

    // Remove the role, then the family truly deletes (it is admin-created, not built-in).
    $roleId = (int)db()->query("SELECT id FROM cx_roles WHERE code='ROBO_TECH'")->fetchColumn();
    connect_qualtax_delete('role', $roleId);
    connect_qualtax_toggle('family', $newId); // ensure active again
    [$d2] = connect_qualtax_delete('family', $newId);
    t_ok($d2, 'an admin-created family with no roles can be deleted');
    t_eq(0, (int)db()->query("SELECT COUNT(*) FROM cx_job_families WHERE id=$newId")->fetchColumn(), 'the family is gone');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
