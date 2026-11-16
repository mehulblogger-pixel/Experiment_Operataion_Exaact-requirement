<?php
// R5 — an expense voucher had two holes: (a) reopen had no source-status guard, so
// any coordinator could revert even a PAID voucher to DRAFT and alter settled money;
// (b) approval had no segregation of duties, so one person could both submit and
// approve. Now: a PAID voucher reopens only for a real manager, and the approver
// must differ from both the claimant and the submitter (maker != checker).
//
// Branch batch R5 (16 Nov 2026) moved approval into the claimant's approval chain
// (lib/voucher_approval.php). The same refusals are asked there, for every level,
// and this test now drives the real predicates rather than a copy of them.
t_section('voucher: reopen guard + segregation of duties');

$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
$va  = file_get_contents(__DIR__ . '/../lib/voucher_approval.php');
t_ok(strpos($va, "ensure_column('vouchers', 'submitted_by'") !== false, 'the submitter is recorded for the maker/checker check');
t_ok(strpos($va, "You cannot approve your own expense voucher") !== false,
    'the claimant cannot approve their own voucher');
t_ok(strpos($va, '$me === (int) ($v[\'submitted_by\'] ?? 0)') !== false,
    'the approver must differ from the submitter (maker != checker)');
t_ok(strpos($ops, "if (\$v['status'] === 'PAID') {") !== false && strpos($ops, "if ((\$v['status'] ?? '') === 'PAID') return is_admin_level();") !== false,
    'a PAID voucher reopens only for a manager');

$pdo = db();
$office = (int) (ops_val("SELECT id FROM offices ORDER BY id LIMIT 1") ?: 0);

// An inspector (claimant) with a login, plus two coordinators and a branch manager,
// all in one office. Asha reports to the second coordinator (her N+1).
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active, home_office_id) VALUES ('r5_co','Co','COORDINATOR',1,?)")->execute([$office]);
$coUid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active, home_office_id) VALUES ('r5_co2','Co2','COORDINATOR',1,?)")->execute([$office]);
$co2Uid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active, home_office_id) VALUES ('r5_bm','BM','BRANCH_MANAGER',1,?)")->execute([$office]);
$bmUid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO inspectors (name, home_office_id, reports_to_id) VALUES ('R5 Asha', ?, ?)")->execute([$office, $co2Uid]);
$insId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, inspector_id, is_active, home_office_id) VALUES ('r5_insp','Asha','INSPECTOR',?,1,?)")->execute([$insId, $office]);
$inspUid = (int)$pdo->lastInsertId();

// The predicates the handler enforces — the real ones.
$canApprove = function($v) { return vappr_can_act($v)['ok']; };
$canReopen  = function($v) use ($insId, $office) { return can_reopen_voucher($v + ['inspector_id' => $insId, 'office_id' => $office]); };

// --- Segregation of duties on approve ----------------------------------------
$vSubByCo = ['id'=>0, 'inspector_id'=>$insId, 'office_id'=>$office, 'status'=>'SUBMITTED', 'submitted_by'=>(string)$coUid, 'total'=>100];

$_SESSION['uid'] = $coUid; current_user(true); ua(true);
t_ok($canApprove($vSubByCo) === false, 'the coordinator who submitted cannot also approve it (maker != checker)');

$_SESSION['uid'] = $co2Uid; current_user(true); ua(true);
t_ok($canApprove($vSubByCo) === true, 'a different coordinator (the claimant\'s N+1) can approve it');

// The submitter is barred even when they are the claimant's own N+1.
$vSubByN1 = ['id'=>0, 'inspector_id'=>$insId, 'office_id'=>$office, 'status'=>'SUBMITTED', 'submitted_by'=>(string)$co2Uid, 'total'=>100];
t_ok($canApprove($vSubByN1) === false, 'the N+1 who submitted it cannot approve it either');

// The claimant still cannot self-approve.
$vSubBySelf = ['id'=>0, 'inspector_id'=>$insId, 'office_id'=>$office, 'status'=>'SUBMITTED', 'submitted_by'=>(string)$inspUid, 'total'=>100];
$_SESSION['uid'] = $inspUid; current_user(true); ua(true);
t_ok($canApprove($vSubBySelf) === false, 'the claimant cannot approve their own voucher');

// A legacy voucher with no recorded submitter can still be approved (graceful).
$vLegacy = ['id'=>0, 'inspector_id'=>$insId, 'office_id'=>$office, 'status'=>'SUBMITTED', 'submitted_by'=>'0', 'total'=>100];
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
