<?php
/**
 * EXAACT audit — Phase F, the Tally export (lib/tally.php).
 *
 * A Tally voucher must balance: its ledger entries sum to zero, or Tally refuses
 * the import. Each voucher kind is built from a books record and the entries are
 * summed; the CSV is produced beside it and PHP warnings are captured.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/tally-balance-probe.php
 *
 * BEFORE (19 Oct 2026): the sales voucher for an invoice with a round-off summed to
 *   0.47, not 0 -- Tally refuses it (GAP-044); the sales CSV's GST % cell was NULL
 *   with a PHP warning; the receipt CSV named the voucher RCPT-RCP/.. while the XML
 *   said RCP/.. and had no TDS column; bank charges recorded on the receipt form
 *   reached nothing (Q-J). Receipt and credit-note vouchers balanced.
 * AFTER: every voucher sums to 0; the round-off is its own ledger entry; the CSVs
 *   carry GST %, round-off, the XML's voucher number and the TDS.
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';
books_migrate(); tally_migrate();
db()->beginTransaction();
$warnings = [];
set_error_handler(function ($no, $str) use (&$warnings) { $warnings[] = $str; return true; });
function voucher_sums($xml) {
    // one sum per <VOUCHER> block
    $out = [];
    foreach (explode('<VOUCHER ', $xml) as $i => $block) {
        if ($i === 0) continue;
        preg_match_all('#<ALLLEDGERENTRIES\.LIST>.*?<AMOUNT>(-?[\d.]+)</AMOUNT>#s', $block, $m);
        $out[] = round(array_sum(array_map('floatval', $m[1])), 2);
    }
    return $out;
}
try {
    db()->prepare("INSERT INTO offices (name,is_active) VALUES ('Tally HQ',1)")->execute(); $off = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,state,gstin) VALUES ('Tally Co','Tally Co',1,'ACTIVE','Gujarat','24AAAAA0000A1Z5')")->execute(); $pid = (int)db()->lastInsertId();
    setting_set('tally_state', 'Gujarat');
    $cfg = tally_settings();

    echo "A. SALES — an invoice whose GST produces paise, rounded to the rupee on the document\n";
    $id = (int)books_invoice_create(['partner_id' => $pid, 'office_id' => $off, 'invoice_date' => '2097-07-10'])['id'];
    books_line_add($id, ['description' => 'Inspection', 'qty' => 1, 'rate' => 1000.40, 'gst_pct' => 18]);
    echo "  issue: " . (books_issue($id) ?: 'ok') . "\n";
    $inv = books_invoice($id);
    printf("  books: subtotal %s  cgst %s  sgst %s  round_off %s  total %s\n", $inv['subtotal'], $inv['cgst'], $inv['sgst'], $inv['round_off'], $inv['total']);
    $rows = array_values(array_filter(tally_rows_from_books('2097-07-01', '2097-07-31', 'SALES'), fn($r) => (int)$r['id'] === $id));
    $xml = tally_xml($rows, $cfg, 'SALES');
    $sums = voucher_sums($xml);
    printf("  sales voucher entries sum to: %s  → %s\n", json_encode($sums), abs($sums[0]) < 0.005 ? 'balanced' : '*** UNBALANCED — Tally rejects this voucher ***');
    $csv = tally_csv_rows($rows, 'SALES');
    printf("  CSV 'GST %%' cell: %s   warnings so far: %d%s\n", var_export($csv[1][6], true), count($warnings), $warnings ? ' (' . $warnings[0] . ')' : '');

    echo "B. RECEIPT — 900 in the bank + 100 TDS, allocated to the invoice; and one with bank charges\n";
    $rc = books_receipt_create(['partner_id' => $pid, 'amount' => 900, 'tds_amount' => 100, 'receipt_date' => '2097-07-20']);
    books_allocate((int)$rc['id'], [$id => ['cash' => 900, 'tds' => 100]]);
    $rc2 = books_receipt_create(['partner_id' => $pid, 'amount' => 150, 'bank_charges' => 30, 'receipt_date' => '2097-07-21']);
    $rrows = array_values(array_filter(tally_rows_from_books('2097-07-01', '2097-07-31', 'RECEIPT'), fn($r) => in_array((int)$r['id'], [(int)$rc['id'], (int)$rc2['id']], true)));
    $rx = tally_xml($rrows, $cfg, 'RECEIPT');
    printf("  receipt vouchers sum to: %s\n", json_encode(voucher_sums($rx)));
    printf("  bank charges 30 appear in the receipt XML: %s\n", strpos($rx, '30.00') !== false ? 'yes' : 'NO — recorded on the form, exported nowhere');
    $rcsv = tally_csv_rows($rrows, 'RECEIPT');
    printf("  receipt CSV voucher column: %s   XML voucher number: %s   CSV has a TDS column: %s\n", $rcsv[1][1], $rrows[0]['job_code'], in_array('TDS', $rcsv[0], true) ? 'yes' : 'no');

    echo "C. CREDIT NOTE — 100 @ 18%\n";
    $cn = books_credit_note(['invoice_id' => $id, 'reason' => 'RATE', 'subtotal' => 100, 'gst_pct' => 18, 'cn_date' => '2097-07-25']);
    $crows = array_values(array_filter(tally_rows_from_books('2097-07-01', '2097-07-31', 'CREDITNOTE'), fn($r) => (int)$r['id'] === (int)$cn['id']));
    printf("  credit-note voucher sums to: %s\n", json_encode(voucher_sums(tally_xml($crows, $cfg, 'CREDITNOTE'))));
    printf("warnings captured: %d\n", count($warnings));
    foreach (array_unique($warnings) as $w) echo "  - $w\n";
} finally { restore_error_handler(); db()->rollBack(); }
