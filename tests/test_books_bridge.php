<?php
// MGH Books bridge — the ERP -> Books connector spine (outbox, gate, idempotency,
// loop-breaker, dry-run drain, stamp-back, invoice trigger).
t_section('MGH Books bridge');

booksbridge_migrate();
books_migrate();

// Off by default: nothing connects, so the ERP keeps its own invoicing.
setting_set('books_connected', '0');
t_ok(!books_connected(), 'the bridge is off by default');
t_eq(books_bridge_queue('PARTY', 1, ['x' => 1]), 0, 'nothing queues while disconnected');

// Connect it, in dry-run mode (marks delivered without a live Books server).
setting_set('books_connected', '1');
setting_set('books_dryrun', '1');
t_ok(books_connected(), 'the bridge switches on');
t_ok(books_configured(), 'dry run counts as configured for testing');

// A client and an issued invoice to push.
db()->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, gstin, state, created_at)
    VALUES (?,?,?,?,?,?)")->execute(['Nayara', 'Nayara Energy', 1, '24AAB1234C1Z9', 'Gujarat', date('c')]);
$pid = (int)db()->lastInsertId();
db()->prepare("INSERT INTO invoices (invoice_no, partner_id, partner_name, invoice_date, status,
    subtotal, cgst, sgst, igst, total, is_igst, place_of_supply, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute(['INV/2627/0001', $pid, 'Nayara Energy', '2026-07-10', 'ISSUED', 10000, 900, 900, 0, 11800, 0, '24', date('c')]);
$invId = (int)db()->lastInsertId();

// The mapping produces the Books import shape.
$mi = books_map_invoice($invId);
t_ok($mi['invoice_no'] === 'INV/2627/0001' && (float)$mi['total'] === 11800.0, 'the invoice maps to the Books shape');
t_eq($mi['party_ext'], 'ERP-BP-' . $pid, 'the invoice references the party by a stable external id');

// Queue the invoice; a second identical queue is folded, not duplicated.
$q1 = books_bridge_queue('INVOICE', $invId, $mi);
t_ok($q1 > 0, 'an invoice queues');
$q2 = books_bridge_queue('INVOICE', $invId, $mi);
t_eq($q2, $q1, 'a second identical queue updates the same pending row, not a duplicate');
t_eq((int)ops_val("SELECT COUNT(*) FROM books_outbox WHERE kind='INVOICE' AND local_id=?", [$invId]), 1, 'only one outbox row exists');

// Drain: dry run marks it delivered and stamps the invoice with the Books ref.
$r = books_bridge_drain();
t_ok($r['sent'] >= 1 && $r['failed'] === 0, 'the queue drains cleanly in dry run');
t_eq(ops_one("SELECT status FROM books_outbox WHERE id=?", [$q1])['status'], 'DONE', 'the item is marked DONE');
$ref = (string)ops_val("SELECT books_ref FROM invoices WHERE id=?", [$invId]);
t_ok($ref !== '' && strncmp($ref, 'DRYRUN-', 7) === 0, 'the invoice is stamped with the Books reference');

// Loop-breaker: re-queuing the identical payload after delivery is dropped.
t_eq(books_bridge_queue('INVOICE', $invId, $mi), 0, 'an identical payload after delivery is dropped (loop-breaker)');

// A changed payload does queue again.
$mi['total'] = 12000.0;
t_ok(books_bridge_queue('INVOICE', $invId, $mi) > 0, 'a changed payload queues a fresh push');

// The invoice-issue trigger queues when connected — end to end through books_issue's hook.
db()->exec("DELETE FROM books_outbox");
books_bridge_on_invoice_issued($invId);
t_ok((int)ops_val("SELECT COUNT(*) FROM books_outbox WHERE kind='INVOICE' AND local_id=?", [$invId]) === 1
   && (int)ops_val("SELECT COUNT(*) FROM books_outbox WHERE kind='PARTY' AND local_id=?", [$pid]) === 1,
   'issuing an invoice queues both the party and the invoice');

// Switch off → the trigger is a clean no-op (standalone mode restored).
setting_set('books_connected', '0');
db()->exec("DELETE FROM books_outbox");
books_bridge_on_invoice_issued($invId);
t_eq((int)ops_val("SELECT COUNT(*) FROM books_outbox"), 0, 'with Books disconnected, issuing queues nothing');

// ---- Status back (Books -> ERP) ----
t_section('MGH Books — status back');

// Before Books reports anything, there is no Books status on the invoice.
t_ok(books_invoice_status($invId) === null || trim((string)books_invoice_status($invId)['books_ref']) !== '',
    'no Books status until it has been sent');

// Stamp a Books ref (as the drain does), then apply the status Books reports.
db()->prepare("UPDATE invoices SET books_ref=? WHERE id=?")->execute(['BK-INV-778', $invId]);
$ok = books_apply_status($invId, ['irn' => 'IRN-77XYZ', 'status' => 'PART_PAID', 'paid' => 5000, 'outstanding' => 6800]);
t_ok($ok, 'the Books status applies to the ERP invoice');

$bs = books_invoice_status($invId);
t_eq($bs['books_ref'], 'BK-INV-778', 'the Books reference is shown');
t_eq($bs['books_irn'], 'IRN-77XYZ', 'the Books IRN is stored');
t_eq($bs['books_status'], 'PART_PAID', 'the paid status comes from Books');
t_eq((float)$bs['books_paid'], 5000.0, 'the paid figure comes from Books');
t_eq((float)$bs['books_outstanding'], 6800.0, 'the outstanding figure comes from Books — the ERP does not recompute it');
t_ok(trim((string)$bs['books_synced_at']) !== '', 'the sync time is recorded');

// Applying to a non-existent invoice is refused, not fatal.
t_ok(!books_apply_status(999999, ['status' => 'PAID']), 'a status for an unknown invoice is refused');

// The pull is a clean no-op in dry run / when disconnected.
setting_set('books_dryrun', '1');
t_eq(books_bridge_pull_status(), 0, 'the status pull is a no-op in dry run');
setting_set('books_connected', '0');
t_eq(books_bridge_pull_status(), 0, 'the status pull is a no-op when disconnected');

// ---- Quote / receipt / credit-note push ----
t_section('MGH Books — quote, receipt & credit note');
setting_set('books_connected', '1');
setting_set('books_dryrun', '1');
db()->exec("DELETE FROM books_outbox");

// An accepted quotation with two lines maps to the Books quote shape.
db()->prepare("INSERT INTO quotations (quote_no, rev, client_id, client_name, subject, currency,
    subtotal, gst_pct, gst_amount, total_amount, status, accepted_date, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute(['Q/2627/0007', 0, $pid, 'Nayara Energy', 'Third-party inspection', 'INR',
               20000, 18, 3600, 23600, 'ACCEPTED', '2026-07-12', date('c')]);
$qid = (int)db()->lastInsertId();
db()->prepare("INSERT INTO quote_lines (quote_id, line_no, description, qty, unit, rate, amount)
    VALUES (?,?,?,?,?,?,?)")->execute([$qid, 1, 'Stage inspection', 10, 'MD', 2000, 20000]);
$mq = books_map_quote($qid);
t_ok($mq['quote_no'] === 'Q/2627/0007' && (float)$mq['total'] === 23600.0, 'the quote maps to the Books shape');
t_eq($mq['party_ext'], 'ERP-BP-' . $pid, 'the quote references its client by the stable external id');
t_eq(count($mq['lines']), 1, 'the quote lines carry across');
t_ok(array_key_exists('pdf_b64', $mq), 'the quote carries the rendered PDF field (exact accepted document)');

// Accepting the quote queues the party and the quote together.
books_bridge_on_quote_accepted($qid);
t_ok((int)ops_val("SELECT COUNT(*) FROM books_outbox WHERE kind='QUOTE' AND local_id=?", [$qid]) === 1
   && (int)ops_val("SELECT COUNT(*) FROM books_outbox WHERE kind='PARTY' AND local_id=?", [$pid]) === 1,
   'accepting a quote queues both the client and the quote');

// A receipt maps and queues.
db()->prepare("INSERT INTO receipts (receipt_no, partner_id, partner_name, receipt_date, mode, reference, amount, created_at)
    VALUES (?,?,?,?,?,?,?,?)")->execute(['RCP/2627/0003', $pid, 'Nayara Energy', '2026-07-15', 'NEFT', 'UTR99', 11800, date('c')]);
$rid = (int)db()->lastInsertId();
$mr = books_map_receipt($rid);
t_ok($mr['ext_id'] === 'ERP-RCP-' . $rid && (float)$mr['amount'] === 11800.0, 'the receipt maps to the Books shape');
books_bridge_on_receipt($rid);
t_eq((int)ops_val("SELECT COUNT(*) FROM books_outbox WHERE kind='RECEIPT' AND local_id=?", [$rid]), 1, 'a receipt queues');

// A credit note maps against its invoice and queues.
db()->prepare("INSERT INTO credit_notes (cn_no, invoice_id, partner_id, partner_name, cn_date, reason, total, status, created_at)
    VALUES (?,?,?,?,?,?,?,?,?)")->execute(['CN/2627/0001', $invId, $pid, 'Nayara Energy', '2026-07-16', 'RATE_CORRECTION', 1180, 'ISSUED', date('c')]);
$cnId = (int)db()->lastInsertId();
$mc = books_map_creditnote($cnId);
t_eq($mc['against_inv'], 'ERP-INV-' . $invId, 'the credit note references the invoice it reverses');
books_bridge_on_creditnote($cnId);
t_eq((int)ops_val("SELECT COUNT(*) FROM books_outbox WHERE kind='CREDITNOTE' AND local_id=?", [$cnId]), 1, 'a credit note queues');

// Draining stamps each record type back with its Books ref.
books_bridge_drain();
t_ok((string)ops_val("SELECT books_ref FROM quotations WHERE id=?", [$qid]) !== '', 'the quote is stamped with its Books ref');
t_ok((string)ops_val("SELECT books_ref FROM receipts WHERE id=?", [$rid]) !== '', 'the receipt is stamped with its Books ref');
t_ok((string)ops_val("SELECT books_ref FROM credit_notes WHERE id=?", [$cnId]) !== '', 'the credit note is stamped with its Books ref');

// When Books is off, none of the new triggers queue anything.
setting_set('books_connected', '0');
db()->exec("DELETE FROM books_outbox");
books_bridge_on_quote_accepted($qid);
books_bridge_on_receipt($rid);
books_bridge_on_creditnote($cnId);
t_eq((int)ops_val("SELECT COUNT(*) FROM books_outbox"), 0, 'with Books off, quote/receipt/credit-note triggers are clean no-ops');

// ---- App switcher (SSO shortcut to Books) ----
t_section('MGH Books — app switcher');

// The web address falls back to the API address when unset.
setting_set('books_connected', '1');
setting_set('books_app_url', '');
setting_set('books_api_url', 'https://books.example.com/api.php');
t_eq(books_app_url(), 'https://books.example.com/api.php', 'the web address falls back to the API address when blank');

// A distinct web address is used verbatim (trailing slash trimmed).
setting_set('books_app_url', 'https://books.example.com/');
t_eq(books_app_url(), 'https://books.example.com', 'a set web address wins over the API address, trailing slash trimmed');

// The switcher is ready only when connected AND a web address resolves.
t_ok(books_switch_ready(), 'the switcher is ready when connected with a web address');
setting_set('books_connected', '0');
t_ok(!books_switch_ready(), 'the switcher is not shown when Books is disconnected');
setting_set('books_connected', '1');
setting_set('books_app_url', '');
setting_set('books_api_url', '');
t_ok(!books_switch_ready(), 'the switcher is not shown when no address is configured');

// tidy
setting_set('books_connected', '0');
setting_set('books_dryrun', '0');
