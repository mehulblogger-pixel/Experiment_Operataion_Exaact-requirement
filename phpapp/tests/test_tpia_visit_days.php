<?php
// TPIA Phase 1 — items 5, 6, 12 (owner, 25 Nov 2026: D5, D8).
//   5. A visit day closes only with ITS OWN report (not "any report on the job").
//   6. A "No report" job closes its days without a report.
//  12. A planned day with no inspection closes as "No inspection" with a reason
//      (editable list) and a note; the planned date stays; an optional new date
//      is a new planned day; both are in History; no report owed; never billed.
// Scenarios 7, 9, 11, 12 of the assessment; the real routes for who may do it.
t_section('TPIA Phase 1 — visit days: own report, No report, No inspection');

t_ok(in_array('reason_code', t_columns('job_visits'), true) && in_array('reason_note', t_columns('job_visits'), true) && in_array('moved_from', t_columns('job_visits'), true),
     'job_visits has reason_code, reason_note, moved_from');
$rs = visit_no_insp_reasons();
foreach (['MATERIAL_NOT_READY', 'POSTPONED_MFR', 'POSTPONED_CLIENT', 'CANCELLED_CLIENT', 'ACCESS_REFUSED', 'OTHER'] as $k) t_ok(isset($rs[$k]), "reason $k is on the list");
t_ok(lk_type('visit_no_insp_reason') !== null, 'the reasons are an editable list in Masters');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $pdo = db();
    t_as_admin();
    $pdo->prepare("INSERT INTO offices (code, name) VALUES (?,?)")->execute(['TPV', 'TPIA visits office']);
    $off = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO inspectors (name, home_office_id) VALUES ('TPV Inspector X', ?)")->execute([$off]);
    $insX = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO inspectors (name, home_office_id) VALUES ('TPV Inspector Y', ?)")->execute([$off]);
    $insY = (int)$pdo->lastInsertId();
    $days = [];
    for ($t = strtotime('-16 days'); count($days) < 10; $t += 86400) if (is_working_day(date('Y-m-d', $t), $off)) $days[] = date('Y-m-d', $t);
    $pdo->prepare("INSERT INTO calls (call_code, executing_office_id, ibo_office_id, contract_number, supply_po_ref, status, created_at) VALUES ('TPV-C',?,?,'C-100','PO-4587','OPEN',?)")->execute([$off, $off, 'x']);
    $call = (int)$pdo->lastInsertId();
    $mk = function ($code, $freq, $n) use ($pdo, $off, $call, $insX, $insY, $days) {
        $pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, reporting_frequency, scheduled_date, inspection_start_date, inspection_end_date, closed_flag, created_at) VALUES (?,?,?,?,?,?,?,?,0,?)")
            ->execute([$code, $call, $off, $insX, $freq, $days[0], $days[0], $days[$n - 1], 'x']);
        $id = (int)$pdo->lastInsertId();
        for ($i = 0; $i < $n; $i++) $pdo->prepare("INSERT INTO job_visits (job_id, visit_date, inspector_id, status) VALUES (?,?,?,'PLANNED')")->execute([$id, $days[$i], $i < 4 ? $insX : $insY]);
        return $id;
    };
    $rep = function ($jobId, $date, $ins, $status = 'SUBMITTED') use ($pdo, $call) {
        $pdo->prepare("INSERT INTO report_docs (job_id, call_id, irn, type_code, status, inspection_date, inspector_id, deleted, data, created_at) VALUES (?,?,?,?,?,?,?,0,'[]',?)")
            ->execute([$jobId, $call, 'TPV-' . $jobId . '-' . $date . '-' . mt_rand(100, 999999), 'IR', $status, $date, $ins, 'x']);
        return (int)$pdo->lastInsertId();
    };
    $vst = fn($j, $d) => (string)ops_val("SELECT status FROM job_visits WHERE job_id=? AND visit_date=?", [$j, $d]);

    // ---- scenario 7: per-day job, day 4 cannot close with day 1's report ----------
    $md = $mk('TPV-MD', 'PER_DAY', 10);
    $rep($md, $days[0], $insX);
    t_eq(job_visit_close($md, $days[0]), '', 'day 1 closes with day 1\'s report');
    $e = job_visit_close($md, $days[3]);
    t_ok(strpos($e, 'Day 4') === 0 && strpos($e, 'needs its own report') !== false, 'scenario 7: day 4 with only day 1\'s report is refused — "' . $e . '"');
    t_eq($vst($md, $days[3]), 'PLANNED', '… and stays open');
    $rep($md, $days[4], $insX);
    t_ok(job_visit_close($md, $days[4]) !== '', "day 5 is Inspector Y's — X's report dated that day does not close it");
    $rep($md, $days[4], $insY);
    t_eq(job_visit_close($md, $days[4]), '', "day 5 closes with Inspector Y's own report");
    t_eq(job_visit_close($md, $days[3], 'https://x.test/day4'), '', 'a day can still be closed with a report link');
    t_eq(job_visit_close($md, $days[3], 'https://x.test/other'), 'That day is already closed.', 'a closed day is never overwritten');

    // ---- scenario 9: "No report" job of 3 days -----------------------------------
    $nr = $mk('TPV-NR', 'NOREPORT', 3);
    foreach ([0, 1, 2] as $i) t_eq(job_visit_close($nr, $days[$i]), '', 'scenario 9: "No report" day ' . ($i + 1) . ' closes without a report');
    t_eq(job_visits_open_days(['id' => $nr]), [], '… and the job has no open day left');
    t_eq(job_final_docs_missing(ops_one("SELECT * FROM jobs WHERE id=?", [$nr])), [], '… and owes no final document');

    // ---- periodic job: a day closes with its period's report -----------------------
    $wk = $mk('TPV-WK', 'WEEKLY', 8);
    t_ok(job_visit_close($wk, $days[1]) !== '', 'weekly job: a day in week 1 with no report yet stays open');
    $rep($wk, $days[2], $insX);
    t_eq(job_visit_close($wk, $days[1]), '', 'weekly job: the week\'s report closes a day of that week');

    // ---- scenario 11: No inspection — Material not ready ---------------------------
    $e = job_visit_no_inspection($md, $days[2], '', '');
    t_ok($e !== '', 'a reason is required');
    t_ok(job_visit_no_inspection($md, $days[2], 'OTHER', '') !== '', '"Other" needs a note');
    t_ok(job_visit_no_inspection($md, $days[2], 'NOT_A_REASON', 'x') !== '', 'a reason not on the list is refused');
    t_eq(job_visit_no_inspection($md, $days[2], 'MATERIAL_NOT_READY', 'Castings not machined'), '', 'scenario 11: day 3 closed as "No inspection — Material not ready"');
    $v3 = ops_one("SELECT * FROM job_visits WHERE job_id=? AND visit_date=?", [$md, $days[2]]);
    t_eq([$v3['status'], $v3['reason_code'], $v3['reason_note'], substr($v3['visit_date'], 0, 10)], ['NO_INSPECTION', 'MATERIAL_NOT_READY', 'Castings not machined', $days[2]], '… the planned date, reason and note are kept');
    t_ok($v3['closed_by'] !== '' && $v3['closed_at'] !== '', '… with who and when');
    t_ok((int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='JOB' AND entity_id=? AND subject LIKE ?", [$md, '%No inspection — Material not ready%']) === 1,
         '… and it is in the job\'s History');
    $o = ops_report_obligations(ops_one("SELECT * FROM jobs WHERE id=?", [$md]));
    t_eq($o['total'], 9, '… no report is asked for that day (9 of 10 days owe one)');
    t_ok(!in_array($days[2], job_visits_open_days(['id' => $md]), true), '… and it does not hold the job open');
    t_ok(job_visit_close($md, $days[2]) !== '' && job_visit_no_inspection($md, $days[2], 'OTHER', 'again') !== '', 'a "No inspection" day cannot be closed again either way');
    t_ok(job_visit_no_inspection($md, $days[0], 'OTHER', 'late') !== '', 'a day already done with its report cannot be turned into "No inspection"');

    // ---- scenario 12: postponed, with a new date ------------------------------------
    $new = date('Y-m-d', strtotime($days[9] . ' +3 days'));
    while (!is_working_day($new, $off)) $new = date('Y-m-d', strtotime($new . ' +1 day'));
    t_ok(job_visit_no_inspection($md, $days[5], 'POSTPONED_MFR', '', $days[5]) !== '', 'the new date must be after the day');
    t_ok(job_visit_no_inspection($md, $days[5], 'POSTPONED_MFR', '', $days[6]) !== '', 'the new date cannot be a day already planned');
    t_eq(job_visit_no_inspection($md, $days[5], 'POSTPONED_MFR', 'Furnace breakdown', $new), '', 'scenario 12: day 6 closed as "No inspection — Postponed" with a new date');
    $nv = ops_one("SELECT * FROM job_visits WHERE job_id=? AND visit_date=?", [$md, $new]);
    t_ok($nv && $nv['status'] === 'PLANNED' && substr($nv['moved_from'], 0, 10) === $days[5], '… the new date is a new planned day that says where it came from');
    t_eq($vst($md, $days[5]), 'NO_INSPECTION', '… and the original day is kept as it was');
    $ev = ops_one("SELECT * FROM assignment_events WHERE job_id=? AND kind='RESCHEDULE' ORDER BY id DESC LIMIT 1", [$md]);
    t_ok($ev && $ev['old_date'] === $days[5] && $ev['new_date'] === $new, '… both are in the assignment history');
    t_ok(in_array($new, job_visits_open_days(['id' => $md]), true), '… the new day must itself be closed before the job can close');
    t_eq(visit_status_label($nv), 'Planned (moved from ' . fdate($days[5]) . ')', 'the new day reads "Planned (moved from …)"');
    t_ok(strpos(visit_status_label(ops_one("SELECT * FROM job_visits WHERE job_id=? AND visit_date=?", [$md, $days[5]])), 'No inspection — Postponed by manufacturer') === 0, 'the old day reads "No inspection — Postponed by manufacturer"');
    // A schedule re-sync never deletes either.
    job_visits_sync($md, $insX);
    t_ok((bool)ops_one("SELECT id FROM job_visits WHERE job_id=? AND visit_date=?", [$md, $new]) && $vst($md, $days[5]) === 'NO_INSPECTION', 'a schedule re-sync keeps the No-inspection day and the moved day');

    // ---- billing: a No-inspection day is never an earned man-day ---------------------
    $doneDays = (int)ops_val("SELECT COUNT(*) FROM job_visits WHERE job_id=? AND status='DONE'", [$md]);
    $src = file_get_contents(__DIR__ . '/../lib/billunits.php');
    t_ok(strpos($src, "v.status='DONE'") !== false, 'billing counts only DONE days (a No-inspection day earns nothing)');
    t_eq($doneDays, 3, '… here 3 done days, the No-inspection days are not among them');

    // ---- the history panel shows it --------------------------------------------------
    $h = chain_history_for_call($call, 'po');
    $titles = implode(' | ', array_column($h['events'], 'title'));
    t_ok(strpos($titles, 'No inspection — Material not ready') !== false && strpos($titles, 'Planned (moved from') !== false, 'the inspection history lists the No-inspection day and the moved day');
    t_as_nobody();
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- who may: the REAL route, in a second process (committed rows, removed after) ----
require_once __DIR__ . '/_exp1_fixture.php';
$f = exp1_fixture();
try {
    $t = $f['today'];
    $d2 = date('Y-m-d', strtotime('+1 day'));
    db()->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, scheduled_date, sbu, reporting_frequency, closed_flag, created_at) VALUES (?,?,?,?,?, 'NDT', 'PER_DAY', 0, ?)")
        ->execute(['TPVR-' . $f['tag'], $f['call'], $f['mum'], $f['amit_ins'], $t, date('c')]);
    $jr = (int)db()->lastInsertId();
    foreach ([$t, $d2] as $d) db()->prepare("INSERT INTO job_visits (job_id, visit_date, inspector_id, status) VALUES (?,?,?, 'PLANNED')")->execute([$jr, $d, $f['amit_ins']]);
    $post = ['date' => $t, 'reason' => 'ACCESS_REFUSED', 'reason_note' => 'Gate pass not issued'];
    bbv_route('job-visit-no-inspection', 'POST', $f['rahul'], 0, $post, ['id' => $jr]);
    t_eq((string)ops_val("SELECT status FROM job_visits WHERE job_id=? AND visit_date=?", [$jr, $t]), 'PLANNED', 'route · another inspector cannot close his day as No inspection');
    $r = bbv_route('job-visit-no-inspection', 'POST', $f['amit'], 0, $post, ['id' => $jr]);
    t_eq((string)ops_val("SELECT status FROM job_visits WHERE job_id=? AND visit_date=?", [$jr, $t]), 'NO_INSPECTION', 'route · the engineer on that day can (the same people as Close day): ' . implode(' | ', $r['flash'] ?? []));
    $r = bbv_route('job-visit-close', 'POST', $f['amit'], 0, ['date' => $d2], ['id' => $jr]);
    t_eq((string)ops_val("SELECT status FROM job_visits WHERE job_id=? AND visit_date=?", [$jr, $d2]), 'PLANNED', 'route · Close day without the day\'s report is refused');
    t_ok(stripos(implode(' | ', $r['flash'] ?? []), 'needs its own report') !== false, '… and says the day needs its own report');
    $ops = file_get_contents(__DIR__ . '/../lib/ops.php');
    $a = strpos($ops, "if (\$route === 'job-visit-no-inspection' && \$method === 'POST')");
    t_ok($a !== false && strpos(substr($ops, $a, 900), "ops_require(is_coordinator_level() || is_master() || \$ownDay, 'You cannot close a visit day.')") !== false,
         'the No-inspection route uses exactly the Close-day gate (no new permission)');
} finally {
    try { db()->prepare("DELETE FROM job_visits WHERE job_id IN (SELECT id FROM jobs WHERE call_id=?)")->execute([$f['call']]); } catch (Throwable $e) {}
    try { db()->prepare("DELETE FROM assignment_events WHERE job_id IN (SELECT id FROM jobs WHERE call_id=?)")->execute([$f['call']]); } catch (Throwable $e) {}
    exp1_cleanup($f);
}
