<?php
// ============================================================================
//  DEMO-S01 — Scenario seed: Transmission Technician / Electrical Inspection
//  Marketplace Journey.  A complete, INTERCONNECTED, production-like scenario
//  built ENTIRELY on the existing engines (no duplicate systems), so the owner
//  can log in as each role and test the whole journey end to end.
//
//  Usage (from phpapp/):
//    php tools/seed-scenario-s01.php            load / refresh (idempotent)
//    php tools/seed-scenario-s01.php --remove   remove all DEMO-S01 data
//    php tools/seed-scenario-s01.php --status    report what exists
//
//  Everything it creates is tagged DEMO-S01 (emails, codes, names) and is
//  removable without touching other data. It REUSES: cx_professionals + the
//  passport engines (taxonomy graph, geo, privacy, verification, credentials,
//  projects), cx_requirements/cx_applications, the identity link, the deploy
//  bridge (PDSO jobs), client_users (portal) and users (staff).
// ============================================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php');
$libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

function say($s) { echo $s . "\n"; }
function pw($p = 'demo12345') { return password_hash($p, PASSWORD_DEFAULT); }
$today = date('Y-m-d');
$D = fn($days) => date('Y-m-d', strtotime($today . ($days >= 0 ? " +$days days" : " -" . abs($days) . " days")));

// ---------------------------------------------------------------------------
//  REMOVE / STATUS
// ---------------------------------------------------------------------------
function s01_purge() {
    $n = 0;
    // Emails/codes that identify DEMO-S01 records.
    $proEmails = ['arjun.s01@demo.test','cand.b.s01@demo.test','cand.c.s01@demo.test','cand.d.s01@demo.test','cand.e.s01@demo.test'];
    $proIds = [];
    foreach ($proEmails as $e) { $id = (int)ops_val("SELECT id FROM cx_professionals WHERE email=?", [$e]); if ($id) $proIds[] = $id; }
    // requirements posted for the DEMO client
    $party = (int)ops_val("SELECT id FROM business_partners WHERE legal_name LIKE 'DEMO-S01%'");
    $reqIds = $party ? array_map(fn($r)=>(int)$r['id'], ops_all("SELECT id FROM cx_requirements WHERE poster_party_id=?", [$party]) ?: []) : [];

    $del = function ($sql, $args = []) use (&$n) { try { $st = db()->prepare($sql); $st->execute($args); $n += $st->rowCount(); } catch (Throwable $e) {} };
    foreach ($reqIds as $rid) {
        $del("DELETE FROM cx_applications WHERE requirement_id=?", [$rid]);
        $del("DELETE FROM cx_engagements WHERE requirement_id=?", [$rid]);
        $del("DELETE FROM jobs WHERE source_module='connect' AND source_requirement_id=?", [$rid]);
        $del("DELETE FROM cx_requirements WHERE id=?", [$rid]);
    }
    foreach ($proIds as $pid) {
        $del("DELETE FROM cx_profile_tax WHERE pro_id=?", [$pid]);
        $del("DELETE FROM cx_pro_certs WHERE pro_id=?", [$pid]);
        $del("DELETE FROM cx_pro_projects WHERE pro_id=?", [$pid]);
        $del("DELETE FROM cx_identity_link WHERE professional_id=?", [$pid]);
        $del("DELETE FROM cx_client_bench WHERE professional_id=?", [$pid]);
        try { db()->prepare("DELETE FROM cx_verifications WHERE subject_kind='professional' AND subject_id=?")->execute([$pid]); } catch (Throwable $e) {}
        $del("DELETE FROM cx_professionals WHERE id=?", [$pid]);
    }
    // DEMO-S01 inspectors, client users, staff users, client party, jobs, calls
    $del("DELETE FROM inspectors WHERE emp_code LIKE 'DEMO-S01%'");
    $del("DELETE FROM client_users WHERE email LIKE '%s01@demo.test'");
    $del("DELETE FROM users WHERE username LIKE 'demo.s01%'");
    if ($party) {
        $calls = array_map(fn($r)=>(int)$r['id'], ops_all("SELECT id FROM calls WHERE client_id=?", [$party]) ?: []);
        foreach ($calls as $cid) { $del("DELETE FROM jobs WHERE call_id=?", [$cid]); $del("DELETE FROM calls WHERE id=?", [$cid]); }
        $del("DELETE FROM cx_req_templates WHERE owner_party_id=?", [$party]);
        $del("DELETE FROM business_partners WHERE id=?", [$party]);
    }
    if (function_exists('setting_set')) setting_set('demo_s01_seed', '');
    return $n;
}

$arg = $argv[1] ?? '';
if ($arg === '--status') {
    say('DEMO-S01 loaded : ' . (setting_get('demo_s01_seed') ? 'yes (' . setting_get('demo_s01_seed') . ')' : 'no'));
    say('Professionals   : ' . (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE email LIKE '%s01@demo.test'"));
    say('Client party    : ' . (int)ops_val("SELECT COUNT(*) FROM business_partners WHERE legal_name LIKE 'DEMO-S01%'"));
    exit(0);
}
if ($arg === '--remove') { $n = s01_purge(); say("Removed $n DEMO-S01 records."); exit(0); }

// A clean reload every run — idempotent.
s01_purge();
$t0 = microtime(true);
say('== DEMO-S01 — Transmission Technician Marketplace Journey ==');

// ===========================================================================
//  PHASE 1 — THE PROFESSIONAL PASSPORT (Arjun Mehta) + 4 more candidates
// ===========================================================================

/** Resolve or create a taxonomy node by (kind,name), optionally under a parent. */
function s01_node($kind, $name, $parentId = 0) {
    return (int)connect_tax_node_add($kind, $name, (int)$parentId);
}
/** Create a professional and return its id. */
function s01_pro(array $a) {
    connect_pro_migrate();
    db()->prepare("INSERT INTO cx_professionals (email,name,mobile,headline,disciplines,skills,work_types,availability,is_active,verification_tier,passport_token,password_hash,created_at)
                   VALUES (?,?,?,?,?,?,?,?,1,'registered',?,?,?)")
        ->execute([$a['email'], $a['name'], $a['mobile'] ?? '', $a['headline'] ?? '', $a['disciplines'] ?? '', $a['skills'] ?? '',
                   $a['work_types'] ?? 'FREELANCE', $a['availability'] ?? 'AVAILABLE', substr(md5($a['email']),0,20), pw()]);
    return (int)db()->lastInsertId();
}

$log = [];

// ---- Arjun Mehta (DEMO-S01-PRO-001) — the hero passport --------------------
$arjun = s01_pro([
    'email' => 'arjun.s01@demo.test', 'name' => 'Arjun Mehta', 'mobile' => '9812300001',
    'headline' => 'Electrical Transmission Inspection & Testing Specialist',
    'disciplines' => 'ELEC,QAQC', 'availability' => 'AVAILABLE',
    'skills' => 'transmission line inspection, substation inspection, electrical equipment inspection, high voltage testing, pre-commissioning, QA/QC electrical, 220kV, 400kV, GIS, protection panels, visual inspection, punch list',
]);
$log['professional'] = $arjun;

// Taxonomy tree: Electrical → Power & Energy → Transmission & Distribution → assets/roles/skills
$nElec = s01_node('DOMAIN', 'Electrical');
$nPower = s01_node('SECTOR', 'Power & Energy', $nElec);
$nTD   = s01_node('SPECIALIZATION', 'Transmission & Distribution', $nPower);
$roleTx = s01_node('ROLE', 'Transmission Technician', $nTD);
$roleSub = s01_node('ROLE', 'Substation Inspection Technician', $nTD);
$skills = ['Transmission Line Inspection','Substation Inspection','Electrical Equipment Inspection','High Voltage Testing Support','Pre-Commissioning Inspection','QA/QC Electrical Inspection','Visual Inspection','Document Review','Material Verification','Punch List Verification','Quality Surveillance','Vendor Inspection Support'];
$equip = ['Power Transformers','Circuit Breakers','Current Transformers','Voltage Transformers','Disconnectors','Surge Arresters','GIS Equipment','Protection Panels','Transmission Line Towers','Conductors','Insulators','Cable Termination Systems'];
$volts = ['132 kV','220 kV','400 kV'];

// attach primary + additional roles
connect_profile_tax_attach($arjun, $roleTx, 'PRIMARY_ROLE', ['years' => 10, 'competency' => 'EXPERT', 'verified' => 1]);
connect_profile_tax_attach($arjun, $roleSub, 'ADDITIONAL_ROLE', ['years' => 8]);
foreach (['Electrical Inspector','Electrical Testing Technician','Commissioning Support Technician','QA/QC Electrical Inspector','Field Engineer'] as $rn)
    connect_profile_tax_attach($arjun, s01_node('ROLE', $rn, $nTD), 'ADDITIONAL_ROLE', ['years' => 5]);
foreach ($skills as $s) connect_profile_tax_attach($arjun, s01_node('SKILL', $s, $nTD), 'SKILL', ['years' => 6, 'competency' => 'STRONG']);
foreach ($equip as $e)  connect_profile_tax_attach($arjun, s01_node('EQUIPMENT', $e, $nTD), 'EQUIPMENT', ['years' => 8]);
foreach ($volts as $v)  connect_profile_tax_attach($arjun, s01_node('SKILL', $v, $nTD), 'SKILL', ['years' => 7, 'competency' => 'STRONG']);
// aliases so keyword search finds him
foreach (['HV Inspector' => $roleTx, 'High Voltage Inspector' => $roleTx, '220 kV Inspector' => s01_node('SKILL','220 kV',$nTD), '400 kV Inspector' => s01_node('SKILL','400 kV',$nTD), 'GIS Inspector' => s01_node('EQUIPMENT','GIS Equipment',$nTD)] as $alias => $nid)
    if (function_exists('connect_tax_alias_add') && $nid) connect_tax_alias_add($nid, $alias);

// Location & mobility — Vadodara base, Pan-India (radius stored but inactive)
if (function_exists('connect_geo_augment_professional')) connect_geo_augment_professional();
if (function_exists('connect_geo_save_mobility'))
    connect_geo_save_mobility($arjun, ['base_city' => 'Vadodara', 'base_state' => 'Gujarat', 'base_country' => 'IN', 'travel_radius_km' => 500, 'mobility_mode' => 'PAN_INDIA', 'pan_india' => 1]);
else db()->prepare("UPDATE cx_professionals SET base_city='Vadodara', pan_india=1, travel_radius_km=500 WHERE id=?")->execute([$arjun]);

// Privacy — full name, on-request contact, rate band, listed
if (function_exists('connect_privacy_save')) connect_privacy_save($arjun, ['contact' => 'on_request', 'rate' => 'band', 'identity' => 'full', 'listed' => 1]);

// Certifications — 3 valid / 1 expiring / 1 expired (expiry drives the state)
if (function_exists('connect_cred_cert_save')) {
    connect_cred_cert_save($arjun, ['name' => 'Electrical Safety Training', 'authority' => 'DEMO Safety Council', 'cert_number' => 'DEMO-ES-4471', 'discipline' => 'Electrical', 'issue_date' => $GLOBALS['D'](-400), 'expiry_date' => date('Y-m-d', strtotime('+2 years'))]);
    connect_cred_cert_save($arjun, ['name' => 'High Voltage Safety Awareness', 'authority' => 'DEMO HV Institute', 'cert_number' => 'DEMO-HV-2210', 'discipline' => 'Electrical', 'issue_date' => $GLOBALS['D'](-300), 'expiry_date' => date('Y-m-d', strtotime('+18 months'))]);
    connect_cred_cert_save($arjun, ['name' => 'Quality Inspection Training', 'authority' => 'DEMO QA Body', 'cert_number' => 'DEMO-QA-9080', 'discipline' => 'QA/QC', 'issue_date' => $GLOBALS['D'](-200), 'expiry_date' => date('Y-m-d', strtotime('+1 year'))]);
    connect_cred_cert_save($arjun, ['name' => 'Working at Height Training', 'authority' => 'DEMO Safety Council', 'cert_number' => 'DEMO-WAH-1120', 'discipline' => 'HSE', 'issue_date' => $GLOBALS['D'](-360), 'expiry_date' => date('Y-m-d', strtotime('+45 days'))]);   // EXPIRING
    connect_cred_cert_save($arjun, ['name' => 'Basic HSE Training', 'authority' => 'DEMO Safety Council', 'cert_number' => 'DEMO-HSE-3300', 'discipline' => 'HSE', 'issue_date' => $GLOBALS['D'](-800), 'expiry_date' => date('Y-m-d', strtotime('-30 days'))]);   // EXPIRED
}
// Projects — 5 structured project records
if (function_exists('connect_cred_project_save')) {
    connect_cred_project_save($arjun, ['title' => '400 kV Transmission Line Project', 'role' => 'Electrical Inspection Support', 'industry' => 'Power Transmission', 'location' => 'Gujarat, India', 'equipment' => 'Transmission Towers, Conductors, Insulators', 'scope' => 'Tower erection & stringing inspection, insulator checks', 'start_date' => '2021-02-01', 'end_date' => '2021-08-15']);
    connect_cred_project_save($arjun, ['title' => '220 kV Substation Construction', 'role' => 'QA/QC Electrical Inspector', 'industry' => 'Power Transmission', 'location' => 'Maharashtra, India', 'equipment' => 'Power Transformers, Circuit Breakers, CTs, VTs', 'scope' => 'Installation inspection, material verification, documentation', 'start_date' => '2020-01-10', 'end_date' => '2020-11-20']);
    connect_cred_project_save($arjun, ['title' => '132 kV GIS Substation', 'role' => 'Inspection & Documentation Verification', 'industry' => 'Power Transmission', 'location' => 'Gujarat, India', 'equipment' => 'GIS, Circuit Breakers, Protection Systems', 'scope' => 'GIS bay inspection, protection documentation review', 'start_date' => '2019-03-01', 'end_date' => '2019-09-30']);
    connect_cred_project_save($arjun, ['title' => '400 kV Transmission Upgrade', 'role' => 'Pre-Commissioning Inspection Support', 'industry' => 'Power Transmission', 'location' => 'Rajasthan, India', 'equipment' => 'Conductors, Insulators', 'scope' => 'Pre-commissioning checks', 'start_date' => '2022-05-01', 'end_date' => '2022-10-10']);
    connect_cred_project_save($arjun, ['title' => '220 kV Substation Quality Surveillance', 'role' => 'Independent Electrical Inspector', 'industry' => 'Power Transmission', 'location' => 'Madhya Pradesh, India', 'equipment' => 'Power Transformers, Protection Panels', 'scope' => 'Quality surveillance, punch list', 'start_date' => '2023-01-15', 'end_date' => '2023-06-30']);
}
// Verification — legitimately elevate via the ledger (ID + credential VERIFIED)
if (function_exists('connect_verify_submit') && function_exists('connect_verify_review')) {
    [, , $c1] = connect_verify_submit('professional', $arjun, 'ID_DOC', '', 'DEMO ONLY — NOT A REAL CERTIFICATE');
    connect_verify_review((int)$c1, 'VERIFIED', 'DEMO-S01 identity check');
    [, , $c2] = connect_verify_submit('professional', $arjun, 'CREDENTIAL', '', 'DEMO ONLY — Electrical Safety Training');
    connect_verify_review((int)$c2, 'VERIFIED', 'DEMO-S01 credential check');
}
db()->prepare("UPDATE cx_professionals SET years_experience=14 WHERE id=?")->execute([$arjun]);
if (function_exists('connect_profile_tax_backfill')) connect_profile_tax_backfill($arjun);

say('  Professional passport: Arjun Mehta (#' . $arjun . ') — roles/skills/equipment/voltage/certs/projects/verified');

// ---- Candidates B–E (for ranking + negative tests) -------------------------
function s01_candidate(array $a, $nodeMap) {
    $id = s01_pro($a);
    foreach ($nodeMap as $rel => $nodes) foreach ($nodes as $nid => $yrs) connect_profile_tax_attach($id, $nid, $rel, ['years' => $yrs]);
    if (function_exists('connect_privacy_save')) connect_privacy_save($id, ['contact' => 'on_request', 'rate' => 'band', 'identity' => 'full', 'listed' => 1]);
    if (function_exists('connect_geo_save_mobility') && !empty($a['base_city'])) connect_geo_save_mobility($id, ['base_city' => $a['base_city'], 'base_state' => $a['base_state'] ?? '', 'base_country' => 'IN', 'mobility_mode' => $a['mobility'] ?? 'RADIUS', 'travel_radius_km' => $a['radius'] ?? 200, 'pan_india' => !empty($a['pan_india']) ? 1 : 0]);
    return $id;
}
$sk = fn($n) => s01_node('SKILL', $n, $nTD);
$candB = s01_candidate([
    'email' => 'cand.b.s01@demo.test', 'name' => 'Vikram Rao (S01-B)', 'mobile' => '9812300002',
    'headline' => 'Senior HV Substation Inspector', 'disciplines' => 'ELEC',
    'skills' => 'substation inspection, 220kV, power transformer, circuit breaker, protection panels, QA/QC electrical',
    'base_city' => 'Ahmedabad', 'base_state' => 'Gujarat', 'pan_india' => 1, 'availability' => 'AVAILABLE',
], ['PRIMARY_ROLE' => [$roleSub => 10], 'SKILL' => [$sk('220 kV') => 9, $sk('Substation Inspection') => 10, $sk('QA/QC Electrical Inspection') => 8], 'EQUIPMENT' => [s01_node('EQUIPMENT','Power Transformers',$nTD) => 9]]);
$candC = s01_candidate([
    'email' => 'cand.c.s01@demo.test', 'name' => 'Sunil Patel (S01-C)', 'mobile' => '9812300003',
    'headline' => 'Electrical Inspector (limited transmission)', 'disciplines' => 'ELEC',
    'skills' => 'electrical inspection, visual inspection, 33kV, panel inspection',
    'base_city' => 'Surat', 'base_state' => 'Gujarat', 'radius' => 150, 'availability' => 'AVAILABLE',
], ['PRIMARY_ROLE' => [s01_node('ROLE','Electrical Inspector',$nTD) => 7], 'SKILL' => [$sk('Visual Inspection') => 6, $sk('33 kV') => 5]]);
$candD = s01_candidate([
    'email' => 'cand.d.s01@demo.test', 'name' => 'Ramesh Iyer (S01-D)', 'mobile' => '9812300004',
    'headline' => 'Civil/Building QA Inspector', 'disciplines' => 'CIVIL',
    'skills' => 'building construction, civil inspection, concrete, basic electrical awareness',
    'base_city' => 'Pune', 'base_state' => 'Maharashtra', 'radius' => 100, 'availability' => 'AVAILABLE',
], ['PRIMARY_ROLE' => [s01_node('ROLE','Civil Inspector', s01_node('SPECIALIZATION','Civil Works', s01_node('DOMAIN','Civil'))) => 8]]);
$candE = s01_candidate([
    'email' => 'cand.e.s01@demo.test', 'name' => 'Deepak Shah (S01-E)', 'mobile' => '9812300005',
    'headline' => 'Mechanical Technician (unavailable)', 'disciplines' => 'MECH',
    'skills' => 'mechanical fitting, no transmission experience',
    'base_city' => 'Chennai', 'base_state' => 'Tamil Nadu', 'radius' => 50, 'availability' => 'BUSY',
], ['PRIMARY_ROLE' => [s01_node('ROLE','Mechanical Fitter', s01_node('SPECIALIZATION','Mechanical Works', s01_node('DOMAIN','Mechanical'))) => 6]]);
$log['candidates'] = compact('candB','candC','candD','candE');
say('  Candidates: B #' . $candB . ' (strong) · C #' . $candC . ' (medium) · D #' . $candD . ' (weak) · E #' . $candE . ' (ineligible/busy)');

// ===========================================================================
//  PHASE 2 — CLIENT + PORTAL LOGIN + STAFF LOGINS
// ===========================================================================
db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,created_at) VALUES ('DEMO-S01 POWER PROJECTS PRIVATE LIMITED','DEMO-S01 Power Projects',1,'ACTIVE',?)")->execute([date('c')]);
$party = (int)db()->lastInsertId();
$log['client_party'] = $party;
// mark it a self-service marketplace org so the buyer lands on the hiring home
if (function_exists('connect_org_migrate')) { connect_org_migrate();
    try { db()->prepare("INSERT INTO cx_organisations (name,org_type,package_key,party_id,status,contact_name,contact_email,approved_by,approved_at,created_at) VALUES ('DEMO-S01 Power Projects','COMPANY','connect',?, 'ACTIVE','Priya Client','client.s01@demo.test','demo-seed',?,?)")->execute([$party, date('c'), date('c')]); } catch (Throwable $e) {}
}
// Client portal login
if (function_exists('portal_migrate')) portal_migrate();
db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,perms,created_by,created_at) VALUES (?,?,?,?,1,0,'', 'demo-seed', ?)")
    ->execute([$party, 'client.s01@demo.test', 'Priya Client (DEMO-S01)', pw(), date('c')]);

// Staff logins (existing users table). Reuse real roles.
$mkUser = function ($username, $name, $role, $email) {
    $ex = (int)ops_val("SELECT id FROM users WHERE username=?", [$username]);
    if ($ex) return $ex;
    $parts = explode(' ', trim($name), 2);
    $super = in_array($role, ['MASTER_ADMIN', 'ADMIN'], true) ? 1 : 0;
    db()->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active) VALUES (?,?,?,?,?,?,?,1)")
        ->execute([$username, pw(), $parts[0], $parts[1] ?? '', $email, $role, $super]);
    return (int)db()->lastInsertId();
};
$uAdmin  = $mkUser('demo.s01.admin',    'DEMO-S01 Marketplace Admin', 'MASTER_ADMIN', 'admin.s01@demo.test');
$uCoord  = $mkUser('demo.s01.coord',    'DEMO-S01 Operations Coordinator', 'COORDINATOR', 'coord.s01@demo.test');
$uReview = $mkUser('demo.s01.reviewer', 'DEMO-S01 Technical Reviewer', 'COORDINATOR', 'reviewer.s01@demo.test');
$uIssue  = $mkUser('demo.s01.approver', 'DEMO-S01 Report Approver', 'MASTER_ADMIN', 'approver.s01@demo.test');
say('  Client party #' . $party . ' + portal login client.s01@demo.test + 4 staff logins (demo.s01.*)');

// ===========================================================================
//  PHASE 3 — THE MARKETPLACE REQUIREMENT
// ===========================================================================
$reqId = (int)cx_requirement_create([
    'title' => 'Transmission Technician for 220 kV Substation Inspection',
    'poster_party_id' => $party, 'poster_name' => 'DEMO-S01 Power Projects',
    'sector_code' => '', 'discipline_code' => 'ELEC', 'location' => 'Ahmedabad, Gujarat',
    'work_type' => 'FREELANCE', 'start_date' => $D(10), 'end_date' => $D(15), 'positions' => 1,
    'rate_min' => 6000, 'rate_max' => 9000, 'rate_unit' => 'day',
    'description' => 'Independent inspection of 220 kV substation electrical equipment installation: power transformers, circuit breakers, CT/VT, disconnectors, protection panels. Visual inspection, document review, installation verification, punch-point identification, evidence capture and reporting. Required 220 kV experience; 400 kV preferred. Diploma/Degree in Electrical OR equivalent demonstrated experience. Required: Electrical Safety Training.',
], true);
$log['requirement'] = $reqId;
say('  Requirement #' . $reqId . ' posted OPEN — 220 kV Substation Inspection (DEMO-S01)');

// Matching snapshot AT PUBLISH TIME (before anyone applies — the matcher excludes
// applicants). This is the honest record of "who did the marketplace recommend".
$matchTopArjun = false; $matchOrder = [];
foreach (connect_match_for_requirement(cx_requirement_get($reqId), 12) as $r) {
    if (($r['kind'] ?? '') !== 'professional') continue;
    $matchOrder[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'score' => (int)$r['score']];
}
$matchTopArjun = !empty($matchOrder) && (int)$matchOrder[0]['id'] === (int)$arjun;
$myIds = [$arjun, $candB, $candC, $candD, $candE];
$scen = array_values(array_filter($matchOrder, fn($m) => in_array($m['id'], $myIds, true)));
say('  Matching @publish: ' . implode(' > ', array_map(fn($m) => explode(' ', $m['name'])[0] . '(' . $m['score'] . ')', array_slice($scen, 0, 5))));

// (Phases 4–7 appended below in the same run.)
$GLOBALS['S01'] = compact('arjun','candB','candC','candD','candE','party','reqId','uAdmin','uCoord','uReview','uIssue','today','matchTopArjun','scen') + ['D' => $D];
if (is_file(__DIR__ . '/seed-scenario-s01-part2.php')) require __DIR__ . '/seed-scenario-s01-part2.php';
else say('  (part 2 not yet present — phases 1–3 only)');
