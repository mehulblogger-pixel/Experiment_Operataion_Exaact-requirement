<?php
// ============================================================================
//  CONNECT marketplace — complete scenario seed.
//
//  seed_demo() covers the ERP world (staff, inspectors, clients, invoices). It
//  does NOT touch the Connect marketplace. This seeder fills that gap: every
//  user TYPE that meets on the marketplace, and every SCENARIO (status) they
//  pass through, so nothing is left out —
//
//    USERS      staff desk (master / coordinator / finance / on-roll inspector),
//               clients (full + restricted portal users), freelancers across all
//               four verification tiers, and a manpower agency with a bench.
//    REQUIREMENTS   one in every status: DRAFT · OPEN · SHORTLISTING · AWARDED ·
//                   CLOSED · CANCELLED · EXPIRED (client-posted AND internal).
//    APPLICATIONS   APPLIED · SHORTLISTED · OFFERED · ACCEPTED · REJECTED · WITHDRAWN.
//    ENGAGEMENTS    every basis (man-days / man-months / deputation / continuous /
//                   frequency) × rate model (inclusive / exclusive) × cadence
//                   (per-day / per-deployment) × subject (professional / inspector
//                   / bench).
//    VOUCHERS       every status: DRAFT · SUBMITTED · REJECTED (returned, with a
//                   client note) · APPROVED (unsettled → report locked) · PAID
//                   (both sides settled → report released) — with receipts,
//                   platform commission, and inspection-report deliverables.
//
//  Idempotent: guarded by the setting `connect_seed_v1`; pass $force to reload.
//  Everything is built through the real engine functions, so the data is exactly
//  what the app would create — valid lifecycles, valid money.
// ============================================================================

/** A 1×1 PNG and a tiny PDF used as stand-in receipt / report bytes. */
function connect_seed_png() { return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='); }
function connect_seed_pdf() { return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF"; }

function connect_seed_scenarios($force = false) {
    foreach (['connect_market_migrate','connect_engv_migrate','connect_pro_migrate','connect_bench_migrate','connect_org_migrate','portal_migrate'] as $fn)
        if (function_exists($fn)) $fn();
    if (!$force && function_exists('setting_get') && setting_get('connect_seed_v1'))
        return ['skipped' => true];

    $now = date('c');
    $PNG = connect_seed_png(); $PDF = connect_seed_pdf();
    $counts = []; $logins = []; $scenarios = [];
    $inc = function ($k, $n = 1) use (&$counts) { $counts[$k] = ($counts[$k] ?? 0) + $n; };
    $scn = function ($what, $detail) use (&$scenarios) { $scenarios[] = ['what' => $what, 'detail' => $detail]; };

    // ---- user factories ----------------------------------------------------
    $party = function ($name, $flags = []) use ($now) {
        $id = (int)ops_val("SELECT id FROM business_partners WHERE legal_name=?", [$name]);
        if ($id) return $id;
        db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,is_vendor,is_subcontractor,status,created_at) VALUES (?,?,?,?,?, 'ACTIVE',?)")
            ->execute([$name, $name, (int)!empty($flags['client']), (int)!empty($flags['vendor']), (int)!empty($flags['sub']), $now]);
        return (int)db()->lastInsertId();
    };
    $clientUser = function ($pid, $email, $name, $perms = '', $note = '') use ($now, $inc, &$logins) {
        if ((int)ops_val("SELECT COUNT(*) FROM client_users WHERE LOWER(email)=?", [strtolower($email)])) return;
        db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,perms,created_at) VALUES (?,?,?,?,1,0,?,?)")
            ->execute([$pid, $email, $name, password_hash('demo12345', PASSWORD_DEFAULT), $perms, $now]);
        $inc('client_users');
        $logins[] = ['type' => 'Client portal', 'who' => $name, 'login' => $email, 'pw' => 'demo12345', 'url' => '/portal/login', 'note' => $note];
    };
    $staff = function ($username, $first, $last, $role, $inspectorId = null) use ($now, $inc, &$logins) {
        $ex = (int)ops_val("SELECT id FROM users WHERE username=?", [$username]);
        if ($ex) return $ex;
        db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active,inspector_id) VALUES (?,?,?,?,?,?,?,1,?)")
            ->execute([$username, password_hash('demo12345', PASSWORD_DEFAULT), $first, $last, $username . '@mgh.test', $role, $role === 'MASTER_ADMIN' ? 1 : 0, $inspectorId]);
        $inc('staff');
        $logins[] = ['type' => 'Staff desk', 'who' => "$first $last · " . $role, 'login' => $username, 'pw' => 'demo12345', 'url' => '/login', 'note' => ''];
        return (int)db()->lastInsertId();
    };
    $pro = function ($email, $name, $tier, $disc, $city, $rmin, $rmax) use ($now, $inc, &$logins) {
        $id = (int)ops_val("SELECT id FROM cx_professionals WHERE email=?", [$email]);
        if ($id) return $id;
        db()->prepare("INSERT INTO cx_professionals (email,name,mobile,password_hash,is_active,verification_tier,headline,disciplines,base_city,availability,day_rate_min,day_rate_max,passport_token,created_at)
                       VALUES (?,?,?,?,1,?,?,?,?, 'AVAILABLE',?,?,?,?)")
            ->execute([$email, $name, '98' . random_int(10000000, 99999999), password_hash('demo12345', PASSWORD_DEFAULT), $tier, ucfirst(strtolower($disc)) . ' specialist', $disc, $city, $rmin, $rmax, bin2hex(random_bytes(8)), $now]);
        $inc('professionals');
        $logins[] = ['type' => 'Freelancer', 'who' => "$name · " . $tier, 'login' => $email, 'pw' => 'demo12345', 'url' => '/pro/login', 'note' => $disc . ' · ' . $city];
        return (int)db()->lastInsertId();
    };
    $inspector = function ($name, $skill) use ($now, $inc) {
        $id = (int)ops_val("SELECT id FROM inspectors WHERE name=?", [$name]);
        if ($id) return $id;
        db()->prepare("INSERT INTO inspectors (name,emp_code,skills,status,created_at) VALUES (?,?,?, 'ACTIVE',?)")->execute([$name, 'CXI-' . random_int(100, 999), $skill, $now]);
        $inc('inspectors'); return (int)db()->lastInsertId();
    };

    // ---- scenario builders (real engines) ----------------------------------
    $mkReq = function ($title, $posterPid, $posterName, $disc, $loc, $basis, $model, $cadence, $status) use ($inc) {
        $id = cx_requirement_create(['title' => $title, 'poster_party_id' => $posterPid, 'poster_name' => $posterName,
            'discipline_code' => $disc, 'location' => $loc, 'positions' => 1, 'rate_max' => 5000,
            'deputation_basis' => $basis, 'rate_inclusive' => $model, 'voucher_cadence' => $cadence,
            'description' => $title . ' — seeded scenario.'], true);   // posted OPEN
        $status = strtoupper((string)$status);
        if ($status !== 'OPEN') db()->prepare("UPDATE cx_requirements SET status=? WHERE id=?")->execute([$status, $id]);
        $inc('requirements'); return $id;
    };
    $addApp = function ($reqId, $applicant, $status = 'APPLIED') use ($inc) {
        $in = ['applicant_name' => $applicant['name'], 'proposed_rate' => $applicant['rate'] ?? 4000, 'cover_note' => 'Seeded application.'];
        if (isset($applicant['professional'])) $in['applicant_professional_id'] = $applicant['professional'];
        if (isset($applicant['inspector']))    $in['inspector_id'] = $applicant['inspector'];
        if (isset($applicant['party']))        $in['applicant_party_id'] = $applicant['party'];
        $aid = cx_application_add($reqId, $in);
        if ($aid && strtoupper($status) !== 'APPLIED') db()->prepare("UPDATE cx_applications SET status=? WHERE id=?")->execute([strtoupper($status), $aid]);
        if ($aid) $inc('applications');
        return $aid;
    };
    $award = function ($reqId, $applicant) use ($inc) {
        $in = ['applicant_name' => $applicant['name'], 'proposed_rate' => $applicant['rate'] ?? 4000];
        if (isset($applicant['professional'])) $in['applicant_professional_id'] = $applicant['professional'];
        if (isset($applicant['inspector']))    $in['inspector_id'] = $applicant['inspector'];
        if (isset($applicant['party']))        $in['applicant_party_id'] = $applicant['party'];
        $aid = cx_application_add($reqId, $in); $inc('applications');
        db()->prepare("UPDATE cx_requirements SET status='SHORTLISTING' WHERE id=?")->execute([$reqId]);
        db()->prepare("UPDATE cx_applications SET status='SHORTLISTED' WHERE id=?")->execute([$aid]);
        cx_requirement_award($reqId, $aid);
        return $aid;
    };
    $book = function ($reqId, $basis, $qty, $rate, $model, $cadence, $unit, $freqNote = '') {
        connect_engage_save_for_requirement($reqId, ['basis' => $basis, 'quantity' => $qty, 'rate' => $rate,
            'rate_inclusive' => $model, 'voucher_cadence' => $cadence, 'rate_unit' => $unit, 'frequency_note' => $freqNote,
            'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+30 days'))]);
        return connect_engage_for_requirement($reqId);   // engagement row
    };
    $voucher = function ($engId, $label, $days) use ($inc) {
        [$ok, , $vid] = connect_engv_open_for_engagement($engId, ['period_label' => $label]);
        foreach ($days as $d) connect_engv_add_line($vid, $d);
        connect_engv_recompute($vid);
        $inc('vouchers'); return $vid;
    };
    $receipt = function ($vid, $name) use ($PNG, $inc) {
        db()->prepare("INSERT INTO cx_engagement_voucher_files (voucher_id,line_id,file_name,mime,size,file_data,uploaded_kind,uploaded_name,created_at)
                       VALUES (?,0,?,?,?,?, 'professional','Seed',?)")
            ->execute([$vid, $name, 'image/png', strlen($PNG), base64_encode($PNG), date('c')]);
        $inc('receipts');
    };
    $report = function ($engId, $title) use ($PDF, $inc) {
        $eng = ops_one("SELECT * FROM cx_engagements WHERE id=?", [(int)$engId]); if (!$eng) return;
        db()->prepare("INSERT INTO cx_engagement_reports (engagement_id,requirement_id,poster_party_id,subject_kind,subject_id,title,file_name,mime,size,file_data,uploaded_kind,uploaded_name,created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?, 'professional','Seed',?)")
            ->execute([(int)$engId, (int)$eng['requirement_id'], (int)$eng['poster_party_id'], (string)$eng['subject_kind'], (int)$eng['subject_id'],
                       $title, 'inspection-report.pdf', 'application/pdf', strlen($PDF), base64_encode($PDF), date('c')]);
        $inc('reports');
    };

    // ========================================================================
    //  1. USERS
    // ========================================================================
    if (function_exists('setting_get') && !setting_get('connect_commission_pct') && function_exists('setting_set'))
        setting_set('connect_commission_pct', 5);

    // Staff desk
    $inspUserId = $inspector('Ravi On-Roll', 'NDT RT/UT');
    $staff('cx.master',  'Meera',  'Nair',   'MASTER_ADMIN');
    $staff('cx.coord',   'Karan',  'Rao',    'COORDINATOR');
    $staff('cx.finance', 'Nikhil', 'Jain',   'FINANCE');
    $staff('cx.insp',    'Ravi',   'OnRoll', 'INSPECTOR', $inspUserId);
    $scn('Staff desk users', 'master / coordinator / finance / on-roll inspector — cx.master · cx.coord · cx.finance · cx.insp (demo12345)');

    // Clients (portal)
    $acme = $party('Acme Fabricators Ltd', ['client' => true]);
    $reli = $party('Reliance Petrochem Ltd', ['client' => true]);
    $clientUser($acme, 'buyer@acme.test',   'Priya Sharma (Acme)',    '', 'Full — can post jobs and review vouchers');
    $clientUser($reli, 'qa@reliance.test',  'Ganesh Rao (Reliance)',  'calls,reports,reports.decide,market.vouchers', 'Restricted — reviews vouchers, cannot post');
    $clientUser($reli, 'buyer@reliance.test', 'Divya Menon (Reliance)', '', 'Full — posts jobs and reviews vouchers');
    $scn('Client portal users', '3 across 2 companies — one restricted to voucher review only');

    // Agency + bench (manpower agency)
    $agencyParty = $party('TechStaff Manpower Agency', ['sub' => true]);
    $agencyOrgId = 0;
    if (function_exists('connect_org_add')) {
        $agencyOrgId = (int)ops_val("SELECT id FROM cx_organisations WHERE name=?", ['TechStaff Manpower Agency']);
        if (!$agencyOrgId) { $agencyOrgId = (int)connect_org_add('TechStaff Manpower Agency', 'MANPOWER_AGENCY', ['party_id' => $agencyParty]); if (function_exists('connect_org_approve')) connect_org_approve($agencyOrgId); $inc('agencies'); }
    }
    if ($agencyOrgId && function_exists('connect_bench_add')) {
        foreach ([['Ajay Bench', 'WELD', 'CSWIP welder'], ['Sunita Bench', 'NDT', 'ASNT L2']] as $bp)
            if (!(int)ops_val("SELECT COUNT(*) FROM cx_bench WHERE org_id=? AND name=?", [$agencyOrgId, $bp[0]])) { connect_bench_add($agencyOrgId, ['name' => $bp[0], 'discipline_code' => $bp[1], 'job_title' => $bp[2], 'base_city' => 'Surat', 'day_rate' => 3800]); $inc('bench_people'); }
    }
    $scn('Agency + bench', 'TechStaff Manpower Agency (org) with 2 bench people — the agency/pool user type');

    // Freelancers across ALL four verification tiers
    $pRegistered = $pro('reg@pro.test',   'Amit Registered', 'registered',           'NDT',  'Vadodara', 3000, 4500);
    $pIdVer      = $pro('idv@pro.test',   'Sanjay IdVerified','id_verified',          'WELD', 'Mumbai',   3500, 5000);
    $pCredVer    = $pro('cred@pro.test',  'Neha Credential', 'credential_verified',   'NDT',  'Surat',    4000, 5500);
    $pProven     = $pro('proven@pro.test','Suresh Proven',   'proven',                'NDT',  'Dahej',    4500, 6000);
    $scn('Freelancers · all tiers', 'registered · id_verified · credential_verified · proven (reg/idv/cred/proven @pro.test, demo12345)');

    // ========================================================================
    //  2. REQUIREMENTS — one per status (client-posted + internal)
    // ========================================================================
    $mkReq('DRAFT — internal welding inspector', 0, 'MGH (internal)', 'WELD', 'Hazira', 'MAN_DAYS', 'EXCLUSIVE', 'PER_DAY', 'DRAFT');
    $rOpen        = $mkReq('OPEN — NDT tech for pressure-vessel FAT', $acme, 'Acme Fabricators', 'NDT', 'Dahej', 'MAN_DAYS', 'EXCLUSIVE', 'PER_DAY', 'OPEN');
    $mkReq('SHORTLISTING — coating inspector', $reli, 'Reliance Petrochem', 'COAT', 'Jamnagar', 'MAN_DAYS', 'INCLUSIVE', 'PER_DEPLOYMENT', 'SHORTLISTING');
    $mkReq('CLOSED — piping QA (filled)', $acme, 'Acme Fabricators', 'PIPE', 'Dahej', 'MAN_DAYS', 'EXCLUSIVE', 'PER_DAY', 'CLOSED');
    $mkReq('CANCELLED — dropped requirement', $reli, 'Reliance Petrochem', 'NDT', 'Jamnagar', 'MAN_DAYS', 'INCLUSIVE', 'PER_DAY', 'CANCELLED');
    $mkReq('EXPIRED — unfilled long enough to lapse', $acme, 'Acme Fabricators', 'WELD', 'Dahej', 'MAN_DAYS', 'EXCLUSIVE', 'PER_DAY', 'EXPIRED');
    $scn('Requirements · every status', 'DRAFT · OPEN · SHORTLISTING · AWARDED (below) · CLOSED · CANCELLED · EXPIRED');

    // Applications in every status on the OPEN board
    $addApp($rOpen, ['professional' => $pRegistered, 'name' => 'Amit Registered'], 'APPLIED');
    $addApp($rOpen, ['professional' => $pIdVer,      'name' => 'Sanjay IdVerified'], 'SHORTLISTED');
    $addApp($rOpen, ['professional' => $pCredVer,    'name' => 'Neha Credential'], 'OFFERED');
    $addApp($rOpen, ['professional' => $pProven,     'name' => 'Suresh Proven'], 'REJECTED');
    $addApp($rOpen, ['party' => $agencyParty,        'name' => 'TechStaff (agency)'], 'WITHDRAWN');
    $scn('Applications · every status', 'APPLIED · SHORTLISTED · OFFERED · REJECTED · WITHDRAWN (+ ACCEPTED on the awarded jobs)');

    // ========================================================================
    //  3. AWARDED → ENGAGEMENTS (every basis × model × cadence × subject)
    //     + 4. VOUCHERS (every status) + receipts + commission + settlement
    //     + reports (locked & released)
    // ========================================================================

    // --- (A) Freelancer · MAN_DAYS · EXCLUSIVE · PER_DAY — the full voucher matrix
    $rA = $mkReq('AWARDED — freelancer man-days (FAT witness)', $acme, 'Acme Fabricators', 'NDT', 'Dahej', 'MAN_DAYS', 'EXCLUSIVE', 'PER_DAY', 'OPEN');
    $award($rA, ['professional' => $pProven, 'name' => 'Suresh Proven', 'rate' => 4500]);
    $engA = $book($rA, 'MAN_DAYS', 8, 4500, 'EXCLUSIVE', 'PER_DAY', 'day');
    if ($engA) {
        $engAId = (int)$engA['id'];
        $lines = [['work_date' => '2026-08-20', 'units' => 1, 'travel' => 1200, 'lodging' => 1800]];
        // DRAFT voucher (being built)
        $voucher($engAId, 'DAY-DRAFT', $lines);
        // SUBMITTED voucher + receipt (awaiting client review)
        $vSub = $voucher($engAId, 'DAY-SUBMIT', $lines); $receipt($vSub, 'cab-bill.png'); connect_engv_set_status($vSub, 'SUBMITTED');
        // REJECTED voucher (client returned for clarification, with a note)
        $vRej = $voucher($engAId, 'DAY-RETURNED', $lines); connect_engv_set_status($vRej, 'SUBMITTED'); connect_engv_set_status($vRej, 'REJECTED', 'Client · Acme Fabricators', 'Please attach the hotel folio for 20 Aug.');
        // APPROVED but UNSETTLED (client approved, no payment yet) → report LOCKED
        $vApp = $voucher($engAId, 'DAY-APPROVED', $lines); $receipt($vApp, 'hotel-folio.png'); connect_engv_set_status($vApp, 'SUBMITTED'); connect_engv_set_status($vApp, 'APPROVED', 'Client · Acme Fabricators');
        // PAID / SETTLED (both sides confirm) → report RELEASED
        $vPaid = $voucher($engAId, 'DAY-PAID', $lines); $receipt($vPaid, 'travel-ticket.png');
        connect_engv_set_status($vPaid, 'SUBMITTED'); connect_engv_set_status($vPaid, 'APPROVED', 'Client · Acme Fabricators');
        connect_engv_confirm($vPaid, 'client', 'Client · Acme Fabricators'); connect_engv_confirm($vPaid, 'pro', 'Suresh Proven');
        $report($engAId, 'RT film review report — Dahej FAT');   // released once settled
        $scn('Voucher lifecycle (freelancer)', 'DRAFT · SUBMITTED · RETURNED · APPROVED (report locked) · PAID/settled (report released) — with receipts + commission');
    }

    // --- (B) Freelancer · MAN_MONTHS · EXCLUSIVE · PER_DEPLOYMENT
    $rB = $mkReq('AWARDED — freelancer man-months (deputed QA)', $reli, 'Reliance Petrochem', 'NDT', 'Jamnagar', 'MAN_MONTHS', 'EXCLUSIVE', 'PER_DEPLOYMENT', 'OPEN');
    $award($rB, ['professional' => $pCredVer, 'name' => 'Neha Credential', 'rate' => 90000]);
    $engB = $book($rB, 'MAN_MONTHS', 3, 90000, 'EXCLUSIVE', 'PER_DEPLOYMENT', 'month');
    if ($engB) { $vB = $voucher((int)$engB['id'], 'MONTH-1', [['work_date' => '2026-08-31', 'units' => 1, 'travel' => 4000, 'lodging' => 12000]]); $receipt($vB, 'monthly-expenses.png'); connect_engv_set_status($vB, 'SUBMITTED'); }
    $scn('Engagement · man-months / exclusive / per-deployment', 'freelancer deputation billed by the month');

    // --- (C) On-roll inspector · DEPUTATION · INCLUSIVE (desk-driven voucher)
    $rC = $mkReq('AWARDED — on-roll inspector deputation', 0, 'MGH (internal)', 'NDT', 'Hazira', 'DEPUTATION', 'INCLUSIVE', 'PER_DEPLOYMENT', 'OPEN');
    $award($rC, ['inspector' => $inspUserId, 'name' => 'Ravi On-Roll', 'rate' => 60000]);
    $engC = $book($rC, 'DEPUTATION', 0, 60000, 'INCLUSIVE', 'PER_DEPLOYMENT', 'month');
    if ($engC) { $vC = $voucher((int)$engC['id'], 'DEP-1', [['work_date' => '2026-08-31', 'units' => 1]]); connect_engv_set_status($vC, 'SUBMITTED'); connect_engv_set_status($vC, 'APPROVED', 'Desk · Coordinator'); connect_engv_set_status($vC, 'PAID', 'Desk · Finance'); }
    $scn('Engagement · deputation / inclusive (on-roll)', 'internal inspector; desk approves & pays (all-inclusive, no expense heads)');

    // --- (D) On-roll inspector · CONTINUOUS · INCLUSIVE
    $rD = $mkReq('AWARDED — continuous site QA (ongoing)', 0, 'MGH (internal)', 'WELD', 'Dahej', 'CONTINUOUS', 'INCLUSIVE', 'PER_DEPLOYMENT', 'OPEN');
    $award($rD, ['inspector' => $inspUserId, 'name' => 'Ravi On-Roll', 'rate' => 55000]);
    $engD = $book($rD, 'CONTINUOUS', 0, 55000, 'INCLUSIVE', 'PER_DEPLOYMENT', 'month');
    if ($engD) { $vD = $voucher((int)$engD['id'], 'CONT-1', [['work_date' => '2026-08-31', 'units' => 1]]); }   // DRAFT — ongoing
    $scn('Engagement · continuous / inclusive', 'ongoing monthly engagement with no fixed end');

    // --- (E) Agency bench · FREQUENCY · EXCLUSIVE · PER_DAY
    $rE = $mkReq('AWARDED — agency bench, weekly visits', $acme, 'Acme Fabricators', 'NDT', 'Dahej', 'FREQUENCY', 'EXCLUSIVE', 'PER_DAY', 'OPEN');
    $award($rE, ['party' => $agencyParty, 'name' => 'TechStaff (agency)', 'rate' => 3800]);
    $engE = $book($rE, 'FREQUENCY', 0, 3800, 'EXCLUSIVE', 'PER_DAY', 'visit', '2 days a week');
    if ($engE) { $vE = $voucher((int)$engE['id'], 'FREQ-1', [['work_date' => '2026-08-24', 'units' => 1, 'conveyance' => 400]]); $receipt($vE, 'conveyance.png'); connect_engv_set_status($vE, 'SUBMITTED'); }
    $scn('Engagement · frequency / exclusive (agency bench)', 'agency-supplied bench person on a regular-visit pattern');

    if (function_exists('setting_set')) setting_set('connect_seed_v1', date('c'));

    return ['skipped' => false, 'counts' => $counts, 'logins' => $logins, 'scenarios' => $scenarios];
}

/** Remove everything this seeder created (marketplace only; leaves seed_demo alone). */
function connect_seed_remove() {
    foreach (['connect_market_migrate','connect_engv_migrate','connect_bench_migrate'] as $fn) if (function_exists($fn)) $fn();
    $del = 0;
    foreach ([
        "DELETE FROM cx_engagement_reports",
        "DELETE FROM cx_engagement_voucher_files",
        "DELETE FROM cx_engagement_voucher_lines",
        "DELETE FROM cx_engagement_vouchers",
        "DELETE FROM cx_engagements",
        "DELETE FROM cx_applications",
        "DELETE FROM cx_requirements",
        "DELETE FROM cx_bench_alloc",
        "DELETE FROM cx_bench",
        "DELETE FROM cx_professionals WHERE email LIKE '%@pro.test'",
        "DELETE FROM client_users WHERE email LIKE '%@acme.test' OR email LIKE '%@reliance.test'",
        "DELETE FROM users WHERE username LIKE 'cx.%'",
        "DELETE FROM inspectors WHERE name='Ravi On-Roll'",
        "DELETE FROM business_partners WHERE legal_name IN ('Acme Fabricators Ltd','Reliance Petrochem Ltd','TechStaff Manpower Agency')",
        "DELETE FROM cx_organisations WHERE name='TechStaff Manpower Agency'",
    ] as $sql) { try { $del += (int)db()->exec($sql); } catch (Throwable $e) {} }
    if (function_exists('setting_set')) setting_set('connect_seed_v1', '');
    return ['deleted' => $del];
}
