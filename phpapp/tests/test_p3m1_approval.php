<?php
// ============================================================================
//  PHASE 3 · M1 — APPROVAL FOUNDATION & HIRING REQUEST APPROVAL WORKFLOW
//
//  M1 does not build an approval engine. One already existed (Phase 6) and it
//  was already entity-agnostic — recruit_approval_requests carries an entity and
//  an entity_id. M1 teaches it one new entity, connects the Hiring Request to
//  it, and closes the holes the audit found in the path it now depends on.
//
//  What these tests hold in place:
//    · DRAFT -> SUBMITTED -> UNDER_REVIEW -> APPROVED / REJECTED, and nothing
//      recruits before APPROVED.
//    · The decision is a CAPABILITY question with an ENTITLEMENT question in
//      front of it, a BRANCH question beside it, and a SEGREGATION rule over it.
//    · A person who raised a request cannot approve it even when they hold the
//      approver role — refused at the engine, not hidden on a screen.
//    · A master on a workspace that has not bought recruitment is refused.
// ============================================================================

t_section('Phase 3 · M1 — hiring request approval');

$pdo = db();
hreq_migrate(); appr_migrate();
$mine = ['h' => [], 'u' => [], 'o' => [], 'rule' => [], 'r' => []];
$origSess = $_SESSION;

foreach ([[961, 'M1 Branch A'], [962, 'M1 Branch B']] as $o) {
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); $mine['o'][] = $o[0]; } catch (Throwable $e) {}
}
$mk = function ($un, $role, $super, $office, $perms) use ($pdo, &$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions)
                   VALUES (?,?,?,1,?,?,'',?)")->execute([$un, 'M1', $role, $super ? 1 : 0, $office, $perms]);
    $id = (int) $pdo->lastInsertId(); $mine['u'][] = $id; return $id;
};
$act = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };

$HR = 'mod.hiring.view,mod.hiring.edit';
// The requestor deliberately ALSO holds the approver's role, so that the only
// thing standing between them and approving their own request is segregation.
$uReq   = $mk('m1_req',   'BRANCH_MANAGER', 0, 961, $HR);
$uAppr  = $mk('m1_appr',  'BRANCH_MANAGER', 0, 961, $HR);
$uOther = $mk('m1_other', 'BRANCH_MANAGER', 0, 962, $HR);   // foreign branch
$uMast  = $mk('m1_master','ADMIN',          1, 961, '');
$uNone  = $mk('m1_none',  'INSPECTOR',      0, 961, 'mod.hiring.view');  // may read, not raise

// Phase 3 · M3 §25 — approval POLICY (matrix, SLA, escalation) may only be written
// by an administrator, and that is now asked at the write rather than only by the
// screen. These fixtures used to configure policy with nobody signed in, which the
// product has never permitted; they now do what the comment always claimed and act
// as the administrator. The acting user is restored afterwards, so every other
// assertion below still runs as whoever it was written for.
$cfg = function (callable $fn) use (&$uMast) {
    $prev = $_SESSION['uid'] ?? null;
    $_SESSION['uid'] = $uMast; current_user(true); ua(true);
    try { return $fn(); }
    finally {
        if ($prev === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prev;
        current_user(true); ua(true);
    }
};

// ---------------------------------------------------------------------------
//  0 · What the audit found already existed
// ---------------------------------------------------------------------------
t_section('M1.0 · reuse, not rebuild');
t_ok(t_table_exists('recruit_approval_requests') && t_table_exists('recruit_approval_steps'),
     'M1.0 · the Phase-6 approval engine is present and is what M1 uses');
t_ok(!t_table_exists('hiring_request_approvals') && !t_table_exists('hreq_approvals'),
     'M1.0 · M1 created no second approval table');
t_ok(isset(APPR_ENTITIES['HIRING_REQUEST']),
     'M1.0 · the engine learned one new entity — that was the whole extension');
$cols = t_columns('hiring_requests');
foreach (['approval_required','approval_ref','decided_by','decided_at','decision_note','snapshot_json','submitted_at'] as $c)
    t_ok(in_array($c, $cols, true), 'M1.0 · hiring_requests already carried ' . $c . ' — no new column');
t_ok(in_array('UNDER_REVIEW', array_keys(HREQ_STATUS), true),
     'M1.0 · "approval pending" is M4\'s existing UNDER_REVIEW — no second status vocabulary');

// A rule an administrator would configure. Approver = the BRANCH_MANAGER role.
$ruleId = $cfg(fn() => appr_rule_save(0, ['name' => 'M1 hiring requests', 'entity' => 'HIRING_REQUEST', 'code' => 'M1HR']));
$mine['rule'][] = $ruleId;
$cfg(fn() => appr_level_save(['rule_id' => $ruleId, 'seq' => 1, 'label' => 'Branch manager', 'approver_role' => 'BRANCH_MANAGER', 'sla_days' => 2]));
t_ok($ruleId > 0 && count(appr_levels($ruleId)) === 1, 'M1.0 · a one-level chain is configured on the existing screen\'s tables');

$form = fn(array $x = []) => array_merge([
    'job_title' => 'M1 QA Inspector', 'quantity' => 2, 'office_id' => 961,
    'priority' => 'NORMAL', 'approval_required' => 1,
], $x);

// ---------------------------------------------------------------------------
//  1 · Draft
// ---------------------------------------------------------------------------
t_section('M1.1 · draft');
$act($uReq);
[$ok, $msg, $h1] = hreq_save(0, $form());
t_ok($ok, 'M1.1 · an authorized requestor may save a draft');
if ($ok) $mine['h'][] = $h1;
t_eq(hreq_get($h1)['status'], 'DRAFT', 'M1.1 · it is a DRAFT');
t_ok(!hreq_is_executable($h1), 'M1.1 · a draft is not executable');
t_ok(!hreq_to_requisition($h1, 1)[0], 'M1.1 · and cannot become a requisition');
$act($uNone);
t_ok(!hreq_save(0, $form())[0], 'M1.1 · a user without the create capability cannot raise one');

// ---------------------------------------------------------------------------
//  2 · Submission starts the chain
// ---------------------------------------------------------------------------
t_section('M1.2 · submission');
$act($uReq);
[$sOk, $sMsg] = hreq_submit($h1);
t_ok($sOk, 'M1.2 · submit is accepted: ' . $sMsg);
$r1 = hreq_get($h1);
t_eq($r1['status'], 'UNDER_REVIEW', 'M1.2 · a matching rule puts it UNDER_REVIEW, not SUBMITTED');
$ap = hreq_approval($h1);
t_ok($ap && strtoupper($ap['status']) === 'PENDING', 'M1.2 · a chain is open against it in the existing engine');
t_eq((string) $r1['approval_ref'], (string) $ap['id'], 'M1.2 · approval_ref records which chain');
t_ok(trim((string) $r1['snapshot_json']) !== '', 'M1.2 · the approval snapshot was taken at submission');
t_ok(!empty($r1['submitted_at']), 'M1.2 · and the submission time recorded');
t_eq((int) $r1['requested_by_id'], $uReq, 'M1.2 · the original requestor is not overwritten by submitting');
t_ok(!hreq_is_executable($h1), 'M1.2 · a request awaiting approval is not executable');
t_ok(!hreq_to_requisition($h1, 1)[0], 'M1.2 · and cannot become a requisition');

// ---------------------------------------------------------------------------
//  3 · Self-approval — the rule that matters most
// ---------------------------------------------------------------------------
t_section('M1.3 · segregation of duties');
$step = appr_current_step($ap);
t_ok($step && (string) $step['approver_role'] === 'BRANCH_MANAGER', 'M1.3 · the pending step is held by a role, not a person');
$act($uReq);
t_ok(appr_can_act($step), 'M1.3 · the requestor DOES hold the approver role — so only segregation stands in the way');
t_ok(appr_guard($ap) !== '', 'M1.3 · the guard refuses them: ' . appr_guard($ap));
[$aOk, $aMsg] = appr_act((int) $step['id'], 'approve');
t_ok(!$aOk, 'M1.3 · and the engine refuses the decision itself, not the button: ' . $aMsg);
t_eq(hreq_get($h1)['status'], 'UNDER_REVIEW', 'M1.3 · nothing was written');
t_eq(ops_one("SELECT status FROM recruit_approval_steps WHERE id=?", [(int) $step['id']])['status'], 'PENDING',
     'M1.3 · and the step is still pending');

// ---------------------------------------------------------------------------
//  4 · Branch scope, at the decision
// ---------------------------------------------------------------------------
t_section('M1.4 · branch scope');
$act($uOther);
t_ok(appr_can_act($step), 'M1.4 · the foreign-branch user also holds the approver role');
t_ok(appr_guard($ap) !== '', 'M1.4 · but the guard refuses on scope: ' . appr_guard($ap));
t_ok(!appr_act((int) $step['id'], 'approve')[0], 'M1.4 · the decision is refused');
t_eq(hreq_get($h1)['status'], 'UNDER_REVIEW', 'M1.4 · nothing was written');

// ---------------------------------------------------------------------------
//  5 · Entitlement, in front of everything
// ---------------------------------------------------------------------------
t_section('M1.5 · entitlement');
$offWas = setting_get('modules_off', '');
$act($uAppr);
t_eq(appr_guard($ap), '', 'M1.5 · with HR on, the rightful approver is allowed');
setting_set('modules_off', 'hr'); licence_disabled(true); ua(true);
t_ok(appr_guard($ap) !== '', 'M1.5 · with HR off the decision is refused: ' . appr_guard($ap));
t_ok(!appr_act((int) $step['id'], 'approve')[0], 'M1.5 · …at the engine, not at a screen');
$act($uMast);
t_ok(is_master(), 'M1.5 · now as a master');
t_ok(!appr_can_act($step), 'M1.5 · a master does NOT walk past an unbought module (M1 finding B)');
t_ok(!appr_act((int) $step['id'], 'approve')[0], 'M1.5 · so the master decision is refused too');
t_eq(hreq_get($h1)['status'], 'UNDER_REVIEW', 'M1.5 · nothing was written');
setting_set('modules_off', $offWas); licence_disabled(true); ua(true);
$act($uMast);
t_ok(appr_can_act($step), 'M1.5 · with the module bought, the master may act again');

// The inbox route itself (M1 finding A).
$srcAppr = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/recruit_approval.php'));
$inbox = substr($srcAppr, strpos($srcAppr, 'function ops_my_approvals('), 900);
t_ok(strpos($inbox, 'licence_blocks(') !== false,
     'M1.5 · /my-approvals now asks the licence question it never asked before');

// ---------------------------------------------------------------------------
//  6 · Approval
// ---------------------------------------------------------------------------
t_section('M1.6 · approval');
$act($uAppr);
[$okA, $msgA] = appr_act((int) $step['id'], 'approve', 'Approved manpower requirement');
t_ok($okA, 'M1.6 · the rightful approver approves: ' . $msgA);
$r1 = hreq_get($h1);
t_eq($r1['status'], 'APPROVED', 'M1.6 · the hiring request is APPROVED through the chain callback');
t_ok(trim((string) $r1['decided_by']) !== '', 'M1.6 · who decided it is recorded');
t_ok(trim((string) $r1['decided_at']) !== '', 'M1.6 · and when');
t_eq(strtoupper(hreq_approval($h1)['status']), 'APPROVED', 'M1.6 · the chain is closed as approved');
t_ok(hreq_is_executable($h1), 'M1.6 · and only NOW is it executable');
$hist = hreq_approval_steps($h1);
t_ok(count($hist) === 1 && strtoupper($hist[0]['status']) === 'APPROVED' && $hist[0]['remarks'] !== '',
     'M1.6 · the approval history keeps the decision, the actor and the reason');

// The conversion boundary, now that it is approved.
[$cOk, $cMsg, $rid] = hreq_to_requisition($h1, 1);
t_ok($cOk, 'M1.6 · an approved request may become a requisition: ' . $cMsg);
if ($cOk) $mine['r'][] = $rid;

// ---------------------------------------------------------------------------
//  7 · Rejection, and the states that must never recruit
// ---------------------------------------------------------------------------
t_section('M1.7 · rejection and the boundary');
$act($uReq);
[$ok2, , $h2] = hreq_save(0, $form(['job_title' => 'M1 to be rejected']));
if ($ok2) $mine['h'][] = $h2;
hreq_submit($h2);
$ap2 = hreq_approval($h2); $step2 = appr_current_step($ap2);
$act($uAppr);
t_ok(appr_act((int) $step2['id'], 'reject', 'Not budgeted')[0], 'M1.7 · the approver rejects');
t_eq(hreq_get($h2)['status'], 'REJECTED', 'M1.7 · the hiring request is REJECTED');
t_ok(!hreq_is_executable($h2), 'M1.7 · a rejected request is not executable');
t_ok(!hreq_to_requisition($h2, 1)[0], 'M1.7 · and cannot become a requisition');
// Re-deciding a closed request.
t_ok(!appr_act((int) $step2['id'], 'approve')[0], 'M1.7 · the same step cannot be acted on twice (replay refused)');
t_ok(!hreq_apply_decision($h2, 'APPROVED', 'x')[0], 'M1.7 · and the one writer refuses to approve a rejected request');
t_eq(hreq_get($h2)['status'], 'REJECTED', 'M1.7 · it stays rejected');
// Cancelled.
$act($uReq);
[$ok3, , $h3] = hreq_save(0, $form(['job_title' => 'M1 to be cancelled']));
if ($ok3) $mine['h'][] = $h3;
hreq_cancel($h3, 'no longer needed');
t_ok(!hreq_to_requisition($h3, 1)[0], 'M1.7 · a cancelled request cannot become a requisition');
t_ok(!hreq_apply_decision($h3, 'APPROVED', 'x')[0], 'M1.7 · nor can it be approved into life');

// ---------------------------------------------------------------------------
//  8 · The direct decision and the chain cannot disagree
// ---------------------------------------------------------------------------
t_section('M1.8 · one decision, not two');
$act($uReq);
[$ok4, , $h4] = hreq_save(0, $form(['job_title' => 'M1 chain running']));
if ($ok4) $mine['h'][] = $h4;
hreq_submit($h4);
t_eq(hreq_get($h4)['status'], 'UNDER_REVIEW', 'M1.8 · a chain is running');
$act($uMast);
[$dOk, $dMsg] = hreq_decide($h4, true);
t_ok(!$dOk, 'M1.8 · the direct decision stands aside while its approvers hold it: ' . $dMsg);
t_eq(hreq_get($h4)['status'], 'UNDER_REVIEW', 'M1.8 · nothing was written');

// With no rule matching, M4's direct path is untouched.
$cfg(fn() => appr_rule_set_active($ruleId, false));
$act($uReq);
[$ok5, , $h5] = hreq_save(0, $form(['job_title' => 'M1 no rule configured']));
if ($ok5) $mine['h'][] = $h5;
hreq_submit($h5);
t_eq(hreq_get($h5)['status'], 'SUBMITTED', 'M1.8 · with no rule configured the request stays SUBMITTED, exactly as M4 behaved');
$act($uMast);
t_ok(hreq_decide($h5, true)[0], 'M1.8 · and is decided directly, as before');
t_eq(hreq_get($h5)['status'], 'APPROVED', 'M1.8 · reaching the same state through the other door');
$cfg(fn() => appr_rule_set_active($ruleId, true));

// ---------------------------------------------------------------------------
//  9 · Audit
// ---------------------------------------------------------------------------
t_section('M1.9 · audit trail');
// M1 FINDING G. The hiring-request layer called activity_log() — a function
// that does not exist anywhere in this application — so every audit call was a
// silent no-op behind function_exists(), and M4's test asserted only that the
// CALL was present. These assertions read the rows back instead.
t_ok(t_table_exists('activities'), 'M1.9 · the real audit spine is the activities table');
t_ok(isset(ACT_ENTITIES['HIRING_REQUEST']),
     'M1.9 · and the hiring request is registered on it, so the timeline can label and link it');
$trail = ops_all("SELECT subject, outcome FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=? ORDER BY id", [$h1]);
$notes = implode(' | ', array_map(fn($k) => (string) $k['subject'], $trail));
t_ok(count($trail) >= 3, 'M1.9 · raising, submitting and deciding all left a row (' . count($trail) . '): ' . $notes);
t_ok(strpos($notes, 'raised') !== false,        'M1.9 · the request being raised is audited');
t_ok(strpos($notes, 'Submitted') !== false,     'M1.9 · the submission is audited');
t_ok(strpos($notes, 'approvers') !== false,     'M1.9 · being sent to its approvers is audited');
t_ok(strpos($notes, 'APPROVED via CHAIN') !== false,
     'M1.9 · the decision is audited WITH the route it arrived by');
t_ok(in_array('APPROVED', array_map(fn($k) => (string) $k['outcome'], $trail), true),
     'M1.9 · and the outcome is recorded as an outcome, not only as prose');
// The rejection and the cancellation too.
$tr2 = ops_all("SELECT subject FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?", [$h2]);
t_ok(count(array_filter($tr2, fn($x) => strpos((string) $x['subject'], 'REJECTED') !== false)) === 1,
     'M1.9 · the rejection is audited');
$tr3 = ops_all("SELECT subject FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?", [$h3]);
t_ok(count(array_filter($tr3, fn($x) => strpos((string) $x['subject'], 'Cancelled') !== false)) === 1,
     'M1.9 · and so is the cancellation');

// ---------------------------------------------------------------------------
//  10 · Write-path audit of the approval layer
// ---------------------------------------------------------------------------
t_section('M1.10 · every approval write');
$guard = substr($srcAppr, strpos($srcAppr, 'function appr_act('), 1400);
$firstWrite = strpos($guard, 'db()->prepare');
t_ok(strpos(substr($guard, 0, $firstWrite), 'appr_guard($req)') !== false,
     'M1.10 · appr_act asks the guard before its first write');
t_ok(strpos(substr($guard, 0, $firstWrite), 'appr_can_act($step)') !== false,
     'M1.10 · …and the approver question too');
$g = substr($srcAppr, strpos($srcAppr, 'function appr_guard('), 1400);
foreach ([['licence_blocks(', 'entitlement'], ['hreq_in_scope(', 'branch scope'], ['hreq_segregation_blocks(', 'segregation']] as $need)
    t_ok(strpos($g, $need[0]) !== false, 'M1.10 · the guard asks ' . $need[1]);
t_ok(strpos($g, 'licence_blocks(') < strpos($g, 'hreq_in_scope('),
     'M1.10 · and asks entitlement FIRST — the order is the security property');

// ---------------------------------------------------------------------------
//  11 · M1 CORRECTION — cancelling a request closes its approval chain
//
//  The adversarial audit found this: hreq_cancel() knew nothing about chains, so
//  a cancelled request left a live step in the approver's inbox, approving it
//  reported "Approved — fully cleared", and the history kept an APPROVED step
//  against a CANCELLED request. hreq_is_executable() held throughout, so nothing
//  could recruit — but the record lied, which in an approval system is the part
//  that matters.
// ---------------------------------------------------------------------------
t_section('M1.11 · cancelling closes the chain');

$act($uReq);
[$okC, , $hC] = hreq_save(0, $form(['job_title' => 'M1c to be cancelled mid-approval']));
if ($okC) $mine['h'][] = $hC;
hreq_submit($hC);
$apC = hreq_approval($hC); $stepC = appr_current_step($apC);
t_eq(strtoupper($apC['status']), 'PENDING', 'M1.11 · submitted — the chain is PENDING');
$act($uAppr);
t_eq(count(array_filter(appr_inbox(), fn($x) => (int) $x['id'] === (int) $stepC['id'])), 1,
     'M1.11 · and the step is in the approver\'s actionable inbox');

$act($uReq);
hreq_cancel($hC, 'no longer needed');
$apC2 = hreq_approval($hC);
t_eq(hreq_get($hC)['status'], 'CANCELLED', 'M1.11 · the request is cancelled');
t_ok(strtoupper($apC2['status']) !== 'PENDING', 'M1.11 · the chain is no longer open (' . $apC2['status'] . ')');
t_eq(appr_open('HIRING_REQUEST', $hC), null, 'M1.11 · …so nothing reads it as an open chain any more');

$act($uAppr);
t_eq(count(array_filter(appr_inbox(), fn($x) => (int) $x['id'] === (int) $stepC['id'])), 0,
     'M1.11 · it has left the approver\'s actionable inbox');
[$okAct, $msgAct] = appr_act((int) $stepC['id'], 'approve', 'trying anyway');
t_ok(!$okAct, 'M1.11 · and the approver cannot approve it: ' . $msgAct);
$stepAfter = ops_one("SELECT status FROM recruit_approval_steps WHERE id=?", [(int) $stepC['id']]);
t_ok(strtoupper($stepAfter['status']) !== 'APPROVED',
     'M1.11 · NO approved step exists against a cancelled request (' . $stepAfter['status'] . ')');
// …and it says so in the history rather than sitting there reading "Awaiting"
// for ever. Mutation C2 survived until this assertion existed.
t_eq(strtoupper($stepAfter['status']), 'CANCELLED',
     'M1.11 · the step itself records that it was withdrawn, not left pending');
t_eq(hreq_get($hC)['status'], 'CANCELLED', 'M1.11 · the request is still cancelled');
t_ok(!hreq_is_executable($hC), 'M1.11 · and still not executable');
t_ok(!hreq_to_requisition($hC, 1)[0], 'M1.11 · so it can never become a requisition');
// The SLA cron must not chase a withdrawn approval either.
$pend = (int) ops_val("SELECT COUNT(*) FROM recruit_approval_steps s JOIN recruit_approval_requests r ON r.id=s.request_id
                       WHERE r.entity='HIRING_REQUEST' AND r.entity_id=? AND r.status='PENDING' AND s.status='PENDING'", [$hC]);
t_eq($pend, 0, 'M1.11 · and the SLA reminders have nothing left to chase');

// ---------------------------------------------------------------------------
//  12 · A failed callback is a failure — defence in depth
//
//  Fix A means the natural route can no longer produce a failed callback, so the
//  guarantee is proved by FORCING one: the request is cancelled BEHIND the
//  engine's back (straight SQL, no hreq_cancel), leaving the chain live. The one
//  writer then refuses, and that refusal must reach the approver.
// ---------------------------------------------------------------------------
t_section('M1.12 · a failed callback cannot read as success');

$act($uReq);
[$okD, , $hD] = hreq_save(0, $form(['job_title' => 'M1c forced callback failure']));
if ($okD) $mine['h'][] = $hD;
hreq_submit($hD);
$apD = hreq_approval($hD); $stepD = appr_current_step($apD);
$pdo->prepare("UPDATE hiring_requests SET status='CANCELLED' WHERE id=?")->execute([$hD]);   // behind the engine's back
t_eq(hreq_get($hD)['status'], 'CANCELLED', 'M1.12 · the request was cancelled without the chain being told');
t_ok(appr_open('HIRING_REQUEST', $hD) !== null, 'M1.12 · so the chain is still live — the case Fix A normally prevents');

$act($uAppr);
[$okE, $msgE] = appr_act((int) $stepD['id'], 'approve', 'should not succeed');
t_ok(!$okE, 'M1.12 · the approver is told it FAILED, not that it was approved: ' . $msgE);
t_ok(stripos($msgE, 'approved — fully cleared') === false, 'M1.12 · …and specifically not "Approved — fully cleared"');
$sD = ops_one("SELECT status FROM recruit_approval_steps WHERE id=?", [(int) $stepD['id']]);
$rD = ops_one("SELECT status FROM recruit_approval_requests WHERE id=?", [(int) $apD['id']]);
t_eq(strtoupper($sD['status']), 'PENDING', 'M1.12 · the step was put back — no approval is left behind');
t_eq(strtoupper($rD['status']), 'PENDING', 'M1.12 · and so was the chain');
t_eq(hreq_get($hD)['status'], 'CANCELLED', 'M1.12 · the request is untouched');
t_ok(!hreq_is_executable($hD), 'M1.12 · and not executable');

// The REJECT path carries the same discard risk and needs its own proof —
// mutation C5 survived until this existed, because only approve was exercised.
$act($uReq);
[$okF, , $hF] = hreq_save(0, $form(['job_title' => 'M1c forced callback failure on reject']));
if ($okF) $mine['h'][] = $hF;
hreq_submit($hF);
$apF = hreq_approval($hF); $stepF = appr_current_step($apF);
$pdo->prepare("UPDATE hiring_requests SET status='CANCELLED' WHERE id=?")->execute([$hF]);
$act($uAppr);
[$okG, $msgG] = appr_act((int) $stepF['id'], 'reject', 'should not succeed either');
t_ok(!$okG, 'M1.12 · a REJECT whose callback fails is also reported as failure: ' . $msgG);
t_ok(stripos($msgG, 'rejected.') === false, 'M1.12 · …and not as "Rejected."');
$sF = ops_one("SELECT status FROM recruit_approval_steps WHERE id=?", [(int) $stepF['id']]);
$rF = ops_one("SELECT status FROM recruit_approval_requests WHERE id=?", [(int) $apF['id']]);
t_eq(strtoupper($sF['status']), 'PENDING', 'M1.12 · the rejected step was put back too');
t_eq(strtoupper($rF['status']), 'PENDING', 'M1.12 · and so was its chain');
t_eq(hreq_get($hF)['status'], 'CANCELLED', 'M1.12 · the request is untouched by the failed rejection');

// The other approval consumers keep their original best-effort semantics.
t_eq(appr_callback('OFFER', 999999, 'APPROVED', null), true,
     'M1.12 · an OFFER callback still reports success even when it matches no row — behaviour preserved');
t_eq(appr_callback('REQUISITION', 999999, 'APPROVED', null), true,
     'M1.12 · and so does a REQUISITION callback');
t_eq(appr_callback('SALARY', 999999, 'APPROVED', null), true,
     'M1.12 · and SALARY, which has no branch at all');

// ---------------------------------------------------------------------------
//  Clean up.
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
foreach ($mine['r'] as $id) $pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([$id]);
foreach ($mine['h'] as $id) {
    $pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id IN (SELECT id FROM recruit_approval_requests WHERE entity='HIRING_REQUEST' AND entity_id=?)")->execute([$id]);
    $pdo->prepare("DELETE FROM recruit_approval_requests WHERE entity='HIRING_REQUEST' AND entity_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([$id]);
}
foreach ($mine['rule'] as $id) {
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([$id]);
}
foreach ($mine['u'] as $id) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
foreach ($mine['o'] as $id) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$id]);
t_ok(true, 'M1 fixtures removed');
