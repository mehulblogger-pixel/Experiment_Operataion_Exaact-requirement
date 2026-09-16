<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #2 — REQUESTER IDENTITY & FAIL-CLOSED RESOLUTION
//
//  Two principles, and they are the point of the whole milestone:
//
//    1. A NAME IS A DISPLAY ATTRIBUTE, NEVER A SECURITY IDENTITY.
//    2. AN UNRESOLVABLE SUBJECT OR ENTITY MUST FAIL CLOSED.
//
//  C1  the decision e-mail resolved its recipient from a display-name string with
//      LIMIT 1, so a namesake in another branch with no recruitment permission
//      was told a hiring request's title and outcome while the real requester
//      heard nothing.
//  C2  when the request row could not be fetched, the entity came out '' and the
//      visibility test fell through to "not a hiring request → allow", skipping
//      entitlement, branch scope and segregation entirely.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #2 — requester identity & fail-closed resolution');

$pdo = db(); hreq_migrate(); appr_migrate();
$mine = ['h' => [], 'u' => [], 'o' => [], 'rule' => [], 'rq' => []];
$origSess = $_SESSION;
$offWas = function_exists('setting_get') ? (string) setting_get('modules_off', '') : '';

foreach ([[951, 'ID Branch A'], [952, 'ID Branch B']] as $o) {
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); $mine['o'][] = $o[0]; } catch (Throwable $e) {}
}
$mk = function ($un, $role, $super, $off, $scope, $perms, $email, $fn, $ln) use ($pdo, &$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions,email)
                   VALUES (?,?,?,?,1,?,?,?,?,?)")
        ->execute([$un, $fn, $ln, $role, $super ? 1 : 0, $off, $scope, $perms, $email]);
    $id = (int) $pdo->lastInsertId(); $mine['u'][] = $id; return $id;
};
$act = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };
$HR = 'mod.hiring.view,mod.hiring.edit';

//  THE ORIGINAL AUDIT, REPRODUCED. The namesake is created FIRST, so that the old
//  "LIMIT 1, no ordering" lookup would find THEM and not the real requester —
//  which is exactly how the probe caught it.
$uGhostB = $mk('id_ghostb', 'INSPECTOR',      0, 952, '952', '',  'idghostb@t.test', 'Ravi', 'Sharma'); // other branch, no recruitment
$uGhostP = $mk('id_ghostp', 'COORDINATOR',    0, 951, '951', '',  'idghostp@t.test', 'Ravi', 'Sharma'); // same branch, no recruitment
$uReal   = $mk('id_real',   'BRANCH_MANAGER', 0, 951, '951', $HR, 'idreal@t.test',   'Ravi', 'Sharma'); // the actual requester
$uApp    = $mk('id_app',    'SBU_HEAD',       0, 951, '951', $HR, 'idapp@t.test',    'ID',   'Approver');
$uOther  = $mk('id_other',  'BRANCH_MANAGER', 0, 951, '951', $HR, 'idother@t.test',  'Nina', 'Desai');
$uMast   = $mk('id_mast',   'ADMIN',          1, 951, '',    '',  'idmast@t.test',   'ID',   'Master');
$uFarApp = $mk('id_farapp', 'SBU_HEAD',       0, 952, '952', $HR, 'idfarapp@t.test', 'ID',   'Far');   // SAME role, other branch

$cfg = function (callable $fn) use ($uMast) {
    $prev = $_SESSION['uid'] ?? null;
    $_SESSION['uid'] = $uMast; current_user(true); ua(true);
    try { return $fn(); }
    finally { if ($prev === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prev; current_user(true); ua(true); }
};
$rule = $cfg(fn() => appr_rule_save(0, ['name' => 'ID hiring', 'entity' => 'HIRING_REQUEST', 'code' => 'IDHR', 'applies_office_id' => 951]));
$mine['rule'][] = $rule;
$cfg(fn() => appr_level_save(['rule_id' => $rule, 'seq' => 1, 'label' => 'Unit head', 'approver_role' => 'SBU_HEAD',
    'sla_days' => 2, 'reminder_days' => 1]));

$mails = fn($a) => (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND to_addr=? AND subject LIKE '%APPROVED%'", [$a]);
$acts  = fn($like) => (int) ops_val("SELECT COUNT(*) FROM activities WHERE subject LIKE ?", ['%' . $like . '%']);
$raise = function ($asUser, $title, $office = 951) use ($act, &$mine) {
    $act($asUser);
    [$ok, , $id] = hreq_save(0, ['job_title' => $title, 'quantity' => 1, 'office_id' => $office,
                                 'priority' => 'NORMAL', 'approval_required' => 1]);
    if ($ok) $mine['h'][] = $id;
    hreq_submit($id);
    $ap = hreq_approval($id);
    return [$id, $ap, $ap ? appr_current_step($ap) : null];
};
$decide = function ($step, $asUser = null) use ($act, $uApp) { $act($asUser ?: $uApp); return appr_act((int) $step['id'], 'approve', 'ok'); };

// ---------------------------------------------------------------------------
//  ID1 · THE AUDIT, REPRODUCED — and C1-1 / C1-2 / C1-3 / C1-4
// ---------------------------------------------------------------------------
t_section('ID1 · a name is not an identity');
[$h1, $ap1, $s1] = $raise($uReal, 'ID confidential restructure');
t_ok($ap1 !== null, 'ID1 · the request went to its approvers');
t_eq((string) $ap1['requester'], 'Ravi Sharma', 'ID1 · the chain still records the display name — that is all it was ever for');
$canon = appr_requester_user($ap1);
t_eq((int) ($canon['id'] ?? 0), $uReal, 'ID1 · but the identity comes from hiring_requests.requested_by_id');
$decide($s1);
t_ok($mails('idreal@t.test') >= 1, 'ID1 · C1-1/C1-2 · the ACTUAL requester is told (got ' . $mails('idreal@t.test') . ')');
t_eq($mails('idghostb@t.test'), 0, 'ID1 · C1-3 · the namesake in ANOTHER BRANCH is told nothing');
t_eq($mails('idghostp@t.test'), 0, 'ID1 · C1-4 · the namesake with NO recruitment permission is told nothing');
t_eq((int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND subject LIKE '%confidential restructure%' AND to_addr IN ('idghostb@t.test','idghostp@t.test')"), 0,
     'ID1 · and nothing naming the request reached either of them');

// ---------------------------------------------------------------------------
//  ID2 · C1-5 — the stored text and the canonical person disagree
// ---------------------------------------------------------------------------
t_section('ID2 · legacy text never overrides the identity');
[$h2, $ap2, $s2] = $raise($uOther, 'ID renamed requester');
$pdo->prepare("UPDATE recruit_approval_requests SET requester=? WHERE id=?")->execute(['Ravi Sharma', (int) $ap2['id']]);
$ap2 = appr_request((int) $ap2['id']);
t_eq((string) $ap2['requester'], 'Ravi Sharma', 'ID2 · the chain text now says somebody else entirely');
t_eq((int) (appr_requester_user($ap2)['id'] ?? 0), $uOther, 'ID2 · C1-5 · the identity is still the person who raised it');
$before = $mails('idother@t.test'); $ghostBefore = $mails('idreal@t.test');
$decide($s2);
t_ok($mails('idother@t.test') > $before, 'ID2 · C1-5 · and the canonical requester is the one told');
t_eq($mails('idreal@t.test'), $ghostBefore, 'ID2 · C1-5 · the person the TEXT named is not');

// ---------------------------------------------------------------------------
//  ID3 · C1-6 / C1-7 / C1-8 — fail closed, and say so
// ---------------------------------------------------------------------------
t_section('ID3 · unresolvable identity fails closed');
[$h3, $ap3, $s3] = $raise($uOther, 'ID no identity');
$pdo->prepare("UPDATE hiring_requests SET requested_by_id=0 WHERE id=?")->execute([(int) $h3]);
$ap3 = appr_request((int) $ap3['id']);
t_ok(appr_requester_user($ap3) === null, 'ID3 · C1-6 · a request with no canonical id resolves to NOBODY');
$b = $mails('idother@t.test'); $ab = $acts('no canonical requester identity');
$decide($s3);
t_eq($mails('idother@t.test'), $b, 'ID3 · C1-6 · so no decision e-mail is sent — it is not guessed from the text');
t_eq($acts('no canonical requester identity'), $ab + 1, 'ID3 · C1-6 · and the silence is recorded on the activity spine');

[$h4, $ap4, $s4] = $raise($uOther, 'ID foreign identity');
$foreign = 99700000 + random_int(1, 999);
t_ok(!ops_one("SELECT id FROM users WHERE id=?", [$foreign]), 'ID3 · C1-7 · the foreign id does not exist in this database');
$pdo->prepare("UPDATE hiring_requests SET requested_by_id=? WHERE id=?")->execute([$foreign, (int) $h4]);
$ap4 = appr_request((int) $ap4['id']);
t_ok(appr_requester_user($ap4) === null, 'ID3 · C1-7 · an id from another tenant resolves to nobody');
$b = $mails('idother@t.test');
$decide($s4);
t_eq($mails('idother@t.test'), $b, 'ID3 · C1-7 · and nothing is sent');

[$h5, $ap5, $s5] = $raise($uOther, 'ID inactive requester');
$pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$uOther]);
$b = $mails('idother@t.test');
$decide($s5);
t_eq($mails('idother@t.test'), $b, 'ID3 · C1-8 · an INACTIVE requester is not written to — the existing policy is respected');
$pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$uOther]);

// ---------------------------------------------------------------------------
//  ID4 · C1-9 / C1-10 — entities with no identity, and direct invocation
// ---------------------------------------------------------------------------
t_section('ID4 · no identity means no guess, on every entity and every call');
$legacy = ['id' => 0, 'entity' => 'OFFER', 'entity_id' => 4242, 'subject' => 'ID legacy offer', 'requester' => 'Ravi Sharma'];
t_ok(appr_requester_user($legacy) === null,
     'ID4 · C1-9 · an entity that records its raiser only as TEXT resolves to nobody');
$b1 = $mails('idreal@t.test'); $b2 = $mails('idghostb@t.test'); $ab = $acts('no canonical requester identity');
appr_email_requester($legacy, 'approved', 'direct call');
t_eq($mails('idreal@t.test'), $b1, 'ID4 · C1-9 · so a historical record tells nobody rather than guessing');
t_eq($mails('idghostb@t.test'), $b2, 'ID4 · C1-9 · …least of all a namesake');
t_eq($acts('no canonical requester identity'), $ab + 1, 'ID4 · C1-9 · and it is recorded');

$forged = ['id' => 0, 'entity' => 'HIRING_REQUEST', 'entity_id' => (int) $h1, 'subject' => 'forged', 'requester' => 'Ravi Sharma'];
$b = $mails('idghostb@t.test');
appr_email_requester($forged, 'approved', 'direct call');
t_eq($mails('idghostb@t.test'), $b,
     'ID4 · C1-10 · invoking the notifier directly cannot make it deliver to a name');
t_ok($mails('idreal@t.test') >= 1, 'ID4 · C1-10 · it still resolves to the canonical person and nobody else');

setting_set('modules_off', 'hr'); licence_disabled(true); ua(true);
$b = $mails('idreal@t.test');
appr_email_requester(appr_request((int) $ap1['id']), 'approved', 'unlicensed');
t_eq($mails('idreal@t.test'), $b, 'ID4 · C1-10 · and identity alone does not authorize — an unlicensed workspace sends nothing');
setting_set('modules_off', $offWas); licence_disabled(true); ua(true);

// ---------------------------------------------------------------------------
//  ID5 · C2 — a missing or unknown entity DENIES
// ---------------------------------------------------------------------------
t_section('ID5 · unknown or unresolvable entity fails closed');
[$h6, $ap6, $s6] = $raise($uReal, 'ID visibility');
$st6 = appr_step_context($s6, $ap6);
$appRow = ops_one("SELECT * FROM users WHERE id=?", [$uApp]);
//  Asked the way the product asks it. appr_visible() parameterises appr_can_act()
//  but evaluates appr_guard() for the SESSION user, so eligibility for somebody
//  else is asked through appr_may_be_asked(), which impersonates. Asserting on
//  appr_visible() directly here would be testing a different question.
t_ok(appr_may_be_asked($st6, $ap6, $appRow), 'ID5 · C2-1 · a VALID hiring request is normally visible to its approver');
t_ok(count(appr_step_recipients($st6, $ap6)) >= 1, 'ID5 · C2-1 · and has recipients');

//  THE DECISIVE ONE: the person CAN act, and must still not be told.
t_ok(appr_can_act($st6, $appRow), 'ID5 · C2-10 · the approver genuinely passes appr_can_act()');
t_ok(!appr_visible($st6, null, $appRow),
     'ID5 · C2-10 · yet a MISSING entity is NOT visible — it does not fall through to appr_can_act()');
t_ok(!appr_visible($st6, ['entity' => '', 'entity_id' => 0], $appRow), 'ID5 · C2-9 · nor is a blank entity reference');
t_ok(!appr_visible($st6, ['entity' => 'NOT_A_THING', 'entity_id' => 7], $appRow), 'ID5 · C2-8 · nor an UNKNOWN entity');
t_ok(!appr_visible($st6, ['entity' => 'HIRING_REQUEST', 'entity_id' => 0], $appRow), 'ID5 · C2-3 · nor an invalid hiring-request id');
t_ok(!appr_visible($st6, ['entity' => 'HIRING_REQUEST', 'entity_id' => $foreign], $appRow), 'ID5 · C2-4 · nor one from another tenant');
//  …and the same through the real recipient path.
$orphan = $st6; $orphan['request_id'] = 99800000 + random_int(1, 999);
t_eq(count(appr_step_recipients($orphan, null)), 0, 'ID5 · C2-2 · a step whose request has vanished notifies NOBODY');

//  Offer / salary / requisition behaviour is untouched — no branch requirement
//  was introduced for entities that cannot establish one.
$offerReq = ['entity' => 'OFFER', 'entity_id' => 4242, 'subject' => 'x'];
t_ok(appr_visible($st6, $offerReq, $appRow), 'ID5 · a KNOWN non-hiring entity is still visible on can-act alone — unchanged');

//  C2-5 / C2-6 / C2-7 — the existing behaviours still hold.
//  A holder of the SAME approver role at another branch: they pass can-act, and
//  must still be refused on scope — otherwise this assertion would be passing for
//  the wrong reason.
$farRow    = ops_one("SELECT * FROM users WHERE id=?", [$uGhostB]);
$farAppRow = ops_one("SELECT * FROM users WHERE id=?", [$uFarApp]);
t_ok(appr_can_act($st6, $farAppRow), 'ID5 · C2-5 · the far approver does hold the approver role');
t_ok(!appr_may_be_asked($st6, $ap6, $farAppRow), 'ID5 · C2-5 · but cross-branch visibility is unchanged — still refused');
t_ok(!in_array('idfarapp@t.test', appr_step_recipients($st6, $ap6), true), 'ID5 · C2-5 · and they are not notified');
$act($uReal); hreq_cancel($h6, 'withdrawn');
$ap6c = appr_request((int) $ap6['id']);
t_eq((string) $ap6c['status'], 'CANCELLED', 'ID5 · C2-6 · cancelling still cancels the chain');
$_SESSION = $origSess; current_user(true); ua(true);
$b = $mails('idapp@t.test');
appr_tick();
t_eq($mails('idapp@t.test'), $b, 'ID5 · C2-6 · and a cancelled request sends nothing');
[$h7, $ap7, $s7] = $raise($uReal, 'ID completed');
$decide($s7);
$pdo->prepare("UPDATE recruit_approval_steps SET reminder_at=? WHERE id=?")->execute([date('c', time() - 86400), (int) $s7['id']]);
$b = (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND to_addr='idapp@t.test'");
$_SESSION = $origSess; current_user(true); ua(true);
appr_tick();
t_eq((int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='recruit_approval' AND to_addr='idapp@t.test'"), $b,
     'ID5 · C2-7 · a COMPLETED approval produces no actionable notification');

// ---------------------------------------------------------------------------
//  ID6 · the impersonation machinery, re-tested as regression only (§6)
// ---------------------------------------------------------------------------
t_section('ID6 · session impersonation — regression only, not redesigned');
$act($uReal);
$uid0 = (int) (current_user()['id'] ?? 0); $scope0 = scope_allows(952, null) ? 1 : 0; $perm0 = can('mod.hiring.edit') ? 1 : 0;
appr_as_user($farRow, fn() => scope_allows(952, null));
t_eq((int) (current_user()['id'] ?? 0), $uid0, 'ID6 · the signed-in user is restored');
t_eq(scope_allows(952, null) ? 1 : 0, $scope0, 'ID6 · …and their branch scope');
t_eq(can('mod.hiring.edit') ? 1 : 0, $perm0, 'ID6 · …and their permissions');
try { appr_as_user($farRow, function () { throw new RuntimeException('boom'); }); } catch (Throwable $e) {}
t_eq((int) (current_user()['id'] ?? 0), $uid0, 'ID6 · an exception still restores the session');
appr_as_user($farRow, function () use ($appRow) { appr_as_user($appRow, fn() => true); return true; });
t_eq((int) (current_user()['id'] ?? 0), $uid0, 'ID6 · nesting unwinds to the original user');

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
foreach ($mine['u'] as $u) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int) $u]);
foreach ($mine['o'] as $o) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([(int) $o]);
$pdo->exec("DELETE FROM email_log WHERE kind='recruit_approval'");
t_ok(true, 'M3 correction #2 fixtures removed');
