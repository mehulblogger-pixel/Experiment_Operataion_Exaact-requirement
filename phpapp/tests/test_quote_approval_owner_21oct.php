<?php
// The owner, 21 Oct 2026:
//  1. "Yes skip the second approval" — a quotation made from an approved
//     costing, at its prices, is not approved again.
//  2. "If there is no approval chain then top management is not required to
//     review or approve quotation."
//  3. "Setting in sales setup wherein simultaneous approval are required."
t_section('quotation approval: costing covers it, no levels = no approval, levels at the same time');

$pdo = db();
if (function_exists('crm_migrate')) crm_migrate(); pc_migrate();
$saved = ops_all("SELECT * FROM quote_approval_rules") ?: [];
$restore = function () use ($pdo, $saved) {
    $pdo->exec("DELETE FROM quote_approval_rules");
    foreach ($saved as $s) { $cols = array_keys($s); $pdo->prepare('INSERT INTO quote_approval_rules (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($s)); }
};
$oldMode = setting_get('quote_approval_mode', ''); $oldPol = setting_get('quote_unmatched_approval', '');
$pdo->exec("DELETE FROM quote_approval_rules");
$pdo->prepare("INSERT INTO offices (name) VALUES ('QA21 Office')")->execute(); $off = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO quotations (quote_no, rev, client_name, status, is_current, office_id, sbu, subtotal, total_amount, created_at) VALUES ('QA21-1',0,'C','DRAFT',1,?,'',800000,944000,?)")->execute([$off, date('c')]);
$q = crm_quote_get((int)$pdo->lastInsertId());

// --- 2. no levels at all ----------------------------------------------------------
setting_set('quote_unmatched_approval', 'top');
t_ok(!crm_quote_needs_approval($q), 'with no approval levels, a quotation needs no approval');
t_ok(strpos(crm_quote_no_approval_reason($q), 'no approval levels') !== false, '…and says why');
$rule = $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,active,created_at) VALUES (?,?,?,?,?,?,?,1,?)");
$rule->execute(['L1', 'ANY', '', 0, 0, 1, 'BRANCH_MANAGER', date('c')]);
$rule->execute(['L2', 'ANY', '', 500000, 0, 2, 'SBU_HEAD', date('c')]);
t_ok(crm_quote_needs_approval($q), 'with levels set up, it does');

// --- 1. made from an approved costing ---------------------------------------------
$pdo->prepare("INSERT INTO project_costings (code, title, office_id, basis, status, quote_id, created_by, created_at) VALUES ('QA21-PC','T',?, 'LONG','APPROVED',?, 'x', ?)")->execute([$off, $q['id'], date('c')]);
$pcId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO project_costing_lines (costing_id, seq, role_label, heads, boq_qty, target_margin) VALUES (?,1,'Engineer',?,12,20)")->execute([$pcId, json_encode(['PERSONNEL' => 50000])]);
$roll = pc_rollup($pcId); $c = pc_line_calc($roll['lines'][0], $roll['header']);
$pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, description, order_type, qty, unit, rate, amount) VALUES (?,1,'Engineer','LINE',12,'MANMONTH',?,?)")->execute([$q['id'], $c['proposed'], $c['revenue']]);
t_ok(!crm_quote_needs_approval($q), 'made from an approved costing, at its prices: not approved a second time');
t_ok(strpos(crm_quote_no_approval_reason($q), 'approved project costing') !== false, '…and says why');
$pdo->prepare("UPDATE quote_lines SET rate=rate+1000 WHERE quote_id=?")->execute([$q['id']]);
t_ok(crm_quote_needs_approval($q), 'a changed price is approved again');
$pdo->prepare("UPDATE quote_lines SET rate=? WHERE quote_id=?")->execute([$c['proposed'], $q['id']]);
$pdo->prepare("UPDATE project_costings SET status='DRAFT' WHERE id=?")->execute([$pcId]);
t_ok(crm_quote_needs_approval($q), 'a costing no longer approved covers nothing');

// --- 3. all levels at the same time ---------------------------------------------
$mkU = function ($u, $role) use ($pdo, $off) { $pdo->prepare("INSERT INTO users (username, first_name, role, home_office_id, permissions, is_active) VALUES (?,?,?,?,'',1)")->execute([$u, $u, $role, $off]); return (int)$pdo->lastInsertId(); };
$bm = $mkU('qa21_bm', 'BRANCH_MANAGER'); $sbu = $mkU('qa21_sbu', 'SBU_HEAD');
$pdo->prepare("UPDATE quotations SET status='PENDING_APPROVAL' WHERE id=?")->execute([$q['id']]);
crm_build_approvals(crm_quote_get($q['id']));
$as = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };
setting_set('quote_approval_mode', 'order');
$as($sbu); t_ok(!in_array((int)$q['id'], crm_quote_ids_awaiting_me(), true), 'one after another: level 2 is not asked before level 1');
setting_set('quote_approval_mode', 'together');
$as($sbu); t_ok(in_array((int)$q['id'], crm_quote_ids_awaiting_me(), true), 'at the same time: level 2 is asked straight away');
$as($bm);  t_ok(in_array((int)$q['id'], crm_quote_ids_awaiting_me(), true), '…and so is level 1');
t_ok(strpos(crm_pending_with($q['id']), 'all at the same time') !== false, 'the quotation says it waits on every level at once');
$crm = file_get_contents(__DIR__ . '/../lib/crm.php');
t_ok(strpos($crm, "crm_approval_mode() === 'order' && (\$_POST['decision'] ?? '') !== 'reject' && (int)\$step['level'] > \$lowest") !== false,
    'the level-order check applies only when levels approve one after another');
$keys = array_column(array_merge(...array_column(setupqa_sales_set()['sections'], 'questions')), 'key');
t_ok(in_array('quote_approval_mode', $keys, true), 'Set up Sales asks how the levels approve');

// Costings follow the same answer.
$pdo->prepare("UPDATE project_costings SET status='SUBMITTED', quote_id=NULL, created_by_user_id=NULL WHERE id=?")->execute([$pcId]);
pc_build_approvals(pc_get($pcId));
$as($sbu); t_ok(pc_can_act(pc_get($pcId)) === (count(pc_steps($pcId)) > 1), 'a costing\'s level 2 may sign at once when levels approve at the same time');

unset($_SESSION['uid']); current_user(true); ua(true);
setting_set('quote_approval_mode', $oldMode); setting_set('quote_unmatched_approval', $oldPol);
$restore();
$pdo->prepare("DELETE FROM quote_approvals WHERE quote_id=?")->execute([$q['id']]);
$pdo->prepare("DELETE FROM quote_lines WHERE quote_id=?")->execute([$q['id']]);
$pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$q['id']]);
$pdo->prepare("DELETE FROM project_costing_approvals WHERE costing_id=?")->execute([$pcId]);
$pdo->prepare("DELETE FROM project_costing_lines WHERE costing_id=?")->execute([$pcId]);
$pdo->prepare("DELETE FROM project_costings WHERE id=?")->execute([$pcId]);
$pdo->exec("DELETE FROM users WHERE username LIKE 'qa21_%'");
$pdo->exec("DELETE FROM offices WHERE name='QA21 Office'");
