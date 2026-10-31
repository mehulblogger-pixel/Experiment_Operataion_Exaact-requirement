<?php
// Universal man-day / man-month billing — stages 2 & 3, the owner's acceptance
// tests (31 Oct 2026) that concern counting the work and the billing triggers.
t_section('man-days and man-months are counted, and billed when the contract says — once');

$pdo = db();
billunit_migrate();
$_SESSION['uid'] = 1; current_user(true); ua(true);
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,state,gstin) VALUES ('Trig Co','Trig Co',1,'ACTIVE','Gujarat','24AAAAA0000A1Z5')")->execute();
$pid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO offices (name,is_active) VALUES ('Trig HQ',1)")->execute();
$off = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO inspectors (name) VALUES ('Trig Inspector')")->execute();
$ins = (int)$pdo->lastInsertId();

$call = function ($basis, $rate, $triggers = '', $period = '') use ($pdo, $pid) {
    $pdo->prepare("INSERT INTO calls (client_id, billable_basis, billable_rate, contract_number, billing_triggers, billing_period) VALUES (?,?,?,?,?,?)")
        ->execute([$pid, $basis, $rate, 'CN-T', $triggers, $period]);
    return (int)$pdo->lastInsertId();
};
$job = function ($callId, $code, $start = null, $end = null) use ($pdo, $off, $ins) {
    $pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, inspection_start_date, inspection_end_date, created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute([$code, $callId, $off, $ins, $start, $end, date('c')]);
    return (int)$pdo->lastInsertId();
};
$day = function ($jobId, $d) use ($pdo, $ins) {
    $pdo->prepare("INSERT INTO job_visits (job_id, visit_date, inspector_id, status) VALUES (?,?,?,'DONE')")->execute([$jobId, $d, $ins]);
};
$close = function ($jobId) use ($pdo) {
    $pdo->prepare("UPDATE jobs SET closed_flag=1, closed_at=? WHERE id=?")->execute([date('Y-m-d'), $jobId]);
    billable_on_job_closed($jobId);
};
$inv = fn() => (int)books_invoice_create(['partner_id' => $pid, 'office_id' => $off])['id'];
$billJob = function ($jobId) use ($inv) { $i = $inv(); $e = books_line_add($i, ['job_id' => $jobId]); return [$i, $e]; };
$units = fn($jobId) => ops_all("SELECT * FROM billable_events WHERE source_module='unit' AND job_id=? ORDER BY period_from", [$jobId]);
$lineSum = fn($i) => (float)ops_val("SELECT COALESCE(SUM(amount),0) FROM invoice_lines WHERE invoice_id=?", [$i]);
$m2 = date('Y-m-01', strtotime('first day of -2 month'));   // a month that is over
// A billing period that has NOT ended today (on the last day of a month the
// monthly cut-off is today, which is correct, so the "not yet" checks use the
// shortest period still running).
$openPeriod = 'MONTHLY';
foreach (['MONTHLY', 'FORTNIGHTLY', 'WEEKLY'] as $p) if (billunit_period_cutoff(date('Y-m-d'), $p) > date('Y-m-d')) { $openPeriod = $p; break; }
$m1 = date('Y-m-01', strtotime('first day of -1 month'));

// 1 — five man-days used, job closed: bill five man-days.
$j = $job($call('MANDAY', 8000), 'T1');
foreach ([1, 2, 3, 4, 5] as $k) $day($j, date('Y-m-d', strtotime($m2 . " +$k day")));
t_eq(count($units($j)), 0, '1 · before anything runs, nothing is invented');
billunit_on_job_changed($j);
t_eq(count($units($j)), 5, '1 · five closed visit days are five man-day units');
t_ok(!array_filter($units($j), fn($u) => $u['due_on'] !== ''), '1 · with the default (at closure) they are earned but not yet due');
[$i, $e] = $billJob($j);
t_ok(strpos($e, 'not yet due') !== false, '1 · so they cannot be invoiced before closure');
$close($j);
[$i, $e] = $billJob($j);
t_eq([$e, $lineSum($i)], ['', 40000.0], '1 · closed: the five man-days bill — 5 × 8,000 = 40,000');

// 2 — ten man-days in a month, job still open, monthly billing: bill that month.
$j2 = $job($call('MANDAY', 5000, 'PERIOD', $openPeriod), 'T2');
for ($k = 0; $k < 10; $k++) $day($j2, date('Y-m-d', strtotime($m2 . " +$k day")));
$day($j2, date('Y-m-d', strtotime($m1 . ' +2 day')));          // one day the next month
$day($j2, date('Y-m-d'));                                        // and today (this month is not over)
billunit_on_job_changed($j2);
[$i2, $e] = $billJob($j2);
t_eq($e, '', '2 · the open job can be billed — its finished months are due');
t_eq((int)ops_val("SELECT COUNT(*) FROM invoice_lines WHERE invoice_id=? AND description LIKE ?", [$i2, '%' . date('M Y', strtotime($m2)) . '%']), 10, '2 · all ten man-days of the finished month are on it');
t_eq((int)ops_val("SELECT COUNT(*) FROM invoice_lines WHERE invoice_id=?", [$i2]), 11, '12 · the next month\'s day is billed on its own month (also over), separately');
t_ok(!ops_val("SELECT COUNT(*) FROM invoice_lines WHERE invoice_id=? AND description LIKE ?", [$i2, '%' . date('d M Y') . '%']), '12 · today\'s day waits for its period\'s cut-off (' . $openPeriod . ')');
books_issue($i2);

// 3 — monthly bill already raised, then the job closes: nothing is billed twice.
$close($j2);
[$i3, $e] = $billJob($j2);
t_eq((int)ops_val("SELECT COUNT(*) FROM invoice_lines WHERE invoice_id=?", [$i3]), 1, '3 · at closure only the one day not yet billed is added');
t_eq((int)ops_val("SELECT COUNT(*) FROM invoice_lines l JOIN invoices i ON i.id=l.invoice_id JOIN billable_events e ON e.id=l.billable_event_id WHERE e.job_id=? AND i.status<>'CANCELLED'", [$j2]), 12, '3 · twelve man-days, twelve live lines — none twice');

// 4 & 5 — FIR is a trigger; a Release Note after it bills nothing again.
$j4 = $job($call('MANDAY', 6000, 'FIR,RN'), 'T4');
foreach ([1, 2, 3] as $k) $day($j4, date('Y-m-d', strtotime($m1 . " +$k day")));
billunit_on_job_changed($j4);
t_ok(!array_filter($units($j4), fn($u) => $u['due_on'] !== ''), '4 · before the FIR the days are not due');
$fir = date('Y-m-d', strtotime($m1 . ' +5 day'));
$pdo->prepare("INSERT INTO report_docs (irn, type_code, status, finalized, job_id, issue_date, data, created_at) VALUES ('T4-FIR','FIR','ISSUED',1,?,?,'[]',?)")->execute([$j4, $fir, date('c')]);
billunit_on_report_issued(['type_code' => 'FIR', 'job_id' => $j4]);
t_eq(count(array_filter($units($j4), fn($u) => $u['due_on'] === $fir)), 3, '4 · the FIR makes the days up to it due, on the FIR date');
[$i4, $e] = $billJob($j4); books_issue($i4);
t_eq($lineSum($i4), 18000.0, '4 · billed on the FIR: 3 × 6,000');
$pdo->prepare("INSERT INTO report_docs (irn, type_code, status, finalized, job_id, issue_date, data, created_at) VALUES ('T4-RN','RN','ISSUED',1,?,?,'[]',?)")->execute([$j4, date('Y-m-d'), date('c')]);
billunit_on_report_issued(['type_code' => 'RN', 'job_id' => $j4]);
[$i5, $e] = $billJob($j4);
t_ok($e !== '' && $lineSum($i5) == 0.0, '5 · the Release Note afterwards bills nothing again — "' . $e . '"');

// 6 — a three-month man-month deployment: three monthly units.
$mmStart = date('Y-m-01', strtotime('first day of -3 month'));
$mmEnd = date('Y-m-t', strtotime('last day of -1 month'));
$j6 = $job($call('MANMONTH', 150000), 'T6', $mmStart, $mmEnd);
billunit_on_job_changed($j6);
$u6 = $units($j6);
t_eq(count($u6), 3, '6 · three completed months are three man-month units');
t_eq(array_map(fn($u) => (float)$u['qty'], $u6), [1.0, 1.0, 1.0], '6 · each a full month');
t_ok(!array_filter($u6, fn($u) => $u['due_on'] === ''), '6 · a deployment bills month by month by default — all three are due');
[$i6, $e] = $billJob($j6);
t_eq([$e, $lineSum($i6)], ['', 450000.0], '6 · three months × 1,50,000');

// Part month — mid-month start, pro-rated by calendar days (the default rule).
$ps = date('Y-m-16', strtotime($m2));
$j7 = $job($call('MANMONTH', 300000), 'T7', $ps, date('Y-m-t', strtotime($m2)));
billunit_on_job_changed($j7);
$u7 = $units($j7)[0] ?? [];
$dim = (int)date('t', strtotime($m2)); $exp = round(($dim - 15) / $dim, 4);
t_eq((float)($u7['qty'] ?? 0), $exp, 'a part month (from the 16th) is ' . ($dim - 15) . '/' . $dim . ' of a month');

// 11 — completed before the monthly cut-off: closure bills it now, still once.
$j11 = $job($call('MANDAY', 4000, 'PERIOD', $openPeriod), 'T11');
$day($j11, date('Y-m-d'));
billunit_on_job_changed($j11);
[$i11, $e] = $billJob($j11);
t_ok($e !== '', '11 · mid-month, before the cut-off, today\'s day is not yet due');
$close($j11);
[$i11b, $e] = $billJob($j11);
t_eq([$e, $lineSum($i11b)], ['', 4000.0], '11 · closing before the cut-off makes it due now — billed once');

// A job billed as a whole before units existed is never billed again by units.
$j8 = $job($call('MANDAY', 1000), 'T8');
$i8 = $inv();
$pdo->prepare("INSERT INTO invoice_lines (invoice_id, seq, job_id, description, qty, rate, amount) VALUES (?,1,?,'old whole-job line',3,1000,3000)")->execute([$i8, $j8]);
$day($j8, $m1);
t_eq(billunit_generate_for_job($j8), 0, 'a job already billed as a whole gets no units — its days are paid for');

// Moving the schedule never deletes a worked, closed day.
$pdo->prepare("UPDATE jobs SET inspection_start_date=?, inspection_end_date=? WHERE id=?")->execute([date('Y-m-d', strtotime('+10 day')), date('Y-m-d', strtotime('+11 day')), $j]);
if (function_exists('job_visits_sync')) job_visits_sync($j);
t_eq((int)ops_val("SELECT COUNT(*) FROM job_visits WHERE job_id=? AND status='DONE'", [$j]), 5, 'rescheduling keeps the five worked days (they are billable evidence)');
unset($_SESSION['uid']); current_user(true); ua(true);
