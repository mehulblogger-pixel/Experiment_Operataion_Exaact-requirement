<?php
// Owner, 12 Nov 2026 — "Suppose first we have approval of any level to X
// designation and due to organizational changes that now goes to Y. X has three
// to four pending quotation when organizational changes happened. So the pending
// quote must now show to Y not to the X automatically. This shall be the case to
// every approval."
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
$keepUid = $_SESSION['uid'] ?? null;
try {
    t_as_admin();
    if (function_exists('crm_migrate')) crm_migrate();
    pc_migrate(); appr_migrate(); gate_migrate(); if (function_exists('idems_migrate')) idems_migrate();
    $tag = 'AFN' . getmypid();
    $mkU = function ($n, $role) use ($pdo, $tag) { $pdo->prepare("INSERT INTO users (username, first_name, role, permissions, is_active) VALUES (?,?,?,'',1)")->execute([strtolower($tag . $n), $n, $role]); return (int) $pdo->lastInsertId(); };
    $x = $mkU('Xavier', 'BRANCH_MANAGER'); $y = $mkU('Yamini', 'MARKETING_MANAGER');

    t_section('Quotations: a level moved from X to Y takes its waiting quotations with it');
    $pdo->exec("DELETE FROM quote_approval_rules");
    $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES ('L1','ANY','',0,0,1,'BRANCH_MANAGER',NULL,1,?)")->execute([date('c')]);
    $rule = ops_one("SELECT * FROM quote_approval_rules ORDER BY id DESC LIMIT 1");
    $mkQ = function ($status) use ($pdo, $tag) {
        $pdo->prepare("INSERT INTO quotations (quote_no, rev, client_name, status, is_current, sbu, subtotal, total_amount, created_at) VALUES (?,0,'C',?,1,'',100000,118000,?)")->execute([$tag . '-' . mt_rand(1000, 99999), $status, date('c')]);
        $id = (int) $pdo->lastInsertId(); crm_build_approvals(crm_quote_get($id)); return $id;
    };
    $waiting = [$mkQ('PENDING_APPROVAL'), $mkQ('PENDING_APPROVAL'), $mkQ('PENDING_APPROVAL')];
    $done = $mkQ('PENDING_APPROVAL');
    $pdo->prepare("UPDATE quote_approvals SET status='APPROVED', acted_by='Xavier' WHERE quote_id=?")->execute([$done]);
    $pdo->prepare("UPDATE quotations SET status='APPROVED' WHERE id=?")->execute([$done]);
    $legacy = $mkQ('PENDING_APPROVAL');
    $pdo->prepare("UPDATE quote_approvals SET rule_id=0 WHERE quote_id=?")->execute([$legacy]);   // a step made before steps remembered their level
    t_eq((int) ops_val("SELECT rule_id FROM quote_approvals WHERE quote_id=?", [$waiting[0]]), (int) $rule['id'], 'a step remembers the level it came from');

    $pdo->prepare("UPDATE quote_approval_rules SET approver_role='MARKETING_MANAGER' WHERE id=?")->execute([(int) $rule['id']]);
    $moved = crm_level_reroute_pending($rule, ['approver_role' => 'MARKETING_MANAGER', 'approver_user_id' => null], 'Owner');
    t_eq($moved, 4, 'the three waiting quotations and the older one all move');
    foreach (array_merge($waiting, [$legacy]) as $qid)
        t_eq((string) ops_val("SELECT approver_role FROM quote_approvals WHERE quote_id=?", [$qid]), 'MARKETING_MANAGER', 'quotation ' . $qid . ' now waits for the Marketing Manager');
    $who = array_map(fn($u) => (int) $u['id'], crm_step_approvers(ops_one("SELECT * FROM quote_approvals WHERE quote_id=?", [$waiting[0]])));
    t_ok(in_array($y, $who, true) && !in_array($x, $who, true), 'Y sees it; X no longer does');
    t_eq((string) ops_val("SELECT approver_role FROM quote_approvals WHERE quote_id=?", [$done]), 'BRANCH_MANAGER', 'an approval already given stays as it was — history is not rewritten');
    t_ok((int) ops_val("SELECT COUNT(*) FROM quote_revisions WHERE quote_id=? AND summary LIKE 'Level 1 approval moved from Branch Manager to Marketing Manager%'", [$waiting[1]]) === 1, 'the quotation\'s history says the approval moved, and why');
    $named = ops_one("SELECT * FROM quote_approval_rules WHERE id=?", [(int) $rule['id']]);
    $pdo->prepare("UPDATE quote_approval_rules SET approver_role='', approver_user_id=? WHERE id=?")->execute([$x, (int) $rule['id']]);
    crm_level_reroute_pending($named, ['approver_role' => '', 'approver_user_id' => $x], 'Owner');
    t_eq((int) ops_val("SELECT approver_user_id FROM quote_approvals WHERE quote_id=?", [$waiting[2]]), $x, 'a level changed to one named person moves to that person too');
    t_eq(crm_level_reroute_pending(ops_one("SELECT * FROM quote_approval_rules WHERE id=?", [(int) $rule['id']]), ['approver_role' => '', 'approver_user_id' => $x]), 0, 'saving a level without changing its approver moves nothing');

    t_section('Project costings follow the same levels');
    $pdo->prepare("INSERT INTO project_costings (code, title, status, created_at) VALUES (?, 'Probe', 'SUBMITTED', ?)")->execute([$tag . '-PC', date('c')]);
    $pc = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO project_costing_approvals (costing_id, level, approver_role, approver_user_id, status, rule_id) VALUES (?,1,'',?, 'PENDING', ?)")->execute([$pc, $x, (int) $rule['id']]);
    $cur = ops_one("SELECT * FROM quote_approval_rules WHERE id=?", [(int) $rule['id']]);
    crm_level_reroute_pending($cur, ['approver_role' => '', 'approver_user_id' => $y]);
    t_eq((int) ops_val("SELECT approver_user_id FROM project_costing_approvals WHERE costing_id=?", [$pc]), $y, 'a submitted costing waiting on X now waits on Y');

    t_section('Recruitment approvals (hiring requests, requisitions, offers …)');
    $pdo->prepare("INSERT INTO recruit_approval_rules (code, name, entity) VALUES (?, 'Offers', 'OFFER')")->execute([$tag]);
    $rr = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO recruit_approval_levels (rule_id, seq, label, approver_role, approver_user_id) VALUES (?,1,'HR head','BRANCH_MANAGER',NULL)")->execute([$rr]);
    $lv = ops_one("SELECT * FROM recruit_approval_levels WHERE id=?", [(int) $pdo->lastInsertId()]);
    $pdo->prepare("INSERT INTO recruit_approval_requests (entity, entity_id, rule_id, status, current_seq, created_at) VALUES ('OFFER', 99, ?, 'PENDING', 1, ?)")->execute([$rr, date('c')]);
    $req = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO recruit_approval_steps (request_id, seq, label, approver_role, status) VALUES (?,1,'HR head','BRANCH_MANAGER','PENDING')")->execute([$req]);
    $pdo->prepare("INSERT INTO recruit_approval_requests (entity, entity_id, rule_id, status, current_seq, created_at) VALUES ('OFFER', 98, ?, 'APPROVED', 1, ?)")->execute([$rr, date('c')]);
    $old = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO recruit_approval_steps (request_id, seq, label, approver_role, status) VALUES (?,1,'HR head','BRANCH_MANAGER','APPROVED')")->execute([$old]);
    t_eq(appr_level_reroute_pending($lv, ['approver_role' => 'MARKETING_MANAGER', 'approver_user_id' => 0]), 1, 'the waiting offer moves');
    t_eq((string) ops_val("SELECT approver_role FROM recruit_approval_steps WHERE request_id=?", [$req]), 'MARKETING_MANAGER', '… to the new role');
    t_eq((string) ops_val("SELECT approver_role FROM recruit_approval_steps WHERE request_id=?", [$old]), 'BRANCH_MANAGER', 'a decided one does not');

    t_section('Deal stage gates');
    $pdo->prepare("INSERT INTO stage_gates (name, entity_kind, approver_role, active, created_at) VALUES ('Big wins', 'OPPORTUNITY', 'BRANCH_MANAGER', 1, ?)")->execute([date('c')]);
    $g = ops_one("SELECT * FROM stage_gates WHERE id=?", [(int) $pdo->lastInsertId()]);
    $pdo->prepare("INSERT INTO stage_gate_requests (entity_kind, entity_id, gate_id, approver_role, status) VALUES ('OPPORTUNITY', 1, ?, 'BRANCH_MANAGER', 'PENDING')")->execute([(int) $g['id']]);
    $gr = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO stage_gate_requests (entity_kind, entity_id, gate_id, approver_role, status) VALUES ('OPPORTUNITY', 2, ?, 'BRANCH_MANAGER', 'APPROVED')")->execute([(int) $g['id']]);
    $gd = (int) $pdo->lastInsertId();
    t_eq(gate_reroute_pending($g, '', $y), 1, 'the deal waiting at the gate moves');
    t_eq([(string) ops_val("SELECT approver_role FROM stage_gate_requests WHERE id=?", [$gr]), (int) ops_val("SELECT approver_user_id FROM stage_gate_requests WHERE id=?", [$gr])], ['', $y], '… to Y by name');
    t_eq((string) ops_val("SELECT approver_role FROM stage_gate_requests WHERE id=?", [$gd]), 'BRANCH_MANAGER', 'a decided one does not');

    t_section('Inspection reports — a rule\'s approver, and an inspector\'s mapped approver');
    $pdo->prepare("INSERT INTO inspectors (name, status, created_at) VALUES ('Map Probe','ACTIVE',?)")->execute([date('c')]);
    $ins = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO idems_approver_map (inspector_id, approver_user_id, active, updated_at) VALUES (?,?,1,?)")->execute([$ins, $x, date('c')]);
    $pdo->prepare("INSERT INTO report_docs (inspector_id, status, created_at) VALUES (?, 'SUBMITTED', ?)")->execute([$ins, date('c')]);
    $doc = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_approvals (report_doc_id, level, approver_kind, approver_role, approver_user_id, resolved_user_id, status, created_at) VALUES (?,1,'INSPECTOR_MAP','',?,?,'PENDING',?)")->execute([$doc, $x, $x, date('c')]);
    $ra = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE idems_approver_map SET approver_user_id=? WHERE inspector_id=?")->execute([$y, $ins]);
    t_eq(idems_map_reroute_pending($ins), 1, 'the inspector\'s approver changes from X to Y: the waiting report moves');
    t_eq((int) ops_val("SELECT resolved_user_id FROM report_approvals WHERE id=?", [$ra]), $y, '… and Y is now the approver');
    t_ok((int) ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity='report' AND entity_id=? AND action='APPROVER_CHANGED'", [$doc]) === 1, 'the report\'s audit trail records the change');
    $pdo->prepare("INSERT INTO idems_approval_rules (name, active, level, approver_kind, approver_user_id, approver_role, created_at) VALUES ('Lead reviewer',1,2,'USER',?, '', ?)")->execute([$x, date('c')]);
    $ir = ops_one("SELECT * FROM idems_approval_rules WHERE id=?", [(int) $pdo->lastInsertId()]);
    $pdo->prepare("INSERT INTO report_approvals (report_doc_id, level, approver_kind, approver_role, approver_user_id, resolved_user_id, status, created_at, rule_id) VALUES (?,2,'USER','',?,?,'PENDING',?,?)")->execute([$doc, $x, $x, date('c'), (int) $ir['id']]);
    $ra2 = (int) $pdo->lastInsertId();
    t_eq(idems_rule_reroute_pending($ir, ['approver_kind' => 'USER', 'approver_user_id' => $y, 'approver_role' => '']), 1, 'a report rule\'s named approver changes from X to Y: the waiting report moves');
    t_eq((int) ops_val("SELECT resolved_user_id FROM report_approvals WHERE id=?", [$ra2]), $y, '… to Y');
} finally {
    if ($keepUid !== null) { $_SESSION['uid'] = $keepUid; current_user(true); ua(true); }
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
}
