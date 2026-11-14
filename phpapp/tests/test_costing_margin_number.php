<?php
// ============================================================================
//  Costing — the margin warning (± 10 points of the target) and the costing number.
//
//  Owner, 14 Nov 2026:
//    "it must show the warning for both high and low margin than required. That
//     shall be above + or - 10% … what is set."
//    "every costing must have a unique number showing quote, client etc. … including
//     the year date and month … when the costing is revised it must have a revision
//     number starting from Rev. 01, 02 etc."
//  Driven as real logins through the production dispatcher and views.
// ============================================================================
t_section('Costing margin warning — ± 10 points of the role\'s target margin');

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
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => [], 'body' => $raw];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? [])) . (!empty($r['error']) ? ' [' . $r['error'] . ']' : '');

$pdo = db(); pc_migrate(); crm_migrate(); if (function_exists('ch_migrate')) ch_migrate();
$tag = 'cmn' . getmypid();
$pdo->prepare("INSERT INTO offices (name, code, is_active) VALUES (?,?,1)")->execute(['CMN Office ' . $tag, 'CMN']);
$off = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
    ->execute([$tag . '_meera', 'Meera', 'Cmn', '', 'MARKETING_MANAGER', $off]);
$MM = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, created_at) VALUES (?,?,1,?)")->execute(['CMN Client Pvt Ltd ' . $tag, 'CMN Client ' . $tag, date('c')]);
$cli = (int) $pdo->lastInsertId();
$madeC = []; $madeInq = [];

try {
    $pdo->prepare("INSERT INTO project_costings (code, title, basis, status, overhead_pct, insurance_pct, baddebt_pct, contingency_pct, negotiation_pct, office_id, created_at)
                   VALUES (?, 'Margin probe', 'LONG', 'DRAFT', 0, 0, 0, 0, 0, ?, ?)")->execute([$tag . '-M', $off, date('c')]);
    $cid = (int) $pdo->lastInsertId(); $madeC[] = $cid; $h = pc_get($cid);
    $ln = fn($rate, $tm = 20) => ['role_label' => 'Engineer', 'heads' => json_encode(['PERSONNEL' => 40000]), 'boq_qty' => 12, 'target_margin' => $tm, 'rate_manual' => $rate, 'basis' => 'LONG'];

    t_eq(pc_margin_check($ln(0), $h), null, 'the rate worked out from the target margin: margin = target, no warning');
    $hi = pc_margin_check($ln(100000), $h);
    t_eq([$hi['side'] ?? '', $hi['margin'] ?? 0], ['HIGH', 60.0], 'a fixed rate giving 60% against a 20% target: warned, too HIGH');
    t_ok(str_contains($hi['words'] ?? '', 'Margin too high') && str_contains($hi['words'] ?? '', '40 points above') && str_contains($hi['words'] ?? '', '10% to 30%'),
        '…in plain words, with the allowed range: ' . ($hi['words'] ?? ''));
    $lo = pc_margin_check($ln(42000), $h);
    t_eq($lo['side'] ?? '', 'LOW', 'a fixed rate giving 4.8% against 20%: warned, too LOW — ' . ($lo['words'] ?? ''));
    t_eq(pc_margin_check($ln(round(40000 / 0.7, 2)), $h), null, 'exactly 10 points above (30%): inside the band, no warning');
    t_eq((pc_margin_check($ln(round(40000 / 0.695, 2)), $h)['side'] ?? ''), 'HIGH', '30.5% — just past 10 points: warned');
    t_eq(pc_margin_check($ln(round(40000 / 0.9, 2)), $h), null, 'exactly 10 points below (10%): no warning');
    t_eq((pc_margin_check($ln(round(40000 / 0.905, 2)), $h)['side'] ?? ''), 'LOW', '9.5% — just past 10 points below: warned');
    t_eq(pc_margin_check($ln(100000, 0), $h), null, 'no target margin set: nothing to compare with, no warning');
    //  The negotiation padding is there to be given away — it does not set off the warning.
    $pdo->prepare("UPDATE project_costings SET negotiation_pct=25 WHERE id=?")->execute([$cid]); $h = pc_get($cid);
    $c = pc_line_calc($ln(0), $h);
    t_ok($c['margin'] > 30, 'ARMING · with 25% negotiation padding the quoted margin reads ' . $c['margin'] . '%');
    t_eq(pc_margin_check($ln(0), $h), null, '…but at the price before the padding it is the 20% set — no warning');
    $pdo->prepare("UPDATE project_costings SET negotiation_pct=0 WHERE id=?")->execute([$cid]); $h = pc_get($cid);

    t_section('Costing margin warning — on the page, for the preparer and the approvers');
    $ins = $pdo->prepare("INSERT INTO project_costing_lines (costing_id, seq, role_label, heads, boq_qty, target_margin, rate_manual, basis) VALUES (?,?,?,?,12,20,?, 'LONG')");
    $ins->execute([$cid, 1, 'Site Engineer', json_encode(['PERSONNEL' => 40000]), 100000]);
    $ins->execute([$cid, 2, 'Welder', json_encode(['PERSONNEL' => 40000]), 0]);
    $ins->execute([$cid, 3, 'Helper', json_encode(['PERSONNEL' => 40000]), 42000]);
    t_eq(array_column(pc_margin_flags(pc_get($cid)), 'role'), ['Site Engineer', 'Helper'], 'the costing lists the two roles outside the band, not the one inside');
    $g = $drive($MM, 'project-costing', 'GET', ['id' => $cid], [], ['2 roles outside the margin set', 'Site Engineer</b> — Margin too high', 'Helper</b> — Margin too low', '⚠ too high', '⚠ too low', 'You can still save and submit']);
    foreach (['2 roles outside the margin set', 'Site Engineer</b> — Margin too high', 'Helper</b> — Margin too low', '⚠ too high', '⚠ too low', 'You can still save and submit'] as $n)
        t_ok(!empty($g['has'][$n]), 'the costing page shows: ' . $n);
    //  Live, while a role is being typed — nothing saved.
    $item = [['label' => 'Basic salary', 'code' => 'BASIC', 'type' => 'AMOUNT', 'grp' => 'PAY', 'per' => 'MONTH', 'calc' => '40000']];
    $pv = $drive($MM, 'project-costing-line-preview', 'POST', [], ['costing_id' => $cid, 'role_label' => 'New', 'boq_qty' => 12, 'target_margin' => 20, 'rate_manual' => 100000,
          'line_basis' => 'LONG', 'items_json' => json_encode($item)], ['"margin_warn":"Margin too high', '"margin_side":"HIGH"']);
    t_ok(!empty($pv['has']['"margin_warn":"Margin too high']) && !empty($pv['has']['"margin_side":"HIGH"']), 'the role editor\'s live figures carry the warning as it is typed' . (!empty($pv['error']) ? ' [' . $pv['error'] . ']' : ''));
    $pv = $drive($MM, 'project-costing-line-preview', 'POST', [], ['costing_id' => $cid, 'role_label' => 'New', 'boq_qty' => 12, 'target_margin' => 20, 'rate_manual' => 0,
          'line_basis' => 'LONG', 'items_json' => json_encode($item)], ['"margin_warn":""']);
    t_ok(!empty($pv['has']['"margin_warn":""']), '…and none when the margin is inside the band');
    //  A warning only — the costing can still be submitted.
    $s = $drive($MM, 'project-costing-submit', 'POST', [], ['id' => $cid]);
    t_ok(stripos($said($s), 'margin') === false || pc_get($cid)['status'] === 'SUBMITTED', 'the warning never blocks a submission (' . $said($s) . ')');

    t_section('Costing number — branch / PC / year / month / number, and Rev');
    $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, client_id, client_name, subject, status, office_id, received_date, created_at) VALUES (?,?,?,?,'OPEN',?,?,?)")
        ->execute([strtoupper($tag) . '-ENQ', $cli, 'CMN Client ' . $tag, 'Plant team', $off, date('Y-m-d'), date('c')]);
    $inq = (int) $pdo->lastInsertId(); $madeInq[] = $inq;
    $r = $drive($MM, 'project-costing-new', 'POST', [], ['inquiry_id' => $inq]);
    $nid = (int) ops_val("SELECT id FROM project_costings WHERE inquiry_id=? ORDER BY id DESC LIMIT 1", [$inq]); if ($nid) $madeC[] = $nid;
    $n = $nid ? pc_get($nid) : [];
    $fy = fy_label(current_fy());
    t_ok((bool) preg_match('#^CMN/PC/' . preg_quote($fy, '#') . '/' . date('m') . '/\d{4} Rev 00$#', (string) ($n['code'] ?? '')),
        'a new costing is numbered branch/PC/year/month/number Rev 00: ' . ($n['code'] ?? '') . ' — ' . $said($r));
    t_eq([(string) ($n['base_code'] ?? ''), (int) ($n['rev'] ?? -1)], [preg_replace('/ Rev 00$/', '', (string) ($n['code'] ?? '')), 0], '…its number without the revision is kept, and it is Rev 00');
    $r2 = $drive($MM, 'project-costing-new', 'POST', [], ['inquiry_id' => $inq]);
    $nid2 = (int) ops_val("SELECT id FROM project_costings WHERE inquiry_id=? ORDER BY id DESC LIMIT 1", [$inq]); if ($nid2 && $nid2 !== $nid) $madeC[] = $nid2;
    $seq = fn($c) => (int) preg_replace('#^.*/(\d{4}) Rev \d+$#', '$1', (string) $c);
    t_eq($seq(pc_get($nid2)['code'] ?? ''), $seq($n['code'] ?? '') + 1, 'the next costing takes the next running number: ' . (pc_get($nid2)['code'] ?? ''));
    t_ok(str_contains(pc_ref_words(pc_get($nid)), 'CMN Client ' . $tag) && str_contains(pc_ref_words(pc_get($nid)), 'enquiry ' . strtoupper($tag) . '-ENQ'),
        'the client and the enquiry are shown beside the number: ' . pc_ref_words(pc_get($nid)));
    $g = $drive($MM, 'project-costing', 'GET', ['id' => $nid], [], [(string) $n['code'], 'CMN Client ' . $tag]);
    t_ok(!empty($g['has'][(string) $n['code']]) && !empty($g['has']['CMN Client ' . $tag]), 'the costing page shows the number with the client beside it');
    $p = $drive($MM, 'project-costing-print', 'GET', ['id' => $nid], [], [(string) $n['code'], 'CMN Client ' . $tag]);
    t_ok(!empty($p['has'][(string) $n['code']]) && !empty($p['has']['CMN Client ' . $tag]), '…and so does the printed sheet');
    t_eq(pc_code_for('PC-00012', 1), 'PC-00012 Rev 01', 'a costing made before keeps its old number; its first revision is PC-00012 Rev 01');
    t_eq(pc_base_of(['code' => 'PC-00012', 'base_code' => '']), 'PC-00012', '…and its number without revision is the old number itself');
} finally {
    foreach ($madeC as $id) {
        $pdo->prepare("DELETE FROM activities WHERE entity_kind='PROJECT_COSTING' AND entity_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM project_costing_approvals WHERE costing_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM project_costing_lines WHERE costing_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM project_costings WHERE id=?")->execute([$id]);
    }
    foreach ($madeInq as $id) { $pdo->prepare("DELETE FROM activities WHERE entity_kind='INQUIRY' AND entity_id=?")->execute([$id]); $pdo->prepare("DELETE FROM crm_inquiries WHERE id=?")->execute([$id]); }
    $pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$cli]);
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
}
