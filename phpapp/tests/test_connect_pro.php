<?php
// Connect A1 — the self-registered freelancer pool (a DISTINCT entity from an
// org's private staff). Asserts registration, login, the M4 profile save, and
// that this pool is separate from the inspectors roster.
t_section('connect freelancer pool (A1)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // --- self-registration --------------------------------------------------
    $err = connect_pro_register(['name' => 'Freelance Farid', 'email' => 'farid@example.com', 'mobile' => '9990001111', 'password' => 'secret12']);
    t_eq('', $err, 'a professional can self-register');
    $me = connect_pro_user();
    t_ok($me && $me['email'] === 'farid@example.com', 'registration signs them in');
    t_ok(preg_match('/^[a-f0-9]{32}$/', (string)$me['passport_token']) === 1, 'they get a passport token');

    // Validation guards.
    t_ok(connect_pro_register(['name' => 'X', 'email' => 'bad', 'password' => 'secret12']) !== '', 'a bad e-mail is rejected');
    t_ok(connect_pro_register(['name' => 'X', 'email' => 'x@example.com', 'password' => 'short']) !== '', 'a short password is rejected');
    t_ok(connect_pro_register(['name' => 'Dupe', 'email' => 'farid@example.com', 'password' => 'secret12']) !== '', 'the same e-mail cannot register twice');

    // --- login --------------------------------------------------------------
    unset($_SESSION['cxpid']);
    t_ok(connect_pro_login('farid@example.com', 'wrong') !== '', 'a wrong password is refused');
    t_eq('', connect_pro_login('farid@example.com', 'secret12'), 'the right password signs in');

    // --- M4 profile ---------------------------------------------------------
    $id = connect_pro_id();
    connect_pro_profile_save($id, [
        'name' => 'Freelance Farid', 'headline' => 'Senior QA/QC & Welding Inspector',
        'skills' => 'Welding inspection, NDT (UT/RT)', 'disciplines' => ['WELD', 'NDT'],
        'work_types' => ['per_visit', 'manday', 'shutdown'], 'base_city' => 'Vadodara',
        'preferred_locations' => 'Dahej, Hazira', 'pan_india' => '1', 'overseas' => '1',
        'availability' => 'AVAILABLE', 'day_rate_min' => 3500, 'day_rate_max' => 5000, 'languages' => 'English, Hindi, Gujarati',
    ]);
    $p = ops_one("SELECT * FROM cx_professionals WHERE id=?", [$id]);
    t_ok(strpos((string)$p['work_types'], 'shutdown') !== false, 'work-type preferences are stored');
    t_ok(strpos((string)$p['disciplines'], 'WELD') !== false, 'discipline preferences are stored');
    t_eq(1, (int)$p['pan_india'], 'pan-India willingness is stored');
    t_ok(connect_pro_profile_pct($p) >= 80, 'a filled profile scores high on strength');

    // --- separation from org staff -----------------------------------------
    $inInspectors = (int)ops_val("SELECT COUNT(*) FROM inspectors WHERE name='Freelance Farid'");
    t_eq(0, $inInspectors, 'a self-registered professional is NOT written into the org staff roster');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
