<?php
// ============================================================================
//  PHASE 3 · M2 — APPROVAL MATRIX, AUTHORITY & DELEGATION
//
//  M2 did not build an approval engine or a rules engine. Phase 6 had both, and
//  M1 connected the Hiring Request to them. M2 adds the two dimensions the
//  matrix could not express (branch, effective dates), writes down the
//  precedence that was already deterministic but undocumented, warns about a
//  configuration that cannot run, and builds the one thing genuinely missing:
//  a standing, dated, scoped delegation of approval authority.
//
//  The sentence an administrator should be able to read off the screen:
//      "For this type of hiring request, these authorities must approve it."
// ============================================================================

t_section('Phase 3 · M2 — approval matrix, authority & delegation');

$pdo = db();
hreq_migrate(); appr_migrate();
$mine = ['h' => [], 'u' => [], 'o' => [], 'rule' => [], 'del' => []];
$origSess = $_SESSION;

foreach ([[991, 'M2 Ahmedabad'], [992, 'M2 Mumbai']] as $o) {
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); $mine['o'][] = $o[0]; } catch (Throwable $e) {}
}
$mk = function ($un, $role, $super, $office, $perms) use ($pdo, &$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions)
                   VALUES (?,?,?,1,?,?,'',?)")->execute([$un, 'M2', $role, $super ? 1 : 0, $office, $perms]);
    $id = (int) $pdo->lastInsertId(); $mine['u'][] = $id; return $id;
};
$act = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };
$HR = 'mod.hiring.view,mod.hiring.edit';
$uReq   = $mk('m2_req',   'BRANCH_MANAGER', 0, 991, $HR);
$uAppr  = $mk('m2_appr',  'BRANCH_MANAGER', 0, 991, $HR);
$uDeleg = $mk('m2_deleg', 'COORDINATOR',    0, 991, $HR);   // acts for uAppr while away
$uFar   = $mk('m2_far',   'BRANCH_MANAGER', 0, 992, $HR);
$uMast  = $mk('m2_master','ADMIN',          1, 991, '');
$uPlain = $mk('m2_plain', 'INSPECTOR',      0, 991, 'mod.hiring.view');

$rule = function (array $x = []) use (&$mine) {
    $id = appr_rule_save(0, array_merge(['entity' => 'HIRING_REQUEST', 'name' => 'r'], $x));
    $mine['rule'][] = $id; return $id;
};
$ctx = fn(array $x = []) => array_merge(
    ['department' => '', 'sbu' => '', 'grade' => '', 'position' => '', 'office_id' => 0, 'amount' => 0], $x);

// ---------------------------------------------------------------------------
//  1 · Reuse, not rebuild
// ---------------------------------------------------------------------------
t_section('M2.1 · reuse');
foreach (['recruit_approval_rules','recruit_approval_levels','recruit_approval_requests','recruit_approval_steps'] as $t)
    t_ok(t_table_exists($t), 'M2.1 · the Phase-6 table ' . $t . ' is what M2 uses');
t_ok(!t_table_exists('approval_policies') && !t_table_exists('approval_rules_v2'),
     'M2.1 · no second rules engine was created');
$cols = t_columns('recruit_approval_rules');
foreach (['applies_office_id','effective_from','effective_to'] as $c)
    t_ok(in_array($c, $cols, true), 'M2.1 · ' . $c . ' was added to the EXISTING rules table');
t_ok(in_array('sort', $cols, true), 'M2.1 · priority reuses the existing "sort" column — no new priority field');
t_ok(t_table_exists('approval_delegations'), 'M2.1 · one new table, for the one thing that did not exist');

// ---------------------------------------------------------------------------
//  2 · Matching is deterministic
// ---------------------------------------------------------------------------
t_section('M2.2 · deterministic matching');
t_eq(appr_match('HIRING_REQUEST', $ctx()), null, 'M2.2 · no rules configured → no match');

$rGlobal = $rule(['name' => 'M2 global', 'sort' => 50]);
t_eq((int) appr_match('HIRING_REQUEST', $ctx())['id'], $rGlobal, 'M2.2 · one rule → that rule');

$rDept = $rule(['name' => 'M2 engineering', 'applies_department' => 'Engineering', 'sort' => 50]);
t_eq((int) appr_match('HIRING_REQUEST', $ctx(['department' => 'Engineering']))['id'], $rDept,
     'M2.2 · a rule that names the department beats the global one — more specific wins');
t_eq((int) appr_match('HIRING_REQUEST', $ctx(['department' => 'Quality']))['id'], $rGlobal,
     'M2.2 · …and for another department the global one still applies');

$rBranch = $rule(['name' => 'M2 engineering @ Ahmedabad', 'applies_department' => 'Engineering',
                  'applies_office_id' => 991, 'sort' => 50]);
t_eq((int) appr_match('HIRING_REQUEST', $ctx(['department' => 'Engineering', 'office_id' => 991]))['id'], $rBranch,
     'M2.2 · department + BRANCH beats department alone');
t_eq((int) appr_match('HIRING_REQUEST', $ctx(['department' => 'Engineering', 'office_id' => 992]))['id'], $rDept,
     'M2.2 · and in another branch the branch rule does not apply at all');

// Ties are broken by match order, then by age — never randomly.
$rTieA = $rule(['name' => 'M2 tie A', 'applies_grade' => 'SENIOR', 'sort' => 20]);
$rTieB = $rule(['name' => 'M2 tie B', 'applies_grade' => 'SENIOR', 'sort' => 10]);
t_eq((int) appr_match('HIRING_REQUEST', $ctx(['grade' => 'SENIOR']))['id'], $rTieB,
     'M2.2 · two equally specific rules → the lower match order wins');
for ($i = 0; $i < 5; $i++)
    t_eq((int) appr_match('HIRING_REQUEST', $ctx(['grade' => 'SENIOR']))['id'], $rTieB,
         'M2.2 · and it is the same answer every time (' . ($i + 1) . '/5)');
$ranked = appr_match_all('HIRING_REQUEST', $ctx(['grade' => 'SENIOR']));
t_ok(count($ranked) >= 3, 'M2.2 · the runners-up are visible to the administrator, not hidden');

// Inactive and out-of-date rules are ignored.
appr_rule_set_active($rTieB, false);
t_eq((int) appr_match('HIRING_REQUEST', $ctx(['grade' => 'SENIOR']))['id'], $rTieA,
     'M2.2 · an inactive rule is ignored');
appr_rule_set_active($rTieB, true);
$future = date('Y-m-d', strtotime('+30 days'));
$past   = date('Y-m-d', strtotime('-30 days'));
$rFuture = $rule(['name' => 'M2 not yet', 'applies_position' => 'WELDER', 'effective_from' => $future, 'sort' => 1]);
t_ok(appr_match('HIRING_REQUEST', $ctx(['position' => 'WELDER']))['id'] !== $rFuture,
     'M2.2 · a rule that has not started yet does not apply');
$rExpired = $rule(['name' => 'M2 expired', 'applies_position' => 'FITTER', 'effective_to' => $past, 'sort' => 1]);
t_ok(appr_match('HIRING_REQUEST', $ctx(['position' => 'FITTER']))['id'] !== $rExpired,
     'M2.2 · and an expired rule stops applying');
$rLive = $rule(['name' => 'M2 in force', 'applies_position' => 'RIGGER',
                'effective_from' => $past, 'effective_to' => $future, 'sort' => 1]);
t_eq((int) appr_match('HIRING_REQUEST', $ctx(['position' => 'RIGGER']))['id'], $rLive,
     'M2.2 · a rule inside its window does apply');

// ---------------------------------------------------------------------------
//  3 · Orphan approver roles (M1 Finding 3)
// ---------------------------------------------------------------------------
t_section('M2.3 · a policy nobody can action');
appr_level_save(['rule_id' => $rGlobal, 'seq' => 1, 'label' => 'Branch manager', 'approver_role' => 'BRANCH_MANAGER']);
t_eq(count(appr_orphan_levels($rGlobal)), 0, 'M2.3 · a level whose role somebody holds is not an orphan');
$rOrphan = $rule(['name' => 'M2 orphan', 'applies_grade' => 'EXEC', 'sort' => 5]);
appr_level_save(['rule_id' => $rOrphan, 'seq' => 1, 'label' => 'Finance', 'approver_role' => 'FINANCE']);
// The orphan condition is created BY CONSTRUCTION rather than by assuming the
// role is unused: the whole suite shares one database and earlier files create
// Finance users, so a test that assumed their absence passed alone and failed in
// the suite. Park any active holders for the duration, then put them back.
$parked = array_map(fn($r) => (int) $r['id'],
    ops_all("SELECT id FROM users WHERE role='FINANCE' AND is_active=1") ?: []);
foreach ($parked as $pid) $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$pid]);
t_eq((int) ops_val("SELECT COUNT(*) FROM users WHERE role='FINANCE' AND is_active=1"), 0,
     'M2.3 · with no active Finance user (' . count($parked) . ' parked for this check)');
$orph = appr_orphan_levels($rOrphan);
t_eq(count($orph), 1, 'M2.3 · so the administrator is warned the policy cannot run');
t_ok(strpos($orph[0]['why'], 'no active user') !== false, 'M2.3 · …and told why: ' . $orph[0]['why']);
$pvOrphNow = appr_preview('HIRING_REQUEST', $ctx(['grade' => 'EXEC']));
t_ok(count($pvOrphNow['orphans']) === 1, 'M2.3 · and the preview surfaces it too');
// Prove the warning CLEARS by giving the role a holder — created here, so this
// works whether or not the suite happened to have Finance users already.
$uFin = $mk('m2_finance', 'FINANCE', 0, 991, $HR);
t_eq(count(appr_orphan_levels($rOrphan)), 0,
     'M2.3 · …and once somebody holds the role, the warning clears');
$pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$uFin]);
t_eq(count(appr_orphan_levels($rOrphan)), 1,
     'M2.3 · switching that person off brings it back — it tracks ACTIVE holders');
foreach ($parked as $pid) $pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$pid]);

// ---------------------------------------------------------------------------
//  4 · "Why this approval?" — the deterministic resolver
// ---------------------------------------------------------------------------
t_section('M2.4 · preview');
$pv = appr_preview('HIRING_REQUEST', $ctx(['department' => 'Engineering', 'office_id' => 991]));
t_eq((int) $pv['matched']['id'], $rBranch, 'M2.4 · the preview names the winning policy');
t_ok(!$pv['no_match'], 'M2.4 · and says it matched');
$pvNone = appr_preview('OFFER', $ctx(['department' => 'Nothing configured here']));
t_ok($pvNone['no_match'], 'M2.4 · with nothing configured it says so, rather than inventing a policy');
// (the orphan preview is asserted in M2.3, where the condition is constructed)

// ---------------------------------------------------------------------------
//  5 · Delegation
// ---------------------------------------------------------------------------
t_section('M2.5 · delegation');
$act($uMast);
$today = date('Y-m-d');
[$dOk, $dMsg, $dId] = appr_delegation_save(0, ['delegator_user_id' => $uAppr, 'delegate_user_id' => $uDeleg,
    'entity' => 'HIRING_REQUEST', 'effective_from' => $today, 'effective_to' => $today, 'reason' => 'annual leave']);
t_ok($dOk, 'M2.5 · a delegation is created: ' . $dMsg);
if ($dOk) $mine['del'][] = $dId;
t_ok(in_array($uAppr, appr_delegators_for($uDeleg, 'HIRING_REQUEST', 991), true),
     'M2.5 · the delegate may act for the delegator today');
t_ok(!appr_delegators_for($uAppr, 'HIRING_REQUEST', 991),
     'M2.5 · and the delegator has gained nothing themselves');

// Refusals at save time.
t_ok(!appr_delegation_save(0, ['delegator_user_id' => $uAppr, 'delegate_user_id' => $uAppr])[0],
     'M2.5 · a person cannot delegate to themselves');
t_ok(!appr_delegation_save(0, ['delegator_user_id' => $uAppr, 'delegate_user_id' => 999999])[0],
     'M2.5 · nor to somebody who is not in this workspace');
t_ok(!appr_delegation_save(0, ['delegator_user_id' => $uAppr, 'delegate_user_id' => $uDeleg,
     'effective_from' => '2026-02-01', 'effective_to' => '2026-01-01'])[0],
     'M2.5 · a delegation cannot end before it starts');

// Windows.
[$fOk, , $fId] = appr_delegation_save(0, ['delegator_user_id' => $uAppr, 'delegate_user_id' => $uFar,
    'effective_from' => date('Y-m-d', strtotime('+10 days'))]);
if ($fOk) $mine['del'][] = $fId;
t_ok(!in_array($uAppr, appr_delegators_for($uFar, 'HIRING_REQUEST', 991), true),
     'M2.5 · a delegation that has not started yet grants nothing');
[$eOk, , $eId] = appr_delegation_save(0, ['delegator_user_id' => $uAppr, 'delegate_user_id' => $uPlain,
    'effective_to' => date('Y-m-d', strtotime('-1 day'))]);
if ($eOk) $mine['del'][] = $eId;
t_ok(!in_array($uAppr, appr_delegators_for($uPlain, 'HIRING_REQUEST', 991), true),
     'M2.5 · and an expired one grants nothing');

// Scope.
[$sOk, , $sId] = appr_delegation_save(0, ['delegator_user_id' => $uAppr, 'delegate_user_id' => $uDeleg,
    'entity' => 'OFFER']);
if ($sOk) $mine['del'][] = $sId;
t_ok(!in_array($uAppr, appr_delegators_for($uDeleg, 'SALARY', 991), true),
     'M2.5 · a delegation for one kind of approval does not cover another');

// Revocation.
appr_delegation_revoke($dId);
t_ok(!in_array($uAppr, appr_delegators_for($uDeleg, 'HIRING_REQUEST', 991), true),
     'M2.5 · a revoked delegation stops working immediately');
[$rOk2, , $dId2] = appr_delegation_save(0, ['delegator_user_id' => $uAppr, 'delegate_user_id' => $uDeleg,
    'entity' => 'HIRING_REQUEST', 'effective_from' => $today]);
if ($rOk2) $mine['del'][] = $dId2;

// No chaining.
[$cOk, , $cId] = appr_delegation_save(0, ['delegator_user_id' => $uDeleg, 'delegate_user_id' => $uFar,
    'entity' => 'HIRING_REQUEST', 'effective_from' => $today]);
if ($cOk) $mine['del'][] = $cId;
t_ok(appr_delegation_chains($uFar, 'HIRING_REQUEST', null),
     'M2.5 · a chain A→B→C is detectable…');
t_ok(!in_array($uAppr, appr_delegators_for($uFar, 'HIRING_REQUEST', 991), true),
     'M2.5 · …and does NOT reach through: C may act for B, never for A');
appr_delegation_revoke($cId);

// ---------------------------------------------------------------------------
//  6 · Delegation cannot defeat segregation of duties (§23)
// ---------------------------------------------------------------------------
t_section('M2.6 · delegation never beats segregation');
appr_level_save(['rule_id' => $rGlobal, 'seq' => 1, 'label' => 'Branch manager', 'approver_role' => 'BRANCH_MANAGER',
                 'level_id' => (appr_levels($rGlobal)[0]['id'] ?? 0)]);
$act($uReq);
$form = ['job_title' => 'M2 welder', 'quantity' => 1, 'office_id' => 991, 'priority' => 'NORMAL', 'approval_required' => 1];
[$hOk, , $h1] = hreq_save(0, $form);
if ($hOk) $mine['h'][] = $h1;
hreq_submit($h1);
$ap = hreq_approval($h1);
t_ok($ap !== null, 'M2.6 · the request went to its approvers');
$step = appr_current_step($ap);

// The requestor is delegated the approver's authority — and still cannot approve.
$act($uMast);
[$bOk, , $bId] = appr_delegation_save(0, ['delegator_user_id' => $uAppr, 'delegate_user_id' => $uReq,
    'entity' => 'HIRING_REQUEST', 'effective_from' => $today]);
if ($bOk) $mine['del'][] = $bId;
$act($uReq);
t_ok(in_array($uAppr, appr_delegators_for($uReq, 'HIRING_REQUEST', 991), true),
     'M2.6 · the REQUESTOR now holds the approver\'s delegated authority');
t_ok(appr_guard($ap) !== '', 'M2.6 · and is still refused: ' . appr_guard($ap));
[$xOk, $xMsg] = appr_act((int) $step['id'], 'approve', 'via delegation');
t_ok(!$xOk, 'M2.6 · delegation does not let you approve your own request: ' . $xMsg);
t_eq(hreq_get($h1)['status'], 'UNDER_REVIEW', 'M2.6 · nothing was written');
appr_delegation_revoke($bId);

// A genuine delegate, who is not the requestor, CAN act.
$act($uDeleg);
t_ok(appr_can_act(appr_step_context($step, $ap)), 'M2.6 · the real delegate is eligible for the step');
t_eq(appr_guard($ap), '', 'M2.6 · and the guard allows them');
[$yOk, $yMsg] = appr_act((int) $step['id'], 'approve', 'approved while the manager is away');
t_ok($yOk, 'M2.6 · so the delegate approves it: ' . $yMsg);
t_eq(hreq_get($h1)['status'], 'APPROVED', 'M2.6 · and the request is approved');
t_ok(hreq_is_executable($h1), 'M2.6 · and only now executable');

// D12 — a delegation cannot hand over an authority the delegator never had.
//  Mutation D12 survived until this existed: every delegation test used a
//  delegator who genuinely held the approver's role, so dropping the role check
//  changed nothing observable.
t_section('M2.6b · delegation grants only what the delegator holds');
$act($uMast);
$uNobody = $mk('m2_nobody', 'INSPECTOR', 0, 991, $HR);   // holds no approver role
$uVia    = $mk('m2_via',    'INSPECTOR', 0, 991, $HR);
[$nOk, , $nId] = appr_delegation_save(0, ['delegator_user_id' => $uNobody, 'delegate_user_id' => $uVia,
    'entity' => 'HIRING_REQUEST', 'effective_from' => $today]);
if ($nOk) $mine['del'][] = $nId;
$act($uReq);
[$gOk, , $hG] = hreq_save(0, array_merge($form, ['job_title' => 'M2 authority check']));
if ($gOk) $mine['h'][] = $hG;
hreq_submit($hG);
$apG = hreq_approval($hG); $stepG = appr_current_step($apG);
$act($uVia);
t_ok(in_array($uNobody, appr_delegators_for($uVia, 'HIRING_REQUEST', 991), true),
     'M2.6b · the delegation itself is live');
t_ok(!appr_can_act(appr_step_context($stepG, $apG)),
     'M2.6b · but the delegator holds no approver role, so the delegate may NOT act');
t_ok(!appr_act((int) $stepG['id'], 'approve')[0], 'M2.6b · and the decision is refused');
t_eq(hreq_get($hG)['status'], 'UNDER_REVIEW', 'M2.6b · nothing was written');
appr_delegation_revoke($nId);

// D13 — the approval QUEUE must show only what the person may act on. This is
//  M1 Finding 2, closed in M2; it survived mutation until it was a test rather
//  than an ad-hoc probe.
t_section('M2.6c · the queue shows only what you may act on');
$act($uFar);                                   // holds the approver role, wrong branch
t_ok(appr_can_act(appr_step_context($stepG, $apG)),
     'M2.6c · the foreign-branch user does hold the approver role');
t_ok(appr_guard($apG) !== '', 'M2.6c · but may not act on this branch\'s request');
$far = array_filter(appr_inbox(), fn($x) => (int) $x['id'] === (int) $stepG['id']);
t_eq(count($far), 0, 'M2.6c · so it is NOT in their queue — seeing and acting are the same question');
$act($uAppr);                                  // right branch, right role
$ok2 = array_filter(appr_inbox(), fn($x) => (int) $x['id'] === (int) $stepG['id']);
t_eq(count($ok2), 1, 'M2.6c · and it IS in the queue of somebody who may act on it');

// D18 — the branch really reaches the matcher from a live hiring request. The
//  matrix tests call appr_match() with a context they build themselves, so
//  blanking office_id in hreq_appr_ctx() changed nothing they could see.
t_section('M2.6d · a live request carries its branch into the matcher');
$act($uMast);
$rAhm = $rule(['name' => 'M2 Ahmedabad only', 'applies_office_id' => 991, 'sort' => 2]);
appr_level_save(['rule_id' => $rAhm, 'seq' => 1, 'label' => 'Branch manager', 'approver_role' => 'BRANCH_MANAGER']);
$act($uReq);
[$bOk2, , $hB] = hreq_save(0, array_merge($form, ['job_title' => 'M2 branch routed', 'office_id' => 991]));
if ($bOk2) $mine['h'][] = $hB;
hreq_submit($hB);
$apB = hreq_approval($hB);
t_ok($apB !== null, 'M2.6d · the request started a chain');
t_eq((int) $apB['rule_id'], $rAhm,
     'M2.6d · and it was routed by the BRANCH policy — the branch reached the matcher');
t_eq((string) $apB['rule_name'], 'M2 Ahmedabad only', 'M2.6d · …recorded by name too');

// ---------------------------------------------------------------------------
//  7 · Policy history survives configuration change (§16, §17)
// ---------------------------------------------------------------------------
t_section('M2.7 · the applied policy is remembered');
$apDone = hreq_approval($h1);
t_ok((int) $apDone['rule_id'] > 0, 'M2.7 · the chain records WHICH policy required this approval');
t_eq((string) $apDone['rule_name'], 'M2 global', 'M2.7 · …by name as well as by id, so a later rename still reads correctly');
appr_rule_save($rGlobal, ['name' => 'M2 global RENAMED']);
$apAfter = hreq_approval($h1);
t_eq((string) $apAfter['rule_name'], 'M2 global', 'M2.7 · renaming the rule does not rewrite the finished chain');
t_eq((int) $apAfter['rule_id'], (int) $apDone['rule_id'], 'M2.7 · nor its policy reference');

// ---------------------------------------------------------------------------
//  8 · Configuration is privileged (§26)
// ---------------------------------------------------------------------------
t_section('M2.8 · configuration security');
$src = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/recruit_approval.php'));
$dlg = substr($src, strpos($src, 'function ops_approval_delegations('), 400);
t_ok(strpos($dlg, 'hiring_admin_can()') !== false,
     'M2.8 · the delegation screen is gated like the rest of approval configuration');
t_ok(strpos(substr($src, strpos($src, 'function ops_recruit_approvals('), 300), 'hiring_admin_can()') !== false,
     'M2.8 · and so is the policy screen');
t_ok(strpos(file_get_contents(__DIR__ . '/../lib/ops.php'), "'approval-delegations'=>'hiring'") !== false,
     'M2.8 · the delegation route is entitlement-mapped to the paid recruitment module');
$offWas = setting_get('modules_off', '');
$act($uMast);
t_ok(ops_module_gate('approval-delegations', true), 'M2.8 · with HR on, the route opens');
setting_set('modules_off', 'hr'); licence_disabled(true); ua(true);
t_ok(!ops_module_gate('approval-delegations', true), 'M2.8 · with HR off it is refused — for a master too');
setting_set('modules_off', $offWas); licence_disabled(true); ua(true);

// A partial update must not erase conditions (§34).
$act($uMast);
$before = appr_rule($rBranch);
appr_rule_save($rBranch, ['name' => 'M2 renamed only']);
$after = appr_rule($rBranch);
t_eq((string) $after['applies_department'], (string) $before['applies_department'],
     'M2.8 · a partial update keeps the department condition');
t_eq((int) $after['applies_office_id'], (int) $before['applies_office_id'], 'M2.8 · …and the branch');
t_eq((int) $after['sort'], (int) $before['sort'], 'M2.8 · …and the match order');

// Configuration changes are audited on the existing spine (§35).
$pol = ops_all("SELECT subject FROM activities WHERE entity_kind='APPROVAL_POLICY' ORDER BY id");
t_ok(count($pol) > 0, 'M2.8 · policy changes are on the audit trail (' . count($pol) . ' entries)');
$del = ops_all("SELECT subject FROM activities WHERE entity_kind='APPROVAL_DELEGATE' ORDER BY id");
t_ok(count($del) > 0, 'M2.8 · and so are delegation changes (' . count($del) . ')');
t_ok(count(array_filter($del, fn($x) => strpos((string) $x['subject'], 'revoked') !== false)) > 0,
     'M2.8 · including revocation');

// ---------------------------------------------------------------------------
//  Clean up.
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
foreach ($mine['h'] as $id) {
    $pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id IN (SELECT id FROM recruit_approval_requests WHERE entity='HIRING_REQUEST' AND entity_id=?)")->execute([$id]);
    $pdo->prepare("DELETE FROM recruit_approval_requests WHERE entity='HIRING_REQUEST' AND entity_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([$id]);
}
foreach ($mine['rule'] as $id) {
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?")->execute([$id]);
}
foreach ($mine['del'] as $id) {
    $pdo->prepare("DELETE FROM approval_delegations WHERE id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='APPROVAL_DELEGATE' AND entity_id=?")->execute([$id]);
}
foreach ($mine['u'] as $id) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
foreach ($mine['o'] as $id) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$id]);
t_ok(true, 'M2 fixtures removed');
