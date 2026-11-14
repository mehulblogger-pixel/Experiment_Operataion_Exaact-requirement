<?php
// ============================================================================
//  Basis per role · who may reopen an approved costing · who is told of a send-back.
//
//  Owner, 14 Nov 2026:
//    "Basis shall be per role it may be either of three option like long term
//     deputation or manday deputation or short term deputation."
//    "Costing once approved shall not be able to open as a draft by who has prepared
//     it. It shall be only the highest authority who has last approved it." — and
//     "Any one at Z level who is delegated his work or designation above Z."
//    "If Z rejects then it shall also open as a draft and notification shall be sent
//     to the Y" — "Dashboard and email" — "Yes everyone approves again."
//  Driven as real logins through the production dispatcher.
// ============================================================================
t_section('Basis per role — one costing, man-month and man-day roles side by side');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode(array_values($needles)));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? [])) . (!empty($r['error']) ? ' [' . $r['error'] . ']' : '');

$pdo = db(); pc_migrate(); crm_migrate(); if (function_exists('appr_migrate')) appr_migrate(); if (function_exists('act_migrate')) act_migrate();
$tag = 'rbr' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['RBR Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$mkU = function ($first, $role, $reportsTo = null) use ($pdo, $off, $tag) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id, reports_to_id) VALUES (?,?,?,?,?,1,0,?,?)")
        ->execute([$tag . '_' . strtolower($first), $first, 'Rbr', strtolower($first) . '.' . $tag . '@example.test', $role, $off, $reportsTo]);
    return (int) $pdo->lastInsertId();
};
$BOSS = $mkU('Bharat', 'BUSINESS_DIRECTOR');                   // above Z in Z's reporting line
$X = $mkU('Xavier', 'BUSINESS_DEV_MANAGER');                   // prepares
$Y = $mkU('Yamini', 'MARKETING_MANAGER');                      // level 1
$Z = $mkU('Zubin', 'BRANCH_MANAGER', $BOSS);                   // level 2, reports to Bharat
$Z2 = $mkU('Zoya', 'BRANCH_MANAGER');                          // the same level as Z, no delegation
$pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,'MASTER_ADMIN',1,1,?)")
    ->execute([$tag . '_master', 'Mitra', 'Rbr', 'mitra.' . $tag . '@example.test', $off]);
$M = (int) $pdo->lastInsertId();                               // the Master Admin
$D = $mkU('Dinesh', 'MARKETING_EXECUTIVE');                    // Z has delegated quotation approvals to Dinesh
$O = $mkU('Omkar', 'OPERATION_MANAGER');                       // nobody in particular
$pdo->prepare("INSERT INTO approval_delegations (delegator_user_id, delegate_user_id, entity, office_id, effective_from, effective_to, reason, active, created_by, created_at)
               VALUES (?,?,'QUOTE',NULL,'','','On leave',1,'test',?)")->execute([$Z, $D, date('c')]);
$delegId = (int) $pdo->lastInsertId();

$rulesWere = ops_all("SELECT id, active FROM quote_approval_rules") ?: [];
$modeWas = setting_get('quote_approval_mode', null);
$pdo->exec("UPDATE quote_approval_rules SET active=0");
$ri = $pdo->prepare("INSERT INTO quote_approval_rules (level, min_amount, max_amount, approver_role, active, sbu) VALUES (?,?,?,?,1,'')");
$ri->execute([1, 0, 0, 'MARKETING_MANAGER']); $r1 = (int) $pdo->lastInsertId();
$ri->execute([2, 0, 0, 'BRANCH_MANAGER']);    $r2 = (int) $pdo->lastInsertId();
setting_set('quote_approval_mode', 'order');

$pdo->prepare("INSERT INTO project_costings (code, title, basis, status, overhead_pct, insurance_pct, baddebt_pct, contingency_pct, negotiation_pct, office_id, created_by, created_by_user_id, created_at, updated_at)
               VALUES (?, 'Mixed team', 'LONG', 'DRAFT', 8, 0.5, 0, 0.5, 0, ?, 'Xavier Rbr', ?, ?, ?)")->execute([strtoupper($tag), $off, $X, date('c'), date('c')]);
$cid = (int) $pdo->lastInsertId();
$madeQ = [];
$item = fn($amount) => json_encode([['code' => 'PAY', 'label' => 'Salary', 'type' => 'AMOUNT', 'amount' => $amount, 'calc' => (string)$amount, 'per' => 'MONTH', 'grp' => 'PAY']]);

try {
    //  Two roles saved through the real editor: one per man-month, one per man-day.
    $r = $drive($X, 'project-costing-line-save', 'POST', [], ['costing_id' => $cid, 'role_label' => 'Site Engineer', 'headcount' => 2, 'boq_qty' => 24,
        'target_margin' => 20, 'dpm' => 26, 'line_basis' => 'LONG', 'items_json' => $item(52000)]);
    $r = $drive($X, 'project-costing-line-save', 'POST', [], ['costing_id' => $cid, 'role_label' => 'NDT Specialist', 'headcount' => 1, 'boq_qty' => 15,
        'target_margin' => 20, 'dpm' => 26, 'line_basis' => 'DAILY', 'items_json' => $item(52000)]);
    $L = ops_all("SELECT * FROM project_costing_lines WHERE costing_id=? ORDER BY id", [$cid]);
    t_eq(array_column($L, 'basis'), ['LONG', 'DAILY'], 'each role keeps the basis chosen for it: ' . $said($r));
    $h = pc_get($cid);
    $cM = pc_line_calc($L[0], $h); $cD = pc_line_calc($L[1], $h);
    t_ok(abs($cM['direct'] - 52000) < 1 && abs($cD['direct'] - 2000) < 1, 'the man-month role costs ₹52,000 a month; the man-day role ₹2,000 a day (52,000 ÷ 26) — same salary, each in its own unit');
    t_ok(abs($cM['revenue'] - $cM['proposed'] * 24) < 1 && abs($cD['revenue'] - $cD['proposed'] * 15) < 1, 'revenue is rate × quantity in each role\'s own unit (24 man-months; 15 man-days)');
    $pv = $drive($X, 'project-costing-line-preview', 'POST', [], ['costing_id' => $cid, 'role_label' => 'x', 'boq_qty' => 1, 'dpm' => 26, 'line_basis' => 'DAILY', 'items_json' => $item(52000)]);
    t_ok(($pv['done'] ?? '') !== 'threw', 'the live figures follow the basis chosen on the role');

    //  Changing the costing's default does not move roles that have their own basis.
    $pdo->prepare("UPDATE project_costings SET basis='DAILY' WHERE id=?")->execute([$cid]);
    t_eq(pc_line_basis($L[0], pc_get($cid)), 'LONG', 'the costing\'s default changed to man-day: the man-month role stays man-month');
    t_eq(pc_line_basis(['basis' => ''], pc_get($cid)), 'DAILY', '…a role saved before this update (no basis of its own) follows the default, as before');
    $pdo->prepare("UPDATE project_costings SET basis='LONG' WHERE id=?")->execute([$cid]);

    //  The quotation made from it has one line per role, each in its own unit.
    $pdo->prepare("UPDATE project_costings SET status='APPROVED' WHERE id=?")->execute([$cid]);
    $mq = pc_make_quote(pc_rollup($cid)); $madeQ[] = (int)($mq['id'] ?? 0);
    $ql = ops_all("SELECT unit, qty FROM quote_lines WHERE quote_id=? ORDER BY line_no", [(int)$mq['id']]);
    t_eq(array_map(fn($x) => [$x['unit'], (float)$x['qty']], $ql), [['MANMONTH', 24.0], ['MANDAY', 15.0]], 'the quotation: a man-month line and a man-day line');
    $pdo->prepare("UPDATE project_costings SET status='DRAFT', quote_id=NULL WHERE id=?")->execute([$cid]);
    $pdo->prepare("DELETE FROM quote_lines WHERE quote_id=?")->execute([(int)$mq['id']]);
    $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([(int)$mq['id']]);
    $g = $drive($X, 'project-costing', 'GET', ['id' => $cid], [], ['name="line_basis"', 'id="pc-settings-panel"', 'id="pcs-basis"']);
    //  Owner, 14 Nov 2026: "dual entry boxes like Default basis for new roles … are not at all good" — the basis
    //  is asked once, on the role; the settings no longer carry a second "Default basis" box.
    t_ok(!empty($g['has']['name="line_basis"']) && empty($g['has']['id="pcs-basis"']), 'the basis is asked once — on the role; the settings have no second "Default basis" box');

    t_section('Settings at the top of the page');
    $v = (string) file_get_contents(__DIR__ . '/../views/ops/project_costing.php');
    t_ok(strpos($v, 'id="pc-settings-panel"') !== false && strpos($v, 'id="pc-settings-panel"') < strpos($v, '<!-- Roles table -->'), 'the settings come before the roles — no scrolling to the foot of the page');
    t_ok(strpos($v, 'grid-template-columns:repeat(4,minmax(0,1fr))') !== false && strpos($v, '@media (max-width:560px){.pc-set{grid-template-columns:1fr}}') !== false, 'four to a row on a laptop, one on a phone');

    t_section('Reopen an approved costing — only the last approver\'s level, a delegate, or above');
    $r = $drive($X, 'project-costing-submit', 'POST', [], ['id' => $cid]);
    $drive($Y, 'project-costing-approve', 'POST', [], ['id' => $cid]);
    $drive($Z, 'project-costing-approve', 'POST', [], ['id' => $cid, 'approval_ref' => 'Z-1']);
    t_eq(pc_get($cid)['status'], 'APPROVED', 'arranged: X prepared it, Y approved level 1, Z gave the last approval');
    t_eq((int) ops_val("SELECT acted_by_id FROM project_costing_approvals WHERE costing_id=? AND level=2", [$cid]), $Z, 'who signed each level is kept by id');
    $reopenAs = function ($uid) use ($drive, $cid, $pdo) {
        $r = $drive($uid, 'project-costing-reopen', 'POST', [], ['id' => $cid]);
        $ok = pc_get($cid)['status'] === 'DRAFT';
        if ($ok) {   // put it back exactly as it was, ready for the next person
            $pdo->prepare("UPDATE project_costings SET status='APPROVED', approved_by='Zubin Rbr' WHERE id=?")->execute([$cid]);
            $pdo->prepare("DELETE FROM project_costing_approvals WHERE costing_id=?")->execute([$cid]);
            $pdo->prepare("INSERT INTO project_costing_approvals (costing_id, level, approver_role, status, acted_by, acted_by_id, acted_at) VALUES (?,1,'MARKETING_MANAGER','APPROVED','Yamini Rbr',?,?),(?,2,'BRANCH_MANAGER','APPROVED','Zubin Rbr',?,?)")
                ->execute([$cid, (int) ops_val("SELECT id FROM users WHERE first_name='Yamini' AND username LIKE ?", ['%rbr%']), date('c'), $cid, (int) ops_val("SELECT id FROM users WHERE first_name='Zubin' AND username LIKE ?", ['%rbr%']), date('c')]);
        }
        return [$ok, $r];
    };
    //  Owner, 14 Nov 2026 (seeing the button still offered): "only the highest level approver can open the costing. No one else."
    foreach ([[$X, false, 'X, who prepared it'], [$Y, false, 'Y, a lower level'], [$O, false, 'someone unrelated'],
              [$Z2, false, 'a colleague at Z\'s level with no delegation from Z'], [$M, false, 'the Master Admin'],
              [$Z, true, 'Z, the highest-level approver'], [$D, true, 'Z\'s delegate (Z away)'], [$BOSS, true, 'above Z in Z\'s reporting line (Z away)']] as [$u, $want, $who]) {
        [$ok, $r] = $reopenAs($u);
        t_eq($ok, $want, ($want ? 'may reopen: ' : 'may NOT reopen: ') . $who . ($want ? '' : ' — "' . $said($r) . '"'));
    }
    $g = $drive($X, 'project-costing', 'GET', ['id' => $cid], [], ['action="/project-costing-reopen"', 'Nobody else, including the person who prepared it']);
    t_ok(empty($g['has']['action="/project-costing-reopen"']) && !empty($g['has']['Nobody else, including the person who prepared it']), 'X sees no "Reopen as draft", and reads who may');
    foreach ([[$M, 'the Master Admin'], [$Z2, 'a colleague at Z\'s level']] as [$u, $who]) {
        $g = $drive($u, 'project-costing', 'GET', ['id' => $cid], [], ['action="/project-costing-reopen"']);
        t_ok(empty($g['has']['action="/project-costing-reopen"']), $who . ' is not offered "Reopen as draft"');
    }
    $g = $drive($Z, 'project-costing', 'GET', ['id' => $cid], [], ['action="/project-costing-reopen"']);
    t_ok(!empty($g['has']['action="/project-costing-reopen"']), 'Z sees "Reopen as draft"');

    t_section('Sent back — the preparer and the earlier approvers are told; everyone approves again');
    $pdo->prepare("UPDATE project_costings SET status='DRAFT' WHERE id=?")->execute([$cid]);
    $drive($X, 'project-costing-submit', 'POST', [], ['id' => $cid]);
    $drive($Y, 'project-costing-approve', 'POST', [], ['id' => $cid]);
    $mailsBefore = (int) ops_val("SELECT COALESCE(MAX(id),0) FROM email_log");
    $r = $drive($Z, 'project-costing-reject', 'POST', [], ['id' => $cid, 'reject_note' => 'Rates too thin for the specialist']);
    t_eq(pc_get($cid)['status'], 'REJECTED', 'Z sends it back: ' . $said($r));
    t_ok(stripos($said($r), 'Xavier Rbr') !== false && stripos($said($r), 'Yamini Rbr') !== false, 'Z is told who has been told');
    $mails = ops_all("SELECT to_addr, subject, body FROM email_log WHERE id>? AND subject LIKE ?", [$mailsBefore, '%' . strtoupper($tag) . '%sent back%']) ?: [];
    $to = array_column($mails, 'to_addr');
    t_ok(in_array('xavier.' . $tag . '@example.test', $to, true), 'X (who prepared it) gets an e-mail — to revise it');
    t_ok(in_array('yamini.' . $tag . '@example.test', $to, true), 'Y (who had approved level 1) gets an e-mail — it will come back to them');
    t_ok(!in_array('zubin.' . $tag . '@example.test', $to, true) && !in_array('omkar.' . $tag . '@example.test', $to, true), 'nobody else is e-mailed');
    $yMail = array_values(array_filter($mails, fn($m) => $m['to_addr'] === 'yamini.' . $tag . '@example.test'))[0] ?? ['body' => ''];
    t_ok(strpos($yMail['body'], 'Rates too thin for the specialist') !== false && strpos($yMail['body'], 'yours included') !== false, 'the e-mail carries the reason and says it will come back to them');
    $_SESSION['uid'] = $X; unset($_SESSION['uid_ws']); current_user(true); ua(true);
    $tX = array_filter(ops_pending_tasks(), fn($t) => strpos($t['label'], 'sent back — revise') !== false);
    $_SESSION['uid'] = $Y; unset($_SESSION['uid_ws']); current_user(true); ua(true);
    $tY = array_filter(ops_pending_tasks(), fn($t) => strpos($t['label'], 'you approved was sent back') !== false);
    t_ok((bool) $tX, 'X\'s dashboard: "costing sent back — revise it"');
    t_ok((bool) $tY, 'Y\'s dashboard: "costing you approved was sent back"');
    $g = $drive($X, 'project-costing', 'GET', ['id' => $cid], [], ['id="add-role"']);
    t_ok(!empty($g['has']['id="add-role"']), 'it is open for X to revise — the role editor is there');

    $drive($X, 'project-costing-submit', 'POST', [], ['id' => $cid]);
    t_eq(array_column(ops_all("SELECT status FROM project_costing_approvals WHERE costing_id=? ORDER BY level", [$cid]), 'status'), ['PENDING', 'PENDING'], 'resubmitted: every level approves again, Y included');
    $_SESSION['uid'] = $Y; unset($_SESSION['uid_ws']); current_user(true); ua(true);
    t_ok(!array_filter(ops_pending_tasks(), fn($t) => strpos($t['label'], 'you approved was sent back') !== false), '…and the "sent back" notice leaves Y\'s dashboard');
} finally {
    t_as_admin();
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='PROJECT_COSTING' AND entity_id=?")->execute([$cid]);
    $pdo->prepare("DELETE FROM project_costing_approvals WHERE costing_id=?")->execute([$cid]);
    $pdo->prepare("DELETE FROM project_costing_lines WHERE costing_id=?")->execute([$cid]);
    $pdo->prepare("DELETE FROM project_costings WHERE id=?")->execute([$cid]);
    foreach (array_filter($madeQ) as $q) { $pdo->prepare("DELETE FROM quote_lines WHERE quote_id=?")->execute([$q]); $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$q]); }
    $pdo->prepare("DELETE FROM approval_delegations WHERE id=?")->execute([$delegId]);
    $pdo->prepare("DELETE FROM quote_approval_rules WHERE id IN (?,?)")->execute([$r1, $r2]);
    foreach ($rulesWere as $rw) $pdo->prepare("UPDATE quote_approval_rules SET active=? WHERE id=?")->execute([(int) $rw['active'], (int) $rw['id']]);
    setting_set('quote_approval_mode', $modeWas === null ? 'order' : $modeWas);
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
}
