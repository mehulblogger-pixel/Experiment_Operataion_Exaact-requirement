<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #6 — AN AUDIT REFERENCE MUST BE OPENABLE
//
//  J1  The audit trail guarded itself with a check on the entity TYPE and called
//      that "never a dangling reference", while the reason it was most often
//      writing under — ENTITY_UNRESOLVED — means the RECORD is gone. And the SLA
//      writer had no check at all, so an offer or salary event reached act_log(),
//      which blanks an unsupported kind and keeps the id: a row with a live
//      entity_id and no type at all.
//
//          A SUPPORTED TYPE  ≠  AN OPENABLE RECORD
//
//  Every assertion below therefore does what the old test did not: it takes the
//  entity_kind and entity_id the row actually claims, and TRIES TO OPEN THEM.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #6 — every audit reference opens');

$pdo = db(); hreq_migrate(); appr_migrate(); recruit_offer_migrate(); act_migrate();
$mine = ['u'=>[], 'o'=>[], 'rule'=>[], 'rq'=>[], 'cand'=>[], 'h'=>[], 'act'=>[]];
$origSess = $_SESSION;

try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (893,'C6 A',1)")->execute(); $mine['o'][]=893; } catch (Throwable $e) {}
$mk = function ($un,$role,$su,$p,$em,$fn,$ln) use ($pdo,&$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions,email)
                   VALUES (?,?,?,?,1,?,893,'893',?,?)")->execute([$un,$fn,$ln,$role,$su?1:0,$p,$em]);
    $id=(int)$pdo->lastInsertId(); $mine['u'][]=$id; return $id;
};
$act = function ($u) { $_SESSION['uid']=$u; current_user(true); ua(true); };
$HR = 'mod.hiring.view,mod.hiring.edit';
$uRaise = $mk('c6_raise','BRANCH_MANAGER',0,$HR,'c6raise@t.test','C6','Raiser');
$uAppr  = $mk('c6_appr','SBU_HEAD',0,$HR,'c6appr@t.test','C6','Approver');
$uMast  = $mk('c6_mast','ADMIN',1,'','c6mast@t.test','C6','Master');
$cfg = function (callable $fn) use ($uMast) {
    $p=$_SESSION['uid']??null; $_SESSION['uid']=$uMast; current_user(true); ua(true);
    try { return $fn(); } finally { if($p===null) unset($_SESSION['uid']); else $_SESSION['uid']=$p; current_user(true); ua(true); }
};

// ---------------------------------------------------------------------------
//  THE RESOLVER THE TEST USES. Deliberately independent of the code under test:
//  it goes straight to the source table, so it cannot inherit the very mistake
//  it is checking for.
// ---------------------------------------------------------------------------
$SRC = ['HIRING_REQUEST'=>'hiring_requests', 'REQUISITION'=>'requisitions', 'OFFER'=>'job_offers',
        'SALARY'=>'salary_structures', 'APPROVAL_POLICY'=>'recruit_approval_rules'];
$open = function ($kind, $id) use ($SRC) {
    $kind = (string) $kind; $id = (int) $id;
    if ($kind === '' || $id <= 0) return false;                       // no type, or no id
    if (!array_key_exists($kind, ACT_ENTITIES)) return false;         // the timeline cannot even label it
    $t = $SRC[$kind] ?? '';
    if ($t === '' || !t_table_exists($t)) return false;
    return (bool) ops_one("SELECT id FROM " . $t . " WHERE id=?", [$id]);
};
$maxAct = fn() => (int) ops_val("SELECT COALESCE(MAX(id),0) FROM activities");
$since  = function ($m) { return ops_all("SELECT id, entity_kind, entity_id, subject FROM activities WHERE id > ? ORDER BY id", [(int)$m]); };
//  The one assertion this whole correction exists for.
$allOpen = function ($rows, $label) use ($open, &$mine) {
    $bad = 0; $orphan = 0;
    foreach ($rows as $r) {
        $mine['act'][] = (int) $r['id'];
        if (!$open($r['entity_kind'], $r['entity_id'])) $bad++;
        if ((string) $r['entity_kind'] === '' && (int) $r['entity_id'] > 0) $orphan++;
    }
    t_eq($bad, 0, $label . ' · every row it wrote points at a record that OPENS');
    t_eq($orphan, 0, $label . ' · and none is an orphan (blank type, live id)');
    return count($rows);
};

// ---------------------------------------------------------------------------
//  Fixture — a live chain and a real source record for each of the four.
// ---------------------------------------------------------------------------
$mkRule = function ($entity,$dept) use ($cfg,&$mine,$uAppr) {
    $id = $cfg(fn() => appr_rule_save(0, ['name'=>'C6 '.$entity,'entity'=>$entity,'code'=>'C6'.$entity,'applies_department'=>$dept]));
    $mine['rule'][]=$id;
    $cfg(fn() => appr_level_save(['rule_id'=>$id,'seq'=>1,'label'=>'H','approver_user_id'=>$uAppr,'sla_days'=>2,'reminder_days'=>1]));
    return $id;
};
$act($uRaise);
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,created_at) VALUES ('C6-C','C6','C','OFFERED',?)")->execute([date('c')]);
$cid=(int)$pdo->lastInsertId(); $mine['cand'][]=$cid;

$E = [];
$rH = $mkRule('HIRING_REQUEST','');
[$okH,,$h] = hreq_save(0,['job_title'=>'C6 hiring','quantity'=>1,'office_id'=>893,'priority'=>'NORMAL','approval_required'=>1]);
$mine['h'][]=$h; hreq_submit($h); $apH = hreq_approval($h); $mine['rq'][]=(int)$apH['id'];
$E['HIRING_REQUEST'] = ['req'=>$apH, 'rec'=>(int)$h, 'table'=>'hiring_requests', 'rule'=>$rH];

$rO = $mkRule('OFFER','C6Off');
$oid = offer_create($cid,['ctc'=>500000]);
[, $rqO] = appr_start('OFFER',$oid,['department'=>'C6Off','amount'=>500000],'C6 offer',500000);
$mine['rq'][]=(int)$rqO;
$E['OFFER'] = ['req'=>appr_request((int)$rqO), 'rec'=>$oid, 'table'=>'job_offers', 'rule'=>$rO];

$rS = $mkRule('SALARY','C6Sal');
$sid = sal_save($cid,['candidate_expected'=>100000]);
[, $rqS] = appr_start('SALARY',$sid,['department'=>'C6Sal','amount'=>0],'C6 salary',0);
$mine['rq'][]=(int)$rqS;
$E['SALARY'] = ['req'=>appr_request((int)$rqS), 'rec'=>$sid, 'table'=>'salary_structures', 'rule'=>$rS];

$rR = $mkRule('REQUISITION','C6Req');
$pdo->prepare("INSERT INTO requisitions (req_code,designation,department,status,created_at) VALUES ('C6-RQ','F','C6Req','OPEN',?)")->execute([date('c')]);
$rid=(int)$pdo->lastInsertId();
[, $rqR] = appr_start('REQUISITION',$rid,['department'=>'C6Req','amount'=>0],'C6 requisition',0);
$mine['rq'][]=(int)$rqR;
$E['REQUISITION'] = ['req'=>appr_request((int)$rqR), 'rec'=>$rid, 'table'=>'requisitions', 'rule'=>$rR];

t_eq(count($E), 4, 'C6.0 · a live chain, a real record and a real policy exist for all four entities');
$apprRow = ops_one("SELECT * FROM users WHERE id=?", [$uAppr]);
$act($uAppr);
$step = fn($n) => appr_step_context(appr_current_step($E[$n]['req']), $E[$n]['req']);

// ---------------------------------------------------------------------------
//  C6.1 · CASE 1 — a real source record still produces a real, traceable event
//  §8 business-function regression: the correction must not cost audit history.
// ---------------------------------------------------------------------------
t_section('C6.1 · case 1 · existing source → a traceable, openable reference');
foreach ($E as $name => $x) {
    $m0 = $maxAct();
    appr_audit_sla($x['req'], $step($name), 'C6 SLA event');
    appr_audit_notify($x['req'], 'approved', 'RECIPIENT_INACTIVE');
    $rows = $since($m0);
    t_ok(count($rows) >= 2, "C6.1 · $name · the events were retained, not silently dropped");
    $allOpen($rows, "C6.1 · $name");
    //  §2 — a LIVE record must never be described as unavailable.
    $lie = 0; foreach ($rows as $r) if (strpos((string)$r['subject'], 'source record unavailable') !== false) $lie++;
    t_eq($lie, 0, "C6.1 · $name · a live record is never described as unavailable");
    //  The timeline-registered entities keep pointing at the record itself.
    if (array_key_exists($name, ACT_ENTITIES)) {
        $self = 0; foreach ($rows as $r) if ((string)$r['entity_kind'] === $name && (int)$r['entity_id'] === (int)$x['rec']) $self++;
        t_eq($self, count($rows), "C6.1 · $name · filed against the source record itself — traceability unchanged");
    } else {
        //  Offer and salary are not on the timeline. Before, act_log() blanked the
        //  kind and kept the id — an orphan. Now they are filed under the policy.
        $pol = 0; foreach ($rows as $r) if ((string)$r['entity_kind'] === 'APPROVAL_POLICY' && (int)$r['entity_id'] === (int)$x['rule']) $pol++;
        t_eq($pol, count($rows), "C6.1 · $name · filed under the governing policy — openable, not orphaned");
    }
}

// ---------------------------------------------------------------------------
//  C6.2 · CASES 3, 5, 6 — invalid id, unknown type, malformed reference
// ---------------------------------------------------------------------------
t_section('C6.2 · cases 3/5/6 · invalid id, unknown type, malformed reference');
foreach ($E as $name => $x) {
    $cases = [
        ['case 3 · id 0',        ['entity'=>$name, 'entity_id'=>0,  'rule_id'=>$x['rule']]],
        ['case 3 · negative id', ['entity'=>$name, 'entity_id'=>-9, 'rule_id'=>$x['rule']]],
        ['case 5 · unknown type',['entity'=>'NOT_A_THING', 'entity_id'=>$x['rec'], 'rule_id'=>$x['rule']]],
        ['case 6 · blank type',  ['entity'=>'', 'entity_id'=>$x['rec'], 'rule_id'=>$x['rule']]],
        ['case 6 · malformed',   ['entity'=>$name, 'rule_id'=>$x['rule']]],
    ];
    foreach ($cases as [$lab, $bad]) {
        $m0 = $maxAct();
        appr_audit_sla($bad, $step($name), 'C6 bad ref');
        appr_audit_notify($bad, 'approved', 'ENTITY_UNRESOLVED');
        $rows = $since($m0);
        $allOpen($rows, "C6.2 $lab · $name");
        //  nothing may point at the bad id
        $ref = 0; foreach ($rows as $r) if ((string)$r['entity_kind'] === $name) $ref++;
        t_eq($ref, 0, "C6.2 $lab · $name · no reference to the unusable source was created");
    }
}

// ---------------------------------------------------------------------------
//  C6.3 · CASE 4 — cross-tenant, for the source AND for the fallback
//  "Any fallback approval/audit subject must also remain tenant-correct."
// ---------------------------------------------------------------------------
t_section('C6.3 · case 4 · a foreign id is never referenced, source or fallback');
$foreign = 99500000 + random_int(1, 999);
foreach ($E as $name => $x) {
    t_ok(!ops_one("SELECT id FROM " . $x['table'] . " WHERE id=?", [$foreign]),
         "C6.3 · $name · the foreign id does not exist in this workspace");
    //  (a) foreign SOURCE, valid local policy — the policy may carry the event
    $m0 = $maxAct();
    appr_audit_sla(['entity'=>$name,'entity_id'=>$foreign,'rule_id'=>$x['rule']], $step($name), 'C6 foreign src');
    $rows = $since($m0);
    $allOpen($rows, "C6.3a · $name");
    $bad = 0; foreach ($rows as $r) if ((int)$r['entity_id'] === $foreign) $bad++;
    t_eq($bad, 0, "C6.3a · $name · no audit reference to the foreign record");
    //  (b) foreign SOURCE and foreign FALLBACK — nothing openable remains
    $m0 = $maxAct();
    appr_audit_sla(['entity'=>$name,'entity_id'=>$foreign,'rule_id'=>$foreign], $step($name), 'C6 foreign both');
    appr_audit_notify(['entity'=>$name,'entity_id'=>$foreign,'rule_id'=>$foreign], 'approved', 'ENTITY_UNRESOLVED');
    t_eq(count($since($m0)), 0, "C6.3b · $name · a foreign fallback is refused too — NO ROW, not a foreign reference");
}
t_ok(!ops_one("SELECT id FROM recruit_approval_rules WHERE id=?", [$foreign]),
     'C6.3 · the foreign policy id does not exist here either — the fallback is tenant-bound');

// ---------------------------------------------------------------------------
//  C6.7 · THE CONTRACTS THAT MAKE THE E2 "NEVER CAST" GUARDS EQUIVALENT TODAY
//
//  Four E2 mutations survive the battery: relaxing `is_string($r) ? $r : …` to
//  `(string) $r`, and `=== true` to `(bool)`. They survive because the values
//  reaching those lines are ALREADY of the right type — not because the guards
//  are pointless. That is an argument about a CONTRACT, so the contract is
//  pinned here rather than asserted in prose: if a future change ever lets one
//  of these return something else, this section fails first and the guard's
//  value becomes visible again.
// ---------------------------------------------------------------------------
t_section('C6.7 · return-type contracts behind the E2 guards');
$act($uAppr);
$stepC = appr_step_context(appr_current_step($E['HIRING_REQUEST']['req']), $E['HIRING_REQUEST']['req']);
$ghost = ['id'=>0, 'is_active'=>1, 'role'=>'SBU_HEAD', 'permissions'=>$HR];   // an unresolvable subject
$probes = [
    'a real approver'         => $apprRow,
    'an unresolvable subject' => $ghost,
    'not a user at all'       => 'nonsense',
];
foreach ($probes as $lab => $who) {
    foreach (['HIRING_REQUEST' => $E['HIRING_REQUEST'], 'OFFER' => $E['OFFER']] as $en => $x) {
        $req = ['entity'=>$en, 'entity_id'=>$x['rec'], 'rule_id'=>$x['rule']];
        $g = appr_notify_gate($req, $who);
        t_ok(is_string($g), "C6.7 · $lab · $en · appr_notify_gate() returns a STRING, never null");
        t_ok($g === '' || in_array($g, array_keys(APPR_NOTIFY_REASONS), true),
             "C6.7 · $lab · $en · …and it is '' or a known reason code");
        $t = appr_told_reason($req, $who);
        t_ok(is_string($t), "C6.7 · $lab · $en · appr_told_reason() returns a STRING, never null");
        $v = appr_may_be_asked($stepC, $req, $who);
        t_ok($v === true || $v === false, "C6.7 · $lab · $en · appr_may_be_asked() returns a STRICT boolean");
    }
}
//  appr_visible() is the value the actionable path compares with === true.
$vis = appr_visible($stepC, ['entity'=>'HIRING_REQUEST','entity_id'=>$E['HIRING_REQUEST']['rec']], $apprRow);
t_ok($vis === true || $vis === false, 'C6.7 · appr_visible() returns a STRICT boolean — what makes `=== true` equivalent to a cast');
$vis2 = appr_visible($stepC, ['entity'=>'NOPE','entity_id'=>1], $apprRow);
t_ok($vis2 === false, 'C6.7 · appr_visible() on an unknown entity is strictly false, not a falsy value');
//  and the correction #6 helpers keep their own contracts
t_ok(appr_audit_ref_ok('APPROVAL_POLICY', $E['HIRING_REQUEST']['rule']) === true, 'C6.7 · appr_audit_ref_ok() returns strict true');
t_ok(appr_audit_ref_ok('APPROVAL_POLICY', 0) === false, 'C6.7 · appr_audit_ref_ok() returns strict false');
[$k7, $i7, $b7] = appr_audit_subject(['entity'=>'HIRING_REQUEST','entity_id'=>$E['HIRING_REQUEST']['rec'],'rule_id'=>$E['HIRING_REQUEST']['rule']]);
t_ok(is_string($k7) && is_int($i7) && is_bool($b7), 'C6.7 · appr_audit_subject() returns [string, int, bool]');

// ---------------------------------------------------------------------------
//  C6.4 · CASE 2 — THE DEFECT: the source record is deleted, the chain lives on
// ---------------------------------------------------------------------------
t_section('C6.4 · case 2 · a deleted source record never leaves a dangling reference');
foreach ($E as $name => $x) {
    $pdo->prepare("DELETE FROM " . $x['table'] . " WHERE id=?")->execute([(int)$x['rec']]);
    t_ok(appr_entity_record($name, $x['rec']) === null, "C6.4 · $name · the source record is gone");

    $m0 = $maxAct();
    appr_audit_sla($x['req'], $step($name), 'C6 SLA after delete');
    appr_audit_notify($x['req'], 'approved', 'ENTITY_UNRESOLVED');
    $rows = $since($m0);

    t_ok(count($rows) >= 1, "C6.4 · $name · the event is still recorded — history is not destroyed");
    $allOpen($rows, "C6.4 · $name");
    $dead = 0; foreach ($rows as $r) if ((string)$r['entity_kind'] === $name && (int)$r['entity_id'] === (int)$x['rec']) $dead++;
    t_eq($dead, 0, "C6.4 · $name · NOTHING points at the deleted record — THE DEFECT IS CLOSED");
    $pol = 0; foreach ($rows as $r) if ((string)$r['entity_kind'] === 'APPROVAL_POLICY' && (int)$r['entity_id'] === (int)$x['rule']) $pol++;
    t_eq($pol, count($rows), "C6.4 · $name · filed under the governing policy instead");
    //  §2 — and it says so, rather than implying the record is still there.
    $said = 0; foreach ($rows as $r) if (strpos((string)$r['subject'], 'source record unavailable') !== false) $said++;
    t_eq($said, count($rows), "C6.4 · $name · each row states the source record is unavailable");

    //  …and with no usable fallback either, nothing is written at all.
    $noRule = $x['req']; $noRule['rule_id'] = 0;
    $m0 = $maxAct();
    appr_audit_sla($noRule, $step($name), 'C6 no fallback');
    appr_audit_notify($noRule, 'approved', 'ENTITY_UNRESOLVED');
    t_eq(count($since($m0)), 0, "C6.4 · $name · no openable subject at all → NO ROW, never an orphan");
}

// ---------------------------------------------------------------------------
//  C6.5 · D2 / D3 preserved
// ---------------------------------------------------------------------------
t_section('C6.5 · D2 and D3 are not weakened');
$m0 = $maxAct();
foreach (['SENT','NO_EMAIL','PROVIDER_FAILURE'] as $quiet)
    appr_audit_notify($E['HIRING_REQUEST']['req'], 'approved', $quiet);
t_eq(count($since($m0)), 0, 'C6.5 · D2 · a routine outcome is still not an event');
foreach (['IDENTITY_UNRESOLVED','ENTITY_UNRESOLVED','RECIPIENT_UNLICENSED','RECIPIENT_OUT_OF_SCOPE','SEGREGATION_BLOCKED'] as $r)
    t_ok(in_array($r, APPR_NOTIFY_AUDITED, true) && isset(APPR_NOTIFY_REASONS[$r]),
         'C6.5 · D3 · "' . $r . '" is still its own reason, with its own words');
t_eq(count(array_unique(APPR_NOTIFY_REASONS)), count(APPR_NOTIFY_REASONS),
     'C6.5 · D3 · no two reasons share a sentence — identity, entity, entitlement, scope and segregation stay distinct');

// ---------------------------------------------------------------------------
//  C6.6 · the five clauses, each one on its own
// ---------------------------------------------------------------------------
t_section('C6.6 · appr_audit_ref_ok() — one clause at a time');
$rHid = (int) $E['HIRING_REQUEST']['rule'];
t_ok(appr_audit_ref_ok('APPROVAL_POLICY', $rHid),        'C6.6 · a supported type + real record + this tenant → VALID');
t_ok(!appr_audit_ref_ok('', $rHid),                      'C6.6 · clause 1 · no type → invalid');
t_ok(!appr_audit_ref_ok('CANDIDATE', $cid),              'C6.6 · clause 1 · a supported timeline type this module does not own → invalid');
t_ok(!appr_audit_ref_ok('APPROVAL_POLICY', 0),           'C6.6 · clause 2 · id 0 → invalid');
t_ok(!appr_audit_ref_ok('APPROVAL_POLICY', -3),          'C6.6 · clause 2 · negative id → invalid');
t_ok(!appr_audit_ref_ok('APPROVAL_POLICY', $foreign),    'C6.6 · clause 3/4 · a policy id that is not in this workspace → invalid');
t_ok(!appr_audit_ref_ok('OFFER', $E['OFFER']['rec']),    'C6.6 · clause 1 · a type the timeline cannot link → invalid, even for a real record');
//  clause 5 — a resolution that ERRORS is a resolution that FAILED
try {
    $pdo->exec("ALTER TABLE recruit_approval_rules RENAME TO recruit_approval_rules_c6tmp");
    t_ok(!appr_audit_ref_ok('APPROVAL_POLICY', $rHid), 'C6.6 · clause 5 · with its table gone the reference is invalid, not an exception');
} finally {
    try { $pdo->exec("ALTER TABLE recruit_approval_rules_c6tmp RENAME TO recruit_approval_rules"); } catch (Throwable $e) {}
}
t_ok(t_table_exists('recruit_approval_rules'), 'C6.6 · the table is put back');

// ---------------------------------------------------------------------------
//  Clean up — J4: this suite removes the activity rows it created.
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
foreach (array_unique($mine['rq']) as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_requests WHERE id=?")->execute([(int)$r]);
}
foreach (array_unique($mine['act']) as $a) $pdo->prepare("DELETE FROM activities WHERE id=?")->execute([(int)$a]);
foreach ($mine['rule'] as $r) {
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([(int)$r]);
}
foreach ($mine['h'] as $x) {
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?")->execute([(int)$x]);
    $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([(int)$x]);
}
$pdo->prepare("DELETE FROM activities WHERE entity_kind='REQUISITION' AND entity_id=?")->execute([(int)$rid]);
$pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([(int)$rid]);
foreach ($mine['cand'] as $x) $pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([(int)$x]);
foreach ($mine['u'] as $x) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$x]);
foreach ($mine['o'] as $x) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([(int)$x]);
$pdo->exec("DELETE FROM email_log WHERE kind='recruit_approval'");
$leftAct = (int) ops_val("SELECT COUNT(*) FROM activities WHERE subject LIKE 'C6 %'");
t_eq($leftAct, 0, 'C6 · J4 · every activity row this suite created has been cleaned up');
t_ok(true, 'M3 correction #6 fixtures removed');
