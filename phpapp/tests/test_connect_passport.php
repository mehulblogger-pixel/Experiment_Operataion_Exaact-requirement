<?php
// Connect K1 — the public professional passport, built over the P1 credential
// vault. Asserts: a stable unguessable share token; token→inspector lookup with
// wrong/empty tokens rejected; regeneration revokes the old token; and the
// PUBLIC payload carries verified credentials + reputation but NOTHING
// confidential (no email, mobile, salary, or cert number).
t_section('connect professional passport (K1)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO inspectors (name, email, mobile, salary_ctc, status, skills, designation, staff_kind, created_at)
                   VALUES ('Asha Welder','asha@example.com','9998887777', 750000, 'ACTIVE','Welding, NDT','Senior QA/QC Inspector','FREELANCER', ?)")
        ->execute([date('c')]);
    $id = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspector_certs (inspector_id, name, number, valid_to, status, verify_status, created_at)
                   VALUES (?, 'CSWIP 3.1 Welding Inspector', 'CW-12345', ?, 'VALID', 'VERIFIED', ?)")
        ->execute([$id, date('Y-m-d', strtotime('+2 years')), date('c')]);

    // --- token: minted once, then stable -----------------------------------
    $t1 = connect_passport_token($id);
    t_ok(preg_match('/^[a-f0-9]{32}$/', $t1) === 1, 'a 32-hex unguessable token is minted');
    $t2 = connect_passport_token($id);
    t_eq($t1, $t2, 'the token is stable across calls (not re-minted)');

    // --- lookup -------------------------------------------------------------
    $found = connect_passport_lookup($t1);
    t_ok($found && (int)$found['id'] === $id, 'a valid token resolves to the professional');
    t_ok(connect_passport_lookup('') === null, 'an empty token resolves to nobody');
    t_ok(connect_passport_lookup('not-a-real-token') === null, 'a malformed token resolves to nobody');
    t_ok(connect_passport_lookup(str_repeat('a', 32)) === null, 'a wrong token resolves to nobody');

    // --- regeneration revokes the old link ---------------------------------
    $t3 = connect_passport_regenerate($id);
    t_ok($t3 !== $t1, 'regeneration mints a different token');
    t_ok(connect_passport_lookup($t1) === null, 'the OLD token no longer resolves (revoked)');
    t_ok(connect_passport_lookup($t3) && (int)connect_passport_lookup($t3)['id'] === $id, 'the NEW token resolves');

    // --- public payload: rich, and free of anything confidential -----------
    $insp = ops_one("SELECT * FROM inspectors WHERE id=?", [$id]);
    $pub = connect_passport_public_data($insp);
    t_eq('Asha Welder', $pub['name'], 'public passport carries the name');
    t_eq(1, $pub['cred_total'], 'public passport lists the credential');
    t_eq(1, $pub['verified_count'], 'the verified credential is counted');
    $flat = strtolower(json_encode($pub));
    t_ok(strpos($flat, 'asha@example.com') === false, 'public passport does NOT leak the email');
    t_ok(strpos($flat, '9998887777') === false, 'public passport does NOT leak the mobile');
    t_ok(strpos($flat, '750000') === false, 'public passport does NOT leak the salary');
    t_ok(strpos($flat, 'cw-12345') === false, 'public passport does NOT leak the certificate number');

    // --- URL shape ----------------------------------------------------------
    t_ok(strpos(connect_passport_url($t3), '/p/' . $t3) !== false, 'the public URL is /p/<token>');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
