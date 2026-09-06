<?php
// Phase 6 — configurable approval matrix + SLA + reminders + escalations.
// Rules are data; the narrowest match wins; a multi-level chain drives approve/
// reject; a completed chain calls back into the entity; the cron tick reminds
// and escalates. Everything additive; nothing hardcoded.
t_section('approvals: matrix, chain, callback, SLA (Phase 6)');

$pdo = db();
appr_migrate();

// --- Configure two rules for OFFER: a broad "Finance dept" rule and a narrower
//     high-value band. The narrowest match must win. -------------------------
$broad = appr_rule_save(0, ['name' => 'Finance offers', 'code' => 'FIN', 'entity' => 'OFFER',
    'applies_department' => 'Finance', 'min_amount' => 0, 'max_amount' => 0, 'sort' => 10]);
appr_level_save(['rule_id' => $broad, 'seq' => 10, 'label' => 'Branch Manager', 'approver_role' => 'BRANCH_MANAGER', 'sla_days' => 2, 'reminder_days' => 1, 'escalate_role' => 'SBU_HEAD']);
appr_level_save(['rule_id' => $broad, 'seq' => 20, 'label' => 'Unit Head', 'approver_role' => 'SBU_HEAD', 'sla_days' => 3, 'reminder_days' => 1, 'escalate_role' => 'MASTER_ADMIN']);

$highval = appr_rule_save(0, ['name' => 'High-value Finance offers', 'code' => 'FINHI', 'entity' => 'OFFER',
    'applies_department' => 'Finance', 'min_amount' => 1000000, 'max_amount' => 0, 'sort' => 5]);
appr_level_save(['rule_id' => $highval, 'seq' => 10, 'label' => 'Director', 'approver_role' => 'BUSINESS_DIRECTOR', 'sla_days' => 2, 'reminder_days' => 1, 'escalate_role' => 'MASTER_ADMIN']);

t_eq(count(appr_levels($broad)), 2, 'the broad rule has a two-level chain');

// --- Matching: narrowest wins, disqualify on mismatch ----------------------
$m1 = appr_match('OFFER', ['department' => 'Finance', 'amount' => 500000]);
t_eq((int)($m1['id'] ?? 0), (int)$broad, 'a mid-value Finance offer matches the broad rule');
$m2 = appr_match('OFFER', ['department' => 'Finance', 'amount' => 1500000]);
t_eq((int)($m2['id'] ?? 0), (int)$highval, 'a high-value Finance offer matches the narrower band rule');
$m3 = appr_match('OFFER', ['department' => 'Marketing', 'amount' => 500000]);
t_ok($m3 === null, 'an offer in another department matches no rule (no accidental catch-all)');

// --- Start a chain on a real offer -----------------------------------------
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,designation,stage,created_at) VALUES ('CAN-AP','Meera','Nair','Accountant','OFFERED',?)")->execute([date('c')]);
$cid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO job_offers (candidate_id,ctc,status,created_at) VALUES (?,?, 'PENDING_APPROVAL', ?)")->execute([$cid, 500000, date('c')]);
$oid = (int)$pdo->lastInsertId();

[$started, $reqId] = appr_start('OFFER', $oid, ['department' => 'Finance', 'amount' => 500000], 'Offer — Meera Nair', 500000);
t_ok($started && $reqId > 0, 'an approval request is started when a rule matches');
t_eq(count(appr_steps($reqId)), 2, 'the request has one step per configured level');
$req = appr_request($reqId);
t_eq((int)$req['current_seq'], 10, 'the request opens at the first level');

// A second start on the same entity is idempotent (no duplicate chain).
[$again, $reqId2] = appr_start('OFFER', $oid, ['department' => 'Finance', 'amount' => 500000], 'dup', 500000);
t_eq((int)$reqId2, (int)$reqId, 'starting again returns the same open request (no duplicate)');

// --- Approver inbox + can-act gate -----------------------------------------
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,email) VALUES ('ap_bm','BM','BRANCH_MANAGER',1,'bm@demo.test')")->execute();
$bmId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,email) VALUES ('ap_sbu','SBU','SBU_HEAD',1,'sbu@demo.test')")->execute();
$sbuId = (int)$pdo->lastInsertId();

$_SESSION['uid'] = $bmId; current_user(true); ua(true);
$inbox = appr_inbox();
t_eq(count($inbox), 1, 'the branch manager sees the pending step in their inbox');
$step1 = $inbox[0];
t_ok(appr_can_act($step1), 'the branch manager can act on their own step');

$_SESSION['uid'] = $sbuId; current_user(true); ua(true);
t_eq(count(appr_inbox()), 0, 'the unit head sees nothing while level 1 is pending');

// --- Approve level 1 → advances to level 2 ---------------------------------
$_SESSION['uid'] = $bmId; current_user(true); ua(true);
[$ok1, $msg1] = appr_act((int)$step1['id'], 'approve', 'Looks good');
t_ok($ok1, 'the branch manager approves level 1');
t_eq((int)appr_request($reqId)['current_seq'], 20, 'the request advances to level 2');
t_eq(count(appr_inbox()), 0, 'the branch manager no longer has the item after acting');

// A user who is not the level-2 approver cannot act on it.
$step2 = appr_current_step(appr_request($reqId));
t_ok(!appr_can_act($step2), 'the branch manager cannot act on the unit-head step');

// --- Approve level 2 → chain completes → callback approves the offer -------
$_SESSION['uid'] = $sbuId; current_user(true); ua(true);
t_eq(count(appr_inbox()), 1, 'the unit head now sees the item');
[$ok2, $msg2] = appr_act((int)$step2['id'], 'approve', 'Approved');
t_ok($ok2, 'the unit head approves level 2');
t_eq(appr_request($reqId)['status'], 'APPROVED', 'the request is fully approved');
t_eq(offer_get($oid)['status'], 'APPROVED', 'the offer is approved via the callback');

// --- Reject path on a fresh request ----------------------------------------
$pdo->prepare("INSERT INTO job_offers (candidate_id,ctc,status,created_at) VALUES (?,?, 'PENDING_APPROVAL', ?)")->execute([$cid, 600000, date('c')]);
$oid2 = (int)$pdo->lastInsertId();
[$s2, $rq2] = appr_start('OFFER', $oid2, ['department' => 'Finance', 'amount' => 600000], 'Offer 2', 600000);
$_SESSION['uid'] = $bmId; current_user(true); ua(true);
$st = appr_current_step(appr_request($rq2));
[$okr, $msgr] = appr_act((int)$st['id'], 'reject', 'Budget not cleared');
t_ok($okr, 'a rejection is accepted');
t_eq(appr_request($rq2)['status'], 'REJECTED', 'the request is rejected');
t_eq(offer_get($oid2)['status'], 'DRAFT', 'a rejected offer falls back to DRAFT (never issuable)');

// --- SLA tick: reminders + escalation --------------------------------------
$pdo->prepare("INSERT INTO job_offers (candidate_id,ctc,status,created_at) VALUES (?,?, 'PENDING_APPROVAL', ?)")->execute([$cid, 700000, date('c')]);
$oid3 = (int)$pdo->lastInsertId();
[$s3, $rq3] = appr_start('OFFER', $oid3, ['department' => 'Finance', 'amount' => 700000], 'Offer 3', 700000);
$st3 = appr_current_step(appr_request($rq3));
// Backdate this step so both the reminder and the SLA are due.
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, reminder_at=? WHERE id=?")
    ->execute([date('c', time() - 86400), date('c', time() - 3600), (int)$st3['id']]);
$acted = appr_tick();
t_ok($acted >= 1, 'the cron tick acts on the overdue step');
$after = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int)$st3['id']]);
t_eq((int)$after['escalated'], 1, 'the overdue step is marked escalated exactly once');
// A second tick does not re-escalate.
$acted2 = appr_tick();
$after2 = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int)$st3['id']]);
t_eq((int)$after2['escalated'], 1, 'a second tick does not re-escalate the same step');

// --- Clean up: remove the rules/requests we created and the session so the
//     shared test DB is left as we found it. ---------------------------------
foreach ([$reqId, $rq2, $rq3] as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_requests WHERE id=?")->execute([(int)$r]);
}
foreach ([$broad, $highval] as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([(int)$r]);
}
unset($_SESSION['uid']); current_user(true); ua(true);
