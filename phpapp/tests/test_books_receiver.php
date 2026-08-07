<?php
// MGH Books receiver — the SERVER side of the connector contract.
// This is the end-to-end proof that the ERP's mapper output (one side) and the
// Books receiver's input (the other side) agree on every field name: real
// mapped payloads are pushed through the real dispatcher, and the money truth
// that comes back is fed into the ERP's own books_apply_status(). If a field is
// renamed on either side, this test fails.
t_section('MGH Books receiver — round trip');

require __DIR__ . '/../../mgh_books_receiver/lib/receiver.php';

// Give the receiver its own throwaway database (separate from the ERP's) and a
// shared token, so auth and storage are exercised for real.
bkrecv_db(new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));
putenv('BOOKS_API_TOKEN=round-trip-secret');

// Auth is real: the wrong token is refused, the right one accepted.
t_ok(!bkrecv_auth('nope'), 'the receiver refuses a wrong token');
t_ok(bkrecv_auth('round-trip-secret'), 'the receiver accepts the shared token');
t_ok(!bkrecv_auth(''), 'the receiver refuses an empty token');

// Turn the bridge on so the ERP mappers produce full payloads.
booksbridge_migrate(); books_migrate();
setting_set('books_connected', '1'); setting_set('books_dryrun', '1');

// An ERP client + issued invoice for 11,800.
db()->prepare("INSERT INTO business_partners (display_name, legal_name, is_client, gstin, state, created_at)
    VALUES (?,?,?,?,?,?)")->execute(['Reliance', 'Reliance Industries', 1, '24AAAAA0000A1Z5', 'Gujarat', date('c')]);
$pid = (int)db()->lastInsertId();
db()->prepare("INSERT INTO invoices (invoice_no, partner_id, partner_name, invoice_date, status,
    subtotal, cgst, sgst, igst, total, is_igst, place_of_supply, gstin, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute(['INV/2627/0100', $pid, 'Reliance Industries', '2026-07-20', 'ISSUED', 10000, 900, 900, 0, 11800, 0, '24', '24AAAAA0000A1Z5', date('c')]);
$invId = (int)db()->lastInsertId();

// Push the party (action=set) through the dispatcher, using the ERP's own mapper.
[$code, $resp] = bkrecv_dispatch('set', 'PARTY', books_map_party($pid));
t_ok($code === 200 && $resp['ok'] && strncmp($resp['ref'], 'BK-CUST-', 8) === 0, 'the party imports and gets a Books reference');

// Push the invoice (action=import). Books stores it and returns its reference.
[$code, $resp] = bkrecv_dispatch('import', 'INVOICE', books_map_invoice($invId));
t_ok($code === 200 && $resp['ok'] && strncmp($resp['ref'], 'BK-INV-', 7) === 0, 'the invoice imports and gets a Books reference');
$invRef = $resp['ref'];

// Idempotent: the same invoice again returns the SAME reference, no duplicate.
[$c2, $r2] = bkrecv_dispatch('import', 'INVOICE', books_map_invoice($invId));
t_eq($r2['ref'], $invRef, 're-importing the same invoice returns the same reference (idempotent)');

// Status back: freshly imported, the whole total is outstanding and UNPAID.
[$code, $st] = bkrecv_dispatch('status', '', [], 'ERP-INV-' . $invId);
t_ok($code === 200 && $st['ok'], 'the status reads back');
t_eq((float)$st['status']['outstanding'], 11800.0, 'the full total is outstanding before any money arrives');
t_eq($st['status']['status'], 'UNPAID', 'a fresh invoice reads UNPAID');
t_ok(strncmp($st['status']['irn'], 'IRN-', 4) === 0, 'Books assigns an IRN');

// Money in: a 5,000 receipt reduces the invoice to part-paid.
db()->prepare("INSERT INTO receipts (receipt_no, partner_id, partner_name, receipt_date, mode, reference, amount, created_at)
    VALUES (?,?,?,?,?,?,?,?)")->execute(['RCP/2627/0050', $pid, 'Reliance Industries', '2026-07-22', 'NEFT', 'UTR-A', 5000, date('c')]);
$rid = (int)db()->lastInsertId();
bkrecv_dispatch('import', 'RECEIPT', books_map_receipt($rid));
[$code, $st] = bkrecv_dispatch('status', '', [], 'ERP-INV-' . $invId);
t_eq((float)$st['status']['paid'], 5000.0, 'the receipt is applied to the invoice');
t_eq((float)$st['status']['outstanding'], 6800.0, 'the outstanding drops by the receipt');
t_eq($st['status']['status'], 'PART_PAID', 'the invoice reads part paid');

// A 1,800 credit note against the invoice reduces the outstanding further.
db()->prepare("INSERT INTO credit_notes (cn_no, invoice_id, partner_id, partner_name, cn_date, reason, total, status, created_at)
    VALUES (?,?,?,?,?,?,?,?,?)")->execute(['CN/2627/0010', $invId, $pid, 'Reliance Industries', '2026-07-23', 'RATE_CORRECTION', 1800, 'ISSUED', date('c')]);
$cnId = (int)db()->lastInsertId();
bkrecv_dispatch('import', 'CREDITNOTE', books_map_creditnote($cnId));
[$code, $st] = bkrecv_dispatch('status', '', [], 'ERP-INV-' . $invId);
t_eq((float)$st['status']['outstanding'], 5000.0, 'the credit note knocks the outstanding down');

// Close the loop: feed Books' reported status into the ERP's own applier and read
// it back on the invoice — proving the return shape matches what the ERP consumes.
db()->prepare("UPDATE invoices SET books_ref=? WHERE id=?")->execute([$invRef, $invId]);
t_ok(books_apply_status($invId, $st['status']), 'the ERP applies the status Books returned');
$bs = books_invoice_status($invId);
t_eq((float)$bs['books_outstanding'], 5000.0, 'the ERP invoice shows the outstanding Books computed');
t_eq($bs['books_status'], 'PART_PAID', 'the ERP invoice shows the status Books computed');

// A quote round-trips too, carrying its PDF flag.
db()->prepare("INSERT INTO quotations (quote_no, rev, client_id, client_name, subject, currency,
    subtotal, gst_pct, gst_amount, total_amount, status, accepted_date, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute(['Q/2627/0050', 0, $pid, 'Reliance Industries', 'Inspection', 'INR', 20000, 18, 3600, 23600, 'ACCEPTED', '2026-07-19', date('c')]);
$qid = (int)db()->lastInsertId();
[$code, $resp] = bkrecv_dispatch('import', 'QUOTE', books_map_quote($qid));
t_ok($code === 200 && $resp['ok'] && strncmp($resp['ref'], 'BK-QT-', 6) === 0, 'the accepted quote imports and gets a Books reference');

// An unknown action and a missing document are refused cleanly, not fatally.
[$code, $resp] = bkrecv_dispatch('frobnicate', '', []);
t_ok($code === 400 && !$resp['ok'], 'an unknown action is refused');
[$code, $resp] = bkrecv_dispatch('status', '', [], 'ERP-INV-999999');
t_ok($code === 404 && !$resp['ok'], 'status for an unknown invoice is refused, not fatal');

// tidy
setting_set('books_connected', '0'); setting_set('books_dryrun', '0');
