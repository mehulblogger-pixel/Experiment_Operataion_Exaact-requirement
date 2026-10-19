<?php
// ============================================================================
//  Money Books holds ON ACCOUNT for a customer must be SEEN in the ERP.
//  GAP-028 stopped the Books receiver dropping it; without this, the figure
//  would only have moved from lost to hidden. (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('books — money on account is stored and shown');
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    books_migrate(); booksbridge_migrate();
    db()->prepare("INSERT INTO invoices (invoice_no, partner_name, invoice_date, status, total, created_at) VALUES (?,?,?,?,?,?)")
       ->execute(['INV/T/1', 'Acme', '2026-10-01', 'ISSUED', 10000, date('c')]);
    $inv = (int)db()->lastInsertId();
    t_eq(books_apply_status($inv, ['irn' => 'IRN-X', 'status' => 'PAID', 'paid' => 10000, 'outstanding' => 0, 'on_account' => 5000]), true,
         'a Books status carrying on_account is applied');
    $row = ops_one("SELECT books_status, books_paid, books_outstanding, books_on_account FROM invoices WHERE id=?", [$inv]);
    t_eq((float)$row['books_on_account'], 5000.0, 'the on-account figure is stored on the invoice');
    t_eq([$row['books_status'], (float)$row['books_paid']], ['PAID', 10000.0], '...alongside the figures it always stored');
    t_eq(books_apply_status($inv, ['irn' => 'IRN-X', 'status' => 'PAID', 'paid' => 10000, 'outstanding' => 0]), true,
         'a reply WITHOUT on_account (an older Books) still applies');
    t_eq((float)ops_val("SELECT books_on_account FROM invoices WHERE id=?", [$inv]), 0.0, '...and reads as nothing on account, not as a stale figure');
    $view = file_get_contents(__DIR__ . '/../views/ops/invoice_detail.php');
    t_ok(strpos($view, "books_on_account") !== false && strpos($view, 'held on account') !== false,
         'the invoice screen prints the held amount in plain words');
    t_ok(strpos($view, "> 0.005") !== false, '...only when there is one — a zero is not announced');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
