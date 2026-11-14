<?php
// Owner, 12 Nov 2026 — "All the pending task like Project costing approval is not
// shown on the dashboard of the Branch manager or the approval hierarchy. All
// approval pending under any designations or level must show as pending task on
// their individual dashboard." … "Waiting for approval — with level 2: any
// approver; … This is shown wherein our approval chain is level 2 as Business
// Unit head."
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
$keepUid = $_SESSION['uid'] ?? null;
try {
    t_as_admin();
    crm_migrate(); pc_migrate(); appr_migrate(); gate_migrate();
    $tag = 'AOD' . getmypid();
    setting_set('custom_roles', json_encode(['BU_HEAD_IND' => ['label' => 'BU Head – Industrial', 'base' => 'SBU_HEAD']]));
    $mkU = function ($n, $role) use ($pdo, $tag) { $pdo->prepare("INSERT INTO users (username, first_name, role, permissions, scope_sbus, is_active) VALUES (?,?,?,'','',1)")->execute([strtolower($tag . $n), $n, $role]); return (int) $pdo->lastInsertId(); };
    $bm = $mkU('Bimal', 'BRANCH_MANAGER'); $bu = $mkU('Bhavna', 'BU_HEAD_IND');
    $as = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };

    t_section('A company\'s own role counts as the role it is built on — in every approval');
    t_ok(in_array('BU_HEAD_IND', approver_role_keys('SBU_HEAD'), true), 'a level for "Business Unit Head" also belongs to "BU Head – Industrial" (built on it)');
    t_ok(user_holds_approver_role('BU_HEAD_IND', 'SBU_HEAD') && !user_holds_approver_role('BU_HEAD_IND', 'BRANCH_MANAGER'), '… and to no other level');
    t_ok(!in_array('BU_HEAD_IND', approver_role_keys('BUSINESS_DIRECTOR'), true), 'never wider than its own base role');

    t_section('Quotations — "at the same time": level 2 is asked while level 1 is still pending');
    setting_set('quote_approval_mode', 'together');
    $pdo->exec("DELETE FROM quote_approval_rules");
    $ins = $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES (?, 'ANY', '', ?, ?, ?, ?, NULL, 1, ?)");
    $ins->execute(['L1', 0, 500000, 1, 'BRANCH_MANAGER', date('c')]); $ins->execute(['L2', 500000, 0, 2, 'SBU_HEAD', date('c')]);
    //  Top management signs last whatever the setup (owner, 14 Nov 2026) — a Business Director level on top,
    //  so levels 1 and 2 are the ones asked at the same time.
    $ins->execute(['L3', 500000, 0, 3, 'BUSINESS_DIRECTOR', date('c')]);
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, client_name, status, is_current, sbu, subtotal, total_amount, created_at) VALUES (?,0,'C','PENDING_APPROVAL',1,'',600000,708000,?)")->execute([$tag . '-Q', date('c')]);
    $qid = (int) $pdo->lastInsertId(); crm_build_approvals(crm_quote_get($qid));
    $l2 = ops_one("SELECT * FROM quote_approvals WHERE quote_id=? AND level=2", [$qid]);
    t_ok(strpos(crm_pending_with($qid), 'Bhavna') !== false, 'the quotation names her at level 2 instead of "any approver": ' . crm_pending_with($qid));
    $as($bu);
    t_ok(crm_can_act_approval($l2), 'she may approve level 2 while level 1 is still pending');
    t_ok(in_array($qid, crm_quote_ids_awaiting_me(), true), 'it is in her approvals');
    t_eq(crm_quote_open_levels($qid), [1, 2], '…levels 1 and 2 at the same time; the Business Director (level 3) is not asked until they have signed');
    $labels = array_column(ops_pending_tasks(), 'label');
    t_ok(in_array('quotes to approve', $labels, true), 'and on her dashboard: "quotes to approve"');

    t_section('Project costings, recruitment and deal stages reach the dashboard too');
    $as($keepUid ?: 1); t_as_admin();
    $pdo->prepare("INSERT INTO project_costings (code, title, status, created_at) VALUES (?, 'Probe', 'SUBMITTED', ?)")->execute([$tag . '-PC', date('c')]);
    $pc = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO project_costing_approvals (costing_id, level, approver_role, status) VALUES (?,1,'BRANCH_MANAGER','PENDING')")->execute([$pc]);
    $as($bm);
    t_ok(in_array($pc, array_map(fn($h) => (int) $h['id'], pc_waiting_on_me()), true), 'a costing waiting on the Branch Manager is in his approvals');
    $tasks = ops_pending_tasks(); $lab = array_column($tasks, 'label', 'label');
    t_ok(isset($lab['project costings to approve']), 'and on his dashboard: "project costings to approve" (it was missing)');
    $one = array_values(array_filter($tasks, fn($t) => $t['label'] === 'project costings to approve'))[0] ?? [];
    t_eq($one['href'] ?? '', '/project-costing?id=' . $pc, 'one costing waiting: the dashboard opens it directly');

    $pdo->prepare("INSERT INTO recruit_approval_rules (code, name, entity) VALUES (?, 'Offers', 'OFFER')")->execute([$tag]);
    $rr = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO recruit_approval_requests (entity, entity_id, rule_id, status, current_seq, subject, created_at) VALUES ('OFFER', 77, ?, 'PENDING', 1, 'Offer probe', ?)")->execute([$rr, date('c')]);
    $req = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO recruit_approval_steps (request_id, seq, label, approver_role, status) VALUES (?,1,'Unit head','SBU_HEAD','PENDING')")->execute([$req]);
    $as($bu);
    t_ok(appr_can_act(ops_one("SELECT * FROM recruit_approval_steps WHERE request_id=?", [$req]) + ['_entity' => 'OFFER']), 'a recruitment step for "Business Unit Head" can be signed by "BU Head – Industrial"');

    $pdo->prepare("INSERT INTO stage_gate_requests (entity_kind, entity_id, gate_id, approver_role, status) VALUES ('OPPORTUNITY', 5, 0, 'SBU_HEAD', 'PENDING')")->execute();
    $gr = ops_one("SELECT * FROM stage_gate_requests WHERE id=?", [(int) $pdo->lastInsertId()]);
    t_ok(in_array($bu, array_map(fn($u) => (int) $u['id'], gate_approvers($gr)), true), 'a deal held for "Business Unit Head" goes to her as well');

    t_section('When nobody holds the role, the quotation says so');
    $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$bu]);
    $who = crm_step_who($l2);
    t_ok(strpos($who, 'nobody active holds this role') !== false && strpos($who, 'any approver') === false, 'instead of "any approver": ' . $who);
} finally {
    if ($keepUid !== null) { $_SESSION['uid'] = $keepUid; current_user(true); ua(true); }
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
}
