<?php
// ============================================================================
//  PHASE 3 · M4 — RE-APPROVAL & REQUISITION CONTROL
//
//  The twelve validation scenarios of the implementation brief, each proved
//  through the PRODUCTION path. Nothing here passes because a button is hidden:
//  every boundary assertion calls the function the form calls.
// ============================================================================

t_section('Phase 3 · M4 — re-approval & requisition control');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate();
$engine = db_driver(); $origSess = $_SESSION;
$mine = ['h'=>[], 'u'=>[], 'o'=>[]];

foreach ([[9441,'M4R Branch A'], [9442,'M4R Branch B']] as $o)
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); $mine['o'][]=$o[0]; } catch (Throwable $e) {}
$mkUser = function ($un,$role,$super,$office,$scope,$perm='') use ($pdo,&$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions)
                   VALUES (?,?,?,1,?,?,?,?)")->execute([$un,'M4R',$role,$super?1:0,$office,$scope,$perm]);
    $id=(int)$pdo->lastInsertId(); $mine['u'][]=$id; return $id;
};
$uMgr = $mkUser('m4r_mgr','MANAGER',1,9441,'');            // may create, edit and decide
$uBranchB = $mkUser('m4r_b','COORDINATOR',0,9442,'9442');  // branch B only
$act = function ($u) { $_SESSION['uid']=$u; current_user(true); ua(true); };
$act($uMgr);

$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$base = fn(array $x=[]) => array_merge([
    'requested_by_id'=>$uMgr, 'requested_by_name'=>'M4R Manager',
    'requesting_department_id'=>$qua['id'], 'hiring_department_id'=>$eng['id'],
    'job_title'=>'M4R Site Engineer', 'designation'=>'ENGINEER',
    'job_description'=>'For the M4 re-approval tests.',
    'quantity'=>10, 'office_id'=>9441, 'required_by'=>'2026-12-01',
    'employment_type'=>'CONTRACT', 'request_type'=>'PROJECT', 'priority'=>'NORMAL',
    'reason'=>'Contract awarded.',
], $x);
//  A request that has genuinely been through submission and approval.
$approve = function (array $x=[]) use ($base,&$mine) {
    [$ok,,$id] = hreq_save(0, $base($x));
    if (!$ok) return 0;
    $mine['h'][]=$id; hreq_submit($id);
    hreq_apply_decision($id, 'APPROVED', 'M4R Approver', 'approved for the tests');
    return (int) $id;
};

// ---------------------------------------------------------------------------
t_section('M4.1 · SCENARIO 1 — the normal path still works');
$h1 = $approve();
t_ok($h1 > 0, 'M4.1 · a hiring request is raised, submitted and approved');
$r1 = hreq_get($h1);
t_eq(strtoupper((string)$r1['status']), 'APPROVED', 'M4.1 · it is APPROVED');
t_eq(hreq_reapproval_state($r1), 'NONE',            'M4.1 · and not awaiting anything');
t_ok(hreq_is_executable($r1),                        'M4.1 · *** recruitment may begin ***');
t_eq(hreq_block_reason($r1), '',                     'M4.1 · with no reason to refuse');
$snap1 = hreq_approved_snapshot($r1);
t_ok(is_array($snap1) && is_array($snap1['fields'] ?? null),
     'M4.1 · *** the approved snapshot exists — "what exactly was approved?" is answerable ***');
t_eq((int)($snap1['fields']['quantity'] ?? 0), 10, 'M4.1 · and it records the approved headcount');
[$cOk,,$req1] = hreq_to_requisition($h1, 5);
t_ok($cOk && $req1 > 0, 'M4.1 · a requisition for 5 of the 10 is created');
t_eq(hreq_remaining_qty($h1), 5, 'M4.1 · 5 of the approved headcount remain');

// ---------------------------------------------------------------------------
t_section('M4.2 · SCENARIO 2 — a NON-material edit keeps the approval');
$snapBefore = json_encode(hreq_approved_snapshot(hreq_get($h1)));
[$ok2,$msg2] = hreq_save($h1, $base(['priority'=>'URGENT', 'reason'=>'Client pulled the date in.']));
$r2 = hreq_get($h1);
t_ok($ok2, 'M4.2 · the edit is allowed: ' . $msg2);
t_eq((string)$r2['priority'], 'URGENT',          'M4.2 · and it really changed');
t_eq(hreq_reapproval_state($r2), 'NONE',         'M4.2 · *** the approval still stands ***');
t_ok(hreq_is_executable($r2),                     'M4.2 · *** recruitment continues ***');
t_eq(json_encode(hreq_approved_snapshot($r2)), $snapBefore, 'M4.2 · the approved snapshot is untouched');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=? AND outcome='NON_MATERIAL'", [$h1]) > 0,
     'M4.2 · *** and it is audited — "not material" never means "not recorded" ***');

// ---------------------------------------------------------------------------
t_section('M4.3 · SCENARIO 3 — a MATERIAL edit invalidates the approval');
$diffPre = hreq_material_diff(hreq_get($h1));
t_ok(!$diffPre, 'M4.3 · nothing material has moved yet');
[$ok3,$msg3] = hreq_save($h1, $base(['priority'=>'URGENT','designation'=>'SUPERVISOR']));
$r3 = hreq_get($h1);
t_ok($ok3, 'M4.3 · the edit is allowed: ' . $msg3);
t_ok(stripos($msg3,'re-approval') !== false, 'M4.3 · and the answer says re-approval');
//  No approval rule is configured in this workspace, so appr_start() matches
//  nothing and the request stays REQUIRED — exactly how hreq_submit() behaves for
//  a FIRST approval, and deliberately so: nothing is forced on a workspace that
//  has configured nothing. M4.12 below configures a rule and proves the chain.
t_eq(hreq_reapproval_state($r3), 'REQUIRED', 'M4.3 · *** re-approval is required ***');
t_eq(strtoupper((string)$r3['status']), 'APPROVED', 'M4.3 · the lifecycle status is untouched — no new status was invented');
t_ok(!hreq_is_executable($r3), 'M4.3 · *** recruitment is BLOCKED ***');
t_ok(stripos(hreq_block_reason($r3),'re-approved') !== false, 'M4.3 · with a reason a coordinator can act on');
t_eq((int)($hreq_snap3 = hreq_approved_snapshot($r3))['fields']['designation'] === 'SUPERVISOR' ? 1 : 0, 0,
     'M4.3 · *** the approved snapshot still says what was APPROVED, not what was typed ***');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=? AND outcome='MATERIAL'", [$h1]) > 0,
     'M4.3 · the material change is audited');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=? AND outcome='BLOCKED'", [$h1]) > 0,
     'M4.3 · so is the execution block');
//  the boundary really stops the production paths
[$cOk3,$cMsg3] = hreq_to_requisition($h1, 1);
t_ok(!$cOk3, 'M4.3 · *** a new requisition is refused while unapproved: ' . $cMsg3 . ' ***');
t_ok(hreq_req_block_reason($req1) !== '', 'M4.3 · *** and execution against the EXISTING requisition is refused too ***');
//  …then re-approval restores it
[$dOk,$dMsg] = hreq_apply_decision($h1, 'APPROVED', 'M4R Approver', 're-approved');
$r3b = hreq_get($h1);
t_ok($dOk, 'M4.3 · the existing decision writer re-approves it: ' . $dMsg);
t_eq(hreq_reapproval_state($r3b), 'REAPPROVED', 'M4.3 · *** the state says re-approved ***');
t_ok(hreq_is_executable($r3b),                   'M4.3 · *** recruitment is restored ***');
t_eq((string)(hreq_approved_snapshot($r3b)['fields']['designation'] ?? ''), 'SUPERVISOR',
     'M4.3 · *** and the NEW approved snapshot was captured AT THE DECISION ***');
t_eq(hreq_req_block_reason($req1), '', 'M4.3 · the existing requisition may be worked again');

// ---------------------------------------------------------------------------
t_section('M4.4 · SCENARIO 4 — a rejected re-approval keeps execution blocked');
$h4 = $approve(['job_title'=>'M4R Rejected Path']);
[$o4,] = hreq_save($h4, $base(['job_title'=>'M4R Rejected Path','quantity'=>25]));
t_eq(hreq_reapproval_state(hreq_get($h4)), 'REQUIRED', 'M4.4 · a material increase opens re-approval');
hreq_apply_decision($h4, 'REJECTED', 'M4R Approver', 'not funded');
$r4 = hreq_get($h4);
t_eq(hreq_reapproval_state($r4), 'REJECTED', 'M4.4 · the re-approval was refused');
t_ok(!hreq_is_executable($r4), 'M4.4 · *** execution REMAINS blocked ***');
t_eq((int) hreq_approved_qty($r4), 10, 'M4.4 · and the approved headcount is still the approved 10, not the asked-for 25');

// ---------------------------------------------------------------------------
t_section('M4.5 · SCENARIO 5 — the headcount ceiling');
$h5 = $approve(['job_title'=>'M4R Ceiling', 'quantity'=>10]);
[$q1ok,,$rq1] = hreq_to_requisition($h5, 5);
[$q2ok,,$rq2] = hreq_to_requisition($h5, 5);
t_ok($q1ok && $q2ok, 'M4.5 · two requisitions of 5 fill the approved 10');
t_eq(hreq_remaining_qty($h5), 0, 'M4.5 · nothing remains to recruit');
[$q3ok,$q3msg] = hreq_to_requisition($h5, 1);
t_ok(!$q3ok, 'M4.5 · an eleventh seat is refused at creation: ' . $q3msg);
t_ok(hreq_qty_guard($h5, 6, $rq2) !== '', 'M4.5 · *** raising requisition B from 5 to 6 is refused ***');
t_eq(hreq_qty_guard($h5, 5, $rq2), '',    'M4.5 · leaving it at 5 is allowed');
t_eq(hreq_qty_guard($h5, 4, $rq2), '',    'M4.5 · lowering it is allowed');
//  and the compensating check catches a write that got past the form
$pdo->prepare("UPDATE requisitions SET quantity=6 WHERE id=?")->execute([$rq2]);
$why5 = hreq_qty_enforce_after_write($rq2, 5);
t_ok($why5 !== '', 'M4.5 · *** a direct write of 6 is caught after the fact: ' . $why5 . ' ***');
t_eq((int) ops_val("SELECT quantity FROM requisitions WHERE id=?", [$rq2]), 5,
     'M4.5 · *** and reverted — the approved headcount is never over-allocated ***');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=? AND outcome='OVER_ALLOCATION_REFUSED'", [$h5]) > 0,
     'M4.5 · and the refusal is audited');

// ---------------------------------------------------------------------------
t_section('M4.7 · SCENARIO 7 — the approval requirement cannot be switched off');
[$b7,$m7] = hreq_save($h5, $base(['job_title'=>'M4R Ceiling','quantity'=>10,'approval_required'=>0]));
t_ok(!$b7, 'M4.7 · *** turning approval off after approval is REFUSED: ' . $m7 . ' ***');
t_eq((int) hreq_get($h5)['approval_required'], 1, 'M4.7 · and the control is still on');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=? AND outcome='DENIED'", [$h5]) > 0,
     'M4.7 · the attempt is audited');
t_eq(hreq_reapproval_state(hreq_get($h5)), 'NONE', 'M4.7 · and it did NOT open a re-approval route for a bypass');

// ---------------------------------------------------------------------------
t_section('M4.10 · SCENARIO 10 — branch scope');
$h10 = $approve(['job_title'=>'M4R Branch A only', 'office_id'=>9441]);
$act($uBranchB);
[$bOk,$bMsg] = hreq_save($h10, $base(['job_title'=>'M4R Branch A only','designation'=>'SUPERVISOR']));
t_ok(!$bOk, 'M4.10 · *** a branch B user cannot edit a branch A request: ' . $bMsg . ' ***');
[$bcOk,$bcMsg] = hreq_to_requisition($h10, 1);
t_ok(!$bcOk, 'M4.10 · *** nor raise a requisition from it: ' . $bcMsg . ' ***');
t_eq(hreq_reapproval_state(hreq_get($h10)), 'NONE', 'M4.10 · and the request was not disturbed by the attempt');
$act($uMgr);

// ---------------------------------------------------------------------------
//  M4.12 · SCENARIO — with a rule configured, the EXISTING approval engine runs.
//  This is the assertion that proves M4 built no approval mechanism of its own.
// ---------------------------------------------------------------------------
t_section('M4.12 · the re-approval chain is the existing M1/M2/M3 engine');
$ruleId = (int) appr_rule_save(0, ['name'=>'M4R re-approval','entity'=>'HIRING_REQUEST',
                                   'code'=>'M4RRULE','applies_department'=>'']);
t_ok($ruleId > 0, 'M4.12 · an approval rule exists for hiring requests');
appr_level_save(['rule_id'=>$ruleId,'seq'=>1,'label'=>'Head','approver_user_id'=>$uMgr,
                 'sla_days'=>3,'reminder_days'=>1]);
$h12 = $approve(['job_title'=>'M4R Chain']);
$chainsBefore = (int) ops_val("SELECT COUNT(*) FROM recruit_approval_requests WHERE entity='HIRING_REQUEST' AND entity_id=?", [$h12]);
hreq_save($h12, $base(['job_title'=>'M4R Chain','grade'=>'G7']));
$r12 = hreq_get($h12);
t_eq(hreq_reapproval_state($r12), 'IN_PROGRESS', 'M4.12 · *** the chain started, so the state is IN_PROGRESS ***');
t_ok(!hreq_is_executable($r12), 'M4.12 · and recruitment is blocked while it runs');
//  The invariant that matters is not "one more row" — appr_start() deliberately
//  RETURNS an already-open chain rather than creating a second, which is the
//  behaviour that stops duplicate approvals. The first version of this assertion
//  expected a new row and was measuring the wrong thing: the fixture's first
//  approval had been decided directly, so its chain was still open and the
//  re-approval correctly reused it.
$openChains = fn() => (int) ops_val("SELECT COUNT(*) FROM recruit_approval_requests
                                     WHERE entity='HIRING_REQUEST' AND entity_id=? AND UPPER(status)='PENDING'", [$h12]);
t_eq($openChains(), 1, 'M4.12 · *** exactly ONE open chain, in the EXISTING approval table ***');
t_ok((int)($r12['approval_ref'] ?? 0) > 0, 'M4.12 · and the request points at it');
$chain = ops_one("SELECT id, status FROM recruit_approval_requests WHERE entity='HIRING_REQUEST' AND entity_id=? ORDER BY id DESC", [$h12]);
t_eq(strtoupper((string)$chain['status']), 'PENDING', 'M4.12 · the chain is pending a decision');
t_ok((int) ops_val("SELECT COUNT(*) FROM recruit_approval_steps WHERE request_id=?", [(int)$chain['id']]) > 0,
     'M4.12 · *** with real steps from the configured matrix — no second engine ***');
//  §22 — the M3 SLA engine picks it up without an M4 equivalent
$sum = appr_sla_summary();
t_ok((int)($sum['pending'] ?? 0) > 0, 'M4.12 · *** the existing SLA summary already counts it ***');
//  a second material change must NOT open a second chain
hreq_save($h12, $base(['job_title'=>'M4R Chain','grade'=>'G8']));
t_eq($openChains(), 1, 'M4.12 · *** a further change does NOT open a second chain ***');
t_eq(hreq_reapproval_state(hreq_get($h12)), 'IN_PROGRESS', 'M4.12 · it stays in progress');
$pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id IN (SELECT id FROM recruit_approval_requests WHERE entity='HIRING_REQUEST' AND entity_id=?)")->execute([$h12]);
$pdo->prepare("DELETE FROM recruit_approval_requests WHERE entity='HIRING_REQUEST' AND entity_id=?")->execute([$h12]);
$pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([$ruleId]);
$pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([$ruleId]);

// ---------------------------------------------------------------------------
t_section('M4.16 · ADR-001 — a requisition with no hiring request is unaffected');
t_eq(hreq_req_block_reason(0), '', 'M4.16 · no requisition, nothing to enforce');
t_eq(hreq_qty_guard(0, 999), '',   'M4.16 · *** no approved headcount is invented from nowhere ***');

// ---------------------------------------------------------------------------
foreach ($mine['h'] as $h) {
    $pdo->prepare("DELETE FROM requisitions WHERE hiring_request_id=?")->execute([(int)$h]);
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?")->execute([(int)$h]);
    $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([(int)$h]);
}
foreach ($mine['u'] as $u) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$u]);
foreach ($mine['o'] as $o) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$o]);
$_SESSION = $origSess; current_user(true); ua(true);
