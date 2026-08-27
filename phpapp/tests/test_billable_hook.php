<?php
// Revamp P4b — the inline job-close hook. Creates the billable candidate the
// moment a job closes (idempotent), and is self-guarded so it can NEVER affect
// whether a job can close. Read-safe; reuses the same upsert the sync uses.
t_section('billable event — inline job-close hook (P4b)');

billable_migrate();

// Never throws, even on rubbish input (the whole point — it must not break close).
t_eq(billable_on_job_closed(0), 0, 'the hook no-ops on a missing job id');
t_nothrow('the hook never throws', function () { billable_on_job_closed(999999999); });

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('Hook Co','Hook Co',1,'ACTIVE')")->execute();
    $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (client_id, contract_number, call_code, inspection_type, billable_value, billable_rate, billable_qty, created_at)
                   VALUES (?,?,?,?,?,?,?,?)")->execute([$cid, 'HK-1', 'C-HK', 'INSPECTION', 9000, 1500, 6, date('c')]);
    $callId = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO jobs (call_id, job_code, inspection_type, closed_flag, closed_at, executing_office_id, created_at)
                   VALUES (?,?,?,?,?,?,?)")->execute([$callId, 'J-HK-1', 'INSPECTION', 1, '2026-08-12', 1, date('c')]);
    $jobId = (int)db()->lastInsertId();

    // Closing the job queues exactly one PENDING candidate carrying its value.
    $id = billable_on_job_closed($jobId);
    t_ok($id > 0, 'closing a job queues a billable candidate');
    $ev = ops_one("SELECT * FROM billable_events WHERE source_module='job' AND source_id=?", [$jobId]);
    t_ok($ev && $ev['status'] === 'PENDING', 'the queued candidate is PENDING');
    t_eq((float)$ev['amount'], 9000.0, 'it carries the call billable value');
    t_eq((int)$ev['party_id'], $cid, 'it carries the client');

    // Idempotent: firing the hook again (e.g. a re-close attempt) does not duplicate.
    billable_on_job_closed($jobId);
    t_eq((int)ops_val("SELECT COUNT(*) FROM billable_events WHERE source_module='job' AND source_id=?", [$jobId]), 1,
        're-firing the hook does not duplicate the candidate');

    // A human decision is preserved across a re-fire (upsert refreshes only PENDING).
    billable_set_status((int)$ev['id'], 'APPROVED');
    billable_on_job_closed($jobId);
    t_eq(ops_val("SELECT status FROM billable_events WHERE id=?", [(int)$ev['id']]), 'APPROVED',
        'the hook never overwrites an approved decision');

    // An already-invoiced job is not re-queued by the hook.
    db()->prepare("INSERT INTO invoices (invoice_no, partner_id, office_id, status, total, invoice_date, created_at) VALUES ('INV-HK',?,?, 'ISSUED', 9000, '2026-08-20', ?)")
        ->execute([$cid, 1, date('c')]);
    $invId = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO invoice_lines (invoice_id, job_id, description, amount, line_total) VALUES (?,?, 'Inspection', 9000, 9000)")->execute([$invId, $jobId]);
    db()->prepare("INSERT INTO jobs (call_id, job_code, inspection_type, closed_flag, closed_at, executing_office_id, created_at)
                   VALUES (?,?,?,?,?,?,?)")->execute([$callId, 'J-HK-2', 'INSPECTION', 1, '2026-08-13', 1, date('c')]);
    $jobId2 = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO invoices (invoice_no, partner_id, office_id, status, total, invoice_date, created_at) VALUES ('INV-HK2',?,?, 'ISSUED', 5000, '2026-08-21', ?)")
        ->execute([$cid, 1, date('c')]);
    $invId2 = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO invoice_lines (invoice_id, job_id, description, amount, line_total) VALUES (?,?, 'Inspection', 5000, 5000)")->execute([$invId2, $jobId2]);
    t_eq(billable_on_job_closed($jobId2), 0, 'an already-invoiced job is not queued');
    t_eq((int)ops_val("SELECT COUNT(*) FROM billable_events WHERE source_id=?", [$jobId2]), 0, 'no candidate exists for the already-invoiced job');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
