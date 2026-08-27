<?php
// Field-finding #25 — a call allocated for the 28th showed in the BACKLOG on the 27th. The backlog is
// meant for work needing attention (unscheduled / unassigned / awaiting clarification / overdue), but it
// listed every non-closed call — including work already allocated AND scheduled for a future date, which
// is "in hand" and correctly appears in the Schedule / Assignment registers. Fix: exclude a call that has
// an open, assigned job scheduled for a FUTURE date; overdue and unassigned work stays.
t_section('Field #25 — future-scheduled allocated work is not backlog');

$src = file_get_contents(__DIR__ . '/../lib/tosrm.php');
t_ok(strpos($src, 'Field-finding #25') !== false, 'the backlog query documents the future-scheduled exclusion');
t_ok(strpos($src, "j2.scheduled_date > '\$today'") !== false, 'the backlog excludes an assigned job scheduled after today');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('tosrm_migrate_d')) tosrm_migrate_d();
    $pdo = db();
    $today = date('Y-m-d'); $fut = date('Y-m-d', strtotime('+3 day')); $past = date('Y-m-d', strtotime('-3 day'));
    $pdo->prepare("INSERT INTO inspectors (name, status) VALUES ('I25','ACTIVE')")->execute();
    $ins = (int)$pdo->lastInsertId();

    // A — allocated + scheduled for a FUTURE date → NOT backlog.
    $pdo->prepare("INSERT INTO calls (call_code, status, op_status, inspection_required_date) VALUES ('C25-FUT','ALLOCATED','SCHEDULED',?)")->execute([$fut]);
    $cA = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (job_code, call_id, inspector_id, scheduled_date, closed_flag) VALUES ('J25-FUT',?,?,?,0)")->execute([$cA, $ins, $fut]);
    // B — unassigned/unscheduled call → stays in backlog (needs scheduling).
    $pdo->prepare("INSERT INTO calls (call_code, status, op_status) VALUES ('C25-UNA','OPEN','RECEIVED')")->execute();
    // C — assigned but OVERDUE (scheduled in the past) → stays in backlog (needs attention).
    $pdo->prepare("INSERT INTO calls (call_code, status, op_status, inspection_required_date) VALUES ('C25-OVD','ALLOCATED','ASSIGNED',?)")->execute([$past]);
    $cC = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (job_code, call_id, inspector_id, scheduled_date, closed_flag) VALUES ('J25-OVD',?,?,?,0)")->execute([$cC, $ins, $past]);
    // D — assigned + scheduled for TODAY → stays (today's work, not future).
    $pdo->prepare("INSERT INTO calls (call_code, status, op_status, inspection_required_date) VALUES ('C25-TOD','ALLOCATED','ASSIGNED',?)")->execute([$today]);
    $cD = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (job_code, call_id, inspector_id, scheduled_date, closed_flag) VALUES ('J25-TOD',?,?,?,0)")->execute([$cD, $ins, $today]);

    $codes = array_column(tosrm_ops_backlog('ALL', 200), 'call_code');
    t_ok(!in_array('C25-FUT', $codes, true), 'a future-scheduled, allocated call is NOT in the backlog');
    t_ok(in_array('C25-UNA', $codes, true), 'an unassigned call stays in the backlog');
    t_ok(in_array('C25-OVD', $codes, true), 'an overdue (past-scheduled) call stays in the backlog');
    t_ok(in_array('C25-TOD', $codes, true), "today's scheduled call stays in the backlog (only the future is excluded)");
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
