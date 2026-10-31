<?php
// Universal man-day / man-month billing — stage 4: early end, suspension,
// charges, partial billing and the reconciliation (owner's acceptance tests 7–10).
t_section('termination, suspension and partial billing never lose or double-bill work');

$pdo = db();
billunit_migrate();
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
t_eq([round($r7['outstanding'], 2), round($r7['due'], 2)], [0.0, 0.0], '7 · reconciliation: nothing outstanding once billed');
t_eq(billunit_end_early($j7, $stop, 'TERMINATED', 'again'), '', '7 · repeating the termination changes nothing billed');
t_eq((int)ops_val("SELECT COUNT(*) FROM billable_events WHERE source_module='unit' AND job_id=? AND status='BILLED'", [$j7]), 6, '7 · and the six billed units stay billed — earlier events are never erased');

// 8 — a man-day assignment terminated midway: every approved day is billed.
$j8 = $job($call('MANDAY', 7000), 'TERM-8');
$base = date('Y-m-01', strtotime('first day of -1 month'));
foreach ([1, 2, 3, 4] as $k) $pdo->prepare("INSERT INTO job_visits (job_id, visit_date, inspector_id, status) VALUES (?,?,?,'DONE')")->execute([$j8, date('Y-m-d', strtotime("$base +$k day")), $ins]);
$pdo->prepare("INSERT INTO job_visits (job_id, visit_date, inspector_id, status) VALUES (?,?,?,'PLANNED')")->execute([$j8, date('Y-m-d', strtotime("$base +8 day")), $ins]);
t_eq(billunit_end_early($j8, date('Y-m-d', strtotime("$base +5 day")), 'TERMINATED', 'Vendor stopped production'), '', '8 · the man-day job is terminated midway');
$i8 = $inv(); books_line_add($i8, ['job_id' => $j8]);
t_eq((float)ops_val("SELECT SUM(amount) FROM invoice_lines WHERE invoice_id=?", [$i8]), 28000.0, '8 · the four worked days bill (4 × 7,000); the planned day after the end does not');

// 9 — partially completed and partially billed: the remaining balance is shown.
$j9 = $job($call('MANDAY', 5000), 'TERM-9');
foreach ([1, 2, 3] as $k) $pdo->prepare("INSERT INTO job_visits (job_id, visit_date, inspector_id, status) VALUES (?,?,?,'DONE')")->execute([$j9, date('Y-m-d', strtotime("$base +$k day")), $ins]);
billunit_on_job_changed($j9);
$first = $units($j9)[0];
t_ok(strpos(billunit_bill_now((int)$first['id'], ''), 'why') !== false, '9 · releasing a unit early needs a reason');
t_eq(billunit_bill_now((int)$first['id'], 'Client agreed part billing at 30% completion'), '', '9 · finance releases one day for partial billing');
$i9 = $inv(); books_line_add($i9, ['job_id' => $j9]); books_issue($i9);
$r9 = billunit_reconciliation($j9);
t_eq([$r9['mandays'], $r9['billed'], $r9['not_due'], $r9['outstanding']], [3.0, 5000.0, 10000.0, 10000.0], '9 · reconciliation: 3 man-days, 5,000 billed, 10,000 earned and not yet due, 10,000 outstanding');

// 10 — one contract with man-day inspection AND man-month deputation, on one invoice.
$cn = 'CN-MIX';
$jd = $job($call('MANDAY', 6000, $cn), 'MIX-D');
foreach ([1, 2] as $k) $pdo->prepare("INSERT INTO job_visits (job_id, visit_date, inspector_id, status) VALUES (?,?,?,'DONE')")->execute([$jd, date('Y-m-d', strtotime("$base +$k day")), $ins]);
$pdo->prepare("UPDATE jobs SET closed_flag=1, closed_at=? WHERE id=?")->execute([date('Y-m-d'), $jd]); billable_on_job_closed($jd);
$jm = $job($call('MANMONTH', 90000, $cn), 'MIX-M', date('Y-m-01', strtotime('first day of -2 month')), date('Y-m-t', strtotime('last day of -1 month')));
billunit_on_job_changed($jm);
$i10 = $inv(); books_line_add($i10, ['job_id' => $jd]); books_line_add($i10, ['job_id' => $jm]);
t_eq((float)ops_val("SELECT SUM(amount) FROM invoice_lines WHERE invoice_id=?", [$i10]), 12000.0 + 180000.0, '10 · 2 man-days (12,000) and 2 man-months (1,80,000) calculated independently, consolidated on one invoice');
t_eq((int)ops_val("SELECT COUNT(DISTINCT contract_number) FROM invoice_lines WHERE invoice_id=?", [$i10]), 1, '10 · all under the one contract number');

// Suspension: suspended days are not charged unless the contract says so.
$sm = date('Y-m-01', strtotime('first day of -1 month'));
$js = $job($call('MANMONTH', 100000), 'SUSP', $sm, date('Y-m-t', strtotime($sm)));
$pdo->prepare("INSERT INTO dep_status_events (job_id, old_status, new_status, at) VALUES (?, 'ACTIVE', 'SUSPENDED', ?)")->execute([$js, date('Y-m-11', strtotime($sm)) . 'T09:00:00']);
$pdo->prepare("INSERT INTO dep_status_events (job_id, old_status, new_status, at) VALUES (?, 'SUSPENDED', 'ACTIVE', ?)")->execute([$js, date('Y-m-21', strtotime($sm)) . 'T09:00:00']);
billunit_on_job_changed($js);
$sdim = (int)date('t', strtotime($sm));
t_eq((float)($units($js)[0]['qty'] ?? 0), round(($sdim - 10) / $sdim, 4), 'a 10-day suspension is not charged: ' . ($sdim - 10) . '/' . $sdim . ' of the month');
$pdo->prepare("UPDATE jobs SET billing_charge_suspension=1 WHERE id=?")->execute([$js]);
billunit_on_job_changed($js);
t_eq((float)($units($js)[0]['qty'] ?? 0), 1.0, 'where the contract charges for suspension, the full month is earned');
unset($_SESSION['uid']); current_user(true); ua(true);
