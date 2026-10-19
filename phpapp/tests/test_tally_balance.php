<?php
// ============================================================================
//  Every Tally voucher the export produces must BALANCE — its ledger entries
//  sum to zero — or Tally refuses the import. (GAP-044)
//
//  Found by docs/audit-2026-10/evidence/tally-balance-probe.php: the sales
//  voucher debited the party with the rupee-rounded total and credited sales +
//  tax with the paise, so any invoice with a round-off was off by that round-off.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('tally — every voucher balances; CSVs carry what the XML carries');
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$sums = function ($xml) {
    $out = [];
    foreach (explode('<VOUCHER ', $xml) as $i => $block) {
        if ($i === 0) continue;
        preg_match_all('#<ALLLEDGERENTRIES\.LIST>.*?<AMOUNT>(-?[\d.]+)</AMOUNT>#s', $block, $m);
        $out[] = round(array_sum(array_map('floatval', $m[1])), 2);
    }
    return $out;
};
$warnings = [];
set_error_handler(function ($no, $str) use (&$warnings) { $warnings[] = $str; return true; });
try {
    books_migrate(); tally_migrate();
    db()->prepare("INSERT INTO offices (name,is_active) VALUES ('Tally HQ',1)")->execute(); $off = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,state,gstin) VALUES ('Tally Co','Tally Co',1,'ACTIVE','Gujarat','24AAAAA0000A1Z5')")->execute(); $pid = (int)db()->lastInsertId();
    $state0 = setting_get('tally_state', ''); setting_set('tally_state', 'Gujarat');
    $cfg = tally_settings();
    t_eq($cfg['tally_roundoff_ledger'], 'Round Off', 'a round-off ledger name exists, with a Tally-standard default');

    // ---- sales: an invoice whose GST produces paise --------------------------------
    $id = (int)books_invoice_create(['partner_id' => $pid, 'office_id' => $off, 'invoice_date' => '2097-07-10'])['id'];
    books_line_add($id, ['description' => 'Inspection', 'qty' => 1, 'rate' => 1000.40, 'gst_pct' => 18]);
    t_eq(books_issue($id), '', 'the invoice is issued');
    $inv = books_invoice($id);
    t_eq([(float)$inv['subtotal'], (float)$inv['cgst'], (float)$inv['sgst'], (float)$inv['round_off'], (float)$inv['total']], [1000.4, 90.04, 90.03, -0.47, 1180.0], 'books: 1000.40 + 180.07 = 1180.47, printed as 1180 with −0.47 round-off');
    $rows = array_values(array_filter(tally_rows_from_books('2097-07-01', '2097-07-31', 'SALES'), fn($r) => (int)$r['id'] === $id));
    t_eq(count($rows), 1, 'the invoice is in the sales export');
    t_eq((float)$rows[0]['round_off'], -0.47, 'the export row carries the round-off');
    t_eq((float)$rows[0]['gst_pct'], 18.0, '...and a GST % derived from tax over taxable');
    $xml = tally_xml($rows, $cfg, 'SALES');
    t_eq($sums($xml), [0.0], 'the SALES voucher balances (entries sum to zero)');
    t_ok(strpos($xml, '<LEDGERNAME>Round Off</LEDGERNAME>') !== false, 'the round-off is its own ledger entry');
    t_ok(preg_match('#<LEDGERNAME>Round Off</LEDGERNAME>\s*<ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>\s*<AMOUNT>-0\.47</AMOUNT>#', $xml) === 1, 'a negative round-off is a 0.47 DEBIT (the party paid less than sales + tax)');
    $csv = tally_csv_rows($rows, 'SALES');
    t_eq($csv[0][6], 'GST %', 'the sales CSV has a GST % column');
    t_eq((string)$csv[1][6], '18', '...filled for a books invoice (it was NULL with a PHP warning)');
    t_eq([$csv[0][10], $csv[1][10]], ['Round off', '-0.47'], '...and a Round off column so the row adds up to its total');
    t_eq(round((float)$csv[1][5] + (float)$csv[1][7] + (float)$csv[1][8] + (float)$csv[1][9] + (float)$csv[1][10], 2), (float)$csv[1][11], 'taxable + CGST + SGST + IGST + round-off = total on the CSV row');

    // ---- an invoice with no round-off has no round-off entry ----------------------------
    $id2 = (int)books_invoice_create(['partner_id' => $pid, 'office_id' => $off, 'invoice_date' => '2097-07-11'])['id'];
    books_line_add($id2, ['description' => 'Whole', 'qty' => 1, 'rate' => 1000, 'gst_pct' => 18]); books_issue($id2);
    $rows2 = array_values(array_filter(tally_rows_from_books('2097-07-01', '2097-07-31', 'SALES'), fn($r) => (int)$r['id'] === $id2));
    $xml2 = tally_xml($rows2, $cfg, 'SALES');
    t_eq($sums($xml2), [0.0], 'a whole-rupee invoice balances too');
    t_ok(strpos($xml2, 'Round Off') === false, '...with no round-off line cluttering it');

    // ---- receipt with TDS, allocated; and a spare on-account amount ----------------------------
    $rc = books_receipt_create(['partner_id' => $pid, 'amount' => 900, 'tds_amount' => 100, 'receipt_date' => '2097-07-20']);
    t_eq(books_allocate((int)$rc['id'], [$id => ['cash' => 900, 'tds' => 100]]), '', 'the receipt is allocated (cash + TDS)');
    $rc2 = books_receipt_create(['partner_id' => $pid, 'amount' => 50, 'receipt_date' => '2097-07-21']);
    $rrows = array_values(array_filter(tally_rows_from_books('2097-07-01', '2097-07-31', 'RECEIPT'), fn($r) => in_array((int)$r['id'], [(int)$rc['id'], (int)$rc2['id']], true)));
    $rx = tally_xml($rrows, $cfg, 'RECEIPT');
    t_eq($sums($rx), [0.0, 0.0], 'both RECEIPT vouchers balance (bank + TDS debits = party credits)');
    $rcsv = tally_csv_rows($rrows, 'RECEIPT');
    t_eq($rcsv[1][1], $rrows[0]['job_code'], 'the receipt CSV names the voucher exactly as the XML does (no RCPT- prefix on a books receipt)');
    t_eq([$rcsv[0][6], $rcsv[1][6]], ['TDS', '100.00'], '...and shows the TDS the voucher carries');

    // ---- credit note -------------------------------------------------------------------
    $cn = books_credit_note(['invoice_id' => $id2, 'reason' => 'RATE', 'subtotal' => 100, 'gst_pct' => 18, 'cn_date' => '2097-07-25']);
    t_ok(empty($cn['err']), 'a credit note is raised');
    $crows = array_values(array_filter(tally_rows_from_books('2097-07-01', '2097-07-31', 'CREDITNOTE'), fn($r) => (int)$r['id'] === (int)$cn['id']));
    t_eq($sums(tally_xml($crows, $cfg, 'CREDITNOTE')), [0.0], 'the CREDIT NOTE voucher balances');

    t_eq($warnings, [], 'no PHP warning anywhere in the export');
} finally {
    restore_error_handler();
    if (isset($state0)) setting_set('tally_state', $state0);
    if ($own && db()->inTransaction()) db()->rollBack();
}
