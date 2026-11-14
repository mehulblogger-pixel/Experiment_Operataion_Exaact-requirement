<?php
// ============================================================================
//  Owner, 14 Nov 2026 — SAL-186 findings and three decisions. Driven as real logins.
//
//  SAL-186: "When from Project costing we click on quote in breadcrumb … it shows page not found";
//           "it opened a revision … there is no costing tab to revise"; "Revision is really very very
//           complex … if there is any change in costing due to revision same shall be opened from the
//           same screen no back and forth."
//  Decisions: the enquiry's Quote button by status; "The assigned person and the people they report
//           to can work on it, others can view" (option C); the coordinator's newly-opened list —
//           "Go for C & D together" (from the start date to the end date; put aside until a date).
// ============================================================================
t_section('SAL-186 — the breadcrumb keeps the record you came from');

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

$pdo = db(); crm_migrate(); pc_migrate(); if (function_exists('contracts_migrate')) contracts_migrate();
$tag = 's186' . getmypid();
$pdo->prepare("INSERT INTO offices (name, code, is_active) VALUES (?,?,1)")->execute(['S186 Office ' . $tag, 'SOT']);
$off = (int) $pdo->lastInsertId();
$mkU = function ($first, $role, $reportsTo = null) use ($pdo, $off, $tag) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id, reports_to_id) VALUES (?,?,?,?,?,1,0,?,?)")
        ->execute([$tag . '_' . strtolower($first), $first, 'S186', '', $role, $off, $reportsTo]);
    return (int) $pdo->lastInsertId();
};
$MM  = $mkU('Meera', 'MARKETING_MANAGER');
$MEX = $mkU('Mohan', 'MARKETING_EXECUTIVE', $MM);
$BDM = $mkU('Bala', 'BUSINESS_DEV_MANAGER');
$CO  = $mkU('Chetan', 'COORDINATOR');
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, created_at) VALUES (?,?,1,?)")->execute(['S186 Client ' . $tag, 'S186 Client ' . $tag, date('c')]);
$cli = (int) $pdo->lastInsertId();
$madeQ = []; $madeC = []; $madeI = []; $madeK = [];

try {
    //  The arrival step keeps its ?id= — it was the cause of "Not found".
    t_as_admin();
    $hostWas = $_SERVER['HTTP_HOST'] ?? null; $refWas = $_SERVER['HTTP_REFERER'] ?? null;
    $_SERVER['HTTP_HOST'] = 'localhost'; $_SERVER['HTTP_REFERER'] = 'http://localhost/quote?id=11';
    $back = nav_back('project-costing');
    $trail = array_column(nav_trail('project-costing'), 'url');
    $_SERVER['HTTP_REFERER'] = 'http://localhost/quote?id=11"><script>';
    $bad = nav_back('project-costing');
    if ($hostWas === null) unset($_SERVER['HTTP_HOST']); else $_SERVER['HTTP_HOST'] = $hostWas;
    if ($refWas === null) unset($_SERVER['HTTP_REFERER']); else $_SERVER['HTTP_REFERER'] = $refWas;
    t_eq($back, '/quote?id=11', 'arriving at a costing from quotation 11: "← Quote" goes back to /quote?id=11, not /quote ("Not found")');
    t_ok(in_array('/quote?id=11', $trail, true), '…and so does the breadcrumb step');
    t_ok(strpos($bad, '<') === false && strpos($bad, '"') === false, 'an odd address in the referrer is never carried into a link: ' . $bad);

    t_section('SAL-186 — a revision has its costing on the same screen');
    $qno = strtoupper($tag) . '-Q';
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, client_name, contact_email, subject, status, office_id, owner_id, created_at, sent_at) VALUES (?,0,1,?,?,'c@example.test','Site team','SENT',?,?,?,?)")
        ->execute([$qno, $cli, 'S186 Client ' . $tag, $off, $MM, date('c'), date('c')]);
    $q0 = (int) $pdo->lastInsertId(); $madeQ[] = $q0;
    $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, description, qty, unit, rate, amount) VALUES (?,1,'Site Engineer',12,'MANMONTH',60000,720000)")->execute([$q0]);
    $pdo->prepare("INSERT INTO project_costings (code, base_code, rev, title, basis, status, overhead_pct, insurance_pct, baddebt_pct, contingency_pct, negotiation_pct, office_id, client_id, quote_id, created_by, created_by_user_id, created_at)
                   VALUES (?,?,0,'Site team','LONG','APPROVED',0,0,0,0,0,?,?,?,'x',?,?)")->execute([$tag . '-PC Rev 00', $tag . '-PC', $off, $cli, $q0, $BDM, date('c')]);
    $c0 = (int) $pdo->lastInsertId(); $madeC[] = $c0;
    $pdo->prepare("INSERT INTO project_costing_lines (costing_id, seq, role_label, heads, boq_qty, target_margin, basis) VALUES (?,1,'Site Engineer',?,12,20,'LONG')")->execute([$c0, json_encode(['PERSONNEL' => 45000])]);
    $r = $drive($MM, 'quote-revise', 'POST', ['id' => $q0], ['summary' => 'Client asked for two more engineers']);
    $q1 = (int) ops_val("SELECT id FROM quotations WHERE quote_no=? AND rev=1", [$qno]); $madeQ[] = $q1;
    $c1 = (int) ops_val("SELECT id FROM project_costings WHERE quote_id=?", [$q1]); if ($c1) $madeC[] = $c1;
    t_ok($q1 > 0 && $c1 > 0 && stripos($said($r), 'Costing tab here') !== false, 'revised: the message points to the Costing tab on the same screen — ' . $said($r));
    $g = $drive($MM, 'quote-edit', 'GET', ['id' => $q1], [], ['data-tab="Costing"', $tag . '-PC Rev 01', '✎ Change the costing', 'embed_stay=1', 'data-embed-keep', 'Take the costing\'s prices into the line items']);
    foreach (['data-tab="Costing"', $tag . '-PC Rev 01', '✎ Change the costing', 'embed_stay=1', 'data-embed-keep', 'Take the costing\'s prices into the line items'] as $n)
        t_ok(!empty($g['has'][$n]), 'the revision\'s edit screen has: ' . $n);
    $pdo->prepare("UPDATE project_costing_lines SET boq_qty=18 WHERE costing_id=?")->execute([$c1]);
    $j = $drive($MM, 'quote-costing-lines', 'GET', ['id' => $q1], [], ['"ok":true', '"desc":"Site Engineer"', '"qty":18']);
    t_ok(!empty($j['has']['"ok":true']) && !empty($j['has']['"desc":"Site Engineer"']) && !empty($j['has']['"qty":18']), '"Take the costing\'s prices" reads the revision\'s own costing (18 man-months now)');
    $card = $drive($MM, 'quote-costing-card', 'GET', ['id' => $q1], [], [$tag . '-PC Rev 01', 'id="qe-costing-box"']);
    t_ok(!empty($card['has'][$tag . '-PC Rev 01']) && !empty($card['has']['id="qe-costing-box"']), 'after the costing popup closes, the tab is drawn again from the costing');
    $em = $drive($MM, 'project-costing', 'GET', ['id' => $c1, 'embed' => '1', 'embed_stay' => '1'], [], ['name="_embed_stay"', 'embed_stay=1']);
    t_ok(!empty($em['has']['name="_embed_stay"']), 'inside the popup every save stays in the popup (its forms carry the "stay" mark)');
    t_eq(array_column(pc_quote_line_rows(pc_get($c1)), 'qty'), [18.0], 'the costing\'s lines are the same rule "Create quotation" uses');

    t_section('Enquiry register — the Quote button follows where the enquiry stands');
    $mkI = function ($st, $assigned = null) use ($pdo, $off, $cli, $tag, &$madeI) {
        $pdo->prepare("INSERT INTO crm_inquiries (inquiry_no, client_id, client_name, subject, status, office_id, received_date, assigned_to, created_at) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([strtoupper($tag) . '-' . $st . count($madeI), $cli, 'S186 Client ' . $tag, $tag . ' ' . $st, $st, $off, date('Y-m-d'), $assigned, date('c')]);
        $id = (int) $pdo->lastInsertId(); $madeI[] = $id; return $id;
    };
    $mkIQ = function ($inq, $st) use ($pdo, $off, $cli, $tag, &$madeQ) {
        $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, inquiry_id, client_id, client_name, subject, status, office_id, created_at) VALUES (?,0,1,?,?,?,'x',?,?,?)")
            ->execute([strtoupper($tag) . '-IQ' . $inq, $inq, $cli, 'S186 Client ' . $tag, $st, $off, date('c')]);
        $id = (int) $pdo->lastInsertId(); $madeQ[] = $id; return $id;
    };
    $iOpen = $mkI('OPEN'); $iQ = $mkI('OPEN'); $qQ = $mkIQ($iQ, 'SENT'); $iW = $mkI('OPEN'); $qW = $mkIQ($iW, 'ACCEPTED');
    $iL = $mkI('OPEN'); $qL = $mkIQ($iL, 'LOST'); $iD = $mkI('DROPPED');
    foreach ([$iQ, $iW, $iL] as $x) crm_inquiry_sync($x);
    $n = ['href="/quote-new?inquiry=' . $iOpen . '">Quote<', 'href="/quote?id=' . $qQ . '"', 'href="/quote-new?inquiry=' . $iQ . '"', 'href="/quote?id=' . $qW . '"',
          'href="/quote-new?inquiry=' . $iW . '"', 'href="/quote-new?inquiry=' . $iL . '">Quote again<', 'href="/quote-new?inquiry=' . $iD . '"'];
    $g = $drive($MM, 'inquiries', 'GET', ['q' => $tag], [], $n);
    t_ok(!empty($g['has'][$n[0]]), 'Open: "Quote"');
    t_ok(!empty($g['has'][$n[1]]) && !empty($g['has'][$n[2]]), 'Quoted: "Open Q-…" and a small "+ another"');
    t_ok(!empty($g['has'][$n[3]]) && empty($g['has'][$n[4]]), 'Won: "Open Q-…" only — no new quotation');
    t_ok(!empty($g['has'][$n[5]]), 'Lost: "Quote again"');
    t_ok(empty($g['has'][$n[6]]), 'Dropped: nothing');

    t_section('Assigned work — the assignee and the people above them work on it; others view (option C)');
    $iA = $mkI('OPEN', $MEX);
    $g = $drive($BDM, 'inquiry-edit', 'GET', ['id' => $iA], [], ['is assigned to Mohan S186', 'Save inquiry']);
    t_ok(!empty($g['has']['is assigned to Mohan S186']) && empty($g['has']['Save inquiry']), 'a BDM outside Mohan\'s reporting line sees it, with who works on it, and no Save');
    $r = $drive($BDM, 'inquiry-edit', 'POST', ['id' => $iA], ['client_name' => 'x', 'subject' => 'changed by Bala', 'received_date' => date('Y-m-d'), 'sbu' => 'IND', 'source' => 'EMAIL', 'assigned_to' => $MEX]);
    t_ok((string) ops_val("SELECT subject FROM crm_inquiries WHERE id=?", [$iA]) !== 'changed by Bala', '…a forced save is refused: ' . $said($r));
    $r = $drive($BDM, 'quote-new', 'GET', ['inquiry' => $iA]);
    t_ok(stripos($said($r), 'is assigned to Mohan') !== false, '…and the BDM cannot quote it: ' . $said($r));
    $g = $drive($MM, 'inquiry-edit', 'GET', ['id' => $iA], [], ['Save inquiry']);
    t_ok(!empty($g['has']['Save inquiry']), 'Meera, whom Mohan reports to, may work on it');
    $r = $drive($MM, 'quote-new', 'POST', [], ['inquiry_id' => $iA, 'client_id' => $cli, 'subject' => 'From assigned enquiry', 'office_id' => $off, 'validity_days' => 30, 'gst_pct' => 18,
                                              'form_once' => bin2hex(random_bytes(6)), 'l_desc' => ['Inspection'], 'l_qty' => ['1'], 'l_unit' => ['MANDAY'], 'l_rate' => ['1000']]);
    $qa = (int) ops_val("SELECT id FROM quotations WHERE inquiry_id=? ORDER BY id DESC LIMIT 1", [$iA]); if ($qa) $madeQ[] = $qa;
    t_eq((int) ops_val("SELECT owner_id FROM quotations WHERE id=?", [$qa]), $MEX, 'the quotation Meera makes from it is assigned to Mohan — so the same people work on it: ' . $said($r));
    $g = $drive($BDM, 'quote', 'GET', ['id' => $qa], [], ['is assigned to Mohan S186', '>Submit for approval<']);
    t_ok(!empty($g['has']['is assigned to Mohan S186']) && empty($g['has']['>Submit for approval<']), 'the BDM sees that quotation, with who works on it — no Submit');
    $r = $drive($BDM, 'quote-edit', 'GET', ['id' => $qa]);
    t_ok(stripos($said($r), 'is assigned to Mohan') !== false, '…and cannot open it for editing: ' . $said($r));
    $g = $drive($MEX, 'quote-edit', 'GET', ['id' => $qa], [], ['Edit — ', 'name="l_desc[]"']);
    t_ok(!empty($g['has']['Edit — ']) && !empty($g['has']['name="l_desc[]"']) && stripos($said($g), 'is assigned to') === false, 'Mohan, the assignee, opens it for editing');
    t_ok((function () use ($MEX, $qa) { $_SESSION['uid'] = $MEX; current_user(true); ua(true); $ok = crm_quote_can_work(crm_quote_get($qa)); unset($_SESSION['uid']); current_user(true); ua(true); return $ok; })(),
        '…and may work on it (submit, revise) — the same rule the buttons and the server ask');

    t_section('Coordinator — newly opened contracts from their start date to their end date, and put aside (C + D)');
    $mkK = function ($start, $end) use ($pdo, $cli, $off, $tag, &$madeK) {
        $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, start_date, end_date, is_active, open_status, opened_at, branch_id) VALUES (?,?,?,?,?,1,'OPEN',?,?)")
            ->execute([$cli, strtoupper($tag) . '/C/' . count($madeK), 'k', $start, $end, date('c'), $off]);
        $id = (int) $pdo->lastInsertId(); $madeK[] = $id; return $id;
    };
    $kNow = $mkK(date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('+300 days')));
    $kLater = $mkK(date('Y-m-d', strtotime('+40 days')), date('Y-m-d', strtotime('+400 days')));
    $kOver = $mkK(date('Y-m-d', strtotime('-400 days')), date('Y-m-d', strtotime('-1 day')));
    $kOpenEnded = $mkK('', '');
    $ids = function ($w) { t_as_admin(); return array_map(fn($r) => (int) $r['id'], contracts_awaiting_first_call($w)); };
    $now = $ids('now');
    t_ok(in_array($kNow, $now, true) && in_array($kOpenEnded, $now, true), 'started (or no dates): on the list');
    t_ok(!in_array($kLater, $now, true) && in_array($kLater, $ids('later'), true), 'starting in 40 days: not on the list yet — shown under "Starting later"');
    t_ok(!in_array($kOver, $now, true), 'ended yesterday with no work: off the list');
    $r = $drive($CO, 'raise-call', 'POST', [], ['do' => 'snooze', 'contract_id' => $kNow, 'until' => date('Y-m-d', strtotime('+10 days')), 'note' => 'Work after the shutdown']);
    t_ok(!in_array($kNow, $ids('now'), true) && in_array($kNow, $ids('snoozed'), true), 'the coordinator puts it aside for 10 days: ' . $said($r));
    t_eq((string) ops_val("SELECT fresh_snooze_note FROM partner_contracts WHERE id=?", [$kNow]), 'Work after the shutdown', '…with the note kept');
    $r = $drive($CO, 'raise-call', 'POST', [], ['do' => 'snooze', 'contract_id' => $kOpenEnded, 'until' => date('Y-m-d')]);
    t_ok(stripos($said($r), 'after today') !== false, 'a date that is not after today is refused: ' . $said($r));
    $r = $drive($CO, 'raise-call', 'POST', [], ['do' => 'snooze', 'contract_id' => $kNow, 'until' => date('Y-m-d', strtotime('+500 days'))]);
    t_ok(stripos($said($r), 'contract ends on') !== false, 'a date after the contract ends is refused: ' . $said($r));
    $g = $drive($CO, 'raise-call', 'GET', [], [], ['Put aside', 'Work after the shutdown', 'Starting later', 'No work yet — remind me later']);
    t_ok(!empty($g['has']['Put aside']) && !empty($g['has']['Work after the shutdown']) && !empty($g['has']['Starting later']) && !empty($g['has']['No work yet — remind me later']),
        'the coordinator\'s screen shows the list, "Starting later", "Put aside" (with the note) and the snooze on each');
    $drive($CO, 'raise-call', 'POST', [], ['do' => 'snooze', 'contract_id' => $kNow, 'until' => '']);
    t_ok(in_array($kNow, $ids('now'), true), '"Back on the list now" puts it straight back');
    $pdo->prepare("UPDATE partner_contracts SET fresh_snooze_until=? WHERE id=?")->execute([date('Y-m-d'), $kNow]);
    t_ok(in_array($kNow, $ids('now'), true), 'on the day it was put aside until, it is back on the list by itself');
} finally {
    foreach ($madeK as $id) $pdo->prepare("DELETE FROM partner_contracts WHERE id=?")->execute([$id]);
    foreach ($madeC as $id) { foreach (['project_costing_lines', 'project_costing_approvals'] as $t) $pdo->prepare("DELETE FROM $t WHERE costing_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM activities WHERE entity_kind='PROJECT_COSTING' AND entity_id=?")->execute([$id]); $pdo->prepare("DELETE FROM project_costings WHERE id=?")->execute([$id]); }
    foreach ($madeQ as $id) { foreach (['quote_lines', 'quote_revisions', 'quote_approvals', 'quote_locations'] as $t) try { $pdo->prepare("DELETE FROM $t WHERE quote_id=?")->execute([$id]); } catch (Throwable $e) {}
        $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]); }
    foreach ($madeI as $id) { $pdo->prepare("DELETE FROM activities WHERE entity_kind='INQUIRY' AND entity_id=?")->execute([$id]); $pdo->prepare("DELETE FROM crm_inquiries WHERE id=?")->execute([$id]); }
    $pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$cli]);
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
}
