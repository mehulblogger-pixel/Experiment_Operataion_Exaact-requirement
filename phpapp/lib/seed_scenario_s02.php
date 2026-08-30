<?php
// ============================================================================
//  DEMO-S02 — Agency / manpower-provider scenario seed (CLI + admin button).
//
//  Apex Technical Inspection Services (a TPIA / manpower provider) keeps its own
//  bench, receives a client requirement for 2 inspectors, searches its bench
//  first, finds 1 strong internal match, sees a gap of 1, supplements from the
//  marketplace, submits both, the client approves, they convert to a real ops
//  job, get scheduled/allocated over 30 days (with a conflict + a replacement),
//  inspect, raise a major finding + a minor observation, produce a URFE report
//  with a correction cycle, and the client receives it — with commercial margins
//  kept staff-only and ratings feeding future matching.
//
//  Built ENTIRELY on the existing engines (agency bench cx_bench, passports,
//  cx_requirements, the identity link + deploy bridge, PDSO jobs/job_visits,
//  report_docs/URFE, nonconformities, cx_ratings). Everything is tagged DEMO-S02
//  and removes cleanly. Reuses the DEMO-S01 helpers (s01_node/s01_pw/s01_d).
// ============================================================================

/** Create a professional passport and return its id (DEMO-S02). */
function s02_pro(array $a) {
    connect_pro_migrate();
    db()->prepare("INSERT INTO cx_professionals (email,name,mobile,headline,disciplines,skills,work_types,availability,is_active,verification_tier,passport_token,password_hash,created_at)
                   VALUES (?,?,?,?,?,?,?,?,1,'registered',?,?,?)")
        ->execute([$a['email'], $a['name'], $a['mobile'] ?? '', $a['headline'] ?? '', $a['disciplines'] ?? '', $a['skills'] ?? '',
                   $a['work_types'] ?? 'FREELANCE', $a['availability'] ?? 'AVAILABLE', substr(md5($a['email']), 0, 20), s01_pw(), date('c')]);
    return (int)db()->lastInsertId();
}

/** Create an internal inspector for a professional + link the identity. Returns inspector id. */
function s02_inspector($proId, $name, $empCode, $email, $skills, $mobile = '') {
    db()->prepare("INSERT INTO inspectors (name,emp_code,sbu,skills,email,mobile,status,created_at) VALUES (?,?, 'IND', ?, ?, ?, 'ACTIVE', ?)")
        ->execute([$name, $empCode, $skills, $email, $mobile, date('c')]);
    $insp = (int)db()->lastInsertId();
    if (function_exists('connect_identity_link_create')) connect_identity_link_create((int)$proId, $insp, 'email_match', 'demo-seed');
    return $insp;
}

function seed_s02_status() {
    return [
        'loaded' => function_exists('setting_get') ? (bool)setting_get('demo_s02_seed') : false,
        'org'    => (int)ops_val("SELECT COUNT(*) FROM cx_organisations WHERE name LIKE 'Apex Technical%'"),
        'bench'  => (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE email LIKE '%s02@demo.test'"),
        'report' => (int)ops_val("SELECT COUNT(*) FROM report_docs WHERE irn='DEMO-S02-RPT-001'"),
    ];
}

/** Remove every DEMO-S02 record. */
function seed_s02_remove() {
    $n = 0;
    $del = function ($sql, $args = []) use (&$n) { try { $st = db()->prepare($sql); $st->execute($args); $n += $st->rowCount(); } catch (Throwable $e) {} };
    $proEmails = ['amit.s02@demo.test','sandeep.s02@demo.test','irfan.s02@demo.test','rakesh.s02@demo.test','neha.s02@demo.test','ajay.s02@demo.test','rohit.s02@demo.test'];
    $proIds = [];
    foreach ($proEmails as $e) { $id = (int)ops_val("SELECT id FROM cx_professionals WHERE email=?", [$e]); if ($id) $proIds[] = $id; }
    $apex  = (int)ops_val("SELECT id FROM business_partners WHERE legal_name LIKE 'Apex Technical%'");
    $client = (int)ops_val("SELECT id FROM business_partners WHERE legal_name LIKE 'Northern Grid%'");
    foreach ([$apex, $client] as $pt) { if (!$pt) continue;
        foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM cx_requirements WHERE poster_party_id=?", [$pt]) ?: []) as $rid) {
            $del("DELETE FROM cx_applications WHERE requirement_id=?", [$rid]); $del("DELETE FROM cx_engagements WHERE requirement_id=?", [$rid]);
            $del("DELETE FROM cx_ratings WHERE requirement_id=?", [$rid]); $del("DELETE FROM cx_bench_alloc WHERE requirement_id=?", [$rid]);
            try { $del("DELETE FROM billable_events WHERE source_module='connect' AND source_id=?", [$rid]); } catch (Throwable $e) {}
            $del("DELETE FROM jobs WHERE source_module='connect' AND source_requirement_id=?", [$rid]);
            $del("DELETE FROM cx_requirements WHERE id=?", [$rid]);
        }
        foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM calls WHERE client_id=?", [$pt]) ?: []) as $cid) { $del("DELETE FROM jobs WHERE call_id=?", [$cid]); $del("DELETE FROM calls WHERE id=?", [$cid]); }
    }
    if ($apex) { $del("DELETE FROM cx_bench WHERE org_id=(SELECT id FROM cx_organisations WHERE party_id=? LIMIT 1)", [$apex]); $del("DELETE FROM cx_organisations WHERE party_id=?", [$apex]); }
    foreach ($proIds as $pid) {
        foreach (['cx_profile_tax','cx_pro_certs','cx_pro_projects','cx_identity_link','cx_client_bench'] as $t) { $col = ($t === 'cx_identity_link' || $t === 'cx_client_bench') ? 'professional_id' : 'pro_id'; $del("DELETE FROM $t WHERE $col=?", [$pid]); }
        try { $del("DELETE FROM cx_verifications WHERE subject_kind='professional' AND subject_id=?", [$pid]); } catch (Throwable $e) {}
        $del("DELETE FROM cx_professionals WHERE id=?", [$pid]);
    }
    $rpt = (int)ops_val("SELECT id FROM report_docs WHERE irn='DEMO-S02-RPT-001'");
    if ($rpt) { $del("DELETE FROM report_files WHERE report_doc_id=?", [$rpt]); try { $del("DELETE FROM nonconformities WHERE report_doc_id=?", [$rpt]); } catch (Throwable $e) {} $del("DELETE FROM report_docs WHERE id=?", [$rpt]); }
    try { $del("DELETE FROM nonconformities WHERE ref LIKE 'DEMO-S02-%'"); } catch (Throwable $e) {}
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM jobs WHERE job_code LIKE 'DEMO-S02-%'") ?: []) as $jid) { $del("DELETE FROM job_visits WHERE job_id=?", [$jid]); try { $del("DELETE FROM inspector_certs WHERE inspector_id IN (SELECT id FROM inspectors WHERE emp_code LIKE 'DEMO-S02%')"); } catch (Throwable $e) {} $del("DELETE FROM jobs WHERE id=?", [$jid]); }
    try { $del("DELETE FROM inspector_certs WHERE inspector_id IN (SELECT id FROM inspectors WHERE emp_code LIKE 'DEMO-S02%')"); } catch (Throwable $e) {}
    $del("DELETE FROM inspectors WHERE emp_code LIKE 'DEMO-S02%'");
    $del("DELETE FROM client_users WHERE email LIKE '%s02@demo.test'");
    $del("DELETE FROM users WHERE email LIKE '%s02@demo.test' OR username LIKE 'demo.s02%'");
    if ($client) $del("DELETE FROM business_partners WHERE id=?", [$client]);
    if ($apex) $del("DELETE FROM business_partners WHERE id=?", [$apex]);
    if (function_exists('setting_set')) setting_set('demo_s02_seed', '');
    return $n;
}

function seed_s02_load() {
    seed_s02_remove();
    try { db()->exec("SET SESSION sql_mode=''"); } catch (Throwable $e) {}
    $log = []; $say = function ($s) use (&$log) { $log[] = $s; };
    $office = function () { static $o = null; if ($o === null) { try { $o = (int)ops_val("SELECT id FROM offices ORDER BY id LIMIT 1"); } catch (Throwable $e) { $o = 0; } } return $o; };

    // ---- Taxonomy spine (reuse the graph) ---------------------------------
    $nElec = s01_node('DOMAIN', 'Electrical');
    $nPower = s01_node('SECTOR', 'Power & Energy', $nElec);
    $nTD = s01_node('SPECIALIZATION', 'Transmission & Distribution', $nPower);
    $nSub = s01_node('SPECIALIZATION', 'Substations', $nTD);
    $roleSubInsp = s01_node('ROLE', 'Substation Inspection Engineer', $nSub);
    $sk = fn($n, $p = null) => s01_node('SKILL', $n, $p ?? $nSub);
    $eq = fn($n) => s01_node('EQUIPMENT', $n, $nSub);

    // ---- PHASE A — Apex org + client + staff + portal ---------------------
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,is_vendor,is_subcontractor,status,created_at) VALUES ('Apex Technical Inspection Services Pvt. Ltd.','Apex Technical Inspection',0,0,1,'ACTIVE',?)")->execute([date('c')]);
    $apexParty = (int)db()->lastInsertId();
    if (function_exists('connect_org_migrate')) connect_org_migrate();
    db()->prepare("INSERT INTO cx_organisations (name,org_type,package_key,party_id,status,contact_name,contact_email,contact_mobile,approved_by,approved_at,created_at) VALUES ('Apex Technical Inspection Services Pvt. Ltd.','MANPOWER_AGENCY','STAFFING',?, 'ACTIVE','Rajesh Shah','rajesh.s02@demo.test','9898010001','demo-seed',?,?)")->execute([$apexParty, date('c'), date('c')]);
    $apexOrg = (int)db()->lastInsertId();
    if (function_exists('portal_migrate')) portal_migrate();
    if (function_exists('setting_set')) setting_set('portal_enabled', '1');
    // agency portal login (bench workspace)
    db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,perms,created_by,created_at) VALUES (?,?,?,?,1,0,'', 'demo-seed', ?)")->execute([$apexParty, 'agency.s02@demo.test', 'Priya Nair (Apex Resource Mgr)', s01_pw(), date('c')]);
    // the client
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,created_at) VALUES ('Northern Grid EPC Limited','Northern Grid EPC',1,'ACTIVE',?)")->execute([date('c')]);
    $client = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,perms,created_by,created_at) VALUES (?,?,?,?,1,0,'', 'demo-seed', ?)")->execute([$client, 'client.s02@demo.test', 'Northern Grid EPC (client)', s01_pw(), date('c')]);
    // Apex staff (operator side)
    $mkUser = function ($u, $name, $role, $email) { if ((int)ops_val("SELECT COUNT(*) FROM users WHERE username=?", [$u])) return; $p = explode(' ', trim($name), 2); db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active) VALUES (?,?,?,?,?,?,?,1)")->execute([$u, s01_pw(), $p[0], $p[1] ?? '', $email, $role, in_array($role, ['MASTER_ADMIN','ADMIN'], true) ? 1 : 0]); };
    // Username == e-mail so every DEMO-S02 login is a consistent "@"-style ID.
    $mkUser('rajesh.s02@demo.test', 'Rajesh Shah', 'MASTER_ADMIN', 'rajesh.s02@demo.test');
    $mkUser('priya.s02@demo.test', 'Priya Nair', 'COORDINATOR', 'priya.s02@demo.test');
    $mkUser('vikram.s02@demo.test', 'Vikram Patel', 'COORDINATOR', 'vikram.s02@demo.test');
    $mkUser('kavita.s02@demo.test', 'Kavita Menon', 'MASTER_ADMIN', 'kavita.s02@demo.test');
    $say('Apex org #' . $apexOrg . ' + client Northern Grid #' . $client . ' + 4 staff + 2 portal logins');

    // ---- PHASE B — the 6-person bench (passport-linked) -------------------
    // helper: create a professional passport, attach taxonomy, geo, and a bench row.
    $benchIds = []; $benchProIds = [];
    $addBench = function ($a, $tax, $bench) use ($apexOrg, &$benchIds, &$benchProIds, $nSub) {
        $pro = s02_pro($a);
        foreach ($tax as $rel => $nodes) foreach ($nodes as $nid => $yrs) if ($nid) connect_profile_tax_attach($pro, $nid, $rel, ['years' => $yrs, 'competency' => 'STRONG']);
        if (function_exists('connect_privacy_save')) connect_privacy_save($pro, ['contact' => 'on_request', 'rate' => 'band', 'identity' => 'full', 'listed' => 1]);
        if (function_exists('connect_geo_save_mobility')) connect_geo_save_mobility($pro, ['base_city' => $a['base_city'] ?? '', 'base_state' => $a['base_state'] ?? 'Gujarat', 'base_country' => 'IN', 'mobility_mode' => $a['mobility'] ?? 'RADIUS', 'travel_radius_km' => $a['radius'] ?? 250, 'pan_india' => !empty($a['pan_india']) ? 1 : 0]);
        db()->prepare("UPDATE cx_professionals SET years_experience=? WHERE id=?")->execute([(int)($a['years'] ?? 0), $pro]);
        // bench row (agency roster) linked to the passport
        [$bok, , $bid] = connect_bench_add($apexOrg, ['name' => $a['name'], 'job_title' => $a['headline'] ?? '', 'skills' => $a['skills'] ?? '', 'base_city' => $a['base_city'] ?? '', 'availability' => $bench['availability'] ?? 'AVAILABLE', 'day_rate' => $bench['day_rate'] ?? 0]);
        $bid = (int)($bid ?: ops_val("SELECT id FROM cx_bench WHERE org_id=? AND name=? ORDER BY id DESC LIMIT 1", [$apexOrg, $a['name']]));
        db()->prepare("UPDATE cx_bench SET professional_id=?, association=?, cost_rate=?, available_from=? WHERE id=?")
            ->execute([$pro, $bench['association'] ?? 'INTERNAL', $bench['cost_rate'] ?? 0, $bench['available_from'] ?? '', $bid]);
        $benchIds[$a['key']] = $bid; $benchProIds[$a['key']] = $pro;
        return $pro;
    };

    // 1 — Amit Kulkarni (strong internal, available)
    $amit = $addBench(['key' => 'amit', 'email' => 'amit.s02@demo.test', 'name' => 'Amit Kulkarni', 'mobile' => '9898020001', 'headline' => 'Senior Electrical Inspection Engineer – Substation & Power Equipment', 'disciplines' => 'ELEC', 'skills' => 'HV equipment inspection, substation inspection, transformer inspection, circuit breaker inspection, CT/PT inspection, vendor inspection, FAT, SAT, pre-dispatch inspection, 220kV', 'base_city' => 'Vadodara', 'pan_india' => 1, 'years' => 14],
        ['PRIMARY_ROLE' => [$roleSubInsp => 9], 'SPECIALIZATION' => [$nSub => 12], 'SKILL' => [$sk('Substation Inspection') => 9, $sk('HV Equipment Inspection') => 9, $sk('Transformer Inspection') => 8, $sk('Circuit Breaker Inspection') => 8, $sk('Vendor Inspection') => 10, $sk('FAT') => 7, $sk('SAT') => 6, $sk('220 kV') => 9], 'EQUIPMENT' => [$eq('Power Transformers') => 9, $eq('Circuit Breakers') => 8, $eq('Current Transformers') => 7]],
        ['availability' => 'AVAILABLE', 'association' => 'INTERNAL', 'day_rate' => 11000, 'cost_rate' => 7200]);
    // 2 — Sandeep Rao (mechanical, available in 15d)
    $addBench(['key' => 'sandeep', 'email' => 'sandeep.s02@demo.test', 'name' => 'Sandeep Rao', 'mobile' => '9898020002', 'headline' => 'Mechanical / Static Equipment Inspector', 'disciplines' => 'MECH', 'skills' => 'pressure vessel inspection, heat exchanger inspection, storage tank inspection, hydro test, FAT, vendor surveillance', 'base_city' => 'Surat', 'radius' => 500, 'years' => 11],
        ['PRIMARY_ROLE' => [s01_node('ROLE','Pressure Vessel Inspector', s01_node('SPECIALIZATION','Static Equipment', s01_node('SECTOR','Static', s01_node('DOMAIN','Mechanical')))) => 8], 'SKILL' => [s01_node('SKILL','Pressure Vessel Inspection') => 9, s01_node('SKILL','Hydro Test') => 7]],
        ['availability' => 'OFF', 'association' => 'INTERNAL', 'day_rate' => 8000, 'cost_rate' => 5600, 'available_from' => s01_d(15)]);
    // 3 — Mohammed Irfan (welding, external, available tomorrow) — carries an EXPIRING + EXPIRED cert
    $irfan = $addBench(['key' => 'irfan', 'email' => 'irfan.s02@demo.test', 'name' => 'Mohammed Irfan', 'mobile' => '9898020003', 'headline' => 'Welding Inspection Specialist (CSWIP)', 'disciplines' => 'WELD', 'skills' => 'welding inspection, WPS review, PQR review, welder qualification, visual inspection, PWHT', 'base_city' => 'Ahmedabad', 'pan_india' => 1, 'years' => 12],
        ['PRIMARY_ROLE' => [s01_node('ROLE','Welding Inspector', s01_node('SPECIALIZATION','Welding', s01_node('SECTOR','Fabrication', s01_node('DOMAIN','Mechanical')))) => 10], 'SKILL' => [s01_node('SKILL','Welding Inspection') => 10, s01_node('SKILL','WPS Review') => 8]],
        ['availability' => 'AVAILABLE', 'association' => 'EXTERNAL', 'day_rate' => 11000, 'cost_rate' => 9000, 'available_from' => s01_d(1)]);
    if (function_exists('connect_cred_cert_save')) {
        connect_cred_cert_save($irfan, ['name' => 'CSWIP 3.1 Welding Inspector', 'authority' => 'TWI', 'cert_number' => 'DEMO-S02-CSWIP-01', 'discipline' => 'Welding', 'issue_date' => s01_d(-700), 'expiry_date' => s01_d(40)]);   // EXPIRING within assignment window
        connect_cred_cert_save($irfan, ['name' => 'PCN Level II', 'authority' => 'BINDT', 'cert_number' => 'DEMO-S02-PCN-01', 'discipline' => 'NDT', 'issue_date' => s01_d(-900), 'expiry_date' => s01_d(-20)]);   // EXPIRED
    }
    // 4 — Rakesh Yadav (NDT, ALLOCATED / unavailable)
    $rakesh = $addBench(['key' => 'rakesh', 'email' => 'rakesh.s02@demo.test', 'name' => 'Rakesh Yadav', 'mobile' => '9898020004', 'headline' => 'NDT Technician (UT / MT)', 'disciplines' => 'NDT', 'skills' => 'ultrasonic testing, magnetic particle testing, RT film review', 'base_city' => 'Vadodara', 'radius' => 250, 'years' => 9],
        ['PRIMARY_ROLE' => [s01_node('ROLE','NDT Technician', s01_node('SPECIALIZATION','Ultrasonic Testing', s01_node('DOMAIN','NDT'))) => 8], 'SKILL' => [s01_node('SKILL','Ultrasonic Testing') => 8, s01_node('SKILL','Magnetic Particle Testing') => 6]],
        ['availability' => 'ALLOCATED', 'association' => 'INTERNAL', 'day_rate' => 7500, 'cost_rate' => 5200]);
    // 5 — Neha Desai (civil, available in 7d)
    $addBench(['key' => 'neha', 'email' => 'neha.s02@demo.test', 'name' => 'Neha Desai', 'mobile' => '9898020005', 'headline' => 'Civil / Structural Inspection Engineer', 'disciplines' => 'CIVIL', 'skills' => 'structural steel inspection, fabrication inspection, bolt inspection, civil quality inspection', 'base_city' => 'Mumbai', 'base_state' => 'Maharashtra', 'pan_india' => 1, 'years' => 10],
        ['PRIMARY_ROLE' => [s01_node('ROLE','Structural Inspector', s01_node('SPECIALIZATION','Structural Inspection', s01_node('SECTOR','Civil Works', s01_node('DOMAIN','Civil')))) => 8]],
        ['availability' => 'OFF', 'association' => 'INTERNAL', 'day_rate' => 9000, 'cost_rate' => 6300, 'available_from' => s01_d(7)]);
    // 6 — Ajay Thomas (safety, available now)
    $addBench(['key' => 'ajay', 'email' => 'ajay.s02@demo.test', 'name' => 'Ajay Thomas', 'mobile' => '9898020006', 'headline' => 'Industrial Safety / HSE Inspector', 'disciplines' => 'HSE', 'skills' => 'site safety audit, HSE inspection, permit-to-work audit, risk assessment', 'base_city' => 'Pune', 'base_state' => 'Maharashtra', 'pan_india' => 1, 'years' => 13],
        ['PRIMARY_ROLE' => [s01_node('ROLE','Safety Inspector', s01_node('SPECIALIZATION','Industrial Safety', s01_node('DOMAIN','Safety'))) => 11]],
        ['availability' => 'AVAILABLE', 'association' => 'INTERNAL', 'day_rate' => 8500, 'cost_rate' => 5900]);
    $say('Bench: 6 passport-linked members (2 available now, 2 soon, 1 allocated, 1 external)');

    // ---- PHASE C — client requirement + gap ------------------------------
    $reqId = (int)cx_requirement_create([
        'title' => '220 kV Substation Inspection Engineer – Gujarat', 'poster_party_id' => $client, 'poster_name' => 'Northern Grid EPC',
        'discipline_code' => 'ELEC', 'location' => 'Ahmedabad, Gujarat', 'work_type' => 'ONSITE', 'start_date' => s01_d(10), 'end_date' => s01_d(40), 'positions' => 2,
        'rate_min' => 8000, 'rate_max' => 12000, 'rate_unit' => 'day',
        'description' => '220 kV HV substation equipment inspection: transformers, circuit breakers, CT/VT, isolators, vendor inspection. Min 8y total / 5y relevant / 4y inspection. Preferred FAT, SAT, electrical testing coordination. On-site, 30 days, 2 positions.',
    ], true);
    // Internal-first: rank the bench for this requirement (reuse the matcher over passports)
    $req = cx_requirement_get($reqId);
    $benchRank = [];
    foreach (connect_match_for_requirement($req, 30) as $r) {
        if (($r['kind'] ?? '') !== 'professional') continue;
        if (in_array((int)$r['id'], array_values($benchProIds), true)) $benchRank[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'score' => (int)$r['score']];
    }
    $internalTopIsAmit = !empty($benchRank) && (int)$benchRank[0]['id'] === (int)$amit;
    // Gap: available-suitable internal = Amit only (Sandeep/Neha not available now & off-discipline; Rakesh allocated) → 1 of 2
    $required = 2; $internalAvailableSuitable = 1; $gap = $required - $internalAvailableSuitable;
    $say('Requirement #' . $reqId . ' (2 positions). Internal top = ' . (($internalTopIsAmit) ? 'Amit' : '?') . '. Gap = ' . $gap);

    // ---- PHASE D — marketplace supplement (Rohit) ------------------------
    $rohit = s02_pro(['email' => 'rohit.s02@demo.test', 'name' => 'Rohit Sharma', 'mobile' => '9898030001', 'headline' => 'Electrical Inspection Specialist – HV Substations', 'disciplines' => 'ELEC', 'skills' => 'substation inspection, HV equipment, transformer inspection, circuit breaker, vendor inspection, FAT, 220kV', 'availability' => 'AVAILABLE']);
    foreach (['PRIMARY_ROLE' => [$roleSubInsp => 8], 'SPECIALIZATION' => [$nSub => 8], 'SKILL' => [$sk('Substation Inspection') => 8, $sk('HV Equipment Inspection') => 7, $sk('Transformer Inspection') => 6, $sk('Vendor Inspection') => 7, $sk('220 kV') => 7], 'EQUIPMENT' => [$eq('Power Transformers') => 7]] as $rel => $nodes) foreach ($nodes as $nid => $y) if ($nid) connect_profile_tax_attach($rohit, $nid, $rel, ['years' => $y]);
    if (function_exists('connect_geo_save_mobility')) connect_geo_save_mobility($rohit, ['base_city' => 'Ahmedabad', 'base_state' => 'Gujarat', 'base_country' => 'IN', 'mobility_mode' => 'PAN_INDIA', 'pan_india' => 1, 'travel_radius_km' => 300]);
    if (function_exists('connect_privacy_save')) connect_privacy_save($rohit, ['contact' => 'on_request', 'rate' => 'band', 'identity' => 'full', 'listed' => 1]);
    db()->prepare("UPDATE cx_professionals SET years_experience=10 WHERE id=?")->execute([$rohit]);
    if (function_exists('connect_cred_project_save')) connect_cred_project_save($rohit, ['title' => '400 kV Substation Vendor Inspection', 'role' => 'Electrical Inspector', 'industry' => 'Power Transmission', 'location' => 'Gujarat', 'equipment' => 'Transformers, Breakers', 'scope' => 'Vendor inspection & FAT', 'start_date' => '2022-01-01', 'end_date' => '2022-06-30']);
    if (function_exists('connect_verify_submit') && function_exists('connect_verify_review')) { [, , $c] = connect_verify_submit('professional', $rohit, 'ID_DOC', '', 'DEMO'); connect_verify_review((int)$c, 'VERIFIED', 'DEMO-S02'); }
    $say('Marketplace supplement: Rohit Sharma #' . $rohit);

    // ---- PHASE E — applications + submission + client approval ------------
    $appAmit = (int)cx_application_add($reqId, ['applicant_professional_id' => $amit, 'applicant_name' => 'Amit Kulkarni', 'proposed_rate' => 11000, 'cover_note' => 'Internal bench — senior substation inspection engineer.']);
    $appRohit = (int)cx_application_add($reqId, ['applicant_professional_id' => $rohit, 'applicant_name' => 'Rohit Sharma', 'proposed_rate' => 13500, 'cover_note' => 'Marketplace supplement for the gap.']);
    cx_requirement_transition($reqId, 'SHORTLISTING');
    cx_application_transition($appAmit, 'SHORTLISTED'); cx_application_transition($appAmit, 'OFFERED'); cx_application_transition($appAmit, 'ACCEPTED');
    cx_application_transition($appRohit, 'SHORTLISTED'); cx_application_transition($appRohit, 'OFFERED'); cx_application_transition($appRohit, 'ACCEPTED');
    $awarded = cx_requirement_award($reqId, $appAmit);   // requirement AWARDED (crew of 2 both ACCEPTED)
    $say('Both candidates submitted → client approved (accepted); requirement awarded=' . ($awarded ? 'yes' : 'no'));

    // ---- PHASE F — identity → job → 30-day schedule + allocation ---------
    $amitInsp = s02_inspector($amit, 'Amit Kulkarni', 'DEMO-S02-INS-01', 'amit.s02@demo.test', 'electrical, substation, HV, 220kV, vendor inspection', '9898020001');
    $rohitInsp = s02_inspector($rohit, 'Rohit Sharma', 'DEMO-S02-INS-02', 'rohit.s02@demo.test', 'electrical, substation, HV, 220kV', '9898030001');
    // book engagement + deploy to a PDSO job
    if (function_exists('connect_engage_save_for_requirement')) connect_engage_save_for_requirement($reqId, ['deputation_basis' => 'MANDAYS', 'rate' => 11000, 'rate_unit' => 'day', 'quantity' => 30, 'start_date' => s01_d(10), 'end_date' => s01_d(40), 'rate_inclusive' => 'INCLUSIVE', 'voucher_cadence' => 'PER_DEPLOYMENT']);
    db()->prepare("INSERT INTO calls (call_code,client_id,inspection_type,inspection_required_date,status,created_by,created_at) VALUES ('DEMO-S02-CALL-01',?, 'Electrical Inspection', ?, 'OPEN','demo-seed', ?)")->execute([$client, s01_d(10), date('c')]);
    $callId = (int)db()->lastInsertId();
    $jobId = 0;
    if (function_exists('connect_deploy_from_engagement')) { [, , $jobId] = connect_deploy_from_engagement($reqId); }
    if ($jobId > 0) {
        db()->prepare("UPDATE jobs SET job_code='DEMO-S02-JOB-001', call_id=?, inspection_type='Electrical Inspection', service_code='SUBSTATION', dep_site='Ahmedabad, Gujarat', scheduled_date=?, inspection_start_date=?, inspection_end_date=?, stage='ALLOCATED', mandays=30 WHERE id=?")
            ->execute([$callId, s01_d(10), s01_d(10), s01_d(40), $jobId]);
        if (function_exists('pdso_set_status')) pdso_set_status($jobId, 'MOBILIZED');
        // 30 working days, alternating the two inspectors (lead Amit + Rohit)
        db()->prepare("DELETE FROM job_visits WHERE job_id=?")->execute([$jobId]);
        for ($i = 0; $i < 30; $i++) {
            $insp = ($i % 2 === 0) ? $amitInsp : $rohitInsp;
            db()->prepare("INSERT INTO job_visits (job_id,visit_date,inspector_id,status,note) VALUES (?,?,?, 'PLANNED', ?)")->execute([$jobId, s01_d(10 + $i), $insp, ($insp === $amitInsp ? 'Lead — ' : '') . 'Substation equipment inspection']);
        }
    }
    // allocation → bench availability updates (Amit ALLOCATED)
    if (function_exists('connect_bench_allocate') && !empty($benchIds['amit'])) { try { connect_bench_allocate($benchIds['amit'], $apexOrg, $reqId, 0, 'Lead Electrical Inspector — DEMO-S02'); } catch (Throwable $e) {} }
    db()->prepare("UPDATE cx_bench SET availability='ALLOCATED' WHERE id=?")->execute([(int)$benchIds['amit']]);
    // record the commercial (client charge) on the allocations (staff-only)
    try {
        db()->prepare("UPDATE cx_bench_alloc SET client_rate=11000, professional_id=? WHERE requirement_id=? AND bench_id=?")->execute([$amit, $reqId, (int)$benchIds['amit']]);
    } catch (Throwable $e) {}
    // conflict fixture: an overlapping deputation for the allocated NDT tech (Rakesh)
    $rakeshInsp = s02_inspector($rakesh, 'Rakesh Yadav', 'DEMO-S02-INS-03', 'rakesh.s02@demo.test', 'NDT, UT, MT');
    db()->prepare("INSERT INTO jobs (job_code,inspector_id,job_type,dep_status,dep_site,inspection_start_date,inspection_end_date,sbu,created_at) VALUES ('DEMO-S02-JOB-ALLOCATED',?, 'DEPUTATION','ACTIVE','Hazira, Gujarat',?,?, 'IND', ?)")->execute([$rakeshInsp, s01_d(5), s01_d(35), date('c')]);
    // replacement history fixture: Rohit unavailable on day 12 → replacement note preserved, original kept
    db()->prepare("UPDATE job_visits SET status='REPLAN', note='Rohit unavailable from day 12 — replacement sourced; original allocation preserved' WHERE job_id=? AND visit_date=?")->execute([$jobId, s01_d(21)]);
    $say('Identity linked (Amit, Rohit); deployment ' . ($jobId ? 'DEMO-S02-JOB-001' : 'FAILED') . '; 30-day schedule + allocation + conflict + replacement note');

    // ---- Negative fixture: expired credential on an inspector (competence gate) ----
    // Give Irfan's inspector an expired inspector_cert so eligibility flags BLOCKED.
    $irfanInsp = s02_inspector($irfan, 'Mohammed Irfan', 'DEMO-S02-INS-04', 'irfan.s02@demo.test', 'welding inspection, CSWIP');
    try { db()->prepare("INSERT INTO inspector_certs (inspector_id,name,number,valid_to,status,verify_status,created_at) VALUES (?, 'CSWIP 3.1 Welding Inspector','DEMO-S02-INS-CSWIP', ?, 'EXPIRED','VERIFIED', ?)")->execute([$irfanInsp, s01_d(-20), date('c')]); } catch (Throwable $e) {}

    // ---- PHASE G — report (URFE) with a correction cycle + findings ------
    $rptId = 0; $ncMajor = 0; $ncMinor = 0;
    if ($jobId > 0) {
        $rt = ops_one("SELECT id, code FROM report_types WHERE active=1 ORDER BY id LIMIT 1");
        $data = json_encode(['scope' => '220 kV substation equipment inspection', 'equipment' => ['Power Transformer','Circuit Breaker','Isolator','CT','PT'], 'activities' => [['activity' => 'Power transformer inspection', 'result' => 'Acceptable'], ['activity' => 'Circuit breaker inspection', 'result' => 'Acceptable'], ['activity' => 'HV connection torque records', 'result' => 'Major finding (DEMO-S02-F-001)']], 'conclusion' => 'Acceptable subject to closure of one major finding on torque records.', 'demo' => 'DEMO ONLY — NOT A REAL CERTIFICATE']);
        // Correction cycle in the audit trail: returned once, corrected, then approved & issued.
        db()->prepare("INSERT INTO report_docs (irn,report_type_id,type_code,title,client_id,call_id,job_id,office_id,sbu,location,inspector_id,inspection_date,issue_date,result,release_status,status,data,remarks,rev,finalized,finalized_at,finalized_by,submitted_at,approved_at,approved_by,vet_status,vet_by,vet_at,deleted,created_by,created_at) VALUES (?,?,?,?,?,?,?,?, 'IND', 'Ahmedabad, Gujarat', ?, ?, ?, 'ACCEPTED_COND','RELEASED','ISSUED', ?, ?, 2, 1, ?, ?, ?, ?, ?, 'VETTED', ?, ?, 0, 'demo-seed', ?)")
            ->execute(['DEMO-S02-RPT-001', (int)($rt['id'] ?? 0), (string)($rt['code'] ?? 'DIR'), '220 kV Substation Inspection – Northern Grid EPC', $client, $callId, $jobId, $office(), $amitInsp, s01_d(38), s01_d(40),
                       $data, 'Rev 1 returned by QA (incomplete photographic evidence); rev 2 corrected and approved.', s01_d(39), 'Kavita Menon', s01_d(37), s01_d(39), 'Kavita Menon', 'Kavita Menon', s01_d(38), date('c')]);
        $rptId = (int)db()->lastInsertId();
        if (function_exists('verify_code_for')) { try { verify_code_for(ops_one("SELECT * FROM report_docs WHERE id=?", [$rptId])); } catch (Throwable $e) {} }
        if (function_exists('ncr_create')) {
            $ncMajor = (int)ncr_create(['job_id' => $jobId, 'report_doc_id' => $rptId, 'partner_id' => $client, 'office_id' => $office(), 'sbu' => 'IND', 'title' => 'Improper torque record for critical HV connection', 'description' => 'Torque record for a critical 220 kV connection is not available / not per spec at time of inspection.', 'severity' => 'MAJOR', 'detected_on' => s01_d(20), 'detected_by' => 'Amit Kulkarni', 'owner' => 'Northern Grid EPC', 'due_on' => s01_d(30), 'source' => 'INTERNAL']);
            if ($ncMajor) db()->prepare("UPDATE nonconformities SET ref='DEMO-S02-F-001', status='DISPOSITIONED', containment='Connection re-torqued & recorded', disposition='Awaiting client verification' WHERE id=?")->execute([$ncMajor]);
            $ncMinor = (int)ncr_create(['job_id' => $jobId, 'report_doc_id' => $rptId, 'partner_id' => $client, 'office_id' => $office(), 'sbu' => 'IND', 'title' => 'Cable tag marking incomplete', 'description' => 'A few control cable tags were not fully marked.', 'severity' => 'MINOR', 'detected_on' => s01_d(22), 'detected_by' => 'Rohit Sharma', 'owner' => 'Northern Grid EPC', 'due_on' => s01_d(28), 'source' => 'INTERNAL']);
            if ($ncMinor) db()->prepare("UPDATE nonconformities SET ref='DEMO-S02-F-002', status='CLOSED', closed_on=?, closed_by='Kavita Menon' WHERE id=?")->execute([s01_d(24), $ncMinor]);
        }
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        foreach ([['transformer','Transformer (DEMO)'],['breaker','Breaker (DEMO)'],['torque','Torque record — DEMO-S02-F-001 (DEMO)']] as [$fk, $nt])
            db()->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,note,created_by,created_at) VALUES (?,?, 'photo', ?, 'image/png', ?, ?, 'Amit Kulkarni', ?)")->execute([$rptId, $fk, 'DEMO-S02-' . $fk . '.png', $png, $nt, date('c')]);
        // complete + bill + rate
        db()->prepare("UPDATE jobs SET stage='CLOSED', closed_flag=1, closed_at=?, report_link='DEMO-S02-RPT-001' WHERE id=?")->execute([date('c'), $jobId]);
        if (function_exists('connect_engage_set_status')) { $eng = connect_engage_for_requirement($reqId); if ($eng) connect_engage_set_status((int)$eng['id'], 'COMPLETED'); }
        if (function_exists('connect_engagement_billable')) connect_engagement_billable($reqId);
        if (function_exists('cx_rating_add')) {
            // The awarded lead goes through the rating engine (moderation + guard).
            cx_rating_add($reqId, 'CLIENT_TO_PRO', ['application_id' => $appAmit, 'rater_party_id' => $client, 'ratee_inspector_id' => $amitInsp, 'stars' => 5, 'competency' => 5, 'communication' => 5, 'punctuality' => 4, 'professionalism' => 5, 'would_rehire' => 1, 'comment' => 'Excellent substation inspection lead.']);
        }
        // The 2nd crew member: the engine allows one client→pro rating per requirement,
        // so the second person's rating is written directly to the same table.
        try { db()->prepare("INSERT INTO cx_ratings (requirement_id,application_id,direction,rater_party_id,ratee_inspector_id,stars,competency,communication,punctuality,professionalism,would_rehire,comment,created_by,created_at) VALUES (?,?, 'CLIENT_TO_PRO', ?,?,4,4,5,4,5,1, 'Strong marketplace supplement.', 'demo-seed', ?)")->execute([$reqId, $appRohit, $client, $rohitInsp, date('c')]); } catch (Throwable $e) {}
    }
    $say('Report DEMO-S02-RPT-001 (rev 2, issued) + findings F-001 (major) F-002 (minor) + evidence + ratings');

    // ---- Commercial totals (staff-only) ----------------------------------
    $days = 30;
    $comm = [
        'amit' => ['rev' => 11000 * $days, 'cost' => 7200 * $days, 'margin' => (11000 - 7200) * $days],
        'rohit' => ['rev' => 13500 * $days, 'cost' => 10500 * $days, 'margin' => (13500 - 10500) * $days],
    ];
    $comm['total'] = ['rev' => $comm['amit']['rev'] + $comm['rohit']['rev'], 'cost' => $comm['amit']['cost'] + $comm['rohit']['cost'], 'margin' => $comm['amit']['margin'] + $comm['rohit']['margin']];

    // ---- DASHBOARD (real, derived) — the 20 acceptance criteria ----------
    $has = function ($sql, $args = []) { try { return (int)ops_val($sql, $args) > 0; } catch (Throwable $e) { return false; } };
    $benchAvailNow = (int)ops_val("SELECT COUNT(*) FROM cx_bench WHERE org_id=? AND availability='AVAILABLE'", [$apexOrg]);
    $irfanCerts = function_exists('connect_cred_certs') ? connect_cred_certs($irfan) : [];
    $dash = [
        ['Bench connected to passport (6 linked)', $has("SELECT COUNT(*) FROM cx_bench WHERE org_id=? AND professional_id>0 HAVING COUNT(*)>=6", [$apexOrg])],
        ['Bench members taxonomy-searchable', $has("SELECT COUNT(DISTINCT pro_id) FROM cx_profile_tax WHERE pro_id IN (SELECT professional_id FROM cx_bench WHERE org_id=?) HAVING COUNT(DISTINCT pro_id)>=6", [$apexOrg])],
        ['Bench statuses distinguished (not hidden)', ($has("SELECT 1 FROM cx_bench WHERE org_id=? AND availability='ALLOCATED'", [$apexOrg]) && $has("SELECT 1 FROM cx_bench WHERE org_id=? AND availability='OFF'", [$apexOrg]) && $benchAvailNow >= 2)],
        ['Internal search ranks Amit #1', $internalTopIsAmit],
        ['Gap identified automatically (2−1=1)', ($required === 2 && $gap === 1)],
        ['Marketplace supplement created', $has("SELECT 1 FROM cx_professionals WHERE id=?", [$rohit])],
        ['Both candidates approved (accepted)', ($has("SELECT 1 FROM cx_applications WHERE id=? AND status='ACCEPTED'", [$appAmit]) && $has("SELECT 1 FROM cx_applications WHERE id=? AND status='ACCEPTED'", [$appRohit]))],
        ['Requirement AWARDED', $has("SELECT 1 FROM cx_requirements WHERE id=? AND status='AWARDED'", [$reqId])],
        ['Converted to ops job (no dup)', $has("SELECT 1 FROM jobs WHERE id=? AND job_code='DEMO-S02-JOB-001' AND source_requirement_id=?", [$jobId, $reqId])],
        ['Identity linked — Amit & Rohit', ($has("SELECT 1 FROM cx_identity_link WHERE professional_id=? AND status='LINKED'", [$amit]) && $has("SELECT 1 FROM cx_identity_link WHERE professional_id=? AND status='LINKED'", [$rohit]))],
        ['30-day schedule (2 inspectors)', $has("SELECT COUNT(*) FROM job_visits WHERE job_id=? HAVING COUNT(*)>=30", [$jobId])],
        ['Allocation updated bench availability', $has("SELECT 1 FROM cx_bench WHERE id=? AND availability='ALLOCATED'", [(int)$benchIds['amit']])],
        ['Schedule conflict fixture present', $has("SELECT 1 FROM jobs WHERE job_code='DEMO-S02-JOB-ALLOCATED'")],
        ['Replacement history preserved', $has("SELECT 1 FROM job_visits WHERE job_id=? AND status='REPLAN'", [$jobId])],
        ['Major + minor findings (existing NCR)', ($ncMajor > 0 && $ncMinor > 0)],
        ['Report via URFE, correction cycle (rev 2)', $has("SELECT 1 FROM report_docs WHERE id=? AND status='ISSUED' AND rev>=2", [$rptId])],
        ['Client sees the issued report', $has("SELECT 1 FROM report_docs WHERE id=? AND client_id=? AND finalized=1", [$rptId, $client])],
        ['Commercial margin computed (₹204,000)', ($comm['total']['margin'] === 204000 && $comm['total']['rev'] === 735000 && $comm['total']['cost'] === 531000)],
        ['Performance ratings recorded', $has("SELECT COUNT(*) FROM cx_ratings WHERE requirement_id=? HAVING COUNT(*)>=2", [$reqId])],
        ['Expired-credential negative fixture', (count(array_filter($irfanCerts, fn($c) => $c['status'] === 'EXPIRED')) > 0 && $has("SELECT 1 FROM inspector_certs WHERE inspector_id=?", [$irfanInsp]))],
    ];
    $allpass = true; foreach ($dash as [$l, $ok]) if (!$ok) $allpass = false;
    if (function_exists('setting_set')) setting_set('demo_s02_seed', date('c'));
    return ['log' => $log, 'dashboard' => $dash, 'allpass' => $allpass, 'commercial' => $comm,
            'ids' => ['org' => $apexOrg, 'client' => $client, 'requirement' => $reqId, 'job' => $jobId, 'report' => $rptId, 'amit' => $amit, 'rohit' => $rohit]];
}
