<?php
// ============================================================================
//  A costing's approval trail, and "Create quotation" only once it is approved.
//
//  Owner, 13 Nov 2026: "when costing is submitted for approval and if every person
//  of level can approve simultaneously then after every approval a create quotation
//  button is there available and any one including costing creator and costing
//  approver at each stage. I think so we must have a trail that who has created the
//  costing, who has approved and what is pending and so on."
//
//  Driven as REAL logins through the production dispatcher and view: the author
//  (a BDM), a Marketing Manager at level 1 and a Business Director at level 2, with
//  the company's "all levels at the same time" answer. Nothing about who may approve
//  changes; the test proves the button and the record.
// ============================================================================
t_section('Costing trail — set up two levels that sign at the same time');

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

$pdo = db(); pc_migrate(); if (function_exists('act_migrate')) act_migrate();
$tag = 'pct' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['PCT Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$mkU = function ($first, $role) use ($pdo, $off, $tag) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
        ->execute([$tag . '_' . strtolower($first), $first, 'Pct', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$BDM = $mkU('Bina', 'BUSINESS_DEV_MANAGER'); $MM = $mkU('Manu', 'MARKETING_MANAGER'); $BD = $mkU('Dev', 'BUSINESS_DIRECTOR');

//  The company's approval levels and mode, put back exactly afterwards.
$rulesWere = ops_all("SELECT id, active FROM quote_approval_rules") ?: [];
$modeWas   = setting_get('quote_approval_mode', null);
$pdo->exec("UPDATE quote_approval_rules SET active=0");
$ruleIns = $pdo->prepare("INSERT INTO quote_approval_rules (level, min_amount, max_amount, approver_role, active, sbu) VALUES (?,?,?,?,1,'')");
$ruleIns->execute([1, 0, 0, 'MARKETING_MANAGER']); $r1 = (int) $pdo->lastInsertId();
$ruleIns->execute([2, 0, 0, 'BUSINESS_DIRECTOR']); $r2 = (int) $pdo->lastInsertId();
setting_set('quote_approval_mode', 'together');

$pdo->prepare("INSERT INTO project_costings (code, title, basis, status, overhead_pct, insurance_pct, baddebt_pct, contingency_pct, negotiation_pct, office_id, created_by, created_by_user_id, created_at, updated_at)
               VALUES (?,?,'LONG','DRAFT',8,0.5,0,0.5,0,?,?,?,?,?)")
    ->execute([strtoupper($tag), 'Trail costing ' . $tag, $off, 'Bina Pct', $BDM, date('c'), date('c')]);
$cid = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO project_costing_lines (costing_id, seq, role_label, heads, boq_qty, target_margin) VALUES (?,1,'Site Engineer',?,12,22)")
    ->execute([$cid, json_encode(['PERSONNEL' => 45000])]);
pc_trail($cid, 'Costing ' . strtoupper($tag) . ' created by Bina Pct');
$quoteMade = 0;
$BUTTON = 'Create quotation</button>';
$trail  = fn() => array_column(ops_all("SELECT subject, body, created_by FROM activities WHERE entity_kind='PROJECT_COSTING' AND entity_id=? ORDER BY id", [$cid]) ?: [], 'subject');
$body   = fn($needle) => (string) ops_val("SELECT body FROM activities WHERE entity_kind='PROJECT_COSTING' AND entity_id=? AND subject LIKE ? ORDER BY id DESC LIMIT 1", [$cid, '%' . $needle . '%']);

try {
    t_eq(crm_approval_mode(), 'together', 'ARMING · the company answered "all levels at the same time" (Set up Sales)');

    t_section('Costing trail — "Create quotation" is offered only once the costing is fully approved');
    $g = $drive($BDM, 'project-costing', 'GET', ['id' => $cid], [], [$BUTTON, 'is offered once this costing is fully approved', 'Prepared</b> by Bina Pct']);
    t_ok(empty($g['has'][$BUTTON]) && !empty($g['has']['is offered once this costing is fully approved']), 'a draft: no "Create quotation" button for its author — the page says when it comes' . $said($g));
    t_ok(!empty($g['has']['Prepared</b> by Bina Pct']), 'the trail starts with who prepared it');

    //  Owner, 14 Nov 2026: "show the levels before submitting the costing".
    $preview = pc_preview_levels(pc_get($cid));
    $g = $drive($BDM, 'project-costing', 'GET', ['id' => $cid], [], ['When submitted it goes to', 'all levels at the same time', e($preview[1] ?? 'x')]);
    t_ok(!empty($g['has']['When submitted it goes to']) && !empty($g['has']['all levels at the same time']) && !empty($g['has'][e($preview[1] ?? 'x')]),
        'before submitting, the costing shows the levels its value will need, by name, and how they sign: ' . implode(' / ', $preview));
    $s = $drive($BDM, 'project-costing-submit', 'POST', [], ['id' => $cid]);
    $built = array_map(fn($st) => pc_level_words($st, pc_get($cid)), pc_steps($cid));
    t_eq($built, $preview, '…and submitting creates exactly those levels — the preview never disagrees with the real thing');
    $g = $drive($BDM, 'project-costing', 'GET', ['id' => $cid], [], ['When submitted it goes to']);
    t_ok(empty($g['has']['When submitted it goes to']), 'once submitted, the preview gives way to the trail');
    t_ok(stripos($said($s), 'submitted for approval — 2 levels') !== false, 'the author submits — 2 levels, all at the same time: ' . $said($s));
    t_ok(strpos((string) ($trail()[1] ?? ''), 'Submitted for approval by Bina Pct — 2 levels, all at the same time') === 0, 'the trail records who submitted it, and how the levels sign');
    //  The names are whoever holds each level over this branch (the first three) — on a shared test
    //  database that may include other Marketing Managers, so compare with what the system picks.
    $hNow = pc_get($cid); $want = array_map(fn($st) => pc_level_words($st, $hNow), pc_steps($cid));
    t_ok(count($want) === 2 && strpos($want[0], 'Level 1 — Marketing Manager (') === 0 && strpos($want[1], 'Level 2 — Business Director (') === 0
        && $body('Submitted for approval') === implode("\n", $want), '…and to whom, by name, level by level: ' . str_replace("\n", ' / ', $body('Submitted for approval')));

    $a1 = $drive($MM, 'project-costing-approve', 'POST', [], ['id' => $cid]);
    t_ok(stripos($said($a1), 'your level is signed') !== false, 'level 1 signs: ' . $said($a1));
    foreach ([[$BDM, 'its author'], [$MM, 'the level 1 approver who just signed'], [$BD, 'the level 2 approver still to sign']] as [$u, $who]) {
        $waitWords = e(pc_waiting_words(pc_get($cid)));
        $v = $drive($u, 'project-costing', 'GET', ['id' => $cid], [], [$BUTTON, $waitWords]);
        t_ok(empty($v['has'][$BUTTON]), 'one level signed: no "Create quotation" for ' . $who);
        t_ok(strpos($waitWords, 'Waiting for Level 2 — Business Director (') === 0 && !empty($v['has'][$waitWords]), '…and ' . $who . ' reads what it waits for, by name');
    }
    t_ok(in_array('Level 1 approved by Manu Pct', $trail(), true), 'the trail records the level 1 signature, by name');

    t_section('Costing trail — sent back, then the second round');
    $rj = $drive($BD, 'project-costing-reject', 'POST', [], ['id' => $cid, 'reject_note' => 'Margin too thin for a new client']);
    t_ok(stripos($said($rj), 'sent back') !== false, 'level 2 sends it back with a reason');
    t_eq($body('ent back by Dev Pct'), 'Reason: Margin too thin for a new client', 'the trail keeps who sent it back and why');
    $s2 = $drive($BDM, 'project-costing-submit', 'POST', [], ['id' => $cid]);
    t_ok(stripos($said($s2), 'submitted') !== false, 'the author submits it again');
    $drive($MM, 'project-costing-approve', 'POST', [], ['id' => $cid]);
    $a2 = $drive($BD, 'project-costing-approve', 'POST', [], ['id' => $cid, 'approval_ref' => 'BD-77']);
    t_ok(stripos($said($a2), 'costing approved') !== false, 'both levels sign — approved: ' . $said($a2));
    $T = $trail();
    t_ok(in_array('Level 2 approved by Dev Pct · ref BD-77 — costing fully approved', $T, true), 'the trail records the last signature, its reference, and that it is fully approved');
    t_eq(count(array_filter($T, fn($x) => strpos($x, 'Submitted for approval') === 0)), 2, 'both rounds stay in the trail — a resubmission no longer wipes the first one');
    t_ok(count(array_filter($T, fn($x) => strpos($x, 'ent back by Dev Pct') !== false)) === 1, '…including the send-back');

    $v = $drive($BDM, 'project-costing', 'GET', ['id' => $cid], [], [$BUTTON, 'Fully approved</b>', 'Approved</b> by Manu Pct', 'Approved</b> by Dev Pct · ref BD-77', 'History of this costing']);
    t_ok(!empty($v['has'][$BUTTON]), 'fully approved: "Create quotation" is offered');
    t_ok(!empty($v['has']['Approved</b> by Manu Pct']) && !empty($v['has']['Approved</b> by Dev Pct · ref BD-77']) && !empty($v['has']['Fully approved</b>']),
        'the trail on the page: each level, who signed, the reference, fully approved');
    t_ok(!empty($v['has']['History of this costing']), 'the full history (every round) is at the foot of the page');

    t_section('Costing trail — the quotation, and a reopening');
    $q = $drive($BDM, 'project-costing-to-quote', 'POST', [], ['id' => $cid]);
    $quoteMade = (int) ops_val("SELECT quote_id FROM project_costings WHERE id=?", [$cid]);
    t_ok($quoteMade > 0, 'the quotation is made from the approved costing: ' . $said($q));
    t_ok((bool) array_filter($trail(), fn($x) => strpos($x, ' made from this costing by Bina Pct') !== false), 'the trail records the quotation and who made it');
    $v = $drive($BDM, 'project-costing', 'GET', ['id' => $cid], [], [$BUTTON, 'made by Bina Pct']);
    t_ok(empty($v['has'][$BUTTON]) && !empty($v['has']['made by Bina Pct']), 'with a quotation made, the button gives way to the link, and the trail names who made it');

    //  A costing that is not yet approved still refuses a quotation at the door, whoever asks.
    $pdo->prepare("UPDATE project_costings SET quote_id=NULL WHERE id=?")->execute([$cid]);
    //  14 Nov 2026: the preparer may not reopen an approved costing; the last approver may.
    $ro = $drive($BDM, 'project-costing-reopen', 'POST', [], ['id' => $cid]);
    t_ok(stripos($said($ro), 'reopened as draft') === false && pc_get($cid)['status'] === 'APPROVED', 'its preparer may not reopen it: ' . $said($ro));
    $ro = $drive($BD, 'project-costing-reopen', 'POST', [], ['id' => $cid]);
    t_ok(stripos($said($ro), 'reopened as draft') !== false, 'the one who gave the last approval reopens it: ' . $said($ro));
    t_ok(in_array('Reopened as draft by Dev Pct — its approval was withdrawn', $trail(), true), 'the trail records who reopened it and that its approval was withdrawn');
    $tq = $drive($BDM, 'project-costing-to-quote', 'POST', [], ['id' => $cid]);
    t_ok(stripos($said($tq), 'once this costing is approved') !== false && !(int) ops_val("SELECT quote_id FROM project_costings WHERE id=?", [$cid]),
        'pressed anyway on a draft (an old page): refused — the rule at the door is unchanged');

    t_section('Costing trail — nothing about who may approve changed');
    $lib = (string) file_get_contents(__DIR__ . '/../lib/projcosting.php');
    t_ok(strpos($lib, "if (pc_is_author(\$h)) return 'You prepared this costing, so someone else must approve it.';") !== false, 'its author still never signs');
    t_ok(strpos($lib, "if ((string)\$roll['header']['status'] !== 'APPROVED') {") !== false, 'a quotation is still made only from an approved costing');
} finally {
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='PROJECT_COSTING' AND entity_id=?")->execute([$cid]);
    $pdo->prepare("DELETE FROM project_costing_approvals WHERE costing_id=?")->execute([$cid]);
    $pdo->prepare("DELETE FROM project_costing_lines WHERE costing_id=?")->execute([$cid]);
    $pdo->prepare("DELETE FROM project_costings WHERE id=?")->execute([$cid]);
    if ($quoteMade) { $pdo->prepare("DELETE FROM quote_lines WHERE quote_id=?")->execute([$quoteMade]); $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$quoteMade]); }
    $pdo->prepare("DELETE FROM quote_approval_rules WHERE id IN (?,?)")->execute([$r1, $r2]);
    foreach ($rulesWere as $r) $pdo->prepare("UPDATE quote_approval_rules SET active=? WHERE id=?")->execute([(int) $r['active'], (int) $r['id']]);
    setting_set('quote_approval_mode', $modeWas === null ? 'order' : $modeWas);
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
}
