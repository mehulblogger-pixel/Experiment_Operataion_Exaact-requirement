<?php
// Duplicate prevention (Stage 7). One person = one marketplace profile. Exact
// e-mail and exact mobile are blocked at registration; a near-name match is a
// soft flag for review, never a block or a penalty (directive F10).
t_section('marketplace duplicate detection & prevention');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_migrate();
    t_eq(connect_pro_phone_key('+91 98123 45678'), '9812345678', 'phone key keeps the last 10 digits');
    t_eq(connect_pro_phone_key('098123-45678'), '9812345678', 'a 0-prefix / punctuation normalises to the same key');

    // an existing professional
    db()->prepare("INSERT INTO cx_professionals (email,name,mobile,is_active,created_at) VALUES ('rk.sharma@demo.test','Rajesh Kumar Sharma','9812345678',1,?)")->execute([date('c')]);
    $exist = (int)db()->lastInsertId();

    // detection by each signal
    $byMail = connect_pro_duplicates('', 'rk.sharma@demo.test', '');
    t_ok(count($byMail) === 1 && $byMail[0]['reason'] === 'email', 'an exact e-mail is detected');
    $byMob = connect_pro_duplicates('', 'someone.else@demo.test', '+91-98123-45678');
    t_ok(count($byMob) === 1 && $byMob[0]['reason'] === 'mobile', 'the same mobile (different e-mail) is detected');
    $byName = connect_pro_duplicates('Rajesh Kumar Sharma', 'brand.new@demo.test', '9000000000');
    t_ok(count($byName) === 1 && $byName[0]['reason'] === 'name', 'a matching name is detected');
    t_ok(count(connect_pro_duplicates('Totally Different', 'nope@demo.test', '9000000001')) === 0, 'an unrelated person is not flagged');
    // exclude-self
    t_ok(count(connect_pro_duplicates('Rajesh Kumar Sharma', 'rk.sharma@demo.test', '9812345678', $exist)) === 0, 'excluding self returns no duplicates');

    // registration: same mobile, new e-mail → BLOCKED
    $err = connect_pro_register(['name' => 'Rajesh K', 'email' => 'rajesh.new@demo.test', 'mobile' => '098123 45678', 'password' => 'password123']);
    t_ok(strpos($err, 'mobile') !== false, 'registering with an existing mobile is blocked');

    // registration: same e-mail → BLOCKED (existing guard)
    $err2 = connect_pro_register(['name' => 'X', 'email' => 'rk.sharma@demo.test', 'mobile' => '9700000000', 'password' => 'password123']);
    t_ok(strpos($err2, 'already registered') !== false, 'registering with an existing e-mail is blocked');

    // registration: new e-mail + new mobile + similar name → ALLOWED (name is soft)
    $err3 = connect_pro_register(['name' => 'Rajesh Kumar Sharma', 'email' => 'rks.other@demo.test', 'mobile' => '9700000001', 'password' => 'password123']);
    t_eq($err3, '', 'a same-name person with a distinct e-mail and mobile can still register (name is only a soft flag)');
    // …but they are surfaced as a possible duplicate for review
    t_ok(count(connect_pro_duplicates('Rajesh Kumar Sharma', 'rks.other@demo.test', '9700000001', $exist)) >= 1, 'the same-name person is still flagged for review');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
