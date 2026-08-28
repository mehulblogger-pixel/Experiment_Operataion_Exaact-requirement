<?php
// Connect K14 / backlog #3 — verification & moderation. Asserts the deterministic
// validators, the honest submit flow (format pass ≠ verified — it queues for a
// human; format fail is auto-rejected), the moderation review, and the tier ladder
// (Registered → ID-verified → Credential-verified → Proven) driven only by real
// VERIFIED decisions, plus the Passport surface.
t_section('connect verification & moderation (#3)');

// --- deterministic validators ------------------------------------------------
t_ok(connect_verify_pan_valid('AAPFU0939F'),  'a well-formed PAN passes the format check');
t_ok(!connect_verify_pan_valid('AAPFU0939'),  'a malformed PAN fails');
t_ok(connect_verify_gstin_valid('27AAPFU0939F1ZV'),  'a valid GSTIN passes its mod-36 checksum');
t_ok(!connect_verify_gstin_valid('27AAPFU0939F1ZZ'), 'a GSTIN with a wrong check digit fails');
t_ok(connect_verify_aadhaar_valid('234123412346'),  'a valid Aadhaar passes the Verhoeff checksum');
t_ok(!connect_verify_aadhaar_valid('234123412347'), 'an Aadhaar with a wrong check digit fails');
t_eq('••••••939F', connect_verify_mask('AAPFU0939F'), 'an identifier is masked to its last 4');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_register(['name' => 'Verify Vikram', 'email' => 'vikram@example.com', 'password' => 'secret12']);
    $pid = connect_pro_id();

    // A fresh professional is 'registered', not verified.
    t_eq('registered', connect_verify_tier_for_professional($pid), 'a new professional starts at Registered');

    // A well-formed PAN does NOT auto-verify — it queues as PENDING (honest).
    [$ok, $msg, $vid] = connect_verify_submit('professional', $pid, 'PAN', 'AAPFU0939F');
    t_ok($ok && $vid > 0, 'a valid-format PAN is accepted for review');
    $row = ops_one("SELECT * FROM cx_verifications WHERE id=?", [$vid]);
    t_eq('PENDING', $row['status'], 'a format-valid PAN is PENDING, not auto-verified (format is not identity)');
    t_eq('deterministic', $row['method'], 'the PAN pre-screen ran deterministically');
    t_eq('registered', connect_verify_tier_for_professional($pid), 'the tier has NOT moved on a mere format pass');
    t_ok(strpos((string)$row['ref_masked'], '939F') !== false && strpos((string)$row['ref_masked'], 'AAPFU') === false,
        'only the masked PAN is stored, never the full number');

    // A malformed PAN is auto-rejected (deterministic catches the fake/typo).
    [$ok2, , $vid2] = connect_verify_submit('professional', $pid, 'PAN', 'NOTAPAN');
    t_eq('REJECTED', ops_val("SELECT status FROM cx_verifications WHERE id=?", [$vid2]), 'a malformed PAN is auto-rejected');

    // The pending PAN is in the moderation queue.
    $q = connect_verify_pending();
    $inQueue = false; foreach ($q as $r) if ((int)$r['id'] === $vid) $inQueue = true;
    t_ok($inQueue, 'the pending check appears in the moderation queue');
    t_ok(connect_verify_pending_count() >= 1, 'the pending count reflects the queue');

    // A moderator VERIFIES it → the professional reaches ID-verified.
    [$rok, ] = connect_verify_review($vid, 'VERIFIED', 'Matched the uploaded PAN scan', 'Coordinator Priya');
    t_ok($rok, 'a moderator can verify a pending check');
    t_eq('id_verified', connect_verify_tier_for_professional($pid), 'a VERIFIED ID check elevates the tier to ID-verified');

    // Credential requires ID first, then a verified credential → Credential-verified.
    [, , $vid3] = connect_verify_submit('professional', $pid, 'CREDENTIAL', '', 'CSWIP 3.1 certificate');
    t_eq('PENDING', ops_val("SELECT status FROM cx_verifications WHERE id=?", [$vid3]), 'a credential check is a manual (human) decision');
    connect_verify_review($vid3, 'VERIFIED', '', 'Coordinator Priya');
    t_eq('credential_verified', connect_verify_tier_for_professional($pid), 'ID + a verified credential reaches Credential-verified');

    // Proven needs a verified work-history record on top.
    [, , $vid4] = connect_verify_submit('professional', $pid, 'WORK_HISTORY', '', 'Two completed engagements');
    connect_verify_review($vid4, 'VERIFIED', '', 'Coordinator Priya');
    t_eq('proven', connect_verify_tier_for_professional($pid), 'a verified work record reaches Proven');

    // Revoking the ID check pulls the whole ladder back down (recomputed, honest).
    connect_verify_review($vid, 'REJECTED', 'Document withdrawn', 'Coordinator Priya');
    t_eq('registered', connect_verify_tier_for_professional($pid), 'losing the ID verification drops the tier back to Registered');

    // The Passport surfaces the tier honestly (not verified once ID is gone).
    $tok = ops_val("SELECT passport_token FROM cx_professionals WHERE id=?", [$pid]);
    $pass = connect_passport_public_data(connect_passport_lookup($tok));
    t_ok(isset($pass['verification']), 'the professional passport carries a verification block');
    t_ok($pass['verification']['verified'] === false, 'the passport shows NOT verified after the ID was revoked');
    t_eq('Registered', $pass['verification']['label'], 'the passport shows the honest current tier label');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
