<?php
// Connect #1 (unify the talent pool) — the marketplace recommender and the
// Passport now span BOTH pools: internal inspectors AND self-registered
// professionals. Asserts a self-registered professional appears in
// recommendations (the "recommender ignores freelancers" bug), scores on
// skills, its Passport resolves, and applying from a card records it correctly.
t_section('connect unify talent pool (#1)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // A self-registered professional whose skills fit a welding job.
    connect_pro_register(['name' => 'Freelance Farah', 'email' => 'farah@example.com', 'password' => 'secret12']);
    $pid = connect_pro_id();
    connect_pro_profile_save($pid, ['name' => 'Freelance Farah', 'headline' => 'Welding Inspector, CSWIP 3.1',
        'skills' => 'Welding inspection, NDT (UT/RT)', 'disciplines' => ['WELD', 'NDT'], 'availability' => 'AVAILABLE']);
    $tok = ops_val("SELECT passport_token FROM cx_professionals WHERE id=?", [$pid]);

    // A welding requirement.
    $rid = cx_requirement_create(['title' => 'Welding inspector for pressure-vessel FAT', 'discipline_code' => 'WELD',
        'description' => 'Welding and NDT witness'], true);
    $req = cx_requirement_get($rid);

    // --- the recommender now includes the freelancer pool ------------------
    $recs = connect_match_for_requirement($req, 20);
    $proRow = null;
    foreach ($recs as $r) if (($r['kind'] ?? '') === 'professional' && (int)$r['id'] === $pid) $proRow = $r;
    t_ok($proRow !== null, 'a self-registered professional appears in recommendations');
    t_eq('professional', $proRow['kind'], 'the candidate is tagged as a professional');
    t_ok($proRow['parts']['skills'] > 0, 'the professional scores on skills overlap');
    t_eq('UNVERIFIED', $proRow['eligibility'], 'an unverified professional shows as UNVERIFIED, not blocked');
    t_eq(0, (int)$proRow['verified'], 'a professional has no verified credentials yet (honest)');

    // --- the Passport resolves for a professional --------------------------
    $found = connect_passport_lookup($tok);
    t_ok($found && ($found['_kind'] ?? '') === 'professional', 'the professional\'s passport token resolves as a professional');
    $pub = connect_passport_public_data($found);
    t_eq('Freelance Farah', $pub['name'], 'the professional passport carries the name');
    t_eq(0, (int)$pub['cred_total'], 'a new professional passport shows no credentials yet');
    t_ok($pub['trust'] === null, 'a new professional has no Trust Score panel yet');

    // --- applying from the card records the professional + excludes them ----
    $aid = cx_application_add($rid, ['applicant_professional_id' => $pid, 'applicant_name' => 'Freelance Farah']);
    t_ok($aid > 0, 'the professional can be added to the shortlist from a card');
    $recs2 = connect_match_for_requirement($req, 20);
    $stillThere = false;
    foreach ($recs2 as $r) if (($r['kind'] ?? '') === 'professional' && (int)$r['id'] === $pid) $stillThere = true;
    t_ok(!$stillThere, 'an already-applied professional drops out of recommendations');

    // --- inspectors still score fully (backward compatible) ----------------
    db()->prepare("INSERT INTO inspectors (name,skills,status,created_at) VALUES ('Staff Welder','Welding inspection','ACTIVE',?)")->execute([date('c')]);
    $recs3 = connect_match_for_requirement($req, 20);
    $hasInsp = false;
    foreach ($recs3 as $r) if (($r['kind'] ?? '') === 'inspector' && $r['name'] === 'Staff Welder') $hasInsp = true;
    t_ok($hasInsp, 'internal inspectors still appear alongside professionals');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
