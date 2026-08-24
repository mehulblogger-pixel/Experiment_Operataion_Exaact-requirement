<?php
// The field engineer (role INSPECTOR) gets a phone-first, stripped job view: the
// desk/commercial panels — the communication log, the contract-number gap notice,
// the client-bills panel and the expenses/profitability fold — are HIDDEN for them,
// not merely collapsed, so nothing on the screen but their own work (check-in,
// report, QAP) is shown. Managers/coordinators still see the full record.
t_section('the inspector job view is stripped of the desk/commercial panels');

$src = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');

// The view computes a single field-inspector predicate, before the tabbed record.
t_ok(strpos($src, '$fieldInspector') !== false, 'the job view computes a field-inspector gate');
$gatePos = strpos($src, '$fieldInspector = ');
t_ok($gatePos !== false, 'the field-inspector gate is assigned once');

// It keys off is_inspector() — the same predicate the rest of the file uses.
t_ok(preg_match('/\$fieldInspector\s*=\s*function_exists\(\'is_inspector\'\)\s*&&\s*is_inspector\(\)/', $src) === 1,
    'the gate is a pure field inspector (role INSPECTOR)');

// Each management/commercial panel is guarded by !$fieldInspector, and each guard
// comes AFTER the predicate is defined (so the variable is in scope).
foreach ([
    ['tosrm_render_comms', 'the communication log'],
    ['$jgap && !$fieldInspector', 'the contract-number gap notice'],
    ['($chgHeads || $byHead) && !$fieldInspector', 'the client-bills panel'],
] as [$needle, $label]) {
    $p = strpos($src, $needle);
    t_ok($p !== false, "$label is present");
}
// The comms log guard specifically carries the !$fieldInspector clause.
t_ok(preg_match('/tosrm_render_comms\'\)\s*&&\s*!\$fieldInspector/', $src) === 1,
    'the communication log is hidden from the inspector');
// The expenses/profitability fold is wrapped in an if (!$fieldInspector) … endif.
t_ok(strpos($src, 'if (!$fieldInspector):') !== false
    && strpos($src, 'expenses/profitability fold') !== false,
    'the expenses/profitability fold is hidden from the inspector');

// Every guard sits after the predicate definition.
foreach (['$jgap && !$fieldInspector', '($chgHeads || $byHead) && !$fieldInspector', 'if (!$fieldInspector):'] as $g) {
    t_ok(strpos($src, $g) > $gatePos, "guard [$g] is in scope of the predicate");
}

// The inspector KEEPS their own work: the site check-in panel (with its voucher
// pointer) is not gated away, so no expense guidance is lost by dropping Money.
t_ok(strpos($src, 'id="checkin"') !== false, 'the site check-in panel is still shown to the inspector');
t_ok(strpos($src, '/vouchers') !== false, 'the voucher pointer survives on the job view');

// The predicate resolves correctly per role: inspector → hidden; coordinator and
// master → full record.
$pdo = db();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('ijd_insp','Ravi','INSPECTOR',1)")->execute();
$insUid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('ijd_coord','Coord','COORDINATOR',1)")->execute();
$coordUid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active, is_superuser) VALUES ('ijd_master','M','MASTER_ADMIN',1,1)")->execute();
$mUid = (int)$pdo->lastInsertId();

$fieldInspector = fn() => function_exists('is_inspector') && is_inspector();

$_SESSION['uid'] = $insUid; current_user(true); ua(true);
t_ok($fieldInspector() === true, 'an inspector gets the stripped view');
$_SESSION['uid'] = $coordUid; current_user(true); ua(true);
t_ok($fieldInspector() === false, 'a coordinator keeps the full record');
$_SESSION['uid'] = $mUid; current_user(true); ua(true);
t_ok($fieldInspector() === false, 'a master admin keeps the full record');

unset($_SESSION['uid']); current_user(true); ua(true);
