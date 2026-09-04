<?php
// Revamp P5 (R10) — the ops-desk "report pending" metric used to count
// jobs.stage='REPORT_PENDING', a vestigial field the app never advances, so it
// was always 0. It now reads the real signal: a CLOSED job whose report is
// awaiting the reporting manager's sign-off (report_approval='PENDING').
t_section('ops-desk report-pending metric reads the real state (Revamp P5 / R10)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $base = (int)(tosrm_ops_metrics('ALL')['report_pending'] ?? 0);

    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('R10 Co',1,'ACTIVE')")->execute();
    $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (client_id, call_code, created_at) VALUES (?,?,?)")->execute([$cid, 'C-R10', date('c')]);
    $callId = (int)db()->lastInsertId();

    $mkJob = function ($code, $closed, $approval, $stage) use ($callId) {
        db()->prepare("INSERT INTO jobs (call_id, job_code, closed_flag, report_approval, stage, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$callId, $code, $closed, $approval, $stage, date('c')]);
        return (int)db()->lastInsertId();
    };

    // A closed job whose report is pending sign-off → counts.
    $mkJob('J-R10-1', 1, 'PENDING', 'ALLOCATED');
    t_eq((int)tosrm_ops_metrics('ALL')['report_pending'], $base + 1, 'a closed job with report_approval=PENDING is counted');

    // A closed, already-approved report → does not count.
    $mkJob('J-R10-2', 1, 'APPROVED', 'ALLOCATED');
    t_eq((int)tosrm_ops_metrics('ALL')['report_pending'], $base + 1, 'a closed, approved report is not counted');

    // A job carrying the vestigial stage but NOT closed → does not count
    // (proves the metric no longer reads jobs.stage).
    $mkJob('J-R10-3', 0, '', 'REPORT_PENDING');
    t_eq((int)tosrm_ops_metrics('ALL')['report_pending'], $base + 1, 'the vestigial jobs.stage value is ignored — an open job is not counted');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
