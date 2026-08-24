<?php
// R5 — an expense voucher had two holes: (a) reopen had no source-status guard, so
// any coordinator could revert even a PAID voucher to DRAFT and alter settled money;
// (b) approval had no segregation of duties, so one person could both submit and
// approve. Now: a PAID voucher reopens only for a real manager, and the approver
// must differ from both the claimant and the submitter (maker != checker).
t_section('voucher: reopen guard + segregation of duties');

$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "ensure_column('vouchers', 'submitted_by'") !== false, 'the submitter is recorded for the maker/checker check');
t_ok(strpos($ops, "ops_require(!voucher_owner_is_me(\$v), 'You cannot approve your own expense voucher") !== false,
    'the claimant cannot approve their own voucher');
t_ok(strpos($ops, '$submittedBy === 0 || $submittedBy !== $meUid') !== false,
    'the approver must differ from the submitter (maker != checker)');
t_ok(strpos($ops, "if (\$v['status'] === 'PAID') {") !== false && strpos($ops, "ops_require(is_admin_level(), 'A paid voucher can only be reopened by a manager.')") !== false,
    'a PAID voucher reopens only for a manager');

$pdo = db();

// An inspector (claimant) with a login, plus a coordinator and a branch manager.
$pdo->prepare("INSERT INTO inspectors (name) VALUES ('R5 Asha')")->execute();
$insId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, inspector_id, is_active) VALUES ('r5_insp','Asha','INSPECTOR',?,1)")->execute([$insId]);
$inspUid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('r5_co','Co','COORDINATOR',1)")->execute();
$coUid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('r5_co2','Co2','COORDINATOR',1)")->execute();
$co2Uid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active) VALUES ('r5_bm','BM','BRANCH_MANAGER',1)")->execute();
$bmUid = (int)$pdo->lastInsertId();

// The predicates exactly as the handler enforces them.
$canApprove = function($v) {
    $meUid = (int)(current_user()['id'] ?? 0);
    if (!(is_coordinator_level() && $v['status'] === 'SUBMITTED')) return false;
    if (voucher_owner_is_me($v)) return false;
    $sb = (int)($v['submitted_by'] ?? 0);
    return $sb === 0 || $sb !== $meUid;
};
$canReopen = function($v) {
    if ($v['status'] === 'PAID') return is_admin_level();
    return is_coordinator_level() && in_array($v['status'], ['SUBMITTED','APPROVED'], true);
};

// --- Segregation of duties on approve ----------------------------------------
$vSubByCo = ['inspector_id'=>$insId, 'status'=>'SUBMITTED', 'submitted_by'=>(string)$coUid];

$_SESSION['uid'] = $coUid; current_user(true); ua(true);
t_ok($canApprove($vSubByCo) === false, 'the coordinator who submitted cannot also approve it (maker != checker)');

$_SESSION['uid'] = $co2Uid; current_user(true); ua(true);
t_ok($canApprove($vSubByCo) === true, 'a different coordinator can approve it');

// The claimant (if they somehow held coordinator rights) still cannot self-approve.
$vSubBySelf = ['inspector_id'=>$insId, 'status'=>'SUBMITTED', 'submitted_by'=>(string)$inspUid];
$_SESSION['uid'] = $inspUid; current_user(true); ua(true);
t_ok($canApprove($vSubBySelf) === false, 'the claimant cannot approve their own voucher');

// A legacy voucher with no recorded submitter can still be approved (graceful).
$vLegacy = ['inspector_id'=>$insId, 'status'=>'SUBMITTED', 'submitted_by'=>'0'];
$_SESSION['uid'] = $co2Uid; current_user(true); ua(true);
t_ok($canApprove($vLegacy) === true, 'a legacy voucher with no recorded submitter is still approvable');

// --- Reopen guard by status/role ---------------------------------------------
$paid = ['status'=>'PAID']; $approved = ['status'=>'APPROVED']; $submitted = ['status'=>'SUBMITTED'];

$_SESSION['uid'] = $coUid; current_user(true); ua(true);
t_ok($canReopen($paid) === false, 'a coordinator cannot reopen a PAID voucher');
t_ok($canReopen($approved) === true, 'a coordinator can reopen an APPROVED voucher');
t_ok($canReopen($submitted) === true, 'a coordinator can reopen a SUBMITTED voucher');

$_SESSION['uid'] = $bmUid; current_user(true); ua(true);
t_ok($canReopen($paid) === true, 'a branch manager can reopen a PAID voucher');

unset($_SESSION['uid']); current_user(true); ua(true);
