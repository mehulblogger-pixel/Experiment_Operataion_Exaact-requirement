<?php
// ============================================================================
//  Project costing and the quotation made from it — the owner's answers of
//  20 Oct 2026:
//    D-11 A "Yes but can also be edited by creator with note or remark to be
//           mentioned mandatory" — a man-month quotation goes forward only from
//           an approved costing; its creator may still change its prices, with
//           a note kept in its history.
//    D-11 B "No" — the author of a costing never approves it.
//  Before: a costing could be approved by its author, from any status; a
//  quotation was made from a draft or rejected costing; nothing tied a
//  man-month quotation to a costing at all (Sales audit SA-14).
// ============================================================================
t_section('Sales — a man-month quotation stands on an approved costing');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? []));
$pdo = db();
pc_migrate();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('PC Office',1)")->execute();
$off = (int) $pdo->lastInsertId();
$mk = function ($un, $role) use ($pdo, $off) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$MM  = $mk('pc_mm',  'MARKETING_MANAGER');     // prepares costings and may approve quotations
$BD  = $mk('pc_bd',  'BUSINESS_DIRECTOR');
$BDM = $mk('pc_bdm', 'BUSINESS_DEV_MANAGER');
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('PC Client','PC Client',1,'ACTIVE')")->execute();
$cli = (int) $pdo->lastInsertId();
$pdo->exec("DELETE FROM quote_approval_rules");
setting_set('quote_unmatched_approval', 'none');   // no approval chain: the costing rule is what is being tested
$pc  = fn($id) => pc_get($id);
$st  = fn($id) => (string) ops_val("SELECT status FROM quotations WHERE id=?", [$id]);

// ---- D-11 B: the costing's own lifecycle -------------------------------------------------
$drive($MM, 'project-costing-new', 'POST', [], ['title' => 'Site engineers', 'client_id' => $cli, 'office_id' => $off, 'basis' => 'LONG']);
$cid = (int) ops_val("SELECT MAX(id) FROM project_costings");
t_eq((int) $pc($cid)['created_by_user_id'], $MM, 'a costing records who prepared it');
$r = $drive($MM, 'project-costing-submit', 'POST', [], ['id' => $cid]);
t_eq($pc($cid)['status'], 'DRAFT', 'a costing with no roles cannot be submitted (' . $said($r) . ')');
$drive($MM, 'project-costing-line-save', 'POST', [], ['costing_id' => $cid, 'role_label' => 'QA/QC engineer', 'head' => ['PERSONNEL' => 60000],
        'boq_qty' => 12, 'target_margin' => 20]);
t_ok(count(pc_lines($cid)) === 1, 'arranged: one role on the costing');
$r = $drive($MM, 'project-costing-to-quote', 'POST', [], ['id' => $cid]);
t_eq((int) $pc($cid)['quote_id'], 0, 'no quotation is made from a draft costing: ' . $said($r));
$r = $drive($MM, 'project-costing-approve', 'POST', [], ['id' => $cid]);
t_eq($pc($cid)['status'], 'DRAFT', 'a draft costing cannot be approved straight away');
$drive($MM, 'project-costing-submit', 'POST', [], ['id' => $cid]);
t_eq($pc($cid)['status'], 'SUBMITTED', 'submitted');
$r = $drive($MM, 'project-costing-approve', 'POST', [], ['id' => $cid]);
t_eq($pc($cid)['status'], 'SUBMITTED', 'its author cannot approve it, though their role may approve: ' . $said($r));
$pg = $drive($MM, 'project-costing', 'GET', ['id' => $cid], [], ['someone else approves it', 'action="/project-costing-approve"']);
t_ok(!empty($pg['has']['someone else approves it']) && empty($pg['has']['action="/project-costing-approve"']),
     '…and their screen says so instead of offering an Approve button');
$r = $drive($BD, 'project-costing-reject', 'POST', [], ['id' => $cid, 'reject_note' => '']);
t_eq($pc($cid)['status'], 'SUBMITTED', 'sending it back needs a reason');
$drive($BD, 'project-costing-reject', 'POST', [], ['id' => $cid, 'reject_note' => 'Check the allowance']);
t_eq($pc($cid)['status'], 'REJECTED', 'with a reason, it goes back');
$drive($MM, 'project-costing-submit', 'POST', [], ['id' => $cid]);
$drive($BD, 'project-costing-approve', 'POST', [], ['id' => $cid]);
t_eq([$pc($cid)['status'], $pc($cid)['approved_by']], ['APPROVED', user_name(ops_one("SELECT * FROM users WHERE id=?", [$BD]))], 'someone else approves it');

// ---- D-11 A: the quotation made from it ------------------------------------------------------
$r = $drive($MM, 'project-costing-to-quote', 'POST', [], ['id' => $cid]);
$qid = (int) $pc($cid)['quote_id'];
t_ok($qid > 0, 'from the approved costing a quotation is drafted: ' . $said($r));
t_eq((int) ops_val("SELECT owner_id FROM quotations WHERE id=?", [$qid]), $MM, '…and belongs to the person who made it');
$drive($MM, 'project-costing-reopen', 'POST', [], ['id' => $cid]);
t_eq([$pc($cid)['status'], $pc($cid)['approved_by']], ['DRAFT', ''], 'reopening an approved costing withdraws its approval');
$r = $drive($MM, 'quote-status', 'POST', ['id' => $qid], ['to' => 'SENT']);
t_eq($st($qid), 'DRAFT', 'a man-month quotation whose costing is no longer approved cannot go to the customer');
t_ok(strpos($said($r), 'approved project costing') !== false, '…and the person is told why: ' . $said($r));
$drive($MM, 'project-costing-submit', 'POST', [], ['id' => $cid]);
$drive($BD, 'project-costing-approve', 'POST', [], ['id' => $cid]);
$drive($MM, 'quote-status', 'POST', ['id' => $qid], ['to' => 'SENT']);
t_eq($st($qid), 'SENT', 'once the costing is approved again, it goes');

// A man-month quotation typed by hand, with no costing at all.
$drive($BDM, 'quote-new', 'POST', [], ['client_id' => $cli, 'subject' => 'Resident engineer', 'office_id' => $off, 'validity_days' => 30,
        'gst_pct' => 18, 'l_desc' => ['Resident engineer'], 'l_qty' => ['6'], 'l_unit' => ['MANMONTH'], 'l_rate' => ['90000']]);
$hand = (int) ops_val("SELECT MAX(id) FROM quotations");
$r = $drive($BDM, 'quote-status', 'POST', ['id' => $hand], ['to' => 'SENT']);
t_eq($st($hand), 'DRAFT', 'a hand-typed man-month quotation with no costing cannot be sent: ' . $said($r));
setting_set('quote_manmonth_costing', 'no');
$drive($BDM, 'quote-status', 'POST', ['id' => $hand], ['to' => 'SENT']);
t_eq($st($hand), 'SENT', 'a company that answers "the costing is optional" in Set up Sales can send it');
setting_set('quote_manmonth_costing', '');
$drive($BDM, 'quote-new', 'POST', [], ['client_id' => $cli, 'subject' => 'Day inspection', 'office_id' => $off, 'validity_days' => 30,
        'gst_pct' => 18, 'l_desc' => ['Inspection'], 'l_qty' => ['2'], 'l_unit' => ['MANDAY'], 'l_rate' => ['8000']]);
$day = (int) ops_val("SELECT MAX(id) FROM quotations");
$drive($BDM, 'quote-status', 'POST', ['id' => $day], ['to' => 'SENT']);
t_eq($st($day), 'SENT', 'control: a man-day quotation needs no costing');

// ---- D-11 A: the creator may change its prices, with a note ------------------------------------
// A second, fresh quotation from the same approved costing, still a draft.
$drive($MM, 'project-costing-to-quote', 'POST', [], ['id' => $cid]);
$q2 = (int) $pc($cid)['quote_id'];
$line = ops_one("SELECT * FROM quote_lines WHERE quote_id=?", [$q2]);
$ver = fn() => (string) ops_val("SELECT updated_at FROM quotations WHERE id=?", [$q2]);   // what the open form carries
$form = fn(array $x = []) => array_merge(['client_id' => $cli, 'subject' => 'Site engineers', 'office_id' => $off, 'validity_days' => 30, 'gst_pct' => 18,
    'row_version' => $ver(),
    'l_desc' => [$line['description']], 'l_qty' => [(string)(float)$line['qty']], 'l_unit' => ['MANMONTH'], 'l_rate' => [(string)(float)$line['rate']]], $x);
$rate0 = (float) $line['rate'];
$drive($MM, 'quote-edit', 'POST', ['id' => $q2], $form(['contact_name' => 'Ravi', 'payment_terms' => '30 days']));
t_eq((float) ops_val("SELECT rate FROM quote_lines WHERE quote_id=?", [$q2]), $rate0, 'an edit that leaves the prices alone needs no note (contact, payment terms saved)');
t_eq((string) ops_val("SELECT contact_name FROM quotations WHERE id=?", [$q2]), 'Ravi', '…and it saved');
$r = $drive($MM, 'quote-edit', 'POST', ['id' => $q2], $form(['row_version' => $ver(), 'l_rate' => ['55000']]),
            ['Why the prices differ from the costing', 'Say why its prices now differ']);
t_eq((float) ops_val("SELECT rate FROM quote_lines WHERE quote_id=?", [$q2]), $rate0, 'changing a price away from the costing without a note is refused');
t_ok(!empty($r['has']['Say why its prices now differ']) && !empty($r['has']['Why the prices differ from the costing']), '…the form is drawn again, saying a note is needed and where');
$r = $drive($BDM, 'quote-edit', 'POST', ['id' => $q2], $form(['row_version' => $ver(), 'l_rate' => ['55000'],
            'costing_note' => 'I think it is too high']), ['Only the person who made it']);
t_eq((float) ops_val("SELECT rate FROM quote_lines WHERE quote_id=?", [$q2]), $rate0, 'someone other than its creator cannot change its prices away from the costing, even with a note');
t_ok(!empty($r['has']['Only the person who made it']), '…and is told that only its creator can');
$r = $drive($MM, 'quote-edit', 'POST', ['id' => $q2], $form(['row_version' => $ver(), 'l_rate' => ['55000'],
       'costing_note' => 'Customer agreed 55,000 a month in the meeting of 18 Oct']), []);
t_eq((float) ops_val("SELECT rate FROM quote_lines WHERE quote_id=?", [$q2]), 55000.0, 'its creator changes the price, with a note');
t_eq((int) ops_val("SELECT COUNT(*) FROM quote_revisions WHERE quote_id=? AND summary LIKE ?", [$q2, '%away from costing%Customer agreed 55,000%']), 1,
     '…and the note is kept in the quotation\'s history');
$drive($MM, 'quote-edit', 'POST', ['id' => $q2], $form(['row_version' => $ver(), 'l_rate' => ['55000'], 'contact_name' => 'Asha']));
t_eq((string) ops_val("SELECT contact_name FROM quotations WHERE id=?", [$q2]), 'Asha', 'a later edit that leaves those prices alone is not asked again');

// A revision is a new record: it still stands on the costing its quotation was priced from.
$drive($MM, 'quote-status', 'POST', ['id' => $q2], ['to' => 'SENT']);
t_eq($st($q2), 'SENT', 'arranged: the changed quotation is sent (its costing is approved)');
$drive($MM, 'quote-revise', 'POST', ['id' => $q2]);
$rev = (int) ops_val("SELECT id FROM quotations WHERE parent_id=? AND is_current=1", [$q2]);
t_ok($rev > 0, 'arranged: a revision is raised');
$r = $drive($MM, 'quote-status', 'POST', ['id' => $rev], ['to' => 'SENT']);
t_eq($st($rev), 'SENT', 'the revision is not refused for "no costing" — it stands on its quotation\'s costing (' . $said($r) . ')');

setting_set('quote_unmatched_approval', '');
$r = $drive($MM, 'sales-setup', 'GET', [], [], ['come from an approved project costing']);
t_ok(!empty($r['has']['come from an approved project costing']), 'Set up Sales asks whether a man-month quotation needs an approved costing');
