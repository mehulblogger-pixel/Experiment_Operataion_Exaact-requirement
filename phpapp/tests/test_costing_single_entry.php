<?php
// ============================================================================
//  Costing, owner's notes of 14 Nov 2026 (SAL-188 testing):
//   1. "There are dual entry boxes like Default basis for new roles, Man months, Margin etc. … Duplicate
//      entries are not at all good for the user as it creates many confusions and error in calculations."
//   2. "No exact approval list is provided. When submitted … the head shows 3 levels approval pending.
//      However history at the bottom shows which 3 levels are required with designation."
//   3. "Every approver has a button to reopen the draft."
//  Driven as real logins through the production dispatcher.
// ============================================================================
t_section('Costing — each figure typed once: one Price box');

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

$pdo = db(); pc_migrate(); crm_migrate(); if (function_exists('ch_migrate')) ch_migrate();
$tag = 'cse' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['CSE Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$mkU = function ($first, $role, $reportsTo = null) use ($pdo, $off, $tag) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id, reports_to_id) VALUES (?,?,?,?,?,1,0,?,?)")
        ->execute([$tag . '_' . strtolower($first), $first, 'Cse', '', $role, $off, $reportsTo]);
    return (int) $pdo->lastInsertId();
};
$BD = $mkU('Dev', 'BUSINESS_DIRECTOR'); $MM = $mkU('Manu', 'MARKETING_MANAGER', $BD); $X = $mkU('Bina', 'BUSINESS_DEV_MANAGER', $MM);
$rulesWere = ops_all("SELECT id, active FROM quote_approval_rules") ?: [];
$pdo->exec("UPDATE quote_approval_rules SET active=0");
$ri = $pdo->prepare("INSERT INTO quote_approval_rules (name, level, min_amount, max_amount, approver_role, active, match_type, sbu, created_at) VALUES (?,?,0,0,?,1,'ANY','',?)");
$newRules = [];
foreach ([[1, 'MARKETING_MANAGER'], [2, 'BUSINESS_DIRECTOR']] as [$l, $r]) { $ri->execute(['CSE L' . $l, $l, $r, date('c')]); $newRules[] = (int) $pdo->lastInsertId(); }
$madeC = [];

try {
    $pdo->prepare("INSERT INTO project_costings (code, title, basis, status, overhead_pct, insurance_pct, baddebt_pct, contingency_pct, negotiation_pct, office_id, created_by, created_by_user_id, created_at)
                   VALUES (?, 'Single entry', 'LONG', 'DRAFT', 0, 0, 0, 0, 5, ?, 'Bina Cse', ?, ?)")->execute([$tag . '-PC', $off, $X, date('c')]);
    $cid = (int) $pdo->lastInsertId(); $madeC[] = $cid;
    //  An older role saved before basis was per role (blank basis — it follows the costing).
    $pdo->prepare("INSERT INTO project_costing_lines (costing_id, seq, role_label, heads, boq_qty, target_margin, basis) VALUES (?,1,'Old role',?,12,20,'')")->execute([$cid, json_encode(['PERSONNEL' => 40000])]);
    $item = json_encode([['label' => 'Basic salary', 'code' => 'BASIC', 'type' => 'AMOUNT', 'grp' => 'PAY', 'per' => 'MONTH', 'calc' => '40000']]);
    $base = ['costing_id' => $cid, 'role_label' => 'Engineer', 'headcount' => 1, 'boq_qty' => 12, 'target_margin' => 20, 'dpm' => 26, 'items_json' => $item, 'line_basis' => 'LONG'];

    $g = $drive($X, 'project-costing', 'GET', ['id' => $cid], [], ['name="price_mode"', 'name="rate_quoted"', 'name="rate_manual"', 'id="ch_ctc_tm"', 'id="pcs-basis"', '<b>Price</b>']);
    t_ok(!empty($g['has']['name="price_mode"']) && !empty($g['has']['name="rate_quoted"']) && !empty($g['has']['<b>Price</b>']), 'the role box has one Price box: how the price is set, the client\'s rate, the margin, the quantity');
    t_ok(empty($g['has']['name="rate_manual"']) && empty($g['has']['id="ch_ctc_tm"']) && empty($g['has']['id="pcs-basis"']),
        'no second margin box, no "fixed rate override" box, no "Default basis" box in the settings');

    //  The client's rate is fixed: typed ONCE, as the client pays it — with 5% negotiation padding.
    $r = pc_role_save(pc_get($cid), $base + ['price_mode' => 'RATE', 'rate_quoted' => 66000]);
    $ln = ops_one("SELECT * FROM project_costing_lines WHERE id=?", [(int) $r['line_id']]);
    $c = pc_line_calc($ln, pc_get($cid));
    t_ok(!$r['errs'] && abs($c['proposed'] - 66000) < 1, 'the client\'s rate typed once — ₹66,000 — is exactly the rate quoted (' . $c['proposed'] . '), padding and all');
    $r2 = pc_role_save(pc_get($cid), $base + ['line_id' => $r['line_id'], 'price_mode' => 'MARGIN', 'rate_quoted' => 66000]);
    t_eq((float) ops_val("SELECT rate_manual FROM project_costing_lines WHERE id=?", [(int) $r['line_id']]), 0.0, 'switched to "Cost + my margin": no fixed rate is kept — the rate follows the margin');
    $r3 = pc_role_save(pc_get($cid), $base + ['price_mode' => 'RATE', 'rate_quoted' => '']);
    t_ok((bool) array_filter($r3['errs'], fn($m) => strpos($m, 'Type the rate') === 0), '"rate is fixed" with no rate: refused, and says what to type');
    unset($_SESSION['pc_line_draft'][$cid]);

    //  The basis, asked once on the role; the next new role starts with the last one's.
    $r4 = pc_role_save(pc_get($cid), array_merge($base, ['role_label' => 'Day specialist', 'line_basis' => 'DAILY', 'boq_qty' => 15, 'price_mode' => 'MARGIN']));
    t_eq((string) pc_get($cid)['basis'], 'DAILY', 'after a man-day role, the next new role starts as man-day');
    t_eq((string) ops_val("SELECT basis FROM project_costing_lines WHERE costing_id=? AND role_label='Old role'", [$cid]), 'LONG', '…and the older role keeps man-month — it was given its basis first, so it never changes');
    pc_settings_save(pc_get($cid), ['title' => 'Single entry', 'overhead_pct' => 8]);
    t_eq((string) pc_get($cid)['basis'], 'DAILY', '"Save settings" (no basis box any more) leaves the basis alone');
    $pdo->prepare("UPDATE project_costings SET overhead_pct=0 WHERE id=?")->execute([$cid]);

    t_section('Costing — submitted: the exact approval list at the top; only its preparer takes it back');
    $s = $drive($X, 'project-costing-submit', 'POST', [], ['id' => $cid]);
    t_eq((string) pc_get($cid)['status'], 'SUBMITTED', 'submitted: ' . $said($s));
    t_ok(strpos($said($s), 'Level 1 — Marketing Manager (') !== false && strpos($said($s), 'Level 2 — Business Director (') !== false && strpos($said($s), 'signs last') !== false,
        'the message lists every level, by designation and name, and who signs last');
    $lv = pc_levels_status(pc_get($cid));
    t_eq(array_column($lv, 1), ['NOW', 'LATER'], 'level 1 is waiting now; the Business Director comes after it');
    $g = $drive($MM, 'project-costing', 'GET', ['id' => $cid], [], ['Waiting for approval — 2 levels:', e($lv[0][0]), e($lv[1][0]), 'action="/project-costing-reopen"', 'action="/project-costing-reject"']);
    t_ok(!empty($g['has']['Waiting for approval — 2 levels:']) && !empty($g['has'][e($lv[0][0])]) && !empty($g['has'][e($lv[1][0])]), 'the band at the top lists both levels, by designation and name, with where each stands');
    t_ok(empty($g['has']['action="/project-costing-reopen"']) && !empty($g['has']['action="/project-costing-reject"']), 'the approver has no "Reopen as draft" — they send it back, with a reason');
    $r = $drive($MM, 'project-costing-reopen', 'POST', [], ['id' => $cid]);
    t_eq((string) pc_get($cid)['status'], 'SUBMITTED', 'a forced reopen by the approver is refused');
    $g = $drive($BD, 'project-costing', 'GET', ['id' => $cid], [], ['action="/project-costing-reopen"']);
    t_ok(empty($g['has']['action="/project-costing-reopen"']), 'nor does the Business Director have one');
    $g = $drive($X, 'project-costing', 'GET', ['id' => $cid], [], ['action="/project-costing-reopen"']);
    t_ok(!empty($g['has']['action="/project-costing-reopen"']), 'its preparer may take it back out of approval');
    $drive($MM, 'project-costing-approve', 'POST', [], ['id' => $cid]);
    $lv = pc_levels_status(pc_get($cid));
    t_ok($lv[0][1] === 'DONE' && strpos($lv[0][0], 'approved by Manu Cse') !== false && $lv[1][1] === 'NOW', 'after level 1 signs: "✓ approved by Manu Cse", and the Business Director is now waiting');
    $r = $drive($X, 'project-costing-reopen', 'POST', [], ['id' => $cid]);
    t_eq((string) pc_get($cid)['status'], 'DRAFT', 'the preparer takes it back: ' . $said($r));
} finally {
    foreach ($madeC as $id) { foreach (['project_costing_lines', 'project_costing_approvals'] as $t) $pdo->prepare("DELETE FROM $t WHERE costing_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM activities WHERE entity_kind='PROJECT_COSTING' AND entity_id=?")->execute([$id]); $pdo->prepare("DELETE FROM project_costings WHERE id=?")->execute([$id]); }
    foreach ($newRules as $id) $pdo->prepare("DELETE FROM quote_approval_rules WHERE id=?")->execute([$id]);
    foreach ($rulesWere as $r) $pdo->prepare("UPDATE quote_approval_rules SET active=? WHERE id=?")->execute([(int) $r['active'], (int) $r['id']]);
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
}
