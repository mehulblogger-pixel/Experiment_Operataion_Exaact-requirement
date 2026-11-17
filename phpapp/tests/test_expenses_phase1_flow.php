<?php
// Expenses Phase 1 (owner, 17 Nov 2026) — the REAL routes, in a second process
// (tests/_bbv_worker.php), for what a person actually clicks:
//   close the day on his own job → lines in his claim, and NOT in `expenses`;
//   profit counts that cost once (and still counts the old `expenses` rows);
//   a coordinator closing the job records nothing for him (V2);
//   his own visit day — he closes it and lands on "the day's expenses";
//   the claim screen shows its total to the approver (the ₹0 fault);
//   "Save all rows" no longer wipes an amount under a head not in his list;
//   approvers / admins cannot change his lines; nobody opens a claim for him;
//   a bill without its photo cannot be submitted;
//   Finance enters the advance when it marks the claim paid (E6);
//   only his N+1 reopens a claim after a deadline, with a reason;
//   the settings screen is the Master Admin's;
//   an old month voucher shows exactly its own figures.
t_section('Expenses Phase 1 — the routes: day close, profit once, V2, advance at Paid, deadline reopen, legacy');

require_once __DIR__ . '/_exp1_fixture.php';
$f = exp1_fixture();
$opened = fn($r, $view) => empty($r['restricted']) && (int) ($r['code'] ?? 0) !== 403 && ($r['view'] ?? '') === $view && !empty($r['view_ok']);
$flash = fn($r) => implode(' | ', $r['flash'] ?? []);
try {
    $t = $f['today'];
    // ---- Close the day on his own job -------------------------------------------
    $r = bbv_route('job-close', 'GET', $f['amit'], 0, [], ['id' => $f['job']]);
    t_ok($opened($r, 'ops/job_close') && stripos($r['out'], 'expenses') !== false && stripos($r['out'], 'Close the day') !== false,
         'screen 1 · the close screen shows the day\'s expenses and "Close the day"');
    t_ok(strpos($r['out'], 'from your band') !== false, 'screen 1 · each filled-in figure says where it came from');
    $r = bbv_route('job-close', 'POST', $f['amit'], 0, ['km' => '42', 'mode' => $f['mode'], 'exp_date' => $t], ['id' => $f['job']]);
    t_eq((int) ops_val("SELECT closed_flag FROM jobs WHERE id=?", [$f['job']]), 1, 'the day (job) is closed: ' . $flash($r));
    $v = exp_claim_for($f['amit_ins'], $t, false); $f['vids'][] = (int) ($v['id'] ?? 0);
    t_ok($v && (float) ops_val("SELECT COALESCE(SUM(row_total),0) FROM voucher_entries WHERE voucher_id=? AND job_id=?", [$v['id'], $f['job']]) == 552.0,
         'closing the day wrote travel ₹252 + allowance ₹300 to his claim\'s lines');
    t_eq((int) ops_val("SELECT COUNT(*) FROM expenses WHERE job_id=?", [$f['job']]), 0, 'and wrote NOTHING to `expenses` (one record)');
    t_eq(job_expenses_total($f['job']), 0.0, 'profit · no job-closure expense for this job');
    t_eq(job_voucher_total($f['job']), 552.0, 'profit · the claim lines are its cost');
    t_eq(call_cost_incurred($f['call']), 552.0, 'profit · the work order\'s cost counts ₹552 once, not twice');
    // An old job-closure expense (before this change) is still counted, once.
    db()->prepare("INSERT INTO expenses (job_id, inspector_id, travel, food, exp_date, created_at) VALUES (?,?,100,50,?,?)")->execute([$f['job_old'], $f['amit_ins'], $f['old'], date('c')]);
    t_eq(call_cost_incurred($f['call']), 702.0, 'profit · an old `expenses` row stays and is still counted');

    // ---- A coordinator closing it records nothing for him (V2) -------------------
    db()->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, scheduled_date, sbu, reporting_frequency, closed_flag, created_at) VALUES (?,?,?,?,?, 'NDT', 'NOREPORT', 0, ?)")
        ->execute(['EX1C-' . $f['tag'], $f['call'], $f['mum'], $f['amit_ins'], $t, date('c')]);
    $jc = (int) db()->lastInsertId();
    db()->prepare("UPDATE users SET permissions=? WHERE id=?")->execute(['ops.job.close', $f['neha']]);
    $r = bbv_route('job-close', 'POST', $f['neha'], 0, ['km' => '99', 'mode' => $f['mode']], ['id' => $jc]);
    t_eq((int) ops_val("SELECT closed_flag FROM jobs WHERE id=?", [$jc]), 1, 'a coordinator still closes the job: ' . $flash($r));
    t_eq((int) ops_val("SELECT COUNT(*) FROM voucher_entries WHERE job_id=?", [$jc]), 0, 'V2 · …but puts no expense on the inspector\'s claim');
    t_eq((int) ops_val("SELECT COUNT(*) FROM expenses WHERE job_id=?", [$jc]), 0, '…and none in `expenses`');
    $r = bbv_route('voucher', 'GET', $f['amit'], (int) $v['id']);
    t_ok($opened($r, 'ops/voucher_detail') && strpos($r['out'], 'still need their expenses confirmed') !== false && strpos($r['out'], 'EX1C-') !== false,
         'the inspector sees that day on his claim, to confirm its expenses himself');

    // ---- His own visit day ---------------------------------------------------------
    db()->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, scheduled_date, sbu, reporting_frequency, closed_flag, created_at) VALUES (?,?,?,?,?, 'NDT', 'NOREPORT', 0, ?)")
        ->execute(['EX1V-' . $f['tag'], $f['call'], $f['mum'], $f['amit_ins'], $t, date('c')]);
    $jv = (int) db()->lastInsertId();
    foreach ([$t, date('Y-m-d', strtotime('+1 day'))] as $d)
        db()->prepare("INSERT INTO job_visits (job_id, visit_date, inspector_id, status) VALUES (?,?,?, 'PLANNED')")->execute([$jv, $d, $f['amit_ins']]);
    $r = bbv_route('job-visit-close', 'POST', $f['rahul'], 0, ['date' => $t, 'report_link' => 'https://x.test/r'], ['id' => $jv]);
    t_ok((string) ops_val("SELECT status FROM job_visits WHERE job_id=? AND visit_date=?", [$jv, $t]) !== 'DONE', 'visit close · another inspector cannot close his day');
    $r = bbv_route('job-visit-close', 'POST', $f['amit'], 0, ['date' => $t, 'report_link' => 'https://x.test/r'], ['id' => $jv]);
    t_eq((string) ops_val("SELECT status FROM job_visits WHERE job_id=? AND visit_date=?", [$jv, $t]), 'DONE', 'visit close · he closes his own visit day: ' . $flash($r));
    $r = bbv_route('voucher-day', 'GET', $f['amit'], 0, [], ['job' => $jv, 'date' => $t]);
    t_ok($opened($r, 'ops/voucher_day') && strpos($r['out'], 'Confirm the day') !== false, 'screen 1 · then confirms the day\'s expenses');
    $r = bbv_route('voucher-day', 'POST', $f['amit'], 0, ['km' => '10', 'mode' => $f['mode'], 'job' => $jv, 'date' => $t], ['job' => $jv, 'date' => $t]);
    t_eq((float) ops_val("SELECT COALESCE(SUM(row_total),0) FROM voucher_entries WHERE job_id=?", [$jv]), 60.0, '…10 km × ₹6 (the allowance is once a day, already on the first job): ' . $flash($r));
    $r = bbv_route('voucher-day', 'GET', $f['rahul'], 0, [], ['job' => $jv, 'date' => $t]);
    t_ok(!$opened($r, 'ops/voucher_day'), 'screen 1 · nobody else opens his day');

    // ---- A bill without its photo cannot be submitted -------------------------------
    [$ok] = (function () use ($f, $t) { bbv_as($f['amit']); return exp_add_line($f['amit_ins'], $f['HOTEL'], 1500, $t, $f['job'], ''); })();
    t_ok($ok, 'a hotel bill without a photo is added to the draft');
    $r = bbv_route('voucher-status', 'POST', $f['amit'], (int) $v['id'], ['action' => 'submit']);
    t_eq($r['status'] ?? '', 'DRAFT', 'BILL · submit is refused until the photo is there: ' . $flash($r));
    $hl = (int) ops_val("SELECT id FROM voucher_entries WHERE voucher_id=? AND head_code=?", [$v['id'], $f['HOTEL']]);
    // V2 — an Admin cannot remove or change his line.
    bbv_route('voucher-line', 'POST', $f['admin'], 0, ['id' => $hl, '_do' => 'del']);
    t_ok((int) ops_val("SELECT COUNT(*) FROM voucher_entries WHERE id=?", [$hl]) === 1, 'V2 · an Admin cannot remove the claimant\'s line');
    bbv_route('voucher-line', 'POST', $f['master'], 0, ['id' => $hl, '_do' => 'update', 'amount' => '10']);
    t_eq((float) ops_val("SELECT row_total FROM voucher_entries WHERE id=?", [$hl]), 1500.0, 'V2 · the Master Admin cannot change it either');
    bbv_route('voucher-line', 'POST', $f['amit'], 0, ['id' => $hl, '_do' => 'del']);
    t_eq((int) ops_val("SELECT COUNT(*) FROM voucher_entries WHERE id=?", [$hl]), 0, 'V2 · the claimant removes his own line');
    bbv_route('voucher', 'GET', $f['admin'], 0, [], ['ins' => $f['rahul_ins'], 'month' => '2031-11']);
    t_eq((int) ops_val("SELECT COUNT(*) FROM vouchers WHERE inspector_id=? AND month='2031-11'", [$f['rahul_ins']]), 0, 'V2 · an Admin no longer opens a claim for somebody else');

    // ---- Submit, approve, and what the approver sees (the ₹0 fault) -------------------
    $r = bbv_route('voucher-status', 'POST', $f['amit'], (int) $v['id'], ['action' => 'submit']);
    t_eq($r['status'] ?? '', 'SUBMITTED', 'the claimant submits his claim: ' . $flash($r));
    $total = (float) ops_val("SELECT total FROM vouchers WHERE id=?", [$v['id']]);
    $r = bbv_route('voucher', 'GET', $f['neha'], (int) $v['id']);
    t_ok($opened($r, 'ops/voucher_detail') && $total > 0 && strpos($r['out'], 'Approve ' . cur_sym() . nf($total, 0)) !== false,
         'approver · sees "Approve ' . cur_sym() . nf($total, 0) . '" — the real total, not ₹0');
    t_ok(strpos($r['out'], 'Waiting for:') !== false && strpos($r['out'], 'Send back with a reason') !== false, 'approver · approve or send back, and nothing to edit');
    t_ok(strpos($r['out'], 'Change the day') === false && strpos($r['out'], 'Save all rows') === false, 'approver · no change controls on his screen (V2)');
    bbv_route('voucher-save', 'POST', $f['neha'], (int) $v['id'], ['rows' => []]);
    t_eq((float) ops_val("SELECT total FROM vouchers WHERE id=?", [$v['id']]), $total, 'V2 · "Save" by the approver changes nothing');

    // ---- Finance enters the advance when paying (E6) ---------------------------------
    $vp = bbv_voucher($f, $f['amit_ins'], 5000, 'APPROVED', '2031-06');
    db()->prepare("UPDATE vouchers SET advance=0 WHERE id=?")->execute([$vp]);
    $r = bbv_route('voucher-status', 'POST', $f['meera'], $vp, ['action' => 'paid', 'advance' => '1200']);
    t_eq($r['status'] ?? '', 'PAID', 'E6 · Finance marks it paid: ' . $flash($r));
    t_eq((float) ops_val("SELECT advance FROM vouchers WHERE id=?", [$vp]), 1200.0, 'E6 · with the advance Finance entered');
    t_ok((bool) ops_val("SELECT COUNT(*) FROM voucher_approval_log WHERE voucher_id=? AND action='PAID' AND reason LIKE '%Advance%'", [$vp]), 'E6 · the advance is in History');
    $vk = bbv_voucher($f, $f['amit_ins'], 800, 'APPROVED', '2031-07');
    db()->prepare("UPDATE vouchers SET advance=300 WHERE id=?")->execute([$vk]);
    bbv_route('voucher-status', 'POST', $f['meera'], $vk, ['action' => 'paid', 'advance' => '']);
    t_eq((float) ops_val("SELECT advance FROM vouchers WHERE id=?", [$vk]), 300.0, 'E6 · left blank, an existing advance is kept');
    $vd = bbv_voucher($f, $f['amit_ins'], 0, 'DRAFT', '2031-08');
    bbv_route('voucher-header', 'POST', $f['amit'], $vd, ['nature' => 'REVENUE', 'advance' => '999', 'office_incurred' => '0']);
    t_eq((float) ops_val("SELECT advance FROM vouchers WHERE id=?", [$vd]), 0.0, 'E6 · the claimant no longer types the advance');

    // ---- "Save all rows" no longer wipes an amount (old month sheet) -------------------
    db()->prepare("INSERT INTO voucher_entries (voucher_id, entry_date, day_type, hours, amounts, row_total, travel_amount, km) VALUES (?, '2031-08-05', 'WORK', 8, ?, 500, 0, 0)")
        ->execute([$vd, json_encode(['ZZOLD' . $f['tag'] => 500])]);
    $eid = (int) db()->lastInsertId();
    bbv_route('voucher-save', 'POST', $f['amit'], $vd, ['rows' => [$eid => ['hours' => '8', 'mode' => '', 'km' => '0', 'note' => 'site']]]);
    $am = expense_extra_decode((string) ops_val("SELECT amounts FROM voucher_entries WHERE id=?", [$eid]));
    t_eq((float) ($am['ZZOLD' . $f['tag']] ?? 0), 500.0, 'fault fixed · an amount under a head not in his list survives "Save all rows"');
    t_eq((float) ops_val("SELECT total FROM vouchers WHERE id=?", [$vd]), 500.0, '…and the total stays ₹500');

    // ---- An old month voucher shows exactly its figures -----------------------------
    $r = bbv_route('voucher', 'GET', $f['amit'], $vd);
    t_ok($opened($r, 'ops/voucher_detail') && strpos($r['out'], 'August 2031') !== false && strpos($r['out'], cur_sym() . '500') !== false,
         'legacy · an old month voucher opens as "August 2031" with its ₹500');
    t_ok(strpos($r['out'], 'month sheet') !== false, 'legacy · its month sheet is still there for the claimant while it is a draft');
    t_eq((int) ops_val("SELECT COUNT(*) FROM voucher_entries WHERE voucher_id=? AND head_code=?", [$vd, $f['MOB']]), 0, 'legacy · nothing is added to an old month voucher by itself');
    $r = bbv_route('voucher', 'GET', $f['admin'], $vd);
    t_ok($opened($r, 'ops/voucher_detail') && strpos($r['out'], 'month sheet') === false && strpos($r['out'], cur_sym() . '500') !== false, 'legacy · an Admin views it read-only, with its figures');

    // ---- Deadline reopen by N+1 only, with a reason ------------------------------------
    db()->prepare("INSERT INTO vouchers (inspector_id, office_id, month, period_start, period_end, status, total, reopen_asked_at, created_at) VALUES (?,?, '2026-01', '2026-01-01', '2026-01-31', 'DRAFT', 0, ?, ?)")
        ->execute([$f['amit_ins'], $f['mum'], date('c'), date('c')]);
    $late = (int) db()->lastInsertId(); $f['vids'][] = $late;
    $r = bbv_route('voucher-status', 'POST', $f['sunil'], $late, ['action' => 'deadline_reopen', 'reason' => 'ok']);
    t_eq((string) ops_val("SELECT deadline_open_until FROM vouchers WHERE id=?", [$late]), '', 'deadline · N+2 cannot reopen it');
    $r = bbv_route('voucher-status', 'POST', $f['neha'], $late, ['action' => 'deadline_reopen', 'reason' => '']);
    t_eq((string) ops_val("SELECT deadline_open_until FROM vouchers WHERE id=?", [$late]), '', 'deadline · not without a reason');
    $r = bbv_route('voucher-status', 'POST', $f['neha'], $late, ['action' => 'deadline_reopen', 'reason' => 'was on leave']);
    t_ok((string) ops_val("SELECT deadline_open_until FROM vouchers WHERE id=?", [$late]) >= $t, 'deadline · his N+1 reopens it with a reason: ' . $flash($r));
    t_ok((bool) ops_val("SELECT COUNT(*) FROM voucher_approval_log WHERE voucher_id=? AND action='REOPENED' AND level_key='DEADLINE' AND reason='was on leave'", [$late]), 'deadline · logged in History with the reason');
    t_eq((string) ops_val("SELECT status FROM vouchers WHERE id=?", [$late]), 'DRAFT', 'deadline · no new status — it is still a DRAFT');

    // ---- Settings: the Master Admin's -----------------------------------------------
    $r = bbv_route('voucher-rules', 'GET', $f['admin'], 0);
    t_ok(!$opened($r, 'ops/voucher_rules'), 'settings · an Admin does not open the expense rules');
    $r = bbv_route('voucher-rules', 'GET', $f['master'], 0);
    t_ok($opened($r, 'ops/voucher_rules') && strpos($r['out'], 'Claim period') !== false && strpos($r['out'], 'Bands') !== false, 'settings · the Master Admin does');
    bbv_route('voucher-rules', 'POST', $f['master'], 0, ['_do' => 'rules', 'period' => 'FORTNIGHTLY', 'day_days' => '8', 'claim_days' => '8', 'km_tol' => '10']);
    t_eq((string) ops_val("SELECT svalue FROM settings WHERE skey='exp_claim_period'"), 'FORTNIGHTLY', 'settings · the claim period is saved');
    t_eq((string) ops_val("SELECT svalue FROM settings WHERE skey='exp_km_tolerance_pct'"), '10', 'settings · the distance tolerance is saved');
    $r = bbv_route('voucher-rules', 'GET', $f['master'], 0, [], ['band' => $f['band']]);
    t_ok($opened($r, 'ops/voucher_rules') && strpos($r['out'], 'what it may claim') !== false, 'settings · a band opens with its amounts');
} finally {
    exp1_cleanup($f);
    try { db()->prepare("UPDATE users SET permissions='' WHERE username LIKE ?")->execute([$f['tag'] . '_%']); } catch (Throwable $e) {}
}
t_ok(!ops_one("SELECT id FROM calls WHERE id=?", [$f['call']]), 'the fixture removed its own rows');
