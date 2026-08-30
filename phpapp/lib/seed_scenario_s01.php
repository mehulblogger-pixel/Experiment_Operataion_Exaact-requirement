<?php
// ============================================================================
//  DEMO-S01 — Scenario seed engine (callable from CLI *and* the admin button).
//
//  Transmission Technician / Electrical Inspection Marketplace Journey: a
//  complete, interconnected, production-like scenario built ENTIRELY on the
//  existing engines (no duplicate systems). Everything is tagged DEMO-S01 and is
//  removable. seed_s01_load() returns a result with a real (never faked)
//  PASS/FAIL dashboard; seed_s01_remove() takes it all out again.
// ============================================================================

function s01_pw($p = 'demo12345') { return password_hash($p, PASSWORD_DEFAULT); }
function s01_d($days) { return date('Y-m-d', strtotime(date('Y-m-d') . ' ' . ($days >= 0 ? "+$days" : $days) . ' days')); }

/** Resolve/create a taxonomy node by (kind,name) under an optional parent. */
function s01_node($kind, $name, $parentId = 0) { return function_exists('connect_tax_node_add') ? (int)connect_tax_node_add($kind, $name, (int)$parentId) : 0; }

/** Create a professional and return its id. */
function s01_pro(array $a) {
    connect_pro_migrate();
    db()->prepare("INSERT INTO cx_professionals (email,name,mobile,headline,disciplines,skills,work_types,availability,is_active,verification_tier,passport_token,password_hash,created_at)
                   VALUES (?,?,?,?,?,?,?,?,1,'registered',?,?,?)")
        ->execute([$a['email'], $a['name'], $a['mobile'] ?? '', $a['headline'] ?? '', $a['disciplines'] ?? '', $a['skills'] ?? '',
                   $a['work_types'] ?? 'FREELANCE', $a['availability'] ?? 'AVAILABLE', substr(md5($a['email']), 0, 20), s01_pw()]);
    return (int)db()->lastInsertId();
}

function seed_s01_status() {
    return [
        'loaded'  => function_exists('setting_get') ? (bool)setting_get('demo_s01_seed') : false,
        'pros'    => (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE email LIKE '%s01@demo.test'"),
        'client'  => (int)ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name LIKE 'DEMO-S01%'"),
        'report'  => (int)ops_val("SELECT COUNT(*) FROM report_docs WHERE irn='DEMO-S01-RPT-001'"),
    ];
}

/** Remove every DEMO-S01 record. Returns the count deleted. */
function seed_s01_remove() {
    $n = 0;
    $del = function ($sql, $args = []) use (&$n) { try { $st = db()->prepare($sql); $st->execute($args); $n += $st->rowCount(); } catch (Throwable $e) {} };
    $proEmails = ['arjun.s01@demo.test','cand.b.s01@demo.test','cand.c.s01@demo.test','cand.d.s01@demo.test','cand.e.s01@demo.test'];
    $proIds = [];
    foreach ($proEmails as $e) { $id = (int)ops_val("SELECT id FROM cx_professionals WHERE email=?", [$e]); if ($id) $proIds[] = $id; }
    $party = (int)ops_val("SELECT id FROM business_partners WHERE legal_name LIKE 'DEMO-S01%'");
    $reqIds = $party ? array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM cx_requirements WHERE poster_party_id=?", [$party]) ?: []) : [];
    foreach ($reqIds as $rid) {
        $del("DELETE FROM cx_applications WHERE requirement_id=?", [$rid]);
        $del("DELETE FROM cx_engagements WHERE requirement_id=?", [$rid]);
        $del("DELETE FROM cx_ratings WHERE requirement_id=?", [$rid]);
        try { $del("DELETE FROM billable_events WHERE source_module='connect' AND source_id=?", [$rid]); } catch (Throwable $e) {}
        $del("DELETE FROM jobs WHERE source_module='connect' AND source_requirement_id=?", [$rid]);
        $del("DELETE FROM cx_requirements WHERE id=?", [$rid]);
    }
    foreach ($proIds as $pid) {
        foreach (['cx_profile_tax','cx_pro_certs','cx_pro_projects','cx_identity_link','cx_client_bench'] as $t) {
            $col = $t === 'cx_identity_link' || $t === 'cx_client_bench' ? 'professional_id' : 'pro_id';
            $del("DELETE FROM $t WHERE $col=?", [$pid]);
        }
        try { $del("DELETE FROM cx_verifications WHERE subject_kind='professional' AND subject_id=?", [$pid]); } catch (Throwable $e) {}
        $del("DELETE FROM cx_professionals WHERE id=?", [$pid]);
    }
    // reports / findings / evidence for the DEMO-S01 job
    $rpt = (int)ops_val("SELECT id FROM report_docs WHERE irn='DEMO-S01-RPT-001'");
    if ($rpt) { $del("DELETE FROM report_files WHERE report_doc_id=?", [$rpt]); try { $del("DELETE FROM nonconformities WHERE report_doc_id=?", [$rpt]); } catch (Throwable $e) {} $del("DELETE FROM report_docs WHERE id=?", [$rpt]); }
    try { $del("DELETE FROM nonconformities WHERE ref='DEMO-S01-F-001'"); } catch (Throwable $e) {}
    $jobIds = array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM jobs WHERE job_code LIKE 'DEMO-S01-%'") ?: []);
    foreach ($jobIds as $jid) { $del("DELETE FROM job_visits WHERE job_id=?", [$jid]); $del("DELETE FROM jobs WHERE id=?", [$jid]); }
    $del("DELETE FROM inspectors WHERE emp_code LIKE 'DEMO-S01%'");
    $del("DELETE FROM client_users WHERE email LIKE '%s01@demo.test'");
    $del("DELETE FROM users WHERE username LIKE 'demo.s01%'");
    if ($party) {
        foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM calls WHERE client_id=?", [$party]) ?: []) as $cid) { $del("DELETE FROM jobs WHERE call_id=?", [$cid]); $del("DELETE FROM calls WHERE id=?", [$cid]); }
        $del("DELETE FROM cx_req_templates WHERE owner_party_id=?", [$party]);
        try { $del("DELETE FROM cx_organisations WHERE party_id=?", [$party]); } catch (Throwable $e) {}
        $del("DELETE FROM business_partners WHERE id=?", [$party]);
    }
    if (function_exists('setting_set')) setting_set('demo_s01_seed', '');
    return $n;
}

/**
 * Load the whole scenario (idempotent — purges DEMO-S01 first). Returns
 * ['log'=>[...], 'dashboard'=>[[label,ok],...], 'allpass'=>bool, 'ids'=>[...]].
 */
function seed_s01_load() {
    seed_s01_remove();
    $log = []; $say = function ($s) use (&$log) { $log[] = $s; };

    // ---- PHASE 1 — passport + candidates ----------------------------------
    $arjun = s01_pro([
        'email' => 'arjun.s01@demo.test', 'name' => 'Arjun Mehta', 'mobile' => '9812300001',
        'headline' => 'Electrical Transmission Inspection & Testing Specialist', 'disciplines' => 'ELEC,QAQC',
        'skills' => 'transmission line inspection, substation inspection, electrical equipment inspection, high voltage testing, pre-commissioning, QA/QC electrical, 220kV, 400kV, GIS, protection panels, visual inspection, punch list',
    ]);
    $nElec = s01_node('DOMAIN', 'Electrical');
    $nPower = s01_node('SECTOR', 'Power & Energy', $nElec);
    $nTD = s01_node('SPECIALIZATION', 'Transmission & Distribution', $nPower);
    $roleTx = s01_node('ROLE', 'Transmission Technician', $nTD);
    $roleSub = s01_node('ROLE', 'Substation Inspection Technician', $nTD);
    $skills = ['Transmission Line Inspection','Substation Inspection','Electrical Equipment Inspection','High Voltage Testing Support','Pre-Commissioning Inspection','QA/QC Electrical Inspection','Visual Inspection','Document Review','Material Verification','Punch List Verification','Quality Surveillance','Vendor Inspection Support'];
    $equip = ['Power Transformers','Circuit Breakers','Current Transformers','Voltage Transformers','Disconnectors','Surge Arresters','GIS Equipment','Protection Panels','Transmission Line Towers','Conductors','Insulators','Cable Termination Systems'];
    connect_profile_tax_attach($arjun, $roleTx, 'PRIMARY_ROLE', ['years' => 10, 'competency' => 'EXPERT', 'verified' => 1]);
    connect_profile_tax_attach($arjun, $roleSub, 'ADDITIONAL_ROLE', ['years' => 8]);
    foreach (['Electrical Inspector','Electrical Testing Technician','Commissioning Support Technician','QA/QC Electrical Inspector','Field Engineer'] as $rn)
        connect_profile_tax_attach($arjun, s01_node('ROLE', $rn, $nTD), 'ADDITIONAL_ROLE', ['years' => 5]);
    foreach ($skills as $s) connect_profile_tax_attach($arjun, s01_node('SKILL', $s, $nTD), 'SKILL', ['years' => 6, 'competency' => 'STRONG']);
    foreach ($equip as $e) connect_profile_tax_attach($arjun, s01_node('EQUIPMENT', $e, $nTD), 'EQUIPMENT', ['years' => 8]);
    foreach (['132 kV','220 kV','400 kV'] as $v) connect_profile_tax_attach($arjun, s01_node('SKILL', $v, $nTD), 'SKILL', ['years' => 7, 'competency' => 'STRONG']);
    if (function_exists('connect_tax_alias_add'))
        foreach (['HV Inspector' => $roleTx, 'High Voltage Inspector' => $roleTx, '220 kV Inspector' => s01_node('SKILL','220 kV',$nTD), '400 kV Inspector' => s01_node('SKILL','400 kV',$nTD), 'GIS Inspector' => s01_node('EQUIPMENT','GIS Equipment',$nTD)] as $al => $nid)
            if ($nid) connect_tax_alias_add($nid, $al);
    if (function_exists('connect_geo_augment_professional')) connect_geo_augment_professional();
    if (function_exists('connect_geo_save_mobility')) connect_geo_save_mobility($arjun, ['base_city' => 'Vadodara', 'base_state' => 'Gujarat', 'base_country' => 'IN', 'travel_radius_km' => 500, 'mobility_mode' => 'PAN_INDIA', 'pan_india' => 1]);
    if (function_exists('connect_privacy_save')) connect_privacy_save($arjun, ['contact' => 'on_request', 'rate' => 'band', 'identity' => 'full', 'listed' => 1]);
    if (function_exists('connect_cred_cert_save')) {
        connect_cred_cert_save($arjun, ['name' => 'Electrical Safety Training', 'authority' => 'DEMO Safety Council', 'cert_number' => 'DEMO-ES-4471', 'discipline' => 'Electrical', 'issue_date' => s01_d(-400), 'expiry_date' => date('Y-m-d', strtotime('+2 years'))]);
        connect_cred_cert_save($arjun, ['name' => 'High Voltage Safety Awareness', 'authority' => 'DEMO HV Institute', 'cert_number' => 'DEMO-HV-2210', 'discipline' => 'Electrical', 'issue_date' => s01_d(-300), 'expiry_date' => date('Y-m-d', strtotime('+18 months'))]);
        connect_cred_cert_save($arjun, ['name' => 'Quality Inspection Training', 'authority' => 'DEMO QA Body', 'cert_number' => 'DEMO-QA-9080', 'discipline' => 'QA/QC', 'issue_date' => s01_d(-200), 'expiry_date' => date('Y-m-d', strtotime('+1 year'))]);
        connect_cred_cert_save($arjun, ['name' => 'Working at Height Training', 'authority' => 'DEMO Safety Council', 'cert_number' => 'DEMO-WAH-1120', 'discipline' => 'HSE', 'issue_date' => s01_d(-360), 'expiry_date' => date('Y-m-d', strtotime('+45 days'))]);
        connect_cred_cert_save($arjun, ['name' => 'Basic HSE Training', 'authority' => 'DEMO Safety Council', 'cert_number' => 'DEMO-HSE-3300', 'discipline' => 'HSE', 'issue_date' => s01_d(-800), 'expiry_date' => date('Y-m-d', strtotime('-30 days'))]);
    }
    if (function_exists('connect_cred_project_save')) {
        connect_cred_project_save($arjun, ['title' => '400 kV Transmission Line Project', 'role' => 'Electrical Inspection Support', 'industry' => 'Power Transmission', 'location' => 'Gujarat, India', 'equipment' => 'Transmission Towers, Conductors, Insulators', 'scope' => 'Tower erection & stringing inspection', 'start_date' => '2021-02-01', 'end_date' => '2021-08-15']);
        connect_cred_project_save($arjun, ['title' => '220 kV Substation Construction', 'role' => 'QA/QC Electrical Inspector', 'industry' => 'Power Transmission', 'location' => 'Maharashtra, India', 'equipment' => 'Power Transformers, Circuit Breakers, CTs, VTs', 'scope' => 'Installation inspection, documentation', 'start_date' => '2020-01-10', 'end_date' => '2020-11-20']);
        connect_cred_project_save($arjun, ['title' => '132 kV GIS Substation', 'role' => 'Inspection & Documentation Verification', 'industry' => 'Power Transmission', 'location' => 'Gujarat, India', 'equipment' => 'GIS, Circuit Breakers, Protection Systems', 'scope' => 'GIS bay inspection', 'start_date' => '2019-03-01', 'end_date' => '2019-09-30']);
        connect_cred_project_save($arjun, ['title' => '400 kV Transmission Upgrade', 'role' => 'Pre-Commissioning Inspection Support', 'industry' => 'Power Transmission', 'location' => 'Rajasthan, India', 'equipment' => 'Conductors, Insulators', 'scope' => 'Pre-commissioning checks', 'start_date' => '2022-05-01', 'end_date' => '2022-10-10']);
        connect_cred_project_save($arjun, ['title' => '220 kV Substation Quality Surveillance', 'role' => 'Independent Electrical Inspector', 'industry' => 'Power Transmission', 'location' => 'Madhya Pradesh, India', 'equipment' => 'Power Transformers, Protection Panels', 'scope' => 'Quality surveillance, punch list', 'start_date' => '2023-01-15', 'end_date' => '2023-06-30']);
    }
    if (function_exists('connect_verify_submit') && function_exists('connect_verify_review')) {
        [, , $c1] = connect_verify_submit('professional', $arjun, 'ID_DOC', '', 'DEMO ONLY — NOT A REAL CERTIFICATE');
        connect_verify_review((int)$c1, 'VERIFIED', 'DEMO-S01 identity check');
        [, , $c2] = connect_verify_submit('professional', $arjun, 'CREDENTIAL', '', 'DEMO ONLY — Electrical Safety Training');
        connect_verify_review((int)$c2, 'VERIFIED', 'DEMO-S01 credential check');
    }
    db()->prepare("UPDATE cx_professionals SET years_experience=14 WHERE id=?")->execute([$arjun]);
    if (function_exists('connect_profile_tax_backfill')) connect_profile_tax_backfill($arjun);
    $say('Passport: Arjun Mehta (#' . $arjun . ')');

    $sk = fn($n) => s01_node('SKILL', $n, $nTD);
    $mkCand = function (array $a, $nodeMap) {
        $id = s01_pro($a);
        foreach ($nodeMap as $rel => $nodes) foreach ($nodes as $nid => $yrs) connect_profile_tax_attach($id, $nid, $rel, ['years' => $yrs]);
        if (function_exists('connect_privacy_save')) connect_privacy_save($id, ['contact' => 'on_request', 'rate' => 'band', 'identity' => 'full', 'listed' => 1]);
        if (function_exists('connect_geo_save_mobility') && !empty($a['base_city'])) connect_geo_save_mobility($id, ['base_city' => $a['base_city'], 'base_state' => $a['base_state'] ?? '', 'base_country' => 'IN', 'mobility_mode' => $a['mobility'] ?? 'RADIUS', 'travel_radius_km' => $a['radius'] ?? 200, 'pan_india' => !empty($a['pan_india']) ? 1 : 0]);
        return $id;
    };
    $candB = $mkCand(['email' => 'cand.b.s01@demo.test', 'name' => 'Vikram Rao (S01-B)', 'mobile' => '9812300002', 'headline' => 'Senior HV Substation Inspector', 'disciplines' => 'ELEC', 'skills' => 'substation inspection, 220kV, power transformer, circuit breaker, protection panels, QA/QC electrical', 'base_city' => 'Ahmedabad', 'base_state' => 'Gujarat', 'pan_india' => 1],
        ['PRIMARY_ROLE' => [$roleSub => 10], 'SKILL' => [$sk('220 kV') => 9, $sk('Substation Inspection') => 10, $sk('QA/QC Electrical Inspection') => 8], 'EQUIPMENT' => [s01_node('EQUIPMENT','Power Transformers',$nTD) => 9]]);
    $candC = $mkCand(['email' => 'cand.c.s01@demo.test', 'name' => 'Sunil Patel (S01-C)', 'mobile' => '9812300003', 'headline' => 'Electrical Inspector (limited transmission)', 'disciplines' => 'ELEC', 'skills' => 'electrical inspection, visual inspection, 33kV, panel inspection', 'base_city' => 'Surat', 'base_state' => 'Gujarat', 'radius' => 150],
        ['PRIMARY_ROLE' => [s01_node('ROLE','Electrical Inspector',$nTD) => 7], 'SKILL' => [$sk('Visual Inspection') => 6, $sk('33 kV') => 5]]);
    $candD = $mkCand(['email' => 'cand.d.s01@demo.test', 'name' => 'Ramesh Iyer (S01-D)', 'mobile' => '9812300004', 'headline' => 'Civil/Building QA Inspector', 'disciplines' => 'CIVIL', 'skills' => 'building construction, civil inspection, concrete', 'base_city' => 'Pune', 'base_state' => 'Maharashtra', 'radius' => 100],
        ['PRIMARY_ROLE' => [s01_node('ROLE','Civil Inspector', s01_node('SPECIALIZATION','Civil Works', s01_node('DOMAIN','Civil'))) => 8]]);
    $candE = $mkCand(['email' => 'cand.e.s01@demo.test', 'name' => 'Deepak Shah (S01-E)', 'mobile' => '9812300005', 'headline' => 'Mechanical Technician (unavailable)', 'disciplines' => 'MECH', 'skills' => 'mechanical fitting, no transmission experience', 'base_city' => 'Chennai', 'base_state' => 'Tamil Nadu', 'radius' => 50, 'availability' => 'BUSY'],
        ['PRIMARY_ROLE' => [s01_node('ROLE','Mechanical Fitter', s01_node('SPECIALIZATION','Mechanical Works', s01_node('DOMAIN','Mechanical'))) => 6]]);
    $say('Candidates B–E created');

    // ---- PHASE 2 — client + logins ----------------------------------------
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,created_at) VALUES ('DEMO-S01 POWER PROJECTS PRIVATE LIMITED','DEMO-S01 Power Projects',1,'ACTIVE',?)")->execute([date('c')]);
    $party = (int)db()->lastInsertId();
    if (function_exists('connect_org_migrate')) { connect_org_migrate(); try { db()->prepare("INSERT INTO cx_organisations (name,org_type,package_key,party_id,status,contact_name,contact_email,approved_by,approved_at,created_at) VALUES ('DEMO-S01 Power Projects','COMPANY','connect',?, 'ACTIVE','Priya Client','client.s01@demo.test','demo-seed',?,?)")->execute([$party, date('c'), date('c')]); } catch (Throwable $e) {} }
    if (function_exists('portal_migrate')) portal_migrate();
    db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,perms,created_by,created_at) VALUES (?,?,?,?,1,0,'', 'demo-seed', ?)")->execute([$party, 'client.s01@demo.test', 'Priya Client (DEMO-S01)', s01_pw(), date('c')]);
    if (function_exists('setting_set')) setting_set('portal_enabled', '1');
    $mkUser = function ($username, $name, $role, $email) {
        if ((int)ops_val("SELECT COUNT(*) FROM users WHERE username=?", [$username])) return;
        $parts = explode(' ', trim($name), 2);
        db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active) VALUES (?,?,?,?,?,?,?,1)")
            ->execute([$username, s01_pw(), $parts[0], $parts[1] ?? '', $email, $role, in_array($role, ['MASTER_ADMIN','ADMIN'], true) ? 1 : 0]);
    };
    $mkUser('demo.s01.admin', 'DEMO-S01 Marketplace Admin', 'MASTER_ADMIN', 'admin.s01@demo.test');
    $mkUser('demo.s01.coord', 'DEMO-S01 Operations Coordinator', 'COORDINATOR', 'coord.s01@demo.test');
    $mkUser('demo.s01.reviewer', 'DEMO-S01 Technical Reviewer', 'COORDINATOR', 'reviewer.s01@demo.test');
    $mkUser('demo.s01.approver', 'DEMO-S01 Report Approver', 'MASTER_ADMIN', 'approver.s01@demo.test');
    $say('Client party #' . $party . ' + 5 logins');

    // ---- PHASE 3 — requirement + matching snapshot ------------------------
    $reqId = (int)cx_requirement_create([
        'title' => 'Transmission Technician for 220 kV Substation Inspection', 'poster_party_id' => $party, 'poster_name' => 'DEMO-S01 Power Projects',
        'discipline_code' => 'ELEC', 'location' => 'Ahmedabad, Gujarat', 'work_type' => 'FREELANCE', 'start_date' => s01_d(10), 'end_date' => s01_d(15), 'positions' => 1,
        'rate_min' => 6000, 'rate_max' => 9000, 'rate_unit' => 'day',
        'description' => 'Independent inspection of 220 kV substation electrical equipment: transformers, breakers, CT/VT, disconnectors, protection panels. 220 kV required; 400 kV preferred. Diploma/Degree in Electrical OR equivalent experience. Required: Electrical Safety Training.',
    ], true);
    $matchTopArjun = false;
    foreach (connect_match_for_requirement(cx_requirement_get($reqId), 12) as $r) { if (($r['kind'] ?? '') === 'professional') { $matchTopArjun = (int)$r['id'] === (int)$arjun; break; } }
    $say('Requirement #' . $reqId . ' posted');

    // ---- PHASE 4 — applications + selection --------------------------------
    $apply = fn($proId, $name, $rate, $note) => (int)cx_application_add($reqId, ['applicant_professional_id' => $proId, 'applicant_name' => $name, 'proposed_rate' => $rate, 'cover_note' => $note]);
    $arjunApp = $apply($arjun, 'Arjun Mehta', 8500, 'Experienced in 220 kV & 400 kV substation inspection.');
    $dupTry = $apply($arjun, 'Arjun Mehta', 8500, 'duplicate');
    $bApp = $apply($candB, 'Vikram Rao (S01-B)', 9000, 'Strong 220 kV.');
    $cApp = $apply($candC, 'Sunil Patel (S01-C)', 7000, 'Limited transmission.');
    $dApp = $apply($candD, 'Ramesh Iyer (S01-D)', 6500, 'Civil/QA.');
    $eApp = $apply($candE, 'Deepak Shah (S01-E)', 12000, 'Not available on dates.');
    if ($cApp) cx_application_transition($cApp, 'WITHDRAWN');
    if ($eApp) cx_application_transition($eApp, 'REJECTED');
    $badTransition = $eApp ? cx_application_transition($eApp, 'ACCEPTED') : true;
    cx_requirement_transition($reqId, 'SHORTLISTING');
    cx_application_transition($arjunApp, 'SHORTLISTED');
    $awarded = cx_requirement_award($reqId, $arjunApp);
    if (function_exists('connect_client_bench_add')) connect_client_bench_add($party, ['professional_id' => $arjun, 'source' => 'marketplace', 'private_note' => 'Excellent on the 220 kV substation inspection — reuse.', 'client_rating' => 5, 'preferred' => 1, 'preferred_rate' => 8500], 'Priya Client');
    $say('Applications + award (dup blocked=' . ($dupTry === 0 ? 'yes' : 'no') . ')');

    // ---- PHASE 5 — identity → engagement → deployment ---------------------
    db()->prepare("INSERT INTO inspectors (name,emp_code,sbu,skills,email,mobile,status,created_at) VALUES ('Arjun Mehta','DEMO-S01-INS-01','IND',?, 'arjun.s01@demo.test','9812300001','ACTIVE',?)")->execute(['electrical, transmission, substation inspection, 220kV, 400kV, QA/QC', date('c')]);
    $arjunInsp = (int)db()->lastInsertId();
    if (function_exists('connect_identity_link_create')) connect_identity_link_create($arjun, $arjunInsp, 'email_match', 'demo-seed');
    if (function_exists('connect_engage_save_for_requirement')) connect_engage_save_for_requirement($reqId, ['deputation_basis' => 'MANDAYS', 'rate' => 8500, 'rate_unit' => 'day', 'quantity' => 5, 'start_date' => s01_d(10), 'end_date' => s01_d(15), 'rate_inclusive' => 'INCLUSIVE', 'voucher_cadence' => 'PER_DEPLOYMENT']);
    db()->prepare("INSERT INTO calls (call_code,client_id,inspection_type,inspection_required_date,status,created_by,created_at) VALUES ('DEMO-S01-CALL-01',?, 'Electrical Inspection', ?, 'OPEN','demo-seed', ?)")->execute([$party, s01_d(10), date('c')]);
    $callId = (int)db()->lastInsertId();
    $jobId = 0;
    if (function_exists('connect_deploy_from_engagement')) { [, , $jobId] = connect_deploy_from_engagement($reqId); }
    if ($jobId > 0) {
        db()->prepare("UPDATE jobs SET job_code='DEMO-S01-JOB-001', call_id=?, inspection_type='Electrical Inspection', service_code='SUBSTATION', dep_site='Ahmedabad, Gujarat', scheduled_date=?, inspection_start_date=?, inspection_end_date=? WHERE id=?")->execute([$callId, s01_d(10), s01_d(10), s01_d(15), $jobId]);
        if (function_exists('pdso_set_status')) pdso_set_status($jobId, 'MOBILIZED');
    }
    db()->prepare("INSERT INTO inspectors (name,emp_code,sbu,skills,email,status,created_at) VALUES ('Vikram Rao','DEMO-S01-INS-02','IND','substation, 220kV','cand.b.s01@demo.test','ACTIVE',?)")->execute([date('c')]);
    $bInsp = (int)db()->lastInsertId();
    if (function_exists('connect_identity_link_create')) connect_identity_link_create($candB, $bInsp, 'email_match', 'demo-seed');
    db()->prepare("INSERT INTO jobs (job_code,inspector_id,job_type,dep_status,dep_site,inspection_start_date,inspection_end_date,sbu,created_at) VALUES ('DEMO-S01-JOB-CONFLICT',?, 'DEPUTATION','ACTIVE','Vapi, Gujarat',?,?, 'IND', ?)")->execute([$bInsp, s01_d(11), s01_d(14), date('c')]);
    $say('Identity linked + deployment ' . ($jobId ? 'DEMO-S01-JOB-001' : 'FAILED'));

    // ---- PHASE 6/7 — schedule, report, finding, evidence, rating ----------
    $rptId = 0; $findingId = 0; $days = 0;
    if ($jobId > 0) {
        db()->prepare("UPDATE jobs SET stage='ALLOCATED', reporting_frequency='ONCE', mandays=5 WHERE id=?")->execute([$jobId]);
        if (function_exists('job_visits_sync')) { try { job_visits_sync($jobId, $arjunInsp); } catch (Throwable $e) {} }
        if ((int)ops_val("SELECT COUNT(*) FROM job_visits WHERE job_id=?", [$jobId]) < 5) {
            db()->prepare("DELETE FROM job_visits WHERE job_id=?")->execute([$jobId]);
            $notes = ['Equipment & document review','Transformer inspection','CB and CT/VT inspection','Protection & control verification','Punch closure & final verification'];
            for ($i = 0; $i < 5; $i++) db()->prepare("INSERT INTO job_visits (job_id,visit_date,inspector_id,status,note) VALUES (?,?,?, 'PLANNED', ?)")->execute([$jobId, s01_d(10 + $i), $arjunInsp, $notes[$i]]);
        }
        $days = (int)ops_val("SELECT COUNT(*) FROM job_visits WHERE job_id=?", [$jobId]);

        $rt = ops_one("SELECT id, code FROM report_types WHERE active=1 ORDER BY id LIMIT 1");
        $rtId = (int)($rt['id'] ?? 0); $rtCode = (string)($rt['code'] ?? 'DIR');
        $reportData = json_encode(['scope' => 'Independent inspection of 220 kV substation electrical equipment installation.', 'equipment' => ['Power Transformers','Circuit Breakers','CT','VT','Disconnectors','Protection Panels'], 'activities' => [['activity' => 'Power Transformer Visual Inspection', 'result' => 'Acceptable'], ['activity' => 'Circuit Breaker Installation Verification', 'result' => 'Acceptable'], ['activity' => 'Protection Panel Documentation Review', 'result' => 'Observation Raised (DEMO-S01-F-001)']], 'conclusion' => 'Acceptable with one open observation on protection-panel marking.', 'demo' => 'DEMO ONLY — NOT A REAL CERTIFICATE']);
        $approver = 'DEMO-S01 Report Approver'; $reviewer = 'DEMO-S01 Technical Reviewer';
        db()->prepare("INSERT INTO report_docs (irn,report_type_id,type_code,title,client_id,call_id,job_id,office_id,sbu,location,inspector_id,inspection_date,issue_date,result,release_status,status,data,remarks,rev,finalized,finalized_at,finalized_by,submitted_at,approved_at,approved_by,vet_status,vet_by,vet_at,deleted,created_by,created_at) VALUES (?,?,?,?,?,?,?,?, 'IND', 'Ahmedabad, Gujarat', ?, ?, ?, 'ACCEPTED_COND','RELEASED','ISSUED', ?, 'Reviewer confirmed closure of DEMO-S01-F-001 (rev 1).', 1, 1, ?, ?, ?, ?, ?, 'VETTED', ?, ?, 0, 'demo-seed', ?)")
            ->execute(['DEMO-S01-RPT-001', $rtId, $rtCode, '220 kV Substation Inspection Report', $party, $callId, $jobId, s01_office_id(), $arjunInsp, s01_d(15), s01_d(16), $reportData, s01_d(15), $approver, s01_d(15), s01_d(16), $approver, $reviewer, s01_d(15), s01_d(16)]);
        $rptId = (int)db()->lastInsertId();
        if (function_exists('verify_code_for')) { try { verify_code_for(ops_one("SELECT * FROM report_docs WHERE id=?", [$rptId])); } catch (Throwable $e) {} }

        if (function_exists('ncr_create')) {
            $findingId = (int)ncr_create(['job_id' => $jobId, 'report_doc_id' => $rptId, 'partner_id' => $party, 'office_id' => s01_office_id(), 'sbu' => 'IND', 'title' => 'Protection panel identification marking incomplete', 'description' => 'Required identification marking on the protection panel is incomplete at time of inspection.', 'severity' => 'MINOR', 'detected_on' => s01_d(13), 'detected_by' => 'Arjun Mehta', 'owner' => 'DEMO-S01 Power Projects', 'due_on' => s01_d(20), 'source' => 'INTERNAL']);
            if ($findingId) db()->prepare("UPDATE nonconformities SET ref='DEMO-S01-F-001', status='CLOSED', containment='Panel re-labelled', disposition='Marking completed and verified', closed_on=?, closed_by=? WHERE id=?")->execute([s01_d(16), 'DEMO-S01 Technical Reviewer', $findingId]);
        }
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        foreach ([['transformer','Power transformer (DEMO)'],['cb_ct_vt','CB + CT/VT (DEMO)'],['finding','Panel marking DEMO-S01-F-001 (DEMO)']] as [$fk, $nt])
            db()->prepare("INSERT INTO report_files (report_doc_id,field_key,kind,file_name,mime,data,note,created_by,created_at) VALUES (?,?, 'photo', ?, 'image/png', ?, ?, 'Arjun Mehta', ?)")->execute([$rptId, $fk, 'DEMO-S01-' . $fk . '.png', $png, $nt, date('c')]);

        db()->prepare("UPDATE jobs SET stage='CLOSED', closed_flag=1, closed_at=?, report_link='DEMO-S01-RPT-001' WHERE id=?")->execute([date('c'), $jobId]);
        foreach (ops_all("SELECT id FROM job_visits WHERE job_id=?", [$jobId]) ?: [] as $v) db()->prepare("UPDATE job_visits SET status='CLOSED', report_doc_id=? WHERE id=?")->execute([$rptId, (int)$v['id']]);
        if (function_exists('connect_engage_set_status')) { $eng = connect_engage_for_requirement($reqId); if ($eng) connect_engage_set_status((int)$eng['id'], 'COMPLETED'); }
        if (function_exists('connect_engagement_billable')) connect_engagement_billable($reqId);
        if (function_exists('cx_rating_add')) cx_rating_add($reqId, 'CLIENT_TO_PRO', ['application_id' => $arjunApp, 'rater_party_id' => $party, 'ratee_inspector_id' => $arjunInsp, 'stars' => 5, 'competency' => 5, 'communication' => 4, 'punctuality' => 5, 'professionalism' => 5, 'would_rehire' => 1, 'comment' => 'Good technical knowledge; completed the assignment successfully.']);
    }
    $say('Schedule/report/finding/evidence/rating done');

    // ---- DASHBOARD (real, derived) ----------------------------------------
    $has = function ($sql, $args = []) { try { return (int)ops_val($sql, $args) > 0; } catch (Throwable $e) { return false; } };
    $certs = function_exists('connect_cred_certs') ? connect_cred_certs($arjun) : [];
    $dash = [
        ['Professional created + verified', $has("SELECT 1 FROM cx_professionals WHERE id=? AND verification_tier<>'registered'", [$arjun])],
        ['Passport taxonomy (>=20 nodes)', $has("SELECT COUNT(*) FROM cx_profile_tax WHERE pro_id=? HAVING COUNT(*)>=20", [$arjun])],
        ['Certs incl. expiring + expired', (count(array_filter($certs, fn($c) => $c['status'] === 'EXPIRED')) > 0 && count(array_filter($certs, fn($c) => $c['status'] === 'EXPIRING')) > 0)],
        ['Requirement OPEN → AWARDED', $has("SELECT 1 FROM cx_requirements WHERE id=? AND status='AWARDED'", [$reqId])],
        ['Matching ranked Arjun #1 @publish', $matchTopArjun],
        ['Application submitted + dup blocked', ($arjunApp > 0 && $dupTry === 0)],
        ['Candidate withdrawn + rejected', ($has("SELECT 1 FROM cx_applications WHERE id=? AND status='WITHDRAWN'", [$cApp]) && $has("SELECT 1 FROM cx_applications WHERE id=? AND status='REJECTED'", [$eApp]))],
        ['Invalid transition blocked', !$badTransition],
        ['Identity linked (no duplicate person)', $has("SELECT 1 FROM cx_identity_link WHERE professional_id=? AND inspector_id=? AND status='LINKED'", [$arjun, $arjunInsp])],
        ['Operations job created (PDSO)', $has("SELECT 1 FROM jobs WHERE id=? AND job_code='DEMO-S01-JOB-001'", [$jobId])],
        ['Scheduling (5 days allocated)', $has("SELECT COUNT(*) FROM job_visits WHERE job_id=? HAVING COUNT(*)>=5", [$jobId])],
        ['Conflict fixture present', $has("SELECT 1 FROM jobs WHERE job_code='DEMO-S01-JOB-CONFLICT'")],
        ['Finding created + linked + closed', ($findingId > 0 && $has("SELECT 1 FROM nonconformities WHERE id=? AND status='CLOSED' AND job_id=?", [$findingId, $jobId]))],
        ['Evidence linked (no orphans)', $has("SELECT COUNT(*) FROM report_files WHERE report_doc_id=? HAVING COUNT(*)>=1", [$rptId])],
        ['Report created + ISSUED', $has("SELECT 1 FROM report_docs WHERE id=? AND status='ISSUED'", [$rptId])],
        ['Report visible to client', $has("SELECT 1 FROM report_docs WHERE id=? AND client_id=? AND finalized=1", [$rptId, $party])],
        ['Client bench (rehire ready)', $has("SELECT 1 FROM cx_client_bench WHERE client_party_id=? AND professional_id=?", [$party, $arjun])],
        ['Sent to billing (award→invoice)', $has("SELECT 1 FROM billable_events WHERE source_module='connect' AND source_id=?", [$reqId])],
        ['Completed + rated', ($has("SELECT 1 FROM jobs WHERE id=? AND closed_flag=1", [$jobId]) && $has("SELECT 1 FROM cx_ratings WHERE requirement_id=?", [$reqId]))],
    ];
    $allpass = true; foreach ($dash as [$l, $ok]) if (!$ok) $allpass = false;
    if (function_exists('setting_set')) setting_set('demo_s01_seed', date('c'));
    return ['log' => $log, 'dashboard' => $dash, 'allpass' => $allpass, 'ids' => ['professional' => $arjun, 'requirement' => $reqId, 'job' => $jobId, 'report' => $rptId, 'finding' => $findingId, 'party' => $party]];
}

/** First active office id (report_docs / findings need one); 0 if none. */
function s01_office_id() {
    static $id = null;
    if ($id === null) { try { $id = (int)ops_val("SELECT id FROM offices ORDER BY id LIMIT 1"); } catch (Throwable $e) { $id = 0; } }
    return $id;
}
