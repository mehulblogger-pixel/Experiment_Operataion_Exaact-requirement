<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #3 — CANONICAL RAISER IDENTITY, AUDIT INTEGRITY
//                               & NOTIFICATION PRESERVATION
//
//  D1  correction #2 gave the hiring request a canonical identity and, by failing
//      closed everywhere else, SILENTLY STOPPED a working notification for offers,
//      salary structures and requisitions. Identity is now established for all of
//      them — without ever consulting a name.
//  D2  it also wrote a "could not identify the requester" row on every such
//      decision, with a blank entity_kind and a dangling id: a permanent condition
//      logged as an event, in rows nobody could follow.
//  D3  and it called every failure "no canonical requester identity", including
//      the ones where the identity resolved perfectly.
//
//  The architecture these tests hold:  IDENTITY → ELIGIBILITY → NOTIFICATION,
//  three questions, asked in that order, never conflated.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #3 — canonical raiser identity & audit integrity');

$pdo = db(); hreq_migrate(); appr_migrate(); recruit_offer_migrate();
$mine = ['u'=>[], 'o'=>[], 'rule'=>[], 'rq'=>[], 'cand'=>[], 'off'=>[], 'sal'=>[], 'req'=>[], 'h'=>[]];
$origSess = $_SESSION;
$offWas = function_exists('setting_get') ? (string) setting_get('modules_off', '') : '';

try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (931,'R3 Branch A',1)")->execute(); $mine['o'][]=931; } catch (Throwable $e) {}
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (932,'R3 Branch B',1)")->execute(); $mine['o'][]=932; } catch (Throwable $e) {}
$mk = function ($un,$role,$su,$off,$scope,$perms,$email,$fn,$ln) use ($pdo,&$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions,email)
                   VALUES (?,?,?,?,1,?,?,?,?,?)")->execute([$un,$fn,$ln,$role,$su?1:0,$off,$scope,$perms,$email]);
    $id=(int)$pdo->lastInsertId(); $mine['u'][]=$id; return $id;
};
$act = function ($uid) { $_SESSION['uid']=$uid; current_user(true); ua(true); };
$HR = 'mod.hiring.view,mod.hiring.edit';

//  The namesakes are created FIRST, exactly as in the audit that found the defect:
//  under any name-based lookup they would be found before the real raiser.
$gBranch = $mk('r3_gb','INSPECTOR',0,932,'932','', 'r3gb@t.test','Meera','Nair');   // other branch
$gPlain  = $mk('r3_gp','COORDINATOR',0,931,'931','','r3gp@t.test','Meera','Nair');   // no recruitment right
$uRaise  = $mk('r3_raise','BRANCH_MANAGER',0,931,'931',$HR,'r3raise@t.test','Meera','Nair');  // THE raiser
$uAppr   = $mk('r3_appr','SBU_HEAD',0,931,'931',$HR,'r3appr@t.test','R3','Approver');
$uMast   = $mk('r3_mast','ADMIN',1,931,'','','r3mast@t.test','R3','Master');

$cfg = function (callable $fn) use ($uMast) {
    $p=$_SESSION['uid']??null; $_SESSION['uid']=$uMast; current_user(true); ua(true);
    try { return $fn(); } finally { if($p===null) unset($_SESSION['uid']); else $_SESSION['uid']=$p; current_user(true); ua(true); }
};
$mkRule = function ($entity, $dept) use ($cfg, &$mine, $uAppr) {
    $id = $cfg(fn() => appr_rule_save(0, ['name'=>'R3 '.$entity,'entity'=>$entity,'code'=>'R3'.$entity,'applies_department'=>$dept]));
    $mine['rule'][] = $id;
    $cfg(fn() => appr_level_save(['rule_id'=>$id,'seq'=>1,'label'=>'Head','approver_user_id'=>$uAppr,'sla_days'=>2,'reminder_days'=>1]));
    return $id;
};
$mails = fn($a) => (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND to_addr=? AND subject LIKE '%APPROVED%'", [$a]);
$logged = fn($a) => (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND to_addr=?", [$a]);
$acts = fn($l) => (int) ops_val("SELECT COUNT(*) FROM activities WHERE subject LIKE ?", ['%'.$l.'%']);
$dangling = fn() => (int) ops_val("SELECT COUNT(*) FROM activities WHERE (entity_kind IS NULL OR entity_kind='') AND subject LIKE '%Decision not notified%'");
//  Decide a chain to its end, as the named approver.
$finish = function ($reqId) use ($act, $uAppr) {
    $act($uAppr);
    for ($i=0; $i<6; $i++) {
        $r = appr_request($reqId); if (!$r || $r['status'] !== 'PENDING') break;
        $st = appr_current_step($r); if (!$st) break;
        [$ok] = appr_act((int)$st['id'], 'approve', 'ok'); if (!$ok) break;
    }
    return appr_request($reqId);
};

// ---------------------------------------------------------------------------
//  R1 · the columns, and what they are for
// ---------------------------------------------------------------------------
t_section('R1 · canonical identity exists, additively');
t_ok(in_array('requester_id', t_columns('recruit_approval_requests'), true),
     'R1 · the approval chain carries the canonical raiser id — one column, every entity');
t_ok(in_array('created_by_id', t_columns('job_offers'), true), 'R1 · an offer carries who created it');
t_ok(in_array('created_by_id', t_columns('salary_structures'), true), 'R1 · so does a salary structure');
t_ok(in_array('created_by', t_columns('job_offers'), true),
     'R1 · and the display name is still there, doing the only job it was ever fit for');

// ---------------------------------------------------------------------------
//  R2 · HIRING REQUEST — D1-1, unchanged
// ---------------------------------------------------------------------------
t_section('R2 · the hiring request still works (D1-1)');
$rHR = $mkRule('HIRING_REQUEST','');
$act($uRaise);
[$okH,,$h] = hreq_save(0,['job_title'=>'R3 hiring','quantity'=>1,'office_id'=>931,'priority'=>'NORMAL','approval_required'=>1]);
$mine['h'][]=$h; hreq_submit($h);
$apH = hreq_approval($h);
t_ok($apH !== null, 'R2 · the chain started');
t_eq((int)($apH['requester_id'] ?? 0), $uRaise, 'R2 · and captured the canonical raiser at creation');
$bH = $mails('r3raise@t.test');
$finish((int)$apH['id']);
t_ok($mails('r3raise@t.test') > $bH, 'R2 · D1-1 · the canonical requester is told');
t_eq($mails('r3gb@t.test') + $mails('r3gp@t.test'), 0, 'R2 · and neither namesake is');

// ---------------------------------------------------------------------------
//  R3 · OFFER — D1-2 … D1-5. The regression the audit found.
// ---------------------------------------------------------------------------
t_section('R3 · an offer decision reaches its raiser again (D1-2…D1-5)');
$rOF = $mkRule('OFFER','R3Dept');
$act($uRaise);
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,created_at) VALUES ('R3-C1','R3','Cand','OFFERED',?)")->execute([date('c')]);
$cid = (int)$pdo->lastInsertId(); $mine['cand'][]=$cid;
$oid = offer_create($cid, ['ctc'=>500000]); $mine['off'][]=$oid;
t_eq((int) ops_val("SELECT created_by_id FROM job_offers WHERE id=?", [$oid]), $uRaise,
     'R3 · the offer captured WHO CREATED IT at creation');
[$st1,$rq1] = appr_start('OFFER', $oid, ['department'=>'R3Dept','amount'=>500000], 'R3 offer', 500000);
$mine['rq'][]=$rq1;
t_ok($st1, 'R3 · an approval chain started for the offer');
$b = $mails('r3raise@t.test'); $gb = $mails('r3gb@t.test'); $gp = $mails('r3gp@t.test');
$finish((int)$rq1);
t_ok($mails('r3raise@t.test') > $b,   'R3 · D1-2 · THE OFFER\'S CANONICAL RAISER IS TOLD — the lost notification is back');
t_eq($mails('r3gb@t.test'), $gb,      'R3 · D1-4 · the same-name person in another branch is not');
t_eq($mails('r3gp@t.test'), $gp,      'R3 · D1-3 · nor the same-name person with no recruitment right');

//  D1-5 — a historical offer, with no captured identity, fails closed.
$pdo->prepare("INSERT INTO job_offers (candidate_id,ctc,status,created_by,created_at) VALUES (?,?, 'PENDING_APPROVAL', 'Meera Nair', ?)")->execute([$cid, 400000, date('c')]);
$oidOld = (int)$pdo->lastInsertId(); $mine['off'][]=$oidOld;
$legacyReq = ['id'=>0,'entity'=>'OFFER','entity_id'=>$oidOld,'subject'=>'R3 legacy offer','requester'=>'Meera Nair'];
$b = $logged('r3raise@t.test'); $gb = $logged('r3gb@t.test');
$why = appr_email_requester($legacyReq, 'approved', '');
t_eq($why, 'IDENTITY_UNRESOLVED', 'R3 · D1-5 · a historical offer reports IDENTITY_UNRESOLVED');
t_eq($logged('r3raise@t.test'), $b, 'R3 · D1-5 · and tells nobody');
t_eq($logged('r3gb@t.test'), $gb,   'R3 · D1-5 · least of all the namesake the old lookup would have found');

// ---------------------------------------------------------------------------
//  R4 · SALARY — D1-6 … D1-8
// ---------------------------------------------------------------------------
t_section('R4 · a salary structure decision reaches its raiser (D1-6…D1-8)');
$rSA = $mkRule('SALARY','R3Sal');
$act($uRaise);
$sid = sal_save($cid, ['candidate_expected'=>100000]); $mine['sal'][]=$sid;
t_eq((int) ops_val("SELECT created_by_id FROM salary_structures WHERE id=?", [$sid]), $uRaise,
     'R4 · the salary structure captured who created it');
[$st2,$rq2] = appr_start('SALARY', $sid, ['department'=>'R3Sal','amount'=>0], 'R3 salary', 0);
$mine['rq'][]=$rq2;
$b = $mails('r3raise@t.test'); $gb = $mails('r3gb@t.test');
$finish((int)$rq2);
t_ok($mails('r3raise@t.test') > $b, 'R4 · D1-6 · the salary structure\'s canonical raiser is told');
t_eq($mails('r3gb@t.test'), $gb,    'R4 · D1-7 · the same-name person is not');
$legacySal = ['id'=>0,'entity'=>'SALARY','entity_id'=>99123456,'subject'=>'R3 legacy salary','requester'=>'Meera Nair'];
t_eq(appr_email_requester($legacySal,'approved',''), 'ENTITY_UNRESOLVED',
     'R4 · D1-8 · a salary structure that no longer exists fails closed, and says so');

// ---------------------------------------------------------------------------
//  R5 · REQUISITION — D1-9 … D1-11. Identity from the chain.
// ---------------------------------------------------------------------------
t_section('R5 · a requisition decision reaches its raiser (D1-9…D1-11)');
$rRQ = $mkRule('REQUISITION','R3Req');
$act($uRaise);
$pdo->prepare("INSERT INTO requisitions (req_code,designation,department,status,created_by,created_at) VALUES ('R3-RQ','Fitter','R3Req','OPEN','Meera Nair',?)")->execute([date('c')]);
$rid = (int)$pdo->lastInsertId(); $mine['req'][]=$rid;
[$st3,$rq3] = appr_start('REQUISITION', $rid, ['department'=>'R3Req','amount'=>0], 'R3 requisition', 0);
$mine['rq'][]=$rq3;
t_eq((int) (appr_request((int)$rq3)['requester_id'] ?? 0), $uRaise,
     'R5 · the requisition chain captured its raiser — the table itself carries no id, and none was invented');
$b = $mails('r3raise@t.test'); $gb = $mails('r3gb@t.test');
$finish((int)$rq3);
t_ok($mails('r3raise@t.test') > $b, 'R5 · D1-9 · the requisition\'s canonical raiser is told');
t_eq($mails('r3gb@t.test'), $gb,    'R5 · D1-10 · the same-name person is not');
$legacyRq = ['id'=>0,'entity'=>'REQUISITION','entity_id'=>$rid,'subject'=>'R3 legacy req','requester'=>'Meera Nair'];
t_eq(appr_email_requester($legacyRq,'approved',''), 'IDENTITY_UNRESOLVED',
     'R5 · D1-11 · a historical chain with no captured identity fails closed');

// ---------------------------------------------------------------------------
//  R6 · ALL ENTITIES — D1-12 … D1-15
// ---------------------------------------------------------------------------
t_section('R6 · identity is not authorization (D1-12…D1-15)');
$foreign = 99600000 + random_int(1,999);
//  Aimed at the REQUISITION chain deliberately: its only identity source is the
//  chain's own requester_id. Pointing this at the offer would prove nothing,
//  because the offer's own created_by_id correctly wins over anything on the
//  chain — which is itself worth stating, so it is asserted first.
$pdo->prepare("UPDATE recruit_approval_requests SET requester_id=? WHERE id=?")->execute([$foreign, (int)$rq1]);
t_ok(appr_email_requester(appr_request((int)$rq1),'approved','') !== 'TENANT_MISMATCH',
     'R6 · the offer\'s OWN created_by_id outranks a bad id on the chain');
$pdo->prepare("UPDATE recruit_approval_requests SET requester_id=? WHERE id=?")->execute([$uRaise, (int)$rq1]);
$pdo->prepare("UPDATE recruit_approval_requests SET requester_id=? WHERE id=?")->execute([$foreign, (int)$rq3]);
t_eq(appr_email_requester(appr_request((int)$rq3),'approved',''), 'TENANT_MISMATCH',
     'R6 · D1-12 · an id that does not exist in this workspace fails closed as TENANT_MISMATCH');
$pdo->prepare("UPDATE recruit_approval_requests SET requester_id=? WHERE id=?")->execute([$uRaise, (int)$rq3]);

$pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$uRaise]);
$b = $logged('r3raise@t.test');
t_eq(appr_email_requester(appr_request((int)$rq1),'approved',''), 'RECIPIENT_INACTIVE',
     'R6 · D1-13 · an inactive requester follows the existing policy, and is named as such');
t_eq($logged('r3raise@t.test'), $b, 'R6 · D1-13 · and nothing is sent');
$pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$uRaise]);

setting_set('modules_off','hr'); licence_disabled(true); ua(true);
t_eq(appr_email_requester(appr_request((int)$apH['id']),'approved',''), 'RECIPIENT_UNLICENSED',
     'R6 · D1-14 · an unlicensed workspace is reported as unlicensed, not as unknown');
setting_set('modules_off',$offWas); licence_disabled(true); ua(true);

$forged = ['id'=>0,'entity'=>'HIRING_REQUEST','entity_id'=>(int)$h,'subject'=>'forged','requester'=>'Meera Nair','requester_id'=>$gBranch];
$b = $logged('r3gb@t.test');
$whyF = appr_email_requester($forged,'approved','');
t_ok($whyF !== 'SENT', 'R6 · D1-15 · a hand-made request cannot post to an arbitrary id: ' . $whyF);
t_eq($logged('r3gb@t.test'), $b, 'R6 · D1-15 · the record\'s own identity wins over anything handed in');

// ---------------------------------------------------------------------------
//  R7 · D2 — audit integrity
// ---------------------------------------------------------------------------
t_section('R7 · the audit says something, once, about something real (D2)');
$d0 = $dangling(); $a0 = $acts('Decision not notified');
for ($i=0;$i<5;$i++) appr_email_requester($legacyReq,'approved','');
t_eq($acts('Decision not notified'), $a0,
     'R7 · D2-1 · five offer decisions with no identity write NO repetitive identity-missing rows');
t_eq($dangling(), $d0, 'R7 · D2-5 · and no dangling audit reference is created for an unlinkable entity');

$act($uRaise);
[$okH2,,$h2] = hreq_save(0,['job_title'=>'R3 clean','quantity'=>1,'office_id'=>931,'priority'=>'NORMAL','approval_required'=>1]);
$mine['h'][]=$h2; hreq_submit($h2);
$apH2 = hreq_approval($h2);
$a1 = $acts('Decision not notified');
$finish((int)$apH2['id']);
t_eq($acts('Decision not notified'), $a1, 'R7 · D2-2 · a record with canonical identity creates no identity-missing event at all');
t_ok($logged('r3raise@t.test') > 0, 'R7 · D2-4 · while every real send attempt stays observable in the existing outbox');
t_ok((int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND sent_ok=0 AND error<>''") >= 1,
     'R7 · D2-4 · including the ones the mailer could not deliver, with their error');
$rowsOk = (int) ops_val("SELECT COUNT(*) FROM activities WHERE subject LIKE '%Decision not notified%' AND entity_kind NOT IN ('HIRING_REQUEST','REQUISITION')");
t_eq($rowsOk, 0, 'R7 · D2-5 · every audit row that IS written points at a supported, traceable entity');

// ---------------------------------------------------------------------------
//  R8 · D3 — the reason is the real reason
// ---------------------------------------------------------------------------
t_section('R8 · accurate failure reasons (D3)');
foreach (['IDENTITY_UNRESOLVED','RECIPIENT_INACTIVE','RECIPIENT_UNLICENSED','RECIPIENT_OUT_OF_SCOPE',
          'RECIPIENT_NOT_VISIBLE','SEGREGATION_BLOCKED','TENANT_MISMATCH','ENTITY_UNRESOLVED','PROVIDER_FAILURE'] as $code)
    t_ok(isset(APPR_NOTIFY_REASONS[$code]), 'R8 · the vocabulary defines ' . $code);
t_eq(appr_email_requester(['entity'=>'HIRING_REQUEST','entity_id'=>99777777,'subject'=>'x'],'approved',''), 'ENTITY_UNRESOLVED',
     'R8 · D3-6 · a missing entity → ENTITY_UNRESOLVED');
t_eq(appr_email_requester($legacyRq,'approved',''), 'IDENTITY_UNRESOLVED', 'R8 · D3-1 · missing identity → IDENTITY_UNRESOLVED');
//  out of scope: the raiser is moved to the other branch, so the hiring request is no longer theirs to see
$pdo->prepare("UPDATE users SET home_office_id=932, scope_offices='932' WHERE id=?")->execute([$uRaise]);
t_eq(appr_email_requester(appr_request((int)$apH['id']),'approved',''), 'RECIPIENT_OUT_OF_SCOPE',
     'R8 · D3-3 · out of scope → RECIPIENT_OUT_OF_SCOPE, not "unknown identity"');
$pdo->prepare("UPDATE users SET home_office_id=931, scope_offices='931' WHERE id=?")->execute([$uRaise]);
//  a successful, deliverable-attempt path reports the delivery truth
$why = appr_email_requester(appr_request((int)$apH['id']),'approved','');
t_ok(in_array($why, ['SENT','PROVIDER_FAILURE'], true),
     'R8 · D3-7 · an eligible recipient reports the DELIVERY outcome (' . $why . '), not an identity problem');
t_ok(!in_array($why, ['IDENTITY_UNRESOLVED'], true), 'R8 · and never collapses into IDENTITY_UNRESOLVED');
//  segregation is deliberately NOT applied to telling somebody their own outcome
$act($uRaise);
t_eq(appr_told_reason(appr_request((int)$apH['id']), ops_one("SELECT * FROM users WHERE id=?", [$uRaise])), '',
     'R8 · D3-5 · segregation does not silence telling the raiser their OWN outcome — by design');

// ---------------------------------------------------------------------------
//  Clean up
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
if (function_exists('setting_set')) { setting_set('modules_off', $offWas); licence_disabled(true); ua(true); }
foreach ($mine['h'] as $hh) {
    $rq = ops_one("SELECT id FROM recruit_approval_requests WHERE entity='HIRING_REQUEST' AND entity_id=?", [(int)$hh]);
    if ($rq) $mine['rq'][] = (int)$rq['id'];
    $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([(int)$hh]);
}
foreach (array_unique($mine['rq']) as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_requests WHERE id=?")->execute([(int)$r]);
}
foreach ($mine['rule'] as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([(int)$r]);
}
foreach ($mine['off'] as $x) $pdo->prepare("DELETE FROM job_offers WHERE id=?")->execute([(int)$x]);
foreach ($mine['sal'] as $x) $pdo->prepare("DELETE FROM salary_structures WHERE id=?")->execute([(int)$x]);
foreach ($mine['req'] as $x) $pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([(int)$x]);
foreach ($mine['cand'] as $x) $pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([(int)$x]);
foreach ($mine['u'] as $x) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$x]);
foreach ($mine['o'] as $x) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([(int)$x]);
$pdo->exec("DELETE FROM email_log WHERE kind='recruit_approval'");
t_ok(true, 'M3 correction #3 fixtures removed');
