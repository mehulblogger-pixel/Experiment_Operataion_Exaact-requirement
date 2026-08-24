<?php
// R7 — is_inspector() matched only the literal INSPECTOR role, so a SR_INSPECTOR
// (a senior inspector who also does field work) got the desk UI and the non-inspector
// dashboard — no My Jobs, no site check-in, no My Voucher. is_field_inspector() now
// recognises INSPECTOR, SR_INSPECTOR and any non-management login seated on an
// inspector record, and the field-tool triggers use it.
t_section('SR_INSPECTOR gets the phone-first field UI (is_field_inspector)');

$pdo = db();
$mk = function($u, $role, $insId = null, $super = 0) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username, first_name, role, inspector_id, is_active, is_superuser) VALUES (?,?,?,?,1,?)")
        ->execute([$u, ucfirst(strtolower($role)), $role, $insId, $super]);
    return (int)$pdo->lastInsertId();
};
$pdo->prepare("INSERT INTO inspectors (name) VALUES ('R7 Sr Vikram')")->execute();
$srInsId = (int)$pdo->lastInsertId();

$insp  = $mk('r7_insp',  'INSPECTOR',    $srInsId + 100);
$sr    = $mk('r7_sr',    'SR_INSPECTOR', $srInsId);
$coord = $mk('r7_coord', 'COORDINATOR');
$mast  = $mk('r7_master','MASTER_ADMIN', null, 1);
// A coordinator who also happens to be seated on an inspector record keeps the desk UI.
$coordSeated = $mk('r7_coord_seat', 'COORDINATOR', $srInsId + 200);

$check = function($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); return is_field_inspector(); };

t_ok($check($insp)  === true,  'a plain inspector is a field inspector');
t_ok($check($sr)    === true,  'a SR_INSPECTOR is now a field inspector (R7)');
t_ok($check($coord) === false, 'a coordinator is not a field inspector');
t_ok($check($mast)  === false, 'a master admin is not a field inspector');
t_ok($check($coordSeated) === false, 'a manager seated on an inspector record keeps the desk UI');

// is_inspector() stays strict (literal role) — used for strict-role behaviour.
$_SESSION['uid'] = $sr; current_user(true); ua(true);
t_ok(is_inspector() === false, 'is_inspector() remains the literal-INSPECTOR predicate');

unset($_SESSION['uid']); current_user(true); ua(true);

// The field-tool triggers were switched to the broader predicate.
t_ok(strpos(file_get_contents(__DIR__ . '/../views/layout_top.php'), 'is_field_inspector()') !== false,
    'the navigation rail keys off is_field_inspector');
t_ok(strpos(file_get_contents(__DIR__ . '/../views/dashboard.php'), 'is_field_inspector()') !== false,
    'the inspector dashboard branch keys off is_field_inspector');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "if (is_field_inspector()) {") !== false, 'the voucher route serves a field inspector their own voucher');
t_ok(strpos(file_get_contents(__DIR__ . '/../lib/navindex.php'), 'is_field_inspector()') !== false,
    'the nav index keys off is_field_inspector');
t_ok(strpos(file_get_contents(__DIR__ . '/../lib/bills.php'), 'is_field_inspector()') !== false,
    'own-job bill upload is available to a field inspector');
