<?php
// ============================================================================
//  PHASE 3 · M4 — SECURITY PROBES
//
//  Every attack below calls the PRODUCTION function the route calls, with the
//  payload a crafted POST/AJAX request would carry. Nothing here is defended by
//  a hidden button, and nothing is asserted about the UI.
// ============================================================================

t_section('Phase 3 · M4 — security probes against the production paths');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate(); ensure_settings_schema();
$engine = db_driver(); $root = dirname(__DIR__); $origSess = $_SESSION;
$mine = ['h'=>[], 'u'=>[], 'o'=>[]];
foreach ([[9451,'M4S Branch A'], [9452,'M4S Branch B']] as $o)
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); $mine['o'][]=$o[0]; } catch (Throwable $e) {}
$mkUser = function ($un,$role,$super,$office,$scope) use ($pdo,&$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,?,?,1,?,?,?)")->execute([$un,'M4S',$role,$super?1:0,$office,$scope]);
    $id=(int)$pdo->lastInsertId(); $mine['u'][]=$id; return $id;
};
$uMgr = $mkUser('m4s_mgr','MANAGER',1,9451,'');
$uB   = $mkUser('m4s_b','COORDINATOR',0,9452,'9452');
$act  = function ($u) { $_SESSION['uid']=$u; current_user(true); ua(true); };
$act($uMgr);
$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$base = fn(array $x=[]) => array_merge([
    'requested_by_id'=>$uMgr,'requested_by_name'=>'M4S Manager',
    'requesting_department_id'=>$qua['id'],'hiring_department_id'=>$eng['id'],
    'job_title'=>'M4S Engineer','designation'=>'ENGINEER','job_description'=>'security probes',
    'quantity'=>10,'office_id'=>9451,'required_by'=>'2026-12-01',
    'employment_type'=>'CONTRACT','request_type'=>'PROJECT','priority'=>'NORMAL','reason'=>'x',
], $x);
$approve = function (array $x=[]) use ($base,&$mine) {
    [$ok,,$id] = hreq_save(0, $base($x)); if (!$ok) return 0;
    $mine['h'][]=$id; hreq_submit($id);
    hreq_apply_decision($id,'APPROVED','M4S Approver','ok'); return (int)$id;
};

// ---- A · DIRECT URL / manipulated identifier -------------------------------
t_section('A · direct URL and manipulated identifiers');
$hA = $approve();
t_ok($hA > 0, 'A · an approved request exists to attack');
[$o1,$m1] = hreq_save(999999, $base());
t_ok(!$o1, 'A · an id that does not exist is refused: ' . $m1);
[$o2,$m2] = hreq_to_requisition(999999, 1);
t_ok(!$o2, 'A · …and cannot be converted to a requisition: ' . $m2);
[$o3,$m3] = hreq_apply_decision(999999, 'APPROVED', 'attacker', '');
t_ok(!$o3, 'A · …nor decided: ' . $m3);
//  a stale approval URL: decide a request that is already decided
[$o4,$m4] = hreq_apply_decision($hA, 'APPROVED', 'attacker', 'replayed');
t_ok(!$o4, 'A · *** a stale approval action on an already-approved request is refused: ' . $m4 . ' ***');
//  an already-rejected re-approval cannot be decided again into approval
$hR = $approve(['job_title'=>'M4S Rejected']);
hreq_save($hR, $base(['job_title'=>'M4S Rejected','grade'=>'G9']));
hreq_apply_decision($hR, 'REJECTED', 'M4S Approver', 'no');
t_eq(hreq_reapproval_state(hreq_get($hR)), 'REJECTED', 'A · a re-approval was rejected');
[$o5,$m5] = hreq_apply_decision($hR, 'APPROVED', 'attacker', 'flip it');
t_ok(!$o5, 'A · *** a rejected re-approval cannot be flipped to approved: ' . $m5 . ' ***');
t_ok(!hreq_is_executable(hreq_get($hR)), 'A · and execution stays blocked');

// ---- B · POST manipulation --------------------------------------------------
t_section('B · crafted POST fields must not win');
$hB = $approve(['job_title'=>'M4S Post']);
$before = hreq_get($hB);
//  Everything a hostile form could carry, in one payload.
$evil = $base(['job_title'=>'M4S Post',
    'status'                 => 'DRAFT',
    'reapproval_state'       => 'REAPPROVED',
    'approved_snapshot_json' => '{"fields":{"quantity":999}}',
    'approved_snapshot_at'   => '1999-01-01',
    'decided_by'             => 'attacker',
    'decided_at'             => '1999-01-01',
    'approval_ref'           => '999999',
    'req_no'                 => 'HACKED-1',
    'submitted_at'           => '1999-01-01',
    'id'                     => 424242,
]);
[$ob,$mb] = hreq_save($hB, $evil);
$after = hreq_get($hB);
t_ok($ob, 'B · the save itself is allowed (the fields above are simply not accepted)');
t_eq(strtoupper((string)$after['status']), 'APPROVED',       'B · *** a submitted status cannot change the lifecycle ***');
t_eq(hreq_reapproval_state($after), 'NONE',                  'B · *** a submitted reapproval_state is ignored ***');
t_eq((string)$after['approved_snapshot_json'], (string)$before['approved_snapshot_json'],
     'B · *** the approved snapshot cannot be overwritten from a POST ***');
t_eq((string)$after['decided_by'], (string)$before['decided_by'], 'B · nor the recorded approver');
t_eq((string)$after['req_no'], (string)$before['req_no'],         'B · nor the request number');
t_eq((int)$after['id'], (int)$hB,                                 'B · nor the identity of the row');
t_eq((int)$after['approval_required'], 1,                         'B · and the control is still on');
//  quantity IS accepted — but it is a material increase, so it costs the approval
[$oq,$mq] = hreq_save($hB, $base(['job_title'=>'M4S Post','quantity'=>500]));
$aq = hreq_get($hB);
t_eq((int)$aq['quantity'], 500, 'B · a quantity increase is accepted as a REQUEST…');
t_eq((int) hreq_approved_qty($aq), 10, 'B · *** …but the APPROVED figure is still 10 ***');
t_ok(!hreq_is_executable($aq), 'B · *** and recruitment is blocked until it is re-approved ***');

// ---- D · TENANT ATTACK — real databases ------------------------------------
t_section('D · tenant attack, with real database switching');
$WS_A = $engine==='sqlite' ? (string)getenv('SQLITE_PATH') : (string)getenv('DB_NAME');
$WS_B = $engine==='sqlite' ? sys_get_temp_dir().'/m4s_ws_b.sqlite' : 'm4s_ws_b';
$enterWs = function ($ws) use ($engine) {
    if ($engine==='sqlite') putenv('SQLITE_PATH='.$ws);
    else { db()->exec("CREATE DATABASE IF NOT EXISTS `".$ws."`"); putenv('DB_NAME='.$ws); }
    db(true); db();
};
$whoAmI = function () use ($engine,$root) {
    $cfg = require $root.'/config.php';
    return $engine==='sqlite' ? (string)$cfg['sqlite_path'] : (string)ops_val("SELECT DATABASE()");
};
$idA = $whoAmI();
$hT = $approve(['job_title'=>'M4S Tenant A only']);
t_ok($hT > 0, 'D · tenant A holds an approved request');

$enterWs($WS_B);
t_ok($whoAmI() !== $idA, 'D · *** Tenant A → DB A, Tenant B → DB B, DB A != DB B ***');
//  Build tenant B the way the application builds a new tenant — including the
//  requisitions table, which hreq_to_requisition() migrates through. The first
//  version of this fixture omitted it and died on "no such table", which tested
//  the fixture rather than the boundary.
ensure_settings_schema();
if (function_exists('ops_ensure_schema')) ops_ensure_schema();   // creates `requisitions` and the core tables
hreq_migrate(); appr_migrate(); act_migrate();
if (function_exists('req_migrate')) req_migrate();
t_ok(hreq_get($hT) === null || !hreq_get($hT), "D · *** B cannot READ A's request by its id ***");
[$t1,$tm1] = hreq_save($hT, $base(['job_title'=>'stolen']));
t_ok(!$t1, "D · *** B cannot EDIT it: " . $tm1 . " ***");
[$t2,$tm2] = hreq_apply_decision($hT, 'APPROVED', 'attacker', '');
t_ok(!$t2, "D · *** B cannot APPROVE or RE-APPROVE it: " . $tm2 . " ***");
[$t3,$tm3] = hreq_to_requisition($hT, 1);
t_ok(!$t3, "D · *** B cannot raise a requisition from it: " . $tm3 . " ***");
t_eq(hreq_remaining_qty($hT), 0, "D · and it has no headcount to spend in B");
$enterWs($WS_A);
t_eq($whoAmI(), $idA, 'D · tenant A is restored');
$tAfter = hreq_get($hT);
t_eq(strtoupper((string)$tAfter['status']), 'APPROVED', "D · *** A's request is exactly as A left it ***");
t_eq(hreq_reapproval_state($tAfter), 'NONE',            "D · untouched by every attempt from B");
if ($engine==='sqlite') { @unlink($WS_B); } else { try { db()->exec("DROP DATABASE IF EXISTS `m4s_ws_b`"); } catch (Throwable $e) {} }

// ---- E · BRANCH ATTACK ------------------------------------------------------
t_section('E · branch attack');
$hBr = $approve(['job_title'=>'M4S Branch A only','office_id'=>9451]);
$act($uB);
$probes = [
    'edit'                => fn() => hreq_save($hBr, $base(['job_title'=>'M4S Branch A only','grade'=>'G9']))[0],
    'material change'     => fn() => hreq_save($hBr, $base(['job_title'=>'M4S Branch A only','designation'=>'SUPERVISOR']))[0],
    'quantity change'     => fn() => hreq_save($hBr, $base(['job_title'=>'M4S Branch A only','quantity'=>50]))[0],
    'approve'             => fn() => hreq_apply_decision($hBr,'APPROVED','branch B','')[0],
    'reject'              => fn() => hreq_apply_decision($hBr,'REJECTED','branch B','')[0],
    'submit'              => fn() => hreq_submit($hBr)[0],
    'cancel'              => fn() => hreq_cancel($hBr,'branch B')[0],
    'requisition creation'=> fn() => hreq_to_requisition($hBr,1)[0],
];
foreach ($probes as $name => $p) {
    $r = null; try { $r = $p(); } catch (Throwable $e) { $r = false; }
    t_ok(!$r, "E · a branch B user cannot $name a branch A request");
}
$act($uMgr);
$brAfter = hreq_get($hBr);
t_eq(strtoupper((string)$brAfter['status']), 'APPROVED', 'E · *** and the request survived every attempt unchanged ***');
t_eq(hreq_reapproval_state($brAfter), 'NONE', 'E · with its approval intact');
t_eq((int)$brAfter['quantity'], 10, 'E · and its headcount untouched');

// ---- H · ENTITLEMENT & PERMISSION — fail CLOSED ----------------------------
t_section('H · entitlement and permission');
//  A REAL least-privilege role. The first version used 'VIEWER', which is not in
//  ORG_ROLES — and an unrecognised role falls back to ADMIN, so the probe granted
//  itself every permission and then reported the product as fail-open. INSPECTOR
//  is a real role whose default permission set is empty.
$uNone = $mkUser('m4s_none','INSPECTOR',0,9451,'');
$hH = $approve(['job_title'=>'M4S Entitlement']);
$act($uNone);
t_ok(!hreq_can_create(), 'H · a user without the hiring right has no create/edit capability');
t_ok(!hreq_can_view(),   'H · nor the view capability');
[$e1,$em1] = hreq_save($hH, $base(['job_title'=>'M4S Entitlement','grade'=>'G9']));
t_ok(!$e1, 'H · *** and cannot edit an approved request: ' . $em1 . ' ***');
[$e2,$em2] = hreq_save(0, $base(['job_title'=>'M4S New By Nobody']));
t_ok(!$e2, 'H · *** nor create one: ' . $em2 . ' ***');
[$e3,$em3] = hreq_to_requisition($hH, 1);
t_ok(!$e3, 'H · *** nor raise a requisition: ' . $em3 . ' ***');
t_ok(!hreq_may_decide(hreq_get($hH)), 'H · *** nor decide ***');
$act($uMgr);
t_eq(hreq_reapproval_state(hreq_get($hH)), 'NONE', 'H · and the request was untouched by all of it');

// ---- I · SEGREGATION OF DUTIES ---------------------------------------------
t_section('I · segregation of duties');
$uReq = $mkUser('m4s_req','MANAGER',0,9451,'');       // raises AND could decide
$act($uMgr);
[$sOk,,$hS] = hreq_save(0, $base(['job_title'=>'M4S Segregation','requested_by_id'=>$uReq]));
t_ok($sOk, 'I · a request is raised on behalf of another user');
$mine['h'][]=$hS; hreq_submit($hS);
$act($uReq);
t_ok(hreq_is_own_request(hreq_get($hS)), 'I · that user is the requestor of record');
t_ok(hreq_segregation_blocks(hreq_get($hS)), 'I · *** segregation blocks them ***');
t_ok(!hreq_may_decide(hreq_get($hS)), 'I · *** so they may NOT decide their own request ***');
$act($uMgr);
hreq_apply_decision($hS,'APPROVED','M4S Approver','ok');
//  and it still holds for a RE-approval
hreq_save($hS, $base(['job_title'=>'M4S Segregation','requested_by_id'=>$uReq,'grade'=>'G8']));
$act($uReq);
t_ok(!hreq_may_decide(hreq_get($hS)), 'I · *** and may not decide their own RE-approval either ***');
$act($uMgr);

// ---- J · THE EXECUTION PATHS §13 NAMED -------------------------------------
//
//  Found by adversarial audit AFTER M4 was first declared accepted: only
//  candidate CREATION and requisition EDIT were gated. A probe shortlisted and
//  then OFFERED a candidate on a hiring request whose approval had been
//  invalidated, and attached another candidate to the blocked requisition by
//  editing it. Both routes now ask the boundary, and both are pinned here.
t_section('J · every execution path asks the boundary');
$hJ = $approve(['job_title'=>'M4S Exec Paths','quantity'=>5]);
[$jr,,$rqJ] = hreq_to_requisition($hJ, 5);
t_ok($jr && $rqJ > 0, 'J · a requisition exists');
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,created_at)
               VALUES ('M4S-J1','M4S','J1','RECEIVED',?,?)")->execute([$rqJ, date('c')]);
$cJ = (int)$pdo->lastInsertId();
t_eq(hreq_req_block_reason($rqJ), '', 'J · and while the request is approved, execution is allowed');
//  invalidate the approval
hreq_save($hJ, $base(['job_title'=>'M4S Exec Paths','quantity'=>5,'designation'=>'SUPERVISOR']));
t_ok(!hreq_is_executable(hreq_get($hJ)), 'J · a material change blocks the request');
$whyJ = hreq_req_block_reason($rqJ);
t_ok($whyJ !== '', 'J · and the boundary refuses this requisition: ' . $whyJ);

$opsSrc = file_get_contents(dirname(__DIR__) . '/lib/ops.php');
//  Comments are stripped first. A source pin that can be satisfied by its own
//  explanatory comment proves nothing — that defect was found once already
//  (M14-9) and must not be repeated here.
$opsSrc = preg_replace('~^\s*//.*$~m', '', $opsSrc);
//  Behavioural: the stage route reads the guard BEFORE it writes the stage.
$stagePos = strpos($opsSrc, "if (\$route === 'candidate-stage')");
$stageEnd = strpos($opsSrc, 'UPDATE candidates SET stage=', $stagePos);
$stageBody = substr($opsSrc, $stagePos, max(0, $stageEnd - $stagePos));
t_ok(strpos($stageBody, 'hreq_req_block_reason') !== false,
     'J · *** the stage route asks the boundary BEFORE it advances a candidate ***');
t_ok(strpos($stageBody, "['REJECTED','WITHDRAWN','OFFER_DECLINED','HOLD']") !== false,
     'J · …and only for ADVANCING — a candidate may still be withdrawn or rejected while approval is pending');
//  Behavioural: the candidate POST handler asks before it writes requisition_id.
$postPos = strpos($opsSrc, "} elseif (\$method === 'POST') {\n            \$b = \$_POST;");
t_ok($postPos !== false, 'J · the candidate POST handler is found');
$postBody = substr($opsSrc, $postPos, 1200);
t_ok(strpos($postBody, 'hreq_req_block_reason') !== false,
     'J · *** the candidate POST handler asks the boundary before writing requisition_id ***');
t_ok(strpos($postBody, 'hreq_req_block_reason') < strpos($postBody, "\$fields = ["),
     'J · …and asks it before it decides which fields to write');
//  and the guard really answers for this requisition
t_ok(hreq_req_block_reason($rqJ) !== '', 'J · the boundary is refusing right now');
hreq_apply_decision($hJ, 'APPROVED', 'M4S Approver', 're-approved');
t_eq(hreq_req_block_reason($rqJ), '', 'J · *** and allows again once re-approved ***');

// ---- F · REPLAY -------------------------------------------------------------
t_section('F · replay');
$hF = $approve(['job_title'=>'M4S Replay','quantity'=>4]);
[$rq1ok,,$rqF] = hreq_to_requisition($hF, 4);
t_ok($rq1ok, 'F · a requisition consumes the whole approved headcount');
$n1 = (int) ops_val("SELECT COUNT(*) FROM requisitions WHERE hiring_request_id=?", [$hF]);
for ($i=0;$i<5;$i++) hreq_to_requisition($hF, 4);           // replay the same creation
$n2 = (int) ops_val("SELECT COUNT(*) FROM requisitions WHERE hiring_request_id=?", [$hF]);
t_eq($n2, $n1, 'F · *** replaying requisition creation allocates nothing further ***');
t_eq((int) ops_val("SELECT COALESCE(SUM(quantity),0) FROM requisitions WHERE hiring_request_id=?", [$hF]), 4,
     'F · *** total allocation is still the approved 4 ***');
//  replay a material change — one chain state, not many
hreq_save($hF, $base(['job_title'=>'M4S Replay','quantity'=>4,'grade'=>'G5']));
$st1 = hreq_reapproval_state(hreq_get($hF));
for ($i=0;$i<5;$i++) hreq_save($hF, $base(['job_title'=>'M4S Replay','quantity'=>4,'grade'=>'G5']));
t_eq(hreq_reapproval_state(hreq_get($hF)), $st1, 'F · *** replaying the same material change does not change the state again ***');
//  replay the decision
hreq_apply_decision($hF,'APPROVED','M4S Approver','re-approved');
t_eq(hreq_reapproval_state(hreq_get($hF)), 'REAPPROVED', 'F · it is re-approved once');
$snapF = (string) hreq_get($hF)['approved_snapshot_json'];
[$dOk,] = hreq_apply_decision($hF,'REJECTED','attacker','flip it');
t_ok(!$dOk, 'F · *** the decision cannot be replayed into the opposite answer ***');
t_eq((string) hreq_get($hF)['approved_snapshot_json'], $snapF, 'F · and the approved snapshot is unchanged');

// ---- G · APPROVAL BYPASS, and it may not become a re-approval route ---------
t_section('G · the approval requirement');
$hG = $approve(['job_title'=>'M4S Bypass']);
[$g1,$gm1] = hreq_save($hG, $base(['job_title'=>'M4S Bypass','approval_required'=>0]));
t_ok(!$g1, 'G · *** switching approval off after approval is refused: ' . $gm1 . ' ***');
t_eq((int) hreq_get($hG)['approval_required'], 1, 'G · the control is still on');
t_eq(hreq_reapproval_state(hreq_get($hG)), 'NONE', 'G · *** and it did not become a re-approval route ***');
//  nor smuggled alongside a legitimate material change
[$g2,$gm2] = hreq_save($hG, $base(['job_title'=>'M4S Bypass','designation'=>'SUPERVISOR','approval_required'=>0]));
t_ok(!$g2, 'G · *** nor smuggled in beside a material change: ' . $gm2 . ' ***');
t_eq((int) hreq_get($hG)['approval_required'], 1, 'G · still on');
t_eq((string) hreq_get($hG)['designation'], 'ENGINEER', 'G · and the whole save was refused, not half-applied');

// ---------------------------------------------------------------------------
foreach ($mine['h'] as $h) {
    $pdo->prepare("DELETE FROM requisitions WHERE hiring_request_id=?")->execute([(int)$h]);
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?")->execute([(int)$h]);
    $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([(int)$h]);
}
foreach ($mine['u'] as $u) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$u]);
foreach ($mine['o'] as $o) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$o]);
$_SESSION = $origSess; current_user(true); ua(true);
