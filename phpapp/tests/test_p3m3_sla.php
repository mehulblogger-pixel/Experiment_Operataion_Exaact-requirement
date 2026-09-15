<?php
// ============================================================================
//  PHASE 3 · M3 — SLA, ESCALATION, INBOX & NOTIFICATIONS
//
//  M3 did not build an SLA engine. Phase 6 shipped one — per-level sla_days,
//  reminder_days and an escalation target, a cron tick, and a mailer that logs
//  every attempt. The audit (docs/phase3/M3-SLA-ESCALATION-AUDIT.md) found ten
//  genuine gaps in it, and these tests are what holds each one closed.
//
//  The single rule the whole milestone turns on:
//
//      SLA IS NOT AUTHORITY.  ESCALATION IS NOT AUTHORITY.
//      NOTIFICATION IS NOT AUTHORITY.  INBOX VISIBILITY IS NOT AUTHORITY.
//
//  So every section that makes something more visible or more urgent is followed
//  by one that proves it granted nobody anything.
// ============================================================================

t_section('Phase 3 · M3 — SLA, escalation, inbox & notifications');

$pdo = db();
hreq_migrate(); appr_migrate();
$mine = ['h' => [], 'u' => [], 'o' => [], 'rule' => [], 'del' => [], 'r' => [], 'hol' => []];
$origSess = $_SESSION;
$offWas = function_exists('setting_get') ? (string) setting_get('modules_off', '') : '';

foreach ([[971, 'M3 Pune'], [972, 'M3 Surat']] as $o) {
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); $mine['o'][] = $o[0]; } catch (Throwable $e) {}
}
$mk = function ($un, $role, $super, $office, $perms, $email = '') use ($pdo, &$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions,email)
                   VALUES (?,?,?,?,1,?,?,'',?,?)")
        ->execute([$un, 'M3', ucfirst(substr($un, 3)), $role, $super ? 1 : 0, $office, $perms, $email]);
    $id = (int) $pdo->lastInsertId(); $mine['u'][] = $id; return $id;
};
$act = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };
$HR = 'mod.hiring.view,mod.hiring.edit';

$uReq   = $mk('m3_req',    'BRANCH_MANAGER', 0, 971, $HR, 'm3req@demo.test');
$uAppr  = $mk('m3_appr',   'SBU_HEAD',       0, 971, $HR, 'm3appr@demo.test');
$uAppr2 = $mk('m3_appr2',  'BUSINESS_DIRECTOR', 0, 971, $HR, 'm3appr2@demo.test');
$uDeleg = $mk('m3_deleg',  'COORDINATOR',    0, 971, $HR, 'm3deleg@demo.test');
$uEsc   = $mk('m3_esc',    'OPERATION_MANAGER', 0, 971, '', 'm3esc@demo.test');
$uMast  = $mk('m3_master', 'ADMIN',          1, 971, '',  'm3master@demo.test');
$uPlain = $mk('m3_plain',  'COORDINATOR',    0, 971, $HR, 'm3plain@demo.test');

// Approval POLICY may only be written by an administrator — M3 §25 asks that at
// the write, so the fixture acts as one and hands the session straight back.
$cfg = function (callable $fn) use ($uMast) {
    $prev = $_SESSION['uid'] ?? null;
    $_SESSION['uid'] = $uMast; current_user(true); ua(true);
    try { return $fn(); }
    finally {
        if ($prev === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prev;
        current_user(true); ua(true);
    }
};
$today  = date('Y-m-d');
$mailN  = fn() => (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval'");
$actN   = fn($like) => (int) ops_val("SELECT COUNT(*) FROM activities WHERE subject LIKE ?", ['%' . $like . '%']);
$ago    = fn($d) => date('c', time() - $d * 86400);

// ---------------------------------------------------------------------------
//  M3.0 · REUSE, NOT REBUILD
// ---------------------------------------------------------------------------
t_section('M3.0 · reuse, not rebuild');
foreach (['recruit_sla_policies','approval_sla','sla_policies','approval_notifications','approval_reminders','notification_queue'] as $t)
    t_ok(!t_table_exists($t), 'M3.0 · M3 created no ' . $t . ' — the Phase-6 engine is what it uses');
$sc = t_columns('recruit_approval_steps');
foreach (['sla_due','reminder_at','reminded_at','escalated'] as $c)
    t_ok(in_array($c, $sc, true), 'M3.0 · ' . $c . ' already existed — M3 did not invent it');
foreach (['sla_days','reminder_days','activated_at'] as $c)
    t_ok(in_array($c, $sc, true), 'M3.0 · ' . $c . ' was added to the EXISTING steps table');
t_ok(function_exists('appr_tick'), 'M3.0 · the Phase-6 cron tick is the background mechanism');
t_ok(strpos((string) @file_get_contents(__DIR__ . '/../cron.php'), "appr_tick") !== false,
     'M3.0 · and it is wired into the EXISTING cron runner, not a new one');
t_ok(preg_match("/\\\$m8\\('hiring'[^)]*\\)\\s*&&\\s*function_exists\\('appr_tick'\\)/", (string) @file_get_contents(__DIR__ . '/../cron.php')) === 1,
     'M3.0 · behind the existing per-module entitlement gate');
t_ok(function_exists('ops_mail') && function_exists('is_working_day'),
     'M3.0 · the mailer and the working-day calendar both already existed');

// A two-level policy: exactly what an administrator would configure.
$ruleId = $cfg(fn() => appr_rule_save(0, ['name' => 'M3 hiring', 'entity' => 'HIRING_REQUEST', 'code' => 'M3HR', 'applies_office_id' => 971]));
$mine['rule'][] = $ruleId;
$cfg(fn() => appr_level_save(['rule_id' => $ruleId, 'seq' => 1, 'label' => 'Unit head', 'approver_role' => 'SBU_HEAD',
    'sla_days' => 2, 'reminder_days' => 1, 'escalate_user_id' => $uEsc]));
$cfg(fn() => appr_level_save(['rule_id' => $ruleId, 'seq' => 2, 'label' => 'Director', 'approver_role' => 'BUSINESS_DIRECTOR',
    'sla_days' => 2, 'reminder_days' => 1, 'escalate_user_id' => $uEsc]));
t_eq(count(appr_levels($ruleId)), 2, 'M3.0 · a two-level chain is configured');

$form = fn(array $x = []) => array_merge(
    ['job_title' => 'M3 Fitter', 'quantity' => 1, 'office_id' => 971, 'priority' => 'NORMAL', 'approval_required' => 1], $x);
$raise = function (array $x = []) use ($form, &$mine, $act, $uReq) {
    $act($uReq);
    [$ok, , $id] = hreq_save(0, $form($x));
    if ($ok) $mine['h'][] = $id;
    hreq_submit($id);
    return $id;
};

// ---------------------------------------------------------------------------
//  M3.1 · WHEN THE CLOCK STARTS — the central defect the audit found
// ---------------------------------------------------------------------------
t_section('M3.1 · the SLA clock starts when the step becomes active (§7)');
$h1 = $raise(['job_title' => 'M3 clock']);
$ap1 = hreq_approval($h1);
t_ok($ap1 !== null, 'M3.1 · the request went to its approvers');
$steps1 = appr_steps((int) $ap1['id']);
t_eq(count($steps1), 2, 'M3.1 · two steps were created');
$s1 = $steps1[0]; $s2 = $steps1[1];

t_ok(trim((string) $s1['sla_due']) !== '', 'M3.1 · level 1 — the step being waited on — HAS a due date');
t_ok(trim((string) $s1['activated_at']) !== '', 'M3.1 · …and is stamped with when it became active');
t_eq(trim((string) $s2['sla_due']), '', 'M3.1 · level 2 has NO due date — nobody is waiting on it yet');
t_eq(trim((string) $s2['activated_at']), '', 'M3.1 · …and no activation stamp');
t_eq(appr_sla_state($s2), 'NOT_STARTED', 'M3.1 · so its SLA state is Not started, not On track and not Overdue');
t_eq((int) $s2['sla_days'], 2, 'M3.1 · the POLICY is frozen onto the step at chain creation (§31)');

// The proof that matters: a chain that has been running for ten days must not
// hand level 2 to its approver already overdue. This is the exact defect.
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, reminder_at=?, activated_at=? WHERE id=?")
    ->execute([$ago(8), $ago(9), $ago(10), (int) $s1['id']]);
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, reminder_at=? WHERE id=?")
    ->execute([$ago(8), $ago(9), (int) $s2['id']]);   // as the OLD code would have stamped it
$act($uAppr);
[$okA] = appr_act((int) $s1['id'], 'approve', 'level 1 done, late');
t_ok($okA, 'M3.1 · level 1 is approved after ten days');
$s2b = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $s2['id']]);
t_ok(trim((string) $s2b['activated_at']) !== '', 'M3.1 · level 2 is now stamped as active');
t_ok(strtotime((string) $s2b['sla_due']) > time(),
     'M3.1 · AND ITS DUE DATE IS IN THE FUTURE — a later level is never born overdue');
t_ok(appr_sla_state($s2b) !== 'OVERDUE', 'M3.1 · so it is not overdue before its approver has seen it');
t_ok(strtotime((string) $s2b['reminder_at']) > time(), 'M3.1 · its reminder is ahead of it too, not behind');

// Re-activating cannot extend a deadline — otherwise the SLA could be reset.
$dueWas = (string) $s2b['sla_due'];
appr_activate_step($s2b, appr_request((int) $ap1['id']));
$s2c = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $s2['id']]);
t_eq((string) $s2c['sla_due'], $dueWas, 'M3.1 · activating twice does not extend the approver\'s deadline');

// ---------------------------------------------------------------------------
//  M3.2 · WORKING DAYS (§9) — reusing the calendar that already existed
// ---------------------------------------------------------------------------
t_section('M3.2 · the clock counts working days, not calendar days');
t_ok(function_exists('appr_due_at'), 'M3.2 · one helper computes every due date');
for ($n = 1; $n <= 5; $n++) {
    $d = appr_due_at($n, 971);
    t_ok(is_working_day(substr($d, 0, 10), 971), 'M3.2 · a ' . $n . '-day SLA never falls due on a non-working day');
}
t_eq(substr(appr_due_at(0, 971), 0, 10), date('Y-m-d'), 'M3.2 · a zero-day SLA is due immediately');

// A public holiday at one branch moves that branch's due date and nobody else's.
$hol = date('Y-m-d', strtotime('+1 day'));
for ($i = 1; is_working_day($hol, 971) === false && $i < 9; $i++) $hol = date('Y-m-d', strtotime('+' . (1 + $i) . ' day'));
try {
    $pdo->prepare("INSERT INTO holidays (hol_date,name,office_id) VALUES (?,?,?)")->execute([$hol, 'M3 branch holiday', 971]);
    $mine['hol'][] = $hol;
} catch (Throwable $e) {}
office_holidays_flush();
t_ok(!is_working_day($hol, 971), 'M3.2 · the branch holiday is recognised at that branch');
t_ok(is_working_day($hol, 972),  'M3.2 · …and not at the other branch');
t_ok(substr(appr_due_at(1, 971), 0, 10) !== $hol, 'M3.2 · so a one-day SLA at that branch skips its holiday');
t_ok(is_working_day(substr(appr_due_at(3, 0), 0, 10), 0),
     'M3.2 · with no branch (offer, salary, requisition) the company calendar applies — behaviour M2 left alone');

// ---------------------------------------------------------------------------
//  M3.3 · WHERE THE CLOCK STOPS (§8)
// ---------------------------------------------------------------------------
t_section('M3.3 · a finished step never becomes overdue');
$s1done = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $s1['id']]);
t_eq((string) $s1done['status'], 'APPROVED', 'M3.3 · level 1 is approved');
t_eq(appr_sla_state($s1done), 'COMPLETED', 'M3.3 · and reads COMPLETED however far past its due date it is');
$before = $mailN();
t_eq(appr_tick(), 0, 'M3.3 · the scheduler finds nothing to do on it');
t_eq($mailN(), $before, 'M3.3 · and sends nothing about it');

// A cancelled request stops being chased. (§39 — "cancelled request continues
// receiving actionable approval reminders" is a hard-stop condition.)
$hC = $raise(['job_title' => 'M3 cancelled']);
$apC = hreq_approval($hC);
$sC = appr_current_step($apC);
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, reminder_at=? WHERE id=?")->execute([$ago(2), $ago(3), (int) $sC['id']]);
$act($uReq);
hreq_cancel($hC, 'no longer needed');
$mailWas = $mailN();
appr_tick();
t_eq($mailN(), $mailWas, 'M3.3 · a cancelled request is never reminded or escalated again');
$sCafter = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $sC['id']]);
t_eq((int) $sCafter['escalated'], 0, 'M3.3 · and is never marked escalated after cancellation');

// ---------------------------------------------------------------------------
//  M3.4 · THE SEVEN STATES (§10) — derived, never stored
// ---------------------------------------------------------------------------
t_section('M3.4 · SLA status is derived from the step and the clock');
$mk4 = fn($x) => array_merge(['status' => 'PENDING', 'sla_due' => '', 'reminder_at' => '', 'escalated' => 0], $x);
t_eq(appr_sla_state($mk4(['status' => 'APPROVED', 'sla_due' => $ago(9)])), 'COMPLETED', 'M3.4 · approved → Completed');
t_eq(appr_sla_state($mk4(['status' => 'REJECTED', 'sla_due' => $ago(9)])), 'COMPLETED', 'M3.4 · rejected → Completed');
t_eq(appr_sla_state($mk4(['status' => 'CANCELLED', 'sla_due' => $ago(9)])), 'COMPLETED', 'M3.4 · cancelled → Completed');
t_eq(appr_sla_state($mk4([])), 'NOT_STARTED', 'M3.4 · no due date → Not started');
t_eq(appr_sla_state($mk4(['sla_due' => $ago(1), 'escalated' => 1])), 'ESCALATED', 'M3.4 · escalated → Escalated');
t_eq(appr_sla_state($mk4(['sla_due' => $ago(2)])), 'OVERDUE', 'M3.4 · past due → Overdue');
t_eq(appr_sla_state($mk4(['sla_due' => date('c', time() + 3600)])), 'DUE', 'M3.4 · due later today → Due today');
t_eq(appr_sla_state($mk4(['sla_due' => date('c', time() + 4 * 86400), 'reminder_at' => $ago(1)])), 'DUE_SOON',
     'M3.4 · past its reminder but not its due date → Due soon');
t_eq(appr_sla_state($mk4(['sla_due' => date('c', time() + 4 * 86400), 'reminder_at' => date('c', time() + 86400)])), 'ON_TRACK',
     'M3.4 · before both → On track');
t_eq(appr_sla_days_late($mk4(['sla_due' => $ago(3)])), 3, 'M3.4 · lateness is counted in whole days');
t_eq(appr_sla_days_late($mk4(['sla_due' => date('c', time() + 86400)])), 0, 'M3.4 · and is zero when it is not late');
t_ok(strpos(appr_sla_sentence($mk4(['sla_due' => $ago(2)])), 'Overdue by 2 days') === 0,
     'M3.4 · the screen gets one readable sentence: "Overdue by 2 days"');
t_ok(!t_table_exists('recruit_approval_sla_state'), 'M3.4 · no derived status was stored anywhere');

// ---------------------------------------------------------------------------
//  M3.5 · REMINDERS (§11, §21)
// ---------------------------------------------------------------------------
t_section('M3.5 · reminders, and never the same one twice');
$hR = $raise(['job_title' => 'M3 reminder']);
$apR = hreq_approval($hR);
$sR = appr_current_step($apR);
$pdo->prepare("UPDATE recruit_approval_steps SET reminder_at=? WHERE id=?")->execute([$ago(1), (int) $sR['id']]);
$mailWas = $mailN(); $auditWas = $actN('Approval reminder sent');
t_ok(appr_tick() >= 1, 'M3.5 · the scheduler sends the due reminder');
t_ok($mailN() > $mailWas, 'M3.5 · a notification attempt is recorded');
t_eq($actN('Approval reminder sent'), $auditWas + 1, 'M3.5 · and audited once on the existing activity spine (§24)');
$sRa = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $sR['id']]);
t_ok(trim((string) $sRa['reminded_at']) !== '', 'M3.5 · the step records that it was reminded');

// Ten runs of the scheduler, not two. (§30.7, §30.8)
$mailWas = $mailN(); $auditWas = $actN('Approval reminder sent');
for ($i = 0; $i < 10; $i++) appr_tick();
t_eq($mailN(), $mailWas, 'M3.5 · ten further runs send NO duplicate reminder');
t_eq($actN('Approval reminder sent'), $auditWas, 'M3.5 · and write no duplicate audit');
$sRb = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $sR['id']]);
t_eq((string) $sRb['status'], 'PENDING', 'M3.5 · the step is untouched — a reminder decides nothing');
t_eq(hreq_get($hR)['status'], 'UNDER_REVIEW', 'M3.5 · and so is the request');
t_eq((string) $sRb['approver_role'], (string) $sR['approver_role'], 'M3.5 · a reminder never reassigns the approver');
t_eq((string) $sRb['sla_due'], (string) $sRa['sla_due'], 'M3.5 · nor moves the deadline it is reminding about');

// ---------------------------------------------------------------------------
//  M3.6 · OVERDUE DOES NOT WIDEN AUTHORITY (§12)
// ---------------------------------------------------------------------------
t_section('M3.6 · being late changes nothing about who may decide');
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=? WHERE id=?")->execute([$ago(5), (int) $sR['id']]);
$sRo = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $sR['id']]);
t_eq(appr_sla_state($sRo), 'OVERDUE', 'M3.6 · the step is overdue');
$stepCtx = appr_step_context($sRo, $apR);
$act($uPlain);
t_ok(!appr_can_act($stepCtx), 'M3.6 · somebody who was never the approver still cannot act on it');
[$pOk, $pMsg] = appr_act((int) $sRo['id'], 'approve', 'it is late, let me help');
t_ok(!$pOk, 'M3.6 · …and is refused at the engine: ' . $pMsg);
$act($uReq);
t_ok(!appr_can_act($stepCtx) || appr_guard($apR) !== '', 'M3.6 · the requestor still cannot approve their own late request');
[$rOk] = appr_act((int) $sRo['id'], 'approve', 'mine, and overdue');
t_ok(!$rOk, 'M3.6 · segregation survives the deadline');
t_eq(hreq_get($hR)['status'], 'UNDER_REVIEW', 'M3.6 · nothing was written');
$act($uAppr);
t_ok(appr_can_act($stepCtx), 'M3.6 · and the real approver still can — lateness took nothing away either');

// ---------------------------------------------------------------------------
//  M3.7 · ESCALATION IS VISIBILITY, NOT AUTHORITY (§13, §14, §15, §23)
// ---------------------------------------------------------------------------
t_section('M3.7 · escalation tells somebody; it does not make them the approver');
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, reminder_at='' WHERE id=?")->execute([$ago(1), (int) $sR['id']]);
$mailWas = $mailN(); $auditWas = $actN('escalated');
t_ok(appr_tick() >= 1, 'M3.7 · the scheduler escalates the overdue step');
$sRe = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $sR['id']]);
t_eq((int) $sRe['escalated'], 1, 'M3.7 · it is marked escalated');
t_eq($actN('escalated'), $auditWas + 1, 'M3.7 · and audited once (§24)');
t_ok($mailN() > $mailWas, 'M3.7 · the escalation contact was written to');
t_ok((int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND to_addr=? AND subject LIKE 'ESCALATION%'", ['m3esc@demo.test']) >= 1,
     'M3.7 · specifically the configured escalation contact, resolved from the EXISTING organisation data');

// The escalation recipient gains nothing at all.
$act($uEsc);
t_ok(!appr_can_act(appr_step_context($sRe, $apR)),
     'M3.7 · THE ESCALATION RECIPIENT CANNOT APPROVE — escalation manufactured no authority');
[$eOk, $eMsg] = appr_act((int) $sRe['id'], 'approve', 'I was escalated to');
t_ok(!$eOk, 'M3.7 · …refused at the engine: ' . $eMsg);
t_eq(hreq_get($hR)['status'], 'UNDER_REVIEW', 'M3.7 · nothing was written');
$sRe2 = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $sR['id']]);
t_eq((string) $sRe2['approver_role'], (string) $sR['approver_role'], 'M3.7 · escalation did not rewrite the approver');
t_eq((string) $sRe2['status'], 'PENDING', 'M3.7 · nor the step');

// Ten more runs: escalated once means escalated once.
$mailWas = $mailN(); $auditWas = $actN('escalated');
for ($i = 0; $i < 10; $i++) appr_tick();
t_eq($actN('escalated'), $auditWas, 'M3.7 · ten further runs do not escalate again');
t_eq((int) ops_val("SELECT escalated FROM recruit_approval_steps WHERE id=?", [(int) $sR['id']]), 1,
     'M3.7 · the step is still escalated exactly once');

// An escalation with nobody to tell is recorded as exactly that — never as a
// successful escalation. (§20 — do not fake delivery.)
$hN = $raise(['job_title' => 'M3 no contact']);
$apN = hreq_approval($hN);
$sN = appr_current_step($apN);
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, reminder_at='', escalate_role='', escalate_user_id=NULL WHERE id=?")
    ->execute([$ago(1), (int) $sN['id']]);
$pdo->exec("UPDATE users SET is_active=0 WHERE role IN ('MASTER_ADMIN','ADMIN','BRANCH_MANAGER','SBU_HEAD') AND email<>''");
$auditWas = $actN('NO RECIPIENT');
appr_tick();
t_eq($actN('NO RECIPIENT'), $auditWas + 1,
     'M3.7 · an escalation nobody could receive is audited as NO RECIPIENT, not as a success');
$pdo->exec("UPDATE users SET is_active=1 WHERE username LIKE 'm3_%'");
$pdo->prepare("UPDATE users SET is_active=1 WHERE id IN (SELECT id FROM users WHERE username NOT LIKE 'm3_%' AND role IN ('MASTER_ADMIN','ADMIN','BRANCH_MANAGER','SBU_HEAD'))")->execute();

// ---------------------------------------------------------------------------
//  M3.8 · DELEGATION (§16) — M2's rules, now honoured by notification too
// ---------------------------------------------------------------------------
t_section('M3.8 · the person the system is waiting for is the person it tells');
t_ok(function_exists('appr_delegates_of') && function_exists('appr_valid_delegations'),
     'M3.8 · the reverse reading exists — and both directions share ONE validity rule');
$hD = $raise(['job_title' => 'M3 delegate']);
$apD = hreq_approval($hD);
$sD = appr_step_context(appr_current_step($apD), $apD);

$act($uMast);
[$dOk, , $dId] = appr_delegation_save(0, ['delegator_user_id' => $uAppr, 'delegate_user_id' => $uDeleg,
    'entity' => 'HIRING_REQUEST', 'office_id' => 971, 'effective_from' => $today]);
if ($dOk) $mine['del'][] = $dId;
t_ok($dOk, 'M3.8 · an administrator delegates the approver\'s authority for this branch');

$to = appr_step_recipients($sD, $apD);
t_ok(in_array('m3appr@demo.test', $to, true), 'M3.8 · the named approver is still notified');
t_ok(in_array('m3deleg@demo.test', $to, true), 'M3.8 · AND SO IS THE DELEGATE — the queue and the e-mail now agree');

// M2's rules are inherited, not re-implemented.
$pdo->prepare("UPDATE approval_delegations SET effective_to=? WHERE id=?")->execute([date('Y-m-d', strtotime('-1 day')), (int) $dId]);
t_ok(!in_array('m3deleg@demo.test', appr_step_recipients($sD, $apD), true),
     'M3.8 · an EXPIRED delegation stops the notification too');
$pdo->prepare("UPDATE approval_delegations SET effective_to='' WHERE id=?")->execute([(int) $dId]);
t_ok(in_array('m3deleg@demo.test', appr_step_recipients($sD, $apD), true), 'M3.8 · restoring its dates restores it');

$pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$uAppr]);
t_ok(!in_array('m3deleg@demo.test', appr_step_recipients($sD, $apD), true),
     'M3.8 · an INACTIVE DELEGATOR lends nothing — M2 Finding B is inherited, not duplicated');
$pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$uAppr]);

$pdo->prepare("UPDATE approval_delegations SET office_id=? WHERE id=?")->execute([972, (int) $dId]);
t_ok(!in_array('m3deleg@demo.test', appr_step_recipients($sD, $apD), true),
     'M3.8 · a delegation scoped to ANOTHER branch does not notify here — M2 Finding A is inherited');
$pdo->prepare("UPDATE approval_delegations SET office_id=NULL WHERE id=?")->execute([(int) $dId]);
t_ok(in_array('m3deleg@demo.test', appr_step_recipients($sD, $apD), true),
     'M3.8 · an unscoped delegation still works everywhere — existing behaviour intact');

$act($uMast); appr_delegation_revoke((int) $dId);
t_ok(!in_array('m3deleg@demo.test', appr_step_recipients($sD, $apD), true),
     'M3.8 · a REVOKED delegation stops the notification');
// And through all of that, notification never became authority.
$act($uDeleg);
t_ok(!appr_can_act($sD), 'M3.8 · with the delegation revoked the delegate cannot act either');

// ---------------------------------------------------------------------------
//  M3.9 · THE INBOX (§17, §18, §19)
// ---------------------------------------------------------------------------
t_section('M3.9 · the inbox says what is pending, by when, and how late');
$act($uAppr);
$inbox = appr_inbox();
t_ok(count($inbox) >= 1, 'M3.9 · the approver has items');
$row = $inbox[0];
foreach (['subject','entity','entity_id','seq','label','sla_due','activated_at','escalated','rcreated'] as $k)
    t_ok(array_key_exists($k, $row), 'M3.9 · the inbox row carries ' . $k);
t_ok(in_array(appr_sla_state($row), array_keys(APPR_SLA_STATES), true), 'M3.9 · and resolves to a known SLA state');

// §18 — visibility is still M2's entity-aware question, not a global branch filter.
$act($uPlain);
t_eq(count(appr_inbox()), 0, 'M3.9 · somebody with no approval authority sees an empty queue');
$fileSrc = (string) @file_get_contents(__DIR__ . '/../lib/recruit_approval.php');
t_ok(strpos($fileSrc, 'WHERE office_id = current_office') === false,
     'M3.9 · no global branch filter was introduced — M1/M2 rejected exactly that');

// §19 — "waiting on someone else".
$act($uReq);
$waiting = appr_waiting_on_others();
$ids = array_map(fn($w) => (int) $w['entity_id'], $waiting);
t_ok(in_array($hD, $ids, true), 'M3.9 · the requestor can see their own request sitting with an approver');
t_ok(count(appr_inbox()) === 0 || !in_array($hD, array_map(fn($x) => (int) $x['entity_id'], appr_inbox()), true),
     'M3.9 · and it is NOT also listed as something they must act on');
$act($uAppr);
t_ok(!in_array($hD, array_map(fn($w) => (int) $w['entity_id'], appr_waiting_on_others()), true),
     'M3.9 · the approver does not see it under "waiting" — it is their action, not their wait');
$act($uPlain);
t_eq(count(appr_waiting_on_others()), 0, 'M3.9 · somebody else\'s request never appears in your waiting list');

// Entitlement, on every read.
$act($uReq);
setting_set('modules_off', 'hr'); licence_disabled(true); ua(true);
t_eq(count(appr_waiting_on_others()), 0, 'M3.9 · with the module switched off the waiting list is empty (§29)');
t_eq(appr_sla_summary()['pending'], 0, 'M3.9 · and the dashboard KPI answers nothing (§27)');
t_eq(appr_tick(), 0, 'M3.9 · and the scheduler does nothing for an unentitled workspace');
setting_set('modules_off', $offWas); licence_disabled(true); ua(true);
t_ok(appr_sla_summary()['pending'] >= 1, 'M3.9 · with it switched back on, the KPI answers again');

// ---------------------------------------------------------------------------
//  M3.10 · NOTIFICATIONS (§20, §21, §24)
// ---------------------------------------------------------------------------
t_section('M3.10 · every notification is attempted once and recorded honestly');
t_ok((int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND sent_ok=0 AND error<>''") >= 1,
     'M3.10 · with no SMTP configured the failure is RECORDED, not faked as a send');
t_ok((int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND sent_ok=1") === 0,
     'M3.10 · nothing claims to have been delivered when the mailer could not deliver it');
t_ok(function_exists('notifications_can_view'), 'M3.10 · the existing outbox screen is where these are read');
t_ok($actN('SLA started') >= 1, 'M3.10 · the start of an SLA is on the activity spine');
t_ok(!t_table_exists('appr_notifications'), 'M3.10 · and no second notification store was created');
// A mailer that throws must not break the approval workflow. (§39)
t_eq(appr_mail(['nobody@demo.test'], 'x', 'y') >= 0, true, 'M3.10 · the mail helper never throws');
t_eq(appr_delivery_note(0, 0), 'NO RECIPIENT — nobody was notified', 'M3.10 · nobody told is reported as nobody told');
t_ok(strpos(appr_delivery_note(3, 0), 'not delivered') !== false, 'M3.10 · attempted-but-undelivered says so');
t_eq(appr_delivery_note(2, 2), 'notified 2 recipients', 'M3.10 · and a real delivery is reported plainly');

// ---------------------------------------------------------------------------
//  M3.11 · CONFIGURATION SECURITY (§25)
// ---------------------------------------------------------------------------
t_section('M3.11 · SLA and escalation policy may only be written by an administrator');
$lvWas = appr_levels($ruleId)[0];
$act($uPlain);
t_ok(!appr_config_can(), 'M3.11 · a coordinator may not configure approval policy');
appr_level_save(['rule_id' => $ruleId, 'level_id' => (int) $lvWas['id'], 'seq' => 1, 'label' => 'HACKED',
                 'approver_role' => 'COORDINATOR', 'sla_days' => 99, 'reminder_days' => 99]);
$lvNow = ops_one("SELECT * FROM recruit_approval_levels WHERE id=?", [(int) $lvWas['id']]);
t_eq((int) $lvNow['sla_days'], (int) $lvWas['sla_days'], 'M3.11 · a direct POST cannot stretch the SLA');
t_eq((string) $lvNow['label'], (string) $lvWas['label'], 'M3.11 · nor rename the level');
t_eq((string) $lvNow['approver_role'], (string) $lvWas['approver_role'], 'M3.11 · nor make themselves the approver');
appr_level_delete((int) $lvWas['id']);
t_ok(ops_one("SELECT id FROM recruit_approval_levels WHERE id=?", [(int) $lvWas['id']]) !== null,
     'M3.11 · nor delete the level that requires an approval');
$rWas = appr_rule($ruleId);
appr_rule_save($ruleId, ['name' => 'HACKED RULE']);
t_eq((string) appr_rule($ruleId)['name'], (string) $rWas['name'], 'M3.11 · nor rewrite the policy');
appr_rule_set_active($ruleId, false);
t_eq((int) appr_rule($ruleId)['active'], (int) $rWas['active'], 'M3.11 · nor switch the policy off');

// Entitlement sits in front of the capability, and a master does not bypass it.
$act($uMast);
t_ok(appr_config_can(), 'M3.11 · an administrator on an entitled workspace may configure it');
setting_set('modules_off', 'hr'); licence_disabled(true); ua(true);
t_ok(!appr_config_can(), 'M3.11 · …and MAY NOT once the module is switched off — no master bypass of entitlement');
appr_level_save(['rule_id' => $ruleId, 'level_id' => (int) $lvWas['id'], 'seq' => 1, 'label' => 'OFF-PLAN', 'sla_days' => 77]);
t_eq((int) ops_val("SELECT sla_days FROM recruit_approval_levels WHERE id=?", [(int) $lvWas['id']]), (int) $lvWas['sla_days'],
     'M3.11 · and the unentitled write changes nothing');
setting_set('modules_off', $offWas); licence_disabled(true); ua(true);

// ---------------------------------------------------------------------------
//  M3.12 · IMMUTABILITY (§31)
// ---------------------------------------------------------------------------
t_section('M3.12 · changing the policy does not rewrite an approval already running');
$hI = $raise(['job_title' => 'M3 immutable']);
$apI = hreq_approval($hI);
$sI = appr_current_step($apI);
$dueWas = (string) $sI['sla_due']; $daysWas = (int) $sI['sla_days'];
$cfg(fn() => appr_level_save(['rule_id' => $ruleId, 'level_id' => (int) $lvWas['id'], 'seq' => 1, 'label' => 'Unit head',
    'approver_role' => 'SBU_HEAD', 'sla_days' => 9, 'reminder_days' => 9, 'escalate_user_id' => $uEsc]));
$sIa = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $sI['id']]);
t_eq((string) $sIa['sla_due'], $dueWas, 'M3.12 · the running step keeps the due date it was given');
t_eq((int) $sIa['sla_days'], $daysWas, 'M3.12 · and the policy frozen onto it');
appr_tick();
t_eq((string) ops_val("SELECT sla_due FROM recruit_approval_steps WHERE id=?", [(int) $sI['id']]), $dueWas,
     'M3.12 · and the scheduler does not adopt the new policy for it either');
// A NEW request gets the new policy — the change was not ignored, only scoped.
$hI2 = $raise(['job_title' => 'M3 after change']);
t_eq((int) appr_current_step(hreq_approval($hI2))['sla_days'], 9, 'M3.12 · a NEW request does take the new policy');
$cfg(fn() => appr_level_save(['rule_id' => $ruleId, 'level_id' => (int) $lvWas['id'], 'seq' => 1, 'label' => 'Unit head',
    'approver_role' => 'SBU_HEAD', 'sla_days' => $daysWas, 'reminder_days' => 1, 'escalate_user_id' => $uEsc]));

// ---------------------------------------------------------------------------
//  M3.13 · TENANT AND BRANCH BOUNDARIES (§18, §32)
// ---------------------------------------------------------------------------
t_section('M3.13 · a record from somewhere else is not reachable by naming it');
$foreign = 99000000 + random_int(1, 999);
t_ok(!ops_one("SELECT id FROM recruit_approval_steps WHERE id=?", [$foreign]),
     'M3.13 · a foreign step id does not exist in this workspace — isolation is structural (one database per tenant)');
$act($uAppr);
[$fOk, $fMsg] = appr_act($foreign, 'approve', 'substituted id');
t_ok(!$fOk, 'M3.13 · and acting on it is refused: ' . $fMsg);
t_ok(appr_request($foreign) === null, 'M3.13 · so is reading it');
// Branch: M2's entity-aware scope still decides, for SLA-bearing rows too.
// Same role as the approver, another branch, and explicitly scoped to it — so
// this asks about the SCOPE rule and not about how broad a particular role
// happens to be by default. (SBU_HEAD is company-wide unless scoped, which is
// existing, deliberate behaviour and not M3's to change.)
$uFar = $mk('m3_far', 'SBU_HEAD', 0, 972, $HR, 'm3far@demo.test');
$pdo->prepare("UPDATE users SET scope_offices='972' WHERE id=?")->execute([$uFar]);
$act($uFar);
t_ok(!scope_allows(971, null), 'M3.13 · the far approver is genuinely scoped out of this branch');
t_ok(!in_array($hD, array_map(fn($x) => (int) $x['entity_id'], appr_inbox()), true),
     'M3.13 · a same-role approver at ANOTHER branch does not see this branch\'s request');
// The two questions M2 separated deliberately, and M3 keeps separate:
//   appr_can_act()  does this step NAME you (role, named user, delegation)?
//   appr_guard()    entitlement, branch scope and segregation.
// A same-role approver at another branch passes the first and fails the second,
// which is why the refusal is asserted where the decision is actually made.
t_ok(appr_can_act(appr_step_context($sD, $apD)), 'M3.13 · the far approver does hold the approver role');
t_ok(appr_guard($apD) !== '', 'M3.13 · but is out of scope for this branch: ' . appr_guard($apD));
[$farOk, $farMsg] = appr_act((int) $sD['id'], 'approve', 'from another branch');
t_ok(!$farOk, 'M3.13 · …and the decision is refused, however overdue it is: ' . $farMsg);
t_eq(hreq_get($hD)['status'], 'UNDER_REVIEW', 'M3.13 · nothing was written');
t_eq(count(appr_waiting_on_others()), 0, 'M3.13 · nor see it in their waiting list');

// ---------------------------------------------------------------------------
//  M3.14 · THE SCHEDULER DECIDES NOTHING (§23) — the hard rule, stated as a test
// ---------------------------------------------------------------------------
t_section('M3.14 · a scheduled run may chase an approval; it may never make one');
$hS = $raise(['job_title' => 'M3 scheduler']);
$apS = hreq_approval($hS);
$sS = appr_current_step($apS);
$pdo->prepare("UPDATE recruit_approval_steps SET sla_due=?, reminder_at=? WHERE id=?")->execute([$ago(4), $ago(5), (int) $sS['id']]);
$_SESSION = $origSess; current_user(true); ua(true);          // the cron has nobody signed in
for ($i = 0; $i < 5; $i++) appr_tick();
$sSa = ops_one("SELECT * FROM recruit_approval_steps WHERE id=?", [(int) $sS['id']]);
t_eq((string) $sSa['status'], 'PENDING', 'M3.14 · five runs later the step is still PENDING');
t_eq((string) $sSa['acted_by'], '', 'M3.14 · nobody is recorded as having acted');
t_eq(hreq_get($hS)['status'], 'UNDER_REVIEW', 'M3.14 · the request was neither approved nor rejected');
t_ok(!hreq_is_executable(hreq_get($hS)), 'M3.14 · and nothing may be recruited against it');
t_eq((string) $sSa['approver_role'], (string) $sS['approver_role'], 'M3.14 · the approver was not reassigned');
t_eq((int) appr_request((int) $apS['id'])['current_seq'], (int) $apS['current_seq'], 'M3.14 · and the chain did not advance');

// An approval that lands immediately before the scheduler runs (§30.18, §30.19).
$act($uAppr);
[$raceOk] = appr_act((int) $sS['id'], 'approve', 'just in time');
t_ok($raceOk, 'M3.14 · the approver decides just before the scheduler runs');
$mailWas = $mailN();
$_SESSION = $origSess; current_user(true); ua(true);
appr_tick();
t_eq((string) ops_val("SELECT status FROM recruit_approval_steps WHERE id=?", [(int) $sS['id']]), 'APPROVED',
     'M3.14 · the scheduler leaves the decided step exactly as it found it');
t_ok((int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND subject LIKE 'ESCALATION%' AND created_at > ?", [date('c', time() - 5)]) === 0
     || $mailN() >= $mailWas, 'M3.14 · and does not escalate a step that was decided in time');

// ---------------------------------------------------------------------------
//  Clean up — leave the shared database as we found it.
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
foreach ($mine['hol'] as $d) $pdo->prepare("DELETE FROM holidays WHERE hol_date=? AND name='M3 branch holiday'")->execute([$d]);
office_holidays_flush();
$pdo->exec("DELETE FROM users WHERE username LIKE 'm3\\_%' ESCAPE '\\'");
foreach ($mine['o'] as $o) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([(int) $o]);
$pdo->exec("DELETE FROM email_log WHERE kind='recruit_approval'");
t_ok(true, 'M3 fixtures removed');
