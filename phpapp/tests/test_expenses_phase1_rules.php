<?php
// Expenses Phase 1 (owner, 17 Nov 2026 — design v3, doc 36) — the rules, at
// the level of the functions every screen and route asks:
//   periods (weekly cut at the month end, fortnight 1–15 / 16–end, monthly);
//   band vs person vs effective date (no amount in code);
//   no expense without an own allocated, closed job day (V1);
//   "not for a job" types only when switched on (E5);
//   a bill needs its photo before submit; a no-bill line its note, and above
//   its daily limit the approver is warned (E3);
//   duplicates refused (date + type + amount; the same photo twice);
//   the usual distance: a different km needs a reason, flagged in amber;
//   the two deadlines, and reopen by the claimant's N+1 only (E4);
//   V2 — only the claimant changes lines; approvers, Finance, admins do not.
t_section('Expenses Phase 1 — periods, bands, day close, duplicates, distance, deadlines, V2');

require_once __DIR__ . '/_exp1_fixture.php';
$f = exp1_fixture();
try {
    // ---- Periods (E1) — never across a month end ------------------------------
    t_eq(exp_period_for('2026-11-15', 'FORTNIGHTLY'), ['2026-11-01', '2026-11-15'], 'fortnight · 1–15');
    t_eq(exp_period_for('2026-11-16', 'FORTNIGHTLY'), ['2026-11-16', '2026-11-30'], 'fortnight · 16–end (30 days)');
    t_eq(exp_period_for('2027-02-20', 'FORTNIGHTLY'), ['2027-02-16', '2027-02-28'], 'fortnight · 16–end of February');
    t_eq(exp_period_for('2026-11-30', 'WEEKLY'), ['2026-11-30', '2026-11-30'], 'week · Monday 30 Nov is cut at the month end');
    t_eq(exp_period_for('2026-12-03', 'WEEKLY'), ['2026-12-01', '2026-12-06'], 'week · the first week of December starts on the 1st (Tue)');
    t_eq(exp_period_for('2026-11-18', 'WEEKLY'), ['2026-11-16', '2026-11-22'], 'week · Monday to Sunday');
    t_eq(exp_period_for('2026-11-18', 'MONTHLY'), ['2026-11-01', '2026-11-30'], 'month · the whole month');
    t_eq(exp_rules()['period'], 'MONTHLY', 'the default period stays monthly until the owner switches it');
    t_eq(voucher_period(['month' => '2026-10', 'period_start' => '']), ['2026-10-01', '2026-10-31'], 'an old voucher is the whole month');
    setting_set('exp_claim_period', 'FORTNIGHTLY');
    $v16 = exp_claim_for($f['amit_ins'], '2031-03-20');
    t_eq([$v16['period_start'], $v16['period_end'], $v16['month']], ['2031-03-16', '2031-03-31', '2031-03'], 'a new claim gets its period; month stays YYYY-MM for the freeze and the cost run');
    t_eq((int) exp_claim_for($f['amit_ins'], '2031-03-28')['id'], (int) $v16['id'], 'a later day of the same fortnight lands in the same claim');
    t_ok((int) exp_claim_for($f['amit_ins'], '2031-03-05')['id'] !== (int) $v16['id'], 'the first fortnight is its own claim');
    setting_set('exp_claim_period', 'MONTHLY');
    db()->prepare("INSERT INTO vouchers (inspector_id, office_id, month, status, total, created_at) VALUES (?,?, '2031-04', 'DRAFT', 0, ?)")->execute([$f['amit_ins'], $f['mum'], date('c')]);
    $legacy = (int) db()->lastInsertId(); $f['vids'][] = $legacy;
    setting_set('exp_claim_period', 'WEEKLY');
    t_eq((int) exp_claim_for($f['amit_ins'], '2031-04-22')['id'], $legacy, 'an existing month voucher keeps holding its month after the setting changes');
    setting_set('exp_claim_period', 'MONTHLY');

    // ---- Band vs person vs effective date ---------------------------------------
    $t = $f['today']; $y = date('Y-m-d', strtotime('-1 day'));
    t_eq(exp_entitlement($f['amit_ins'], 'HEAD', $f['DA'], $t)['rate'], 300.0, 'band · the daily allowance comes from his band');
    t_eq(exp_entitlement($f['amit_ins'], 'HEAD', $f['DA'], $t)['rate_source'], 'band', 'band · and says so');
    t_ok(!exp_entitlement($f['amit_ins'], 'MODE', $f['mode2'], $t)['allowed'], 'band · the car is not on his band');
    exp_save_rows('person', $f['amit_ins'], $t, [['HEAD', $f['DA'], -1, 350, null], ['MODE', $f['mode2'], 1, null, null]]);
    t_eq(exp_entitlement($f['amit_ins'], 'HEAD', $f['DA'], $t)['rate'], 350.0, 'person · his own amount from today');
    t_ok(exp_entitlement($f['amit_ins'], 'HEAD', $f['DA'], $t)['allowed'], 'person · "follows band" keeps it allowed');
    t_eq(exp_entitlement($f['amit_ins'], 'HEAD', $f['DA'], $y)['rate'], 300.0, 'effective date · yesterday keeps the band amount (history kept)');
    t_ok(exp_entitlement($f['amit_ins'], 'MODE', $f['mode2'], $t)['allowed'], 'person · an extra type (own car) on his profile');
    t_eq(exp_entitlement($f['amit_ins'], 'MODE', $f['mode2'], $t)['rate'], 12.0, 'no band or person rate · the travel mode\'s default (data, not code)');
    exp_save_rows('band', $f['band'], date('Y-m-d', strtotime('+10 days')), [['HEAD', $f['DA'], 1, 320, null], ['MODE', $f['mode'], 1, 7, null]]);
    t_eq(exp_entitlement($f['amit_ins'], 'MODE', $f['mode'], date('Y-m-d', strtotime('+11 days')))['rate'], 7.0, 'a band rate from a later date applies from that date');
    t_eq(exp_entitlement($f['amit_ins'], 'MODE', $f['mode'], $t)['rate'], 6.0, '…and not before it');
    t_eq(exp_entitlement($f['amit_ins'], 'HEAD', $f['DA'], date('Y-m-d', strtotime('+11 days')))['rate'], 350.0, 'the person\'s own amount still wins over the band');
    db()->prepare("DELETE FROM inspector_allowances WHERE band_id=? AND effective_from>?")->execute([$f['band'], $t]);
    t_ok(exp_entitlement($f['rahul_ins'], 'HEAD', $f['DA'], $t)['allowed'], 'no band, no rows · every job-day type, as before');
    t_ok(!exp_entitlement($f['rahul_ins'], 'HEAD', $f['TEL'], $t)['allowed'], 'no band · a "not for a job" type is never on by default (E5)');

    // ---- V1 — only an own, allocated, closed job day ----------------------------
    [$ok, $msg] = exp_day_confirm($f['amit_ins'], $f['job'], $t, ['km' => '42', 'mode' => $f['mode']]);
    t_ok(!$ok && stripos($msg, 'Close the day') !== false, 'V1 · no expense before the day is closed: ' . $msg);
    [$ok, $msg] = exp_day_confirm($f['rahul_ins'], $f['job'], $t, ['km' => '42', 'mode' => $f['mode']]);
    t_ok(!$ok && stripos($msg, 'not allocated to you') !== false, 'V1 · nobody claims on a job that is not theirs');
    exp1_close_job($f['job']);
    [$ok, $msg] = exp_day_confirm($f['amit_ins'], $f['job'], date('Y-m-d', strtotime('-3 days')), ['km' => '42', 'mode' => $f['mode']]);
    t_ok(!$ok && stripos($msg, 'not one of your days') !== false, 'V1 · nor on a day that is not one of his days on the job');
    bbv_as($f['amit']);
    [$ok, $msg, $cv] = exp_day_confirm($f['amit_ins'], $f['job'], $t, ['km' => '42', 'mode' => $f['mode'], 'tick' => [$f['OUT'] => '1']]);
    t_ok($ok, 'V1 · on his closed day, the day is confirmed: ' . $msg);
    $lines = exp1_lines($cv['id']); $f['vids'][] = (int) $cv['id'];
    $by = []; foreach ($lines as $l) $by[$l['head_code']] = $l;
    t_eq((float) ($by['KMTRAVEL']['travel_amount'] ?? 0), 252.0, 'travel · 42 km × ₹6 from the band = ₹252');
    t_eq((float) ($by[$f['DA']]['row_total'] ?? 0), 350.0, 'allowance · AUTO, at his own amount');
    t_eq((int) ($by[$f['OUT']]['ticked'] ?? 0), 1, 'allowance · TICK, added because he ticked "Outstation today"');
    t_eq((int) ($by['KMTRAVEL']['job_id'] ?? 0), $f['job'], 'the line carries the job');
    t_eq((int) ($by['KMTRAVEL']['client_id'] ?? 0), $f['client'], '…the client');
    t_ok(strpos((string) ($by['KMTRAVEL']['site_label'] ?? ''), 'TB Steel') !== false, '…and the site');
    t_eq(array_sum(array_map(fn($l) => (float) $l['hours'], array_filter($lines, fn($l) => $l['entry_date'] === $t))), 8.0, 'the day carries its 8 hours once');
    [$ok] = exp_day_confirm($f['amit_ins'], $f['job'], $t, ['km' => '42', 'mode' => $f['mode']]);
    t_eq(count(array_filter(exp1_lines($cv['id']), fn($l) => $l['head_code'] === 'KMTRAVEL')), 1, 'confirming the day again replaces it — never twice');
    t_eq(count(array_filter(exp1_lines($cv['id']), fn($l) => $l['head_code'] === $f['OUT'])), 0, '…and an unticked allowance goes');
    t_eq((float) ops_val("SELECT total FROM vouchers WHERE id=?", [$cv['id']]), (float) ops_val("SELECT SUM(row_total) FROM voucher_entries WHERE voucher_id=?", [$cv['id']]), 'the claim total is the sum of its lines');
    t_ok(count(array_filter(exp1_lines($cv['id']), fn($l) => $l['head_code'] === $f['MOB'])) === 1, 'MONTHLY · the mobile allowance is added once to the month');
    exp_claim_refresh(ops_one("SELECT * FROM vouchers WHERE id=?", [$cv['id']]));
    t_eq(count(array_filter(exp1_lines($cv['id']), fn($l) => $l['head_code'] === $f['MOB'])), 1, 'MONTHLY · …and only once');

    // ---- NONJOB only when switched on (E5) -----------------------------------------
    [$ok, $msg] = exp_add_line($f['amit_ins'], $f['TEL'], 120, $t, 0, 'courier');
    t_ok(!$ok && stripos($msg, 'not one of the expense types') !== false, 'E5 · a "not for a job" type that is off for his band is refused');
    exp_save_rows('person', $f['amit_ins'], date('Y-m-d', strtotime('-2 days')), [['HEAD', $f['TEL'], 1, null, null]]);
    [$ok, $msg] = exp_add_line($f['amit_ins'], $f['TEL'], 120, $t, 0, 'courier');
    t_ok($ok, 'E5 · switched on for the person, it is added: ' . $msg);
    [$ok, $msg] = exp_add_line($f['amit_ins'], $f['HOTEL'], 1200, $t, 0, '');
    t_ok(!$ok && stripos($msg, 'goes on a') !== false, 'a job type without a job is refused (use the day close)');

    // ---- Bills: photo before submit; no bill: note + daily limit ------------------
    [$ok, $msg] = exp_add_line($f['amit_ins'], $f['HOTEL'], 3200, $t, $f['job'], '');
    t_ok($ok && stripos($msg, 'photo') !== false, 'BILL · added, and told to add the photo before submit');
    $vv = ops_one("SELECT * FROM vouchers WHERE id=?", [$cv['id']]);
    t_ok((bool) array_filter(exp_submit_problems($vv), fn($p) => stripos($p, 'photo') !== false), 'BILL · submit is held until the photo is there');
    t_ok((bool) array_filter(exp_warnings($vv), fn($w) => stripos($w['text'], 'above the limit') !== false), 'E3 · above the hotel limit — warned, not refused');
    [$ok, $msg] = exp_add_line($f['amit_ins'], $f['RICK'], 150, $t, $f['job'], '');
    t_ok(!$ok && stripos($msg, 'note') !== false, 'NOBILL · needs a short note');
    [$ok] = exp_add_line($f['amit_ins'], $f['RICK'], 150, $t, $f['job'], 'station to plant');
    [$ok2] = exp_add_line($f['amit_ins'], $f['RICK'], 90, $t, $f['job'], 'plant to hotel');
    t_ok($ok && $ok2, 'NOBILL · two rides with notes are added');
    t_ok((bool) array_filter(exp_warnings($vv), fn($w) => stripos($w['text'], 'daily limit') !== false && stripos($w['text'], 'no bill') !== false),
         'NOBILL · ₹240 in a day over the ₹200 daily limit is shown to the approver as "no bill", in amber');

    // ---- Duplicates ------------------------------------------------------------------
    [$ok, $msg] = exp_add_line($f['amit_ins'], $f['RICK'], 150, $t, $f['job'], 'again');
    t_ok(!$ok && stripos($msg, 'twice') !== false, 'duplicate · same date + type + amount is refused');
    $png = "\x89PNG\r\n" . random_bytes(32);
    [$ok, $msg] = exp_add_line($f['amit_ins'], $f['HOTEL'], 900, $t, $f['job'], '', exp1_photo($png));
    t_ok($ok, 'a bill with its photo is added');
    [$ok, $msg] = exp_add_line($f['amit_ins'], $f['HOTEL'], 950, $t, $f['job'], '', exp1_photo($png));
    t_ok(!$ok && stripos($msg, 'already been used') !== false, 'duplicate · the same photo used twice is refused');

    // ---- Usual distance --------------------------------------------------------------
    db()->prepare("INSERT INTO vendor_km_memory (inspector_id, vendor_id, mode_code, km, approved) VALUES (?,?,?,?,1)")->execute([$f['amit_ins'], $f['vendor'], $f['mode'], 40]);
    [$ok, $msg] = exp_day_confirm($f['amit_ins'], $f['job'], $t, ['km' => '50', 'mode' => $f['mode']]);
    t_ok(!$ok && stripos($msg, 'usual distance') !== false, 'distance · 50 km against an approved 40 km needs a reason');
    [$ok] = exp_day_confirm($f['amit_ins'], $f['job'], $t, ['km' => '50', 'mode' => $f['mode'], 'km_reason' => 'road closed, took the bypass']);
    t_ok($ok, 'distance · with a reason it is accepted');
    t_ok((bool) array_filter(exp_warnings($vv), fn($w) => stripos($w['text'], 'usual distance') !== false && stripos($w['text'], 'bypass') !== false),
         'distance · the approver sees it in amber, with the reason');
    setting_set('exp_km_tolerance_pct', '30');
    [$ok] = exp_day_confirm($f['amit_ins'], $f['job'], $t, ['km' => '50', 'mode' => $f['mode']]);
    t_ok($ok, 'distance · within a 30 % tolerance no reason is needed');
    setting_set('exp_km_tolerance_pct', '0');
    exp_on_approved($vv);
    t_eq((float) ops_val("SELECT km FROM vendor_km_memory WHERE inspector_id=? AND vendor_id=?", [$f['amit_ins'], $f['vendor']]), 50.0, 'approved · the approved claim\'s distance becomes the usual distance');
    vendor_km_remember($f['amit_ins'], $f['vendor'], $f['mode'], 77);
    t_eq((float) ops_val("SELECT km FROM vendor_km_memory WHERE inspector_id=? AND vendor_id=?", [$f['amit_ins'], $f['vendor']]), 50.0, 'approved · a draft being typed does not change the usual distance');

    // ---- Deadlines and reopen by N+1 (E4) ----------------------------------------------
    exp1_close_job($f['job_old']);
    $old = $f['old'];
    t_ok(exp_day_lock(['status' => 'DRAFT'], $old) !== '', 'deadline · a day 20 days ago is past the 8-day limit');
    [$ok, $msg] = exp_day_confirm($f['amit_ins'], $f['job_old'], $old, ['km' => '10', 'mode' => $f['mode']]);
    t_ok(!$ok && stripos($msg, 'N+1') !== false, 'deadline · its expenses can no longer be added: ' . $msg);
    setting_set('exp_rules_from', date('Y-m-d', strtotime('-5 days')));
    t_eq(exp_day_lock(['status' => 'DRAFT'], $old), '', 'deadline · a day before the rules started is never locked by them');
    setting_set('exp_rules_from', '2020-01-01');
    db()->prepare("INSERT INTO vouchers (inspector_id, office_id, month, period_start, period_end, status, total, created_at) VALUES (?,?, '2026-01', '2026-01-01', '2026-01-31', 'DRAFT', 0, ?)")->execute([$f['amit_ins'], $f['mum'], date('c')]);
    $late = (int) db()->lastInsertId(); $f['vids'][] = $late;
    $lv = ops_one("SELECT * FROM vouchers WHERE id=?", [$late]);
    t_ok(exp_claim_lock($lv) !== '' && !can_submit_voucher($lv), 'deadline · a claim not submitted within 8 days of its period is locked');
    t_ok(exp_can_ask_reopen($lv), 'deadline · the claimant may ask his N+1 to reopen it');
    bbv_as($f['neha']);
    t_ok(!can_view_voucher($lv), 'N+1 · does not see the draft before being asked');
    db()->prepare("UPDATE vouchers SET reopen_asked_at=? WHERE id=?")->execute([date('c'), $late]);
    $lv = ops_one("SELECT * FROM vouchers WHERE id=?", [$late]);
    t_ok(can_view_voucher($lv) && exp_can_deadline_reopen($lv), 'N+1 · once asked, sees it and may reopen it');
    foreach (['sunil' => 'N+2', 'admin' => 'an Admin', 'master' => 'the Master Admin', 'meera' => 'Finance'] as $who => $lab) {
        bbv_as($f[$who]); t_ok(!exp_can_deadline_reopen($lv), "deadline reopen · not $lab — only the claimant's N+1");
    }
    bbv_as($f['amit']); t_ok(!exp_can_deadline_reopen($lv), 'deadline reopen · not the claimant himself');
    db()->prepare("UPDATE vouchers SET deadline_open_until=?, reopen_asked_at='' WHERE id=?")->execute([date('Y-m-d', strtotime('+8 days')), $late]);
    $lv = ops_one("SELECT * FROM vouchers WHERE id=?", [$late]);
    t_eq(exp_claim_lock($lv), '', 'reopened · the claim is open again for its window');
    t_ok(can_submit_voucher($lv), 'reopened · and the claimant can submit it');

    // ---- V2 — nobody edits someone else's voucher -----------------------------------
    $vv = ops_one("SELECT * FROM vouchers WHERE id=?", [$cv['id']]);
    bbv_as($f['amit']);
    t_ok(can_edit_voucher($vv) && can_submit_voucher($vv) && exp_line_change_problem($vv, $t) === '', 'V2 · the claimant edits and submits his own draft');
    foreach (['master' => 'the Master Admin', 'admin' => 'an Admin', 'neha' => 'his N+1 approver', 'meera' => 'Finance', 'rakesh' => 'central Finance'] as $who => $lab) {
        bbv_as($f[$who]);
        t_ok(!can_edit_voucher($vv) && !can_submit_voucher($vv) && exp_line_change_problem($vv, $t) !== '', "V2 · $lab neither edits nor submits his draft");
    }
    bbv_as($f['admin']); t_ok(can_view_voucher($vv), 'V2 · an Admin still views every voucher');
} finally {
    exp1_cleanup($f);
}
t_ok(!ops_one("SELECT id FROM expense_bands WHERE id=?", [$f['band']]) && !ops_one("SELECT id FROM jobs WHERE id=?", [$f['job']]), 'the fixture removed its own rows');
