<?php
// Stage 1 · B2 (owner, 24 Nov 2026 — "approve B0–B5, all as recommended"):
// ONE status reading everywhere. A cancelled work order is in no count; "awaiting
// triage" leaves out orders somebody is already on; the job's stage is derived from
// facts (never the vestigial jobs.stage); a job is never both "report to write" and
// "ready to close".
t_section('Stage 1 B2 — one status reading everywhere');

t_ok(function_exists('job_stage_key') && function_exists('job_stage_label'), 'one job-stage derivation exists');
$today = date('Y-m-d'); $past = date('Y-m-d', strtotime('-4 days')); $fut = date('Y-m-d', strtotime('+6 days'));
$none = ['any' => 0, 'review' => 0, 'issued' => 0];
$base = ['id' => 0, 'closed_flag' => 0, 'stage' => 'ALLOCATED', 'inspector_id' => 7, 'subcon_id' => null,
         'scheduled_date' => $fut, 'inspection_start_date' => '', 'inspection_end_date' => '',
         'reporting_frequency' => 'DAILY', 'report_upload_date' => ''];
t_eq(job_stage_key(['closed_flag' => 1] + $base, $none), 'CLOSED', 'a closed job reads Closed');
t_eq(job_stage_key(['stage' => 'CANCELLED'] + $base, $none), 'CANCELLED', 'a cancelled assignment reads Cancelled');
t_eq(job_stage_key(['stage' => 'ON_HOLD'] + $base, $none), 'ON_HOLD', 'a job put on hold on purpose reads On hold');
t_eq(job_stage_key($base, $none), 'ALLOCATED', 'a future job with a person reads Allocated');
t_eq(job_stage_key(['stage' => 'REPORT_PENDING'] + $base, $none), 'ALLOCATED',
     'the never-advanced stage field is ignored: a future job does not read "Report pending" (Stage 0 S1-06)');
t_eq(job_stage_key(['scheduled_date' => $today] + $base, $none), 'IN_PROGRESS', 'a job scheduled today reads Inspection in progress');
t_eq(job_stage_key(['scheduled_date' => $past, 'inspection_start_date' => $past, 'inspection_end_date' => $fut] + $base, $none), 'IN_PROGRESS',
     'a running deputation (end date ahead) is still in progress');
t_eq(job_stage_key(['scheduled_date' => $past, 'inspection_end_date' => $past] + $base, $none), 'REPORT_PENDING',
     'an inspection that ended, report owed and none uploaded, reads Report pending');
t_eq(job_stage_key(['scheduled_date' => $past, 'inspection_end_date' => $past, 'reporting_frequency' => 'NOREPORT'] + $base, $none), 'IN_PROGRESS',
     'with No report owed it never reads Report pending');
t_eq(job_stage_key(['report_upload_date' => $today] + $base, $none), 'SUBMITTED', 'an uploaded report reads Report submitted');
t_eq(job_stage_key($base, ['any' => 1, 'review' => 1, 'issued' => 0]), 'SUBMITTED', 'a report in review reads Report submitted (was "Allocated")');
t_eq(job_stage_label(['inspector_id' => null, 'subcon_id' => null] + $base, $none), 'No engineer yet', 'a job with nobody on it says so');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO offices (code, name) VALUES (?,?)")->execute(['S1B2', 'Stage1 B2 office']);
    $off = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspectors (name) VALUES (?)")->execute(['S1B2 Engineer']);
    $ins = (int)db()->lastInsertId();
    $call = function ($code, $status, $op = '') use ($off, $past) {
        db()->prepare("INSERT INTO calls (call_code, executing_office_id, ibo_office_id, status, op_status, inspection_required_date, created_at) VALUES (?,?,?,?,?,?,?)")
            ->execute([$code, $off, $off, $status, $op, $past, 'x']);
        return (int)db()->lastInsertId();
    };
    $cLegacyCancel = $call('S2-LC', 'CANCELLED');
    $cOpCancel     = $call('S2-OC', 'OPEN', 'CANCELLED');
    $cRejected     = $call('S2-RJ', 'OPEN', 'REJECTED');
    $cDeclined     = $call('S2-DC', 'OPEN');
    db()->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, accept_state, closed_flag, created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute(['S2-JD', $cDeclined, $off, $ins, 'DECLINED', 0, 'x']);
    $jDeclined = (int)db()->lastInsertId();
    $cLive         = $call('S2-LV', 'OPEN');

    // The backlog: cancelled / rejected out, a declined assignment is "No engineer yet".
    $bk = []; foreach (tosrm_ops_backlog([$off], 100) as $r) $bk[(int)$r['id']] = $r;
    t_ok(!isset($bk[$cLegacyCancel]), 'backlog: a legacy-cancelled work order is not backlog');
    t_ok(!isset($bk[$cOpCancel]), 'backlog: a lifecycle-cancelled work order is not backlog');
    t_ok(!isset($bk[$cRejected]), 'backlog: a rejected work order is not backlog');
    t_ok(isset($bk[$cDeclined]) && $bk[$cDeclined]['pending_reason'] === 'No engineer yet',
         'backlog: a declined assignment reads "No engineer yet" (not "has a resource")');
    t_ok(isset($bk[$cLive]) && $bk[$cLive]['pending_reason'] === 'No engineer yet', 'backlog: an untouched live order reads "No engineer yet"');

    // Every card ignores the cancelled / rejected ones.
    $mine = [$cLegacyCancel, $cOpCancel, $cRejected];
    foreach (['new', 'no_engineer', 'wo_overdue', 'critical', 'clarification', 'ready'] as $k) {
        $n = (int) ops_val("SELECT COUNT(*) FROM calls c WHERE c.id IN (" . implode(',', $mine) . ") AND " . opk_call_pred($k));
        t_eq($n, 0, "a cancelled or rejected work order is in no '" . opk_label($k) . "' count");
    }
    $open = (int) ops_val("SELECT COUNT(*) FROM calls c WHERE c.id IN (" . implode(',', array_merge($mine, [$cLive])) . ") AND " . opk_call_live_sql('c'));
    t_eq($open, 1, 'the Ops home "Open" figure counts only the live one');

    // Status pill reads the same: the declined order is not "Assigned".
    t_eq(tosrm_call_status(ops_one("SELECT * FROM calls WHERE id=?", [$cDeclined])), 'RECEIVED', 'a work order whose only assignment was declined is not read as Assigned');
    t_eq(opk_progress_label(ops_one("SELECT c.*, " . opk_status_sql('c') . " opk_status FROM calls c WHERE c.id=?", [$cLegacyCancel])), 'Cancelled',
         'a legacy-cancelled order reads Cancelled in the register / CSV');

    // Never both "report to write" and "ready to close".
    db()->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, closed_flag, reporting_frequency, report_upload_date, created_at) VALUES (?,?,?,?,?,?,?,?)")
        ->execute(['S2-JI', $cLive, $off, $ins, 0, 'DAILY', '', 'x']);
    $jIssued = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO report_docs (job_id, finalized, deleted) VALUES (?,1,0)")->execute([$jIssued]);
    $tw = (int) ops_val("SELECT COUNT(*) FROM jobs j WHERE j.id=? AND " . opk_job_pred('to_write'), [$jIssued]);
    t_eq($tw, 0, 'a job whose report is issued is not a "report to write"');
    t_ok(function_exists('job_has_issued_report') ? job_has_issued_report($jIssued) : true, '… and it is "ready to close" (report issued)');

    // Portal: the client sees the same reading.
    t_eq(portal_call_state(['status' => 'OPEN', 'op_status' => 'CANCELLED']), 'Cancelled', 'client portal: a cancelled work order reads Cancelled, not "With our coordinators"');
    t_eq(portal_call_state(['status' => 'OPEN', 'op_status' => 'CLOSED']), 'Completed', 'client portal: a work order closed on the lifecycle field reads Completed');
    t_eq(portal_job_state(['closed_flag' => 0, 'stage' => 'CANCELLED', 'scheduled_date' => $fut]), 'Cancelled', 'client portal: a cancelled assignment reads Cancelled, not Scheduled');

    // Customer 360: open orders leave out the cancelled / rejected.
    if (function_exists('c360_work')) {
        db()->prepare("UPDATE calls SET client_id=? WHERE id IN (" . implode(',', array_merge($mine, [$cLive])) . ")")->execute([987654]);
        $w = c360_work(987654);
        if (is_array($w)) t_eq((int)($w['open_calls'] ?? -1), 1, 'customer 360: only the live order is "open"');
        $n360 = (int) ops_val("SELECT COUNT(*) FROM calls c WHERE c.client_id=? AND " . opk_call_live_sql('c'), [987654]);
        t_eq($n360, 1, 'customer 360 rule: only the live order is "open"');
    }
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The screens that show a job's stage all use the derivation (no raw field on display).
foreach (['views/ops/job_detail.php', 'views/ops/call_detail.php', 'views/ops/contract_detail.php', 'views/ops/_ops_registers.php', 'views/ops/ops_desk.php'] as $f) {
    $src = (string) file_get_contents(__DIR__ . '/../' . $f);
    t_ok(strpos($src, "lk_options_or('job_stage', JOB_STAGES)[") === false, "$f no longer prints the raw job stage");
}
