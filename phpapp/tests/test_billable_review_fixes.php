<?php
// PR #2 review fixes (chatgpt-codex-connector findings):
//  1. reconcile a job event to BILLED only on an ISSUED invoice, never a draft;
//  2. the attested billing path rejects job-sourced events (they must reconcile
//     via the real books invoice);
//  3. mobilization_readiness uses scheduled_date when inspection_start_date is a
//     blank string, not today.
t_section('PR #2 review fixes — billable reconcile / attest, mobilization date');

billable_migrate();
if (function_exists('pdso_migrate')) pdso_migrate();
if (function_exists('competence_migrate')) competence_migrate();

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('RF Co',1,'ACTIVE')")->execute();
    $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (client_id, call_code, created_at) VALUES (?,?,?)")->execute([$cid, 'C-RF', date('c')]);
    $callId = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO jobs (call_id, job_code, closed_flag, created_at) VALUES (?,?,?,?)")->execute([$callId, 'J-RF', 1, date('c')]);
    $jobId = (int)db()->lastInsertId();

    // ---- Fix 1: a DRAFT invoice must not reconcile the event to BILLED --------
    $ev = billable_event_upsert('job', 'JOB_CLOSED', $jobId, ['party_id' => $cid, 'amount' => 1000]);
    billable_set_status($ev, 'APPROVED');
    db()->prepare("INSERT INTO invoices (invoice_no, partner_id, status, total, created_at) VALUES ('INV-RF-D',?, 'DRAFT', 1000, ?)")->execute([$cid, date('c')]);
    $draftInv = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO invoice_lines (invoice_id, job_id, amount, line_total) VALUES (?,?,?,?)")->execute([$draftInv, $jobId, 1000, 1180]);
    billable_events_sync();
    t_eq(ops_one("SELECT status FROM billable_events WHERE id=?", [$ev])['status'], 'APPROVED', 'a draft invoice does NOT reconcile the event to BILLED');
    // Issue the invoice → now it reconciles.
    db()->prepare("UPDATE invoices SET status='ISSUED' WHERE id=?")->execute([$draftInv]);
    billable_events_sync();
    t_eq(ops_one("SELECT status FROM billable_events WHERE id=?", [$ev])['status'], 'BILLED', 'once the invoice is issued the event reconciles to BILLED');

    // ---- Fix 2: attested billing rejects a job-sourced event -----------------
    db()->prepare("INSERT INTO jobs (call_id, job_code, closed_flag, created_at) VALUES (?,?,?,?)")->execute([$callId, 'J-RF2', 1, date('c')]);
    $job2 = (int)db()->lastInsertId();
    $ev2 = billable_event_upsert('job', 'JOB_CLOSED', $job2, ['party_id' => $cid, 'amount' => 500]);
    billable_set_status($ev2, 'APPROVED');
    t_ok(billable_mark_billed($ev2, 'INV-TYPED') === false, 'a job-sourced event cannot be billed by attestation');
    t_eq(ops_one("SELECT status FROM billable_events WHERE id=?", [$ev2])['status'], 'APPROVED', 'the job event stays APPROVED (still on the unbilled board)');
    // A non-job (timesheet) event still accepts attestation.
    $evT = billable_event_upsert('pdso', 'TIMESHEET_APPROVED', 777, ['party_id' => $cid, 'qty' => 10, 'amount' => 0]);
    billable_set_status($evT, 'APPROVED');
    t_ok(billable_mark_billed($evT, 'INV-TS-9') === true, 'a timesheet event is billed by attestation');

    // ---- Fix 3: mobilization uses scheduled_date when start is blank ----------
    db()->prepare("INSERT INTO inspectors (name, status, created_at) VALUES ('RF Insp','ACTIVE',?)")->execute([date('c')]);
    $iid = (int)db()->lastInsertId();
    // Mandatory cert valid today (2026-08-27) but lapsed by the scheduled date.
    db()->prepare("INSERT INTO inspector_certs (inspector_id,name,number,valid_to,is_mandatory,status) VALUES (?,?,?,?,1,'VALID')")
        ->execute([$iid, 'Safety Card', 'SC-1', '2026-09-01']);
    db()->prepare("INSERT INTO jobs (call_id, job_code, inspector_id, job_type, dep_status, inspection_start_date, scheduled_date, created_at)
                   VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$callId, 'J-RF-DEP', $iid, 'DEPUTATION', 'MOB_PENDING', '', '2026-12-01', date('c')]);
    $depJob = (int)db()->lastInsertId();
    $r = mobilization_readiness($depJob);
    t_eq($r['on_date'], '2026-12-01', 'readiness falls back to scheduled_date when inspection_start_date is blank');
    t_ok((bool)array_filter($r['blockers'], fn($b) => $b['source'] === 'Competence'),
        'the cert lapsed by the scheduled date is caught (would be missed if it used today)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
