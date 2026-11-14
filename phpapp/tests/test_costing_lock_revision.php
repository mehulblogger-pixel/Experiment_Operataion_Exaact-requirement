<?php
// ============================================================================
//  A costing is locked once its quotation goes for approval; a revision can get
//  its own editable copy; an enquiry cannot be received in the future.
//
//  Owner, 14 Nov 2026: "Even when the quote is approved still costing can be
//  unlocked" — answered "Lock from: quote submitted for approval" and "Revision
//  makes a new costing"; then "once the quote is revised for what so ever reason
//  and is required to revise the costing it shall be editable".
//  Owner, 13 Nov 2026 (test step 4): "Inquiry received date in future must show a
//  warning sign and shall not be recorded."
//  Driven as real logins through the production dispatcher and views.
// ============================================================================
t_section('Costing lock — from the moment its quotation goes for approval');

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

$pdo = db(); pc_migrate(); crm_migrate();
$tag = 'clr' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['CLR Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$mkU = function ($first, $role) use ($pdo, $off, $tag) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
        ->execute([$tag . '_' . strtolower($first), $first, 'Clr', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$MM = $mkU('Meera', 'MARKETING_MANAGER'); $MEX = $mkU('Mohan', 'MARKETING_EXECUTIVE');
$qno = strtoupper($tag) . '-Q';
$pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_name, subject, status, office_id, created_at) VALUES (?,0,1,'CLR Client','Site team','DRAFT',?,?)")
    ->execute([$qno, $off, date('c')]);
$q0 = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO quote_lines (quote_id, description, qty, unit, rate, amount) VALUES (?, 'Site engineer', 12, 'MANDAY', 50000, 600000)")->execute([$q0]);
$pdo->prepare("INSERT INTO project_costings (code, title, basis, status, overhead_pct, insurance_pct, baddebt_pct, contingency_pct, negotiation_pct, office_id, quote_id, created_by, created_by_user_id, approved_by, approved_at, created_at, updated_at)
               VALUES (?, 'Site team', 'LONG', 'APPROVED', 8, 0.5, 0, 0.5, 0, ?, ?, 'Meera Clr', ?, 'Someone Else', ?, ?, ?)")
    ->execute([strtoupper($tag) . '-PC', $off, $q0, $MM, date('c'), date('c'), date('c')]);
$c0 = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO project_costing_lines (costing_id, seq, role_label, heads, boq_qty, target_margin) VALUES (?,1,'Site Engineer',?,12,22)")->execute([$c0, json_encode(['PERSONNEL' => 45000])]);
$setQ = fn($id, $st, $sent = '') => $pdo->prepare("UPDATE quotations SET status=?, sent_at=? WHERE id=?")->execute([$st, $sent, $id]);
$madeCostings = [$c0]; $madeQuotes = [$q0]; $madeInq = [];

try {
    t_eq(pc_quote_lock_reason(pc_get($c0)), '', 'its quotation is still a draft: the costing is not held by it');
    t_eq(pc_transition_problem(pc_get($c0), 'project-costing-reopen'), '', '…so an approved costing may still be reopened (nobody has priced from it yet)');
    //  Found walking the practice copy: the copy button showed here and made a needless copy.
    t_ok(stripos(pc_revise_problem($q0), 'Reopen as draft') !== false, '…and no copy is offered for it — "Reopen as draft" is the way: ' . pc_revise_problem($q0));
    $g = $drive($MM, 'quote', 'GET', ['id' => $q0], [], ['Change the costing for this revision']);
    t_ok(empty($g['has']['Change the costing for this revision']), 'the draft quotation\'s page does not offer "Change the costing for this revision"');

    foreach ([['PENDING_APPROVAL', 'has gone for approval'], ['APPROVED', 'has been approved']] as [$st, $words]) {
        $setQ($q0, $st);
        $r = $drive($MM, 'project-costing-reopen', 'POST', [], ['id' => $c0]);
        t_ok(pc_get($c0)['status'] === 'APPROVED' && stripos($said($r), 'Not reopened') !== false && stripos($said($r), $words) !== false,
            'quotation ' . strtolower(str_replace('_', ' ', $st)) . ': "Reopen as draft" is refused — ' . $said($r));
        $g = $drive($MM, 'project-costing', 'GET', ['id' => $c0], [], ['action="/project-costing-reopen"', $words]);
        t_ok(empty($g['has']['action="/project-costing-reopen"']) && !empty($g['has'][$words]), '…the costing page offers no "Reopen as draft" and says why');
    }
    //  A DRAFT costing whose quotation has gone for approval cannot be edited either.
    $pdo->prepare("UPDATE project_costings SET status='DRAFT' WHERE id=?")->execute([$c0]);
    t_ok(pc_locked(pc_get($c0)), 'a draft costing is locked too once its quotation has gone for approval — its roles cannot be changed');
    $pdo->prepare("UPDATE project_costings SET status='APPROVED' WHERE id=?")->execute([$c0]);
    $r = $drive($MM, 'project-costing-attach', 'POST', [], ['costing_id' => $c0, 'quote_id' => 0]);
    t_ok((int) pc_get($c0)['quote_id'] === $q0, 'and it cannot be unlinked from its quotation: ' . $said($r));

    t_section('Costing lock — a revision gets its own costing automatically, as the next Rev');
    //  Owner, 14 Nov 2026: "Yes" (made automatically) and "when the costing is revised it must have a
    //  revision number starting from Rev. 01, 02 etc."
    $setQ($q0, 'SENT', date('c'));
    $before = (int) ops_val("SELECT COUNT(*) FROM project_costings WHERE quote_id IN (SELECT id FROM quotations WHERE quote_no=?)", [$qno]);
    $r = $drive($MM, 'quote-revise', 'POST', ['id' => $q0], ['summary' => 'Address corrected']);
    $q1 = (int) ops_val("SELECT id FROM quotations WHERE quote_no=? AND rev=1", [$qno]); $madeQuotes[] = $q1;
    t_ok($q1 > 0, 'a revision is raised: ' . $said($r));
    $c1 = (int) ops_val("SELECT id FROM project_costings WHERE quote_id=? ORDER BY id DESC LIMIT 1", [$q1]); if ($c1) $madeCostings[] = $c1;
    t_eq((int) ops_val("SELECT COUNT(*) FROM project_costings WHERE quote_id IN (SELECT id FROM quotations WHERE quote_no=?)", [$qno]), $before + 1,
        'its costing is copied at once — no button to press');
    $n1 = $c1 ? pc_get($c1) : [];
    $base0 = strtoupper($tag) . '-PC';
    t_eq([(string) ($n1['code'] ?? ''), (string) ($n1['base_code'] ?? ''), (int) ($n1['rev'] ?? -1)], [$base0 . ' Rev 01', $base0, 1],
        'the copy keeps the costing\'s number and is its Rev 01');
    t_ok(stripos($said($r), $base0 . ' Rev 01') !== false && stripos($said($r), 'submit it for approval') !== false, '…and the message names it and says what to do: ' . $said($r));
    t_ok(($n1['status'] ?? '') === 'DRAFT' && !pc_locked($n1), '…a draft that can be changed — it is not locked');
    t_eq((int) ops_val("SELECT COUNT(*) FROM project_costing_lines WHERE costing_id=?", [$c1]), 1, '…with every role carried over');
    t_eq([(float) ($n1['overhead_pct'] ?? 0), (string) ($n1['basis'] ?? '')], [8.0, 'LONG'], '…and every setting');
    t_eq((string) ($n1['title'] ?? ''), 'Site team', '…under the same title — the revision is in its number, not its name');
    t_eq((int) ($n1['created_by_user_id'] ?? 0), $MM, '…prepared by whoever revised — so they cannot approve it themselves');
    $o = pc_get($c0);
    t_eq([$o['status'], (int) $o['quote_id']], ['APPROVED', $q0], 'the original stays approved, on the revision that went out — the record of the earlier price');
    t_eq((int) (crm_quote_costing(crm_quote_get($q1))['id'] ?? 0), $c1, 'the revision is priced by its own copy');
    t_eq((int) (crm_quote_costing(crm_quote_get($q0))['id'] ?? 0), $c0, 'the earlier revision still shows its own costing');
    $trail0 = array_column(ops_all("SELECT subject FROM activities WHERE entity_kind='PROJECT_COSTING' AND entity_id=?", [$c0]) ?: [], 'subject');
    $trail1 = array_column(ops_all("SELECT subject FROM activities WHERE entity_kind='PROJECT_COSTING' AND entity_id=?", [$c1]) ?: [], 'subject');
    t_ok((bool) array_filter($trail0, fn($x) => str_contains($x, 'Copied to ')) && (bool) array_filter($trail1, fn($x) => str_contains($x, ' copied from ')),
        'both costings record the copy in their history');
    t_eq(array_map(fn($x) => (int) $x['rev'], pc_revisions(pc_get($c1))), [0, 1], 'both are listed as revisions of one costing: Rev 00 and Rev 01');
    $g1 = $drive($MM, 'project-costing', 'GET', ['id' => $c1], [], ['Revisions:', 'href="/project-costing?id=' . $c0 . '"', $base0 . ' Rev 01']);
    t_ok(!empty($g1['has']['Revisions:']) && !empty($g1['has']['href="/project-costing?id=' . $c0 . '"']) && !empty($g1['has'][$base0 . ' Rev 01']),
        'the costing page shows its number and links to the earlier revision');
    $g1 = $drive($MM, 'quote', 'GET', ['id' => $q1], [], ['Change the costing for this revision']);
    t_ok(empty($g1['has']['Change the costing for this revision']), 'the revision\'s page no longer needs "Change the costing for this revision"');
    $r = $drive($MM, 'project-costing-revise', 'POST', [], ['quote_id' => $q1]);
    t_ok(stripos($said($r), 'already editable') !== false, 'pressed anyway: its costing is already editable — no second copy');

    //  A second revision of the quotation, after the first went for approval: Rev 02.
    $setQ($q1, 'SENT', date('c')); $pdo->prepare("UPDATE project_costings SET status='APPROVED' WHERE id=?")->execute([$c1]);
    $r = $drive($MM, 'quote-revise', 'POST', ['id' => $q1], ['summary' => 'Rate change']);
    $q2 = (int) ops_val("SELECT id FROM quotations WHERE quote_no=? AND rev=2", [$qno]); $madeQuotes[] = $q2;
    $c2 = (int) ops_val("SELECT id FROM project_costings WHERE quote_id=? ORDER BY id DESC LIMIT 1", [$q2]); if ($c2) $madeCostings[] = $c2;
    t_eq((string) (pc_get($c2)['code'] ?? ''), $base0 . ' Rev 02', 'the next revision is Rev 02: ' . $said($r));
    //  A brand-new costing gets the new number: branch / PC / year / month / running number, Rev 00.
    $pdo->prepare("UPDATE offices SET code=? WHERE id=?")->execute(['CLR', $off]);
    $nb = pc_new_number($off);
    $fy = fy_label(current_fy());
    t_ok((bool) preg_match('#^CLR/PC/' . preg_quote($fy, '#') . '/' . date('m') . '/\d{4}$#', $nb), 'a new costing number reads branch/PC/year/month/number: ' . $nb);
    t_eq(pc_new_number(0) !== '' && str_starts_with(pc_new_number(0), 'GEN/PC/'), true, 'with no office it starts GEN/PC/');
    $setQ($q1, 'DRAFT'); $setQ($q2, 'DRAFT');
    $r = $drive($MM, 'project-costing-revise', 'POST', [], ['quote_id' => $q0]);
    t_ok(stripos($said($r), 'older revision') !== false, 'an older revision cannot have its costing changed: ' . $said($r));

    $setQ($q1, 'PENDING_APPROVAL');
    t_ok(pc_locked(pc_get($c1)) && stripos(pc_quote_lock_reason(pc_get($c1)), 'revision 1') !== false, 'once the revision goes for approval, its own copy locks in turn');
    t_eq(pc_quote_lock_reason(pc_get($c1)) !== '' && stripos(pc_quote_lock_reason(pc_get($c1)), '(sent ') === false, true,
        '…and it is locked by its own revision, not by the earlier one that was sent');

    t_section('Enquiry — a received date in the future is refused');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $cnt = fn() => (int) ops_val("SELECT COUNT(*) FROM crm_inquiries WHERE subject LIKE ?", [$tag . '%']);
    $post = ['client_name' => 'CLR Client', 'contact_name' => 'Ravi', 'contact_mobile' => '9800000000', 'sbu' => 'IND', 'source' => 'EMAIL', 'subject' => $tag . ' tomorrow', 'received_date' => $tomorrow];
    $r = $drive($MEX, 'inquiry-new', 'POST', [], $post, ['is in the future', 'it was not saved']);
    t_ok($cnt() === 0 && !empty($r['has']['is in the future']) && !empty($r['has']['it was not saved']), 'tomorrow\'s date: not recorded, and the form says why');
    $r = $drive($MEX, 'inquiry-new', 'POST', [], ['received_date' => '2026-02-31'] + $post, ['is not a real date']);
    t_ok($cnt() === 0 && !empty($r['has']['is not a real date']), 'an impossible date (31 February): not recorded');
    $r = $drive($MEX, 'inquiry-new', 'POST', [], ['received_date' => date('Y-m-d'), 'subject' => $tag . ' today'] + $post);
    t_eq($cnt(), 1, 'today\'s date: recorded — ' . $said($r));
    $madeInq = array_column(ops_all("SELECT id FROM crm_inquiries WHERE subject LIKE ?", [$tag . '%']) ?: [], 'id');
    $g = $drive($MEX, 'inquiry-new', 'GET', [], [], ['max="' . date('Y-m-d') . '"', 'This date is in the future']);
    t_ok(!empty($g['has']['max="' . date('Y-m-d') . '"']) && !empty($g['has']['This date is in the future']), 'the form stops the calendar at today and carries the warning sign for a future date');
} finally {
    foreach ($madeCostings as $id) {
        $pdo->prepare("DELETE FROM activities WHERE entity_kind='PROJECT_COSTING' AND entity_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM project_costing_lines WHERE costing_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM project_costings WHERE id=?")->execute([$id]);
    }
    foreach (ops_all("SELECT id FROM quotations WHERE quote_no=?", [$qno]) ?: [] as $qq) {
        $pdo->prepare("DELETE FROM quote_lines WHERE quote_id=?")->execute([(int) $qq['id']]);
        $pdo->prepare("DELETE FROM quote_revisions WHERE quote_id=?")->execute([(int) $qq['id']]);
        $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([(int) $qq['id']]);
    }
    foreach ($madeInq as $id) { $pdo->prepare("DELETE FROM activities WHERE entity_kind='INQUIRY' AND entity_id=?")->execute([(int) $id]); $pdo->prepare("DELETE FROM crm_inquiries WHERE id=?")->execute([(int) $id]); }
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
}
