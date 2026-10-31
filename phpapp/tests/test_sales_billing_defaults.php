<?php
// ============================================================================
//  Default billing figures are the job's own (Sales audit SA-15, SA-16, SA-22,
//  SA-36 — Stage 1.8, no owner decision needed).
//
//  The case the audit probe found: a work order for 3 man-days at 9,500,
//  delivered as a 2-day job and a 1-day job. Each job's invoice line used to
//  default to the whole order (3 × 9,500 = 28,500), and once the invoice was
//  issued every billing event on it was overwritten with the invoice's WHOLE
//  total, GST included. (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('Sales → billing — each job bills its own share, and reports what it was billed');

t_as_admin();
books_migrate(); if (function_exists('billable_migrate')) billable_migrate();
$pdo = db();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('BD Office',1)")->execute();
$off = (int) $pdo->lastInsertId();
$client = function ($n) use ($pdo) {
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status, state, gstin) VALUES (?,?,1,'ACTIVE','Gujarat','24AAAAA0000A1Z5')")->execute([$n, $n]);
    return (int) $pdo->lastInsertId();
};
$acme = $client('BD Acme'); $beta = $client('BD Beta');
$pdo->prepare("INSERT INTO calls (call_code, client_id, status, executing_office_id, ibo_office_id, billable_rate, billable_qty, billable_value, billable_basis, created_at)
               VALUES ('BD-CALL',?,'OPEN',?,?,9500,3,28500,'MANDAY',?)")->execute([$acme, $off, $off, date('c')]);
$call = (int) $pdo->lastInsertId();
$job = function ($code, $days) use ($pdo, $call, $off) {
    $pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, mandays, closed_flag, created_at) VALUES (?,?,?,?,1,?)")
        ->execute([$code, $call, $off, $days, date('c')]);
    return (int) $pdo->lastInsertId();
};
$j2 = $job('BD-JOB-2D', 2); $j1 = $job('BD-JOB-1D', 1);
// Closing a job queues its billing, before anyone invoices it. A man-day order is
// billed by its man-days (universal billing engine, 31 Oct 2026): each job's own
// man-days are its own billing unit — never the whole order's.
billable_events_sync();
$e2 = (int) ops_val("SELECT id FROM billable_events WHERE source_module='unit' AND job_id=?", [$j2]);
$e1 = (int) ops_val("SELECT id FROM billable_events WHERE source_module='unit' AND job_id=?", [$j1]);
t_ok($e2 > 0 && $e1 > 0, 'arranged: each closed job has its man-days queued for billing');
t_eq((float) ops_val("SELECT amount FROM billable_events WHERE id=?", [$e2]), 19000.0, 'the 2-day job is queued at its own man-days, 2 × 9,500 = 19,000');

// ---- SA-15: a job's own share ------------------------------------------------------
$f2 = billable_job_fields($j2);
t_eq([(float) $f2['qty'], (float) $f2['amount']], [2.0, 19000.0], 'the 2-day job of a 3-day order carries 2 man-days, 19,000 — not the whole order');
t_eq((float) billable_job_fields($j1)['amount'], 9500.0, 'the 1-day job carries 9,500');
$inv = books_invoice_create(['partner_id' => $acme, 'office_id' => $off]);
t_eq(books_line_add((int) $inv['id'], ['job_id' => $j2]), '', 'the 2-day job is added to the invoice');
$ln = ops_one("SELECT qty, rate FROM invoice_lines WHERE invoice_id=? AND job_id=?", [(int) $inv['id'], $j2]);
t_eq([(float) $ln['qty'], (float) $ln['rate']], [2.0, 9500.0], 'its invoice line defaults to 2 × 9,500, not 3 × 9,500 (SA-15)');
t_eq(books_line_add((int) $inv['id'], ['job_id' => $j1]), '', 'the 1-day job is added too');
books_recalc((int) $inv['id']);
t_eq((float) ops_val("SELECT SUM(amount) FROM invoice_lines WHERE invoice_id=?", [(int) $inv['id']]), 28500.0,
     'together the two jobs bill exactly the order: 28,500');

// A single-job order keeps its own figures exactly as before.
$pdo->prepare("INSERT INTO calls (call_code, client_id, status, executing_office_id, ibo_office_id, billable_rate, billable_qty, billable_value, billable_basis, created_at)
               VALUES ('BD-SOLO',?,'OPEN',?,?,9500,3,28500,'MANDAY',?)")->execute([$acme, $off, $off, date('c')]);
$solo = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, mandays, closed_flag, created_at) VALUES ('BD-SOLO-J',?,?,2,1,?)")->execute([$solo, $off, date('c')]);
t_eq((float) billable_job_fields((int) $pdo->lastInsertId())['amount'], 28500.0, 'control: an order delivered as one job still carries the order\'s own value');

// ---- SA-36: a job belongs on its own customer's invoice --------------------------------
$invB = books_invoice_create(['partner_id' => $beta, 'office_id' => $off]);
$err = books_line_add((int) $invB['id'], ['job_id' => $j1]);
t_ok(strpos($err, 'BD Acme') !== false, 'Acme\'s job cannot go on Beta\'s invoice, and the refusal names whose job it is: ' . $err);

// ---- SA-16: each billing event reports what IT was billed ------------------------------
$pdo->prepare("UPDATE invoices SET status='ISSUED', total=? WHERE id=?")->execute([33630, (int) $inv['id']]);   // 28,500 + 18 % GST
billable_events_sync();
$amt = fn($id) => (float) ops_val("SELECT amount FROM billable_events WHERE id=?", [$id]);
t_eq([$amt($e2), $amt($e1)], [19000.0, 9500.0], 'after the invoice is issued each event holds its own line, before tax — not the invoice\'s 33,630 each');
t_eq([(float) ops_val("SELECT billed_amount FROM billable_events WHERE id=?", [$e2]), (string) ops_val("SELECT status FROM billable_events WHERE id=?", [$e2])], [19000.0, 'BILLED'], 'and the 2-day job\'s man-days read BILLED for exactly their own line');
t_eq(round($amt($e2) + $amt($e1), 2), 28500.0, '…so together they equal what the invoice billed for them, before tax');
t_ok(!array_filter(billable_mismatch(), fn($m) => in_array((int) $m['id'], [$e1, $e2], true)),
     'and neither shows as a false mismatch against what it earned');

// ---- SA-22: a work order raised from a won deal is priced from the quotation -----------
$src = file_get_contents(__DIR__ . '/../lib/opportunities.php');
t_ok(preg_match('/SELECT q\.id, q\.quote_no, q\.rev, q\.status, q\.subtotal,/', $src) === 1,
     'the deal\'s quotation list carries the subtotal, so a work order raised from the deal takes the accepted quotation\'s value, not the estimate');
