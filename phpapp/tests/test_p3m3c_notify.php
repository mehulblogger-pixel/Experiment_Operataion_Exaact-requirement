<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION — NOTIFICATION VISIBILITY, ENTITLEMENT, ESCALATION
//
//  Three defects the M3 adversarial audit proved, and the rule each one is now
//  held to:
//
//   F1  NOTIFICATION ELIGIBILITY IS NEVER BROADER THAN APPROVAL VISIBILITY.
//       A role-based step used to write to every holder of the role in every
//       branch — including the person M1/M2 hide the request from and refuse at
//       the engine, and the requestor segregation will never let approve. E-mail
//       leaves the application, so that was a disclosure, and M3's reminders made
//       it repeat.
//   F2  DELEGATION IS GATED LIKE POLICY. Entitlement in front of capability, at
//       the write, for the more privileged of the two.
//   F3  ONCE ESCALATED, THE ROUTINE REMINDER STOPS. The loud signal fired once and
//       the quiet one repeated for ever; that was backwards and unbounded.
//
//  Every F1 assertion is made against the ACTUAL RECIPIENT LIST, and the important
//  ones again against what actually landed in email_log — never against a count of
//  notification events.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION — notification, entitlement, escalation');

$pdo = db(); hreq_migrate(); appr_migrate();
$mine = ['h' => [], 'u' => [], 'o' => [], 'rule' => [], 'del' => []];
$origSess = $_SESSION;
$offWas = function_exists('setting_get') ? (string) setting_get('modules_off', '') : '';

foreach ([[976, 'MC Branch A'], [977, 'MC Branch B']] as $o) {
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); $mine['o'][] = $o[0]; } catch (Throwable $e) {}
}
$mk = function ($un, $role, $super, $off, $scope, $perms, $email) use ($pdo, &$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions,email)
                   VALUES (?,?,?,?,1,?,?,?,?,?)")
        ->execute([$un, 'MC', ucfirst(substr($un, 3)), $role, $super ? 1 : 0, $off, $scope, $perms, $email]);
    $id = (int) $pdo->lastInsertId(); $mine['u'][] = $id; return $id;
};
$act = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };
$HR = 'mod.hiring.view,mod.hiring.edit';

$uReq   = $mk('mc_req',    'BRANCH_MANAGER',    0, 976, '976', $HR, 'mcreq@t.test');
$uApp   = $mk('mc_app',    'SBU_HEAD',          0, 976, '976', $HR, 'mcapp@t.test');
$uFar   = $mk('mc_far',    'SBU_HEAD',          0, 977, '977', $HR, 'mcfar@t.test');   // SAME ROLE, other branch
$uDel   = $mk('mc_del',    'COORDINATOR',       0, 976, '976', $HR, 'mcdel@t.test');
$uDelFar= $mk('mc_delfar', 'COORDINATOR',       0, 977, '977', $HR, 'mcdelfar@t.test');
$uNamed = $mk('mc_named',  'BUSINESS_DIRECTOR', 0, 976, '976', $HR, 'mcnamed@t.test');
$uNmFar = $mk('mc_nmfar',  'BUSINESS_DIRECTOR', 0, 977, '977', $HR, 'mcnmfar@t.test');
$uEsc   = $mk('mc_esc',    'OPERATION_MANAGER', 0, 976, '976', '',  'mcesc@t.test');
$uEscFar= $mk('mc_escfar', 'OPERATION_MANAGER', 0, 977, '977', '',  'mcescfar@t.test');
$uSelf  = $mk('mc_self',   'SBU_HEAD',          0, 976, '976', $HR, 'mcself@t.test');   // raises AND holds the approver role
$uMast  = $mk('mc_mast',   'ADMIN',             1, 976, '',    '',  'mcmast@t.test');

$cfg = function (callable $fn) use ($uMast) {
    $prev = $_SESSION['uid'] ?? null;
    $_SESSION['uid'] = $uMast; current_user(true); ua(true);
    try { return $fn(); }
    finally { if ($prev === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prev; current_user(true); ua(true); }
};
$today = date('Y-m-d');
$ago   = fn($d) => date('c', time() - $d * 86400);
$mails = fn($addr) => (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND to_addr=?", [$addr]);
$lst   = fn($a) => implode(',', $a) ?: '(none)';

$rule = $cfg(fn() => appr_rule_save(0, ['name' => 'MC hiring', 'entity' => 'HIRING_REQUEST', 'code' => 'MCHR', 'applies_office_id' => 976]));
$mine['rule'][] = $rule;
$cfg(fn() => appr_level_save(['rule_id' => $rule, 'seq' => 1, 'label' => 'Unit head', 'approver_role' => 'SBU_HEAD',
    'sla_days' => 2, 'reminder_days' => 1, 'escalate_user_id' => $uEsc]));

//  Raise a request at branch 976 as a given person, and hand back [id, req, step].
$raise = function ($asUser, $title) use ($act, &$mine) {
    $act($asUser);
    [$ok, , $id] = hreq_save(0, ['job_title' => $title, 'quantity' => 1, 'office_id' => 976,
                                 'priority' => 'NORMAL', 'approval_required' => 1]);
    if ($ok) $mine['h'][] = $id;
    hreq_submit($id);
    $ap = hreq_approval($id);
    $st = $ap ? appr_step_context(appr_current_step($ap), $ap) : null;
    return [$id, $ap, $st];
};

// ---------------------------------------------------------------------------
//  C1 · F1 ROLE PATH — holding the role is a candidacy, not an eligibility
// ---------------------------------------------------------------------------
t_section('MC1 · F1 role path');
[$h1, $ap1, $s1] = $raise($uReq, 'MC confidential restructure');
t_ok($ap1 !== null, 'MC1 · the request went to its approvers');
$to = appr_step_recipients($s1, $ap1);
t_ok(in_array('mcapp@t.test', $to, true), 'MC1 A · the SAME-BRANCH role approver IS notified — ' . $lst($to));
t_ok(!in_array('mcfar@t.test', $to, true), 'MC1 B · the OTHER-BRANCH holder of the same role is NOT — ' . $lst($to));

// …and that refusal agrees with the model it now asks.
$act($uFar);
t_ok(!scope_allows(976, null), 'MC1 B · the far approver is genuinely scoped out of branch 976');
t_ok(!in_array($h1, array_map(fn($x) => (int) $x['entity_id'], appr_inbox()), true),
     'MC1 B · M2 hides it from their queue');
t_ok(appr_guard($ap1) !== '', 'MC1 B · the engine refuses their decision');
[$fOk] = appr_act((int) $s1['id'], 'approve', 'from the other branch');
t_ok(!$fOk, 'MC1 B · …so notification, visibility and decision now say the same thing');

// And nothing naming the request reaches them when the scheduler runs.
$pdo->prepare("UPDATE recruit_approval_steps SET reminder_at=? WHERE id=?")->execute([$ago(1), (int) $s1['id']]);
$_SESSION = $origSess; current_user(true); ua(true);
appr_tick();
t_eq((int) ops_val("SELECT COUNT(*) FROM email_log WHERE to_addr='mcfar@t.test' AND subject LIKE ?", ['%confidential restructure%']), 0,
     'MC1 B · and NO e-mail naming the request reaches the other branch');
t_ok($mails('mcapp@t.test') >= 1, 'MC1 A · while the branch\'s own approver was written to');

// ---------------------------------------------------------------------------
//  C2 · F1 SEGREGATION — both halves proved, as required
// ---------------------------------------------------------------------------
t_section('MC2 · F1 segregation');
[$h2, $ap2, $s2] = $raise($uSelf, 'MC own request');
$act($uSelf);
t_ok(appr_guard($ap2) !== '', 'MC2 C · 1/2 the requestor\'s own decision is DENIED: ' . appr_guard($ap2));
[$sOk] = appr_act((int) $s2['id'], 'approve', 'mine');
t_ok(!$sOk, 'MC2 C · …refused at the engine, not hidden on a screen');
$to2 = appr_step_recipients($s2, $ap2);
t_ok(!in_array('mcself@t.test', $to2, true),
     'MC2 C · 2/2 and they are NOT asked by e-mail to approve it — ' . $lst($to2));
t_ok(in_array('mcapp@t.test', $to2, true), 'MC2 C · the person who CAN decide it still is');

// ---------------------------------------------------------------------------
//  C3 · F1 NAMED-USER PATH — audited separately, not assumed safe
// ---------------------------------------------------------------------------
t_section('MC3 · F1 named-user path');
[$h3, $ap3, $s3] = $raise($uReq, 'MC named');
$pdo->prepare("UPDATE recruit_approval_steps SET approver_role='', approver_user_id=? WHERE id=?")->execute([$uNamed, (int) $s3['id']]);
$s3 = appr_step_context(ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $s3['id']]), $ap3);
$to3 = appr_step_recipients($s3, $ap3);
t_ok(in_array('mcnamed@t.test', $to3, true), 'MC3 D · a NAMED eligible approver is notified — ' . $lst($to3));

$pdo->prepare("UPDATE recruit_approval_steps SET approver_user_id=? WHERE id=?")->execute([$uNmFar, (int) $s3['id']]);
$s3b = appr_step_context(ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $s3['id']]), $ap3);
$to3b = appr_step_recipients($s3b, $ap3);
t_ok(!in_array('mcnmfar@t.test', $to3b, true),
     'MC3 E · a NAMED approver outside the branch scope is NOT — being named is not a bypass — ' . $lst($to3b));
$act($uNmFar);
t_ok(appr_guard($ap3) !== '', 'MC3 E · and the engine refuses them too — the two agree');
$pdo->prepare("UPDATE recruit_approval_steps SET approver_role='SBU_HEAD', approver_user_id=NULL WHERE id=?")->execute([(int) $s3['id']]);

// ---------------------------------------------------------------------------
//  C4 · F1 DELEGATION PATH — M2's rules, unchanged, now also deciding the e-mail
// ---------------------------------------------------------------------------
t_section('MC4 · F1 delegation path');
[$h4, $ap4, $s4] = $raise($uReq, 'MC delegated');
$act($uMast);
[$dOk, $dMsg, $dId] = appr_delegation_save(0, ['delegator_user_id' => $uApp, 'delegate_user_id' => $uDel,
    'entity' => 'HIRING_REQUEST', 'office_id' => 976, 'effective_from' => $today]);
t_ok($dOk, 'MC4 · a delegation is configured: ' . $dMsg);
if ($dOk) $mine['del'][] = $dId;
t_ok(in_array('mcdel@t.test', appr_step_recipients($s4, $ap4), true), 'MC4 F · a VALID delegate is notified');

$pdo->prepare("UPDATE approval_delegations SET effective_to=? WHERE id=?")->execute([date('Y-m-d', strtotime('-1 day')), (int) $dId]);
t_ok(!in_array('mcdel@t.test', appr_step_recipients($s4, $ap4), true), 'MC4 G · an EXPIRED delegate is not');
$pdo->prepare("UPDATE approval_delegations SET effective_to='' WHERE id=?")->execute([(int) $dId]);

$pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$uApp]);
t_ok(!in_array('mcdel@t.test', appr_step_recipients($s4, $ap4), true), 'MC4 I · an INACTIVE DELEGATOR lends nothing');
$pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$uApp]);
t_ok(in_array('mcdel@t.test', appr_step_recipients($s4, $ap4), true), 'MC4 · reactivating the delegator restores it');

// A delegate outside the branch is refused by the SAME visibility question.
$act($uMast);
[$d2Ok, , $d2Id] = appr_delegation_save(0, ['delegator_user_id' => $uApp, 'delegate_user_id' => $uDelFar,
    'entity' => 'HIRING_REQUEST', 'effective_from' => $today]);
if ($d2Ok) $mine['del'][] = $d2Id;
t_ok(!in_array('mcdelfar@t.test', appr_step_recipients($s4, $ap4), true),
     'MC4 · a delegate who cannot see the branch is not notified either');
$act($uMast); appr_delegation_revoke((int) $d2Id);

$act($uMast); appr_delegation_revoke((int) $dId);
t_ok(!in_array('mcdel@t.test', appr_step_recipients($s4, $ap4), true), 'MC4 H · a REVOKED delegate is not notified');
$act($uDel);
t_ok(!appr_can_act($s4), 'MC4 H · and cannot act — M2 semantics are untouched');

// ---------------------------------------------------------------------------
//  C5 · F1 ENTITLEMENT AND TENANT
// ---------------------------------------------------------------------------
t_section('MC5 · F1 entitlement and tenant');
setting_set('modules_off', 'hr'); licence_disabled(true); ua(true);
t_eq(count(appr_step_recipients($s1, $ap1)), 0, 'MC5 J · an UNLICENSED workspace notifies nobody at all');
setting_set('modules_off', $offWas); licence_disabled(true); ua(true);
t_ok(count(appr_step_recipients($s1, $ap1)) >= 1, 'MC5 J · and notifies again once it is licensed');

$foreign = 99500000 + random_int(1, 999);
t_ok(!ops_one("SELECT id FROM users WHERE id=?", [$foreign]),
     'MC5 K · a foreign user id does not exist here — isolation is structural');
$sFk = $s1; $sFk['approver_role'] = ''; $sFk['approver_user_id'] = $foreign;
t_eq(count(appr_step_recipients($sFk, $ap1)), 0, 'MC5 K · naming one as the approver notifies nobody');

// ---------------------------------------------------------------------------
//  C6 · F1 ESCALATION — informational, and still not a disclosure free pass
// ---------------------------------------------------------------------------
t_section('MC6 · F1 escalation recipients');
[$h6, $ap6, $s6] = $raise($uReq, 'MC escalating');
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, reminder_at='', escalate_user_id=? WHERE id=?")
    ->execute([$ago(1), $uEsc, (int) $s6['id']]);
$_SESSION = $origSess; current_user(true); ua(true);
appr_tick();
t_ok($mails('mcesc@t.test') >= 1, 'MC6 L · the configured escalation contact IS told');
$body = (string) ops_val("SELECT body FROM email_log WHERE to_addr='mcesc@t.test' ORDER BY id DESC LIMIT 1");
t_ok(stripos($body, 'does not give you authority') !== false,
     'MC6 L · and the message says plainly that it grants no authority to approve');
$act($uEsc);
$s6b = appr_step_context(ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $s6['id']]), $ap6);
t_ok(!appr_can_act($s6b), 'MC6 L · because it does not — the contact still cannot approve');

// An escalation contact who cannot see the branch is not told either.
[$h6b, $ap6b, $s6c] = $raise($uReq, 'MC escalating far');
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, reminder_at='', escalate_user_id=? WHERE id=?")
    ->execute([$ago(1), $uEscFar, (int) $s6c['id']]);
$before = $mails('mcescfar@t.test');
$_SESSION = $origSess; current_user(true); ua(true);
appr_tick();
t_eq($mails('mcescfar@t.test'), $before,
     'MC6 · an escalation contact outside the branch scope is NOT told what it is about');

// ---------------------------------------------------------------------------
//  C7 · F1 LIFECYCLE — cancelled, completed, repeated
// ---------------------------------------------------------------------------
t_section('MC7 · F1 lifecycle');
[$h7, $ap7, $s7] = $raise($uReq, 'MC cancelled');
$pdo->prepare("UPDATE recruit_approval_steps SET reminder_at=? WHERE id=?")->execute([$ago(1), (int) $s7['id']]);
$act($uReq); hreq_cancel($h7, 'withdrawn');
$before = $mails('mcapp@t.test');
$_SESSION = $origSess; current_user(true); ua(true);
appr_tick();
t_eq($mails('mcapp@t.test'), $before, 'MC7 M · a CANCELLED approval sends no actionable reminder');

[$h7b, $ap7b, $s7b] = $raise($uReq, 'MC completed');
$act($uApp); appr_act((int) $s7b['id'], 'approve', 'done');
$pdo->prepare("UPDATE recruit_approval_steps SET reminder_at=? WHERE id=?")->execute([$ago(1), (int) $s7b['id']]);
$before = $mails('mcapp@t.test');
$_SESSION = $origSess; current_user(true); ua(true);
appr_tick();
t_eq($mails('mcapp@t.test'), $before, 'MC7 N · a COMPLETED approval sends no reminder');

[$h7c, $ap7c, $s7c] = $raise($uReq, 'MC repeated');
$pdo->prepare("UPDATE recruit_approval_steps SET reminder_at=? WHERE id=?")->execute([$ago(1), (int) $s7c['id']]);
$_SESSION = $origSess; current_user(true); ua(true);
$before = $mails('mcapp@t.test');
appr_tick();
$afterOne = $mails('mcapp@t.test');
t_eq($afterOne, $before + 1, 'MC7 O · one reminder, to one recipient');
for ($i = 0; $i < 10; $i++) appr_tick();
t_eq($mails('mcapp@t.test'), $afterOne, 'MC7 O · ten further scheduler runs send no duplicate');

// ---------------------------------------------------------------------------
//  C8 · F2 — delegation is gated exactly like policy
// ---------------------------------------------------------------------------
t_section('MC8 · F2 delegation entitlement');
$act($uMast);
t_ok(appr_config_can(), 'MC8 1 · recruitment ON + administrator → configuration allowed');
[$e1Ok, , $e1Id] = appr_delegation_save(0, ['delegator_user_id' => $uApp, 'delegate_user_id' => $uDel,
    'entity' => 'HIRING_REQUEST', 'effective_from' => $today]);
t_ok($e1Ok, 'MC8 1 · …and a delegation can be created');
if ($e1Ok) $mine['del'][] = $e1Id;
$delCount = fn() => (int) ops_val("SELECT COUNT(*) FROM approval_delegations WHERE delegate_user_id=?", [$uDel]);
$was = $delCount();

setting_set('modules_off', 'hr'); licence_disabled(true); ua(true);
t_ok(!appr_config_can(), 'MC8 2 · recruitment OFF → configuration refused, administrator or not');
[$e2Ok, $e2Msg] = appr_delegation_save(0, ['delegator_user_id' => $uApp, 'delegate_user_id' => $uDel,
    'entity' => 'HIRING_REQUEST', 'effective_from' => $today]);
t_ok(!$e2Ok, 'MC8 2/3 · CREATE is refused at the write — a direct POST lands here too: ' . $e2Msg);
t_eq($delCount(), $was, 'MC8 3 · and nothing was written');
[$e3Ok] = appr_delegation_save((int) $e1Id, ['reason' => 'edited while unlicensed']);
t_ok(!$e3Ok, 'MC8 6 · EDIT is refused');
t_eq((string) appr_delegation((int) $e1Id)['reason'], '', 'MC8 6 · and the row is unchanged');
[$e4Ok] = appr_delegation_revoke((int) $e1Id);
t_ok(!$e4Ok, 'MC8 5 · REVOKE is refused');
t_eq((int) appr_delegation((int) $e1Id)['active'], 1, 'MC8 5 · and the delegation is still active');
[$e5Ok] = appr_delegation_save((int) $e1Id, ['active' => 0]);
t_ok(!$e5Ok, 'MC8 5 · DEACTIVATE through the save path is refused as well');
t_eq((int) appr_delegation((int) $e1Id)['active'], 1, 'MC8 5 · …and it is still active');
setting_set('modules_off', $offWas); licence_disabled(true); ua(true);
t_ok(appr_config_can(), 'MC8 11 · re-enabling entitlement permits it again');

$act($uDel);
t_ok(!appr_config_can(), 'MC8 7 · a coordinator may not configure delegation');
[$e6Ok] = appr_delegation_revoke((int) $e1Id);
t_ok(!$e6Ok, 'MC8 8 · and a DELEGATE cannot revoke their own delegation');
t_eq((int) appr_delegation((int) $e1Id)['active'], 1, 'MC8 8 · nothing was written');

$act($uMast);
[$e7Ok, , $e7Id] = appr_delegation_save(0, ['delegator_user_id' => $foreign, 'delegate_user_id' => $uDel,
    'entity' => 'HIRING_REQUEST', 'effective_from' => $today]);
if ($e7Ok) $mine['del'][] = $e7Id;
t_ok(!in_array($foreign, appr_delegators_for($uDel, 'HIRING_REQUEST', 976), true),
     'MC8 9 · a delegation naming a user that does not exist here grants nothing');
t_ok(function_exists('ops_module_gate'), 'MC8 4 · the route is still module-gated as well — the write is the second boundary, not the only one');
$act($uMast); appr_delegation_revoke((int) $e1Id);

// ---------------------------------------------------------------------------
//  C9 · F3 — once escalated, the routine reminder stops
// ---------------------------------------------------------------------------
t_section('MC9 · F3 reminders after escalation');
[$h9, $ap9, $s9] = $raise($uReq, 'MC abandoned');
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, reminder_at=?, escalate_user_id=? WHERE id=?")
    ->execute([date('c', time() + 3 * 86400), $ago(1), $uEsc, (int) $s9['id']]);
$_SESSION = $origSess; current_user(true); ua(true);
$b = $mails('mcapp@t.test');
appr_tick();
t_eq($mails('mcapp@t.test'), $b + 1, 'MC9 1 · a reminder BEFORE escalation is sent');
t_eq((int) ops_val("SELECT escalated FROM recruit_approval_steps WHERE id=?", [(int) $s9['id']]), 0, 'MC9 1 · nothing is escalated yet');

$eb = $mails('mcesc@t.test'); $ab = $mails('mcapp@t.test');
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, reminder_at=? WHERE id=?")->execute([$ago(1), $ago(1), (int) $s9['id']]);
appr_tick();
t_eq((int) ops_val("SELECT escalated FROM recruit_approval_steps WHERE id=?", [(int) $s9['id']]), 1, 'MC9 2 · the SLA passes and the step is escalated');
t_eq($mails('mcesc@t.test'), $eb + 1, 'MC9 3 · the escalation notification goes out');

$ab2 = $mails('mcapp@t.test'); $eb2 = $mails('mcesc@t.test');
for ($i = 0; $i < 100; $i++) {
    $pdo->prepare("UPDATE recruit_approval_steps SET reminder_at=? WHERE id=?")->execute([$ago(1), (int) $s9['id']]);
    appr_tick();
}
t_eq($mails('mcapp@t.test'), $ab2, 'MC9 4/5/6/7 · ONE HUNDRED further runs send the approver NOTHING — the reminder cycle has stopped');
t_eq($mails('mcesc@t.test'), $eb2, 'MC9 8 · and the escalation is not sent again either');

// The approval has not gone quiet — it is louder, and still governed.
$s9b = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $s9['id']]);
t_eq((string) $s9b['status'], 'PENDING', 'MC9 9 · the step is still pending — nothing was decided');
t_eq(appr_sla_state($s9b), 'ESCALATED', 'MC9 9 · and reads ESCALATED on every screen — visibility did not stop, repetition did');
t_ok(appr_sla_summary()['escalated'] >= 1, 'MC9 9 · the dashboard still counts it');
t_eq(hreq_get($h9)['status'], 'UNDER_REVIEW', 'MC9 9 · the request was neither approved nor rejected');
$act($uEsc);
t_ok(!appr_can_act(appr_step_context($s9b, $ap9)), 'MC9 10 · and the escalation recipient still gains no authority');
$act($uApp);
t_ok(appr_can_act(appr_step_context($s9b, $ap9)), 'MC9 9 · while the real approver keeps theirs');

// Cancellation and completion stop everything, escalated or not.
$act($uReq); hreq_cancel($h9, 'gave up');
$before = $mails('mcapp@t.test') + $mails('mcesc@t.test');
$_SESSION = $origSess; current_user(true); ua(true);
for ($i = 0; $i < 5; $i++) appr_tick();
t_eq($mails('mcapp@t.test') + $mails('mcesc@t.test'), $before, 'MC9 11 · cancelling stops reminders and escalation outright');

// A revoked delegation cannot resurrect a stopped reminder.
[$h9b, $ap9b, $s9c] = $raise($uReq, 'MC resurrect');
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, reminder_at=?, escalate_user_id=? WHERE id=?")
    ->execute([$ago(2), $ago(2), $uEsc, (int) $s9c['id']]);
$_SESSION = $origSess; current_user(true); ua(true);
appr_tick();
$act($uMast);
[$rOk, , $rId] = appr_delegation_save(0, ['delegator_user_id' => $uApp, 'delegate_user_id' => $uDel,
    'entity' => 'HIRING_REQUEST', 'effective_from' => $today]);
if ($rOk) $mine['del'][] = $rId;
appr_delegation_revoke((int) $rId);
$before = $mails('mcdel@t.test');
$_SESSION = $origSess; current_user(true); ua(true);
for ($i = 0; $i < 5; $i++) appr_tick();
t_eq($mails('mcdel@t.test'), $before, 'MC9 13 · an expired/revoked delegation does not resurrect a stopped reminder');

// ---------------------------------------------------------------------------
//  Clean up
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
if (function_exists('setting_set')) { setting_set('modules_off', $offWas); licence_disabled(true); ua(true); }
foreach ($mine['h'] as $h) {
    $rq = ops_one("SELECT id FROM recruit_approval_requests WHERE entity='HIRING_REQUEST' AND entity_id=?", [(int) $h]);
    if ($rq) { $pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id=?")->execute([(int) $rq['id']]);
               $pdo->prepare("DELETE FROM recruit_approval_requests WHERE id=?")->execute([(int) $rq['id']]); }
    $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([(int) $h]);
}
foreach ($mine['rule'] as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([(int) $r]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([(int) $r]);
}
foreach ($mine['del'] as $d) $pdo->prepare("DELETE FROM approval_delegations WHERE id=?")->execute([(int) $d]);
foreach ($mine['u'] as $u) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int) $u]);
foreach ($mine['o'] as $o) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([(int) $o]);
$pdo->exec("DELETE FROM email_log WHERE kind='recruit_approval'");
t_ok(true, 'M3 correction fixtures removed');
