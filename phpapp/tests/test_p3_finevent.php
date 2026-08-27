<?php
// Phase 3 §27 — the financial-event stream. A read-only projection that turns the existing money records
// (accepted quotations, issued/cancelled invoices, receipts, credit notes) into ONE uniform, time-ordered
// stream with a rollup. It is a read model over the books ledger — it cannot drift from it, and it writes
// nothing. Self-contained: seeds its own partner + money records in a rolled-back transaction.
t_section('Phase 3 §27 — financial-event stream (read-only projection)');

t_ok(function_exists('financial_events') && function_exists('financial_rollup') && function_exists('financial_events_render'),
     'the financial-event helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/finevent.php'") !== false, 'the finevent lib is loaded by the front controller');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$prevUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('crm_migrate')) crm_migrate();
    if (function_exists('books_migrate')) books_migrate();
    $pdo = db();
    // Master session so scope is ALL (the projection is office-scoped).
    $pdo->prepare("INSERT INTO users (username, is_superuser, is_active) VALUES ('t27','1',1)")->execute();
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); if (function_exists('ua')) ua(true);

    $pid = 909027;   // one client, referenced across every money record
    // Accepted quote (committed) — client_id.
    $pdo->prepare("INSERT INTO quotations (quote_no, client_id, client_name, total_amount, status, accepted_date, office_id, sbu) VALUES ('Q-27',?, 'Acme Metals', 100000, 'ACCEPTED', '2026-03-01', 1, 'NDT')")->execute([$pid]);
    // Two issued invoices + one cancelled — partner_id.
    $pdo->prepare("INSERT INTO invoices (invoice_no, partner_id, partner_name, total, status, invoice_date, office_id) VALUES ('INV-27a',?, 'Acme Metals', 60000, 'ISSUED', '2026-03-10', 1)")->execute([$pid]);
    $pdo->prepare("INSERT INTO invoices (invoice_no, partner_id, partner_name, total, status, invoice_date, office_id) VALUES ('INV-27b',?, 'Acme Metals', 40000, 'ISSUED', '2026-03-20', 1)")->execute([$pid]);
    $pdo->prepare("INSERT INTO invoices (invoice_no, partner_id, partner_name, total, status, invoice_date, cancelled_at, office_id) VALUES ('INV-27c',?, 'Acme Metals', 15000, 'CANCELLED', '2026-03-22', '2026-03-25', 1)")->execute([$pid]);
    // A draft invoice must NOT appear.
    $pdo->prepare("INSERT INTO invoices (invoice_no, partner_id, partner_name, total, status, invoice_date, office_id) VALUES ('INV-27d',?, 'Acme Metals', 99999, 'DRAFT', '2026-03-28', 1)")->execute([$pid]);
    // A receipt (cash in).
    $pdo->prepare("INSERT INTO receipts (receipt_no, partner_id, partner_name, amount, receipt_date, office_id) VALUES ('RC-27',?, 'Acme Metals', 50000, '2026-04-01', 1)")->execute([$pid]);
    // A credit note.
    $pdo->prepare("INSERT INTO credit_notes (cn_no, partner_id, partner_name, total, status, cn_date, office_id) VALUES ('CN-27',?, 'Acme Metals', 5000, 'ISSUED', '2026-04-05', 1)")->execute([$pid]);

    $ev = financial_events(['partner_id' => $pid]);
    $kinds = array_column($ev, 'kind');
    t_ok(in_array('QUOTE_ACCEPTED', $kinds, true), 'the accepted quote is in the stream');
    t_eq(count(array_filter($kinds, fn($k) => $k === 'INVOICE_ISSUED')), 2, 'both issued invoices are in the stream');
    t_ok(in_array('INVOICE_CANCELLED', $kinds, true), 'the cancelled invoice appears as a reversal event');
    t_ok(in_array('RECEIPT_RECEIVED', $kinds, true), 'the receipt is in the stream');
    t_ok(in_array('CREDIT_NOTE', $kinds, true), 'the credit note is in the stream');
    t_ok(!in_array(99999.0, array_column($ev, 'amount'), true), 'a DRAFT invoice is excluded');

    // Newest first.
    t_ok($ev[0]['date'] >= $ev[count($ev) - 1]['date'], 'events are ordered newest-first');

    // The rollup adds up to the truth.
    $r = financial_rollup(['partner_id' => $pid]);
    t_eq(round($r['committed'], 2), 100000.0, 'committed = the accepted quote');
    t_eq(round($r['billed'], 2), 100000.0, 'billed = the two issued invoices');
    t_eq(round($r['cancelled'], 2), 15000.0, 'cancelled = the reversed invoice');
    t_eq(round($r['net_billed'], 2), 85000.0, 'net billed = issued − cancelled');
    t_eq(round($r['received'], 2), 50000.0, 'received = the receipt');
    t_eq(round($r['credited'], 2), 5000.0, 'credited = the credit note');
    t_eq(round($r['outstanding'], 2), 30000.0, 'outstanding = net billed − received − credited');

    // Date filter narrows the window.
    $march = financial_events(['partner_id' => $pid, 'from' => '2026-03-01', 'to' => '2026-03-31']);
    t_ok(count($march) === 4, 'the date filter keeps only March events (quote + 2 issued + 1 cancelled)');

    // Another partner sees none of Acme's events.
    t_ok(count(financial_events(['partner_id' => 111111])) === 0, 'the partner filter isolates one client');
} finally {
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}

// wiring
$v = @file_get_contents(__DIR__ . '/../views/ops/customer360.php');
t_ok($v && strpos($v, 'financial_events_render') !== false, 'the client-360 screen shows the money timeline');
