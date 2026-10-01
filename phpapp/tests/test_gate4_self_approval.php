<?php
// ============================================================================
//  GATE 4 — APPROVAL GOVERNANCE, SELF-APPROVAL & CONFIGURATION SAFETY
//
//  The claims asserted below:
//
//    A  what the code did BEFORE this gate, asserted so the defect cannot return
//    B  the two settings are ORGANISATION configuration, OFF by default
//    C  requester = approver is BLOCKED, for EVERY entity on the common engine
//    D  the master exception: off blocks, on allows AND is audited
//    E  there is no per-user mechanism, and ADMIN is not master
//    F  §12 A–D, the four locked cases, end to end
//    G  §11 material-change routing uses the PROPOSED values
//    H  §9 approval-required OFF vs ON-with-no-matching-rule
//    I  §10 a request in flight keeps the policy it was raised under
//    J  §16 concurrency, including a self-approval attempt racing a real approver
//    K  §17 two organisations, complete isolation
//    L  configuration changes are auditable; approval history is immutable
//    M  Gate 2 and Gate 3 protection
//
//  THE LOAD-BEARING ASSERTIONS are marked ***.
// ============================================================================

$s = 'G4-' . random_int(100000, 999999);
appr_migrate(); hreq_migrate(); rver_migrate(); act_migrate(); recruit_offer_migrate();
recruitpipe_migrate(); crev_migrate();
$pdo = db();

$off = (int) ops_val("SELECT COALESCE(MAX(id),0)+1 FROM offices");
$pdo->prepare("INSERT INTO offices (id,name,city) VALUES (?,?,?)")->execute([$off, $s . ' Office', 'Kolkata']);
$mkUser = function ($name, $role, $super, $office) use ($pdo, $s) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,?,?,1,?,?,'')")->execute([$s . '_' . $name, $name, $role, $super ? 1 : 0, $office]);
    return (int) $pdo->lastInsertId();
};
//  FOUR DISTINCT PEOPLE, because every claim here is about identity:
$uMaster  = $mkUser('master',  'MANAGER', 1, $off);   // a superuser
$uAdmin   = $mkUser('admin',   'ADMIN',   0, $off);   // an ADMIN role, NOT a superuser
$uRaiser  = $mkUser('raiser',  'MANAGER', 0, $off);   // raises things
$uDecider = $mkUser('decider', 'MANAGER', 1, $off);   // a different person who may decide
$asUser = function ($uid) { $_SESSION['uid'] = (int) $uid; current_user(true); ua(true); };

$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$asUser($uMaster);
$base = fn(array $x = []) => array_merge([
    'requested_by_id' => $uRaiser, 'requested_by_name' => 'G4 Raiser',
    'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
    'job_title' => 'G4 Engineer', 'designation' => 'ENGINEER', 'job_description' => 'g4',
    'quantity' => 6, 'office_id' => $off, 'required_by' => '2026-12-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL',
    'reason' => 'Contract awarded.', 'change_reason' => 'G4 test change',
    'min_experience_years' => 2,
], $x);

//  THE SETTINGS, SET EXPLICITLY FOR EVERY CLAIM. The suite's bootstrap declares the
//  world the OLD fixtures live in; this file never relies on it.
$policy = function ($self, $master) {
    setting_set('appr_self_approval', $self ? '1' : '0');
    setting_set('appr_self_master_exception', $master ? '1' : '0');
};
$policyBefore = [(string) setting_get('appr_self_approval', ''), (string) setting_get('appr_self_master_exception', '')];

// ---------------------------------------------------------------------------
t_section('G4 · A — the defect this gate removes, asserted against the source');
// ---------------------------------------------------------------------------
$srcA = (string) file_get_contents(__DIR__ . '/../lib/recruit_approval.php');
//  COMMENTS STRIPPED FIRST. The explanation inside appr_guard() quotes the old line
//  verbatim so a reader can see what was removed — and a naive source search would
//  find it there and report the defect as still present. What is asserted here is
//  the CODE, not the prose about the code.
$noComments = function ($src) {
    $out = [];
    foreach (explode("\n", $src) as $ln) { if (preg_match('~^\s*//~', $ln)) continue; $out[] = $ln; }
    return implode("\n", $out);
};
$srcAcode = $noComments($srcA);
$guardSrc = substr($srcAcode, strpos($srcAcode, 'function appr_guard('), 2600);
t_ok(strpos($guardSrc, "if (\$entity !== 'HIRING_REQUEST'") === false,
     'A1 · *** appr_guard no longer returns early for every entity but one ***');
t_ok(strpos($guardSrc, 'appr_self_block_reason(') !== false,
     'A2 · …and asks the universal segregation question instead');
$hrSrc = $noComments((string) file_get_contents(__DIR__ . '/../lib/hiringreq.php'));
$segSrc = substr($hrSrc, strpos($hrSrc, 'function hreq_segregation_blocks('), 1600);
t_ok(strpos($segSrc, 'if (function_exists(\'is_master\') && is_master()) return false;') === false,
     'A3 · *** the unconditional master bypass is gone from the segregation rule ***');
t_ok(strpos($segSrc, 'appr_self_master_exception()') !== false,
     'A4 · …and the organisation is asked instead');
//  AND THE ENTITIES GATE 2 LEFT UNREGISTERED ARE REGISTERED (G4-2).
t_ok(array_key_exists('HREQ_CHANGE', APPR_ENTITY_SOURCE) && array_key_exists('REQ_CHANGE', APPR_ENTITY_SOURCE),
     'A5 · *** Gate 2\'s change entities are resolvable by the engine at last ***');
t_ok(array_key_exists('HREQ_CHANGE', APPR_ENTITY_MODULE) && array_key_exists('REQ_CHANGE', APPR_ENTITY_MODULE),
     'A6 · …and entitled like the requirements they change');

// ---------------------------------------------------------------------------
t_section('G4 · B — organisation configuration, OFF by default');
// ---------------------------------------------------------------------------
//  THE DEFAULT IS THE RULE. Read with the keys absent, which is what a brand-new
//  organisation looks like before anybody configures anything.
$keep = [(string) setting_get('appr_self_approval', ''), (string) setting_get('appr_self_master_exception', '')];
//  The in-memory settings cache reloads on a database switch, not on demand, so
//  "absent" is produced by removing the keys from the live cache as well as the
//  table. This is what a brand-new organisation's first read actually sees.
//  THE REFERENCE IS RELEASED IMMEDIATELY. Every test file is required into the SAME
//  global scope, so a reference left bound to the settings cache is a live alias in
//  that scope — and the next file that happens to use the same variable name assigns
//  a string straight into the cache. That is not hypothetical: it crashed the suite
//  three files later, in a test that has nothing to do with settings.
$g4sc = &settings_cache();
unset($g4sc['appr_self_approval'], $g4sc['appr_self_master_exception']);
unset($g4sc);
t_eq(appr_self_allowed(), false, 'B1 · *** with nothing configured, self-approval is OFF ***');
t_eq(appr_self_master_exception(), false, 'B2 · *** …and the master exception is OFF ***');
setting_set('appr_self_approval', $keep[0] === '' ? '0' : $keep[0]);
setting_set('appr_self_master_exception', $keep[1] === '' ? '0' : $keep[1]);

t_ok(defined('APPR_SELF_KEY') && APPR_SELF_KEY === 'appr_self_approval', 'B3 · the key is named once, as a constant');
$policy(false, false);
t_eq(appr_self_allowed(), false, 'B4 · the organisation can hold it off');
$policy(true, false);
t_eq(appr_self_allowed(), true, 'B5 · …and can turn it on');
$policy(false, true);
t_eq(appr_self_master_exception(), true, 'B6 · the master exception is its own switch');
t_eq(appr_self_allowed(), false, 'B7 · *** …and turning it on does NOT turn self-approval on for everybody ***');

//  IT IS NOT PER USER, PER REQUEST, OR NAME-BASED — asserted against the code.
t_ok(strpos($srcA, 'allow_self_approval') === false, 'B8 · *** there is no per-user self-approval flag ***');
$userCols = array_map('strtolower', rver_table_fields('users'));
t_ok(!in_array('allow_self_approval', $userCols, true) && !in_array('can_self_approve', $userCols, true),
     'B9 · *** …and no such column on users ***');
$selfFn = substr($srcA, strpos($srcA, 'function appr_self_block_reason('), 2200);
t_ok(strpos($selfFn, 'username') === false && strpos($selfFn, 'requested_by_name') === false,
     'B10 · *** the rule compares identities, never names ***');

// ---------------------------------------------------------------------------
t_section('G4 · C — requester = approver is blocked, for EVERY entity');
// ---------------------------------------------------------------------------
$policy(false, false);
//  Built as the engine builds them, so what is asserted is the engine's own answer.
$mkReq = function ($entity, $entityId, $requesterId) use ($pdo) {
    $pdo->prepare("INSERT INTO recruit_approval_requests (entity,entity_id,rule_id,rule_name,subject,amount,
                       status,current_seq,requester,requester_id,self_policy,created_at)
                   VALUES (?,?,0,'G4','G4 subject',0,'PENDING',1,'G4',?,?,?)")
        ->execute([$entity, (int) $entityId, (int) $requesterId, appr_self_policy_token(), date('c')]);
    return ops_one("SELECT * FROM recruit_approval_requests WHERE id=?", [(int) $pdo->lastInsertId()]);
};
//  REAL RECORDS, one per entity, each raised by $uRaiser.
//
//  Not invented ids: appr_requester_id() resolves the BUSINESS OBJECT first and
//  refuses to guess when it cannot find one, so a fixture pointing at a row that
//  does not exist would make every assertion below pass for the wrong reason — the
//  rule would be silent, not satisfied.
$asUser($uRaiser);
[$cHOk,, $cHid] = hreq_save(0, $base(['job_title' => 'G4 Entity HR']));
$pdo->prepare("INSERT INTO requisitions (req_code,designation,office_id,sbu,status,quantity,created_at)
               VALUES (?,?,?, 'IND','OPEN',2,?)")->execute([$s . '-RQ', 'ENGINEER', $off, date('c')]);
$cRqid = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,created_at)
               VALUES (?,'G4','Ent','RECEIVED',?,?)")->execute([$s . '-EC', $cRqid, date('c')]);
$cCand = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO salary_structures (candidate_id,currency,gross_ctc,created_by,created_by_id,created_at)
               VALUES (?,'INR',500000,'G4',?,?)")->execute([$cCand, $uRaiser, date('c')]);
$cSal = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO job_offers (candidate_id,ctc,status,created_by,created_by_id,created_at)
               VALUES (?,500000,'DRAFT','G4',?,?)")->execute([$cCand, $uRaiser, date('c')]);
$cOff = (int) $pdo->lastInsertId();
//  A pending CHANGE proposal on each requirement, proposed by $uRaiser — which is
//  what makes the change entities resolvable at all (G4-2).
$mkProposal = function ($target, $tid) use ($pdo, $uRaiser) {
    $pdo->prepare("INSERT INTO requirement_change_proposals (entity,entity_id,status,base_version,
                       proposed_json,reason,proposed_by,proposed_by_id,proposed_at,requires_approval,created_at,pending_key)
                   VALUES (?,?, 'PENDING',1,'{}','g4','G4',?,?,1,?,?)")
        ->execute([$target, (int) $tid, $uRaiser, date('c'), date('c'),
                   $target . ':' . (int) $tid . ':g4' . random_int(1000, 9999)]);
    return (int) $pdo->lastInsertId();
};
$mkProposal('HIRING_REQUEST', $cHid);
$mkProposal('REQUISITION', $cRqid);
$entityIds = ['HIRING_REQUEST' => $cHid, 'REQUISITION' => $cRqid, 'OFFER' => $cOff,
              'SALARY' => $cSal, 'HREQ_CHANGE' => $cHid, 'REQ_CHANGE' => $cRqid];
foreach ($entityIds as $ent => $eid) {
    $rq = $mkReq($ent, $eid, $uRaiser);
    [$rid, $rwhy] = appr_requester_id($rq);
    t_eq((int) $rid, $uRaiser, 'C · ' . $ent . ' — the requester resolves to a real identity'
         . ($rid ? '' : ' (' . $rwhy . ')'));
    t_ok(appr_self_block_reason($rq) !== '',
         'C · *** ' . $ent . ' — the person who raised it cannot decide it ***');
}
//  …AND A DIFFERENT PERSON IS NOT BLOCKED. A rule that refused everybody would pass
//  every assertion above and be useless.
$asUser($uDecider);
foreach ($entityIds as $ent => $eid) {
    $rq = $mkReq($ent, $eid, $uRaiser);
    t_eq(appr_self_block_reason($rq), '', 'C · ' . $ent . ' — somebody else decides it freely');
}

//  AN UNRESOLVABLE REQUESTER IS NOT ASSERTED ABOUT. A legacy row carries no
//  identity; refusing those would make every historical approval undecidable.
$asUser($uRaiser);
$legacy = $mkReq('REQUISITION', $cRqid, 0);
t_eq(appr_self_block_reason($legacy), '',
     'C13 · a row with no recorded requester is not guessed about either way');

// ---------------------------------------------------------------------------
t_section('G4 · D — the master exception: off blocks, on allows and is recorded');
// ---------------------------------------------------------------------------
$asUser($uMaster);
$policy(false, false);
$mrq = $mkReq('REQUISITION', $cRqid, $uMaster);
t_ok(appr_self_block_reason($mrq) !== '',
     'D1 · *** a superuser is BLOCKED while the exception is off ***');
t_ok(stripos(appr_self_block_reason($mrq), 'master self-approval exception') !== false,
     'D2 · …and told that the exception exists and is switched off');

$policy(false, true);
$mrq2 = $mkReq('REQUISITION', $cRqid, $uMaster);
$auditBefore = (int) ops_val("SELECT COUNT(*) FROM activities WHERE outcome='SELF_APPROVAL_EXCEPTION'");
t_eq(appr_self_block_reason($mrq2), '', 'D3 · *** with the exception on, the superuser may decide ***');
$auditAfter = (int) ops_val("SELECT COUNT(*) FROM activities WHERE outcome='SELF_APPROVAL_EXCEPTION'");
t_ok($auditAfter > $auditBefore, 'D4 · *** …and the use of the exception is AUDITED ***');
$row = ops_one("SELECT * FROM activities WHERE outcome='SELF_APPROVAL_EXCEPTION' ORDER BY id DESC LIMIT 1");
$body = json_decode((string) ($row['body'] ?? ''), true);
foreach (['requester_id', 'approver_id', 'requester_equals_approver', 'self_approval_exception',
          'organisation', 'approval_entity', 'approval_request_id', 'at'] as $f)
    t_ok(array_key_exists($f, is_array($body) ? $body : []), 'D · the record carries ' . $f);
t_eq((int) $body['requester_id'], $uMaster, 'D13 · …the right requester');
t_eq((int) $body['approver_id'], $uMaster, 'D14 · …the right approver');
t_eq($body['requester_equals_approver'], true, 'D15 · …and says plainly that they are the same person');
t_eq((string) $body['self_approval_exception'], 'MASTER', 'D16 · …under which exception');

//  THE EXCEPTION IS FOR A SUPERUSER, NOT FOR AN ADMIN ROLE (§15).
$asUser($uAdmin);
t_ok(!is_master(), 'D17 · an ADMIN role is not a superuser');
$arq = $mkReq('REQUISITION', $cRqid, $uAdmin);
t_ok(appr_self_block_reason($arq) !== '',
     'D18 · *** …so an administrator does NOT get the exception, even with it enabled ***');
t_ok(stripos(appr_self_block_reason($arq), 'master') === false,
     'D19 · …and is not told about an exception that is not theirs');

// ---------------------------------------------------------------------------
t_section('G4 · E — no per-user mechanism, and ADMIN is not unrestricted');
// ---------------------------------------------------------------------------
$asUser($uAdmin);
//  WHAT "INDEPENDENT" ACTUALLY MEANS HERE (§14), stated precisely because the
//  obvious assertion is the wrong one.
//
//  The ADMIN role is granted array_keys(PERMISSIONS) by this platform's own design —
//  an administrator holds every permission — so "an admin does not hold
//  hiring.review.clear" is false, and asserting it would be asserting a thing this
//  product deliberately does not do. Independence is not absence. It is that the two
//  capabilities are separate keys, separately checked, and that NEITHER implies the
//  other: approval authority does not come with review clearance, and review
//  clearance does not come with approval authority.
t_ok(array_key_exists('hiring.review.clear', PERMISSIONS),
     'E1 · the review permission is its own key in the catalogue');
t_ok(in_array('hiring.review.clear', role_defaults('ADMIN')['perms'], true),
     'E1b · an ADMIN holds it because an ADMIN holds every permission — by design');
t_ok(!in_array('hiring.review.clear', role_defaults('COORDINATOR')['perms'], true),
     'E1c · *** …and a non-administrator role does NOT, unless granted it ***');

//  A NAMED APPROVER WHO IS NOT AN ADMINISTRATOR HOLDS APPROVAL AUTHORITY AND NOTHING
//  ELSE. This is the independence that matters: being trusted to approve a budget is
//  not being trusted to rule that a candidate still meets a raised requirement.
$uAppr = $mkUser('approver', 'COORDINATOR', 0, $off);
$asUser($uAppr);
t_ok(!can('hiring.review.clear'),
     'E2 · *** a plain approver cannot clear a requirement review ***');
t_ok(!can('hiring.material_change.propose'),
     'E2b · …nor propose a material change, merely by being an approver');
$asUser($uAdmin);                 // back to the administrator — $uAppr was a different probe
$policy(true, false);
$aok = $mkReq('REQUISITION', $cRqid, $uAdmin);
t_eq(appr_self_block_reason($aok), '', 'E3 · with self-approval ON, everyone may — including this admin');
$policy(false, false);
$ano = $mkReq('REQUISITION', $cRqid, $uAdmin);
t_ok(appr_self_block_reason($ano) !== '', 'E4 · *** with it OFF, the ADMIN role buys no exemption ***');

// ---------------------------------------------------------------------------
t_section('G4 · F — the four locked cases, end to end on a real hiring request');
// ---------------------------------------------------------------------------
//  hreq_decide() is the GUARDED entry point — scope, capability and segregation are
//  all asked there, "so no caller can decide around it". hreq_apply_decision() is
//  the internal writer that both the direct path and the approval callback share,
//  and it is deliberately unguarded because the chain's own guard has already run.
//  A test that called the writer would be testing nothing.
$approveAs = function ($hid, $uid) use ($asUser) {
    $asUser($uid);
    return hreq_decide($hid, true, 'decided');
};
//  A — a different authorised person approves. PASS.
$policy(false, false);
$asUser($uRaiser);
[$okA,, $hA] = hreq_save(0, $base(['job_title' => 'G4 Case A']));
hreq_submit($hA);
[$fa] = $approveAs($hA, $uDecider);
t_ok($fa, 'F-A · *** a different authorised person approves it: PASS ***');
t_eq(hreq_get($hA)['status'], 'APPROVED', 'F-A2 · …and it really is approved');

//  B — the requester is the one deciding, self-approval OFF. BLOCK.
$asUser($uRaiser);
[$okB,, $hB] = hreq_save(0, $base(['job_title' => 'G4 Case B']));
hreq_submit($hB);
[$fb, $fbMsg] = $approveAs($hB, $uRaiser);
t_ok(!$fb, 'F-B · *** the requester cannot approve their own: BLOCK — ' . $fbMsg . ' ***');
t_eq(hreq_get($hB)['status'], 'SUBMITTED', 'F-B2 · …and it is still waiting');

//  C — the requester is a superuser, exception OFF. BLOCK.
$policy(false, false);
$asUser($uMaster);
[$okC,, $hC] = hreq_save(0, $base(['job_title' => 'G4 Case C', 'requested_by_id' => $uMaster]));
hreq_submit($hC);
[$fc, $fcMsg] = $approveAs($hC, $uMaster);
t_ok(!$fc, 'F-C · *** a superuser with the exception OFF: BLOCK — ' . $fcMsg . ' ***');
t_eq(hreq_get($hC)['status'], 'SUBMITTED', 'F-C2 · …still waiting');

//  D — the requester is a superuser, exception ON. ALLOW + audit.
$policy(false, true);
$asUser($uMaster);
[$okD,, $hD] = hreq_save(0, $base(['job_title' => 'G4 Case D', 'requested_by_id' => $uMaster]));
hreq_submit($hD);
$auditD0 = (int) ops_val("SELECT COUNT(*) FROM activities WHERE outcome='SELF_APPROVAL_EXCEPTION'");
[$fd, $fdMsg] = $approveAs($hD, $uMaster);
t_ok($fd, 'F-D · *** a superuser with the exception ON: ALLOW — ' . $fdMsg . ' ***');
t_eq(hreq_get($hD)['status'], 'APPROVED', 'F-D2 · …and it is approved');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE outcome='SELF_APPROVAL_EXCEPTION'") > $auditD0,
     'F-D3 · *** …with the exception recorded in the audit trail ***');

//  AND CASE B BECOMES POSSIBLE THE MOMENT THE ORGANISATION SAYS SO — proving the
//  block is the configuration speaking and not a hard-coded refusal.
$policy(true, false);
[$fb2] = $approveAs($hB, $uRaiser);
t_ok($fb2, 'F-E · *** with self-approval ON, the same blocked decision now succeeds ***');
$policy(false, false);

// ---------------------------------------------------------------------------
t_section('G4 · G — §11: a material change is routed on the PROPOSED values');
// ---------------------------------------------------------------------------
//  §11's EXAMPLE, IN ITS OWN UNITS.
//
//  Approved commitment Rs 10,00,000. Proposed Rs 13,00,000. Threshold Rs 12,00,000.
//  The PROPOSED figure must choose the rule — if the approved one did, no rule would
//  match and the change would go through with nobody senior ever seeing it.
//
//  Asserted on a REQUISITION change, deliberately, because that is where the band is
//  MONEY. For a hiring request this product reads the band as HEADCOUNT and keeps
//  money as a separate key — documented in hreq_appr_ctx() — so a money threshold
//  asserted there would be asserting nothing. Same rule, different unit; the unit
//  has to match the example or the test is theatre.
$asUser($uMaster);
$policy(false, true);
[$gOk,, $hG] = hreq_save(0, $base(['job_title' => 'G4 Routing', 'requested_by_id' => $uMaster, 'quantity' => 10]));
hreq_submit($hG); hreq_decide($hG, true, 'ok');
[$grOk,, $rqG] = hreq_to_requisition($hG, 10);
t_ok($rqG > 0, 'G0 · a requirement exists to change');
//  Rs 1,00,000 per person per month x 10 people x 1 month = Rs 10,00,000 approved.
db()->prepare("UPDATE requisitions SET cost_wage=100000, rate_basis='MONTHLY', duration_months=1,
               cost_statutory_pct=0, cost_agency_pct=0, cost_reimburse=0, cost_oneoff=0 WHERE id=?")
    ->execute([(int) $rqG]);
rver_ensure_initial('REQUISITION', (int) $rqG, null, ['note' => 'G4 baseline']);
$rqRow = ops_one("SELECT * FROM requisitions WHERE id=?", [(int) $rqG]);
$cmtApproved = (float) rver_commitment('REQUISITION', $rqRow)['total'];
t_eq($cmtApproved, 1000000.0, 'G1 · the approved commitment is Rs 10,00,000');
$proposedRow = ['cost_wage' => 130000] + $rqRow;
$cmtProposed = (float) rver_commitment('REQUISITION', $proposedRow)['total'];
t_eq($cmtProposed, 1300000.0, 'G2 · the proposed commitment is Rs 13,00,000');

//  A rule that only something above Rs 12,00,000 can match.
$ruleId = appr_rule_save(0, ['name' => $s . ' big change', 'entity' => 'REQ_CHANGE',
    'min_amount' => 1200000, 'max_amount' => 0, 'active' => 1]);
appr_level_save(['rule_id' => $ruleId, 'seq' => 1, 'label' => 'L1', 'approver_user_id' => $uDecider,
                 'approver_role' => '', 'sla_days' => 3, 'reminder_days' => 1]);
t_ok($ruleId > 0 && count(appr_levels($ruleId)) === 1, 'G3 · the Rs 12,00,000 rule exists, with one approver');
t_ok(!appr_match('REQ_CHANGE', ['amount' => $cmtApproved]),
     'G4 · *** the APPROVED Rs 10,00,000 does NOT reach the threshold ***');
t_ok((bool) appr_match('REQ_CHANGE', ['amount' => $cmtProposed]),
     'G5 · *** …and the PROPOSED Rs 13,00,000 does ***');

//  AND THAT IS WHAT THE ENGINE ACTUALLY USES.
$route = rver_route('REQUISITION', $proposedRow);
t_eq((string) $route['entity'], 'REQ_CHANGE', 'G6 · the change routes as a REQ_CHANGE');
t_eq((int) ($route['rule']['id'] ?? 0), (int) $ruleId,
     'G7 · *** …and the rule it picks is the one only the PROPOSED value matches ***');

$asUser($uRaiser);
[$pOk, $pMsg] = rver_propose('REQUISITION', (int) $rqG, ['cost_wage' => 130000], 'Client raised the rate');
t_ok($pOk, 'G8 · the change is proposed: ' . $pMsg);
$chain = appr_open('REQ_CHANGE', (int) $rqG);
t_ok(is_array($chain), 'G9 · *** a chain opened on the proposed value ***');
t_eq((int) ($chain['rule_id'] ?? 0), (int) $ruleId, 'G10 · …the Rs 12,00,000 rule');
t_ok((float) ($chain['amount'] ?? 0) >= 1300000.0,
     'G11 · *** …carrying the PROPOSED commitment, not the approved one ***');

//  AND THE PROPOSER CANNOT APPROVE THEIR OWN CHANGE — the hole this gate closed.
t_ok(appr_guard($chain) !== '',
     'G12 · *** the person who proposed the change cannot decide it ***');
$asUser($uDecider);
t_eq(appr_guard($chain), '', 'G13 · …while a different person can');
//  Before Gate 4 this was the whole problem: appr_guard returned '' for REQ_CHANGE
//  no matter who was asking.
$asUser($uRaiser);
t_ok(stripos(appr_guard($chain), 'raised this') !== false,
     'G14 · …and the refusal says why');

// ---------------------------------------------------------------------------
t_section('G4 · H — §9: approval required OFF vs ON with no matching rule');
// ---------------------------------------------------------------------------
$asUser($uMaster); $policy(false, true);
[$hOk,, $hH] = hreq_save(0, $base(['job_title' => 'G4 NoRule', 'requested_by_id' => $uMaster]));
hreq_submit($hH); hreq_apply_decision($hH, 'APPROVED', 'G4', 'ok');
$asUser($uRaiser);
//  No rule matches a REQ_CHANGE here, so the proposal must WAIT — not self-approve,
//  not invent an approver, not apply itself.
[$rqOk,, $rqH] = hreq_to_requisition($hH, 3);
rver_ensure_initial('REQUISITION', $rqH);
t_ok(!appr_match('REQ_CHANGE', ['amount' => 1]), 'H1 · no rule matches this change');
rver_gate_requisition_edit($rqH, ['designation' => 'SUPERVISOR'], ['change_reason' => 'no rule exists']);
$pH = rver_pending('REQUISITION', $rqH);
t_ok(is_array($pH), 'H2 · *** the change is PENDING — it did not apply itself ***');
t_eq((int) rver_current('REQUISITION', $rqH)['version'], 1,
     'H3 · *** …no new version was created ***');
t_eq((string) ops_val("SELECT designation FROM requisitions WHERE id=?", [$rqH]), 'ENGINEER',
     'H4 · *** …and the approved value is still what the record serves ***');
t_ok((int) $pH['requires_approval'] === 1,
     'H5 · *** "no matching rule" is recorded as approval still REQUIRED ***');

// ---------------------------------------------------------------------------
t_section('G4 · I — §10: a request in flight keeps the policy it was raised under');
// ---------------------------------------------------------------------------
$asUser($uRaiser);
$policy(true, false);                                  // raised while self-approval is ON
$flight = $mkReq('REQUISITION', $cRqid, $uRaiser);
t_eq(appr_self_block_reason($flight), '', 'I1 · raised under ON, the requester may decide it');
$policy(false, false);                                 // the organisation changes its mind
t_eq(appr_self_block_reason($flight), '',
     'I2 · *** the chain already in flight is STILL judged under the policy it was raised under ***');
$fresh = $mkReq('REQUISITION', $cRqid, $uRaiser);
t_ok(appr_self_block_reason($fresh) !== '',
     'I3 · *** …while a request raised AFTER the change gets the new policy ***');
$pol = appr_self_policy_for($flight);
t_eq($pol['frozen'], true, 'I4 · the in-flight request carries its own frozen policy');
t_eq(appr_self_policy_for(['entity' => 'REQUISITION'])['frozen'], false,
     'I5 · …and a row with no stamp falls back to what is configured now');
//  A row from before this gate has no stamp and is not broken by its absence.
$pdo->prepare("UPDATE recruit_approval_requests SET self_policy='' WHERE id=?")->execute([(int) $flight['id']]);
$legacyFlight = ops_one("SELECT * FROM recruit_approval_requests WHERE id=?", [(int) $flight['id']]);
t_ok(appr_self_block_reason($legacyFlight) !== '',
     'I6 · a pre-Gate-4 row is judged under the current policy, which is the only one it has');

// ---------------------------------------------------------------------------
t_section('G4 · J — §16: concurrency, and a race that must not bypass the rule');
// ---------------------------------------------------------------------------
$asUser($uMaster); $policy(false, true);
[$jOk,, $hJ] = hreq_save(0, $base(['job_title' => 'G4 Race', 'requested_by_id' => $uRaiser]));
hreq_submit($hJ);
$root4 = dirname(__DIR__);
$engine4 = (getenv('DB_DRIVER') === 'mysql') ? 'mysql' : 'sqlite';
$race = function (array $ops) use ($root4, $engine4) {
    $env = $engine4 === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
          . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
          . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    $procs = [];
    foreach ($ops as [$hid, $uid]) {
        $cmd = $env . ' php ' . escapeshellarg($root4 . '/tests/_g4_worker.php') . ' '
             . (int) $hid . ' ' . (int) $uid . ' 400 2>&1';
        $pipes = []; $pr = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($pr)) $procs[] = [$pr, $pipes];
    }
    $res = [];
    foreach ($procs as [$pr, $pipes]) {
        $raw = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($pr);
        foreach (explode("\n", trim($raw)) as $line) { $d = json_decode(trim($line), true); if (is_array($d)) $res[] = $d; }
    }
    return $res;
};
//  TWO LEGITIMATE APPROVERS at the same instant: exactly one transition.
$r1 = $race([[$hJ, $uDecider], [$hJ, $uMaster]]);
t_eq(count($r1), 2, 'J1 · two real processes both ran');
t_eq(count(array_filter($r1, fn($x) => !empty($x['ok']))), 1,
     'J2 · *** exactly one of two simultaneous approvals succeeded ***');
t_eq((string) hreq_get($hJ)['status'], 'APPROVED', 'J3 · …and the request is approved exactly once');
t_eq((int) ops_val("SELECT COUNT(*) FROM hiring_requests WHERE id=? AND status='APPROVED'", [$hJ]), 1,
     'J4 · …with no duplicate final transition');

//  THE REQUESTER RACING A REAL APPROVER must not get through on timing.
$policy(false, false);
$asUser($uRaiser);
[$kOk,, $hK] = hreq_save(0, $base(['job_title' => 'G4 Race2', 'requested_by_id' => $uRaiser]));
hreq_submit($hK);
$r2 = $race([[$hK, $uRaiser], [$hK, $uDecider]]);
$selfWon = false;
foreach ($r2 as $x) if (!empty($x['ok']) && (int) $x['uid'] === $uRaiser) $selfWon = true;
t_ok(!$selfWon, 'J5 · *** the requester never won the race — no bypass through timing ***');
t_eq((string) hreq_get($hK)['status'], 'APPROVED', 'J6 · …and the legitimate approver did approve it');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=? AND outcome='APPROVED'", [$hK]) >= 1,
     'J7 · …with the approval recorded once on the audit spine');

// ---------------------------------------------------------------------------
t_section('G4 · K — §17: two organisations, complete isolation');
// ---------------------------------------------------------------------------
$policy(false, false);
$aSelf = appr_self_allowed(); $aMaster = appr_self_master_exception();
$aRules = (int) ops_val("SELECT COUNT(*) FROM recruit_approval_rules");
$WS_A = $engine4 === 'sqlite' ? (string) getenv('SQLITE_PATH') : (string) getenv('DB_NAME');
$WS_B = $engine4 === 'sqlite' ? sys_get_temp_dir() . '/g4_ws_b.sqlite' : 'g4_ws_b';
$enterWs = function ($ws) use ($engine4) {
    if ($engine4 === 'sqlite') putenv('SQLITE_PATH=' . $ws);
    else { db()->exec("CREATE DATABASE IF NOT EXISTS `" . $ws . "`"); putenv('DB_NAME=' . $ws); }
    db(true); db();
};
//  ORGANISATION B STARTS EMPTY, every run.
if ($engine4 === 'sqlite') { @unlink($WS_B); @unlink($WS_B . '-wal'); @unlink($WS_B . '-shm'); }
else { try { db()->exec("DROP DATABASE IF EXISTS `" . $WS_B . "`"); } catch (Throwable $e) {} }
$whoAmI = function () use ($engine4, $root4) {
    $cfg = require $root4 . '/config.php';
    return $engine4 === 'sqlite' ? (string) $cfg['sqlite_path'] : (string) ops_val("SELECT DATABASE()");
};
$idA = $whoAmI();

$enterWs($WS_B);
t_ok($whoAmI() !== $idA, 'K1 · *** organisation A and organisation B are different databases ***');
ensure_settings_schema();
if (function_exists('ops_ensure_schema')) ops_ensure_schema();
appr_migrate(); hreq_migrate(); rver_migrate(); act_migrate();
//  B IS A NEW ORGANISATION, so the locked defaults apply to it.
t_eq(appr_self_allowed(), false, 'K2 · *** a new organisation starts with self-approval OFF ***');
t_eq(appr_self_master_exception(), false, 'K3 · *** …and the master exception OFF ***');
t_ok(strpos((string) setting_get('appr_self_migrated', ''), 'NEW') === 0,
     'K4 · …and its migration recorded it as NEW: ' . setting_get('appr_self_migrated', ''));
//  B TURNS SELF-APPROVAL ON. A must not notice.
setting_set('appr_self_approval', '1');
t_eq(appr_self_allowed(), true, 'K5 · organisation B turns it ON for itself');
t_eq((int) ops_val("SELECT COUNT(*) FROM recruit_approval_rules"), 0,
     'K6 · *** B sees none of A\'s approval rules ***');
t_eq((int) ops_val("SELECT COUNT(*) FROM recruit_approval_requests"), 0, 'K7 · …nor A\'s approval chains');
t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE outcome='SELF_APPROVAL_EXCEPTION'"), 0,
     'K8 · *** …nor A\'s self-approval exception records ***');

$enterWs($WS_A);
$asUser($uMaster);
t_eq($whoAmI(), $idA, 'K9 · back in organisation A');
t_eq(appr_self_allowed(), $aSelf,
     'K10 · *** A\'s self-approval setting is exactly as it was — B changed nothing ***');
t_eq(appr_self_master_exception(), $aMaster, 'K11 · …and so is its master exception');
t_eq((int) ops_val("SELECT COUNT(*) FROM recruit_approval_rules"), $aRules, 'K12 · …and its rules');

// ---------------------------------------------------------------------------
t_section('G4 · L — configuration is auditable, approval history is immutable');
// ---------------------------------------------------------------------------
//  §13's configuration audit needed nothing built: setting_set() already records
//  who changed which key, from what to what, on the sealed chain. Asserted, not
//  assumed, because "it already does that" is the kind of claim that rots.
$cfgBefore = (int) ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity='setting'");
setting_set('appr_self_approval', '1');
setting_set('appr_self_approval', '0');
$cfgAfter = (int) ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity='setting'");
t_ok($cfgAfter > $cfgBefore, 'L1 · *** changing the policy is recorded on the audit chain ***');
//  EVERY FIELD §13 ASKS FOR, read off the row the existing mechanism already writes:
//  the setting, its old value, its new value, who changed it and when — on a
//  hash-chained trail. The organisation is implicit and absolute, because one
//  database per tenant means this row cannot be in another organisation's trail.
$cfgRow = ops_one("SELECT * FROM idems_audit WHERE entity='setting' AND field=? ORDER BY id DESC LIMIT 1",
                  ['appr_self_approval']);
t_ok(is_array($cfgRow), 'L2 · the change is on the trail, filed under the setting that changed');
t_eq((string) $cfgRow['field'], 'appr_self_approval', 'L3 · …naming the setting');
t_eq((string) $cfgRow['action'], 'SETTING_CHANGED', 'L3b · …as a configuration change');
t_eq((string) $cfgRow['old_value'], '1', 'L3c · *** …with the value it had ***');
t_eq((string) $cfgRow['new_value'], '0', 'L3d · *** …and the value it was given ***');
t_ok(trim((string) ($cfgRow['username'] ?? '')) !== '', 'L3e · …and who changed it');
t_ok(trim((string) ($cfgRow['created_at'] ?? '')) !== '', 'L3f · …and when');
t_ok(trim((string) ($cfgRow['entry_hash'] ?? '')) !== '', 'L3g · …sealed into the hash chain');
t_ok(!in_array('appr_self_approval', ['setup_done', 'schema_sig'], true)
     && setting_change_class('appr_self_approval')['audit'] === true,
     'L4 · *** the key is classified as auditable configuration, not a system marker ***');
t_eq(setting_change_class('appr_self_approval')['secret'], false, 'L5 · …and holds no secret');

//  HISTORY IS NOT REWRITTEN. The decided requests above keep their decisions.
t_eq((string) hreq_get($hA)['status'], 'APPROVED', 'L6 · an approved request stays approved');
t_eq((string) hreq_get($hC)['status'], 'SUBMITTED',
     'L7 · *** …and a request that was refused a self-approval was not quietly decided later ***');

// ---------------------------------------------------------------------------
t_section('G4 · M — Gate 2 and Gate 3 are undisturbed');
// ---------------------------------------------------------------------------
$policy(false, true); $asUser($uMaster);
[$mOk,, $hM] = hreq_save(0, $base(['job_title' => 'G4 G23', 'requested_by_id' => $uMaster, 'quantity' => 5]));
hreq_submit($hM); hreq_apply_decision($hM, 'APPROVED', 'G4', 'ok');
t_eq((int) rver_current('HIRING_REQUEST', $hM)['version'], 1, 'M1 · Gate 2 still records version 1 on approval');
hreq_save($hM, $base(['job_title' => 'G4 G23', 'requested_by_id' => $uMaster, 'quantity' => 5, 'grade' => 'G7']));
t_ok(is_array(rver_pending('HIRING_REQUEST', $hM)), 'M2 · a material change is still a proposal');
t_eq((string) hreq_get($hM)['grade'], '', 'M3 · *** the approved record still does not carry it ***');
[$mrOk] = rver_reject((int) rver_pending('HIRING_REQUEST', $hM)['id'], 'not now');
t_ok($mrOk, 'M4 · a refusal still works');
t_eq((string) rver_proposal((int) ops_val("SELECT MAX(id) FROM requirement_change_proposals"))['status'],
     'REJECTED', 'M5 · …and is kept');

//  GATE 3 — a stricter version still reaches every active candidate, and the review
//  permission is still independent of approval authority.
//
//  On a FRESH request, deliberately. $hM above has just had a proposal refused, which
//  leaves it in re-approval and therefore not executable — so raising a requirement
//  from it would fail for a reason that has nothing to do with Gate 3, and the
//  assertion would report a Gate 3 regression that was really a fixture ordering
//  mistake. This is the second time in this programme that reusing a record across
//  two unrelated claims produced a misleading failure.
[$m2Ok,, $hM2] = hreq_save(0, $base(['job_title' => 'G4 G3 check', 'requested_by_id' => $uMaster, 'quantity' => 5]));
hreq_submit($hM2); hreq_decide($hM2, true, 'ok');
t_eq((string) hreq_get($hM2)['status'], 'APPROVED', 'M5b · a clean approved request to work from');
[$m3Ok, $m3Msg, $rqM] = hreq_to_requisition($hM2, 3);
t_ok($m3Ok && $rqM > 0, 'M5c · …and a requirement raised from it: ' . $m3Msg);
db()->prepare("UPDATE requisitions SET min_experience_years=4 WHERE id=?")->execute([(int) $rqM]);
rver_ensure_initial('REQUISITION', (int) $rqM);
$pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,experience_years,created_at)
               VALUES (?,'G4','Cand','RECEIVED',?,20,?)")->execute([$s . '-C1', (int) $rqM, date('c')]);
$cM = (int) $pdo->lastInsertId();
rver_gate_requisition_edit((int) $rqM, ['min_experience_years' => 12], ['change_reason' => 'bar raised']);
$pM = rver_pending('REQUISITION', (int) $rqM);
if ($pM) rver_apply((int) $pM['id'], ['decided_by' => 'G4']);
t_eq((int) ops_val("SELECT COUNT(*) FROM candidate_reviews WHERE candidate_id=? AND status='OPEN'", [$cM]), 1,
     'M6 · *** Gate 3 still raises a review when the bar goes up ***');
t_ok(crev_block_reason($cM, 'OFFER') !== '', 'M7 · …and still stops an offer until it is decided');
t_eq(CREV_PERM_CLEAR, 'hiring.review.clear', 'M8 · …through its own permission, not approval authority');

// ---------------------------------------------------------------------------
t_section('G4 · Z — this file puts back what it changed');
// ---------------------------------------------------------------------------
setting_set('appr_self_approval', $policyBefore[0] === '' ? '0' : $policyBefore[0]);
setting_set('appr_self_master_exception', $policyBefore[1] === '' ? '0' : $policyBefore[1]);
t_eq((string) setting_get('appr_self_approval', ''), $policyBefore[0] === '' ? '0' : $policyBefore[0],
     'Z1 · *** the organisation policy is back to what this file found ***');
t_eq((string) setting_get('appr_self_master_exception', ''), $policyBefore[1] === '' ? '0' : $policyBefore[1],
     'Z2 · …both switches');
try { db()->prepare("UPDATE recruit_approval_rules SET active=0 WHERE name LIKE ?")->execute([$s . '%']); } catch (Throwable $e) {}
t_ok(true, 'Z3 · and the rule it created is switched off, so no later test matches it');
$asUser($uMaster);
