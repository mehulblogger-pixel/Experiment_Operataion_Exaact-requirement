<?php
// Revamp P4c — additional billable-event sources (timesheet, placement) and the
// attested "mark billed (invoice #)" path for events with no automatic invoice
// linkage. Additive, non-destructive.
t_section('billable sources: timesheet, placement, attested billing (P4c)');

billable_migrate();

// The additive bill_ref column exists.
$probe = ops_one("SELECT * FROM billable_events LIMIT 1");
// (may be null on an empty table; assert via a written row below instead)

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('P4c Co',1,'ACTIVE')")->execute();
    $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (client_id, contract_number, call_code, created_at) VALUES (?,?,?,?)")->execute([$cid, 'CT-P4C', 'C-P4C', date('c')]);
    $callId = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO jobs (call_id, job_code, executing_office_id, created_at) VALUES (?,?,?,?)")->execute([$callId, 'J-P4C', 1, date('c')]);
    $jobId = (int)db()->lastInsertId();

    // ---- Timesheet source via the approval hook ----------------------------
    if (function_exists('pdso_migrate')) pdso_migrate();
    $apprId = pdso_att_approval_add(['job_id' => $jobId, 'client_id' => $cid, 'period_from' => '2026-08-01',
        'period_to' => '2026-08-31', 'basis' => 'MANDAY', 'billable_days' => 22, 'status' => 'DRAFT']);
    pdso_att_approval_set_status($apprId, 'APPROVED', 'Client rep');
    $tev = ops_one("SELECT * FROM billable_events WHERE source_module='pdso' AND source_kind='TIMESHEET_APPROVED' AND source_id=?", [$apprId]);
    t_ok($tev && $tev['status'] === 'PENDING', 'approving a deputation timesheet queues a PENDING billable candidate');
    t_eq((float)$tev['qty'], 22.0, 'the candidate carries the man-days');
    t_eq((int)$tev['party_id'], $cid, 'the candidate carries the client');
    t_eq((string)$tev['contract_number'], 'CT-P4C', 'the candidate carries the contract from the job/call');

    // ---- Attested billing (non-job path) -----------------------------------
    t_ok(!billable_mark_billed((int)$tev['id'], 'INV-1'), 'a PENDING event cannot be marked billed (must be approved first)');
    billable_set_status((int)$tev['id'], 'APPROVED');
    t_ok(!billable_mark_billed((int)$tev['id'], ''), 'marking billed requires an invoice reference');
    t_ok(billable_mark_billed((int)$tev['id'], 'INV-2026-42'), 'an approved event is billed with an invoice reference');
    $after = ops_one("SELECT * FROM billable_events WHERE id=?", [(int)$tev['id']]);
    t_eq($after['status'], 'BILLED', 'the event is now BILLED');
    t_eq((string)$after['bill_ref'], 'INV-2026-42', 'the attested invoice reference is stored');

    // ---- Placement fee source via sync -------------------------------------
    db()->prepare("INSERT INTO inspectors (name, status, placement_fee, fee_status, created_at) VALUES ('Placed One','ACTIVE',50000,'CONFIRMED',?)")->execute([date('c')]);
    $insId = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO candidates (first_name, last_name, client_id, inspector_id, stage, created_at) VALUES ('Placed','One',?,?, 'ACCEPTED', ?)")->execute([$cid, $insId, date('c')]);
    // A provisional fee must NOT be derived.
    db()->prepare("INSERT INTO inspectors (name, status, placement_fee, fee_status, created_at) VALUES ('Prov Two','ACTIVE',40000,'PROVISIONAL',?)")->execute([date('c')]);
    $provId = (int)db()->lastInsertId();

    billable_events_sync();
    $pev = ops_one("SELECT * FROM billable_events WHERE source_module='recruit' AND source_kind='PLACEMENT_FEE' AND source_id=?", [$insId]);
    t_ok($pev && $pev['status'] === 'PENDING', 'a confirmed placement fee is derived as a PENDING candidate');
    t_eq((float)$pev['amount'], 50000.0, 'the placement candidate carries the real fee amount');
    t_eq((int)$pev['party_id'], $cid, 'the placement candidate is attributed to the hiring client');
    t_ok(!ops_one("SELECT id FROM billable_events WHERE source_kind='PLACEMENT_FEE' AND source_id=?", [$provId]),
        'a provisional (within-guarantee) fee is NOT billable yet');

    t_ok(isset(BILLABLE_SOURCES['TIMESHEET_APPROVED'], BILLABLE_SOURCES['PLACEMENT_FEE']), 'the new sources are registered');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
