<?php
// ============================================================================
//  (1) An invoice settled beyond its value is FOUND on a screen, not by a
//      database query: register panel, register filter, home "needs attention".
//  (2) The owner's rule on bank charges (Q-J, 2026-10-19): the customer bears
//      them — they never settle an invoice, the screens say so, and the default
//      quotation terms say so.  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('over-credited invoices are visible; bank charges stay with the customer');
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    books_migrate();
    t_as_admin();
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('Over Co','Over Co',1,'ACTIVE')")->execute(); $pid = (int)db()->lastInsertId();
    $inv = function ($no, $total) use ($pid) {
        db()->prepare("INSERT INTO invoices (invoice_no, partner_id, partner_name, status, invoice_date, total, created_at) VALUES (?,?,?,'ISSUED','2096-07-10',?,?)")
            ->execute([$no, $pid, 'Over Co', $total, date('c')]);
        return (int)db()->lastInsertId();
    };
    $cn = function ($invId, $total, $no) use ($pid) {   // history: written directly, as the old ceiling allowed
        db()->prepare("INSERT INTO credit_notes (cn_no, invoice_id, partner_id, partner_name, cn_date, reason, subtotal, total, status, created_at) VALUES (?,?,?,?,'2096-07-20','RATE',?,?,'ISSUED',?)")
            ->execute([$no, $invId, $pid, 'Over Co', $total, $total, date('c')]);
    };
    $clean = $inv('INV/OC/1', 11800); $cn($clean, 5900, 'CN/OC/1');
    $bad   = $inv('INV/OC/2', 11800); $cn($bad, 5900, 'CN/OC/2'); $cn($bad, 5900, 'CN/OC/3'); $cn($bad, 5900, 'CN/OC/4');
    $exact = $inv('INV/OC/3', 1000);  $cn($exact, 1000, 'CN/OC/5');

    $over = books_overcredited();
    $ids = array_map(fn($o) => (int)$o['id'], $over);
    t_ok(in_array($bad, $ids, true), 'an invoice credited 17,700 against 11,800 is found');
    t_ok(!in_array($clean, $ids, true) && !in_array($exact, $ids, true), 'a part-credited invoice and an exactly-credited one are not');
    $row = array_values(array_filter($over, fn($o) => (int)$o['id'] === $bad))[0];
    t_eq(round((float)$row['excess'], 2), 5900.0, '...and the excess is stated: 5,900 over');
    $fl = array_map(fn($r) => (int)$r['id'], books_invoices(['overcredited' => 1]));
    t_ok(in_array($bad, $fl, true) && !in_array($clean, $fl, true), 'the register filter "Credited beyond their value" lists exactly those');
    t_eq(books_invoices_count(['overcredited' => 1]), count($fl), '...and its count agrees with its rows');
    $att = array_values(array_filter(attention_summary(), fn($a) => $a['key'] === 'inv_overcredit'));
    t_ok($att && $att[0]['url'] === '/invoices?f=overcredited' && (int)$att[0]['n'] >= 1, 'accounts see it on the home screen, linked to the filtered register');
    $ui = file_get_contents(__DIR__ . '/../lib/booksui.php');
    t_ok(strpos($ui, "\$f === 'overcredited'") !== false, 'the register route honours ?f=overcredited');
    $view = file_get_contents(__DIR__ . '/../views/ops/invoices.php');
    t_ok(strpos($view, 'Invoices credited beyond their value') !== false && strpos($view, 'books_overcredited()') !== false, 'the register shows the red panel when there are any');

    // a clean ledger says nothing at all
    db()->prepare("DELETE FROM credit_notes WHERE invoice_id=?")->execute([$bad]);
    t_ok(!in_array($bad, array_map(fn($o) => (int)$o['id'], books_overcredited()), true), 'once corrected, the invoice drops off');

    // ---- bank charges ----------------------------------------------------------------
    $i2 = (int)books_invoice_create(['partner_id' => $pid])['id'];
    db()->prepare("UPDATE invoices SET status='ISSUED', invoice_no='INV/BC/1', total=10000 WHERE id=?")->execute([$i2]);
    $rc = books_receipt_create(['partner_id' => $pid, 'amount' => 9900, 'bank_charges' => 100]);
    t_eq(books_allocate((int)$rc['id'], [$i2 => ['cash' => 9900]]), '', 'the 9,900 that arrived is matched');
    t_eq(books_allocate((int)$rc['id'], [$i2 => ['cash' => 100]]) !== '', true, 'the 100 the bank kept cannot be matched as cash');
    t_eq(books_settled($i2)['outstanding'], 100.0, 'the invoice stays 100 short — the customer bears the bank charge');
    t_eq(ops_one("SELECT status FROM invoices WHERE id=?", [$i2])['status'], 'PART_PAID', '...so it reads Part paid, not Paid');
    $rf = file_get_contents(__DIR__ . '/../views/ops/receipt_form.php');
    t_ok(strpos($rf, "customer's to bear") !== false || strpos($rf, "customer\\'s to bear") !== false, 'the receipt form says bank charges are the customer\'s and do not settle the invoice');
    $rd = file_get_contents(__DIR__ . '/../views/ops/receipt_detail.php');
    t_ok(strpos($rd, 'The bank kept') !== false && strpos($rd, 'not a credit note') !== false, 'the receipt page explains the shortfall and why absorbing it is not a credit note');
    $prev = setting_get('quote_terms', ''); setting_set('quote_terms', '');
    t_ok(strpos(crm_default_terms(), "bank and remittance charges are to the payer's account") !== false, 'the built-in quotation terms carry the bank-charges clause');
    setting_set('quote_terms', 'Our own terms.');
    t_eq(crm_default_terms(), 'Our own terms.', 'terms a company already saved are left exactly as written');
    setting_set('quote_terms', $prev);
} finally { t_as_nobody(); if ($own && db()->inTransaction()) db()->rollBack(); }
