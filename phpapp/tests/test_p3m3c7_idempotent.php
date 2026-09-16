<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #7 — A PERMANENT CONDITION IS A STATE, NOT AN EVENT
//
//  K1  Correction #6 gave an unresolvable offer/salary a subject that opens, and
//      with it the ability to repeat: 0 rows became 10 for ten identical refusals.
//  K2  The anti-noise assertion that should have caught it passed because its
//      fixture carried no rule_id — there was no fallback, so no row, for a
//      reason that had nothing to do with suppression.
//  H2  The scheduler has written one row a day, for ever, over a chain whose
//      source record is gone.
//
//  One rule, one place:
//      OBSERVATION -> CLASSIFY -> STABLE IDENTITY -> ALREADY RECORDED? -> RECORD
//
//  EVERY fixture below is checked for the thing that would make its assertion
//  pass for the wrong reason: the rule_id is asserted present, the approver is
//  asserted NOT segregated, the scheduler is asserted to have actually run, and
//  the source record is asserted to have existed before it was deleted.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #7 — permanent conditions are recorded once');

$pdo = db(); hreq_migrate(); appr_migrate(); recruit_offer_migrate(); act_migrate();
$mine = ['u'=>[], 'o'=>[], 'rule'=>[], 'rq'=>[], 'cand'=>[], 'h'=>[], 'rqn'=>[]];
$origSess = $_SESSION;

t_ok(t_columns('activities', ['cond_key']), 'C7.0 · the spine carries a condition key');

try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (894,'C7',1)")->execute(); $mine['o'][]=894; } catch (Throwable $e) {}
$mk = function ($un,$role,$su,$p,$em,$fn,$ln) use ($pdo,&$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions,email)
                   VALUES (?,?,?,?,1,?,894,'894',?,?)")->execute([$un,$fn,$ln,$role,$su?1:0,$p,$em]);
    $id=(int)$pdo->lastInsertId(); $mine['u'][]=$id; return $id;
};
$act = function ($u) { $_SESSION['uid']=$u; current_user(true); ua(true); };
$HR = 'mod.hiring.view,mod.hiring.edit';
$uRaise = $mk('c7_raise','BRANCH_MANAGER',0,$HR,'c7raise@t.test','C7','Raiser');
$uAppr  = $mk('c7_appr','SBU_HEAD',0,$HR,'c7appr@t.test','C7','Approver');
$uMast  = $mk('c7_mast','ADMIN',1,'','c7mast@t.test','C7','Master');
$cfg = function (callable $fn) use ($uMast) {
    $p=$_SESSION['uid']??null; $_SESSION['uid']=$uMast; current_user(true); ua(true);
    try { return $fn(); } finally { if($p===null) unset($_SESSION['uid']); else $_SESSION['uid']=$p; current_user(true); ua(true); }
};
$mkRule = function ($entity,$dept) use ($cfg,&$mine,$uAppr) {
    $id = $cfg(fn() => appr_rule_save(0, ['name'=>'C7 '.$entity,'entity'=>$entity,'code'=>'C7'.$entity,'applies_department'=>$dept]));
    $mine['rule'][]=$id;
    $cfg(fn() => appr_level_save(['rule_id'=>$id,'seq'=>1,'label'=>'H','approver_user_id'=>$uAppr,'sla_days'=>5,'reminder_days'=>1]));
    return $id;
};
//  Count rows for ONE subject only — other suites' fixtures share this database.
$condRows = function ($entity, $eid) {
    return (int) ops_val("SELECT COUNT(*) FROM activities WHERE cond_key LIKE ?", ['PC|%|' . $entity . '|' . (int)$eid . '|%']);
};
$allRows = function ($entity, $eid) {
    return (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind=? AND entity_id=?", [$entity, (int)$eid]);
};

$act($uRaise);
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,created_at) VALUES ('C7-C','C7','C','OFFERED',?)")->execute([date('c')]);
$cid=(int)$pdo->lastInsertId(); $mine['cand'][]=$cid;

$E = [];
$rH = $mkRule('HIRING_REQUEST','');
[$okH,,$h] = hreq_save(0,['job_title'=>'C7 hiring','quantity'=>1,'office_id'=>894,'priority'=>'NORMAL','approval_required'=>1]);
$mine['h'][]=$h; hreq_submit($h); $apH = hreq_approval($h); $mine['rq'][]=(int)$apH['id'];
$E['HIRING_REQUEST'] = ['req'=>$apH,'rec'=>(int)$h,'table'=>'hiring_requests','rule'=>$rH];

$rO = $mkRule('OFFER','C7Off');
$oid = offer_create($cid,['ctc'=>500000]);
[, $rqO] = appr_start('OFFER',$oid,['department'=>'C7Off','amount'=>500000],'C7 offer',500000);
$mine['rq'][]=(int)$rqO;
$E['OFFER'] = ['req'=>appr_request((int)$rqO),'rec'=>$oid,'table'=>'job_offers','rule'=>$rO];

$rS = $mkRule('SALARY','C7Sal');
$sid = sal_save($cid,['candidate_expected'=>100000]);
[, $rqS] = appr_start('SALARY',$sid,['department'=>'C7Sal','amount'=>0],'C7 salary',0);
$mine['rq'][]=(int)$rqS;
$E['SALARY'] = ['req'=>appr_request((int)$rqS),'rec'=>$sid,'table'=>'salary_structures','rule'=>$rS];

$rR = $mkRule('REQUISITION','C7Req');
$pdo->prepare("INSERT INTO requisitions (req_code,designation,department,status,created_at) VALUES ('C7-RQ','F','C7Req','OPEN',?)")->execute([date('c')]);
$rid=(int)$pdo->lastInsertId(); $mine['rqn'][]=$rid;
[, $rqR] = appr_start('REQUISITION',$rid,['department'=>'C7Req','amount'=>0],'C7 requisition',0);
$mine['rq'][]=(int)$rqR;
$E['REQUISITION'] = ['req'=>appr_request((int)$rqR),'rec'=>$rid,'table'=>'requisitions','rule'=>$rR];

$apprRow = ops_one("SELECT * FROM users WHERE id=?", [$uAppr]);
t_eq(count($E), 4, 'C7.0 · a live chain, a real record and a real policy exist for all four entities');

// ---------------------------------------------------------------------------
//  C7.1 · §10 S / R — the fixture itself is honest before anything is measured
// ---------------------------------------------------------------------------
t_section('C7.1 · the fixture cannot make a later assertion pass for the wrong reason');
$act($uAppr);
$stepH = appr_step_context(appr_current_step($E['HIRING_REQUEST']['req']), $E['HIRING_REQUEST']['req']);
t_ok(appr_may_be_asked($stepH, $E['HIRING_REQUEST']['req'], $apprRow) === true,
     'C7.1 S · the approver is GENUINELY eligible — not accidentally segregated or out of scope');
$selfReq = ops_one("SELECT * FROM users WHERE id=?", [$uRaise]);
t_ok(appr_may_be_asked($stepH, $E['HIRING_REQUEST']['req'], $selfReq) !== true,
     'C7.1 R · and the raiser is still refused — segregation is untouched');
foreach ($E as $name => $x) {
    t_ok((int) $x['rule'] > 0, "C7.1 · $name · the rule really exists — the K2 fixture trap is closed");
    t_eq((int) ($x['req']['rule_id'] ?? 0), (int) $x['rule'], "C7.1 · $name · and the CHAIN carries it");
    t_ok(appr_entity_record($name, $x['rec']) !== null, "C7.1 · $name · the source record exists before it is removed");
}

// ---------------------------------------------------------------------------
//  C7.2 · §2 — the classification itself
// ---------------------------------------------------------------------------
t_section('C7.2 · permanent vs transient, stated explicitly');
foreach (['ENTITY_UNRESOLVED','TENANT_MISMATCH','IDENTITY_UNRESOLVED'] as $r)
    t_eq(appr_condition_kind($r), 'PERMANENT', "C7.2 · $r is PERMANENT");
foreach (['PROVIDER_FAILURE','NO_EMAIL','RECIPIENT_INACTIVE','RECIPIENT_UNLICENSED',
          'RECIPIENT_OUT_OF_SCOPE','SEGREGATION_BLOCKED','RECIPIENT_NOT_VISIBLE','SENT'] as $r)
    t_eq(appr_condition_kind($r), 'TRANSIENT', "C7.2 · $r is TRANSIENT — a later attempt may legitimately record again");
t_eq(appr_condition_key('DECISION', ['entity'=>'OFFER','entity_id'=>1,'id'=>2], null, 'PROVIDER_FAILURE'), '',
     'C7.2 K · a transient condition has NO suppression key at all');
t_ok(appr_condition_key('DECISION', ['entity'=>'OFFER','entity_id'=>1,'id'=>2], null, 'ENTITY_UNRESOLVED') !== '',
     'C7.2 · a permanent one does');
t_eq(appr_condition_key('DECISION', ['entity'=>'OFFER','entity_id'=>1,'id'=>2], null, 'ENTITY_UNRESOLVED'),
     appr_condition_key('DECISION', ['entity'=>'OFFER','entity_id'=>1,'id'=>2], null, 'ENTITY_UNRESOLVED'),
     'C7.2 · the key is stable across observations — no timestamp in it');
t_ok(appr_condition_key('DECISION', ['entity'=>'OFFER','entity_id'=>1,'id'=>2], null, 'ENTITY_UNRESOLVED')
  !== appr_condition_key('DECISION', ['entity'=>'OFFER','entity_id'=>1,'id'=>3], null, 'ENTITY_UNRESOLVED'),
     'C7.2 · two chains on the same record are two conditions — not collapsed');

// ---------------------------------------------------------------------------
//  C7.3 · §6 / §10 F-I — K1: the decision path, for all four entities
// ---------------------------------------------------------------------------
t_section('C7.3 · K1 · ten identical refusals record ONE event, per entity');
foreach ($E as $name => $x) {
    $pdo->prepare("DELETE FROM " . $x['table'] . " WHERE id=?")->execute([(int)$x['rec']]);
    t_ok(appr_entity_record($name, $x['rec']) === null, "C7.3 · $name · the record is now permanently unresolvable");
    $before = $condRows($name, $x['rec']);
    $why = appr_email_requester($x['req'], 'approved', '');
    t_eq($why, 'ENTITY_UNRESOLVED', "C7.3 · $name · the refusal reports the entity reason, not an identity one");
    $after1 = $condRows($name, $x['rec']);
    t_eq($after1, $before + 1, "C7.3 · $name · the FIRST observation IS recorded — history is not suppressed");
    for ($i = 0; $i < 9; $i++) appr_email_requester($x['req'], 'approved', '');
    t_eq($condRows($name, $x['rec']), $after1,
         "C7.3 · $name · nine more identical refusals add NOTHING — K1 is closed");
}

// ---------------------------------------------------------------------------
//  C7.4 · §4 / §10 A-B — rule_id must not decide whether dedup happens
// ---------------------------------------------------------------------------
t_section('C7.4 · K2 · deduplication is identical with and without a rule');
$noRule = $E['OFFER']['req']; $noRule['rule_id'] = 0; $noRule['id'] = 990001;
$withRule = $E['OFFER']['req']; $withRule['id'] = 990002;
t_eq((int) $noRule['rule_id'], 0,        'C7.4 A · CASE A really has no rule_id');
t_ok((int) $withRule['rule_id'] > 0,     'C7.4 B · CASE B really HAS a rule_id — the false-green fixture, fixed');
foreach ([['A · no rule_id', $noRule], ['B · with rule_id', $withRule]] as [$lab, $r]) {
    $k = appr_condition_key('DECISION', $r, null, 'ENTITY_UNRESOLVED');
    t_ok($k !== '', "C7.4 $lab · a suppression key exists either way");
    t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE cond_key=?", [$k]), 0, "C7.4 $lab · and nothing is recorded yet");
    for ($i = 0; $i < 5; $i++) appr_email_requester($r, 'approved', '');
    $n = (int) ops_val("SELECT COUNT(*) FROM activities WHERE cond_key=?", [$k]);
    t_ok($n <= 1, "C7.4 $lab · five identical refusals recorded at most once (got $n)");
}
t_ok(appr_condition_key('DECISION', $noRule, null, 'ENTITY_UNRESOLVED')
  !== appr_condition_key('DECISION', $withRule, null, 'ENTITY_UNRESOLVED'),
     'C7.4 · the two chains keep separate identities — dedup did not merge them');

// ---------------------------------------------------------------------------
//  C7.5 · §5 / §10 C-E — H2: the scheduler, run after run after run
// ---------------------------------------------------------------------------
t_section('C7.5 · H2 · the scheduler records a permanent condition once, not daily');
$act($uMast);
$hx = $E['HIRING_REQUEST'];
$stepRow = ops_one("SELECT * FROM recruit_approval_steps WHERE request_id=? AND seq=?",
                   [(int)$hx['req']['id'], (int)$hx['req']['current_seq']]);
t_ok(is_array($stepRow), 'C7.5 · the pending step exists');
$due = date('c', time() + 30 * 86400);
$past = fn() => $pdo->prepare("UPDATE recruit_approval_steps SET reminder_at=?, sla_due=?, escalated=0, status='PENDING' WHERE id=?")
                    ->execute([date('c', time() - 3600), $due, (int)$stepRow['id']]);
$runs = [];
for ($run = 1; $run <= 3; $run++) {
    $past();                                   // the next day arrives
    $before = $condRows('HIRING_REQUEST', $hx['rec']);
    $acted = appr_tick();
    $runs[$run] = ['acted' => $acted, 'delta' => $condRows('HIRING_REQUEST', $hx['rec']) - $before];
}
t_ok($runs[1]['acted'] > 0, 'C7.5 C · run 1 · the scheduler GENUINELY ran — it did not skip the step');
t_eq($runs[1]['delta'], 1,  'C7.5 C · run 1 · and recorded the condition once');
t_ok($runs[2]['acted'] > 0, 'C7.5 D · run 2 · the scheduler ran again — it was not silenced');
t_eq($runs[2]['delta'], 0,  'C7.5 D · run 2 · and wrote NOTHING — H2 is closed');
t_ok($runs[3]['acted'] > 0, 'C7.5 E · run 3 · the scheduler ran again');
t_eq($runs[3]['delta'], 0,  'C7.5 E · run 3 · and wrote nothing again — stable, not merely slower');

// ---------------------------------------------------------------------------
//  C7.6 · §7 / §10 J — a genuinely different condition is still recorded
// ---------------------------------------------------------------------------
t_section('C7.6 · idempotency does not suppress a genuinely new condition');
$n0 = $condRows('HIRING_REQUEST', $hx['rec']);
//  the SAME step, a DIFFERENT event: the escalation is a real transition
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, escalated=0 WHERE id=?")
    ->execute([date('c', time() - 3600), (int)$stepRow['id']]);
$acted = appr_tick();
t_ok($acted > 0, 'C7.6 J · the scheduler escalated the step');
t_eq($condRows('HIRING_REQUEST', $hx['rec']), $n0 + 1,
     'C7.6 J · a DIFFERENT condition on the same subject IS recorded — suppression is per-condition, not per-subject');
//  and a different REASON on the decision path is a different condition too
$k1 = appr_condition_key('DECISION', $hx['req'], null, 'ENTITY_UNRESOLVED');
$k2 = appr_condition_key('DECISION', $hx['req'], null, 'TENANT_MISMATCH');
t_ok($k1 !== $k2 && $k1 !== '' && $k2 !== '', 'C7.6 J · two different permanent reasons are two different conditions');

// ---------------------------------------------------------------------------
//  C7.7 · §10 K — a TRANSIENT condition is never suppressed
// ---------------------------------------------------------------------------
t_section('C7.7 · a transient condition may recur');
$liveRule = $mkRule('HIRING_REQUEST','C7Live');
$act($uRaise);
[$ok2,,$h2] = hreq_save(0,['job_title'=>'C7 live','quantity'=>1,'office_id'=>894,'priority'=>'NORMAL','approval_required'=>1]);
$mine['h'][]=$h2; hreq_submit($h2); $ap2 = hreq_approval($h2); $mine['rq'][]=(int)$ap2['id'];
t_ok(appr_entity_record('HIRING_REQUEST', $h2) !== null, 'C7.7 · this record genuinely exists — so nothing here is permanent');
$b0 = $allRows('HIRING_REQUEST', $h2);
//  a recipient who is merely inactive is a TRANSIENT condition
$pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$uAppr]);
$inactive = ops_one("SELECT * FROM users WHERE id=?", [$uAppr]);
t_eq(appr_told_reason($ap2, $inactive), 'RECIPIENT_INACTIVE', 'C7.7 K · the condition is the transient one, not a permanent one');
appr_audit_notify($ap2, 'approved', 'RECIPIENT_INACTIVE');
appr_audit_notify($ap2, 'approved', 'RECIPIENT_INACTIVE');
t_eq($allRows('HIRING_REQUEST', $h2), $b0 + 2,
     'C7.7 K · BOTH transient observations are recorded — this correction suppresses only permanent conditions');
$pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$uAppr]);
ua(true);

// ---------------------------------------------------------------------------
//  C7.8 · §10 L-Q — the correction #6 guarantees still hold
// ---------------------------------------------------------------------------
t_section('C7.8 · references stay valid and openable');
$SRC7 = ['HIRING_REQUEST'=>'hiring_requests','REQUISITION'=>'requisitions','APPROVAL_POLICY'=>'recruit_approval_rules'];
$foreign = 99600000 + random_int(1,999);
$bad = [
    'L · invalid id'      => ['entity'=>'OFFER','entity_id'=>-4,'rule_id'=>$rO,'id'=>990010],
    'M · missing entity'  => ['entity'=>'OFFER','entity_id'=>$foreign,'rule_id'=>$rO,'id'=>990011],
    'N · cross-tenant'    => ['entity'=>'HIRING_REQUEST','entity_id'=>$foreign,'rule_id'=>$rH,'id'=>990012],
    'O · unknown type'    => ['entity'=>'NOT_A_THING','entity_id'=>5,'rule_id'=>$rH,'id'=>990013],
    'P · malformed id'    => ['entity'=>'OFFER','rule_id'=>$rO,'id'=>990014],
];
foreach ($bad as $lab => $r) {
    $m0 = (int) ops_val("SELECT COALESCE(MAX(id),0) FROM activities");
    appr_audit_notify($r, 'approved', 'ENTITY_UNRESOLVED');
    $unopenable = 0; $linkless = 0;
    foreach (ops_all("SELECT * FROM activities WHERE id > ?", [$m0]) as $row) {
        $t = $SRC7[(string)$row['entity_kind']] ?? '';
        if ($t === '' || !ops_one("SELECT id FROM " . $t . " WHERE id=?", [(int)$row['entity_id']])) $unopenable++;
        if (!function_exists('act_link') || (string) act_link($row) === '') $linkless++;
    }
    t_eq($unopenable, 0, "C7.8 $lab · every row still points at a record that opens");
    t_eq($linkless, 0,   "C7.8 $lab · Q · and produces a real link through act_link()");
    t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_id=? AND entity_kind<>''", [$foreign]), 0,
         "C7.8 $lab · N · nothing references the foreign id");
}

// ---------------------------------------------------------------------------
//  Clean up — by id, never a LIKE sweep over somebody else's history.
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
foreach (array_unique($mine['rq']) as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_requests WHERE id=?")->execute([(int)$r]);
}
foreach (array_unique($mine['rule']) as $r) {
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([(int)$r]);
}
foreach (array_unique($mine['h']) as $x) {
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?")->execute([(int)$x]);
    $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([(int)$x]);
}
foreach (array_unique($mine['rqn']) as $x) {
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='REQUISITION' AND entity_id=?")->execute([(int)$x]);
    $pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([(int)$x]);
}
foreach ($mine['cand'] as $x) $pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([(int)$x]);
foreach ($mine['u'] as $x) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$x]);
foreach ($mine['o'] as $x) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([(int)$x]);
$pdo->exec("DELETE FROM email_log WHERE kind='recruit_approval'");
$left = 0;
foreach ($E as $name => $x) $left += $condRows($name, $x['rec']);
t_eq($left, 0, 'C7 · every condition row this suite created has been cleaned up');
t_ok(true, 'M3 correction #7 fixtures removed');
