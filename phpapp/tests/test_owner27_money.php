<?php
// Owner, 27 Nov 2026 — money and invoices (his test results BL-09, BL-10, GD-04, GD-05 and his OK):
//   M1 our state comes from the Company profile too (it was never read → always CGST+SGST);
//      an invoice is not issued while our state is unknown;
//   M2 people who may see everyone open Orders & payments (and the other Sales screens) on Everyone;
//   M3 "Received this month" counts the TDS deducted, like "Received";
//   M4 the Invoices list: an FY filter and "Record payment" for one invoice, matched in one step;
//   M5 matching says, per invoice, what was matched and what was not; the form is pre-filled;
//   M6 a new invoice: our branch filled in, the client's contracts as a choice, units from the master;
//   M7 the manual "invoice raised" form is folded and labelled; the "already billed" message is honest.
// Made-up figures throughout (owner's GD-05): invoice A ₹1,18,000, B ₹59,000; bank ₹1,06,200 + TDS ₹11,800.
t_section('Money and invoices — owner 27 Nov (M1–M7)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$made = ['bp' => [], 'inv' => [], 'rc' => [], 'pc' => []];
$keep = [];
foreach (['company_gstin', 'company_state', 'tally_state'] as $k) $keep[$k] = (string) setting_get($k, '');
$G = function ($state, $pan, $ent = '1') { $first = $state . $pan . $ent . 'Z'; return $first . commercial_gstin_check_char($first); };
$PAN = 'AAACM' . str_pad((string) (abs(crc32($f['tag'])) % 10000), 4, '0', STR_PAD_LEFT) . 'D';
$guj = $G('24', $PAN); $mah = $G('27', $PAN);
$newInv = function ($pid, $loc = 0) use (&$made, $f) {
    $r = books_invoice_create(['partner_id' => $pid, 'office_id' => $f['mum'], 'billing_address_id' => $loc, 'invoice_date' => date('Y-m-d')]);
    $id = (int) ($r['id'] ?? 0); $made['inv'][] = $id; return $id;
};
try {
    bbv_as($f['master']); custid_migrate(); books_migrate();
    // Our branch (Mumbai office of the fixture) has NO GSTIN of its own — as Ahmedabad on opstest.
    $pdo->prepare("UPDATE offices SET gstin='' WHERE id=?")->execute([$f['mum']]);
    $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
        ->execute(['MV-' . $f['tag'], 'Money Valves Pvt Ltd ' . $f['tag'], 'Money Valves ' . $f['tag'], date('c')]);
    $sv = (int) $pdo->lastInsertId(); $made['bp'][] = $sv;
    custid_save($sv, custid_from_input(['gstin' => $guj], true));
    $pdo->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city,state,pincode,is_primary) VALUES (?,'REGISTERED','HO','Plot 12','Vadodara','Gujarat','390010',1)")->execute([$sv]);
    $r = custid_add_location($sv, ['gstin' => $mah, 'label' => 'Mumbai plant', 'line1' => 'MIDC Andheri', 'city' => 'Mumbai', 'pincode' => '400093']);
    $mahLoc = (int) ($r['id'] ?? 0);
    t_ok($mahLoc > 0, 'set-up · the customer has a Gujarat main address and a Maharashtra billing address');

    // ---- M1 our state --------------------------------------------------------------------------
    setting_set('company_gstin', ''); setting_set('company_state', ''); setting_set('tally_state', '');
    t_eq(books_our_state($f['mum']), '', 'M1 · nothing set anywhere → our state is unknown');
    $i0 = $newInv($sv, $mahLoc);
    books_line_add($i0, ['description' => 'Inspection services', 'qty' => 1, 'rate' => 100000, 'gst_pct' => 18]);
    $why = books_issue($i0);
    t_ok(strpos($why, 'Our own state is not known') !== false && strpos($why, 'Admin → Company profile') !== false && strpos($why, 'branch its GSTIN') !== false,
         'M1 · with our state unknown the invoice is NOT issued, and the message says where to set it (' . substr($why, 0, 160) . ')');
    t_eq((string) ops_val("SELECT status FROM invoices WHERE id=?", [$i0]), 'DRAFT', 'M1 · ...it stays a draft');

    setting_set('company_state', 'Gujarat');
    t_eq(books_our_state($f['mum']), '24', 'M1 · Company profile state Gujarat → our state 24 (branch has no GSTIN)');
    setting_set('company_state', ''); setting_set('company_gstin', $G('24', 'AABCE1234F'));
    t_eq(books_our_state($f['mum']), '24', 'M1 · Company profile GSTIN 24… → our state 24');
    setting_set('company_state', 'Maharashtra');
    t_eq(books_our_state($f['mum']), '24', 'M1 · the profile GSTIN wins over a differently typed profile state');
    setting_set('company_gstin', ''); setting_set('company_state', ''); setting_set('tally_state', 'Gujarat');
    t_eq(books_our_state($f['mum']), '24', 'M1 · Tally "Our state" is still the last fallback');
    setting_set('tally_state', 'Karnataka'); setting_set('company_state', 'Gujarat');
    t_eq(books_our_state($f['mum']), '24', 'M1 · the Company profile comes before Tally');
    $pdo->prepare("UPDATE offices SET gstin=? WHERE id=?")->execute([$G('27', 'AABCE1234F'), $f['mum']]);
    t_eq(books_our_state($f['mum']), '27', 'M1 · the branch\'s own GSTIN comes first of all');
    $pdo->prepare("UPDATE offices SET gstin='' WHERE id=?")->execute([$f['mum']]);
    setting_set('tally_state', '');

    // Profile Gujarat, branch without GSTIN: ₹1,00,000 to Maharashtra = IGST 18,000; to Gujarat = CGST 9,000 + SGST 9,000.
    $t = tally_compute(['office_id' => $f['mum'], 'gstin' => $mah, 'state' => '', 'subtotal' => 1000, 'taxable' => 1000, 'gst_pct' => 18, 'total' => 1180, 'amount' => 1000], tally_settings());
    t_ok(is_array($t), 'M1 · the Tally export reads our state through the same rule (no error)');
    t_eq(books_issue($i0), '', 'M1 · the same draft issues once the Company profile state is set');
    $row = ops_one("SELECT * FROM invoices WHERE id=?", [$i0]);
    t_eq([(int) $row['is_igst'], $row['supplier_state'], round((float) $row['igst'], 2), round((float) $row['cgst'], 2)], [1, '24', 18000.0, 0.0],
         'M1 · ₹1,00,000 billed to Maharashtra → IGST ₹18,000 (was CGST+SGST), our state stored as Gujarat');
    $i1 = $newInv($sv, $mahLoc);
    books_line_add($i1, ['description' => 'Inspection services', 'qty' => 1, 'rate' => 100000, 'gst_pct' => 18]);
    t_eq(custid_invoice_set_location($i1, 0), '', 'M1 · "Billed to" changed to the Gujarat main address');
    $row = ops_one("SELECT * FROM invoices WHERE id=?", [$i1]);
    t_eq([(int) $row['is_igst'], round((float) $row['cgst'], 2), round((float) $row['sgst'], 2), round((float) $row['igst'], 2), $row['supplier_state']], [0, 9000.0, 9000.0, 0.0, '24'],
         'M1 · Gujarat → CGST ₹9,000 + SGST ₹9,000, re-computed when Billed to changes');
    custid_invoice_set_location($i1, $mahLoc);
    $row = ops_one("SELECT * FROM invoices WHERE id=?", [$i1]);
    t_eq([(int) $row['is_igst'], round((float) $row['igst'], 2)], [1, 18000.0], 'M1 · ...and back to Maharashtra → IGST ₹18,000');
    // A draft made while our state was unknown takes the state when issued.
    setting_set('company_state', '');
    $i2 = $newInv($sv, $mahLoc);
    books_line_add($i2, ['description' => 'x', 'qty' => 1, 'rate' => 1000, 'gst_pct' => 18]);
    t_eq((string) ops_val("SELECT supplier_state FROM invoices WHERE id=?", [$i2]), '', 'M1 · a draft started with our state unknown holds no state');
    setting_set('company_state', 'Gujarat');
    t_eq(books_issue($i2), '', 'M1 · once the profile state is filled the draft issues');
    $row = ops_one("SELECT * FROM invoices WHERE id=?", [$i2]);
    t_eq([(int) $row['is_igst'], $row['supplier_state'], round((float) $row['igst'], 2)], [1, '24', 180.0], 'M1 · ...as IGST, our state taken at issue');
    setting_set('company_state', 'Maharashtra');
    t_eq((int) ops_val("SELECT is_igst FROM invoices WHERE id=?", [$i2]), 1, 'M1 · an issued invoice never changes when the setting changes later');
    setting_set('company_state', 'Gujarat');

    // ---- M4 / M5: two invoices, receipts, matching ---------------------------------------------
    $A = $newInv($sv, 0); books_line_add($A, ['description' => 'Inspection A', 'qty' => 1, 'rate' => 100000, 'gst_pct' => 18]); books_issue($A);
    $B = $newInv($sv, 0); books_line_add($B, ['description' => 'Inspection B', 'qty' => 1, 'rate' => 50000, 'gst_pct' => 18]); books_issue($B);
    $noA = (string) ops_val("SELECT invoice_no FROM invoices WHERE id=?", [$A]); $noB = (string) ops_val("SELECT invoice_no FROM invoices WHERE id=?", [$B]);
    t_eq([(float) ops_val("SELECT total FROM invoices WHERE id=?", [$A]), (float) ops_val("SELECT total FROM invoices WHERE id=?", [$B])], [118000.0, 59000.0], 'set-up · invoice A ₹1,18,000 and B ₹59,000 issued (CGST+SGST)');

    // M5 — one press matches A and refuses B; the answer names both.
    $rc = books_receipt_create(['partner_id' => $sv, 'amount' => 106200, 'tds_amount' => 11800, 'receipt_date' => date('Y-m-d')]); $made['rc'][] = (int) $rc['id'];
    $sug = books_allocate_suggest(array_values(array_filter(books_open_invoices($sv), fn($o) => in_array((int) $o['id'], [$A, $B], true))), 106200, 11800);
    t_eq($sug[$A] ?? null, ['cash' => 106200.0, 'tds' => 11800.0], 'M5 · the match form is pre-filled oldest first: A gets cash ₹1,06,200 + TDS ₹11,800');
    t_eq($sug[$B] ?? null, ['cash' => 0.0, 'tds' => 0.0], 'M5 · ...and nothing is left for B');
    $rep = books_allocate_report((int) $rc['id'], [$A => ['cash' => 106200, 'tds' => 11800], $B => ['cash' => 59000]]);
    t_eq($rep['tone'], 'part', 'M5 · part matched → amber');
    t_ok(strpos($rep['text'], 'Matched ₹1,06,200') !== false && strpos($rep['text'], 'TDS ₹11,800 to ' . $noA) !== false,
         'M5 · it says what WAS matched: "Matched ₹1,06,200 + TDS ₹11,800 to ' . $noA . '"');
    t_ok(strpos($rep['text'], 'Not matched: ₹59,000 to ' . $noB) !== false && strpos($rep['text'], 'own receipt') !== false,
         'M5 · and what was NOT, with the way out: ' . substr($rep['text'], 0, 240));
    t_eq((string) ops_val("SELECT status FROM invoices WHERE id=?", [$A]), 'PAID', 'M5 · invoice A is PAID (cash + TDS)');
    t_eq(books_allocate((int) $rc['id'], [$B => ['cash' => 1]]) !== '', true, 'M5 · books_allocate() still answers non-empty for a refusal (existing callers)');
    $rep = books_allocate_report((int) $rc['id'], [$B => ['tds' => 100]]);
    t_eq($rep['tone'], 'none', 'M5 · nothing matched → red');
    t_ok(strpos($rep['text'], 'TDS ₹100') !== false && strpos($rep['text'], 'TDS left') !== false, 'M5 · a TDS refusal names the TDS: ' . $rep['text']);
    $rep = books_allocate_report((int) $rc['id'], []);
    t_eq([$rep['tone'], $rep['text']], ['none', 'Nothing was allocated — every figure was blank or zero.'], 'M5 · nothing typed → plain message');

    // M4 — record a payment against B directly from the list.
    $pre = books_settled($B)['outstanding'];
    $r = books_invoice_pay($B, ['amount' => 60000, 'tds_amount' => 0]);
    t_ok(!empty($r['err']) && strpos($r['err'], 'more than ' . $noB . ' still owes') !== false && strpos($r['err'], 'Money in') !== false,
         'M4 · more than B owes is refused before any receipt is made, pointing to Money in');
    t_eq(books_settled($B)['outstanding'], $pre, 'M4 · ...and nothing changed');
    $nRc = (int) ops_val("SELECT COUNT(*) FROM receipts WHERE partner_id=?", [$sv]);
    $r = books_invoice_pay($B, ['amount' => 53100, 'tds_amount' => 5900, 'mode' => 'NEFT', 'reference' => 'UTR123']);
    if (!empty($r['id'])) $made['rc'][] = (int) $r['id'];
    t_eq([$r['tone'] ?? '', (string) ops_val("SELECT status FROM invoices WHERE id=?", [$B])], ['ok', 'PAID'], 'M4 · bank ₹53,100 + TDS ₹5,900 against B → one receipt, matched, B PAID');
    t_eq((int) ops_val("SELECT COUNT(*) FROM receipts WHERE partner_id=?", [$sv]), $nRc + 1, 'M4 · exactly one receipt was written (the Money-in code, not a copy)');
    $rr = ops_one("SELECT * FROM receipts WHERE id=?", [(int) $r['id']]);
    t_eq([(float) $rr['amount'], (float) $rr['tds_amount'], (string) $rr['reference'], (int) $rr['office_id']], [53100.0, 5900.0, 'UTR123', $f['mum']], 'M4 · the receipt carries bank, TDS, reference and the invoice\'s branch');
    t_ok(strpos($r['text'], 'is now paid') !== false && strpos($r['text'], 'TDS ₹5,900') !== false, 'M4 · the message: ' . $r['text']);
    $r = books_invoice_pay($B, ['amount' => 10]);
    t_ok(!empty($r['err']) && strpos($r['err'], 'nothing to pay') !== false, 'M4 · a paid invoice cannot take another payment here');
    $d = $newInv($sv, 0);
    $r = books_invoice_pay($d, ['amount' => 10]);
    t_ok(!empty($r['err']) && strpos($r['err'], 'issue it first') !== false, 'M4 · a draft cannot take a payment');

    // M4 — the list: FY filter, Outstanding, the button.
    $cols = books_dt_invoice_columns(date('Y-m-d'));
    t_ok(isset($cols['due']) && $cols['due']['label'] === 'Outstanding' && isset($cols['pay']), 'M4 · the list has an Outstanding column and a Record payment column');
    $C = $newInv($sv, 0); books_line_add($C, ['description' => 'Inspection C', 'qty' => 1, 'rate' => 1000, 'gst_pct' => 18]); books_issue($C);
    $rowC = books_invoices(['partner' => $sv, 'status' => 'ISSUED']);
    $rowC = array_values(array_filter($rowC, fn($x) => (int) $x['id'] === $C))[0] ?? null;
    t_ok($rowC && strpos($cols['pay']['render']($rowC), '/invoice-pay?id=' . $C) !== false, 'M4 · an issued invoice with money owed shows "₹ Record payment"');
    $rowB = array_values(array_filter(books_invoices(['partner' => $sv]), fn($x) => (int) $x['id'] === $B))[0] ?? null;
    t_eq($rowB ? $cols['pay']['render']($rowB) : 'x', '', 'M4 · a paid invoice shows no button');
    $pdo->prepare("UPDATE invoices SET invoice_date=? WHERE id=?")->execute(['2001-05-10', $C]);
    [$fyFrom, $fyTo] = fy_range(current_fy());
    $inYear = array_map(fn($x) => (int) $x['id'], books_invoices(['partner' => $sv, 'from' => $fyFrom, 'to' => $fyTo]));
    t_ok(in_array($A, $inYear, true) && !in_array($C, $inYear, true), 'M4 · FY filter: this year shows A, not an invoice dated 2001');
    t_ok(in_array($C, array_map(fn($x) => (int) $x['id'], books_invoices(['partner' => $sv])), true), 'M4 · All years shows it');
    t_eq(books_invoices_count(['partner' => $sv, 'from' => $fyFrom, 'to' => $fyTo]), count($inYear), 'M4 · the count follows the same filter');
    $pdo->prepare("UPDATE invoices SET invoice_date=? WHERE id=?")->execute([date('Y-m-d'), $C]);
    $ui = (string) file_get_contents(dirname(__DIR__) . '/lib/booksui.php');
    t_ok(strpos($ui, "fy_filter(\$f === 'open' || \$f === 'overcredited' ? 'ALL' : null)") !== false, 'M4 · the Open view starts on All years, so nothing owed from last year is hidden');
    t_ok(isset(ops_route_module_map()['invoice-pay']), 'M4 · /invoice-pay carries the money module gate like every books route');
    putenv('BBV_RAW=1');
    $res = bbv_route('invoice-pay', 'GET', $f['master'], $C, [], ['id' => $C]);
    putenv('BBV_RAW');
    $h = (string) ($res['html'] ?? '');
    t_ok(strpos($h, 'Record payment') !== false && strpos($h, 'name="amount"') !== false && strpos($h, 'value="1180.00"') !== false && strpos($h, 'name="tds_amount"') !== false,
         'M4 · the Record payment screen opens with the amount owed filled in and a TDS box' . (!empty($res['view_err']) ? ' — ' . $res['view_err'] : ''));
    t_ok(strpos($h, 'grid-template-columns:1fr') !== false, 'M4 · ...one column on a phone');

    // ---- M3 Received this month ----------------------------------------------------------------
    $all = sx_received_this_month([$A, $B]);
    t_eq($all, 177000.0, 'M3 · Received this month for A and B = ₹1,06,200 + ₹11,800 + ₹53,100 + ₹5,900 = ₹1,77,000 (TDS counted, like Received)');
    $src = (string) file_get_contents(dirname(__DIR__) . '/lib/sales_more.php');
    t_ok(strpos($src, "SUM(r.amount + COALESCE(r.tds_amount,0))") !== false, 'M3 · the branch-wide figure adds the receipts\' TDS too');

    // ---- M2 Everyone by default -----------------------------------------------------------------
    unset($_GET['who']);
    bbv_as($f['master']);
    t_eq([sx_who_default(), sx_person()], ['all', 0], 'M2 · someone who may see everyone opens on Everyone');
    $_GET['who'] = 'me';
    t_eq(sx_person(), (int) $f['master'], 'M2 · ...and can still pick Me');
    unset($_GET['who']);
    bbv_as($f['neha']);
    t_eq([sx_who_default(), sx_person()], ['me', (int) $f['neha']], 'M2 · anyone else still opens on Me');
    bbv_as($f['master']);
    foreach (['sx_board.php', 'sx_orders.php', 'sx_home.php', 'sx_today.php'] as $v)
        t_ok(strpos((string) file_get_contents(dirname(__DIR__) . '/views/ops/' . $v), 'sx_who_carry($whoKey)') !== false, "M2 · $v keeps the chosen view in its links");

    // ---- M6 new invoice -------------------------------------------------------------------------
    $pdo->prepare("UPDATE business_partners SET home_branch_id=? WHERE id=?")->execute([$f['ahm'], $sv]);
    t_eq(books_default_office_for($sv), $f['ahm'], 'M6 · the client\'s billing branch is filled in');
    $pdo->prepare("UPDATE business_partners SET home_branch_id=NULL WHERE id=?")->execute([$sv]);
    bbv_as($f['neha']);
    t_eq(books_default_office_for($sv), $f['mum'], 'M6 · ...else the branch of the person raising it');
    bbv_as($f['master']);
    $pdo->prepare("INSERT INTO partner_contracts (partner_id,contract_number,title,value,start_date,end_date,is_active) VALUES (?,?,?,?,?,?,1)")->execute([$sv, 'CT-' . $f['tag'] . '-1', 'Annual inspection', 500000, '2026-04-01', '2027-03-31']);
    $made['pc'][] = (int) $pdo->lastInsertId();
    putenv('BBV_RAW=1');
    $res = bbv_route('invoice-new', 'GET', $f['master'], $sv, [], ['partner' => $sv]);
    $h = (string) ($res['html'] ?? '');
    t_ok(preg_match('~<select name="contract_number"[^>]*>.*?<option value="CT-' . $f['tag'] . '-1" selected>~s', $h) === 1,
         'M6 · one contract on file → a drop-down with it chosen' . (!empty($res['view_err']) ? ' — ' . $res['view_err'] : ''));
    $pdo->prepare("INSERT INTO partner_contracts (partner_id,contract_number,title,value,start_date,end_date,is_active) VALUES (?,?,?,?,?,?,1)")->execute([$sv, 'CT-' . $f['tag'] . '-2', 'Shutdown', 200000, '2026-06-01', '2026-07-31']);
    $made['pc'][] = (int) $pdo->lastInsertId();
    $res = bbv_route('invoice-new', 'GET', $f['master'], $sv, [], ['partner' => $sv]);
    $h = (string) ($res['html'] ?? '');
    t_ok(strpos($h, 'CT-' . $f['tag'] . '-1') !== false && strpos($h, 'CT-' . $f['tag'] . '-2') !== false && strpos($h, '" selected>CT-') === false,
         'M6 · two contracts → both offered, neither guessed');
    $res = bbv_route('invoice', 'GET', $f['master'], $d, [], ['id' => $d]);
    $h = (string) ($res['html'] ?? '');
    putenv('BBV_RAW');
    t_ok(preg_match('~<select class="form-control" id="inv-unit" name="unit">.*?Man-day.*?Per visit~s', $h) === 1, 'M6 · Add a line: Unit is the same list as the quotation (Man-day, Per visit …)');
    t_ok(strpos($h, '⬇ PDF / Print (draft)') !== false, 'M7 · a draft shows "⬇ PDF / Print (draft)"');
    // The job's contract is carried (job → invoice); books_carry_for picks a single contract from the work.
    t_ok(strpos((string) file_get_contents(dirname(__DIR__) . '/lib/ops.php'), "'contract_number' => \$contractNo,") !== false, 'M6 · invoicing from a job still takes that job\'s contract');

    // ---- M7 wording ------------------------------------------------------------------------------
    $ops = (string) file_get_contents(dirname(__DIR__) . '/lib/ops.php');
    t_ok(strpos($ops, 'To charge more, start a + New invoice for the extra; to reduce it, raise a credit note') !== false
         && strpos($ops, "already billed on invoice ' . \$exNo") !== false && strpos($ops, "on invoice #' . \$existingInv") === false,
         'M7 · "already billed" names the real invoice number and offers only what is possible');
    $jd = (string) file_get_contents(dirname(__DIR__) . '/views/ops/job_detail.php');
    t_ok(strpos($jd, 'Only if the invoice was raised outside EXAACT') !== false && strpos($jd, "<details<?= \$bInv ? '' : ' open' ?>") === false,
         'M7 · the manual form is always folded and says what it is for');
    t_section('Money received — the branch starts on the one that invoiced (owner, 28 Nov)');
    bbv_as($f['master']);
    $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at,home_branch_id) VALUES (?,?,?,1,0,'ACTIVE',?,?)")
        ->execute(['RB-' . $f['tag'], 'Receipt Branch Co ' . $f['tag'], 'Receipt Branch ' . $f['tag'], date('c'), $f['ahm']]);
    $rb = (int) $pdo->lastInsertId(); $made['bp'][] = $rb;
    t_eq(books_receipt_default_office($rb), [(int) $f['ahm'], 'default'], 'no unpaid invoice: the customer\'s billing branch (Ahmedabad)');
    $pdo->prepare("INSERT INTO invoices (invoice_no, partner_id, office_id, status, total) VALUES (?,?,?,'ISSUED',59000)")->execute(['RBM/' . $f['tag'], $rb, $f['mum']]);
    $made['inv'][] = (int) $pdo->lastInsertId();
    t_eq(books_receipt_default_office($rb), [(int) $f['mum'], 'invoices'], 'its unpaid invoice was raised by Mumbai: the money starts on Mumbai');
    $pdo->prepare("INSERT INTO invoices (invoice_no, partner_id, office_id, status, total) VALUES (?,?,?,'PART_PAID',11800)")->execute(['RBA/' . $f['tag'], $rb, $f['ahm']]);
    $made['inv'][] = (int) $pdo->lastInsertId();
    t_eq(books_receipt_default_office($rb), [(int) $f['ahm'], 'default'], 'unpaid invoices from two branches: back to the customer\'s billing branch (no guessing)');
    $src = (string) file_get_contents(dirname(__DIR__) . '/views/ops/receipt_form.php');
    t_ok(strpos($src, 'Filled in from the branch that raised this customer') !== false && strpos($src, "'/receipt-new?partner='") !== false,
         'the form says where the branch came from, and choosing the customer fills it in');
} finally {
    bbv_as($f['master']);
    putenv('BBV_RAW'); unset($_GET['who']);
    foreach ($keep as $k => $v) setting_set($k, $v);
    $ids = fn($a) => implode(',', array_map('intval', array_filter($a)));
    if ($made['rc']) { $pdo->exec("DELETE FROM receipt_allocations WHERE receipt_id IN (" . $ids($made['rc']) . ")"); $pdo->exec("DELETE FROM receipts WHERE id IN (" . $ids($made['rc']) . ")"); }
    if ($made['inv']) { $in = $ids($made['inv']); try { $pdo->exec("DELETE FROM receipt_allocations WHERE invoice_id IN ($in)"); $pdo->exec("DELETE FROM invoice_lines WHERE invoice_id IN ($in)"); } catch (Throwable $e) {} $pdo->exec("DELETE FROM invoices WHERE id IN ($in)"); }
    if ($made['pc']) $pdo->exec("DELETE FROM partner_contracts WHERE id IN (" . $ids($made['pc']) . ")");
    if ($made['bp']) { $in = $ids($made['bp']); $pdo->exec("DELETE FROM partner_addresses WHERE partner_id IN ($in)"); try { $pdo->exec("DELETE FROM receipts WHERE partner_id IN ($in)"); } catch (Throwable $e) {} $pdo->exec("DELETE FROM business_partners WHERE id IN ($in)"); }
    bbv_cleanup($f);
}
