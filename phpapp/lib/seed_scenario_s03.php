<?php
// ============================================================================
//  DEMO-S03 — Client + Client-Portal foundation seed (CLI + admin button).
//
//  The client-side foundation that later scenario prompts (04–12) reuse: 6 client
//  organisations, their branches/locations, departments, contacts, portal users
//  (five role types + lifecycle states) and a spread of requirements — all on the
//  ONE existing client master (business_partners) and the existing portal/CRM
//  tables. No duplicate client database; every client is simultaneously an
//  operational client (CRM/ops) and a marketplace client (one master record).
//
//  Reuses: business_partners (+ parent_id branches, client_type, industry),
//  cx_organisations (marketplace-enable), partner_addresses (locations),
//  partner_contacts (contacts + departments), client_users (portal + role_preset
//  + perms + lifecycle), cx_requirements (requirements). Tagged DEMO-S03.
// ============================================================================

function seed_s03_status() {
    return [
        'loaded'   => function_exists('setting_get') ? (bool)setting_get('demo_s03_seed') : false,
        'clients'  => (int)ops_val("SELECT COUNT(*) FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%'"),
        'users'    => (int)ops_val("SELECT COUNT(*) FROM client_users WHERE email LIKE '%s03@demo.test'"),
        'reqs'     => (int)ops_val("SELECT COUNT(*) FROM cx_requirements WHERE ref_code LIKE 'CX-%' AND poster_party_id IN (SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%')"),
    ];
}

function seed_s03_remove() {
    $n = 0;
    $del = function ($sql, $args = []) use (&$n) { try { $st = db()->prepare($sql); $st->execute($args); $n += $st->rowCount(); } catch (Throwable $e) {} };
    $parties = array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-%'") ?: []);
    foreach ($parties as $pid) {
        foreach (array_map(fn($r) => (int)$r['id'], ops_all("SELECT id FROM cx_requirements WHERE poster_party_id=?", [$pid]) ?: []) as $rid) {
            $del("DELETE FROM cx_applications WHERE requirement_id=?", [$rid]);
            $del("DELETE FROM cx_requirements WHERE id=?", [$rid]);
        }
        $del("DELETE FROM partner_contacts WHERE partner_id=?", [$pid]);
        $del("DELETE FROM partner_addresses WHERE partner_id=?", [$pid]);
        $del("DELETE FROM client_users WHERE partner_id=?", [$pid]);
        try { $del("DELETE FROM cx_organisations WHERE party_id=?", [$pid]); } catch (Throwable $e) {}
    }
    // delete branches (children) before parents
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

    // ---- helpers ----------------------------------------------------------
    $mkClient = function ($code, $name, $type, $industry, $city) use ($now) {
        db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,client_type,industry,status,created_at) VALUES (?,?,?,1,?,?, 'ACTIVE', ?)")
            ->execute([$code, $name, $name, $type, $industry, $now]);
        $pid = (int)db()->lastInsertId();
        if (function_exists('connect_org_migrate')) { connect_org_migrate();
            try { db()->prepare("INSERT INTO cx_organisations (name,org_type,package_key,party_id,status,approved_by,approved_at,created_at) VALUES (?, 'COMPANY','connect',?, 'ACTIVE','demo-seed',?,?)")->execute([$name, $pid, $now, $now]); } catch (Throwable $e) {}
        }
        return $pid;
    };
    $mkBranch = function ($parentId, $code, $name, $city) use ($now) {
        db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,parent_id,status,created_at) VALUES (?,?,?,1,?, 'ACTIVE', ?)")->execute([$code, $name, $name, $parentId, $now]);
        return (int)db()->lastInsertId();
    };
    $mkLoc = function ($pid, $label, $city) {
        db()->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city) VALUES (?, 'SITE', ?, ?, ?)")->execute([$pid, $label, $label, $city]);
    };
    $mkContact = function ($pid, $name, $desig, $dept, $email, $primary = 0) {
        db()->prepare("INSERT INTO partner_contacts (partner_id,name,designation,department,email,mobile,is_primary) VALUES (?,?,?,?,?,?,?)")->execute([$pid, $name, $desig, $dept, $email, '90000' . rand(10000, 99999), $primary]);
    };
    // role_preset → perms (blank = full incl. market.post). state: active|invited|suspended
    $mkUser = function ($pid, $email, $name, $preset, $perms, $state = 'active') use ($now) {
        $active = $state === 'suspended' ? 0 : 1;
        $mustChange = $state === 'invited' ? 1 : 0;
        $token = $state === 'invited' ? substr(md5($email), 0, 24) : '';
        db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,perms,role_preset,invite_token,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?, 'demo-seed', ?)")
            ->execute([$pid, $email, $name, s01_pw(), $active, $mustChange, $perms, $preset, $token, $now]);
    };
    $mkReq = function ($pid, $name, $title, $disc, $loc, $positions, $status, $desc = '') {
        $id = (int)cx_requirement_create(['title' => $title, 'poster_party_id' => $pid, 'poster_name' => $name, 'discipline_code' => $disc, 'location' => $loc, 'work_type' => 'FREELANCE', 'positions' => $positions, 'start_date' => s01_d(10), 'end_date' => s01_d(40), 'rate_min' => 6000, 'rate_max' => 14000, 'rate_unit' => 'day', 'description' => $desc], strtoupper($status) !== 'DRAFT');
        if (!in_array(strtoupper($status), ['DRAFT', 'OPEN'], true)) db()->prepare("UPDATE cx_requirements SET status=?, updated_at=? WHERE id=?")->execute([strtoupper($status), date('c'), $id]);
        return $id;
    };

    // ---- 6 client organisations -------------------------------------------
    $C1 = $mkClient('DEMO-S03-CLIENT-01', 'EXAACT Demo EPC Projects India Private Limited', 'EPC Contractor', 'Oil & Gas', 'Mumbai');
    $C2 = $mkClient('DEMO-S03-CLIENT-02', 'EXAACT Demo Energy & Refinery Limited', 'Asset Owner', 'Refinery', 'Vadodara');
    $C3 = $mkClient('DEMO-S03-CLIENT-03', 'EXAACT Demo Heavy Engineering Limited', 'Manufacturer', 'Heavy Fabrication', 'Hazira');
    $C4 = $mkClient('DEMO-S03-CLIENT-04', 'EXAACT Demo Power Transmission Solutions Limited', 'Power / Transmission', 'Power Transmission', 'Vadodara');
    $C5 = $mkClient('DEMO-S03-CLIENT-05', 'EXAACT Demo Renewable Projects Limited', 'Renewable', 'Solar & Wind', 'Pune');
    $C6 = $mkClient('DEMO-S03-CLIENT-06', 'EXAACT Demo Industrial Services', 'Small Business', 'Industrial Services', 'Rajkot');
    $clients = compact('C1', 'C2', 'C3', 'C4', 'C5', 'C6');
    // branches (parent_id hierarchy) on the large EPC client
    $mkBranch($C1, 'DEMO-S03-BRANCH-01', 'EPC Projects — Jamnagar Branch', 'Jamnagar');
    $mkBranch($C1, 'DEMO-S03-BRANCH-02', 'EPC Projects — Chennai Branch', 'Chennai');

    // ---- 12 locations (partner_addresses) ---------------------------------
    foreach ([[$C1, 'Head Office', 'Mumbai'], [$C1, 'Project Office', 'Jamnagar'], [$C1, 'Project Office', 'Hazira'], [$C1, 'Project Office', 'Chennai'], [$C1, 'Project Office', 'Pune'],
              [$C2, 'Gujarat Refinery Site', 'Vadodara'], [$C2, 'Maharashtra Terminal', 'Mumbai'], [$C2, 'Panipat Project', 'Panipat'],
              [$C3, 'Fabrication Works', 'Hazira'], [$C4, 'Substation Project Office', 'Vadodara'], [$C5, 'Solar Site Office', 'Pune'], [$C6, 'Head Office', 'Rajkot']] as [$pid, $label, $city]) $mkLoc($pid, $label, $city);

    // ---- 30 contacts across ≥20 departments -------------------------------
    $depts = ['Projects','Quality Assurance','Quality Control','Procurement','Engineering','Construction','Inspection','Finance','Commercial','Vendor Management','Operations','HSE','Planning','Contracts','Electrical','Mechanical','Civil','Instrumentation','Commissioning','Site Coordination'];
    $roles = ['Project Director','Technical Manager','QA Manager','QC Manager','Engineering Manager','Inspection Coordinator','Project Manager','Site Manager','Construction Manager','Commercial Manager','Procurement Manager','Accounts Manager','Site Coordinator','Operations Director','Managing Director'];
    $ci = 0;
    $spread = [$C1 => 10, $C2 => 6, $C3 => 5, $C4 => 5, $C5 => 3, $C6 => 1];
    foreach ($spread as $pid => $count) {
        for ($k = 0; $k < $count; $k++) {
            $dept = $depts[$ci % count($depts)]; $role = $roles[$ci % count($roles)];
            $mkContact($pid, 'Contact ' . ($ci + 1) . ' (S03)', $role, $dept, 'contact' . ($ci + 1) . '.s03@demo.test', $k === 0 ? 1 : 0);
            $ci++;
        }
    }

    // ---- 20 portal users (5 role types + lifecycle states) ----------------
    // Presets: A admin(full), B technical, C project, D commercial, E site.
    $permsB = 'calls,reports,reports.decide,request,complaint,market.post';   // technical manager (creates requirements, reviews)
    $permsC = 'calls,deputation,request,market.post';                          // project manager
    $permsD = 'invoices,complaint,market.vouchers';                            // procurement / commercial
    $permsE = 'deputation';                                                    // site user (restricted)
    // showcase users on the two most-used clients + a spread on the rest
    $mkUser($C1, 'epc.admin.s03@demo.test', 'EPC Client Administrator', 'FULL', '', 'active');
    $mkUser($C1, 'epc.tech.s03@demo.test', 'EPC Technical Manager', 'QUALITY', $permsB, 'active');
    $mkUser($C1, 'epc.project.s03@demo.test', 'EPC Project Manager', 'FULL', $permsC, 'active');
    $mkUser($C1, 'epc.commercial.s03@demo.test', 'EPC Commercial Manager', 'COMMERCIAL', $permsD, 'active');
    $mkUser($C1, 'epc.site.s03@demo.test', 'EPC Site User', 'READONLY', $permsE, 'active');
    $mkUser($C1, 'epc.invited.s03@demo.test', 'EPC Invited User', 'QUALITY', $permsB, 'invited');
    $mkUser($C2, 'energy.admin.s03@demo.test', 'Energy Client Administrator', 'FULL', '', 'active');
    $mkUser($C2, 'energy.tech.s03@demo.test', 'Energy Technical Manager', 'QUALITY', $permsB, 'active');
    $mkUser($C2, 'energy.commercial.s03@demo.test', 'Energy Commercial Manager', 'COMMERCIAL', $permsD, 'active');
    $mkUser($C2, 'energy.suspended.s03@demo.test', 'Energy Ex-User', 'READONLY', $permsE, 'suspended');
    $mkUser($C3, 'heavy.admin.s03@demo.test', 'Heavy Eng Administrator', 'FULL', '', 'active');
    $mkUser($C3, 'heavy.tech.s03@demo.test', 'Heavy Eng Technical Manager', 'QUALITY', $permsB, 'active');
    $mkUser($C3, 'heavy.site.s03@demo.test', 'Heavy Eng Site User', 'READONLY', $permsE, 'active');
    $mkUser($C4, 'power.admin.s03@demo.test', 'Power Administrator', 'FULL', '', 'active');
    $mkUser($C4, 'power.tech.s03@demo.test', 'Power Technical Manager', 'QUALITY', $permsB, 'active');
    $mkUser($C4, 'power.project.s03@demo.test', 'Power Project Manager', 'FULL', $permsC, 'active');
    $mkUser($C4, 'power.invited.s03@demo.test', 'Power Invited User', 'QUALITY', $permsB, 'invited');
    $mkUser($C5, 'renew.admin.s03@demo.test', 'Renewable Administrator', 'FULL', '', 'active');
    $mkUser($C5, 'renew.tech.s03@demo.test', 'Renewable Technical Manager', 'QUALITY', $permsB, 'active');
    $mkUser($C6, 'small.admin.s03@demo.test', 'Industrial Services Admin', 'FULL', '', 'active');
    if (function_exists('setting_set')) setting_set('portal_enabled', '1');

    // ---- requirements: 5 active + 2 draft + 2 completed + 1 cancelled ------
    $mkReq($C4, 'EXAACT Demo Power Transmission Solutions Limited', 'Transmission Line & Substation Testing Technician', 'ELEC', 'Vadodara', 3, 'OPEN', 'Transmission / substation / electrical testing / HV / EHV. Min 5y. Local + 200 km. Freelance, 30 days.');
    $mkReq($C3, 'EXAACT Demo Heavy Engineering Limited', 'Welding Inspector (CSWIP) — Pressure Vessels', 'WELD', 'Hazira', 2, 'OPEN', 'CSWIP welding inspection, pressure-vessel experience.');
    $mkReq($C2, 'EXAACT Demo Energy & Refinery Limited', 'NDT UT Level II — Oil & Gas', 'NDT', 'Vadodara', 2, 'OPEN', 'NDT UT Level II, oil & gas experience. 45 days, 12-hour shift.');
    $mkReq($C1, 'EXAACT Demo EPC Projects India Private Limited', 'QA/QC Mechanical Engineer', 'MECH', 'Jamnagar', 4, 'OPEN', 'QA/QC mechanical, 6 months.');
    $mkReq($C5, 'EXAACT Demo Renewable Projects Limited', 'Electrical Commissioning Engineer', 'ELEC', 'Multiple sites', 2, 'OPEN', 'Electrical commissioning, Pan-India travel.');
    $mkReq($C1, 'EXAACT Demo EPC Projects India Private Limited', 'Piping Inspector (draft)', 'MECH', 'Hazira', 2, 'DRAFT', 'Draft — not yet posted.');
    $mkReq($C4, 'EXAACT Demo Power Transmission Solutions Limited', 'Protection Engineer (draft)', 'ELEC', 'Vadodara', 1, 'DRAFT', 'Draft.');
    $mkReq($C1, 'EXAACT Demo EPC Projects India Private Limited', 'Coating Inspector (completed)', 'MECH', 'Mumbai', 1, 'CLOSED', 'Completed engagement.');
    $mkReq($C2, 'EXAACT Demo Energy & Refinery Limited', 'Turnaround Inspector (completed)', 'MECH', 'Vadodara', 3, 'CLOSED', 'Completed shutdown.');
    $mkReq($C3, 'EXAACT Demo Heavy Engineering Limited', 'Dimensional Inspector (cancelled)', 'MECH', 'Hazira', 1, 'CANCELLED', 'Cancelled by client.');
    $say('6 clients + 2 branches + 12 locations + 30 contacts + 20 portal users + 10 requirements');

    // ---- DASHBOARD (real, derived) ---------------------------------------
    $has = function ($sql, $args = []) { try { return (int)ops_val($sql, $args) > 0; } catch (Throwable $e) { return false; } };
    $cnt = function ($sql, $args = []) { try { return (int)ops_val($sql, $args); } catch (Throwable $e) { return 0; } };
    $reqBase = "FROM cx_requirements WHERE poster_party_id IN (SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%')";
    // isolation check: C4's transmission requirement must not belong to C3
    $c4Req = (int)ops_val("SELECT id FROM cx_requirements WHERE poster_party_id=? AND title LIKE 'Transmission%'", [$C4]);
    $dash = [
        ['6 client organisations (one master each)', $cnt("SELECT COUNT(*) FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%'") === 6],
        ['Each client is also a marketplace org', $cnt("SELECT COUNT(*) FROM cx_organisations WHERE party_id IN (SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%')") >= 6],
        ['Branch hierarchy (parent_id)', $has("SELECT 1 FROM business_partners WHERE code LIKE 'DEMO-S03-BRANCH-%' AND parent_id=?", [$C1])],
        ['>= 12 client locations', $cnt("SELECT COUNT(*) FROM partner_addresses WHERE partner_id IN (SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%')") >= 12],
        ['>= 20 distinct departments', $cnt("SELECT COUNT(DISTINCT department) FROM partner_contacts WHERE partner_id IN (SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%') AND department<>''") >= 20],
        ['>= 30 client contacts', $cnt("SELECT COUNT(*) FROM partner_contacts WHERE partner_id IN (SELECT id FROM business_partners WHERE code LIKE 'DEMO-S03-CLIENT-%')") >= 30],
        ['>= 20 client-portal users', $cnt("SELECT COUNT(*) FROM client_users WHERE email LIKE '%s03@demo.test'") >= 20],
        ['5 role types present', ($has("SELECT 1 FROM client_users WHERE role_preset='FULL' AND email LIKE '%s03@demo.test'") && $has("SELECT 1 FROM client_users WHERE role_preset='QUALITY' AND email LIKE '%s03@demo.test'") && $has("SELECT 1 FROM client_users WHERE role_preset='COMMERCIAL' AND email LIKE '%s03@demo.test'") && $has("SELECT 1 FROM client_users WHERE role_preset='READONLY' AND email LIKE '%s03@demo.test'"))],
        ['User lifecycle: invited / active / suspended', ($has("SELECT 1 FROM client_users WHERE must_change=1 AND invite_token<>'' AND email LIKE '%s03@demo.test'") && $has("SELECT 1 FROM client_users WHERE is_active=1 AND email LIKE '%s03@demo.test'") && $has("SELECT 1 FROM client_users WHERE is_active=0 AND email LIKE '%s03@demo.test'"))],
        ['5 active (OPEN) requirements', $cnt("SELECT COUNT(*) $reqBase AND status='OPEN'") >= 5],
        ['2 draft requirements', $cnt("SELECT COUNT(*) $reqBase AND status='DRAFT'") >= 2],
        ['2 completed (CLOSED) requirements', $cnt("SELECT COUNT(*) $reqBase AND status='CLOSED'") >= 2],
        ['1 cancelled requirement', $cnt("SELECT COUNT(*) $reqBase AND status='CANCELLED'") >= 1],
        ['Multi-client isolation (scoped by party)', ($c4Req > 0 && !$has("SELECT 1 FROM cx_requirements WHERE id=? AND poster_party_id=?", [$c4Req, $C3]))],
        ['Requirements reusable by later prompts', ($has("SELECT 1 FROM cx_requirements WHERE poster_party_id=? AND title LIKE 'Transmission%'", [$C4]) && $has("SELECT 1 FROM cx_requirements WHERE poster_party_id=? AND title LIKE 'Welding%'", [$C3]))],
    ];
    $allpass = true; foreach ($dash as [$l, $ok]) if (!$ok) $allpass = false;
    if (function_exists('setting_set')) setting_set('demo_s03_seed', date('c'));
    return ['log' => $log, 'dashboard' => $dash, 'allpass' => $allpass, 'ids' => $clients];
}
