<?php
// Profitability (revenue, invoice value, margin, net profit) on a job is commercial
// and shown only to managers/finance who hold the figure permissions — never to a
// coordinator or an inspector.
t_section('job profitability is managers-only');

// The job view gates the profitability panel behind the figure permissions.
$src = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
t_ok(strpos($src, '$canSeeProfit') !== false, 'the job view computes a profitability-visibility gate');
t_ok(strpos($src, "can('data.profitability')") !== false && strpos($src, "can('data.revenue')") !== false,
    'the gate keys off the profitability / revenue permissions');
// The Revenue line sits inside the gated block (after the gate is opened).
$gatePos = strpos($src, '$canSeeProfit');
$revPos  = strpos($src, '>Revenue');
t_ok($gatePos !== false && $revPos !== false && $revPos > $gatePos, 'the Revenue figure is inside the gated panel');

// The permission logic: an inspector and a coordinator are excluded; a master or a
// user holding data.profitability is included.
$pdo = db();
$gate = fn() => (can('data.profitability') || can('data.revenue')) || is_master();

$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('pv_insp','Ravi','INSPECTOR',1)")->execute();
$insUid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('pv_coord','Coord','COORDINATOR',1)")->execute();
$coordUid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active, is_superuser) VALUES ('pv_master','M','MASTER_ADMIN',1,1)")->execute();
$mUid = (int)$pdo->lastInsertId();

$_SESSION['uid'] = $insUid; current_user(true); ua(true);
t_ok($gate() === false, 'an inspector does not see profitability');
$_SESSION['uid'] = $coordUid; current_user(true); ua(true);
t_ok($gate() === false, 'a coordinator does not see profitability');
$_SESSION['uid'] = $mUid; current_user(true); ua(true);
t_ok($gate() === true, 'a master admin sees profitability');

unset($_SESSION['uid']); current_user(true); ua(true);
