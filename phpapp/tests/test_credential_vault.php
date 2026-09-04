<?php
// Slice P1 — Credential Vault: status derivation, the additive verify column,
// the render panel, and the Entity-360 registration. All additive; the
// allocation gate is untouched (covered by test_module24_competence.php).
t_section('credential vault (Slice P1)');

competence_migrate();

// ---- 1. credential_status() derivation, on a fixed reference date -----------
$on = '2026-08-27';
t_eq(credential_status(['valid_to' => '2027-01-01'], $on), 'VALID',        'in-date certificate is Valid');
t_eq(credential_status(['valid_to' => '2026-08-01'], $on), 'EXPIRED',      'past valid_to is Expired');
t_eq(credential_status(['valid_to' => '2026-09-10'], $on), 'EXPIRING',     'within the 45-day window is Expiring soon');
t_eq(credential_status(['valid_to' => ''],           $on), 'VALID',        'no-expiry certificate is Valid');
t_eq(credential_status(['valid_to' => $on],          $on), 'EXPIRING',     'valid_to == today is Expiring (not yet Expired)');

// verification verdicts
t_eq(credential_status(['valid_to' => '2027-01-01', 'verify_status' => 'REJECTED'],   $on), 'REJECTED',
    'a Rejected verdict overrides an in-date certificate');
t_eq(credential_status(['valid_to' => '2027-01-01', 'verify_status' => 'SUPERSEDED'], $on), 'SUPERSEDED',
    'a Superseded verdict stands regardless of date');
t_eq(credential_status(['valid_to' => '2027-01-01', 'verify_status' => 'UNDER_VERIFICATION'], $on), 'UNDER_VERIFICATION',
    'Under verification surfaces when nothing worse applies');
t_eq(credential_status(['valid_to' => '2026-08-01', 'verify_status' => 'UNDER_VERIFICATION'], $on), 'EXPIRED',
    'an expired certificate is Expired even while under verification');
t_eq(credential_status(['valid_to' => '2027-01-01', 'verify_status' => 'VERIFIED'], $on), 'VALID',
    'a Verified in-date certificate is Valid');

// pills are always a [label, class] pair
foreach (array_keys(CREDENTIAL_STATUS) as $st) {
    $p = credential_status_pill($st);
    t_ok(is_array($p) && count($p) === 2 && $p[0] !== '', "pill for $st is a label+class pair");
}

// ---- 2. the additive verify column exists and accepts a value --------------
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO inspectors (name, status, created_at) VALUES ('Vault Tester','ACTIVE',?)")->execute([date('c')]);
    $iid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspector_certs (inspector_id, name, number, valid_to, is_mandatory, status, verify_status)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute([$iid, 'CSWIP 3.1', 'C-1', '2027-01-01', 1, 'VALID', 'UNDER_VERIFICATION']);
    $row = ops_one("SELECT * FROM inspector_certs WHERE inspector_id=?", [$iid]);
    t_ok(array_key_exists('verify_status', $row), 'inspector_certs has the additive verify_status column');
    t_eq(credential_status($row, $on), 'UNDER_VERIFICATION', 'the stored verify_status drives the derived status');

    // ---- 3. the vault panel renders without error ---------------------------
    t_nothrow('credential_vault_render does not throw', function () use ($iid) {
        ob_start(); credential_vault_render('INSPECTOR', $iid, ['editable' => true]); ob_get_clean();
    });
    ob_start(); credential_vault_render('INSPECTOR', $iid, ['editable' => false]); $html = ob_get_clean();
    t_ok(is_string($html) && strpos($html, 'Credential vault') !== false, 'the vault panel renders its heading');
    t_ok(strpos($html, 'CSWIP 3.1') !== false, 'the vault lists the held certificate');

    // an unknown / non-inspector kind renders nothing (fail-closed)
    ob_start(); credential_vault_render('NCR', $iid); $none = ob_get_clean();
    t_eq($none, '', 'the vault renders nothing for a non-inspector kind');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- 4. Entity-360 registration --------------------------------------------
$reg = entity_360_registry();
t_ok(isset($reg['INSPECTOR']), 'the Entity-360 registry includes INSPECTOR');
t_ok(isset($reg['INSPECTOR']) && in_array('credential', $reg['INSPECTOR'][2], true), 'INSPECTOR gets the credential panel');
t_ok(isset($reg['INSPECTOR']) && in_array('tasks', $reg['INSPECTOR'][2], true) && in_array('history', $reg['INSPECTOR'][2], true),
    'INSPECTOR still gets the common tasks + history panels');
t_ok(defined('CREDENTIAL_VERIFY_STATES') || is_array(CREDENTIAL_VERIFY_STATES), 'the verify-state vocabulary is defined');
