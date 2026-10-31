<?php
// Universal man-day / man-month billing — stage 1, the foundation (owner, 31 Oct).
// A billing unit is billed EXACTLY ONCE: an invoice line points at one unit, a unit
// sits on only one invoice that is not cancelled, BILLED comes only from an issued
// invoice, and cancelling that invoice returns the unit to be billed (controlled
// reversal). A job billed by its units is never also billed as a whole.
t_section('billing units: exactly once, reversible, never lost');

$pdo = db();
billunit_migrate();
$_SESSION['uid'] = 1; current_user(true); ua(true);
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,state,gstin) VALUES ('Unit Co','Unit Co',1,'ACTIVE','Gujarat','24AAAAA0000A1Z5')")->execute();
$pid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO offices (name,is_active) VALUES ('Unit HQ',1)")->execute();
$off = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO calls (client_id, billable_basis, billable_rate, contract_number) VALUES (?, 'MANDAY', 10000, 'CN-U1')")->execute([$pid]);
$callId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, created_at) VALUES ('UNIT-J1', ?, ?, ?)")->execute([$callId, $off, date('c')]);
$jobId = (int)$pdo->lastInsertId();
$today = date('Y-m-d');
$mk = function ($d) use ($jobId, $pid) {
    return billunit_upsert('MANDAY', "MD|$jobId|0|$d", ['job_id' => $jobId, 'party_id' => $pid, 'contract_number' => 'CN-U1',
        'qty' => 1, 'unit' => 'man-day', 'rate' => 10000, 'amount' => 10000, 'period_from' => $d, 'period_to' => $d]);
};
$d1 = date('Y-m-d', strtotime('-3 day')); $d2 = date('Y-m-d', strtotime('-2 day'));
$u1 = $mk($d1); $u2 = $mk($d2);
t_ok($u1 > 0 && $u2 > 0 && $u1 !== $u2, 'two man-days are two units');
t_eq($mk($d1), $u1, 'the same day again is the same unit (unique key — never two rows for one day)');

$newInv = fn() => (int)books_invoice_create(['partner_id' => $pid, 'office_id' => $off])['id'];

// Not yet due under the contract → cannot be billed yet (but is not lost).
$i1 = $newInv();
t_ok(strpos(billunit_line_add($i1, $u1), 'not yet due') !== false, 'an earned unit that is not yet due cannot be invoiced');
billunit_mark_due($u1, $today, 'test'); billunit_mark_due($u2, $today, 'test');
t_eq(billunit_line_add($i1, $u1), '', 'a due unit goes on the invoice');
t_ok(strpos(billunit_line_add($newInv(), $u1), 'already on another invoice') !== false, 'the same unit cannot go on a second invoice');
// A whole-job line beside units is refused.
// "Raise invoice from the job" bills its DUE units that are on no invoice — here only the next day.
$i2 = $newInv();
t_eq(books_line_add($i2, ['job_id' => $jobId]), '', 'billing the job adds its due units, never a whole-job line');
t_eq(array_map('intval', array_column(ops_all("SELECT billable_event_id FROM invoice_lines WHERE invoice_id=?", [$i2]), 'billable_event_id')), [$u2], 'only the day not already invoiced goes on');

// Issue → BILLED at once (no manual sync).
t_eq(books_issue($i1), '', 'the invoice is issued');
$e1 = ops_one("SELECT * FROM billable_events WHERE id=?", [$u1]);
t_eq($e1['status'], 'BILLED', 'issuing marks the unit BILLED immediately');
t_eq((int)$e1['invoice_id'], $i1, 'and it points at the invoice that billed it');
$job = ops_one("SELECT invoice_amount, invoice_raised FROM jobs WHERE id=?", [$jobId]);
t_eq((float)$job['invoice_amount'], 11800.0, 'the job shows the billed amount (10,000 + 18% GST)');

// The second invoice is issued: the job's figures ADD UP, not overwrite.
books_issue($i2);
t_eq((float)ops_val("SELECT invoice_amount FROM jobs WHERE id=?", [$jobId]), 23600.0, 'two invoices for one job sum on the job (October is not lost when November is billed)');

// Cancel the first → the unit returns to be billed, logged, and can be billed again.
t_eq(books_cancel($i1, 'wrong PO'), '', 'the first invoice is cancelled');
$e1 = ops_one("SELECT * FROM billable_events WHERE id=?", [$u1]);
t_eq($e1['status'], 'APPROVED', 'its unit is back to be billed (controlled reversal)');
t_ok(strpos((string)$e1['status_reason'], 'cancelled') !== false, 'the reason says the invoice was cancelled');
t_ok((float)ops_val("SELECT SUM(amount) FROM billable_bills WHERE event_id=?", [$u1]) == 0.0, 'the bill log nets to zero (billed, then reversed)');
t_eq((float)ops_val("SELECT invoice_amount FROM jobs WHERE id=?", [$jobId]), 11800.0, 'the job now shows only the invoice still standing');
$i3 = $newInv(); t_eq(billunit_line_add($i3, $u1), '', 'the reversed day can be billed again — once');
books_issue($i3);
t_eq(ops_val("SELECT status FROM billable_events WHERE id=?", [$u1]), 'BILLED', 'and is BILLED on the new invoice');
t_eq((int)ops_val("SELECT COUNT(*) FROM invoice_lines l JOIN invoices i ON i.id=l.invoice_id WHERE l.billable_event_id=? AND i.status<>'CANCELLED'", [$u1]), 1, 'exactly one live invoice line ever holds that day');

// A unit refreshes its figures only while PENDING and on no invoice.
billunit_upsert('MANDAY', "MD|$jobId|0|$d1", ['job_id' => $jobId, 'party_id' => $pid, 'qty' => 1, 'rate' => 99999, 'amount' => 99999, 'period_from' => $d1]);
t_eq((float)ops_val("SELECT amount FROM billable_events WHERE id=?", [$u1]), 10000.0, 'a billed unit is never re-priced by a later run');

// A disputed unit cannot be billed.
$u3 = $mk(date('Y-m-d', strtotime('-1 day'))); billunit_mark_due($u3, $today, 'test');
billable_set_status($u3, 'APPROVED'); billable_set_status($u3, 'DISPUTED', 'client queries the day');
t_ok(strpos(billunit_line_add($newInv(), $u3), 'disputed') !== false, 'a disputed unit cannot be billed');

// The older one-per-job event is released when its invoice is cancelled (map defect 1).
$pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, closed_flag, invoice_value, created_at) VALUES ('UNIT-LUMP', NULL, ?, 1, 5000, ?)")->execute([$off, date('c')]);
$lump = (int)$pdo->lastInsertId();
$ev = billable_event_upsert('job', 'JOB_CLOSED', $lump, ['party_id' => $pid, 'amount' => 5000]);
$i4 = $newInv(); books_line_add($i4, ['job_id' => $lump, 'rate' => 5000, 'qty' => 1]); books_issue($i4);
t_eq(ops_val("SELECT status FROM billable_events WHERE id=?", [$ev]), 'BILLED', 'a lump-sum job event is BILLED the moment its invoice is issued');
books_cancel($i4, 'duplicate');
t_eq(ops_val("SELECT status FROM billable_events WHERE id=?", [$ev]), 'APPROVED', 'cancelling that invoice releases the job back to be billed');
unset($_SESSION['uid']); current_user(true); ua(true);
