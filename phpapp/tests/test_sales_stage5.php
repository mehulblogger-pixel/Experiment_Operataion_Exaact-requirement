<?php
// Sales redesign — Stage 5 (owner, 21 Nov 2026): Orders & payments, Renewals,
// Targets, Client register (Sales view), with the owner's decisions:
//   DEF-06  the order's value before GST is the main figure;
//   DEF-07  Received = payments + the TDS actually deducted on them; credit notes apart;
//   item 15 Orders CSV (money columns only with the receivables right);
//   DEF-10  renewals: open contracts only; one window (Sales settings, 60 to start)
//           for Renewals, Today and Sales home;
//   Q2      contract value only for people who may see contract money;
//   Q3      a renewal call is logged on the client's timeline with the contract number;
//   DEF-11  targets: year picker, empty box = no target, full paged history,
//           a non-management setter only for the people of their branches;
//   DEF-12  the target notice is put away on Sales home itself;
//   ledger  the customer ledger follows branches; every-branch finance sees it whole.
t_section('Sales Stage 5 — Orders & payments, Renewals, Targets, Client register (owner, 21 Nov 2026)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$made = ['q' => [], 'inv' => [], 'rc' => [], 'al' => [], 'cn' => [], 'c' => [], 'p' => [], 'act' => [], 't' => []];
$prevSet = (string) setting_get('sales_renewal_days', '');
$refused = fn($r) => !empty($r['restricted']) || (int) ($r['code'] ?? 0) === 403 || (bool) array_filter($r['flash'] ?? [], fn($x) => stripos($x, 'error') === 0);
try {
    bbv_as($f['master']);
    if (function_exists('crm_migrate')) crm_migrate();
    if (function_exists('books_migrate')) books_migrate();
    if (function_exists('contracts_migrate')) contracts_migrate();
    sx_more_migrate();
    $tag = $f['tag'];
    // A client in Mumbai.
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, home_branch_id) VALUES (?,?,1,?)")->execute(['S5 Valves ' . $tag, 'S5 Valves ' . $tag, $f['mum']]);
    $cli = (int) $pdo->lastInsertId(); $made['p'][] = $cli;
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, home_branch_id) VALUES (?,?,1,?)")->execute(['S5 Pumps ' . $tag, 'S5 Pumps ' . $tag, $f['ahm']]);
    $cliA = (int) $pdo->lastInsertId(); $made['p'][] = $cliA;

    // ---- An accepted quotation: ₹1,00,000 before GST, ₹1,18,000 with GST ----------
    $today = date('Y-m-d');
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, client_name, office_id, subtotal, gst_amount, total_amount, status, accepted_date, created_at, updated_at)
                   VALUES (?,0,1,?,?,?,100000,18000,118000,'ACCEPTED',?,?,?)")
        ->execute(['S5-Q-' . $tag, $cli, 'S5 Valves ' . $tag, $f['mum'], $today, date('c'), date('c')]);
    $qid = (int) $pdo->lastInsertId(); $made['q'][] = $qid;
    // Two invoices against it (₹59,000 each, with GST).
    $inv = function ($no, $total, $office) use ($pdo, $qid, $cli, &$made) {
        $pdo->prepare("INSERT INTO invoices (invoice_no, quotation_id, partner_id, office_id, invoice_date, due_date, total, status) VALUES (?,?,?,?,?,?,?, 'ISSUED')")
            ->execute([$no, $qid, $cli, $office, date('Y-m-d', strtotime('-40 days')), date('Y-m-d', strtotime('-10 days')), $total]);
        $id = (int) $pdo->lastInsertId(); $made['inv'][] = $id; return $id;
    };
    $i1 = $inv('S5-INV1-' . $tag, 59000, $f['mum']);
    $i2 = $inv('S5-INV2-' . $tag, 59000, $f['mum']);
    // Receipt 1 against invoice 1: ₹53,100 paid + ₹5,900 TDS deducted (TDS at 10% on the before-GST ₹50,000 would be 5,000 — the test does not care: it is what was deducted).
    $rcp = function ($amount, $tds, $office) use ($pdo, $cli, &$made) {
        $pdo->prepare("INSERT INTO receipts (receipt_no, partner_id, office_id, receipt_date, amount, tds_amount) VALUES (?,?,?,?,?,?)")
            ->execute(['S5-R-' . uniqid(), $cli, $office, date('Y-m-d'), $amount, $tds]);
        $id = (int) $pdo->lastInsertId(); $made['rc'][] = $id; return $id;
    };
    $alloc = function ($r, $i, $kind, $amt) use ($pdo, &$made) {
        $pdo->prepare("INSERT INTO receipt_allocations (receipt_id, invoice_id, kind, amount, allocated_at) VALUES (?,?,?,?,?)")->execute([$r, $i, $kind, $amt, date('c')]);
        $made['al'][] = (int) $pdo->lastInsertId();
    };
    $r1 = $rcp(53100, 5900, $f['mum']); $alloc($r1, $i1, 'CASH', 53100); $alloc($r1, $i1, 'TDS', 5900);
    // Receipt 2 against invoice 2: ₹20,000 paid, NO TDS deducted.
    $r2 = $rcp(20000, 0, $f['mum']); $alloc($r2, $i2, 'CASH', 20000);
    // A credit note of ₹5,000 on invoice 2.
    $pdo->prepare("INSERT INTO credit_notes (cn_no, invoice_id, partner_id, office_id, cn_date, total, status) VALUES (?,?,?,?,?,5000,'ISSUED')")
        ->execute(['S5-CN-' . $tag, $i2, $cli, $f['mum'], $today]);
    $made['cn'][] = (int) $pdo->lastInsertId();

    // ---- DEF-07 / DEF-06 on the order --------------------------------------------
    $mine = function ($rows) use ($qid) { foreach ($rows as $o) if ((int) $o['id'] === $qid) return $o; return null; };
    [$fa, $fb] = fy_range(active_fy());
    $o = $mine(sx_orders(0, $fa, $fb));
    t_ok($o !== null, 'Orders · the accepted quotation is listed');
    t_eq((float) $o['_value'], 100000.0, 'DEF-06 · the order\'s main figure is before GST: ₹1,00,000');
    t_eq((float) $o['_billed'], 118000.0, 'billed: two invoices of ₹59,000 = ₹1,18,000');
    t_eq([(float) $o['_cash'], (float) $o['_tds']], [73100.0, 5900.0], 'DEF-07 · payments ₹73,100, TDS ₹5,900 — only on the receipt that deducted it');
    t_eq((float) $o['_recv'], 79000.0, 'DEF-07 · Received = payments + TDS = ₹79,000 (the receipt without TDS adds none)');
    t_eq((float) $o['_credit'], 5000.0, 'DEF-07 · the ₹5,000 credit note is shown apart, not as money received');
    t_eq((float) $o['_out'], 34000.0, 'still owed = billed − received − credit notes = ₹34,000');

    // ---- The screen and the CSV as real people ----------------------------------------
    $r = bbv_route('sales-orders', 'GET', $f['master'], 0, [], ['who' => 'all']);
    t_ok(!$refused($r) && ($r['view'] ?? '') === 'ops/sx_orders' && !empty($r['view_ok']), 'Orders & payments · opens' . (($r['view_err'] ?? '') !== '' ? ' — ' . $r['view_err'] : ''));
    $out = (string) ($r['out'] ?? '');
    t_ok(strpos($out, 'S5-Q-' . $tag) !== false && strpos($out, 'Order · before GST') !== false && strpos($out, '₹1,18,000 incl. GST') !== false, 'Orders · before GST shown, with GST beside it');
    t_ok(strpos($out, 'Reduced by credit note') !== false && strpos($out, 'payments + TDS deducted') !== false, 'Orders · Received is labelled payments + TDS; credit notes have their own figure');
    t_ok(strpos($out, 'S5-INV1-' . $tag) !== false, 'Orders · the invoices are linked');
    $r = bbv_route('sales-orders', 'GET', $f['master'], 0, [], ['who' => 'all', 'export' => 'csv']);
    $csv = (string) ($r['out'] ?? '');
    t_ok(strpos($csv, 'Order before GST') !== false && strpos($csv, 'TDS deducted') !== false && strpos($csv, 'S5-Q-' . $tag) !== false, 'item 15 · CSV with the money columns for someone who may see receivables');
    // Marketing Executive (no receivables right) — orders, no money.
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id) VALUES (?,?,?,?,1,?)")->execute([$tag . '_s5me', 'S5', 'ME', 'MARKETING_EXECUTIVE', $f['mum']]);
    $me = (int) $pdo->lastInsertId();
    $r = bbv_route('sales-orders', 'GET', $me, 0, [], []);
    t_ok(!$refused($r) && strpos((string) $r['out'], 'Billed and received amounts are shown to people who may see receivables') !== false && strpos((string) $r['out'], 'Reduced by credit note') === false, 'Orders · without the receivables right: no money figures, and why');
    $r = bbv_route('sales-orders', 'GET', $me, 0, [], ['export' => 'csv']);
    t_ok(strpos((string) $r['out'], 'TDS deducted') === false, 'item 15 · the CSV drops the money columns without the receivables right');

    // ---- Renewals -----------------------------------------------------------------
    setting_set('sales_renewal_days', '');
    t_eq(sx_renewal_days(), 60, 'DEF-10 · the window starts at 60 days (example value)');
    $con = function ($no, $partner, $end, $status, $value = 840000) use ($pdo, &$made) {
        $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, start_date, end_date, is_active, open_status) VALUES (?,?,?,?,?,?,1,?)")
            ->execute([$partner, $no, 'Annual inspection', $value, date('Y-m-d', strtotime('-300 days')), $end, $status]);
        $id = (int) $pdo->lastInsertId(); $made['c'][] = $id; return $id;
    };
    $cOpen  = $con('S5-C-OPEN-' . $tag, $cli, date('Y-m-d', strtotime('+45 days')), 'OPEN');
    $cPend  = $con('S5-C-PEND-' . $tag, $cli, date('Y-m-d', strtotime('+20 days')), 'PENDING');
    $cRej   = $con('S5-C-REJ-' . $tag, $cli, date('Y-m-d', strtotime('+20 days')), 'REJECTED');
    $cFar   = $con('S5-C-FAR-' . $tag, $cli, date('Y-m-d', strtotime('+80 days')), 'OPEN');
    $cOther = $con('S5-C-AHM-' . $tag, $cliA, date('Y-m-d', strtotime('+15 days')), 'OPEN');
    $nos = fn($rows) => array_column($rows, 'contract_number');
    $l60 = $nos(sx_renewals_list());
    t_ok(in_array('S5-C-OPEN-' . $tag, $l60, true) && !in_array('S5-C-PEND-' . $tag, $l60, true) && !in_array('S5-C-REJ-' . $tag, $l60, true), 'DEF-10 · only open contracts — pending and rejected are not listed');
    t_ok(!in_array('S5-C-FAR-' . $tag, $l60, true), 'DEF-10 · 80 days away is outside the 60-day window');
    t_ok(in_array('S5-C-OPEN-' . $tag, $nos(sx_renewals()), true), 'DEF-10 · Today / Sales home read the same 60-day window (45 days away is in it)');
    setting_set('sales_renewal_days', '90');
    t_ok(in_array('S5-C-FAR-' . $tag, $nos(sx_renewals_list()), true) && in_array('S5-C-FAR-' . $tag, $nos(sx_renewals()), true), 'DEF-10 · the setting moves Renewals and Today together (90 days)');
    setting_set('sales_renewal_days', '');
    $prevOut = (string) setting_get('sales_outcomes', ''); $prevOff = (string) setting_get('sales_weekly_off', ''); $prevStale = (string) setting_get('sales_stale_days', ''); $prevOwn = (string) setting_get('sales_owner_by_office', '');
    bbv_route('sales-settings', 'POST', $f['master'], 0, ['oc_label' => ['Called'], 'oc_key' => ['CALLED'], 'oc_days' => ['2'], 'off' => ['0'], 'stale' => '15', 'renew_days' => '45']);
    t_eq((string) ops_val("SELECT svalue FROM settings WHERE skey='sales_renewal_days'"), '45', 'DEF-10 · Sales settings saves the window (45 days) — Renewals, Today and Sales home follow it');
    setting_set('sales_outcomes', $prevOut); setting_set('sales_weekly_off', $prevOff); setting_set('sales_stale_days', $prevStale); setting_set('sales_owner_by_office', $prevOwn);
    setting_set('sales_renewal_days', '');
    // Branch Manager of Mumbai (has contract money): sees values; KAM? use the ME (no contract money) for Q2.
    $r = bbv_route('sales-renewals', 'GET', $f['kiran'], 0, [], []);
    $out = (string) ($r['out'] ?? '');
    t_ok(!$refused($r) && strpos($out, 'S5-C-OPEN-' . $tag) !== false && strpos($out, 'S5-C-AHM-' . $tag) === false, 'Renewals · Branch Manager (Mumbai) sees Mumbai\'s contracts only');
    t_ok(strpos($out, '8,40,000') !== false, 'Q2 · the Branch Manager (credit / revenue data) sees the contract value');
    $r = bbv_route('sales-renewals', 'GET', $me, 0, [], []);
    t_ok(!$refused($r) && strpos((string) $r['out'], 'S5-C-OPEN-' . $tag) !== false && strpos((string) $r['out'], '8,40,000') === false, 'Q2 · a Marketing Executive sees the contract but not its value');
    // Log a renewal call (Q3).
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id) VALUES (?,?,?,?,1,?)")->execute([$tag . '_s5bdm', 'S5', 'BDM', 'BUSINESS_DEV_MANAGER', $f['mum']]);
    $bdm = (int) $pdo->lastInsertId();
    $r = bbv_route('sales-renewals', 'POST', $bdm, 0, ['do' => 'log', 'contract_id' => $cOpen, 'method' => 'CALL', 'note' => 'Wants the renewal quote by Friday']);
    $act = ops_one("SELECT * FROM activities WHERE entity_kind='PARTNER' AND entity_id=? ORDER BY id DESC LIMIT 1", [$cli]);
    if ($act) $made['act'][] = (int) $act['id'];
    t_ok($act && $act['kind'] === 'CALL' && strpos($act['subject'], 'S5-C-OPEN-' . $tag) !== false && strpos($act['body'], 'Friday') !== false, 'Q3 · the call is on the client\'s timeline, naming the contract');
    $n0 = (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='PARTNER' AND entity_id=?", [$cliA]);
    $r = bbv_route('sales-renewals', 'POST', $bdm, 0, ['do' => 'log', 'contract_id' => $cOther, 'note' => 'x']);
    t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='PARTNER' AND entity_id=?", [$cliA]), $n0, 'Q3 · another branch\'s contract cannot be logged on');
    $r = bbv_route('sales-renewals', 'POST', $f['kiran'], 0, ['do' => 'log', 'contract_id' => $cOpen, 'note' => 'BM note']);
    t_ok($refused($r) || (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='PARTNER' AND entity_id=? AND body='BM note'", [$cli]) === 0, 'Q3 · the Branch Manager (view only) cannot log one');

    // ---- Targets (DEF-11) ------------------------------------------------------------
    $fy = active_fy(); $months = sxt_months($fy); $m0 = $months[0];
    $prevFy = fy_label((int) substr($fy, 0, 4) - 1);
    t_eq(sxt_months($prevFy)[0], substr(fy_range($prevFy)[0], 0, 7), 'DEF-11 · the year picker gives that year\'s months');
    bbv_as($f['master']);
    $_POST = []; $post = ['t' => [$bdm => [$m0 => '500000']], 'o' => [$bdm => [$m0 => '']]];
    t_eq(sxt_save($post, $months), 1, 'targets · ₹5,00,000 set');
    t_eq((float) (sxt_grid($months)[$bdm][$m0] ?? -1), 500000.0, 'targets · stored');
    $post = ['t' => [$bdm => [$m0 => '']], 'o' => [$bdm => [$m0 => '500000']]];
    t_eq(sxt_save($post, $months), 1, 'DEF-11 · emptying the box is saved as a change');
    t_ok(!isset(sxt_grid($months)[$bdm][$m0]), 'DEF-11 · an empty box reads as "no target" — not ₹0');
    $last = ops_one("SELECT * FROM sales_targets WHERE user_id=? AND ym=? ORDER BY id DESC LIMIT 1", [$bdm, $m0]);
    t_ok($last && $last['amount'] === null && (float) $last['prev_amount'] === 500000.0, 'DEF-11 · the history keeps ₹5,00,000 → no target');
    for ($i = 0; $i < 35; $i++) $pdo->prepare("INSERT INTO sales_targets (user_id, ym, amount, set_by, set_at) VALUES (?,?,?,?,?)")->execute([$bdm, $months[1], 1000 + $i, 'test', date('c')]);
    $r = bbv_route('sales-targets', 'GET', $f['master'], 0, [], ['fy' => $fy, 'hp' => '2']);
    t_ok(!$refused($r) && strpos((string) $r['out'], 'Page 2 of') !== false, 'DEF-11 · the full history, a page at a time (page 2 opens)');
    // A non-management setter: Marketing Manager of Mumbai with the switch, sees only Mumbai people.
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices, permissions) VALUES (?,?,?,?,1,?,?,?)")
        ->execute([$tag . '_s5mm', 'S5', 'MM', 'MARKETING_MANAGER', $f['mum'], (string) $f['mum'], '@diff,+crm.targets.set']);
    $mm = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id) VALUES (?,?,?,?,1,?)")->execute([$tag . '_s5kamA', 'S5', 'KamAhm', 'KEY_ACCOUNTS_MANAGER', $f['ahm']]);
    $kamA = (int) $pdo->lastInsertId();
    bbv_as($mm);
    t_ok(sxt_can_set(), 'DEF-11 · a Marketing Manager with "Set targets for others" may set targets');
    $pp = sxt_people_for_setter(sx_target_people());
    t_ok(isset($pp[$bdm]) && !isset($pp[$kamA]), 'DEF-11 · …for the people of their own branch only (Mumbai BDM yes, Ahmedabad KAM no)');
    $post = ['t' => [$kamA => [$m0 => '300000']], 'o' => [$kamA => [$m0 => '']]];
    t_eq(sxt_save($post, $months), 0, 'DEF-11 · a target for another branch\'s person is not saved');

    // ---- DEF-12: the notice is put away on Sales home ---------------------------------
    $pdo->prepare("INSERT INTO sales_targets (user_id, ym, amount, set_by, set_at, seen_at) VALUES (?,?,?,?,?, '')")->execute([$bdm, $months[2], 200000, 'Someone Else', date('c')]);
    t_ok((bool) sxt_unseen_for($bdm), 'DEF-12 · a change waits to be seen');
    bbv_route('sales-home', 'POST', $bdm, 0, ['seen' => '1']);
    t_ok(!sxt_unseen_for($bdm), 'DEF-12 · "OK, seen" on Sales home puts it away (it no longer needs the Targets screen)');

    // ---- The customer ledger follows branches --------------------------------------------
    $iA = $inv('S5-INV-AHM-' . $tag, 23600, $f['ahm']);
    bbv_as($f['meera']);   // Finance, Mumbai only
    $refs = array_column(books_ledger($cli, '', '', true)['rows'], 'ref');
    t_ok(in_array('S5-INV1-' . $tag, $refs, true) && !in_array('S5-INV-AHM-' . $tag, $refs, true), 'ledger · Finance (Mumbai) sees the Mumbai invoices only');
    bbv_as($f['rakesh']);  // central Finance, every branch
    $refs = array_column(books_ledger($cli, '', '', true)['rows'], 'ref');
    t_ok(in_array('S5-INV1-' . $tag, $refs, true) && in_array('S5-INV-AHM-' . $tag, $refs, true), 'ledger · central Finance sees every branch (owner: "irrespective of the branches")');
    $r = bbv_route('ledger', 'GET', $f['meera'], 0, [], ['id' => $cli]);
    t_ok(!$refused($r) && strpos((string) $r['out'], 'S5-INV-AHM-' . $tag) === false && strpos((string) $r['out'], 'S5-INV1-' . $tag) !== false, 'ledger screen · Finance (Mumbai) — Mumbai only');
    $r = bbv_route('ledger', 'GET', $f['rakesh'], 0, [], ['id' => $cli]);
    t_ok(!$refused($r) && strpos((string) $r['out'], 'S5-INV-AHM-' . $tag) !== false, 'ledger screen · central Finance — the whole ledger');

    // ---- Client register (Sales view) sits in the Sales workspace -----------------------
    //  /clients is drawn by index.php (not the ops dispatcher), so the view is drawn
    //  here with the variables index.php gives it.
    bbv_as($f['master']);
    $drawList = function ($salesView) use ($cli) {
        $vars = ['rows' => [['id' => $cli, 'code' => 'X', 'legal_name' => 'S5 Valves', 'display_name' => 'S5 Valves', 'is_active' => 1, 'gstin' => '', 'city' => '', 'state' => '']],
                 'total' => 1, 'roleField' => 'is_client', 'salesView' => $salesView, 'salesOk' => true,
                 'salesCols' => $salesView ? sx_client_sales_cols([$cli]) : [], 'title' => 'Client register', 'subtitle' => 'Clients',
                 'q' => '', 'status' => '', 'page' => 1, 'pages' => 1, 'base' => '/clients?'];
        ob_start();
        try { (static function ($__v) { extract($__v); include __DIR__ . '/../views/list.php'; })($vars); $err = ''; }
        catch (Throwable $e) { $err = $e->getMessage(); }
        $html = (string) ob_get_clean();
        return [$html, $err];
    };
    [$h, $err] = $drawList(true);
    t_ok($err === '' && strpos($h, 'All Sales screens') !== false && strpos($h, 'sxw-bn') !== false, 'Client register · the Sales view carries the Sales section bar and the phone bar' . ($err ? ' — ' . $err : ''));
    [$h2, $err2] = $drawList(false);
    t_ok($err2 === '' && strpos($h2, 'All Sales screens') === false, 'Client register · the standard view is unchanged (no Sales bar)');

    // ---- Every screen renders as each role, the same refusals ------------------------------
    foreach (['kiran', 'neha', 'meera', 'rakesh', 'bd', 'master'] as $k) foreach (['sales-orders', 'sales-renewals', 'sales-targets'] as $rt) {
        $r = bbv_route($rt, 'GET', $f[$k], 0, [], []);
        t_ok(($r['view_err'] ?? '') === '', "$rt as $k · no page error" . (($r['view_err'] ?? '') !== '' ? ' — ' . $r['view_err'] : ''));
    }
} finally {
    setting_set('sales_renewal_days', $prevSet);
    $del = function ($t, $ids) use ($pdo) { if ($ids) $pdo->exec("DELETE FROM $t WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")"); };
    $del('receipt_allocations', $made['al']); $del('receipts', $made['rc']); $del('credit_notes', $made['cn']);
    $pdo->exec("DELETE FROM invoices WHERE invoice_no LIKE 'S5-%" . $f['tag'] . "'");
    $del('quotations', $made['q']); $del('partner_contracts', $made['c']);
    $pdo->exec("DELETE FROM activities WHERE entity_kind='PARTNER' AND entity_id IN (" . implode(',', array_map('intval', $made['p'] ?: [0])) . ")");
    $del('business_partners', $made['p']);
    $pdo->exec("DELETE FROM sales_targets WHERE user_id IN (SELECT id FROM users WHERE username LIKE '" . $f['tag'] . "\\_%' ESCAPE '\\')");
    bbv_cleanup($f);
}
