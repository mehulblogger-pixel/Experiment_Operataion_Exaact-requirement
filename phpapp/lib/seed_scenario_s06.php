<?php
// ============================================================================
//  DEMO-S06 — Gap-closure showcase (the eight Stage-0 residual gaps).
//
//  Sixth in the progressive program (S01 freelancer · S02 agency · S03 client ·
//  S04 marketplace lifecycle · S05 convergence detectors → S06 the gap closures).
//  One namespaced thread that lights up every gap closed in the controlled
//  gap-closure program, each asserted from live data:
//    1 deploy→engagement/finance spine · 2 taxonomy in inspector matching ·
//    3 engagement status machine · 4 shift-aware conflict · 5 one credential
//    ladder · 6 duplicate-requirement detection · 7 partial billing ·
//    8 unified person resolver.
//  Idempotent (purge-first), on existing tables, with a real 10-point dashboard.
// ============================================================================

function seed_s06_status() {
    return [
        'loaded' => function_exists('setting_get') ? (bool)setting_get('demo_s06_seed') : false,
        'jobs'   => (int)ops_val("SELECT COUNT(*) FROM jobs WHERE job_code LIKE 'DEMO-S06-%'"),
        'pros'   => (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE email LIKE '%s06pro@demo.test'"),
        'reqs'   => (int)ops_val("SELECT COUNT(*) FROM cx_requirements WHERE poster_name='DEMO-S06'"),
    ];
}

function seed_s06_remove() {
    $n = 0;
    $del = function ($sql, $args = []) use (&$n) { try { $st = db()->prepare($sql); $st->execute($args); $n += $st->rowCount(); } catch (Throwable $e) {} };
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM cx_professionals WHERE email LIKE '%s06pro@demo.test'") ?: []) as $pid) {
        $del("DELETE FROM cx_pro_certs WHERE pro_id=?", [$pid]);
        $del("DELETE FROM cx_identity_link WHERE professional_id=?", [$pid]);
        $del("DELETE FROM cx_engagements WHERE subject_kind='professional' AND subject_id=?", [$pid]);
        $del("DELETE FROM cx_applications WHERE applicant_professional_id=?", [$pid]);
        $del("DELETE FROM cx_professionals WHERE id=?", [$pid]);
    }
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM inspectors WHERE name LIKE 'DEMO-S06 %'") ?: []) as $iid) {
        $del("DELETE FROM inspector_certs WHERE inspector_id=?", [$iid]);
        $del("DELETE FROM cx_identity_link WHERE inspector_id=?", [$iid]);
        $del("DELETE FROM inspectors WHERE id=?", [$iid]);
    }
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM candidates WHERE cand_code LIKE 'DEMO-S06-%'") ?: []) as $cid) {
        $del("DELETE FROM cx_identity_link WHERE candidate_id=?", [$cid]);
        $del("DELETE FROM candidates WHERE id=?", [$cid]);
    }
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM jobs WHERE job_code LIKE 'DEMO-S06-%'") ?: []) as $jid) $del("DELETE FROM jobs WHERE id=?", [$jid]);
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM business_partners WHERE code LIKE 'DEMO-S06-%'") ?: []) as $bid) {
        $del("DELETE FROM cx_requirements WHERE poster_party_id=?", [$bid]);
        $del("DELETE FROM billable_events WHERE party_id=?", [$bid]);
    }
    $del("DELETE FROM cx_engagements WHERE poster_name='DEMO-S06'");
    $del("DELETE FROM engagements WHERE engagement_key LIKE 'DEMO-S06-%' OR engagement_key LIKE 'CXR-%DEMO-S06%'");
    $del("DELETE FROM billable_bills WHERE bill_ref LIKE 'DEMO-S06-%'");
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM cx_tax_nodes WHERE name='Zephyrline Inspection'") ?: []) as $tn) { $del("DELETE FROM cx_tax_aliases WHERE node_id=?", [$tn]); $del("DELETE FROM cx_tax_nodes WHERE id=?", [$tn]); }
    $del("DELETE FROM business_partners WHERE code LIKE 'DEMO-S06-%'");
    if (function_exists('setting_set')) setting_set('demo_s06_seed', '');
    return $n;
}

function seed_s06_load() {
    seed_s06_remove();
    try { db()->exec("SET SESSION sql_mode=''"); } catch (Throwable $e) {}
    $now = date('c'); $log = []; $say = function ($s) use (&$log) { $log[] = $s; };
    foreach (['connect_market_migrate','connect_deploy_migrate','engagement_migrate','connect_pro_migrate','connect_engage_migrate',
              'connect_identity_migrate','connect_cred_migrate','connect_tax_graph_migrate','billable_migrate','competence_migrate'] as $mg)
        if (function_exists($mg)) { try { $mg(); } catch (Throwable $e) {} }
    $D = date('Y-m-d', strtotime('+10 days'));
    $future = date('Y-m-d', strtotime('+2 years'));

    // A client.
    db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,status,created_at) VALUES ('DEMO-S06-CLIENT','DEMO-S06 Nova Energy Ltd','DEMO-S06 Nova Energy Ltd',1,'ACTIVE',?)")->execute([$now]);
    $client = (int)db()->lastInsertId();

    // === One person across three pools (Gaps 8, 5, 2) ===
    db()->prepare("INSERT INTO cx_professionals (email,name,mobile,skills,disciplines,is_active,verification_tier,created_at) VALUES ('farooq.s06pro@demo.test','Farooq Alam','9820060001','Zephyrline Inspection, CSWIP','Welding',1,'verified',?)")->execute([$now]);
    $pro = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspectors (name,skills,designation,status,sbu,created_at) VALUES ('DEMO-S06 Farooq Alam','Zephyrline Inspection, CSWIP','Inspector','ACTIVE','IND',?)")->execute([$now]);
    $insp = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO candidates (cand_code,first_name,last_name,mobile,email,stage,client_id,created_at) VALUES ('DEMO-S06-CAND','Farooq','Alam','9820060001','farooq.s06pro@demo.test','INTERVIEW',?,?)")->execute([$client, $now]);
    $cand = (int)db()->lastInsertId();
    // link them (pro hub); reversible, no merge
    db()->prepare("INSERT INTO cx_identity_link (professional_id,inspector_id,status,linked_at) VALUES (?,?, 'LINKED', ?)")->execute([$pro, $insp, $now]);
    db()->prepare("INSERT INTO cx_identity_link (professional_id,candidate_id,status,linked_at) VALUES (?,?, 'LINKED', ?)")->execute([$pro, $cand, $now]);
    // a verified cert in EACH pool → the unified ladder reads both
    db()->prepare("INSERT INTO cx_pro_certs (pro_id,name,expiry_date,verified) VALUES (?, 'CSWIP 3.1', ?, 1)")->execute([$pro, $future]);
    db()->prepare("INSERT INTO inspector_certs (inspector_id,name,valid_to,verify_status) VALUES (?, 'ASNT NDT II', ?, 'VERIFIED')")->execute([$insp, $future]);

    // taxonomy concept the inspector text resolves to (Gap 2)
    $node = function_exists('connect_tax_node_add') ? (int)connect_tax_node_add('ROLE', 'Zephyrline Inspection') : 0;

    // === Awarded requirement → deployment threaded into the spine (Gap 1) ===
    $rid = (int)cx_requirement_create(['title' => 'Zephyrline Inspection Engineer', 'poster_party_id' => $client, 'poster_name' => 'DEMO-S06',
        'discipline_code' => 'MECH', 'location' => 'Hazira', 'work_type' => 'FREELANCE', 'positions' => 1,
        'start_date' => $D, 'end_date' => date('Y-m-d', strtotime('+30 days')), 'rate_min' => 9000, 'rate_max' => 13000, 'rate_unit' => 'day'], true);
    $aid = (int)cx_application_add($rid, ['applicant_professional_id' => $pro, 'applicant_name' => 'Farooq', 'proposed_rate' => 10000]);
    db()->prepare("UPDATE cx_applications SET status='ACCEPTED' WHERE id=?")->execute([$aid]);
    db()->prepare("UPDATE cx_requirements SET status='AWARDED', awarded_application_id=?, updated_at=? WHERE id=?")->execute([$aid, $now, $rid]);
    // rename the deploy job code into our namespace after creation
    $deploy = function_exists('connect_deploy_from_engagement') ? connect_deploy_from_engagement($rid) : [false, '', 0];
    $depJob = (int)($deploy[2] ?? 0);
    if ($depJob) db()->prepare("UPDATE jobs SET job_code='DEMO-S06-DEP-1' WHERE id=?")->execute([$depJob]);

    // === Engagement status machine (Gap 3) ===
    db()->prepare("INSERT INTO cx_engagements (requirement_id,subject_kind,subject_id,subject_name,poster_party_id,poster_name,start_date,end_date,status,created_at) VALUES (?, 'professional',?, 'Farooq',?, 'DEMO-S06', ?, ?, 'BOOKED', ?)")
        ->execute([$rid, $pro, $client, $D, date('Y-m-d', strtotime('+30 days')), $now]);
    $engId = (int)db()->lastInsertId();

    // === Shift-aware conflict (Gap 4) — a dedicated subject with a DAY booking ===
    db()->prepare("INSERT INTO cx_professionals (email,name,mobile,is_active,created_at) VALUES ('shifttest.s06pro@demo.test','DEMO-S06 Shift Tester','9820060002',1,?)")->execute([$now]);
    $shiftPro = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_engagements (subject_kind,subject_id,subject_name,poster_name,start_date,end_date,status,shift,created_at) VALUES ('professional',?, 'Shift','DEMO-S06', ?, ?, 'BOOKED','DAY',?)")
        ->execute([$shiftPro, $D, $D, $now]);

    // === Duplicate-requirement (Gap 6) — an open requirement to be matched against ===
    cx_requirement_create(['title' => 'DEMO-S06 Piping Inspector at Dahej', 'poster_party_id' => $client, 'poster_name' => 'DEMO-S06',
        'discipline_code' => 'PIPE', 'location' => 'Dahej', 'work_type' => 'FREELANCE', 'positions' => 1,
        'start_date' => $D, 'end_date' => date('Y-m-d', strtotime('+25 days')), 'rate_min' => 8000, 'rate_max' => 12000, 'rate_unit' => 'day'], true);

    // === Partial billing (Gap 7) — an approved event, part-billed ===
    db()->prepare("INSERT INTO billable_events (source_module,source_kind,source_id,party_id,amount,derived_amount,status,created_at) VALUES ('timesheet','TIMESHEET_APPROVED',760601,?,1000,1000,'APPROVED',?)")->execute([$client, $now]);
    $billId = (int)db()->lastInsertId();
    if (function_exists('billable_bill_partial')) billable_bill_partial($billId, 400, 'DEMO-S06-INV-M1');

    $say('One person across 3 pools (pro+inspector+candidate, linked) · awarded requirement deployed · engagement + shift bookings');
    $say('Duplicate requirement · a part-billed event · a taxonomy concept for inspector matching');

    // ---- DASHBOARD (real, derived — one check per gap) ------------------------
    $depRow = $depJob ? ops_one("SELECT contract_number, engagement_id FROM jobs WHERE id=?", [$depJob]) : null;
    $reqNodes = function_exists('connect_match_req_nodes') ? connect_match_req_nodes(cx_requirement_get($rid)) : [];
    $taxBonus = function_exists('connect_match_tax_bonus_text') ? connect_match_tax_bonus_text('Zephyrline Inspection CSWIP', $reqNodes)[0] : 0;
    $badMove  = function_exists('connect_engage_can_transition') ? connect_engage_can_transition('COMPLETED', 'BOOKED') : true;
    $goodMove = function_exists('connect_engage_can_transition') ? connect_engage_can_transition('BOOKED', 'ACTIVE') : false;
    $shiftClear = function_exists('connect_conflict_check') ? connect_conflict_check($shiftPro, $D, $D, ['shift' => 'NIGHT'])['status'] : '?';
    $shiftClash = function_exists('connect_conflict_check') ? connect_conflict_check($shiftPro, $D, $D, ['shift' => 'DAY'])['status'] : '?';
    $inspCert = ops_one("SELECT * FROM inspector_certs WHERE inspector_id=? LIMIT 1", [$insp]) ?: [];
    $ladder = function_exists('connect_cred_verify_state') ? connect_cred_verify_state($inspCert)['code'] : '?';
    $dupes = function_exists('connect_requirement_duplicates') ? connect_requirement_duplicates(['poster_party_id' => $client, 'discipline_code' => 'PIPE', 'location' => 'Dahej', 'title' => 'Piping Inspector at Dahej site']) : [];
    $billRow = ops_one("SELECT amount, billed_amount, status FROM billable_events WHERE id=?", [$billId]) ?: [];
    $person = function_exists('connect_person_summary') ? connect_person_summary('candidate', $cand) : ['pools' => [], 'credentials' => 0];

    $dash = [
        ['Gap 1 — marketplace deployment threaded into the finance spine', $depRow && trim((string)$depRow['contract_number']) !== '' && (int)$depRow['engagement_id'] > 0],
        ['Gap 2 — taxonomy concept counts for inspector matching', (int)$taxBonus > 0],
        ['Gap 3 — an invalid engagement transition is refused', $badMove === false],
        ['Gap 3 — a valid engagement transition is allowed', $goodMove === true],
        ['Gap 4 — a different-shift booking is CLEAR on the same day', $shiftClear === 'CLEAR'],
        ['Gap 4 — a same-shift booking on the same day CONFLICTS', $shiftClash === 'CONFLICT'],
        ['Gap 5 — an inspector cert reads VERIFIED on the one ladder', $ladder === 'VERIFIED'],
        ['Gap 6 — a near-duplicate requirement is detected', count($dupes) >= 1],
        ['Gap 7 — a billable event is part-billed (400 of 1000, balance open)', ((float)($billRow['billed_amount'] ?? 0) == 400.0) && (($billRow['status'] ?? '') === 'APPROVED')],
        ['Gap 8 — one person resolves across all three pools with cross-pool credentials',
            ((int)($person['pools']['professional'] ?? 0) > 0) && ((int)($person['pools']['inspector'] ?? 0) > 0) && ((int)($person['pools']['candidate'] ?? 0) > 0) && ((int)($person['credentials'] ?? 0) >= 2)],
    ];
    $allpass = true; foreach ($dash as [$l, $ok]) if (!$ok) $allpass = false;
    if (function_exists('setting_set')) setting_set('demo_s06_seed', date('c'));
    return ['log' => $log, 'dashboard' => $dash, 'allpass' => $allpass];
}
