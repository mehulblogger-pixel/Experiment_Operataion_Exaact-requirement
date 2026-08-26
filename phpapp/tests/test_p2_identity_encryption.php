<?php
// Phase 2 §53 — identity documents (government/tax/ID numbers + scanned files) were masked and
// access-logged but stored in PLAINTEXT at rest, so a DB dump exposed them. Add app-level AES-256-GCM
// encryption keyed ONLY from the APP_ENCRYPTION_KEY env var (not in the DB). Ciphertext is
// self-describing (enc:v1:) so encrypted + legacy plaintext coexist; every path degrades safely, and a
// nightly backfill migrates existing plaintext once a key is set. Non-destructive: no key = old behaviour.
t_section('Phase 2 §53 — identity documents encrypted at rest');

t_ok(function_exists('app_encrypt') && function_exists('app_decrypt'), 'app_encrypt/app_decrypt exist');
t_ok(function_exists('iddoc_number_read') && function_exists('iddoc_file_bytes'), 'identity read helpers exist');
t_ok(function_exists('iddoc_encrypt_backfill') && function_exists('iddoc_plaintext_count'), 'backfill + count helpers exist');

$hadKey = getenv('APP_ENCRYPTION_KEY');

// With NO key, encryption is a safe no-op — the app behaves exactly as before.
putenv('APP_ENCRYPTION_KEY');   // unset
t_ok(app_enc_available() === false, 'with no key, encryption is unavailable');
t_eq(app_encrypt('SECRET-123'), 'SECRET-123', 'with no key, app_encrypt returns plaintext unchanged');
t_eq(app_decrypt('SECRET-123'), 'SECRET-123', 'with no key, app_decrypt returns plaintext unchanged');

// With a key set, values round-trip and ciphertext neither equals nor contains the plaintext.
putenv('APP_ENCRYPTION_KEY=unit-test-key-please-change-32bytes!!');
t_ok(app_enc_available() === true, 'with a key, encryption is available');
$ct = app_encrypt('AADHAAR-1234-5678');
t_ok(app_is_encrypted($ct), 'the ciphertext carries the enc:v1: marker');
t_ok(strpos($ct, 'AADHAAR') === false && strpos($ct, '1234') === false, 'the ciphertext does not contain the plaintext');
t_eq(app_decrypt($ct), 'AADHAAR-1234-5678', 'app_decrypt recovers the exact plaintext');
$ct2 = app_encrypt('AADHAAR-1234-5678');
t_ok($ct2 !== $ct, 'the same plaintext encrypts to different ciphertext (random IV)');
// A tampered ciphertext fails the GCM tag and is returned unchanged (never wrong plaintext).
t_ok(app_decrypt(APP_ENC_PREFIX . base64_encode('garbagegarbagegarbagegarbage!!')) === APP_ENC_PREFIX . base64_encode('garbagegarbagegarbagegarbage!!'),
    'a corrupted ciphertext is not silently decrypted to wrong data');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('identity_migrate')) identity_migrate();
    $pdo = db();

    // A NEW identity document stored the way iddoc_add does with a key set: number encrypted (plaintext
    // column blank), last-4 in the clear for masking, scan encrypted — and read back via the helpers.
    // (iddoc_add's own form validation, e.g. doc_kind, is unrelated to §53 and covered by its wiring
    // assertion below; here we exercise the storage + read shape directly.)
    $num = 'PAN-ABCDE1234F';
    $pdo->prepare("INSERT INTO person_documents (person_kind, person_id, doc_kind, doc_number, doc_number_enc, number_last4, file_data, uploaded_at)
                   VALUES ('INSPECTOR',9911,'PAN','', ?, ?, ?, ?)")
        ->execute([app_encrypt($num), substr($num, -4), app_encrypt(base64_encode('PANSCAN')), date('c')]);
    $row = ops_one("SELECT * FROM person_documents WHERE person_id=9911 ORDER BY id DESC LIMIT 1");
    t_ok($row !== null, 'the identity document was filed');
    t_eq((string)$row['doc_number'], '', 'the plaintext number column is left blank when a key is set');
    t_ok(app_is_encrypted((string)$row['doc_number_enc']), 'the number is stored encrypted');
    t_eq((string)$row['number_last4'], '234F', 'the last-4 is kept in the clear for masking');
    t_eq(iddoc_number_read($row), 'PAN-ABCDE1234F', 'iddoc_number_read decrypts the full number');
    t_eq(iddoc_file_bytes($row), 'PANSCAN', 'iddoc_file_bytes decrypts + decodes the scan');
    // iddoc_add itself carries the §53 encryption path.
    $idnSrc = file_get_contents(__DIR__ . '/../lib/identity.php');
    t_ok(strpos($idnSrc, '$numEnc') !== false && strpos($idnSrc, 'app_encrypt($number)') !== false,
        'iddoc_add encrypts the number when a key is set');

    // A LEGACY plaintext row (pre-encryption) is still readable, and the backfill migrates it.
    $pdo->prepare("INSERT INTO person_documents (person_kind, person_id, doc_kind, doc_number, number_last4, file_data, uploaded_at)
                   VALUES ('INSPECTOR',9912,'PASSPORT','P1234567','4567',?, ?)")->execute([base64_encode('SCANBYTES'), date('c')]);
    $legacy = ops_one("SELECT * FROM person_documents WHERE person_id=9912");
    t_eq(iddoc_number_read($legacy), 'P1234567', 'a legacy plaintext number is read as-is');
    t_eq(iddoc_file_bytes($legacy), 'SCANBYTES', 'a legacy plaintext scan is read as-is');

    $before = iddoc_plaintext_count();
    t_ok($before >= 1, 'the legacy plaintext row is counted as unencrypted');
    $n = iddoc_encrypt_backfill();
    t_ok($n >= 1, 'the backfill encrypts the legacy plaintext row');
    $migrated = ops_one("SELECT * FROM person_documents WHERE person_id=9912");
    t_eq((string)$migrated['doc_number'], '', 'the migrated row blanks the plaintext number');
    t_ok(app_is_encrypted((string)$migrated['doc_number_enc']), 'the migrated number is now encrypted');
    t_eq(iddoc_number_read($migrated), 'P1234567', 'the migrated number still decrypts correctly');
    t_eq(iddoc_file_bytes($migrated), 'SCANBYTES', 'the migrated scan still decodes correctly');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    if ($hadKey === false) putenv('APP_ENCRYPTION_KEY'); else putenv('APP_ENCRYPTION_KEY=' . $hadKey);
}

// wiring
$idn = file_get_contents(__DIR__ . '/../lib/identity.php');
$cron = file_get_contents(__DIR__ . '/../cron.php');
t_ok(strpos($idn, 'iddoc_number_read($d)') !== false, 'the reveal path decrypts through the helper');
t_ok(strpos($idn, 'iddoc_file_bytes($d)') !== false, 'the file-download path decrypts through the helper');
t_ok(strpos($cron, 'iddoc_encrypt_backfill()') !== false, 'the nightly cron migrates plaintext identity docs');
