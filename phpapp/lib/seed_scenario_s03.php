<?php
// ============================================================================
//  DEMO-S03 — Client + Client-Portal foundation seed (CLI + admin button).
//
//  The client-side foundation reused by later scenario prompts (04–12): 6 client
//  organisations, branches, locations, departments, NAMED contacts, portal users
//  (five role types + lifecycle states), activity timelines and a spread of
//  requirements — all on the ONE existing client master (business_partners) and
//  the existing portal/CRM tables. Plus a showcase client (EPC) whose ROLE-BASED
//  dashboards render the exact target values via connect_client_dash (live-
//  computed, never hard-coded). No duplicate client database. Tagged DEMO-S03.
// ============================================================================

function seed_s03_status() {
    return [
        'loaded'  => function_exists('setting_get') ? (bool)setting_get('demo_s03_seed') : false,
        'clients' => (int)ops_val("SELECT COUNT(*) FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%'"),
        'users'   => (int)ops_val("SELECT COUNT(*) FROM client_users WHERE email LIKE '%s03@demo.test'"),
        'reqs'    => (int)ops_val("SELECT COUNT(*) FROM cx_requirements WHERE poster_party_id IN (SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%')"),
    ];
}

function seed_s03_remove() {
    $n = 0;
    $del = function ($sql, $args = []) use (&$n) { try { $st = db()->prepare($sql); $st->execute($args); $n += $st->rowCount(); } catch (Throwable $e) {} };
    $parties = array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-%'") ?: []);
    // showcase professionals (applicants)
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM cx_professionals WHERE email LIKE '%s03pro@demo.test'") ?: []) as $pid) {
        foreach (['cx_profile_tax','cx_pro_certs','cx_pro_projects','cx_identity_link','cx_client_bench'] as $t) { $col = ($t === 'cx_identity_link' || $t === 'cx_client_bench') ? 'professional_id' : 'pro_id'; $del("DELETE FROM $t WHERE $col=?", [$pid]); }
        $del("DELETE FROM cx_applications WHERE applicant_professional_id=?", [$pid]);
        $del("DELETE FROM cx_professionals WHERE id=?", [$pid]);
    }
    foreach ($parties as $pid) {
        foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM cx_requirements WHERE poster_party_id=?", [$pid]) ?: []) as $rid) {
            $del("DELETE FROM cx_applications WHERE requirement_id=?", [$rid]);
            $del("DELETE FROM cx_requirements WHERE id=?", [$rid]);
        }
        $del("DELETE FROM partner_contacts WHERE partner_id=?", [$pid]);
        $del("DELETE FROM partner_addresses WHERE partner_id=?", [$pid]);
        $del("DELETE FROM client_users WHERE partner_id=?", [$pid]);
        try { $del("DELETE FROM cx_engagements WHERE poster_party_id=?", [$pid]); } catch (Throwable $e) {}
        try { $del("DELETE FROM billable_events WHERE party_id=?", [$pid]); } catch (Throwable $e) {}
        try { $del("DELETE FROM dep_att_approval WHERE client_id=?", [$pid]); } catch (Throwable $e) {}
        try { $del("DELETE FROM report_docs WHERE client_id=? AND irn LIKE 'DEMO-S03-%'", [$pid]); } catch (Throwable $e) {}
        try { $del("DELETE FROM activities WHERE partner_id=? AND kind LIKE 'S03_%'", [$pid]); } catch (Throwable $e) {}
        try { $del("DELETE FROM cx_organisations WHERE party_id=?", [$pid]); } catch (Throwable $e) {}
    }
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM jobs WHERE job_code LIKE 'DEMO-S03-%'") ?: []) as $jid) { $del("DELETE FROM job_visits WHERE job_id=?", [$jid]); $del("DELETE FROM jobs WHERE id=?", [$jid]); }
    foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM calls WHERE call_code LIKE 'DEMO-S03-%'") ?: []) as $cid) { $del("DELETE FROM jobs WHERE call_id=?", [$cid]); $del("DELETE FROM calls WHERE id=?", [$cid]); }
    $del("DELETE FROM inspectors WHERE emp_code LIKE 'DEMO-S03-%'");
    $del("DELETE FROM business_partners WHERE code LIKE 'DEMO-S03-BRANCH-%'");
    $del("DELETE FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%'");
    $del("DELETE FROM client_users WHERE email LIKE '%s03@demo.test'");
    if (function_exists('setting_set')) setting_set('demo_s03_seed', '');
    return $n;
}

function seed_s03_load() {
    seed_s03_remove();
    try { db()->exec("SET SESSION sql_mode=''"); } catch (Throwable $e) {}
    $log = []; $say = function ($s) use (&$log) { $log[] = $s; };
    $now = date('c');
    $office = function () { static $o = null; if ($o === null) { try { $o = (int)ops_val("SELECT id FROM offices ORDER BY id LIMIT 1"); } catch (Throwable $e) { $o = 0; } } return $o; };

    // ---- helpers ----------------------------------------------------------
    $mkClient = function ($code, $name, $type, $industry) use ($now) {
        db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,client_type,industry,status,created_at) VALUES (?,?,?,1,?,?, 'ACTIVE', ?)")->execute([$code, $name, $name, $type, $industry, $now]);
        $pid = (int)db()->lastInsertId();
        if (function_exists('connect_org_migrate')) { connect_org_migrate(); try { db()->prepare("INSERT INTO cx_organisations (name,org_type,package_key,party_id,status,approved_by,approved_at,created_at) VALUES (?, 'COMPANY','connect',?, 'ACTIVE','demo-seed',?,?)")->execute([$name, $pid, $now, $now]); } catch (Throwable $e) {} }
        return $pid;
    };
    $mkBranch = function ($parentId, $code, $name) use ($now) { db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,parent_id,status,created_at) VALUES (?,?,?,1,?, 'ACTIVE', ?)")->execute([$code, $name, $name, $parentId, $now]); return (int)db()->lastInsertId(); };
    $mkLoc = function ($pid, $label, $city) { db()->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city) VALUES (?, 'SITE', ?, ?, ?)")->execute([$pid, $label, $label, $city]); };
    $mkContact = function ($pid, $name, $desig, $dept, $email, $primary = 0) { db()->prepare("INSERT INTO partner_contacts (partner_id,name,designation,department,email,mobile,is_primary) VALUES (?,?,?,?,?,?,?)")->execute([$pid, $name, $desig, $dept, $email, '9' . rand(700000000, 999999999), $primary]); };
    $mkUser = function ($pid, $email, $name, $preset, $perms, $state = 'active') use ($now) {
        $active = $state === 'suspended' ? 0 : 1; $mc = $state === 'invited' ? 1 : 0; $tok = $state === 'invited' ? substr(md5($email), 0, 24) : '';
        db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,perms,role_preset,invite_token,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?, 'demo-seed', ?)")->execute([$pid, $email, $name, s01_pw(), $active, $mc, $perms, $preset, $tok, $now]);
    };
    $mkReq = function ($pid, $name, $title, $disc, $loc, $positions, $status, $desc = '') {
        $id = (int)cx_requirement_create(['title' => $title, 'poster_party_id' => $pid, 'poster_name' => $name, 'discipline_code' => $disc, 'location' => $loc, 'work_type' => 'FREELANCE', 'positions' => $positions, 'start_date' => s01_d(10), 'end_date' => s01_d(40), 'rate_min' => 6000, 'rate_max' => 14000, 'rate_unit' => 'day', 'description' => $desc], strtoupper($status) !== 'DRAFT');
        if (!in_array(strtoupper($status), ['DRAFT', 'OPEN'], true)) db()->prepare("UPDATE cx_requirements SET status=?, updated_at=? WHERE id=?")->execute([strtoupper($status), date('c'), $id]);
        return $id;
    };
    $act = function ($pid, $kind, $subject, $daysAgo = 0) {
        try { db()->prepare("INSERT INTO activities (kind,entity_kind,entity_id,partner_id,subject,occurred_at,auto,created_at) VALUES (?, 'partner', ?, ?, ?, ?, 1, ?)")->execute(['S03_' . $kind, $pid, $pid, $subject, date('c', strtotime("-$daysAgo days")), date('c')]); }
        catch (Throwable $e) { if (function_exists('act_log')) { try { act_log('partner', $pid, 'S03_' . $kind, $subject, ['partner_id' => $pid, 'auto' => 1]); } catch (Throwable $e2) {} } }
    };

    // ---- 6 client organisations + branches + locations --------------------
    $C1 = $mkClient('DEMO-S03-CLIENT-01', 'EXAACT Demo EPC Projects India Private Limited', 'EPC Contractor', 'Oil & Gas');
    $C2 = $mkClient('DEMO-S03-CLIENT-02', 'EXAACT Demo Energy & Refinery Limited', 'Asset Owner', 'Refinery');
    $C3 = $mkClient('DEMO-S03-CLIENT-03', 'EXAACT Demo Heavy Engineering Limited', 'Manufacturer', 'Heavy Fabrication');
    $C4 = $mkClient('DEMO-S03-CLIENT-04', 'EXAACT Demo Power Transmission Solutions Limited', 'Power / Transmission', 'Power Transmission');
    $C5 = $mkClient('DEMO-S03-CLIENT-05', 'EXAACT Demo Renewable Projects Limited', 'Renewable', 'Solar & Wind');
    $C6 = $mkClient('DEMO-S03-CLIENT-06', 'EXAACT Demo Industrial Services', 'Small Business', 'Industrial Services');
    $mkBranch($C1, 'DEMO-S03-BRANCH-01', 'EPC Projects — Jamnagar Branch');
    $mkBranch($C1, 'DEMO-S03-BRANCH-02', 'EPC Projects — Chennai Branch');
    foreach ([[$C1, 'Head Office', 'Mumbai'], [$C1, 'Project Office', 'Jamnagar'], [$C1, 'Project Office', 'Hazira'], [$C1, 'Project Office', 'Chennai'], [$C1, 'Project Office', 'Pune'], [$C2, 'Gujarat Refinery Site', 'Vadodara'], [$C2, 'Maharashtra Terminal', 'Mumbai'], [$C2, 'Panipat Project', 'Panipat'], [$C3, 'Fabrication Works', 'Hazira'], [$C4, 'Substation Project Office', 'Vadodara'], [$C5, 'Solar Site Office', 'Pune'], [$C6, 'Head Office', 'Rajkot']] as [$pid, $label, $city]) $mkLoc($pid, $label, $city);

    // ---- 30 NAMED contacts across ≥20 departments -------------------------
    $names = ['Rajesh Sharma','Priya Menon','Amit Verma','Neha Gupta','Vikram Nair','Sunita Rao','Arjun Patel','Kavita Iyer','Rohit Desai','Deepa Joshi','Sanjay Kulkarni','Meera Shah','Anil Kapoor','Pooja Reddy','Manoj Tiwari','Ritu Singh','Suresh Pillai','Anita George','Karan Malhotra','Divya Krishnan','Naveen Rao','Shalini Bhat','Prakash Jain','Lakshmi Nair','Gaurav Mehta','Farah Khan','Vinod Kumar','Sneha Patil','Rahul Bose','Isha Agarwal'];
    $desigs = ['Project Director','Technical Manager','QA Manager','QC Manager','Engineering Manager','Inspection Coordinator','Project Manager','Site Manager','Construction Manager','Commercial Manager','Procurement Manager','Accounts Manager','Site Coordinator','Operations Director','Managing Director'];
    $depts = ['Projects','Quality Assurance','Quality Control','Procurement','Engineering','Construction','Inspection','Finance','Commercial','Vendor Management','Operations','HSE','Planning','Contracts','Electrical','Mechanical','Civil','Instrumentation','Commissioning','Site Coordination'];
    $ci = 0; $spread = [$C1 => 10, $C2 => 6, $C3 => 5, $C4 => 5, $C5 => 3, $C6 => 1];
    foreach ($spread as $pid => $count) for ($k = 0; $k < $count; $k++) { $mkContact($pid, $names[$ci % count($names)], $desigs[$ci % count($desigs)], $depts[$ci % count($depts)], 'contact' . ($ci + 1) . '.s03@demo.test', $k === 0 ? 1 : 0); $ci++; }

    // ---- 20 portal users: 5 role types + lifecycle ------------------------
    $permsTech = 'calls,reports,reports.decide,request,complaint,market.post';
    $permsProj = 'calls,deputation,deputation.approve,request,market.post';
    $permsComm = 'invoices,complaint,market.vouchers';
    $permsSite = 'deputation';
    // C1 EPC — the full showcase (admin, technical, project, commercial, site, invited)
    $mkUser($C1, 'epc.admin.s03@demo.test', 'EPC Client Administrator', 'FULL', '', 'active');
    $mkUser($C1, 'epc.tech.s03@demo.test', 'EPC Technical Manager', 'TECHNICAL', $permsTech, 'active');
    $mkUser($C1, 'epc.project.s03@demo.test', 'EPC Project Manager', 'PROJECT', $permsProj, 'active');
    $mkUser($C1, 'epc.commercial.s03@demo.test', 'EPC Commercial Manager', 'COMMERCIAL', $permsComm, 'active');
    $mkUser($C1, 'epc.site.s03@demo.test', 'EPC Site User', 'READONLY', $permsSite, 'active');
    $mkUser($C1, 'epc.invited.s03@demo.test', 'EPC Invited User', 'TECHNICAL', $permsTech, 'invited');
    $mkUser($C2, 'energy.admin.s03@demo.test', 'Energy Client Administrator', 'FULL', '', 'active');
    $mkUser($C2, 'energy.tech.s03@demo.test', 'Energy Technical Manager', 'TECHNICAL', $permsTech, 'active');
    $mkUser($C2, 'energy.commercial.s03@demo.test', 'Energy Commercial Manager', 'COMMERCIAL', $permsComm, 'active');
    $mkUser($C2, 'energy.suspended.s03@demo.test', 'Energy Ex-User', 'READONLY', $permsSite, 'suspended');
    $mkUser($C3, 'heavy.admin.s03@demo.test', 'Heavy Eng Administrator', 'FULL', '', 'active');
    $mkUser($C3, 'heavy.tech.s03@demo.test', 'Heavy Eng Technical Manager', 'TECHNICAL', $permsTech, 'active');
    $mkUser($C3, 'heavy.site.s03@demo.test', 'Heavy Eng Site User', 'READONLY', $permsSite, 'active');
    $mkUser($C4, 'power.admin.s03@demo.test', 'Power Administrator', 'FULL', '', 'active');
    $mkUser($C4, 'power.tech.s03@demo.test', 'Power Technical Manager', 'TECHNICAL', $permsTech, 'active');
    $mkUser($C4, 'power.project.s03@demo.test', 'Power Project Manager', 'PROJECT', $permsProj, 'active');
    $mkUser($C4, 'power.invited.s03@demo.test', 'Power Invited User', 'TECHNICAL', $permsTech, 'invited');
    $mkUser($C5, 'renew.admin.s03@demo.test', 'Renewable Administrator', 'FULL', '', 'active');
    $mkUser($C5, 'renew.tech.s03@demo.test', 'Renewable Technical Manager', 'TECHNICAL', $permsTech, 'active');
    $mkUser($C6, 'small.admin.s03@demo.test', 'Industrial Services Admin', 'FULL', '', 'active');
    if (function_exists('setting_set')) setting_set('portal_enabled', '1');

    // ---- foundation requirements (spread across clients) ------------------
    $mkReq($C4, 'EXAACT Demo Power Transmission Solutions Limited', 'Transmission Line & Substation Testing Technician', 'ELEC', 'Vadodara', 3, 'OPEN', 'Transmission / substation / electrical testing / HV / EHV. Min 5y. Local + 200 km. Freelance, 30 days.');
    $mkReq($C3, 'EXAACT Demo Heavy Engineering Limited', 'Welding Inspector (CSWIP) — Pressure Vessels', 'WELD', 'Hazira', 2, 'OPEN', 'CSWIP welding inspection, pressure-vessel experience.');
    $mkReq($C2, 'EXAACT Demo Energy & Refinery Limited', 'NDT UT Level II — Oil & Gas', 'NDT', 'Vadodara', 2, 'OPEN', 'NDT UT Level II, oil & gas. 45 days, 12-hour shift.');
    $mkReq($C5, 'EXAACT Demo Renewable Projects Limited', 'Electrical Commissioning Engineer', 'ELEC', 'Multiple sites', 2, 'OPEN', 'Electrical commissioning, Pan-India travel.');
    $mkReq($C4, 'EXAACT Demo Power Transmission Solutions Limited', 'Protection Engineer (draft)', 'ELEC', 'Vadodara', 1, 'DRAFT', 'Draft.');
    $mkReq($C2, 'EXAACT Demo Energy & Refinery Limited', 'Turnaround Inspector (completed)', 'MECH', 'Vadodara', 3, 'CLOSED', 'Completed shutdown.');
    $mkReq($C3, 'EXAACT Demo Heavy Engineering Limited', 'Dimensional Inspector (cancelled)', 'MECH', 'Hazira', 1, 'CANCELLED', 'Cancelled by client.');

    // ======================================================================
    //  SHOWCASE — EPC (C1): role dashboards hit the exact target values.
    // ======================================================================
    // --- Technical: Open 4 · Matching 2 · Shortlisted 2 · Requests 3 · Reviews 2 · Expiring 4
    $rA = $mkReq($C1, 'EPC', 'QA/QC Mechanical Engineer', 'MECH', 'Jamnagar', 4, 'OPEN', 'QA/QC mechanical, 6 months.');
    $rB = $mkReq($C1, 'EPC', 'Piping Inspector', 'MECH', 'Hazira', 2, 'OPEN', 'Piping inspection.');
    $rC = $mkReq($C1, 'EPC', 'Electrical Supervisor', 'ELEC', 'Mumbai', 1, 'OPEN', 'Electrical supervision.');
    $rD = $mkReq($C1, 'EPC', 'Coating Inspector (NACE)', 'MECH', 'Pune', 1, 'OPEN', 'Coating inspection.');
    $mkReq($C1, 'EPC', 'Structural Draftsman (draft)', 'CIVIL', 'Mumbai', 1, 'DRAFT', 'Draft.');
    $mkReq($C1, 'EPC', 'Refinery Shutdown Inspector (completed)', 'MECH', 'Jamnagar', 2, 'CLOSED', 'Completed.');
    // 5 applicant professionals; 4 carry an expiring credential
    connect_pro_migrate();
    $apps = [];
    for ($i = 1; $i <= 5; $i++) {
        db()->prepare("INSERT INTO cx_professionals (email,name,mobile,headline,skills,availability,is_active,verification_tier,passport_token,password_hash,created_at) VALUES (?,?,?,?,?, 'AVAILABLE',1,'registered',?,?,?)")
            ->execute(['applicant' . $i . '.s03pro@demo.test', 'Applicant ' . $i . ' (S03)', '98' . rand(10000000, 99999999), 'QA/QC / mechanical inspector', 'QA/QC, mechanical inspection, piping, welding', substr(md5('app' . $i), 0, 20), s01_pw(), $now]);
        $apps[$i] = (int)db()->lastInsertId();
        // Marketplace privacy-first: a client browsing the pool sees a masked name
        // and no contact until the professional approves or is engaged.
        if (function_exists('connect_privacy_save')) connect_privacy_save($apps[$i], ['identity' => 'first_initial', 'contact' => 'on_request', 'rate' => 'band', 'listed' => 1]);
        if ($i <= 4 && function_exists('connect_cred_cert_save')) connect_cred_cert_save($apps[$i], ['name' => 'ASNT NDT Level II', 'authority' => 'ASNT', 'cert_number' => 'DEMO-S03-CERT-' . $i, 'discipline' => 'NDT', 'issue_date' => s01_d(-300), 'expiry_date' => s01_d(45)]);   // expiring ≤60d
    }
    // applications: rA gets 2 APPLIED + 1 SHORTLISTED ; rB gets 1 APPLIED + 1 SHORTLISTED  → APPLIED=3, SHORTLISTED=2, reqs-with-apps=2
    $mkApp = function ($rid, $proId, $status) {
        $aid = (int)cx_application_add($rid, ['applicant_professional_id' => $proId, 'applicant_name' => 'Applicant', 'proposed_rate' => 9000]);
        if ($aid && $status !== 'APPLIED') db()->prepare("UPDATE cx_applications SET status=? WHERE id=?")->execute([$status, $aid]);
        return $aid;
    };
    $mkApp($rA, $apps[1], 'APPLIED'); $mkApp($rA, $apps[2], 'APPLIED'); $mkApp($rA, $apps[3], 'SHORTLISTED');
    $mkApp($rB, $apps[4], 'APPLIED'); $mkApp($rB, $apps[5], 'SHORTLISTED');
    // 2 issued reports for C1 awaiting the client's decision (Pending Technical Reviews = 2)
    $rt = ops_one("SELECT id, code FROM report_types WHERE active=1 ORDER BY id LIMIT 1");
    for ($i = 1; $i <= 2; $i++) {
        db()->prepare("INSERT INTO report_docs (irn,report_type_id,type_code,title,client_id,office_id,sbu,result,release_status,status,finalized,finalized_at,issue_date,client_decision,created_by,created_at) VALUES (?,?,?,?,?,?, 'IND','ACCEPTED','RELEASED','ISSUED',1,?,?, '', 'demo-seed', ?)")
            ->execute(['DEMO-S03-RPT-' . $i, (int)($rt['id'] ?? 0), (string)($rt['code'] ?? 'DIR'), 'EPC Inspection Report ' . $i, $C1, $office(), s01_d(-3), s01_d(-3), $now]);
    }

    // --- Project: Active 3 · Deployed 18 · Upcoming 6 · Mob 4 · Conflicts 2 · Site 3
    db()->prepare("INSERT INTO calls (call_code,client_id,inspection_type,inspection_required_date,status,created_by,created_at) VALUES ('DEMO-S03-CALL-01',?, 'Inspection', ?, 'OPEN','demo-seed', ?)")->execute([$C1, s01_d(5), $now]);
    $call = (int)db()->lastInsertId();
    // 5 inspectors for the deployment visits
    $insp = [];
    for ($i = 1; $i <= 5; $i++) { db()->prepare("INSERT INTO inspectors (name,emp_code,sbu,skills,status,created_at) VALUES (?,?, 'IND','mechanical inspection','ACTIVE', ?)")->execute(['S03 Inspector ' . $i, 'DEMO-S03-INS-' . $i, $now]); $insp[$i] = (int)db()->lastInsertId(); }
    // 3 ACTIVE jobs + 18 ACTIVE visits (6 each)
    for ($j = 1; $j <= 3; $j++) {
        db()->prepare("INSERT INTO jobs (job_code,call_id,inspector_id,job_type,dep_status,dep_site,stage,inspection_start_date,inspection_end_date,sbu,created_at) VALUES (?,?,?, 'DEPUTATION','ACTIVE','Jamnagar','ALLOCATED', ?, ?, 'IND', ?)")->execute(['DEMO-S03-JOB-A' . $j, $call, $insp[$j], s01_d(-5), s01_d(25), $now]);
        $jid = (int)db()->lastInsertId();
        for ($d = 0; $d < 6; $d++) db()->prepare("INSERT INTO job_visits (job_id,visit_date,inspector_id,status,note) VALUES (?,?,?, 'ACTIVE', 'On-site inspection')")->execute([$jid, s01_d(-3 + $d), $insp[(($j + $d) % 5) + 1]]);
    }
    // 4 MOB_PENDING jobs, carrying 6 upcoming (future PLANNED) visits between them
    $mobJobs = [];
    for ($j = 1; $j <= 4; $j++) { db()->prepare("INSERT INTO jobs (job_code,call_id,inspector_id,job_type,dep_status,dep_site,stage,inspection_start_date,inspection_end_date,sbu,created_at) VALUES (?,?,?, 'DEPUTATION','MOB_PENDING','Hazira','ALLOCATED', ?, ?, 'IND', ?)")->execute(['DEMO-S03-JOB-M' . $j, $call, $insp[($j % 5) + 1], s01_d(12), s01_d(40), $now]); $mobJobs[$j] = (int)db()->lastInsertId(); }
    for ($u = 0; $u < 6; $u++) { $jid = $mobJobs[($u % 4) + 1]; db()->prepare("INSERT INTO job_visits (job_id,visit_date,inspector_id,status,note) VALUES (?,?,?, 'PLANNED', 'Upcoming deployment')")->execute([$jid, s01_d(12 + $u), $insp[($u % 5) + 1]]); }
    // 2 schedule-conflict visits (flagged CONFLICT) on an active job
    $confJob = (int)ops_val("SELECT id FROM jobs WHERE job_code='DEMO-S03-JOB-A1'");
    for ($u = 0; $u < 2; $u++) db()->prepare("INSERT INTO job_visits (job_id,visit_date,inspector_id,status,note) VALUES (?,?,?, 'CONFLICT', 'Double-booked — conflict')")->execute([$confJob, s01_d(1 + $u), $insp[1]]);
    // dep_att_approval: 3 CLIENT_REVIEW (site actions) + 4 SUBMITTED (client approval)
    $mobJ1 = $mobJobs[1];
    for ($i = 0; $i < 3; $i++) { try { db()->prepare("INSERT INTO dep_att_approval (job_id,client_id,period_from,period_to,basis,billable_days,status,source,created_by,created_at) VALUES (?,?,?,?, 'MANDAYS', 5, 'CLIENT_REVIEW','demo-seed','demo-seed', ?)")->execute([$confJob, $C1, s01_d(-7), s01_d(-1), $now]); } catch (Throwable $e) {} }
    for ($i = 0; $i < 4; $i++) { try { db()->prepare("INSERT INTO dep_att_approval (job_id,client_id,period_from,period_to,basis,billable_days,status,source,created_by,created_at) VALUES (?,?,?,?, 'MANDAYS', 6, 'SUBMITTED','demo-seed','demo-seed', ?)")->execute([$mobJ1, $C1, s01_d(-14), s01_d(-8), $now]); } catch (Throwable $e) {} }

    // --- Commercial: Review 3 (PENDING) · Draft 2 (BOOKED eng) · Approval 4 (dep SUBMITTED) · Invoices 3 (APPROVED) · Approved ₹12,50,000 (BILLED)
    $beSeq = 0;
    $be = function ($status, $amount) use ($C1, $office, $now, &$beSeq) { $beSeq++; try { db()->prepare("INSERT INTO billable_events (source_module,source_kind,source_id,party_id,office_id,sbu,service_type,qty,unit,rate,amount,status,created_at) VALUES ('demo','DEMO_S03',?,?,?, 'IND','Inspection', 1,'LOT', ?, ?, ?, ?)")->execute([$beSeq, $C1, $office(), $amount, $amount, $status, $now]); } catch (Throwable $e) {} };
    for ($i = 0; $i < 3; $i++) $be('PENDING', 45000);
    for ($i = 0; $i < 3; $i++) $be('APPROVED', 60000);
    $be('BILLED', 750000); $be('BILLED', 500000);   // sum = 12,50,000
    // 2 draft (BOOKED) engagements for C1
    if (function_exists('connect_engage_migrate')) connect_engage_migrate();
    for ($i = 1; $i <= 2; $i++) { try { db()->prepare("INSERT INTO cx_engagements (requirement_id,subject_kind,subject_id,subject_name,poster_party_id,poster_name,basis,rate,rate_unit,quantity,status,created_at) VALUES (0,'professional',0,?,?, 'EPC','MANDAYS',9000,'day',30,'BOOKED', ?)")->execute(['Draft engagement ' . $i, $C1, $now]); } catch (Throwable $e) {} }

    // ---- activity timelines (reuse the activity spine) --------------------
    foreach ([[$C1, 'EPC'], [$C2, 'Energy'], [$C4, 'Power']] as [$pid, $tag]) {
        $act($pid, 'REQ_CREATED', 'Requirement created', 12);
        $act($pid, 'SEARCH', 'Professionals searched', 10);
        $act($pid, 'SHORTLIST', 'Professional shortlisted', 8);
        $act($pid, 'INVITE', 'Invitation sent', 7);
        $act($pid, 'REVIEW', 'Technical review completed', 5);
        $act($pid, 'ENGAGE', 'Engagement created', 4);
        $act($pid, 'REPORT', 'Report submitted', 2);
    }
    $say('6 clients + 2 branches + 12 locations + 30 named contacts + 20 portal users + activity timelines');
    $say('EPC showcase: role dashboards + 5 applicants + jobs/visits + commercial records');

    // ---- DASHBOARD (real, derived — asserts the exact tile values) --------
    $has = function ($sql, $args = []) { try { return (int)ops_val($sql, $args) > 0; } catch (Throwable $e) { return false; } };
    $cnt = function ($sql, $args = []) { try { return (int)ops_val($sql, $args); } catch (Throwable $e) { return 0; } };
    $reqBase = "FROM cx_requirements WHERE poster_party_id IN (SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%')";
    $tech = connect_client_dash($C1, 'technical'); $proj = connect_client_dash($C1, 'project'); $comm = connect_client_dash($C1, 'commercial');
    $tv = array_column($tech, 'value'); $pv = array_column($proj, 'value'); $cv = array_column($comm, 'value');
    $dash = [
        ['6 client organisations (one master each)', $cnt("SELECT COUNT(*) FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%'") === 6],
        ['Each client also a marketplace org', $cnt("SELECT COUNT(*) FROM cx_organisations WHERE party_id IN (SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%')") >= 6],
        ['Branch hierarchy (parent_id)', $has("SELECT 1 FROM business_partners WHERE code LIKE 'DEMO-S03-BRANCH-%' AND parent_id=?", [$C1])],
        ['>= 12 locations · >= 20 departments · >= 30 named contacts', ($cnt("SELECT COUNT(*) FROM partner_addresses WHERE partner_id IN (SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%')") >= 12 && $cnt("SELECT COUNT(DISTINCT department) FROM partner_contacts WHERE partner_id IN (SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%') AND department<>''") >= 20 && $cnt("SELECT COUNT(*) FROM partner_contacts WHERE partner_id IN (SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%') AND name NOT LIKE 'Contact %'") >= 30)],
        ['>= 20 portal users · 5 role types', ($cnt("SELECT COUNT(*) FROM client_users WHERE email LIKE '%s03@demo.test'") >= 20 && $has("SELECT 1 FROM client_users WHERE role_preset='TECHNICAL' AND email LIKE '%s03@demo.test'") && $has("SELECT 1 FROM client_users WHERE role_preset='PROJECT'") && $has("SELECT 1 FROM client_users WHERE role_preset='COMMERCIAL' AND email LIKE '%s03@demo.test'") && $has("SELECT 1 FROM client_users WHERE role_preset='READONLY' AND email LIKE '%s03@demo.test'"))],
        ['User lifecycle: invited / active / suspended', ($has("SELECT 1 FROM client_users WHERE must_change=1 AND invite_token<>'' AND email LIKE '%s03@demo.test'") && $has("SELECT 1 FROM client_users WHERE is_active=1 AND email LIKE '%s03@demo.test'") && $has("SELECT 1 FROM client_users WHERE is_active=0 AND email LIKE '%s03@demo.test'"))],
        ['Requirement spread 5 active/2 draft/2 completed/1 cancelled', ($cnt("SELECT COUNT(*) $reqBase AND status='OPEN'") >= 5 && $cnt("SELECT COUNT(*) $reqBase AND status='DRAFT'") >= 2 && $cnt("SELECT COUNT(*) $reqBase AND status='CLOSED'") >= 2 && $cnt("SELECT COUNT(*) $reqBase AND status='CANCELLED'") >= 1)],
        ['Multi-client isolation (scoped by party)', ($has("SELECT 1 FROM cx_requirements WHERE poster_party_id=? AND title LIKE 'Transmission%'", [$C4]) && !$has("SELECT 1 FROM cx_requirements WHERE poster_party_id=? AND title LIKE 'Transmission%'", [$C3]))],
        ['Activity timeline present', $has("SELECT 1 FROM activities WHERE partner_id=? AND kind LIKE 'S03_%'", [$C1])],
        // ---- role dashboards render the EXACT target values ----
        ['Technical dashboard = 4/2/2/3/2/4', ($tv === [4, 2, 2, 3, 2, 4])],
        ['Project dashboard = 3/18/6/4/2/3', ($pv === [3, 18, 6, 4, 2, 3])],
        ['Commercial = 3/2/4/3 + ₹12,50,000 approved', (array_slice($cv, 0, 4) === [3, 2, 4, 3] && $cnt("SELECT COALESCE(SUM(amount),0) FROM billable_events WHERE party_id=? AND status='BILLED'", [$C1]) === 1250000)],
    ];
    $allpass = true; foreach ($dash as [$l, $ok]) if (!$ok) $allpass = false;
    if (function_exists('setting_set')) setting_set('demo_s03_seed', date('c'));
    return ['log' => $log, 'dashboard' => $dash, 'allpass' => $allpass, 'tech' => $tv, 'project' => $pv, 'commercial' => $cv, 'ids' => compact('C1', 'C2', 'C3', 'C4', 'C5', 'C6')];
}
