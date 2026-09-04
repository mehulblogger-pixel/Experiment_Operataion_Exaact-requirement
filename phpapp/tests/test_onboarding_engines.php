<?php
// First-run onboarding engines — a brand-new customer's day-one path. The full empty-database
// walkthrough lives in tools/cold-start.php (setup_needed detection needs a truly empty DB);
// this guards the onboarding ENGINES in the main suite: a multi-capability company self-registers
// (NOT single-select), a professional self-registers on the passport, and the company posts its
// first requirement. Runs in the shared transaction with fresh unique e-mails.
t_section('first-run onboarding engines');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_org_migrate(); connect_pro_migrate();
    $sfx = substr(md5((string)mt_rand()), 0, 6);

    // A company onboards itself with SEVERAL capabilities at once.
    $email = 'owner.' . $sfx . '@onb.test';
    [$ok, $msg, $acct] = connect_org_register([
        'name' => 'Onboard Multi Ltd ' . $sfx, 'org_type' => 'ENTERPRISE',
        'contact_name' => 'Owner', 'contact_email' => $email, 'contact_mobile' => '9820001000',
        'password' => 'onboard123',
        'caps' => ['TPIA', 'TECHNICAL_MANPOWER', 'FREELANCE_SUPPLY'],
    ]);
    t_ok($ok === true, 'a multi-capability company registers: ' . $msg);
    $party = (int)ops_val("SELECT id FROM business_partners WHERE legal_name=? ORDER BY id DESC LIMIT 1", ['Onboard Multi Ltd ' . $sfx]);
    t_ok($party > 0, 'the business party is created');
    t_ok((int)ops_val("SELECT COUNT(*) FROM cx_organisations WHERE party_id=?", [$party]) > 0, 'the organisation is created');
    t_ok((int)ops_val("SELECT COUNT(*) FROM client_users WHERE LOWER(email)=?", [strtolower($email)]) > 0, 'a working portal login is created');

    // ALL selected capabilities persist — the company is not forced into one identity.
    $caps = connect_org_caps($party);
    $codes = strtoupper(implode(',', array_map(fn($c) => is_array($c) ? ($c['cap_code'] ?? $c['code'] ?? '') : (string)$c, is_array($caps) ? $caps : [])));
    t_ok(strpos($codes, 'TPIA') !== false, 'the TPIA capability persists');
    t_ok(strpos($codes, 'TECHNICAL_MANPOWER') !== false, 'the manpower-supply capability persists');
    t_ok(strpos($codes, 'FREELANCE_SUPPLY') !== false, 'the freelance-supply capability persists (multi, not single-select)');

    // A duplicate registration on the same e-mail is refused (sign in instead).
    t_ok(connect_org_register(['name' => 'Dup', 'org_type' => 'ENTERPRISE', 'contact_name' => 'D', 'contact_email' => $email, 'password' => 'onboard123'])[0] === false,
        'a second company on the same e-mail is refused');

    // A professional self-registers on the marketplace passport.
    $proEmail = 'pro.' . $sfx . '@onb.test';
    t_eq(connect_pro_register(['name' => 'Kavya Menon', 'email' => $proEmail, 'mobile' => '9820002000', 'password' => 'onboard123']), '', 'a professional self-registers');
    t_ok((int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE email=?", [$proEmail]) > 0, 'the professional record exists');
    // a short password / bad e-mail is refused
    t_ok(connect_pro_register(['name' => 'X', 'email' => 'not-an-email', 'password' => 'x'])  !== '', 'a bad professional registration is refused');

    // The new company posts its first requirement — it lands OPEN.
    $rid = (int)cx_requirement_create(['title' => 'First Requirement', 'poster_party_id' => $party, 'poster_name' => 'Onboard Multi Ltd',
        'discipline_code' => 'MECH', 'location' => 'Surat', 'work_type' => 'FREELANCE', 'positions' => 1,
        'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+30 days')), 'rate_min' => 8000, 'rate_max' => 12000, 'rate_unit' => 'day'], true);
    t_ok($rid > 0 && strtoupper((string)ops_val("SELECT status FROM cx_requirements WHERE id=?", [$rid])) === 'OPEN', 'the first requirement is posted OPEN');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
