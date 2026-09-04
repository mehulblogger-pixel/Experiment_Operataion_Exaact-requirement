<?php
// ============================================================================
//  DEMO-S04 — Marketplace lifecycle exercising the NEW engines (CLI + admin).
//
//  Fourth in the progressive program (S01 freelancer · S02 agency · S03 client
//  foundation → S04 the full marketplace lifecycle). It seeds ONE realistic
//  thread that lights up every capability built in this revamp — supplier types,
//  the resource-conflict flag, the mobilization gate pass, cancellation/no-show
//  needs-cover, the marketplace→operations deployment bridge, the credential
//  verification ladder and the billing-mismatch flag — all on the existing
//  tables, namespaced DEMO-S04, idempotent (purge-first), with a real derived
//  PASS/FAIL dashboard. Reuses s01_pw()/s01_d() from DEMO-S01.
// ============================================================================

function seed_s04_status() {
    return [
        'loaded'   => function_exists('setting_get') ? (bool)setting_get('demo_s04_seed') : false,
        'pros'     => (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE email LIKE '%s04pro@demo.test'"),
        'reqs'     => (int)ops_val("SELECT COUNT(*) FROM cx_requirements WHERE poster_name='DEMO-S04'"),
    ];
}

function seed_s04_remove() {
    $n = 0;
    $del = function ($sql, $args = []) use (&$n) { try { $st = db()->prepare($sql); $st->execute($args); $n += $st->rowCount(); } catch (Throwable $e) {} };
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM cx_professionals WHERE email LIKE '%s04pro@demo.test'") ?: []) as $pid) {
        foreach (['cx_pro_certs' => 'pro_id', 'cx_profile_tax' => 'pro_id', 'cx_bench' => 'professional_id', 'cx_privacy' => 'pro_id'] as $t => $col) $del("DELETE FROM $t WHERE $col=?", [$pid]);
        $del("DELETE FROM cx_applications WHERE applicant_professional_id=?", [$pid]);
        $del("DELETE FROM cx_engagements WHERE subject_kind='professional' AND subject_id=?", [$pid]);
        $del("DELETE FROM cx_professionals WHERE id=?", [$pid]);
    }
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM business_partners WHERE code LIKE 'DEMO-S04-%'") ?: []) as $pid) {
        $del("DELETE FROM cx_organisations WHERE party_id=?", [$pid]);
        $del("DELETE FROM cx_org_capabilities WHERE org_party_id=?", [$pid]);
        $del("DELETE FROM client_users WHERE partner_id=?", [$pid]);
        $del("DELETE FROM cx_requirements WHERE poster_party_id=?", [$pid]);
        $del("DELETE FROM billable_events WHERE party_id=?", [$pid]);
        $del("DELETE FROM dep_gate_pass WHERE job_id IN (SELECT id FROM jobs WHERE source_requirement_id IN (SELECT id FROM cx_requirements WHERE poster_party_id=?))", [$pid]);
    }
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM jobs WHERE job_code LIKE 'DEMO-S04-%'") ?: []) as $jid) { $del("DELETE FROM dep_checklist WHERE job_id=?", [$jid]); $del("DELETE FROM dep_gate_pass WHERE job_id=?", [$jid]); $del("DELETE FROM jobs WHERE id=?", [$jid]); }
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM calls WHERE call_code LIKE 'DEMO-S04-%'") ?: []) as $cid) $del("DELETE FROM calls WHERE id=?", [$cid]);
    $del("DELETE FROM cx_organisations WHERE name LIKE 'DEMO-S04 %'");
    $del("DELETE FROM business_partners WHERE code LIKE 'DEMO-S04-%'");
    $del("DELETE FROM client_users WHERE email LIKE '%s04@demo.test'");
    if (function_exists('setting_set')) setting_set('demo_s04_seed', '');
    return $n;
}

function seed_s04_load() {
    seed_s04_remove();
    try { db()->exec("SET SESSION sql_mode=''"); } catch (Throwable $e) {}
    $now = date('c'); $log = []; $say = function ($s) use (&$log) { $log[] = $s; };
    foreach (['connect_org_migrate', 'connect_cap_migrate', 'connect_bench_migrate', 'connect_engage_migrate', 'connect_deploy_migrate', 'pdso_gate_migrate', 'billable_migrate', 'connect_pro_migrate', 'connect_privacy_migrate', 'connect_cred_migrate'] as $mg) if (function_exists($mg)) { try { $mg(); } catch (Throwable $e) {} }
    $office = (int)(ops_val("SELECT id FROM offices ORDER BY id LIMIT 1") ?: 0);

    // 1) A supplier AGENCY with a multi-capability profile incl. Freelance Supply.
    db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES ('DEMO-S04-AGENCY','DEMO-S04 Apex Technical Manpower','DEMO-S04 Apex Technical Manpower',0,1,'ACTIVE',?)")->execute([$now]);
    $agencyParty = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_organisations (name,org_type,package_key,party_id,status,created_at) VALUES ('DEMO-S04 Apex Technical Manpower','MANPOWER_AGENCY','STAFFING',?, 'ACTIVE', ?)")->execute([$agencyParty, $now]);
    $agencyOrg = (int)db()->lastInsertId();
    if (function_exists('connect_org_cap_bulk_set')) connect_org_cap_bulk_set($agencyParty, ['TECHNICAL_MANPOWER', 'FREELANCE_SUPPLY', 'FREELANCE_INSPECTOR_SUPPLY'], 'demo-seed');

    // 2) The client + a Technical-Manager portal login.
    db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,status,created_at) VALUES ('DEMO-S04-CLIENT','DEMO-S04 EPC Client Ltd','DEMO-S04 EPC Client Ltd',1,'ACTIVE',?)")->execute([$now]);
    $client = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,perms,role_preset,created_by,created_at) VALUES (?, 's04.tech@demo.test','S04 Technical Manager',?,1,0,'calls,reports,reports.decide,request,complaint,market.post','TECHNICAL','demo-seed',?)")->execute([$client, s01_pw(), $now]);
    if (function_exists('setting_set')) setting_set('portal_enabled', '1');

    // 3) Three applicant professionals — two on the agency bench (supplier badge),
    //    one individual. All privacy-first (masked name + on-request contact).
    $apps = [];
    for ($i = 1; $i <= 3; $i++) {
        db()->prepare("INSERT INTO cx_professionals (email,name,mobile,headline,skills,disciplines,availability,is_active,verification_tier,base_city,passport_token,password_hash,created_at) VALUES (?,?,?,?,?,?, 'AVAILABLE',1,'registered',?,?,?,?)")
            ->execute(["s04applicant$i.s04pro@demo.test", ['Rajesh Kumar Sharma', 'Anil Verma', 'Suresh Pillai'][$i - 1], '98' . rand(10000000, 99999999),
                       'QA/QC & welding inspector', 'QA/QC, welding inspection, piping, NDT', 'Welding Inspection, Mechanical', 'Vadodara, Gujarat', substr(md5('s04app' . $i), 0, 20), s01_pw(), $now]);
        $apps[$i] = (int)db()->lastInsertId();
        if (function_exists('connect_privacy_save')) connect_privacy_save($apps[$i], ['identity' => 'first_initial', 'contact' => 'on_request', 'rate' => 'band', 'listed' => 1]);
    }
    // credential verification ladder: one verified, one document-attached, one self-declared
    if (function_exists('connect_cred_cert_save')) {
        connect_cred_cert_save($apps[1], ['name' => 'CSWIP 3.1 Welding Inspector', 'authority' => 'TWI', 'cert_number' => 'DEMO-S04-CSWIP', 'discipline' => 'WELD', 'issue_date' => s01_d(-300), 'expiry_date' => s01_d(500)]);
        try { db()->prepare("UPDATE cx_pro_certs SET verified=1 WHERE pro_id=? AND cert_number='DEMO-S04-CSWIP'")->execute([$apps[1]]); } catch (Throwable $e) {}
        connect_cred_cert_save($apps[2], ['name' => 'ASNT NDT Level II', 'authority' => 'ASNT', 'cert_number' => 'DEMO-S04-NDT', 'discipline' => 'NDT', 'issue_date' => s01_d(-200), 'expiry_date' => s01_d(400)]);
        try { db()->prepare("UPDATE cx_pro_certs SET file_id=9, verified=0 WHERE pro_id=? AND cert_number='DEMO-S04-NDT'")->execute([$apps[2]]); } catch (Throwable $e) {}
        connect_cred_cert_save($apps[3], ['name' => 'NACE Coating Inspector', 'authority' => 'NACE', 'cert_number' => '', 'discipline' => 'COAT', 'issue_date' => s01_d(-100), 'expiry_date' => s01_d(600)]);
    }
    // bench the first two under the agency → supplier type = Freelance Resource Supplier
    foreach ([1, 2] as $i) db()->prepare("INSERT INTO cx_bench (org_id,name,professional_id,is_active,created_at) VALUES (?,?,?,1,?)")->execute([$agencyOrg, 'Applicant', $apps[$i], $now]);

    // 4) A requirement with the three applicants; one has an overlapping booking.
    $rid = (int)cx_requirement_create(['title' => 'QA/QC Mechanical Engineer (S04)', 'poster_party_id' => $client, 'poster_name' => 'DEMO-S04', 'discipline_code' => 'MECH', 'location' => 'Jamnagar', 'work_type' => 'FREELANCE', 'positions' => 2, 'start_date' => s01_d(10), 'end_date' => s01_d(40), 'rate_min' => 8000, 'rate_max' => 14000, 'rate_unit' => 'day', 'description' => 'QA/QC mechanical, 6 months.'], true);
    $appIds = [];
    foreach ([1 => 'APPLIED', 2 => 'APPLIED', 3 => 'SHORTLISTED'] as $i => $st) {
        $aid = (int)cx_application_add($rid, ['applicant_professional_id' => $apps[$i], 'applicant_name' => 'Applicant', 'proposed_rate' => 9000]);
        if ($aid && $st !== 'APPLIED') db()->prepare("UPDATE cx_applications SET status=? WHERE id=?")->execute([$st, $aid]);
        $appIds[$i] = $aid;
    }
    // applicant #1 already BOOKED elsewhere over the same window → conflict flag
    $rq = ops_one("SELECT start_date, end_date FROM cx_requirements WHERE id=?", [$rid]);
    db()->prepare("INSERT INTO cx_engagements (subject_kind,subject_id,subject_name,poster_party_id,poster_name,start_date,end_date,status,created_at) VALUES ('professional',?, 'Rajesh K.',?, 'Another Client', ?, ?, 'BOOKED', ?)")
        ->execute([$apps[1], 0, (string)$rq['start_date'], (string)$rq['end_date'], $now]);

    // 5) Award a SECOND requirement and deploy it → operations bridge + gate pass.
    $rid2 = (int)cx_requirement_create(['title' => 'Piping Inspector (S04 awarded)', 'poster_party_id' => $client, 'poster_name' => 'DEMO-S04', 'discipline_code' => 'MECH', 'location' => 'Hazira', 'work_type' => 'FREELANCE', 'positions' => 1, 'start_date' => s01_d(5), 'end_date' => s01_d(35), 'rate_min' => 9000, 'rate_max' => 13000, 'rate_unit' => 'day'], true);
    $aid2 = (int)cx_application_add($rid2, ['applicant_professional_id' => $apps[3], 'applicant_name' => 'Applicant', 'proposed_rate' => 10000]);
    db()->prepare("UPDATE cx_applications SET status='ACCEPTED' WHERE id=?")->execute([$aid2]);
    db()->prepare("UPDATE cx_requirements SET status='AWARDED', awarded_application_id=?, updated_at=? WHERE id=?")->execute([$aid2, $now, $rid2]);
    db()->prepare("INSERT INTO calls (call_code,client_id,inspection_type,inspection_required_date,status,created_by,created_at) VALUES ('DEMO-S04-CALL',?, 'Inspection', ?, 'OPEN','demo-seed', ?)")->execute([$client, s01_d(5), $now]);
    $call = (int)db()->lastInsertId();
    // the deployment created from the award (source_requirement_id links them)
    db()->prepare("INSERT INTO jobs (job_code,call_id,job_type,dep_status,dep_site,source_module,source_requirement_id,sbu,inspection_start_date,inspection_end_date,created_at) VALUES ('DEMO-S04-DEP-1',?, 'DEPUTATION','MOB_PENDING','Hazira','connect',?, 'IND', ?, ?, ?)")->execute([$call, $rid2, s01_d(5), s01_d(35), $now]);
    $depJob = (int)db()->lastInsertId();
    // gate pass: this job has a required, still-open checklist item → NOT CLEARED
    db()->prepare("INSERT INTO dep_checklist (job_id,phase,item,category,required,status,sort_order,updated_at) VALUES (?, 'MOB','Medical fitness','MEDICAL',1,'REQUIRED',1,?)")->execute([$depJob, $now]);
    // a second job that IS cleared (gate pass issued)
    db()->prepare("INSERT INTO jobs (job_code,call_id,job_type,dep_status,dep_site,source_module,sbu,inspection_start_date,created_at) VALUES ('DEMO-S04-DEP-2',?, 'DEPUTATION','ACTIVE','Hazira','connect','IND', ?, ?)")->execute([$call, s01_d(-2), $now]);
    $depJob2 = (int)db()->lastInsertId();
    if (function_exists('mobilization_gate_issue')) mobilization_gate_issue($depJob2, 'demo-seed', 'Verified at gate');

    // 6) A NO-SHOW engagement that still needs cover.
    db()->prepare("INSERT INTO cx_engagements (requirement_id,subject_kind,subject_id,subject_name,poster_party_id,poster_name,start_date,end_date,status,cancel_kind,cancel_reason,cancelled_at,cancelled_by,created_at) VALUES (?, 'professional',?, 'Anil V.',?, 'DEMO-S04', ?, ?, 'CANCELLED','NO_SHOW','Did not report to Hazira on day 1', ?, 'S04 Technical Manager', ?)")
        ->execute([$rid, $apps[2], $client, s01_d(-6), s01_d(-1), $now, $now]);

    // 7) A billing mismatch — a billed event whose invoice drifted from the earned amount.
    try {
        db()->prepare("INSERT INTO billable_events (source_module,source_kind,source_id,party_id,office_id,sbu,service_type,qty,unit,rate,amount,derived_amount,status,invoice_id,created_at) VALUES ('timesheet','TIMESHEET_APPROVED',987041,?,?, 'IND','Inspection man-days',10,'day',1000,8500,10000,'BILLED',0,?)")->execute([$client, $office, $now]);
    } catch (Throwable $e) {}

    $say('Agency (multi-capability) + bench of 2 · client + technical manager · 3 masked applicants');
    $say('Conflict booking · awarded+deployed requirement · gate pass (cleared + not) · no-show · billing mismatch');

    // ---- DASHBOARD (real, derived — asserts each NEW engine lights up) --------
    $has = function ($cond) { return $cond ? true : false; };
    $conf1 = function_exists('connect_conflict_check') ? connect_conflict_check($apps[1], (string)$rq['start_date'], (string)$rq['end_date']) : ['status' => '?'];
    $conf3 = function_exists('connect_conflict_check') ? connect_conflict_check($apps[3], (string)$rq['start_date'], (string)$rq['end_date']) : ['status' => '?'];
    $sup1  = function_exists('connect_supplier_type') ? connect_supplier_type($apps[1]) : ['channel' => '?'];
    $sup3  = function_exists('connect_supplier_type') ? connect_supplier_type($apps[3]) : ['channel' => '?'];
    $gate1 = function_exists('mobilization_gate') ? mobilization_gate($depJob) : ['ready' => true];
    $gate2 = function_exists('mobilization_gate') ? mobilization_gate($depJob2) : ['cleared' => false];
    $cover = function_exists('connect_engage_needs_cover') ? connect_engage_needs_cover($client) : [];
    $mismatch = function_exists('billable_mismatch') ? billable_mismatch() : [];
    $deploy = function_exists('connect_deploy_row_for_requirement') ? connect_deploy_row_for_requirement($rid2) : null;
    $vstate = function_exists('connect_cred_verify_state') ? connect_cred_verify_state(ops_one("SELECT * FROM cx_pro_certs WHERE cert_number='DEMO-S04-CSWIP'") ?: []) : ['code' => '?'];

    $dash = [
        ['Supplier type: benched applicant → org-supplied', $sup1['channel'] === 'ORG' && strpos($sup1['type'] ?? '', 'Freelance') !== false],
        ['Supplier type: individual applicant → Individual', $sup3['channel'] === 'INDIVIDUAL'],
        ['Conflict flag: overlapping-booked applicant = CONFLICT', ($conf1['status'] ?? '') === 'CONFLICT'],
        ['Conflict flag: free applicant = CLEAR', ($conf3['status'] ?? '') === 'CLEAR'],
        ['Gate pass: mob-pending job NOT cleared (blocker open)', empty($gate1['ready'])],
        ['Gate pass: prepared job cleared for site entry', !empty($gate2['cleared'])],
        ['Cancellation: a no-show engagement needs cover', count($cover) >= 1],
        ['Deployment bridge: awarded requirement → operational job', $deploy !== null && (int)($deploy['source_requirement_id'] ?? 0) === $rid2],
        ['Billing mismatch: a billed event disagrees with its invoice', count($mismatch) >= 1],
        ['Verification ladder: a verified credential reads VERIFIED', ($vstate['code'] ?? '') === 'VERIFIED'],
    ];
    $allpass = true; foreach ($dash as [$l, $ok]) if (!$ok) $allpass = false;
    if (function_exists('setting_set')) setting_set('demo_s04_seed', date('c'));
    return ['log' => $log, 'dashboard' => $dash, 'allpass' => $allpass];
}
