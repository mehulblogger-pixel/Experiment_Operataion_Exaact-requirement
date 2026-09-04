<?php
// Connect B0 — organisation accounts + org-type entitlements. Asserts an org's
// type maps to the right module bundle (from the existing product packages), the
// registry stores it, and the module-gate helper answers correctly.
t_section('connect organisation accounts (B0)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // Entitlement mapping (derived from PRODUCT_PACKAGES 'off' lists).
    $tpia = connect_org_type_modules('TPIA');
    t_ok(in_array('operations', $tpia, true) && in_array('reporting', $tpia, true), 'a TPIA gets operations + reporting');
    t_ok(!in_array('sales', $tpia, true) && !in_array('hr', $tpia, true), "a TPIA hides sales + hr (the package's 'off' list)");
    t_ok(in_array('connect', $tpia, true), 'a TPIA also gets the shared marketplace');

    $agency = connect_org_type_modules('MANPOWER_AGENCY');
    t_ok(in_array('hr', $agency, true) && in_array('operations', $agency, true), 'a manpower agency gets hiring + operations (STAFFING)');
    t_ok(!in_array('sales', $agency, true), 'a manpower agency hides the sales CRM');

    $recruit = connect_org_type_modules('RECRUITMENT_AGENCY');
    t_ok(in_array('sales', $recruit, true) && in_array('hr', $recruit, true), 'a recruitment agency gets CRM + hiring');
    t_ok(!in_array('operations', $recruit, true) && !in_array('reporting', $recruit, true), 'a recruitment agency hides field ops + the report engine');

    t_eq(['connect'], connect_org_type_modules('COMPANY'), 'a company gets the marketplace only');
    t_eq(['pro'], connect_org_type_modules('FREELANCER'), 'a freelancer gets self-service only');
    t_eq([], connect_org_type_modules('NONSENSE'), 'an unknown type gets nothing');

    // Registry: register, read back, change type.
    $id = connect_org_add('Gujarat Inspection Services', 'TPIA');
    t_ok($id > 0, 'an organisation can be registered');
    $o = connect_org_get($id);
    t_eq('TPIA', $o['org_type'], 'the type is stored');
    t_eq('TPIA', $o['package_key'], 'the package is derived from the type');
    t_eq(0, connect_org_add('', 'TPIA'), 'a nameless organisation is rejected');
    t_eq(0, connect_org_add('X', 'NOPE'), 'an unknown type is rejected');

    // The gate helper.
    t_ok(connect_org_can_module($id, 'operations'), 'the TPIA org can use operations');
    t_ok(!connect_org_can_module($id, 'sales'), 'the TPIA org cannot use sales');

    // Re-typing re-derives the package + entitlements.
    connect_org_set_type($id, 'MANPOWER_AGENCY');
    t_eq('STAFFING', connect_org_get($id)['package_key'], 're-typing re-derives the package');
    t_ok(connect_org_can_module($id, 'hr'), 'now the org (agency) can use hiring');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
