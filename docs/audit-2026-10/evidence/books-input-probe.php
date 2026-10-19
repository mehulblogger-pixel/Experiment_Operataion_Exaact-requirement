<?php
/**
 * EXAACT audit — Phase F, the ERP's own invoicing engine (lib/books.php).
 *
 * Question: does the engine accept a figure that no accountant would, when it
 * arrives straight off a form? Each case has a CONTROL (the sane figure is
 * accepted) next to the PROBE (the insane one), so "refused" is proven to be
 * the validation and not a broken fixture.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/books-input-probe.php
 *
 * BEFORE (19 Oct 2026, first run): 15 of 16 probes ACCEPTED -- negative rate,
 *   negative and zero quantity, GST -18% and 500%, rate "abc" (read as 0), a
 *   receipt of -5,000, negative TDS and bank charges, credit notes at -18% and
 *   900% -- and three credit notes totalling 12,000 landed on an 11,800 invoice
 *   (outstanding -200). GAP-034 / GAP-035.
 * AFTER (same day, lib/books.php validated + cumulative ceiling): every probe
 *   refused, every control accepted; see the guard rail
 *   tests/test_books_input_guard.php (38 assertions).
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';
books_migrate();
db()->beginTransaction();
function say($label, $want, $got) {
    $flag = ($want === 'refused') === ($got !== '') ? 'as expected' : '*** DEFECT ***';
    printf("  %-62s want %-8s got %-9s %s\n", $label, $want, $got === '' ? 'accepted' : 'refused', $flag);
}
db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,state,gstin) VALUES ('Probe Co','Probe Co',1,'ACTIVE','Gujarat','24AAAAA0000A1Z5')")->execute();
$pid = (int)db()->lastInsertId();
db()->prepare("INSERT INTO offices (name,is_active) VALUES ('Probe HQ',1)")->execute();
$off = (int)db()->lastInsertId();
$inv = books_invoice_create(['partner_id' => $pid, 'office_id' => $off]);
$id = (int)$inv['id'];

echo "A. invoice lines (books_line_add)\n";
say('control: qty 2 × rate 5000 @ 18%',           'accepted', books_line_add($id, ['description' => 'ok', 'qty' => 2, 'rate' => 5000, 'gst_pct' => 18]));
say('probe: negative rate (−5000)',                'refused',  books_line_add($id, ['description' => 'neg rate', 'qty' => 1, 'rate' => -5000, 'gst_pct' => 18]));
say('probe: negative qty (−1)',                    'refused',  books_line_add($id, ['description' => 'neg qty', 'qty' => -1, 'rate' => 5000, 'gst_pct' => 18]));
say('probe: zero qty',                             'refused',  books_line_add($id, ['description' => 'zero qty', 'qty' => 0, 'rate' => 5000, 'gst_pct' => 18]));
say('probe: GST −18%',                             'refused',  books_line_add($id, ['description' => 'neg gst', 'qty' => 1, 'rate' => 100, 'gst_pct' => -18]));
say('probe: GST 500%',                             'refused',  books_line_add($id, ['description' => 'huge gst', 'qty' => 1, 'rate' => 100, 'gst_pct' => 500]));
say('probe: rate "abc" (non-numeric)',             'refused',  books_line_add($id, ['description' => 'nan', 'qty' => 1, 'rate' => 'abc', 'gst_pct' => 18]));
$i = books_invoice($id);
printf("  → invoice now totals %s from %d line(s)\n", fmoney($i['total']), count(books_lines($id)));

echo "B. receipts (books_receipt_create)\n";
say('control: amount 1000, tds 0',                 'accepted', books_receipt_create(['partner_id' => $pid, 'amount' => 1000])['err'] ?? '');
say('probe: amount −5000 with tds 100',            'refused',  books_receipt_create(['partner_id' => $pid, 'amount' => -5000, 'tds_amount' => 100])['err'] ?? '');
say('probe: amount 5000 with tds −5000',           'refused',  books_receipt_create(['partner_id' => $pid, 'amount' => 5000, 'tds_amount' => -5000])['err'] ?? '');
say('probe: bank charges −999',                    'refused',  books_receipt_create(['partner_id' => $pid, 'amount' => 100, 'bank_charges' => -999])['err'] ?? '');
$sum = ops_val("SELECT COALESCE(SUM(amount+tds_amount),0) FROM receipts WHERE partner_id=?", [$pid]);
printf("  → receipts for this customer now sum to %s (control alone would be ₹1,000)\n", fmoney($sum));
printf("  → books_outstanding on_account = %s\n", fmoney(books_outstanding($pid)['on_account']));

echo "C. credit notes (books_credit_note)\n";
// a clean issued invoice to credit against
$inv2 = books_invoice_create(['partner_id' => $pid, 'office_id' => $off]); $id2 = (int)$inv2['id'];
books_line_add($id2, ['description' => 'ok', 'qty' => 1, 'rate' => 10000, 'gst_pct' => 18]);
$e = books_issue($id2); echo "  (issue: " . ($e === '' ? 'ok' : $e) . ")\n";
say('control: credit 1000 @ 18%',                  'accepted', books_credit_note(['invoice_id' => $id2, 'reason' => 'RATE', 'subtotal' => 1000, 'gst_pct' => 18])['err'] ?? '');
say('probe: credit 1000 @ −18% (tax goes negative)','refused', books_credit_note(['invoice_id' => $id2, 'reason' => 'RATE', 'subtotal' => 1000, 'gst_pct' => -18])['err'] ?? '');
say('probe: credit 1000 @ 900%',                   'refused',  books_credit_note(['invoice_id' => $id2, 'reason' => 'RATE', 'subtotal' => 1000, 'gst_pct' => 900])['err'] ?? '');
foreach (ops_all("SELECT cn_no, subtotal, cgst, sgst, igst, total FROM credit_notes WHERE invoice_id=?", [$id2]) as $cn)
    printf("  → %s sub %s cgst %s sgst %s igst %s total %s\n", $cn['cn_no'], $cn['subtotal'], $cn['cgst'], $cn['sgst'], $cn['igst'], $cn['total']);

echo "D. allocation arithmetic (books_allocate)\n";
$rc = books_receipt_create(['partner_id' => $pid, 'amount' => 3000]); $rid = (int)$rc['id'];
say('control: allocate 3000 cash to the 11,800 invoice', 'accepted', books_allocate($rid, [$id2 => ['cash' => 3000]]));
say('probe: allocate −500 cash (would free money)',  'refused', books_allocate($rid, [$id2 => ['cash' => -500]]));
say('probe: allocate 1 more than arrived',           'refused', books_allocate($rid, [$id2 => ['cash' => 1]]));
printf("  → settled on invoice: %s\n", json_encode(books_settled($id2)));
db()->rollBack();
