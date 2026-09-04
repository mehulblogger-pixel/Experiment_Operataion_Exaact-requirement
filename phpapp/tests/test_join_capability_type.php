<?php
// Capability-led onboarding: a company just ticks what it does, and we set up the
// right primary type from that (no jargon radio to decode). An explicit type still
// wins, so the old form and deep links keep working. This guards the derivation and
// the register path that leans on it.
t_section('capability-led org type');

// --- derivation rules ---
t_eq(connect_org_type_from_caps([]), 'COMPANY', 'nothing ticked → plain hire-only company');
t_eq(connect_org_type_from_caps(['TPIA', 'NDT']), 'TPIA', 'inspection only → inspection body');
t_eq(connect_org_type_from_caps(['PROJECT_MANAGEMENT']), 'TPIA', 'project services (runs work) → operations body');
t_eq(connect_org_type_from_caps(['TECHNICAL_MANPOWER']), 'MANPOWER_AGENCY', 'supply only → staffing agency');
t_eq(connect_org_type_from_caps(['TECH_RECRUITMENT', 'EXECUTIVE_SEARCH']), 'RECRUITMENT_AGENCY', 'recruitment only → recruitment agency');
t_eq(connect_org_type_from_caps(['TPIA', 'TECHNICAL_MANPOWER']), 'ENTERPRISE', 'does work AND supplies people → enterprise');
t_eq(connect_org_type_from_caps(['TECHNICAL_MANPOWER', 'TECH_RECRUITMENT']), 'ENTERPRISE', 'supply + recruitment → enterprise');
t_ok(isset(connect_org_types()[connect_org_type_from_caps(['NDT', 'QAQC', 'EXPEDITING'])]), 'derived type is always a real, valid org type');

// --- the register path derives the type when none is picked ---
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_org_migrate(); if (function_exists('connect_cap_migrate')) connect_cap_migrate();
    $mail = 'derive_' . substr(md5(uniqid('', true)), 0, 8) . '@ex.com';
    [$ok, $msg, $acct] = connect_org_register([
        'name' => 'Derive Co', 'contact_email' => $mail, 'contact_name' => 'D', 'password' => 'password1',
        'caps' => ['TECHNICAL_MANPOWER', 'TPIA'], // no org_type at all
    ]);
    t_ok($ok, 'a company can register with only capabilities ticked (no type picked): ' . $msg);
    $org = ops_one("SELECT org_type FROM cx_organisations WHERE contact_email=?", [$mail]);
    t_eq($org['org_type'] ?? '', 'ENTERPRISE', 'the type was derived from the ticked capabilities');

    // an explicit type still wins (back-compat)
    $mail2 = 'explicit_' . substr(md5(uniqid('', true)), 0, 8) . '@ex.com';
    [$ok2] = connect_org_register([
        'name' => 'Explicit Co', 'contact_email' => $mail2, 'contact_name' => 'E', 'password' => 'password1',
        'org_type' => 'TPIA', 'caps' => ['TECHNICAL_MANPOWER'], // caps say agency, but TPIA chosen
    ]);
    t_ok($ok2, 'explicit type still registers');
    $org2 = ops_one("SELECT org_type FROM cx_organisations WHERE contact_email=?", [$mail2]);
    t_eq($org2['org_type'] ?? '', 'TPIA', 'an explicitly chosen type overrides the derivation');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
