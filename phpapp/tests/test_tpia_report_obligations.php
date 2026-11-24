<?php
// TPIA Phase 1 — items 7, 8, 9, 10 (owner, 25 Nov 2026: D5, D6).
//   7. "One report per inspection day": each visit day owes its own report, and
//      the job / call page says "Day reports: n of m".
//   8. Man-month (posting) reports in FIXED periods from the job start date; a
//      late report never moves the next due date (no drift).
//   9. "Reports to write" counts a job while ANY day / period is missing — not
//      once, and not dropped at its first report.
//  10. Reminders follow the missing reports, not calendar days.
// Made-up data: contract C-100, PO-4587, Inspector X on day 1, Inspector Y on day 5.
t_section('TPIA Phase 1 — report obligations (per day, fixed periods, no drift)');

t_ok(isset(REPORT_FREQ['PER_DAY']) && REPORT_FREQ['PER_DAY'] === 'One report per inspection day', 'the frequency list has "One report per inspection day"');
t_ok(isset(lk_options_or('reporting_frequency', REPORT_FREQ)['PER_DAY']), '… and the editable list offers it');
foreach (['DAILY', 'ALTERNATE', 'WEEKLY', 'FORTNIGHTLY', 'MONTHLY', 'CUSTOM', 'NOREPORT'] as $k) t_ok(isset(REPORT_FREQ[$k]), "existing value $k is kept");

// ---- fixed periods (pure) ----------------------------------------------------
$p = report_periods('WEEKLY', '2026-01-05', '2026-03-29');
t_eq(count($p), 12, '12-week posting, weekly: 12 periods');
t_eq($p[0], ['2026-01-05', '2026-01-11'], 'week 1 runs from the start date for 7 days');
t_eq($p[1], ['2026-01-12', '2026-01-18'], 'week 2 follows on, fixed');
t_eq(report_periods('FORTNIGHTLY', '2026-01-05', '2026-02-01'), [['2026-01-05', '2026-01-18'], ['2026-01-19', '2026-02-01']], 'fortnightly: 14-day periods from the start');
t_eq(report_periods('CUSTOM', '2026-01-05', '2026-01-14', 4), [['2026-01-05', '2026-01-08'], ['2026-01-09', '2026-01-12'], ['2026-01-13', '2026-01-14']], 'custom every 4 days, last period cut at the end date');
t_eq(report_periods('ALTERNATE', '2026-01-05', '2026-01-09'), [['2026-01-05', '2026-01-06'], ['2026-01-07', '2026-01-08'], ['2026-01-09', '2026-01-09']], 'alternate day: 2-day periods');
t_eq(report_periods('MONTHLY', '2026-01-10', '2026-04-09'), [['2026-01-10', '2026-02-09'], ['2026-02-10', '2026-03-09'], ['2026-03-10', '2026-04-09']], 'monthly by the start date (10th to 9th) — owner D6');
t_eq(report_periods('MONTHLY', '2026-01-31', '2026-03-30')[0], ['2026-01-31', '2026-02-27'], 'a start on the 31st runs to the end of a short month');
t_eq(report_periods('CUSTOM', '2026-01-05', '2026-01-14', 0), [], 'custom with no N has no periods (one report, as before)');

// ---- scenario 10: weekly posting, week 1's report filed on day 9 ------------------
$D = fn($n) => date('Y-m-d', strtotime('2026-01-05 +' . $n . ' day'));
$dep = ['id' => 0, 'reporting_frequency' => 'WEEKLY', 'inspection_start_date' => $D(0), 'inspection_end_date' => $D(83)];
$ev = ['reports' => [['irn' => 'W1', 'type_code' => 'IR', 'status' => 'SUBMITTED', 'finalized' => 0, 'inspection_date' => $D(8), 'inspector_id' => 0]], 'visits' => []];
$o = ops_report_obligations($dep, $D(9), $ev);
t_eq($o['kind'], 'PERIOD', 'a weekly posting reports in periods');
t_eq($o['total'], 12, '… 12 of them');
t_ok($o['items'][0]['met'] && $o['items'][0]['by'] === 'W1', "scenario 10: week 1's report filed on day 9 counts for week 1");
t_eq($o['next_due'], $D(13), 'scenario 10: week 2 is still due on day 14 (no drift)');
t_eq($o['missing'], 0, 'on day 10 nothing is missing yet');
$o14 = ops_report_obligations($dep, $D(14), $ev);
t_eq([$o14['missing'], $o14['overdue'], $o14['oldest_missing']], [1, 1, $D(13)], 'on day 15, week 2 is missing and overdue since day 14');
$ev2 = ['reports' => [['irn' => 'W1a', 'type_code' => 'IR', 'status' => 'APPROVED', 'finalized' => 0, 'inspection_date' => $D(2), 'inspector_id' => 0],
                       ['irn' => 'W1b', 'type_code' => 'IR', 'status' => 'APPROVED', 'finalized' => 0, 'inspection_date' => $D(5), 'inspector_id' => 0]], 'visits' => []];
$o2 = ops_report_obligations($dep, $D(14), $ev2);
t_eq($o2['missing'], 1, 'two reports dated in week 1 never cover week 2');
$ev3 = $ev; $ev3['reports'][] = ['irn' => 'D', 'type_code' => 'IR', 'status' => 'DRAFT', 'finalized' => 0, 'inspection_date' => $D(12), 'inspector_id' => 0];
t_eq(ops_report_obligations($dep, $D(14), $ev3)['missing'], 1, 'a draft is still a report to write');
$ev4 = $ev; $ev4['reports'][] = ['irn' => 'RN1', 'type_code' => 'RN', 'status' => 'ISSUED', 'finalized' => 1, 'inspection_date' => $D(12), 'inspector_id' => 0];
t_eq(ops_report_obligations($dep, $D(14), $ev4)['missing'], 1, 'a Release Note is not a progress report');
$ev5 = $ev; $ev5['reports'][] = ['irn' => 'OLD', 'type_code' => 'IR', 'status' => 'ISSUED', 'finalized' => 1, 'inspection_date' => $D(12), 'inspector_id' => 0, 'revised_by_id' => 99];
t_eq(ops_report_obligations($dep, $D(14), $ev5)['missing'], 1, 'a revision replaced by a later one is counted once');
$early = $dep + ['billing_stop_on' => $D(20)];
t_eq(ops_report_obligations($early, $D(60), $ev)['total'], 3, 'an early end stops the periods (3 weeks, not 12)');

// ---- per inspection day (scenarios 7 and 8) ----------------------------------------
$days = [];
for ($t = strtotime('2026-02-02'); count($days) < 10; $t += 86400) if (is_working_day(date('Y-m-d', $t), null)) $days[] = date('Y-m-d', $t);
$visits = [];
foreach ($days as $i => $d) $visits[] = ['visit_date' => $d, 'status' => 'PLANNED', 'inspector_id' => $i < 4 ? 501 : 502, 'report_link' => ''];
$md = ['id' => 0, 'reporting_frequency' => 'PER_DAY', 'inspection_start_date' => $days[0], 'inspection_end_date' => $days[9]];
$r1 = ['irn' => 'IR-D1', 'type_code' => 'IR', 'status' => 'SUBMITTED', 'finalized' => 0, 'inspection_date' => $days[0], 'inspector_id' => 501];
$o = ops_report_obligations($md, $days[9], ['reports' => [$r1], 'visits' => $visits]);
t_eq([$o['kind'], $o['total'], $o['met']], ['DAY', 10, 1], 'scenario 8: 10-day man-day job after day 1\'s report — 1 of 10');
t_eq($o['missing'], 9, '… the 9 days since without their own report are missing (counted, not dropped)');
t_ok(!$o['items'][3]['met'], "scenario 7: day 4 is not covered by day 1's report");
$rY = ['irn' => 'IR-D5', 'type_code' => 'IR', 'status' => 'SUBMITTED', 'finalized' => 0, 'inspection_date' => $days[4], 'inspector_id' => 502];
$rWrong = ['irn' => 'IR-X5', 'type_code' => 'IR', 'status' => 'SUBMITTED', 'finalized' => 0, 'inspection_date' => $days[5], 'inspector_id' => 501];
$o = ops_report_obligations($md, $days[9], ['reports' => [$r1, $rY, $rWrong], 'visits' => $visits]);
t_ok($o['items'][4]['met'], "Inspector Y's own report covers day 5 (Y's day)");
t_ok(!$o['items'][5]['met'], "Inspector X's report cannot stand in for Y's day 6");
$visits[2]['status'] = 'NO_INSPECTION';
$o = ops_report_obligations($md, $days[9], ['reports' => [$r1], 'visits' => $visits]);
t_eq($o['total'], 9, 'a "No inspection" day owes no report');
$visits[1]['status'] = 'DONE'; $visits[1]['report_link'] = 'https://x.test/r2';
t_ok(ops_report_obligations($md, $days[9], ['reports' => [$r1], 'visits' => $visits])['items'][1]['met'], 'a day closed with a report link counts');
t_eq(ops_report_obligations(['reporting_frequency' => 'NOREPORT'] + $md, $days[9], ['reports' => [], 'visits' => $visits])['kind'], 'NONE', 'scenario 9: a "No report" job owes nothing');
t_eq(ops_report_obligations(['reporting_frequency' => 'ONCE', 'id' => 0, 'scheduled_date' => '2026-02-02'], '2026-03-01', ['reports' => [], 'visits' => []])['kind'], 'LEGACY', 'an older value (ONCE) still owes one report, as before');
t_eq(ops_report_obligations(['reporting_frequency' => 'WEEKLY', 'id' => 0], '2026-03-01', ['reports' => [], 'visits' => []])['missing'], 1, 'a job with no dates still owes one report, counted now (as before)');

// ---- the figure, the job line and the reminders on real rows --------------------------
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $pdo = db();
    $pdo->prepare("INSERT INTO offices (code, name) VALUES (?,?)")->execute(['TPO', 'TPIA obligations office']);
    $off = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO inspectors (name, home_office_id, email) VALUES ('TPO Inspector X', ?, 'x@example.test')")->execute([$off]);
    $insX = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO inspectors (name, home_office_id) VALUES ('TPO Inspector Y', ?)")->execute([$off]);
    $insY = (int)$pdo->lastInsertId();
    $past = [];
    for ($t = strtotime('-20 days'); count($past) < 10; $t += 86400) if (is_working_day(date('Y-m-d', $t), $off)) $past[] = date('Y-m-d', $t);
    $mk = function ($code, $freq, $start, $end, $ins) use ($pdo, $off) {
        $pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, inspector_id, reporting_frequency, scheduled_date, inspection_start_date, inspection_end_date, closed_flag, created_at) VALUES (?,?,?,?,?,?,?,0,?)")
            ->execute([$code, $off, $ins, $freq, $start, $start, $end, 'x']);
        return (int)$pdo->lastInsertId();
    };
    $jMd = $mk('TPO-MD', 'PER_DAY', $past[0], $past[9], $insX);
    foreach ($past as $i => $d) $pdo->prepare("INSERT INTO job_visits (job_id, visit_date, inspector_id, status) VALUES (?,?,?,'PLANNED')")->execute([$jMd, $d, $i < 4 ? $insX : $insY]);
    $pdo->prepare("INSERT INTO report_docs (job_id, irn, type_code, status, finalized, inspection_date, inspector_id, deleted, data, created_at) VALUES (?,?,?,?,0,?,?,0,'[]',?)")
        ->execute([$jMd, 'TPO-IR-1', 'IR', 'SUBMITTED', $past[0], $insX, 'x']);
    $jNo = $mk('TPO-NR', 'NOREPORT', $past[0], $past[2], $insX);
    $jDone = $mk('TPO-ONE', 'PER_DAY', $past[0], $past[0], $insX);
    $pdo->prepare("INSERT INTO report_docs (job_id, irn, type_code, status, finalized, inspection_date, inspector_id, deleted, data, created_at) VALUES (?,?,?,?,1,?,?,0,'[]',?)")
        ->execute([$jDone, 'TPO-IR-2', 'IR', 'ISSUED', $past[0], $insX, 'x']);
    $ids = array_map('intval', array_column(ops_all("SELECT j.id FROM jobs j WHERE j.executing_office_id=? AND " . opk_job_pred('to_write'), [$off]) ?: [], 'id'));
    t_ok(in_array($jMd, $ids, true), 'scenario 8: "Reports to write" still counts the man-day job after day 1\'s report');
    t_ok(!in_array($jNo, $ids, true), 'scenario 9: a "No report" job is never counted');
    t_ok(!in_array($jDone, $ids, true), 'a one-day job with its day report is not counted');
    t_eq(opk_count_my_jobs('to_write', $insX), count(array_intersect($ids, [$jMd, $jDone])), "the inspector's own figure reads the same calculation");
    $line = ops_report_progress_line(['id' => $jMd]);
    t_ok(strpos($line, 'Day reports: 1 of 10') === 0, 'scenario 8: the job page shows "Day reports: 1 of 10" (' . $line . ')');
    t_eq(ops_report_progress_line(['id' => $jNo]), '', 'a "No report" job shows no report line');

    // Reminders: the man-day job is reminded for its missing day; the No-report job
    // gets only the existing close reminder (its end date has passed), never a report one.
    $mails = [];
    $GLOBALS['__tpo_mail_hook'] = true;
    $pdo->exec("UPDATE jobs SET last_reminder='', last_escalation='' WHERE job_code LIKE 'TPO-%'");
    $before = (int)ops_val("SELECT COUNT(*) FROM email_log") ?: 0;
    ops_run_reminders();
    $rows = ops_all("SELECT subject FROM email_log ORDER BY id DESC LIMIT 50") ?: [];
    $subj = implode(' | ', array_column($rows, 'subject'));
    t_ok(strpos($subj, 'TPO-MD (day report for') !== false, 'item 10: the man-day job is reminded about its missing day report');
    t_ok(strpos($subj, 'TPO-NR (day report') === false && strpos($subj, 'TPO-NR (report') === false, 'scenario 9: the "No report" job is never reminded about a report');
    t_ok((string)ops_val("SELECT last_reminder FROM jobs WHERE id=?", [$jDone]) !== date('Y-m-d') || strpos($subj, 'TPO-ONE (day report') === false, 'the job with its report gets no report reminder');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
