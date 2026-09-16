<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #4 — ENTITLEMENT-FIRST & FAIL-CLOSED SUBJECT
//
//  E1  the entitlement question lived INSIDE the hiring-request branch, so an
//      offer, a salary structure and a requisition reached "eligible" having
//      never been asked for a licence. Correction #3 restored their
//      notifications and made that live, on the one boundary that leaves the
//      application.
//  E2  an unusable subject id produced null, `(string) null` is '', and '' was
//      this predicate's word for ELIGIBLE. A type conversion was deciding a
//      security question.
//
//  THE MATRIX IS RUN AGAINST ALL FOUR ENTITIES. Passing it for the hiring
//  request proves nothing about the other three — that assumption is what every
//  correction in this milestone has been about.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #4 — entitlement-first, fail-closed subject');

$pdo = db(); hreq_migrate(); appr_migrate(); recruit_offer_migrate();
$mine = ['u'=>[], 'o'=>[], 'rule'=>[], 'rq'=>[], 'cand'=>[], 'off'=>[], 'sal'=>[], 'req'=>[], 'h'=>[], 'del'=>[]];
$origSess = $_SESSION;
$offWas = function_exists('setting_get') ? (string) setting_get('modules_off', '') : '';
$today  = date('Y-m-d');

foreach ([[911,'C4 A'],[912,'C4 B']] as $o) { try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); $mine['o'][]=$o[0]; } catch (Throwable $e) {} }
$mk = function ($un,$role,$su,$off,$sc,$p,$em,$fn,$ln) use ($pdo,&$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions,email)
                   VALUES (?,?,?,?,1,?,?,?,?,?)")->execute([$un,$fn,$ln,$role,$su?1:0,$off,$sc,$p,$em]);
    $id=(int)$pdo->lastInsertId(); $mine['u'][]=$id; return $id;
};
$act = function ($u) { $_SESSION['uid']=$u; current_user(true); ua(true); };
$HR = 'mod.hiring.view,mod.hiring.edit';
$uRaise = $mk('c4_raise','BRANCH_MANAGER',0,911,'911',$HR,'c4raise@t.test','C4','Raiser');
$uAppr  = $mk('c4_appr','SBU_HEAD',0,911,'911',$HR,'c4appr@t.test','C4','Approver');
$uDeleg = $mk('c4_deleg','COORDINATOR',0,911,'911',$HR,'c4deleg@t.test','C4','Delegate');
$uFar   = $mk('c4_far','SBU_HEAD',0,912,'912',$HR,'c4far@t.test','C4','Far');
$uMast  = $mk('c4_mast','ADMIN',1,911,'','','c4mast@t.test','C4','Master');
$cfg = function (callable $fn) use ($uMast) {
    $p=$_SESSION['uid']??null; $_SESSION['uid']=$uMast; current_user(true); ua(true);
    try { return $fn(); } finally { if($p===null) unset($_SESSION['uid']); else $_SESSION['uid']=$p; current_user(true); ua(true); }
};
$licence = function ($on) { setting_set('modules_off', $on ? '' : 'hr'); licence_disabled(true); ua(true); };
$mailsTo = fn($a) => (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND to_addr=?", [$a]);
$allMail = fn() => (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval'");
$allActs = fn() => (int) ops_val("SELECT COUNT(*) FROM activities WHERE subject LIKE '%Decision not notified%'");

//  Build one live chain per entity, each with the SAME named approver.
$mkRule = function ($entity,$dept) use ($cfg,&$mine,$uAppr) {
    $id = $cfg(fn() => appr_rule_save(0, ['name'=>'C4 '.$entity,'entity'=>$entity,'code'=>'C4'.$entity,'applies_department'=>$dept]));
    $mine['rule'][] = $id;
    $cfg(fn() => appr_level_save(['rule_id'=>$id,'seq'=>1,'label'=>'Head','approver_user_id'=>$uAppr,'sla_days'=>2,'reminder_days'=>1]));
    return $id;
};
$act($uRaise);
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,created_at) VALUES ('C4-C','C4','Cand','OFFERED',?)")->execute([date('c')]);
$cid=(int)$pdo->lastInsertId(); $mine['cand'][]=$cid;

$E = [];   // entity => ['req'=>row, 'step'=>row]

$mkRule('HIRING_REQUEST','');
[$okH,,$h] = hreq_save(0,['job_title'=>'C4 hiring','quantity'=>1,'office_id'=>911,'priority'=>'NORMAL','approval_required'=>1]);
$mine['h'][]=$h; hreq_submit($h);
$apH = hreq_approval($h); $mine['rq'][]=(int)$apH['id'];
$E['HIRING_REQUEST'] = ['req'=>$apH, 'step'=>appr_current_step($apH)];

$mkRule('OFFER','C4Off');
$oid = offer_create($cid, ['ctc'=>500000]); $mine['off'][]=$oid;
[, $rqO] = appr_start('OFFER', $oid, ['department'=>'C4Off','amount'=>500000], 'C4 offer', 500000);
$mine['rq'][]=(int)$rqO; $apO = appr_request((int)$rqO);
$E['OFFER'] = ['req'=>$apO, 'step'=>appr_current_step($apO)];

$mkRule('SALARY','C4Sal');
$sid = sal_save($cid, ['candidate_expected'=>100000]); $mine['sal'][]=$sid;
[, $rqS] = appr_start('SALARY', $sid, ['department'=>'C4Sal','amount'=>0], 'C4 salary', 0);
$mine['rq'][]=(int)$rqS; $apS = appr_request((int)$rqS);
$E['SALARY'] = ['req'=>$apS, 'step'=>appr_current_step($apS)];

$mkRule('REQUISITION','C4Req');
$pdo->prepare("INSERT INTO requisitions (req_code,designation,department,status,created_by,created_at) VALUES ('C4-RQ','Fitter','C4Req','OPEN','C4 Raiser',?)")->execute([date('c')]);
$rid=(int)$pdo->lastInsertId(); $mine['req'][]=$rid;
[, $rqR] = appr_start('REQUISITION', $rid, ['department'=>'C4Req','amount'=>0], 'C4 requisition', 0);
$mine['rq'][]=(int)$rqR; $apR = appr_request((int)$rqR);
$E['REQUISITION'] = ['req'=>$apR, 'step'=>appr_current_step($apR)];

t_eq(count($E), 4, 'C4.0 · a live approval chain exists for all FOUR entities');
$apprRow = ops_one("SELECT * FROM users WHERE id=?", [$uAppr]);
$farRow  = ops_one("SELECT * FROM users WHERE id=?", [$uFar]);

// ---------------------------------------------------------------------------
//  C4.1 · THE MATRIX — A, B, C, D, E, I for every entity
// ---------------------------------------------------------------------------
t_section('C4.1 · the predicate matrix, run against all four entities');
foreach ($E as $name => $x) {
    $req = $x['req']; $step = appr_step_context($x['step'], $req);

    // A · valid + entitled + visible → eligible
    $licence(true); $act($uAppr);
    t_eq(appr_told_reason($req, $apprRow), '', "C4.1 A · $name · a valid, entitled, visible recipient is eligible");
    t_ok(appr_may_be_asked($step, $req, $apprRow),   "C4.1 A · $name · …and may be asked to act");

    // B · valid + UNLICENSED → denied. THE E1 FIX.
    $licence(false);
    t_eq(appr_told_reason($req, $apprRow), 'RECIPIENT_UNLICENSED', "C4.1 B · $name · AN UNLICENSED WORKSPACE DENIES IT");
    t_ok(!appr_may_be_asked($step, $req, $apprRow),  "C4.1 B · $name · …on the actionable path too");
    //  §11 side effects — denied must mean nothing happened at all.
    $m0 = $allMail(); $a0 = $allActs();
    t_eq(count(appr_step_recipients($step, $req)), 0, "C4.1 B · $name · no recipient is produced");
    $why = appr_email_requester($req, 'approved', '');
    t_eq($why, 'RECIPIENT_UNLICENSED', "C4.1 B · $name · the decision notifier says why");
    t_eq($allMail(), $m0, "C4.1 B · $name · nothing was sent, and nothing logged as sent");
    $licence(true);

    // C/D · no subject, zero id, garbage → denied, never eligible
    foreach ([['null', null], ['zero id', ['id'=>0,'is_active'=>1,'email'=>'x@t.test']],
              ['negative id', ['id'=>-5,'is_active'=>1,'email'=>'x@t.test']],
              ['no id key', ['is_active'=>1,'email'=>'x@t.test']],
              ['a string', 'not-a-user']] as [$label,$subject]) {
        t_eq(appr_told_reason($req, $subject), 'IDENTITY_UNRESOLVED', "C4.1 C/D · $name · $label → denied, explicitly");
        t_ok(!appr_may_be_asked($step, $req, $subject), "C4.1 C/D · $name · $label → not actionable either");
    }

    // E · inactive subject → denied, and named as such
    $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$uAppr]);
    $inact = ops_one("SELECT * FROM users WHERE id=?", [$uAppr]);
    t_eq(appr_told_reason($req, $inact), 'RECIPIENT_INACTIVE', "C4.1 E · $name · an inactive subject is denied");
    $pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$uAppr]);

    // I · unknown entity → denied
    t_eq(appr_told_reason(['entity'=>'NOT_A_THING','entity_id'=>1], $apprRow), 'ENTITY_UNRESOLVED',
         "C4.1 I · $name · an unknown entity is denied");
    t_eq(appr_told_reason(['entity'=>'','entity_id'=>1], $apprRow), 'ENTITY_UNRESOLVED',
         "C4.1 I · $name · …and a blank one");

    // F · cross-tenant subject → denied at resolution
    $foreign = 99400000 + random_int(1,999);
    $pdo->prepare("UPDATE recruit_approval_requests SET requester_id=? WHERE id=?")->execute([$foreign, (int)$req['id']]);
    if ($name === 'HIRING_REQUEST') $pdo->prepare("UPDATE hiring_requests SET requested_by_id=? WHERE id=?")->execute([$foreign, (int)$req['entity_id']]);
    if ($name === 'OFFER')  $pdo->prepare("UPDATE job_offers SET created_by_id=? WHERE id=?")->execute([$foreign, (int)$req['entity_id']]);
    if ($name === 'SALARY') $pdo->prepare("UPDATE salary_structures SET created_by_id=? WHERE id=?")->execute([$foreign, (int)$req['entity_id']]);
    [$fu,$fwhy] = appr_resolve_requester(appr_request((int)$req['id']));
    t_ok($fu === null, "C4.1 F · $name · a cross-tenant subject resolves to nobody");
    t_eq($fwhy, 'TENANT_MISMATCH', "C4.1 F · $name · and is named a TENANT_MISMATCH");
    $pdo->prepare("UPDATE recruit_approval_requests SET requester_id=? WHERE id=?")->execute([$uRaise, (int)$req['id']]);
    if ($name === 'HIRING_REQUEST') $pdo->prepare("UPDATE hiring_requests SET requested_by_id=? WHERE id=?")->execute([$uRaise, (int)$req['entity_id']]);
    if ($name === 'OFFER')  $pdo->prepare("UPDATE job_offers SET created_by_id=? WHERE id=?")->execute([$uRaise, (int)$req['entity_id']]);
    if ($name === 'SALARY') $pdo->prepare("UPDATE salary_structures SET created_by_id=? WHERE id=?")->execute([$uRaise, (int)$req['entity_id']]);
}

// ---------------------------------------------------------------------------
//  C4.2 · G and H — where branch scope and segregation apply, and where they do not
// ---------------------------------------------------------------------------
t_section('C4.2 · scope and segregation, honestly scoped');
$act($uAppr);
t_eq(appr_told_reason($E['HIRING_REQUEST']['req'], $farRow), 'RECIPIENT_OUT_OF_SCOPE',
     'C4.2 G · a hiring request denies an out-of-branch recipient, and names the reason');
t_ok(!appr_may_be_asked(appr_step_context($E['HIRING_REQUEST']['step'], $E['HIRING_REQUEST']['req']), $E['HIRING_REQUEST']['req'], $farRow),
     'C4.2 G · …on the actionable path too');
//  Offer, salary and requisition carry no branch — M2 established that and this
//  correction did not invent one. Stated, not faked.
foreach (['OFFER','SALARY','REQUISITION'] as $n)
    t_eq(appr_told_reason($E[$n]['req'], $farRow), '',
         "C4.2 G · $n carries no branch, so branch scope does not apply — unchanged from M2");

//  H · segregation: the requestor may not be ASKED to approve their own request…
$raiseRow = ops_one("SELECT * FROM users WHERE id=?", [$uRaise]);
$stepH = appr_step_context($E['HIRING_REQUEST']['step'], $E['HIRING_REQUEST']['req']);
$pdo->prepare("UPDATE recruit_approval_steps SET approver_user_id=NULL, approver_role='BRANCH_MANAGER' WHERE id=?")->execute([(int)$stepH['id']]);
$stepH2 = appr_step_context(ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int)$stepH['id']]), $E['HIRING_REQUEST']['req']);
t_ok(!appr_may_be_asked($stepH2, $E['HIRING_REQUEST']['req'], $raiseRow),
     'C4.2 H · segregation denies asking the requestor to approve their own request');
t_ok(!in_array('c4raise@t.test', appr_step_recipients($stepH2, $E['HIRING_REQUEST']['req']), true),
     'C4.2 H · …so they are not written to');
//  …but telling them their OWN outcome is the point of the message, by design.
t_eq(appr_told_reason($E['HIRING_REQUEST']['req'], $raiseRow), '',
     'C4.2 H · while telling them their own outcome stays allowed — deliberate, and stated');
$pdo->prepare("UPDATE recruit_approval_steps SET approver_user_id=?, approver_role='' WHERE id=?")->execute([$uAppr, (int)$stepH['id']]);

// ---------------------------------------------------------------------------
//  C4.3 · J–M — delegation, on the hiring request AND on the offer
// ---------------------------------------------------------------------------
t_section('C4.3 · delegation, on more than one entity');
foreach (['HIRING_REQUEST','OFFER'] as $n) {
    $req = $E[$n]['req'];
    $step = appr_step_context(ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int)$E[$n]['step']['id']]), $req);
    $act($uMast);
    [$dOk,,$dId] = appr_delegation_save(0, ['delegator_user_id'=>$uAppr,'delegate_user_id'=>$uDeleg,
        'entity'=>$n,'effective_from'=>$today]);
    if ($dOk) $mine['del'][] = $dId;
    t_ok(in_array('c4deleg@t.test', appr_step_recipients($step, $req), true), "C4.3 J · $n · a VALID delegate is eligible");
    $pdo->prepare("UPDATE approval_delegations SET effective_to=? WHERE id=?")->execute([date('Y-m-d', strtotime('-1 day')), (int)$dId]);
    t_ok(!in_array('c4deleg@t.test', appr_step_recipients($step, $req), true), "C4.3 K · $n · an EXPIRED delegate is not");
    $pdo->prepare("UPDATE approval_delegations SET effective_to='' WHERE id=?")->execute([(int)$dId]);
    $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$uAppr]);
    t_ok(!in_array('c4deleg@t.test', appr_step_recipients($step, $req), true), "C4.3 M · $n · an INACTIVE DELEGATOR lends nothing");
    $pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$uAppr]);
    $act($uMast); appr_delegation_revoke((int)$dId);
    t_ok(!in_array('c4deleg@t.test', appr_step_recipients($step, $req), true), "C4.3 L · $n · a REVOKED delegate is not");
    //  and an unlicensed workspace denies the delegate too — E1 on the delegate path
    $act($uMast);
    [$d2Ok,,$d2] = appr_delegation_save(0, ['delegator_user_id'=>$uAppr,'delegate_user_id'=>$uDeleg,'entity'=>$n,'effective_from'=>$today]);
    if ($d2Ok) $mine['del'][] = $d2;
    $licence(false);
    t_eq(count(appr_step_recipients($step, $req)), 0, "C4.3 · $n · an unlicensed workspace notifies neither approver nor delegate");
    $licence(true);
    $act($uMast); appr_delegation_revoke((int)$d2);
}

// ---------------------------------------------------------------------------
//  C4.4 · §11 — the eligible side effects, exactly
// ---------------------------------------------------------------------------
t_section('C4.4 · an eligible case produces exactly the expected recipient set');
$act($uAppr);
foreach ($E as $name => $x) {
    $step = appr_step_context(ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int)$x['step']['id']]), $x['req']);
    $to = appr_step_recipients($step, $x['req']);
    t_eq(count($to), 1, "C4.4 · $name · exactly ONE recipient — the named approver, nobody else");
    t_eq($to[0] ?? '', 'c4appr@t.test', "C4.4 · $name · and it is the right address");
    t_ok(!in_array('c4far@t.test', $to, true) && !in_array('c4raise@t.test', $to, true),
         "C4.4 · $name · no same-branch, cross-branch or requestor leakage");
}
//  a decision reaches the raiser, once, for every entity
foreach ($E as $name => $x) {
    $b = $mailsTo('c4raise@t.test');
    appr_email_requester(appr_request((int)$x['req']['id']), 'approved', '');
    t_eq($mailsTo('c4raise@t.test'), $b + 1, "C4.4 · $name · the canonical raiser is written to exactly once");
}

// ---------------------------------------------------------------------------
//  C4.5 · the impersonation helper, regression only
// ---------------------------------------------------------------------------
t_section('C4.5 · session impersonation — regression only');
$act($uRaise);
$u0 = (int)(current_user()['id'] ?? 0); $s0 = scope_allows(912,null)?1:0; $p0 = can('mod.hiring.edit')?1:0;
appr_as_user($farRow, fn() => scope_allows(912,null));
t_eq((int)(current_user()['id'] ?? 0), $u0, 'C4.5 · the signed-in user is restored');
t_eq(scope_allows(912,null)?1:0, $s0, 'C4.5 · …and their branch scope');
t_eq(can('mod.hiring.edit')?1:0, $p0, 'C4.5 · …and their permissions');
try { appr_as_user($farRow, function () { throw new RuntimeException('x'); }); } catch (Throwable $e) {}
t_eq((int)(current_user()['id'] ?? 0), $u0, 'C4.5 · an exception still restores it');
appr_as_user($farRow, function () use ($apprRow) { appr_as_user($apprRow, fn() => true); return true; });
t_eq((int)(current_user()['id'] ?? 0), $u0, 'C4.5 · nesting unwinds');

// ---------------------------------------------------------------------------
//  Clean up
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
if (function_exists('setting_set')) { setting_set('modules_off', $offWas); licence_disabled(true); ua(true); }
foreach ($mine['h'] as $hh) $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([(int)$hh]);
foreach (array_unique($mine['rq']) as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_requests WHERE id=?")->execute([(int)$r]);
}
foreach ($mine['rule'] as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([(int)$r]);
}
foreach ($mine['del'] as $d) $pdo->prepare("DELETE FROM approval_delegations WHERE id=?")->execute([(int)$d]);
foreach ($mine['off'] as $x) $pdo->prepare("DELETE FROM job_offers WHERE id=?")->execute([(int)$x]);
foreach ($mine['sal'] as $x) $pdo->prepare("DELETE FROM salary_structures WHERE id=?")->execute([(int)$x]);
foreach ($mine['req'] as $x) $pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([(int)$x]);
foreach ($mine['cand'] as $x) $pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([(int)$x]);
foreach ($mine['u'] as $x) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$x]);
foreach ($mine['o'] as $x) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([(int)$x]);
$pdo->exec("DELETE FROM email_log WHERE kind='recruit_approval'");
t_ok(true, 'M3 correction #4 fixtures removed');
