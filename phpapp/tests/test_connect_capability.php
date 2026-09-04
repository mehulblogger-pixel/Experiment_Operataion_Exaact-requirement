<?php
// Company Business Capabilities — a company enables a MIX of capabilities, the
// Combination Engine derives which modules are relevant, and the whole thing is
// additive + backward-compatible (an unconfigured company sees everything).
t_section('company business capabilities (multi-select + combination engine)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_cap_migrate();
    connect_cap_migrate(); // idempotent — running twice must not throw
    t_ok(true, 'connect_cap_migrate() is idempotent');

    // catalogue
    $cat = connect_cap_catalog();
    t_ok(count($cat) >= 20, 'the capability catalogue is populated');
    t_ok(isset($cat['TPIA']) && isset($cat['TECHNICAL_MANPOWER']), 'core capabilities (TPIA, manpower) are present');
    t_ok(isset($cat['FREELANCE_SUPPLY']) && isset($cat['FREELANCE_INSPECTOR_SUPPLY']),
        'Freelance Technical Resource Supplier is a first-class capability');
    t_ok(count(connect_cap_groups()) >= 4, 'capabilities are grouped (Inspection / Supply / Recruitment / Project)');

    // a company master to hang capabilities on
    db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,status,created_at) VALUES (?,?,?,1,'ACTIVE',?)")
        ->execute(['TEST-CAP-CO', 'Test Capability Co', 'Test Capability Co', date('c')]);
    $party = (int)db()->lastInsertId();
    t_ok($party > 0, 'a company master exists to carry capabilities');

    // BACKWARD COMPATIBLE: unconfigured company sees everything
    t_ok(!connect_cap_configured($party), 'a new company starts unconfigured');
    t_ok(connect_cap_shows($party, 'operations') && connect_cap_shows($party, 'reporting') && connect_cap_shows($party, 'money'),
        'an unconfigured company sees every module (nothing hidden — backward compatible)');

    // enable a mix
    connect_org_cap_bulk_set($party, ['TPIA', 'TECHNICAL_MANPOWER', 'FREELANCE_INSPECTOR_SUPPLY'], 'tester');
    t_ok(connect_cap_configured($party), 'the company is now configured');
    $caps = connect_org_caps($party);
    t_ok(in_array('TPIA', $caps, true) && in_array('TECHNICAL_MANPOWER', $caps, true), 'a company can hold MULTIPLE capabilities at once');
    t_ok(connect_org_has_cap($party, 'FREELANCE_INSPECTOR_SUPPLY'), 'connect_org_has_cap() reports an enabled capability');
    t_ok(!connect_org_has_cap($party, 'NDT'), 'a capability not enabled reports false');

    // combination engine derives modules from the enabled mix
    $mods = connect_cap_modules($party);
    t_ok(in_array('operations', $mods, true) && in_array('reporting', $mods, true) && in_array('hr', $mods, true),
        'the Combination Engine unions the modules of every enabled capability');

    // gating is now selective but 'connect' and 'admin' always show
    t_ok(connect_cap_shows($party, 'operations'), 'a configured company still sees modules its capabilities unlock');
    t_ok(connect_cap_shows($party, 'connect') && connect_cap_shows($party, 'admin'), 'marketplace + admin are always visible');

    // toggling one capability off updates the set (no duplicate rows)
    connect_org_cap_set($party, 'TPIA', false, 'tester');
    t_ok(!connect_org_has_cap($party, 'TPIA'), 'a capability can be switched off');
    $rowcount = (int)ops_val("SELECT COUNT(*) FROM cx_org_capabilities WHERE org_party_id=? AND capability_code='TPIA'", [$party]);
    t_eq($rowcount, 1, 'toggling never creates duplicate rows (one row per company+capability)');

    // resetting to empty returns to "sees everything"
    connect_org_cap_bulk_set($party, [], 'tester');
    t_ok(!connect_cap_configured($party), 'a company reset to no capabilities counts as unconfigured');
    t_ok(connect_cap_shows($party, 'operations') && connect_cap_shows($party, 'money'),
        'after reset the gate is permissive again (all modules visible)');

    // freelance supplier pools reader never fatals
    $pools = connect_supplier_pools($party);
    t_ok(isset($pools['internal']) && isset($pools['associated']) && isset($pools['marketplace']),
        'connect_supplier_pools() returns the three sourcing pools without error');

    // capabilities NEVER grant a permission — they are visibility only
    t_ok(!function_exists('connect_cap_grant') && !function_exists('connect_cap_permission'),
        'the engine exposes no permission-granting function (visibility only)');

    // ---- Stage 6 — operating company drives nav visibility ----
    t_ok(connect_cap_owner_does_inspection(), 'no operating company set → inspection visible (permissive default)');
    // a pure recruiter operating company hides the inspection registers
    connect_org_cap_bulk_set($party, ['TECH_RECRUITMENT', 'PERMANENT_PLACEMENT'], 'tester');
    connect_cap_owner_set($party);
    t_ok(connect_cap_owner_party() === $party, 'the operating company can be designated');
    t_ok(!connect_cap_owner_does_inspection(), 'a pure recruiter operating company hides inspection/ISO registers');
    // reporting area gating (Stage 6, extended): a recruiter produces no reports
    t_ok(!connect_cap_owner_shows('reporting'), 'a pure recruiter operating company hides the inspection Reporting area');
    // a freelance-inspector supplier DOES produce reports → Reporting visible
    connect_org_cap_bulk_set($party, ['FREELANCE_INSPECTOR_SUPPLY'], 'tester');
    t_ok(connect_cap_owner_shows('reporting'), 'a freelance-inspector supplier sees the Reporting area');
    // switch it to a TPIA and inspection returns
    connect_org_cap_bulk_set($party, ['TPIA', 'QAQC'], 'tester');
    t_ok(connect_cap_owner_does_inspection(), 'a TPIA/QA-QC operating company sees inspection registers again');
    t_ok(connect_cap_owner_shows('reporting'), 'a TPIA sees the Reporting area');
    // Money stays universal — never gated by capabilities
    t_ok(connect_cap_owner_shows('money'), 'the Money area is universal (never gated by capabilities)');

    // Operations + Recruitment (hr) gating
    connect_org_cap_bulk_set($party, ['PERMANENT_PLACEMENT', 'EXECUTIVE_SEARCH'], 'tester'); // pure recruiter
    t_ok(!connect_cap_owner_shows('operations'), 'a pure recruiter hides field Operations (no operational work)');
    t_ok(connect_cap_owner_shows('hr'), 'a recruiter still sees Recruitment (hr module)');
    connect_org_cap_bulk_set($party, ['TPIA', 'SITE_INSPECTION'], 'tester'); // pure TPIA, no staffing/recruitment
    t_ok(connect_cap_owner_shows('operations'), 'a TPIA sees Operations (inspection is operational work)');
    t_ok(!connect_cap_owner_shows('hr'), 'a pure inspection body without staffing/recruitment hides Recruitment');
    connect_org_cap_bulk_set($party, ['TECHNICAL_MANPOWER'], 'tester');
    t_ok(connect_cap_owner_shows('operations') && connect_cap_owner_shows('hr'),
        'a manpower supplier sees both Operations and Recruitment');

    // capability-appropriate landing: a non-reporting operating company's staff
    // KPI board drops the inspection-specific tiles (Stage 6 completion)
    if (function_exists('connect_kpi_board')) {
        connect_cap_owner_set(0);
        $keysDefault = array_column(connect_kpi_board(['audience' => 'staff'])['tiles'], 'key');
        t_ok(in_array('inspections', $keysDefault, true) && in_array('reports', $keysDefault, true),
            'with no operating company the staff board keeps inspection KPIs (backward compatible)');
        connect_org_cap_bulk_set($party, ['PERMANENT_PLACEMENT'], 'tester'); connect_cap_owner_set($party);
        $keysRec = array_column(connect_kpi_board(['audience' => 'staff'])['tiles'], 'key');
        t_ok(!in_array('inspections', $keysRec, true) && !in_array('reports', $keysRec, true),
            'a pure recruiter operating company drops inspection KPIs from the staff dashboard');
        t_ok(in_array('revenue', $keysRec, true) && in_array('ratings', $keysRec, true),
            'universal KPIs (revenue, ratings) remain for every company');
    }
    // clearing the operating company returns to fully permissive
    connect_cap_owner_set(0);
    t_ok(connect_cap_owner_party() === 0 && connect_cap_owner_does_inspection(),
        'clearing the operating company returns the workspace to permissive');

    // ---- Multi-capability self-onboarding via /join ----
    if (function_exists('connect_org_register')) {
        [$ok, $msg, $acct] = connect_org_register([
            'name' => 'Multi-Cap Onboard Co', 'org_type' => 'ENTERPRISE',
            'contact_name' => 'Owner', 'contact_email' => 'multicap.onboard@demo.test',
            'contact_mobile' => '9800000000', 'password' => 'onboard12345',
            'caps' => ['TPIA', 'FREELANCE_INSPECTOR_SUPPLY', 'TECHNICAL_MANPOWER'],
        ]);
        t_ok($ok, 'a company can self-onboard through connect_org_register()');
        $newParty = (int)ops_val("SELECT id FROM business_partners WHERE legal_name='Multi-Cap Onboard Co' ORDER BY id DESC LIMIT 1");
        $onboardCaps = $newParty ? connect_org_caps($newParty) : [];
        t_ok(in_array('TPIA', $onboardCaps, true) && in_array('FREELANCE_INSPECTOR_SUPPLY', $onboardCaps, true) && in_array('TECHNICAL_MANPOWER', $onboardCaps, true),
            'onboarding persists the MULTIPLE capabilities the company ticked at /join');
    }
} finally {
    connect_cap_owner_set(0); // never leak the operating-company setting out of the test
    if ($own && db()->inTransaction()) db()->rollBack();
}
