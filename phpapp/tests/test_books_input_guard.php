<?php
// ============================================================================
//  The invoicing engine refuses figures no accountant would accept, and a
//  credit note can never take an invoice below zero.  (GAP-034 / 035 / 036)
//
//  Found by docs/audit-2026-10/evidence/books-input-probe.php: a negative
//  rate, a 500% GST, a negative receipt and a non-numeric rate all went
//  straight into the ledger; and because the credit-note ceiling added back
//  the notes already raised, an 11,800 invoice took four 5,900 notes.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('books — figures off a form are validated; credit is bounded');
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    books_migrate();
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,state,gstin) VALUES ('Guard Co','Guard Co',1,'ACTIVE','Gujarat','24AAAAA0000A1Z5')")->execute();
    $pid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO offices (name,is_active) VALUES ('Guard HQ',1)")->execute();
    $off = (int)db()->lastInsertId();
    $mk = function () use ($pid, $off) { return (int)books_invoice_create(['partner_id' => $pid, 'office_id' => $off])['id']; };

    // ---- lines -------------------------------------------------------------
    $id = $mk();
    t_eq(books_line_add($id, ['description' => 'ok', 'qty' => 2, 'rate' => 5000, 'gst_pct' => 18]), '', 'control: 2 × 5,000 @ 18% is accepted');
    t_ok(stripos(books_line_add($id, ['description' => 'x', 'qty' => 1, 'rate' => -5000]), 'rate') !== false,      'a negative rate is refused, naming the field');
    t_ok(stripos(books_line_add($id, ['description' => 'x', 'qty' => -1, 'rate' => 5000]), 'quantity') !== false,  'a negative quantity is refused');
    t_ok(stripos(books_line_add($id, ['description' => 'x', 'qty' => 0, 'rate' => 5000]), 'more than zero') !== false, 'a zero quantity is refused');
    t_ok(stripos(books_line_add($id, ['description' => 'x', 'qty' => 1, 'rate' => 100, 'gst_pct' => -18]), 'gst') !== false, 'GST −18% is refused');
    t_ok(stripos(books_line_add($id, ['description' => 'x', 'qty' => 1, 'rate' => 100, 'gst_pct' => 500]), 'gst') !== false, 'GST 500% is refused');
    t_ok(stripos(books_line_add($id, ['description' => 'x', 'qty' => 1, 'rate' => 'abc']), 'must be a number') !== false, 'a non-numeric rate is refused, not silently read as 0');
    t_eq(count(books_lines($id)), 1, 'only the control line exists');
    t_eq((float)books_invoice($id)['total'], 11800.0, 'the invoice totals 11,800 — nothing crept in');
    t_eq(books_line_add($id, ['description' => 'free re-visit', 'qty' => 1, 'rate' => 0]), '', 'a zero-rate line (a free item) is still allowed');

    // ---- receipts -----------------------------------------------------------
    t_eq(books_receipt_create(['partner_id' => $pid, 'amount' => 1000])['err'] ?? '', '', 'control: a 1,000 receipt is accepted');
    t_ok(($e = books_receipt_create(['partner_id' => $pid, 'amount' => -5000, 'tds_amount' => 100])['err'] ?? '') !== '' && stripos($e, 'negative') !== false,
         'amount −5,000 with TDS 100 is refused (it used to pass the "nothing" test)');
    t_ok(stripos(books_receipt_create(['partner_id' => $pid, 'amount' => 5000, 'tds_amount' => -5000])['err'] ?? '', 'tds') !== false, 'a negative TDS is refused');
    t_ok(stripos(books_receipt_create(['partner_id' => $pid, 'amount' => 100, 'bank_charges' => -999])['err'] ?? '', 'bank charges') !== false, 'negative bank charges are refused');
    t_ok(stripos(books_receipt_create(['partner_id' => $pid, 'amount' => 'ten'])['err'] ?? '', 'must be a number') !== false, 'a non-numeric amount is refused');
    t_eq((float)ops_val("SELECT COALESCE(SUM(amount+tds_amount),0) FROM receipts WHERE partner_id=?", [$pid]), 1000.0, 'the customer\'s receipts sum to the control alone');

    // ---- credit notes: inputs ----------------------------------------------------
    $id2 = $mk();
    books_line_add($id2, ['description' => 'ok', 'qty' => 1, 'rate' => 10000, 'gst_pct' => 18]);
    t_eq(books_issue($id2), '', 'an 11,800 invoice is issued');
    t_ok(stripos(books_credit_note(['invoice_id' => $id2, 'reason' => 'RATE', 'subtotal' => 1000, 'gst_pct' => -18])['err'] ?? '', 'gst') !== false, 'a credit note at GST −18% is refused');
    t_ok(stripos(books_credit_note(['invoice_id' => $id2, 'reason' => 'RATE', 'subtotal' => 1000, 'gst_pct' => 900])['err'] ?? '', 'gst') !== false, 'a credit note at GST 900% is refused');

    // ---- credit notes: the ceiling is cumulative ---------------------------------
    t_eq(books_credit_note(['invoice_id' => $id2, 'reason' => 'RATE', 'subtotal' => 5000, 'gst_pct' => 18])['err'] ?? '', '', 'control: a 5,900 note is accepted');
    t_eq(books_credit_note(['invoice_id' => $id2, 'reason' => 'RATE', 'subtotal' => 5000, 'gst_pct' => 18])['err'] ?? '', '', 'a second 5,900 note is accepted — exactly the invoice');
    t_eq(books_settled($id2)['outstanding'], 0.0, 'the invoice now owes nothing');
    $e = books_credit_note(['invoice_id' => $id2, 'reason' => 'RATE', 'subtotal' => 5000, 'gst_pct' => 18])['err'] ?? '';
    t_ok($e !== '' && stripos($e, 'already raised') !== false, 'a THIRD 5,900 note is refused, and the refusal names the notes already raised');
    t_ok(stripos(books_credit_note(['invoice_id' => $id2, 'reason' => 'RATE', 'subtotal' => 1, 'gst_pct' => 18])['err'] ?? '', 'outstanding') !== false, '...so is a 1.18 note');
    t_eq(books_settled($id2)['outstanding'], 0.0, 'the invoice never goes below zero');
    t_eq((int)ops_val("SELECT COUNT(*) FROM credit_notes WHERE invoice_id=?", [$id2]), 2, 'two notes exist, not four');

    // ---- the database-level guards hold even when the PHP pre-checks are bypassed ----
    $id3 = $mk();
    books_line_add($id3, ['description' => 'ok', 'qty' => 1, 'rate' => 10000, 'gst_pct' => 18]); books_issue($id3);
    $rid = (int)books_receipt_create(['partner_id' => $pid, 'amount' => 3000])['id'];
    t_eq(books_alloc_insert($rid, $id3, 'CASH', 3001, 'test', date('c')), 0, 'the allocation INSERT itself refuses 1 more than the receipt holds');
    t_eq(books_alloc_insert($rid, $id3, 'CASH', 3000, 'test', date('c')), 1, '...and writes exactly the free amount');
    t_eq(books_alloc_insert($rid, $id3, 'CASH', 0.5, 'test', date('c')), 0, '...after which even 50 paise more is refused (concurrent-click safety)');
    t_eq(books_settled($id3)['cash'], 3000.0, 'cash on the invoice is 3,000');
    $cn = ['cn_no' => 'CN/T/1', 'fy' => current_fy(), 'partner_id' => $pid, 'partner_name' => 'Guard Co', 'office_id' => $off, 'cn_date' => '2026-10-01',
           'reason' => 'RATE', 'reason_note' => '', 'subtotal' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'created_by' => 't', 'created_at' => date('c')];
    t_eq(books_cn_insert($id3, $cn + ['total' => 8801]), 0, 'the credit-note INSERT itself refuses 1 more than the 8,800 still owed');
    t_eq(books_cn_insert($id3, $cn + ['total' => 8800]), 1, '...and writes exactly what is owed');
    t_eq(books_settled($id3)['outstanding'], 0.0, 'leaving the invoice at zero');

    // ---- allocation through the normal path -------------------------------------------
    $id4 = $mk();
    books_line_add($id4, ['description' => 'ok', 'qty' => 1, 'rate' => 10000, 'gst_pct' => 18]); books_issue($id4);
    $r2 = (int)books_receipt_create(['partner_id' => $pid, 'amount' => 3000])['id'];
    t_eq(books_allocate($r2, [$id4 => ['cash' => 3000]]), '', 'control: 3,000 allocated to an 11,800 invoice');
    t_ok(books_allocate($r2, [$id4 => ['cash' => 1]]) !== '', 'one rupee more than arrived is refused');
    t_eq(books_settled($id4)['cash'], 3000.0, 'and nothing extra was written');

    // ---- the invoice screen says so when history left an invoice over-credited -------------
    $view = file_get_contents(__DIR__ . '/../views/ops/invoice_detail.php');
    t_ok(strpos($view, '$ivOut < -1.0') !== false && strpos($view, 'Credited beyond its value') !== false,
         'the invoice screen flags a negative outstanding in words instead of calling it "Paid in full"');
    t_ok(strpos($view, '$ivOut < -1.0') < strpos($view, "=== 'PAID'"), '...and checks it BEFORE the PAID branch that would hide it');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
