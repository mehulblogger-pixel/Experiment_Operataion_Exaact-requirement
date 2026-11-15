<?php
// Universal man-day / man-month billing — stage 4: early end, suspension,
// charges, partial billing and the reconciliation (owner's acceptance tests 7–10).
t_section('termination, suspension and partial billing never lose or double-bill work');

$pdo = db();
billunit_migrate();

// Leave the shared test database as found: the invoices (and their numbers),
// lines and billing records this file creates are removed at the end, so a later
// file that types an invoice number by hand never collides with ours.
$__bu_inv0 = (int)ops_val("SELECT COALESCE(MAX(id),0) FROM invoices");
$__bu_ev0  = (int)ops_val("SELECT COALESCE(MAX(id),0) FROM billable_events");
$__bu_bb0  = (int)ops_val("SELECT COALESCE(MAX(id),0) FROM billable_bills");
$_SESSION['uid'] = 1; current_user(true); ua(true);
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,state,gstin) VALUES ('Term Co','Term Co',1,'ACTIVE','Gujarat','24AAAAA0000A1Z5')")->execute();
$pid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO offices (name,is_active) VALUES ('Term HQ',1)")->execute();
$off = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO inspectors (name) VALUES ('Term Inspector')")->execute();
$ins = (int)$pdo->lastInsertId();
$call = function ($basis, $rate, $cn = 'CN-TERM') use ($pdo, $pid) {
    $pdo->prepare("INSERT INTO calls (client_id, billable_basis, billable_rate, contract_number) VALUES (?,?,?,?)")->execute([$pid, $basis, $rate, $cn]);
    return (int)$pdo->lastInsertId();
};
$job = function ($callId, $code, $start = null, $end = null) use ($pdo, $off, $ins) {
    $pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, inspection_start_date, inspection_end_date, created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute([$code, $callId, $off, $ins, $start, $end, date('c')]);
    return (int)$pdo->lastInsertId();
};
$units = fn($j, $st = null) => ops_all("SELECT * FROM billable_events WHERE source_module='unit' AND job_id=?" . ($st ? " AND status='$st'" : " AND status<>'CANCELLED'") . " ORDER BY period_from", [$j]);
$inv = fn() => (int)books_invoice_create(['partner_id' => $pid, 'office_id' => $off])['id'];

// 7 — a six-month deputation terminated after four months and ten days.
$start = date('Y-m-01', strtotime('first day of -5 month'));
$plannedEnd = date('Y-m-t', strtotime($start . ' +5 month'));
$j7 = $job($call('MANMONTH', 120000), 'TERM-7', $start, $plannedEnd);
$stop = date('Y-m-10', strtotime($start . ' +4 month'));
$dim = (int)date('t', strtotime($stop));
t_ok(strpos(billunit_end_early($j7, $stop, 'TERMINATED', ''), 'Say why') !== false, '7 · an early end needs a reason');
t_eq(billunit_end_early($j7, $stop, 'TERMINATED', 'Client terminated — project put on hold'), '', '7 · the deputation is terminated after 4 months and 10 days');
$u7 = $units($j7);
t_eq(count($u7), 5, '7 · four full months and the part month — five units');
t_eq(round(array_sum(array_map(fn($u) => (float)$u['qty'], $u7)), 4), round(4 + 10 / $dim, 4), '7 · 4 + 10/' . $dim . ' man-months');
t_ok(!array_filter($u7, fn($u) => $u['due_on'] === '' || $u['due_on'] > date('Y-m-d')), '7 · all of it is due at once (final billing on termination)');
t_eq(round((float)ops_val("SELECT SUM(amount) FROM billable_events WHERE source_module='unit' AND job_id=? AND status<>'CANCELLED'", [$j7]), 2),
     round(4 * 120000 + round(10 / $dim, 4) * 120000, 2), '7 · amount = 4 months + the part month at the agreed rate');
// The contract's termination charge, entered by finance with its reason.
t_ok(strpos(billunit_add_adjustment($j7, 0, 'x'), 'amount') !== false, '7 · a charge needs an amount');
t_eq(billunit_add_adjustment($j7, 25000, 'Notice-period charge per clause 14.2'), '', '7 · finance adds the contractual termination charge');
$i7 = $inv(); t_eq(books_line_add($i7, ['job_id' => $j7]), '', '7 · one invoice bills the months and the charge');
t_eq((int)ops_val("SELECT COUNT(*) FROM invoice_lines WHERE invoice_id=?", [$i7]), 6, '7 · five months + one charge line');
books_issue($i7);
$r7 = billunit_reconciliation($j7);
foreach (ops_all("SELECT id,source_kind,status,qty,rate,amount,billed_amount,invoice_id FROM billable_events WHERE source_module='unit' AND job_id=? ORDER BY id", [$j7]) as $r) echo json_encode($r), "\n";
foreach (ops_all("SELECT id,qty,rate,amount FROM invoice_lines WHERE invoice_id=?", [$i7]) as $r) echo json_encode($r), "\n";
$r7 = billunit_reconciliation($j7); unset($r7['units'],$r7['triggers']); echo json_encode($r7), "\n";
foreach (ops_all("SELECT invoice_no,subtotal,cgst,sgst,total FROM invoices WHERE id=?", [$i7]) as $r) echo "INVOICE ", json_encode($r), "\n";
