<?php
// ============================================================================
//  v51.1 — the owner's decisions of 15 Nov 2026:
//   1. "Create quotation should ask revise or new" — when the costing's enquiry, lead or deal
//      (or else the same client) already has a live quotation, "Create quotation" asks: revise
//      it (the next revision, at the costing's prices) or make a separate new quotation.
//   2. "History tab must have detail on who created the quote" — every new quotation and every
//      revision writes who made it, when and from what; the revision table shows Created by.
// ============================================================================
t_section('v51.1 — Create quotation: revise or new; who created each quotation');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'GET', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? []));
$pdo = db(); pc_migrate(); if (function_exists('crm_migrate')) crm_migrate();
$tag = 'RN' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute([$tag . ' Office']);
$off = (int) $pdo->lastInsertId();
$mk = function ($un, $role) use ($pdo, $off) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$MM = $mk(strtolower($tag) . '_mm', 'MARKETING_MANAGER');
$mmName = user_name(ops_one("SELECT * FROM users WHERE id=?", [$MM]));
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES (?,?,1,'ACTIVE')")->execute([$tag . ' Client', $tag . ' Client']);
$cli = (int) $pdo->lastInsertId();
$pdo->exec("DELETE FROM quote_approval_rules");
$oldPol = setting_get('quote_unmatched_approval', ''); setting_set('quote_unmatched_approval', 'none');
$enq = function ($subject) use ($pdo, $cli, $off, $tag) {
    static $n = 0; $n++;
    $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, client_id, client_name, subject, status, office_id, created_at) VALUES (?,?,?,?,'OPEN',?,?)")
        ->execute([$tag . '-ENQ' . $n, $cli, $tag . ' Client', $subject, $off, date('c')]);
    return (int) $pdo->lastInsertId();
};
//  An approved costing with roles, tagged to an enquiry (approval set directly: the approval chain is tested elsewhere).
$costing = function ($inq, array $roles) use ($pdo, $cli, $off, $MM, $tag) {
    static $n = 0; $n++;
    $pdo->prepare("INSERT INTO project_costings (code, base_code, rev, title, client_id, office_id, basis, overhead_pct, insurance_pct, baddebt_pct, contingency_pct, negotiation_pct,
                   status, inquiry_id, created_by, created_by_user_id, created_at) VALUES (?,?,0,?,?,?,'LONG',0,0,0,0,0,'APPROVED',?,?,?,?)")
        ->execute([$tag . '/PC/' . $n . ' Rev 00', $tag . '/PC/' . $n, 'Site team ' . $n, $cli, $off, $inq, 'x', $MM, date('c')]);
    $id = (int) $pdo->lastInsertId();
    foreach ($roles as $i => [$role, $pay, $qty]) $pdo->prepare("INSERT INTO project_costing_lines (costing_id, seq, role_label, heads, boq_qty, target_margin) VALUES (?,?,?,?,?,20)")
        ->execute([$id, $i + 1, $role, json_encode(['PERSONNEL' => $pay]), $qty]);
    pc_restamp($id);
    return $id;
};
$quote = function ($inq, $status, array $lines) use ($pdo, $cli, $off, $MM, $tag) {
    static $n = 0; $n++;
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, client_id, client_name, office_id, inquiry_id, subject, gst_pct, owner_id, created_by, created_at)
                   VALUES (?,0,1,?,?,?,?,?, 'Site team', 18, ?, 'sales.probe', ?)")->execute([$tag . '-Q' . $n, $status, $cli, $tag . ' Client', $off, $inq, $MM, date('c')]);
    $id = (int) $pdo->lastInsertId();
    foreach ($lines as $i => [$d, $qty, $unit, $rate]) $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, description, order_type, qty, unit, rate, amount) VALUES (?,?,?,'LINE',?,?,?,?)")
        ->execute([$id, $i + 1, $d, $qty, $unit, $rate, $qty * $rate]);
    crm_quote_recalc($id);
    return $id;
};
$hist = fn($qid) => implode(' || ', array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=? ORDER BY id",
                        [(int)(ops_val("SELECT COALESCE(NULLIF(parent_id,0), id) FROM quotations WHERE id=?", [$qid]) ?: $qid)]) ?: [], 'summary'));
try {
    // ---- 1. Revise or new ----------------------------------------------------------------------
    $e1 = $enq('Site engineers, 12 months');
    $q1 = $quote($e1, 'SENT', [['Site Engineer', 12, 'MANMONTH', 45000], ['Travel to site', 4, 'NOS', 5000]]);
    $won = $quote($e1, 'ACCEPTED', [['Old job', 1, 'MANMONTH', 1000]]);
    $c1 = $costing($e1, [['Site Engineer', 40000, 12], ['Supervisor', 50000, 6]]);
    $cands = pc_quote_candidates(pc_get($c1));
    $ids = array_map(fn($c) => (int)$c['id'], $cands);
    t_ok(in_array($q1, $ids, true), 'a live quotation of the same enquiry is offered for revision');
    t_ok(!in_array($won, $ids, true), 'a won quotation is not offered');
    t_eq($cands[0]['match'] ?? '', 'inquiry', '…the same-enquiry one comes first');
    $pg = $drive($MM, 'project-costing', 'GET', ['id' => $c1], [], ['live quotation already exists', 'Revise ' . $tag . '-Q1', 'recommended', 'Make a separate new quotation']);
    t_ok(!empty($pg['has']['live quotation already exists']) && !empty($pg['has']['Revise ' . $tag . '-Q1']) && !empty($pg['has']['recommended']) && !empty($pg['has']['Make a separate new quotation']),
         'the costing asks: revise ' . $tag . '-Q1 (recommended) or make a separate new quotation');
    $before = (int) ops_val("SELECT COUNT(*) FROM quotations");
    $r = $drive($MM, 'project-costing-to-quote', 'POST', [], ['id' => $c1]);
    t_ok((int) ops_val("SELECT COUNT(*) FROM quotations") === $before && strpos($said($r), 'Choose first') !== false, 'pressing Create quotation without choosing makes nothing: ' . $said($r));
    $r = $drive($MM, 'project-costing-to-quote', 'POST', [], ['id' => $c1, 'make' => 'rev:' . $q1]);
    $nq = (int) ops_val("SELECT id FROM quotations WHERE quote_no=? AND is_current=1", [$tag . '-Q1']);
    t_ok($nq && $nq !== $q1, '"Revise" makes the next revision of the same quotation: ' . $said($r));
    t_eq([(int) ops_val("SELECT rev FROM quotations WHERE id=?", [$nq]), (string) ops_val("SELECT status FROM quotations WHERE id=?", [$nq])], [1, 'DRAFT'], '…revision 1, a draft');
    t_eq((int) ops_val("SELECT is_current FROM quotations WHERE id=?", [$q1]), 0, '…the earlier revision stays, no longer current');
    t_eq((int) pc_get($c1)['quote_id'], $nq, '…and the costing is linked to the new revision');
    $lines = ops_all("SELECT description, qty, unit, rate FROM quote_lines WHERE quote_id=? ORDER BY line_no", [$nq]);
    t_eq(array_column($lines, 'description'), ['Site Engineer', 'Supervisor', 'Travel to site'], 'one line per role from the costing, and the non-manpower line kept');
    t_eq((float) $lines[0]['rate'], 50000.0, '…at the costing\'s rate (₹40,000 at 20% margin → ₹50,000)');
    t_eq((int) ops_val("SELECT COUNT(*) FROM quote_lines WHERE quote_id=?", [$q1]), 2, 'the earlier revision keeps its own lines');
    $h = $hist($nq);
    t_ok(strpos($h, 'Revision 1 created by ' . $mmName) !== false, 'history: "Revision 1 created by ' . $mmName . ' …"');
    t_ok(strpos($h, 'Priced from costing') !== false && strpos($h, '1 other line kept as before') !== false, 'history: priced from the costing, and the kept line said');

    //  A revision exactly at the costing's prices needs no second approval — and says the true reason.
    $e2 = $enq('Inspectors');
    $q2 = $quote($e2, 'SENT', [['Inspector', 6, 'MANMONTH', 30000]]);
    $c2 = $costing($e2, [['Inspector', 24000, 6]]);
    $drive($MM, 'project-costing-to-quote', 'POST', [], ['id' => $c2, 'make' => 'rev:' . $q2]);
    $nq2 = (int) ops_val("SELECT id FROM quotations WHERE quote_no=(SELECT quote_no FROM quotations WHERE id=?) AND is_current=1", [$q2]);
    t_ok($nq2 !== $q2 && (int) pc_get($c2)['quote_id'] === $nq2, 'arranged: the inspectors\' quotation revised from its costing');
    $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,active,created_at) VALUES ('L1','ANY','',0,0,1,'BRANCH_MANAGER',1,?)")->execute([date('c')]);
    t_ok(!crm_quote_needs_approval(crm_quote_get($nq2)), 'a revision at the approved costing\'s prices needs no second approval');
    $g = $drive($MM, 'quote', 'GET', ['id' => $nq2], [], ['No approval needed — it is at the prices of its approved project costing', 'your approval rules do not cover this value']);
    t_ok(!empty($g['has']['No approval needed — it is at the prices of its approved project costing']) && empty($g['has']['your approval rules do not cover this value']),
         'its page gives the true reason, not "your approval rules do not cover this value"');
    $pdo->exec("DELETE FROM quote_approval_rules");

    //  "Make a separate new quotation" — written down beside the live ones.
    $e3 = $enq('Another job');
    $c3 = $costing($e3, [['Welder', 30000, 3]]);
    $cands3 = pc_quote_candidates(pc_get($c3));
    t_ok(in_array('client', array_column($cands3, 'match'), true), 'with no live quotation on its own enquiry, the client\'s live ones are offered (marked "same client")');
    $r = $drive($MM, 'project-costing-to-quote', 'POST', [], ['id' => $c3, 'make' => 'new']);
    $nq3 = (int) pc_get($c3)['quote_id'];
    t_ok($nq3 > 0 && (int) ops_val("SELECT rev FROM quotations WHERE id=?", [$nq3]) === 0, '"Make a separate new quotation" makes a new one: ' . $said($r));
    t_ok(strpos($hist($nq3), 'Made as a separate ' . Tl('quote') . ' by ' . $mmName) !== false, '…and its history names the live quotations it stands beside');
    t_ok(strpos($hist($nq3), 'Created by ' . $mmName) !== false && strpos($hist($nq3), 'from costing') !== false, '…and who created it, from which costing');

    //  A costing with nothing live beside it: no question.
    $pdo->prepare("UPDATE quotations SET status='LOST' WHERE client_id=? AND is_current=1")->execute([$cli]);
    $e4 = $enq('Fresh job');
    $c4 = $costing($e4, [['Fitter', 30000, 3]]);
    t_eq(pc_quote_candidates(pc_get($c4)), [], 'nothing live for the job or the client: nothing to ask');
    $r = $drive($MM, 'project-costing-to-quote', 'POST', [], ['id' => $c4]);
    t_ok((int) pc_get($c4)['quote_id'] > 0, '…"Create quotation" makes the quotation at once, as before: ' . $said($r));

    // ---- 2. Who created it ---------------------------------------------------------------------
    $r = $drive($MM, 'quote-new', 'POST', [], ['client_id' => $cli, 'subject' => 'Visit', 'office_id' => $off, 'validity_days' => 30, 'gst_pct' => 18, 'inquiry_id' => $e4,
        'l_desc' => ['Inspection visit'], 'l_qty' => ['2'], 'l_unit' => ['MANDAY'], 'l_rate' => ['9000']]);
    $nq5 = (int) ops_val("SELECT MAX(id) FROM quotations WHERE subject='Visit'");
    t_ok($nq5 > 0 && strpos($hist($nq5), 'Created by ' . $mmName) !== false && strpos($hist($nq5), 'from ' . Tl('inquiry') . ' ' . $tag . '-ENQ4') !== false,
         'a quotation made on the screen: "Created by ' . $mmName . ' on … — from enquiry …" (' . $said($r) . ')');
    $g = $drive($MM, 'quote', 'GET', ['id' => $nq], [], ['<th>Created by</th>', $mmName]);
    t_ok(!empty($g['has']['<th>Created by</th>']) && !empty($g['has'][$mmName]), 'the revision table shows who created each revision');
    $src = (string) file_get_contents($root . '/lib/opportunities.php');
    t_ok(substr_count($src, "crm_log_created(\$qid, 'from deal '") === 2, 'both ways a deal makes a quotation write who created it');
    t_ok(strpos((string) file_get_contents($root . '/lib/crm.php'), "crm_log_created(\$id, 'registered from '") !== false, 'a quotation lodged from a portal writes who registered it');
} finally {
    setting_set('quote_unmatched_approval', $oldPol);
    $pdo->exec("DELETE FROM quote_approval_rules");
}
