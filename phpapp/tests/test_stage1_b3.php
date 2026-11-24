<?php
// Stage 1 · B3 (owner, 24 Nov 2026 — "approve B0–B5, all as recommended"): no false
// "ready". Moving a work order to Ready for scheduling needs the existing readiness
// check (complete, or an override with a reason); the client portal's job words follow
// the dates; card notes say what they count; unknown report frequencies are listed.
t_section('Stage 1 B3 — no false "ready"');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO calls (call_code, status, op_status, client_id, inspection_type, inspection_required_date, created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute(['S3-INC', 'OPEN', 'ACCEPTED', 5, 'INSPECTION', date('Y-m-d', strtotime('+3 days')), 'x']);
    $inc = (int)db()->lastInsertId();   // no site / vendor, no deliverable → incomplete
    t_ok(!tosrm_call_ready(ops_one("SELECT * FROM calls WHERE id=?", [$inc]))['ok'], 'the fixture is genuinely incomplete');
    t_ok(!tosrm_set_status($inc, 'READY_TO_SCHEDULE', 'try'), 'an incomplete work order cannot be moved to Ready for scheduling');
    t_eq(ops_val("SELECT op_status FROM calls WHERE id=?", [$inc]), 'ACCEPTED', '… and its status is unchanged');
    t_ok(!tosrm_set_status($inc, 'READY_TO_SCHEDULE', 'mgr', '', true), 'not even a transition override skips the readiness check');
    t_ok(tosrm_override_validation($inc, 'Client confirmed the site by phone'), 'the existing validation override (with a reason) is recorded');
    t_ok(tosrm_set_status($inc, 'READY_TO_SCHEDULE', 'after override'), '… after which Ready for scheduling is allowed');
    t_eq(ops_val("SELECT op_status FROM calls WHERE id=?", [$inc]), 'READY_TO_SCHEDULE', 'the move is saved');
    t_ok(tosrm_set_status($inc, 'ON_HOLD', 'pause'), 'other moves are unaffected');

    db()->prepare("INSERT INTO calls (call_code, status, op_status, client_id, inspection_type, inspection_required_date, vendor_id, deliverables, created_at) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute(['S3-OK', 'OPEN', 'ACCEPTED', 5, 'INSPECTION', date('Y-m-d', strtotime('+3 days')), 9, 'IR', 'x']);
    $ok = (int)db()->lastInsertId();
    t_ok(tosrm_set_status($ok, 'READY_TO_SCHEDULE', 'complete'), 'a complete work order moves to Ready for scheduling as before');

    // Data health: open jobs with a report frequency off the list are listed for correction.
    db()->prepare("INSERT INTO jobs (job_code, call_id, closed_flag, reporting_frequency, created_at) VALUES (?,?,?,?,?)")->execute(['S3-JSINGLE', $ok, 0, 'SINGLE', 'x']);
    $h = opk_freq_health(50);
    t_ok($h['n'] >= 1 && in_array('S3-JSINGLE', array_column($h['jobs'], 'job_code'), true), 'data health lists a job whose frequency ("SINGLE") is not on the list');
    t_ok(strpos(opk_freq_health_html(), 'Data health') !== false, 'the data-health line is drawn when there is something to fix');
    t_eq((string) ops_val("SELECT reporting_frequency FROM jobs WHERE job_code='S3-JSINGLE'"), 'SINGLE', 'nothing is changed automatically');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// Client portal: the words follow the dates.
$today = date('Y-m-d'); $past = date('Y-m-d', strtotime('-10 days')); $fut = date('Y-m-d', strtotime('+20 days'));
t_eq(portal_job_state(['closed_flag' => 0, 'inspection_start_date' => $past, 'inspection_end_date' => $fut, 'scheduled_date' => $past]),
     'Inspection under way', 'a deputation running to a future end date is "under way", not "finished" (Stage 0 S3-04)');
t_eq(portal_job_state(['closed_flag' => 0, 'inspection_start_date' => $past, 'inspection_end_date' => date('Y-m-d', strtotime('-1 day'))]),
     'Inspection finished — report being prepared', 'an inspection whose end date has passed is finished');
t_eq(portal_job_state(['closed_flag' => 0, 'inspection_start_date' => $fut, 'inspection_end_date' => $fut]), 'Scheduled', 'a start date still ahead reads Scheduled');
t_eq(portal_job_state(['closed_flag' => 1]), 'Completed', 'a closed job reads Completed');

// Honest notes.
t_eq(opk_hint('ready'), 'marked ready for scheduling', 'the Ready card no longer claims "all inputs complete"');
t_eq(opk_hint('signoff'), 'closed jobs, report not yet signed off', 'the sign-off card says what it counts (was "done, not issued")');
$s = tosrm_ops_summary(['unscheduled' => 1, 'clarification' => 2, 'ready' => 3, 'overdue' => 4, 'jobs_overdue' => 5, 'critical' => 6, 'report_pending' => 7, 'today' => 8]);
t_ok(strpos($s['text'], 'no engineer yet') !== false && strpos($s['text'], 'awaiting sign-off') !== false, 'the one-line summary uses the card names');
