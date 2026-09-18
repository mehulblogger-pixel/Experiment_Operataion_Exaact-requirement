<?php
// ============================================================================
//  PHASE 3 · M6 — THE INTEGRATED LIFECYCLE
//
//  One transaction from requestor to joining, with the boundary attacked at
//  every step. Every probe calls the PRODUCTION function the route calls.
//  Fixtures are built here and nowhere else — no probe depends on another file
//  having run first.
// ============================================================================

t_section('Phase 3 · M6 — the lifecycle, end to end');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate(); rasg_migrate();
recruit_iv_migrate(); recruit_offer_migrate(); ensure_settings_schema();
$m6o = $_SESSION;
foreach ([[9611,'M6L Branch A'], [9612,'M6L Branch B']] as $o)
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); } catch (Throwable $e) {}
$mk = function ($un, $role, $super, $office, $scope) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,'M6L',?,1,?,?,?)")->execute([$un, $role, $super ? 1 : 0, $office, $scope]);
    return (int) $pdo->lastInsertId(); };
$act = function ($u) { $_SESSION['uid'] = $u; current_user(true); ua(true); };
$uBoss = $mk('m6l_boss', 'MANAGER', 1, 9611, '');
$uRecA = $mk('m6l_a', 'COORDINATOR', 0, 9611, '9611');
$uRecB = $mk('m6l_b', 'COORDINATOR', 0, 9612, '9612');
$act($uBoss);
$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$base = fn(array $x = []) => array_merge([
    'requested_by_id' => $uBoss, 'requested_by_name' => 'M6L Boss',
    'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
    'job_title' => 'M6L Engineer', 'designation' => 'ENGINEER', 'job_description' => 'm6',
    'quantity' => 10, 'office_id' => 9611, 'required_by' => '2026-12-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL', 'reason' => 'x'], $x);
$approve = function (array $x = []) use ($base) {
    [$ok,, $id] = hreq_save(0, $base($x)); if (!$ok) return 0;
    hreq_submit($id); hreq_apply_decision($id, 'APPROVED', 'M6L Approver', 'ok'); return (int) $id; };
$mkCand = function ($req, $stage = 'RECEIVED') use ($pdo) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,created_at)
                   VALUES (?,'M6L','C',?,?,?)")->execute(['M6LC-' . bin2hex(random_bytes(3)), $stage, $req, date('c')]);
    return (int) $pdo->lastInsertId(); };
$stage = fn($c) => (string) ops_val("SELECT stage FROM candidates WHERE id=?", [$c]);
$ivN   = fn($c) => (int) ops_val("SELECT COUNT(*) FROM interviews WHERE candidate_id=?", [$c]);
$offN  = fn($c) => (int) ops_val("SELECT COUNT(*) FROM job_offers WHERE candidate_id=?", [$c]);

// ---- L1 · THE HAPPY LIFECYCLE ----------------------------------------------
t_section('L1 · requestor → approval → requisition → recruiter → candidate → offer → joining');
$h = $approve(['quantity' => 2, 'job_title' => 'M6L Happy']);
t_ok($h > 0, 'L1.1 · a hiring request was raised and approved');
t_eq(strtoupper((string) hreq_get($h)['status']), 'APPROVED', 'L1.2 · it is APPROVED');
[$okR,, $rq] = hreq_to_requisition($h, 2);
t_ok($okR && $rq > 0, 'L1.3 · a requisition was raised from it');
t_eq(rasg_assign('REQ_RECRUITER', $rq, $uRecA, ['expect' => null])['code'], 'OK', 'L1.4 · a recruiter was assigned');
$c1 = $mkCand($rq);
t_eq(rexec_block_reason($rq, 'ADVANCE'), '', 'L1.5 · execution is permitted');
t_eq(rexec_block_reason($rq, 'INTERVIEW'), '', 'L1.6 · an interview may be scheduled');
t_ok(iv_schedule($c1, ['round' => 'L1', 'mode' => 'In person', 'scheduled_at' => date('c')]) > 0, 'L1.7 · …and is');
t_eq($ivN($c1), 1, 'L1.8 · the interview exists');
$o1 = offer_create($c1, ['ctc' => 600000, 'joining_date' => '2026-12-01']);
t_eq($offN($c1), 1, 'L1.9 · an offer was drafted');
offer_submit($o1); offer_approve($o1);
[$iOk, $iMsg] = offer_issue($o1);
t_ok($iOk, 'L1.10 · the offer was issued: ' . $iMsg);
t_eq($stage($c1), 'OFFERED', 'L1.11 · the candidate is OFFERED');
[$aOk, $aMsg] = offer_accept($o1);
t_ok($aOk, 'L1.12 · the offer was accepted: ' . $aMsg);
//  the joining itself goes through the stage write the route performs
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $c1]);
reqf_sync($rq);
t_eq(rexec_seats($rq)['remaining'], 1, 'L1.13 · one of the two seats is now taken');
t_eq(strtoupper((string) ops_val("SELECT status FROM requisitions WHERE id=?", [$rq])), 'PARTIALLY_FILLED',
     'L1.14 · *** one joining does NOT close a two-seat requirement ***');

// ---- L2 · THE SEAT CEILING AT JOINING --------------------------------------
t_section('L2 · the last seat, and the one after it');
$c2 = $mkCand($rq);
t_eq(rexec_block_reason($rq, 'JOIN', $c2), '', 'L2.1 · the second joining is permitted');
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $c2]);
reqf_sync($rq);
t_eq(rexec_seats($rq)['remaining'], 0, 'L2.2 · both seats are now taken');
$c3 = $mkCand($rq);
$why3 = rexec_block_reason($rq, 'JOIN', $c3);
t_ok($why3 !== '', 'L2.3 · *** a third joining on a two-seat requirement is refused: ' . $why3 . ' ***');
t_eq(rexec_block_reason($rq, 'ADVANCE', $c3), '', 'L2.4 · …but the third candidate may still be worked — only the seat is gone');
t_eq(rexec_block_reason($rq, 'OFFER', $c3), '',
     'L2.5 · …and may still be offered, because offers are declined and the business runs more than it has seats');
//  the compensating revert: the write happens anyway, and is put back
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED' WHERE id=?")->execute([$c3]);
$rev = rexec_join_enforce_after_write($c3, 'OFFERED');
t_ok($rev !== '', 'L2.6 · *** a joining written past the gate is reverted: ' . $rev . ' ***');
t_eq($stage($c3), 'OFFERED', 'L2.7 · …and the candidate is back where they were');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND subject LIKE 'Joining reverted%'", [$c3]) >= 1,
     'L2.8 · the reverted joining is on the audit spine');
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rq]), 2,
     'L2.9 · *** never more joined than approved ***');
//  a candidate already holding a seat is not refused their own seat
t_eq(rexec_block_reason($rq, 'JOIN', $c1), '', 'L2.10 · somebody already in a seat does not compete with themselves');

// ---- L3 · M4 BLOCKS EVERY EXECUTION PATH -----------------------------------
t_section('L3 · a material change blocks every execution path, not one of them');
$hB = $approve(['job_title' => 'M6L Blocked', 'quantity' => 4]);
[$okB,, $rqB] = hreq_to_requisition($hB, 4);
$cB = $mkCand($rqB);
hreq_save($hB, $base(['job_title' => 'M6L Blocked', 'quantity' => 4, 'designation' => 'SUPERVISOR']));
t_ok(!hreq_is_executable(hreq_get($hB)), 'L3.1 · the request is blocked');
foreach (['ADVANCE', 'INTERVIEW', 'OFFER', 'JOIN'] as $what)
    t_ok(rexec_block_reason($rqB, $what, $cB) !== '', "L3.2 · the gate refuses $what");
t_eq(iv_schedule($cB, ['round' => 'L1', 'mode' => 'In person', 'scheduled_at' => date('c')]), 0,
     'L3.3 · *** no interview can be scheduled ***');
t_eq($ivN($cB), 0, 'L3.4 · …and none exists');
t_eq(offer_create($cB, ['ctc' => 500000]), 0, 'L3.5 · *** no offer can be created ***');
t_eq($offN($cB), 0, 'L3.6 · …and none exists');
t_ok(!recruitpipe_cand_goto(ops_one("SELECT * FROM candidates WHERE id=?", [$cB]), 1, 'attack', 'attacker'),
     'L3.7 · *** the configured pipeline will not advance it either ***');
t_eq($stage($cB), 'RECEIVED', 'L3.8 · the candidate has not moved');
//  …and it all works again once re-approved
hreq_apply_decision($hB, 'APPROVED', 'M6L Approver', 're-approved');
t_eq(rexec_block_reason($rqB, 'OFFER', $cB), '', 'L3.9 · re-approval restores execution');
t_ok(offer_create($cB, ['ctc' => 500000]) > 0, 'L3.10 · …and the offer can now be made');

// ---- L4 · AN OFFER ALREADY IN FLIGHT WHEN THE BLOCK LANDS ------------------
t_section('L4 · the block catches an offer mid-flight');
$hF = $approve(['job_title' => 'M6L Inflight', 'quantity' => 2]);
[$okF,, $rqF] = hreq_to_requisition($hF, 2);
$cF = $mkCand($rqF);
$oF = offer_create($cF, ['ctc' => 700000]);
t_ok($oF > 0, 'L4.1 · an offer is drafted while everything is in order');
offer_submit($oF); offer_approve($oF);
hreq_save($hF, $base(['job_title' => 'M6L Inflight', 'quantity' => 2, 'office_id' => 9612]));  // material
t_ok(!hreq_is_executable(hreq_get($hF)), 'L4.2 · a material change lands while the offer is approved');
[$issOk, $issMsg] = offer_issue($oF);
t_ok(!$issOk, 'L4.3 · *** the offer cannot be ISSUED any more: ' . $issMsg . ' ***');
t_ok($stage($cF) !== 'OFFERED', 'L4.4 · and the candidate was not moved to OFFERED');
[$accOk, $accMsg] = offer_accept($oF);
t_ok(!$accOk, 'L4.5 · *** nor accepted ***');

// ---- L5 · A CANCELLED OR CLOSED REQUIREMENT --------------------------------
t_section('L5 · a requirement that is over');
foreach (['CANCELLED', 'CLOSED'] as $dead) {
    $hD = $approve(['job_title' => 'M6L ' . $dead]);
    [$okD,, $rqD] = hreq_to_requisition($hD, 1);
    $cD = $mkCand($rqD);
    $pdo->prepare("UPDATE requisitions SET status=? WHERE id=?")->execute([$dead, $rqD]);
    t_ok(rexec_block_reason($rqD, 'ADVANCE', $cD) !== '', "L5 · $dead · the gate refuses");
    t_eq(iv_schedule($cD, ['round' => 'L1', 'mode' => 'In person', 'scheduled_at' => date('c')]), 0,
         "L5 · $dead · *** no interview ***");
    t_eq(offer_create($cD, ['ctc' => 1]), 0, "L5 · $dead · *** no offer ***");
}
//  an unreadable state fails closed
$hU = $approve(['job_title' => 'M6L Blank']); [$okU,, $rqU] = hreq_to_requisition($hU, 1);
$pdo->prepare("UPDATE requisitions SET status='' WHERE id=?")->execute([$rqU]);
t_ok(rexec_block_reason($rqU, 'ADVANCE') !== '', 'L5 · an unreadable requirement status is refused, not assumed safe');

// ---- L6 · ADR-001 — THE DIRECT PATH IS UNCHANGED ---------------------------
t_section('L6 · a candidate with no requirement is not invented a refusal');
$cFree = $mkCand(null);
t_eq(rexec_cand_block_reason($cFree, 'OFFER'), '', 'L6.1 · no requirement means no approval to respect');
t_ok(offer_create($cFree, ['ctc' => 400000]) > 0, 'L6.2 · …and the offer is allowed');
t_ok(iv_schedule($cFree, ['round' => 'L1', 'mode' => 'In person', 'scheduled_at' => date('c')]) > 0,
     'L6.3 · …as is the interview');
t_eq(rexec_block_reason(0, 'JOIN'), '', 'L6.4 · and there is no seat ceiling to apply');

// ---- L7 · THE GATE COMPOSES, IT DOES NOT RE-DECIDE -------------------------
t_section('L7 · one authority per question');
$src = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/recruit_exec.php'));
t_ok(strpos($src, 'hreq_req_block_reason') !== false, 'L7.1 · approval is asked of M4');
t_ok(strpos($src, 'reqf_counts') !== false, 'L7.2 · the seat count is asked of M3\'s counter');
t_ok(strpos($src, 'HREQ_REAPPROVAL') === false && strpos($src, 'hreq_material_diff') === false,
     'L7.3 · *** M6 does not re-implement materiality — it asks M4 ***');
t_ok(strpos($src, 'INSERT INTO requisitions') === false && strpos($src, 'UPDATE requisitions') === false,
     'L7.4 · the gate writes no requirement of its own');

// ---- L9 · NOTHING EXECUTES BEFORE APPROVAL ---------------------------------
//  Invariant I1. A mutation that deleted the approval-status line from
//  hreq_is_executable() SURVIVED the first M6 battery: the M1/M4 suites assert
//  this, but M6's own suite — which claims I1 — never did, and the battery ran
//  M6's suites. A milestone must prove the invariants it claims.
t_section('L9 · no execution before an approved hiring request');
foreach (['DRAFT', 'SUBMITTED', 'REJECTED', 'CANCELLED'] as $st) {
    [$okN,, $hN] = hreq_save(0, $base(['job_title' => 'M6L ' . $st]));
    if ($st === 'SUBMITTED') hreq_submit($hN);
    elseif ($st === 'REJECTED') { hreq_submit($hN); hreq_apply_decision($hN, 'REJECTED', 'M6L Approver', 'no'); }
    elseif ($st === 'CANCELLED') hreq_cancel($hN, 'not needed');
    $row = hreq_get($hN);
    t_eq(strtoupper((string) $row['status']), $st, "L9 · a $st request is in that state");
    t_ok(!hreq_is_executable($row), "L9 · *** a $st request is NOT executable ***");
    [$okC, $msgC] = hreq_to_requisition($hN, 1);
    t_ok(!$okC, "L9 · …and no requisition can be raised from it: " . $msgC);
}
//  and the approved one is, so the assertions above are not vacuous
$hYes = $approve(['job_title' => 'M6L Approved Control']);
t_ok(hreq_is_executable(hreq_get($hYes)), 'L9 · an APPROVED request IS executable — the checks above mean something');

// ---- L8 · NO MODULE MAY INVENT ANOTHER MODULE'S STATUS ---------------------
//  Found by the M6 repository sweep: the approval chain's REQUISITION callback
//  wrote status='approved' and status='on_hold'. Neither exists in the
//  requisition lifecycle, and the effect was measured — the requirement vanished
//  from the command centre's open demand and the execution gate refused all
//  recruitment against it, moments after it had been APPROVED.
t_section('L8 · the approval chain leaves the requisition lifecycle alone');
$lifecycle = ['OPEN', 'PROPOSED', 'OFFERED', 'PARTIALLY_FILLED', 'HIRED', 'CLOSED', 'CANCELLED'];
$hL = $approve(['job_title' => 'M6L Chain']); [$okL,, $rqL] = hreq_to_requisition($hL, 3);
$stBefore = strtoupper((string) ops_val("SELECT status FROM requisitions WHERE id=?", [$rqL]));
t_ok(in_array($stBefore, $lifecycle, true), 'L8.1 · the requisition starts in a lifecycle status: ' . $stBefore);
//  the production callback, for both outcomes
appr_callback('REQUISITION', $rqL, 'APPROVED', ['rule_name' => 'M6 test rule']);
$stAppr = strtoupper((string) ops_val("SELECT status FROM requisitions WHERE id=?", [$rqL]));
t_ok(in_array($stAppr, $lifecycle, true),
     'L8.2 · *** after an APPROVED decision the status is still a real one: ' . $stAppr . ' ***');
t_eq((string) ops_val("SELECT approved_by FROM requisitions WHERE id=?", [$rqL]), 'Approval chain',
     'L8.3 · …and the decision is recorded where it belongs');
t_eq(rexec_block_reason($rqL, 'ADVANCE'), '',
     'L8.4 · *** recruitment can still execute against a requirement the chain approved ***');
//  Asked of the dashboard's OWN query rather than of its top-eight list. The
//  first version of this probe looked in $d['demand'], which is sliced to the
//  eight biggest — so it passed alone and failed in the full suite, where other
//  fixtures crowd the list. That is a probe depending on what else ran, which is
//  precisely what test isolation forbids.
$f8 = ['fy' => '', 'range' => null, 'month' => '', 'dept' => '', 'source' => '', 'manager' => ''];
[$rw8, $ra8] = rcc_req_where($f8, 'r');
$live8 = "'" . implode("','", RASG_LIVE_REQ) . "'";
$onDash = (int) ops_val("SELECT COUNT(*) FROM requisitions r WHERE $rw8 AND r.status IN ($live8) AND r.id=?",
                        array_merge($ra8, [$rqL]));
t_eq($onDash, 1, 'L8.5 · *** and the command centre\'s own query still returns it as live demand ***');
appr_callback('REQUISITION', $rqL, 'REJECTED', ['rule_name' => 'M6 test rule']);
$stRej = strtoupper((string) ops_val("SELECT status FROM requisitions WHERE id=?", [$rqL]));
t_ok(in_array($stRej, $lifecycle, true),
     'L8.6 · *** and after a REJECTED decision too: ' . $stRej . ' ***');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='REQUISITION' AND entity_id=? AND subject LIKE '%approval chain%'", [$rqL]) >= 2,
     'L8.7 · both decisions are on the audit spine, which is where they belong');
//  the source can never quietly regain an invented status
$apprSrc = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/recruit_approval.php'));
t_ok(strpos($apprSrc, "status='approved'") === false && strpos($apprSrc, "status='on_hold'") === false,
     'L8.8 · *** no invented requisition status remains in the approval engine ***');

// ---- L10 · A REFUSED OPERATION MUST NOT REPORT SUCCESS ---------------------
//  Found by attacking M6 after it was accepted. The interview and offer routes
//  announced success unconditionally, so once the gate began refusing, a
//  coordinator on a blocked requirement was told the interview was booked and
//  the offer drafted while nothing had been written. A failed operation that
//  reports success is worse than the gap it replaced: the person acts on it.
t_section('L10 · the screen says what actually happened');
$hT = $approve(['job_title' => 'M6L Truth', 'quantity' => 2]);
[$okT,, $rqT] = hreq_to_requisition($hT, 2);
$cT = $mkCand($rqT);
hreq_save($hT, $base(['job_title' => 'M6L Truth', 'quantity' => 2, 'designation' => 'SUPERVISOR']));
t_ok(rexec_cand_block_reason($cT, 'INTERVIEW') !== '', 'L10.1 · the requirement is blocked');
//  the functions refuse …
t_eq(iv_schedule($cT, ['round' => 'L1', 'mode' => 'In person', 'scheduled_at' => date('c')]), 0, 'L10.2 · iv_schedule() returns nothing');
t_eq(offer_create($cT, ['ctc' => 100]), 0, 'L10.3 · offer_create() returns nothing');
//  … and the routes report the refusal rather than a success.
$ivSrc = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/recruit_iv.php'));
$ivBlk = substr($ivSrc, strpos($ivSrc, "if (\$do === 'schedule')"), 420);
//  The claim is that the result is KEPT AND TESTED — not that a substring is
//  absent. My first version looked for the absence of "iv_schedule($id, $_POST);",
//  which still appears inside the assignment that fixes the defect.
t_ok(strpos($ivBlk, '$ivNew = iv_schedule(') !== false && strpos($ivBlk, 'if ((int) $ivNew > 0)') !== false,
     'L10.4 · *** the interview route keeps the result and branches on it ***');
t_ok(strpos($ivBlk, "rexec_cand_block_reason") !== false,
     'L10.5 · …it asks the gate why, and says so');
$ofSrc = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/recruit_offer.php'));
$ofBlk = substr($ofSrc, strpos($ofSrc, "elseif (\$do === 'offer_create')"), 520);
t_ok(strpos($ofBlk, '$offNew = offer_create(') !== false && strpos($ofBlk, 'if ((int) $offNew > 0)') !== false,
     'L10.6 · *** the offer route keeps the result and branches on it ***');
t_ok(strpos($ofBlk, "rexec_cand_block_reason") !== false, 'L10.7 · …and reports the real reason');
//  and on a healthy requirement both still succeed, so the checks are not vacuous
$hOk = $approve(['job_title' => 'M6L Truth OK']); [$okOk,, $rqOk] = hreq_to_requisition($hOk, 1);
$cOk = $mkCand($rqOk);
t_ok(iv_schedule($cOk, ['round' => 'L1', 'mode' => 'In person', 'scheduled_at' => date('c')]) > 0,
     'L10.8 · an interview on a healthy requirement is still scheduled');
t_ok(offer_create($cOk, ['ctc' => 100]) > 0, 'L10.9 · …and an offer is still drafted');

// ---- L11 · ONE REQUEST, ONE DECISION --------------------------------------
//  C5 in the concurrency suite is the behavioural detector for this, and it is a
//  PROBABILISTIC one: two approvers deciding the same re-approval at the same
//  moment both succeeded in two runs out of five on MariaDB, and never on SQLite,
//  which serialises writers. The defect — check-then-write around the decision —
//  is therefore pinned deterministically here as well.
t_section('L11 · the decision is a compare-and-swap, not a check then a write');
$hqSrc = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/hiringreq.php'));
$dec = substr($hqSrc, strpos($hqSrc, 'function hreq_apply_decision('));
$dec = substr($dec, 0, strpos($dec, "\nfunction "));
t_ok(substr_count($dec, "reapproval_state,'')) IN ('REQUIRED','IN_PROGRESS')") >= 1,
     'L11.1 · *** the re-approval write carries the state the gate checked ***');
t_ok(substr_count($dec, "status,'')) IN ('SUBMITTED','UNDER_REVIEW')") >= 1,
     'L11.2 · *** and so does the first decision ***');
t_eq(substr_count($dec, 'rowCount() < 1'), 2,
     'L11.3 · *** both writes check that they matched a row, and refuse when they did not ***');
t_ok(strpos($dec, 'Somebody else decided') !== false,
     'L11.4 · …and the loser is told plainly rather than silently ignored');
//  Behavioural: the loser writes nothing at all — no state, no audit.
$hD2 = $approve(['job_title' => 'M6L One Decision']);
hreq_save($hD2, $base(['job_title' => 'M6L One Decision', 'designation' => 'SUPERVISOR']));
t_eq(hreq_reapproval_state(hreq_get($hD2)), 'REQUIRED', 'L11.5 · a re-approval is open');
[$d1ok, $d1msg] = hreq_apply_decision($hD2, 'APPROVED', 'M6L Approver', 'first');
t_ok($d1ok, 'L11.6 · the first decision is recorded');
$auditAfterFirst = (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?", [$hD2]);
[$d2ok, $d2msg] = hreq_apply_decision($hD2, 'REJECTED', 'M6L Other', 'second');
t_ok(!$d2ok, 'L11.7 · *** the second decision is refused: ' . $d2msg . ' ***');
t_eq(hreq_reapproval_state(hreq_get($hD2)), 'REAPPROVED', 'L11.8 · the first decision stands');
t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?", [$hD2]),
     $auditAfterFirst, 'L11.9 · *** and the refused decision wrote nothing to the audit trail ***');

// ---- L12 · A MOVE IS A JOINING ON THE REQUIREMENT IT ARRIVES AT ------------
//  Found by attacking M6 after the third pass. The candidate edit path asked the
//  gate with ADVANCE, which does not look at seats — correctly, because advancing
//  does not take one. But moving somebody who ALREADY HOLDS A SEAT onto another
//  requirement is a joining there, and asked as an advance it put two people into
//  one approved seat.
t_section('L12 · moving a joined person onto a full requirement');
$hM1 = $approve(['job_title' => 'M6L Move A', 'quantity' => 1]);
[$okM1,, $rqM1] = hreq_to_requisition($hM1, 1);
$hM2 = $approve(['job_title' => 'M6L Move B', 'quantity' => 1]);
[$okM2,, $rqM2] = hreq_to_requisition($hM2, 1);
$onM1 = $mkCand($rqM1); $onM2 = $mkCand($rqM2);
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED' WHERE id IN (?,?)")->execute([$onM1, $onM2]);
reqf_sync($rqM1); reqf_sync($rqM2);
t_eq(rexec_seats($rqM2)['remaining'], 0, 'L12.1 · the destination is full');
$movingRow = ops_one("SELECT * FROM candidates WHERE id=?", [$onM1]);
t_eq(rexec_move_action($movingRow, $rqM2), 'JOIN',
     'L12.2 · *** moving a joined person is recognised as a JOINING, not an advance ***');
t_ok(rexec_block_reason($rqM2, 'JOIN', 0) !== '',
     'L12.3 · *** and the full requirement refuses it ***');
//  the compensating check, for the race
$pdo->prepare("UPDATE candidates SET requisition_id=? WHERE id=?")->execute([$rqM2, $onM1]);
reqf_sync($rqM1); reqf_sync($rqM2);
$mv = rexec_move_enforce_after_write($onM1, $rqM1);
t_ok($mv !== '', 'L12.4 · *** a move written past the gate is reverted: ' . $mv . ' ***');
t_eq((int) ops_val("SELECT requisition_id FROM candidates WHERE id=?", [$onM1]), $rqM1,
     'L12.5 · …the person is back on the requirement they came from');
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rqM2]), 1,
     'L12.6 · *** and the destination still holds exactly its one approved person ***');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND subject LIKE 'Move reverted%'", [$onM1]) >= 1,
     'L12.7 · the attempt is on the audit spine');
//  a move onto a requirement WITH a seat still works
$hM3 = $approve(['job_title' => 'M6L Move C', 'quantity' => 2]);
[$okM3,, $rqM3] = hreq_to_requisition($hM3, 2);
t_eq(rexec_move_action($movingRow, $rqM3), 'JOIN', 'L12.8 · it is still a joining…');
t_eq(rexec_block_reason($rqM3, 'JOIN', 0), '', 'L12.9 · …and a requirement with a free seat allows it');
//  and an ordinary advance is untouched
$plain = $mkCand($rqM3);
t_eq(rexec_move_action(ops_one("SELECT * FROM candidates WHERE id=?", [$plain]), $rqM1), 'ADVANCE',
     'L12.10 · moving somebody who holds no seat is still just an advance');
//  L12.11–L12.13 pin the WIRING. A mutation that removed the compensator's call
//  from the candidate save survived the battery: the function is covered above,
//  but no test can drive the route itself — it ends in redirect(), which exits.
//  The same answer M5 used for the same class: assert the call is there, and that
//  the save asks for the action it is really performing.
$opsSrc = preg_replace('~^\s*//.*$~m', '', (string) file_get_contents(dirname(__DIR__) . '/lib/ops.php'));
t_ok(strpos($opsSrc, 'rexec_move_enforce_after_write((int) $cand[\'id\'], (int) ($cand[\'requisition_id\'] ?? 0))') !== false,
     'L12.11 · *** the candidate save runs the move compensator after its write ***');
t_ok(strpos($opsSrc, "if (function_exists('rexec_move_enforce_after_write')) {") !== false,
     'L12.12 · …behind a real guard, not a disabled one');
t_ok(strpos($opsSrc, 'rexec_move_action($cand, (int) $b[\'requisition_id\'])') !== false,
     'L12.13 · *** and it asks the gate for the action the save is actually performing ***');

// ---- L13 · A DECISION THAT WAS UNDONE LEAVES NO DECISION STAMP -------------
t_section('L13 · the reverted joining leaves nothing behind');
$hR = $approve(['job_title' => 'M6L Stamp', 'quantity' => 1]);
[$okR2,, $rqR] = hreq_to_requisition($hR, 1);
$seat = $mkCand($rqR); $pdo->prepare("UPDATE candidates SET stage='ACCEPTED' WHERE id=?")->execute([$seat]);
reqf_sync($rqR);
$lateR = $mkCand($rqR, 'OFFERED');
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $lateR]);
t_ok(rexec_join_enforce_after_write($lateR, 'OFFERED', '') !== '', 'L13.1 · the extra joining is reverted');
$rowR = ops_one("SELECT stage, decided_at FROM candidates WHERE id=?", [$lateR]);
t_eq((string) $rowR['stage'], 'OFFERED', 'L13.2 · the stage is back');
t_eq(trim((string) $rowR['decided_at']), '',
     'L13.3 · *** and no decision stamp is left for a decision that was undone ***');
//  a candidate reverted INTO a closing stage keeps the stamp that belongs there
$lateR2 = $mkCand($rqR, 'OFFERED');
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $lateR2]);
rexec_join_enforce_after_write($lateR2, 'WITHDRAWN', '2026-01-01T00:00:00+00:00');
t_eq(substr((string) ops_val("SELECT decided_at FROM candidates WHERE id=?", [$lateR2]), 0, 10), '2026-01-01',
     'L13.4 · …while a genuine earlier decision is restored, not erased');

// ---- L14 · INVARIANT I21 — CAPACITY AND INCUMBENCY -------------------------
//  The business rule, ratified:
//
//    "Recruitment must never exceed approved capacity and must never displace an
//     established holder merely to manufacture a concurrency winner. Where
//     simultaneous claims cannot be deterministically resolved without risking
//     displacement, the system may refuse the contested claims and leave the
//     capacity available for a subsequent valid transaction."
//
//  I21a never over capacity · I21b never displace an incumbent · I21c a refused
//  dead heat leaves the capacity USABLE, never consumed. I21c is a permitted
//  outcome, not a shortfall: two attempts to guarantee a winner instead each
//  violated I21b by letting an arriving candidate displace an established one,
//  and each was caught by L12 and L13.
t_section('L14 · invariant I21 — capacity and incumbency');
$hK = $approve(['job_title' => 'M6L Fair', 'quantity' => 2]);
[$okK,, $rqK] = hreq_to_requisition($hK, 2);
$first = $mkCand($rqK);
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $first]);
reqf_sync($rqK);
t_eq(rexec_seats($rqK)['remaining'], 1, 'L14.1 · one seat remains');
//  Two write for the last seat, as two racing processes do; both compensators run.
$x = $mkCand($rqK, 'OFFERED'); $y = $mkCand($rqK, 'OFFERED');
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id IN (?,?)")->execute([date('c'), $x, $y]);
reqf_sync($rqK);
$revX = rexec_join_enforce_after_write($x, 'OFFERED', '');
$revY = rexec_join_enforce_after_write($y, 'OFFERED', '');
$joinedK = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rqK]);
t_ok($joinedK <= 2, 'L14.2 · *** I21a — never more than the approved seats: ' . $joinedK . ' of 2 ***');
t_eq((string) ops_val("SELECT stage FROM candidates WHERE id=?", [$first]), 'ACCEPTED',
     'L14.3 · *** I21b — the person already in a seat is NEVER displaced ***');
t_ok(!($revX === '' && $revY === ''), 'L14.4 · the contested claim does not simply stand for both');
//  The cost, asserted rather than hidden: under a dead heat the seat may be left
//  for the next attempt instead of going to one of the two.
if ($revX !== '' && $revY !== '') {
    t_eq($joinedK, 1, 'L14.5 · I21c — under a dead heat both claims are refused…');
    t_eq(rexec_seats($rqK)['remaining'], 1, 'L14.6 · …and the capacity is still there, not consumed by the refusal');
    $retry = $mkCand($rqK, 'OFFERED');
    t_eq(rexec_block_reason($rqK, 'JOIN', $retry), '',
         'L14.7 · *** I21c — a subsequent valid transaction is permitted ***');
    //  …and proved all the way through: the retry actually takes the seat.
    $pdo->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $retry]);
    reqf_sync($rqK);
    t_eq(rexec_join_enforce_after_write($retry, 'OFFERED', ''), '',
         'L14.7b · …and it stands — the released capacity is genuinely reusable');
    t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rqK]), 2,
         'L14.7c · *** ending at exactly the approved two, filled by a valid later claim ***');
} else {
    t_eq($joinedK, 2, 'L14.5 · one claim survived and both seats are filled');
    t_eq(rexec_seats($rqK)['remaining'], 0, 'L14.6 · …and the requirement is full');
    t_ok(true, 'L14.7 · (no dead heat in this run)');
}
//  Re-running a compensator on a settled seat changes nothing.
$settled = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rqK]);
rexec_join_enforce_after_write($first, 'OFFERED', '');
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rqK]), $settled,
     'L14.8 · the compensator is idempotent on a settled requirement');

$_SESSION = $m6o; current_user(true); ua(true);
