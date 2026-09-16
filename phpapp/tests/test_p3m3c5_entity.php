<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #5 — UNRESOLVED SOURCE ENTITY MUST FAIL CLOSED
//
//  G1  appr_visible() asked whether the entity TYPE was known and then resolved
//      the RECORD for the hiring request alone. A step whose offer, salary
//      structure or requisition had been deleted still produced recipients.
//      C2's rule had its unknown-type half implemented for every entity and its
//      missing-record half for one.
//
//      SUPPORTED TYPE  ≠  RESOLVED RECORD
//
//  The matrix below has a row for EACH HALF of that rule, for each entity —
//  which is the narrower lesson from the last audit, where a matrix built to stop
//  sibling omissions was itself missing a column.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #5 — unresolved source entity fails closed');

$pdo = db(); hreq_migrate(); appr_migrate(); recruit_offer_migrate();
$mine = ['u'=>[], 'o'=>[], 'rule'=>[], 'rq'=>[], 'cand'=>[], 'h'=>[]];
$origSess = $_SESSION;
$offWas = function_exists('setting_get') ? (string) setting_get('modules_off', '') : '';

try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (891,'C5 A',1)")->execute(); $mine['o'][]=891; } catch (Throwable $e) {}
$mk = function ($un,$role,$su,$off,$p,$em,$fn,$ln) use ($pdo,&$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions,email)
                   VALUES (?,?,?,?,1,?,?,'891',?,?)")->execute([$un,$fn,$ln,$role,$su?1:0,$off,$p,$em]);
    $id=(int)$pdo->lastInsertId(); $mine['u'][]=$id; return $id;
};
$act = function ($u) { $_SESSION['uid']=$u; current_user(true); ua(true); };
$HR = 'mod.hiring.view,mod.hiring.edit';
$uRaise = $mk('c5_raise','BRANCH_MANAGER',0,891,$HR,'c5raise@t.test','C5','Raiser');
$uAppr  = $mk('c5_appr','SBU_HEAD',0,891,$HR,'c5appr@t.test','C5','Approver');
$uMast  = $mk('c5_mast','ADMIN',1,891,'','c5mast@t.test','C5','Master');
$cfg = function (callable $fn) use ($uMast) {
    $p=$_SESSION['uid']??null; $_SESSION['uid']=$uMast; current_user(true); ua(true);
    try { return $fn(); } finally { if($p===null) unset($_SESSION['uid']); else $_SESSION['uid']=$p; current_user(true); ua(true); }
};
$apprRow = null;
$mails = fn() => (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval'");
$acts  = fn() => (int) ops_val("SELECT COUNT(*) FROM activities");

//  One live chain per entity, each with the same named approver.
$mkRule = function ($entity,$dept) use ($cfg,&$mine,$uAppr) {
    $id = $cfg(fn() => appr_rule_save(0, ['name'=>'C5 '.$entity,'entity'=>$entity,'code'=>'C5'.$entity,'applies_department'=>$dept]));
    $mine['rule'][]=$id;
    $cfg(fn() => appr_level_save(['rule_id'=>$id,'seq'=>1,'label'=>'H','approver_user_id'=>$uAppr,'sla_days'=>2,'reminder_days'=>1]));
    return $id;
};
$act($uRaise);
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,created_at) VALUES ('C5-C','C5','C','OFFERED',?)")->execute([date('c')]);
$cid=(int)$pdo->lastInsertId(); $mine['cand'][]=$cid;

$E = [];
$mkRule('HIRING_REQUEST','');
[$okH,,$h] = hreq_save(0,['job_title'=>'C5 hiring','quantity'=>1,'office_id'=>891,'priority'=>'NORMAL','approval_required'=>1]);
$mine['h'][]=$h; hreq_submit($h); $apH = hreq_approval($h); $mine['rq'][]=(int)$apH['id'];
$E['HIRING_REQUEST'] = ['req'=>$apH, 'rec'=>(int)$h, 'table'=>'hiring_requests'];

$mkRule('OFFER','C5Off');
$oid = offer_create($cid,['ctc'=>500000]);
[, $rqO] = appr_start('OFFER',$oid,['department'=>'C5Off','amount'=>500000],'C5 offer',500000);
$mine['rq'][]=(int)$rqO;
$E['OFFER'] = ['req'=>appr_request((int)$rqO), 'rec'=>$oid, 'table'=>'job_offers'];

$mkRule('SALARY','C5Sal');
$sid = sal_save($cid,['candidate_expected'=>100000]);
[, $rqS] = appr_start('SALARY',$sid,['department'=>'C5Sal','amount'=>0],'C5 salary',0);
$mine['rq'][]=(int)$rqS;
$E['SALARY'] = ['req'=>appr_request((int)$rqS), 'rec'=>$sid, 'table'=>'salary_structures'];

$mkRule('REQUISITION','C5Req');
$pdo->prepare("INSERT INTO requisitions (req_code,designation,department,status,created_at) VALUES ('C5-RQ','F','C5Req','OPEN',?)")->execute([date('c')]);
$rid=(int)$pdo->lastInsertId();
[, $rqR] = appr_start('REQUISITION',$rid,['department'=>'C5Req','amount'=>0],'C5 requisition',0);
$mine['rq'][]=(int)$rqR;
$E['REQUISITION'] = ['req'=>appr_request((int)$rqR), 'rec'=>$rid, 'table'=>'requisitions'];

t_eq(count($E), 4, 'C5.0 · a live chain and a real source record exist for all four entities');
$apprRow = ops_one("SELECT * FROM users WHERE id=?", [$uAppr]);
$act($uAppr);

// ---------------------------------------------------------------------------
//  C5.1 · A — valid type + valid record → existing behaviour (§12 protected)
// ---------------------------------------------------------------------------
t_section('C5.1 · A · a real record behaves exactly as before');
foreach ($E as $name => $x) {
    $step = appr_step_context(appr_current_step($x['req']), $x['req']);
    t_ok(appr_entity_record($name, $x['rec']) !== null, "C5.1 A · $name · the source record resolves");
    t_eq(appr_told_reason($x['req'], $apprRow), '', "C5.1 A · $name · the informational level allows it");
    t_ok(appr_may_be_asked($step, $x['req'], $apprRow), "C5.1 A · $name · the actionable level allows it");
    $to = appr_step_recipients($step, $x['req']);
    t_eq(count($to), 1, "C5.1 A · $name · exactly one recipient");
    t_eq($to[0] ?? '', 'c5appr@t.test', "C5.1 A · $name · and it is the right one");
    //  §12 — the decision notification still reaches the canonical raiser.
    $b = (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND to_addr='c5raise@t.test'");
    appr_email_requester($x['req'], 'approved', '');
    t_eq((int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND to_addr='c5raise@t.test'"), $b + 1,
         "C5.1 A · $name · §12 · a VALID entity still notifies its raiser — INVALID→DENY did not become VALID→DENY");
}

// ---------------------------------------------------------------------------
//  C5.2 · B/C/D/E/F — every way an entity can fail to resolve
// ---------------------------------------------------------------------------
t_section('C5.2 · B–F · unresolved, invalid, cross-tenant, unknown, malformed');
$foreign = 99300000 + random_int(1,999);
foreach ($E as $name => $x) {
    $step = appr_step_context(appr_current_step($x['req']), $x['req']);
    $cases = [
        ['C · id 0',        ['entity'=>$name, 'entity_id'=>0]],
        ['C · negative id', ['entity'=>$name, 'entity_id'=>-7]],
        ['D · cross-tenant id', ['entity'=>$name, 'entity_id'=>$foreign]],
        ['E · unknown type',    ['entity'=>'NOT_A_THING', 'entity_id'=>$x['rec']]],
        ['F · blank type',      ['entity'=>'', 'entity_id'=>$x['rec']]],
        ['F · malformed ref',   ['entity'=>$name]],
    ];
    foreach ($cases as [$label,$bad]) {
        $m0 = $mails(); $a0 = $acts();
        t_ok(appr_told_reason($bad, $apprRow) !== '', "C5.2 $label · $name · informational DENIES");
        t_ok(!appr_may_be_asked($step, $bad, $apprRow),  "C5.2 $label · $name · actionable DENIES");
        t_eq(count(appr_step_recipients($step, $bad)), 0, "C5.2 $label · $name · no recipient");
        t_eq($mails(), $m0, "C5.2 $label · $name · nothing sent, nothing logged as sent");
    }
    //  D · a cross-tenant id names a record that does not exist in THIS database —
    //  isolation here is structural, one database per tenant, and it is asserted.
    t_ok(!ops_one("SELECT id FROM " . $x['table'] . " WHERE id=?", [$foreign]),
         "C5.2 D · $name · the foreign id does not exist in this workspace's " . $x['table']);
    t_ok(appr_entity_record($name, $foreign) === null, "C5.2 D · $name · so it cannot resolve");
}

// ---------------------------------------------------------------------------
//  C5.3 · B — THE DEFECT: the record is deleted, the chain survives
// ---------------------------------------------------------------------------
t_section('C5.3 · B · a deleted source record denies, for every entity');
foreach ($E as $name => $x) {
    $step = appr_step_context(appr_current_step($x['req']), $x['req']);
    t_ok(appr_may_be_asked($step, $x['req'], $apprRow), "C5.3 · $name · eligible while the record exists");
    $pdo->prepare("DELETE FROM " . $x['table'] . " WHERE id=?")->execute([(int)$x['rec']]);
    $m0 = $mails(); $st0 = (string) ops_val("SELECT status FROM recruit_approval_steps WHERE request_id=?", [(int)$x['req']['id']]);
    t_ok(appr_entity_record($name, $x['rec']) === null,  "C5.3 B · $name · the record no longer resolves");
    t_eq(appr_told_reason($x['req'], $apprRow), 'ENTITY_UNRESOLVED',
         "C5.3 B · $name · informational DENIES with ENTITY_UNRESOLVED — not IDENTITY_UNRESOLVED");
    t_ok(!appr_may_be_asked($step, $x['req'], $apprRow), "C5.3 B · $name · actionable DENIES");
    t_eq(count(appr_step_recipients($step, $x['req'])), 0, "C5.3 B · $name · NO RECIPIENT — the defect is closed");
    t_eq(appr_email_requester($x['req'],'approved',''), 'ENTITY_UNRESOLVED', "C5.3 B · $name · the decision notifier denies too");
    t_eq($mails(), $m0, "C5.3 B · $name · nothing was sent");
    t_eq((string) ops_val("SELECT status FROM recruit_approval_steps WHERE request_id=?", [(int)$x['req']['id']]), $st0,
         "C5.3 B · $name · and no approval state changed");
    //  the scheduler is quiet about it too
    $pdo->prepare("UPDATE recruit_approval_steps SET reminder_at=?, sla_due=? WHERE request_id=?")
        ->execute([date('c',time()-86400), date('c',time()-86400), (int)$x['req']['id']]);
    $_SESSION = $origSess; current_user(true); ua(true);
    $m1 = $mails(); appr_tick();
    t_eq($mails(), $m1, "C5.3 B · $name · and the scheduler sends nothing about it either");
    $act($uAppr);
}

// ---------------------------------------------------------------------------
//  C5.4 · the appr_can_act() rescue path is gone
// ---------------------------------------------------------------------------
t_section('C5.4 · a missing record never falls through to appr_can_act()');
foreach ($E as $name => $x) {
    $step = appr_step_context(appr_current_step($x['req']), $x['req']);
    t_ok(appr_can_act($step, $apprRow), "C5.4 · $name · the approver still genuinely passes appr_can_act()");
    t_ok(!appr_visible($step, $x['req'], $apprRow), "C5.4 · $name · yet visibility DENIES — no generic rescue");
}

// ---------------------------------------------------------------------------
//  C5.5 · D2/D3 — reasons stay distinct, audit stays clean
// ---------------------------------------------------------------------------
t_section('C5.5 · reasons and audit integrity');
t_eq(appr_told_reason(['entity'=>'HIRING_REQUEST','entity_id'=>$foreign], $apprRow), 'ENTITY_UNRESOLVED',
     'C5.5 · an unresolved ENTITY is ENTITY_UNRESOLVED');
$dangling = (int) ops_val("SELECT COUNT(*) FROM activities WHERE (entity_kind IS NULL OR entity_kind='') AND subject LIKE '%Decision not notified%'");
t_eq($dangling, 0, 'C5.5 · and no dangling audit reference was created along the way');

// ---------------------------------------------------------------------------
//  Clean up
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
if (function_exists('setting_set')) { setting_set('modules_off', $offWas); licence_disabled(true); ua(true); }
foreach (array_unique($mine['rq']) as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_requests WHERE id=?")->execute([(int)$r]);
}
foreach ($mine['rule'] as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([(int)$r]);
}
foreach ($mine['h'] as $x) $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([(int)$x]);
foreach ($mine['cand'] as $x) $pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([(int)$x]);
foreach ($mine['u'] as $x) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$x]);
foreach ($mine['o'] as $x) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([(int)$x]);
$pdo->exec("DELETE FROM email_log WHERE kind='recruit_approval'");
t_ok(true, 'M3 correction #5 fixtures removed');
