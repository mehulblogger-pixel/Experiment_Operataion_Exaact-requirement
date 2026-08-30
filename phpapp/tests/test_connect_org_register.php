<?php
// Connect B1 — self-service organisation signup mints a WORKING login (auto-approve).
t_section('connect self-service org signup (B1)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_org_migrate(); portal_migrate();
    // Bad inputs
    [$b1] = connect_org_register(['name'=>'','org_type'=>'COMPANY','contact_email'=>'a@b.com','password'=>'longenough']);
    t_ok($b1 === false, 'a nameless org is refused');
    [$b2] = connect_org_register(['name'=>'X','org_type'=>'COMPANY','contact_email'=>'notanemail','password'=>'longenough']);
    t_ok($b2 === false, 'a bad e-mail is refused');
    [$b3] = connect_org_register(['name'=>'X','org_type'=>'COMPANY','contact_email'=>'a@b.com','password'=>'short']);
    t_ok($b3 === false, 'a too-short password is refused');

    // A company signs up → ACTIVE org + party(client) + working portal login
    [$ok, $msg, $acct] = connect_org_register(['name'=>'Zephyr Fab','org_type'=>'COMPANY','contact_name'=>'Ravi','contact_email'=>'ravi@zephyr.test','password'=>'Zephyr@2026']);
    t_ok($ok, 'a company self-registers ('.$msg.')');
    t_eq($acct['login_url'], '/portal/login?for=hire', 'a hiring company is pointed at the hiring-aware login');
    $u = ops_one("SELECT * FROM client_users WHERE LOWER(email)='ravi@zephyr.test'");
    t_ok($u && (int)$u['is_active'] === 1, 'a working, active login is created');
    t_ok($u && password_verify('Zephyr@2026', (string)$u['password_hash']), 'the chosen password signs in');
    $party = ops_one("SELECT * FROM business_partners WHERE id=?", [(int)$u['partner_id']]);
    t_ok($party && (int)$party['is_client'] === 1, 'a company party is a client');
    $org = ops_one("SELECT * FROM cx_organisations WHERE party_id=?", [(int)$u['partner_id']]);
    t_ok($org && strtoupper((string)$org['status']) === 'ACTIVE', 'the organisation is ACTIVE (auto-approved)');

    // An agency signs up → party is a subcontractor, agency flag on the account
    [$ok2,, $acct2] = connect_org_register(['name'=>'Vector Staffing','org_type'=>'MANPOWER_AGENCY','contact_email'=>'ops@vector.test','password'=>'Vector@2026']);
    t_ok($ok2, 'an agency self-registers');
    t_ok(!empty($acct2['is_agency']), 'the agency is flagged as an agency');
    $ap = ops_one("SELECT bp.* FROM business_partners bp JOIN client_users cu ON cu.partner_id=bp.id WHERE cu.email='ops@vector.test'");
    t_ok($ap && (int)$ap['is_subcontractor'] === 1, 'an agency party is a subcontractor');

    // Duplicate e-mail is refused
    [$dup] = connect_org_register(['name'=>'Zephyr Two','org_type'=>'COMPANY','contact_email'=>'ravi@zephyr.test','password'=>'Another@2026']);
    t_ok($dup === false, 'a duplicate e-mail is refused');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
