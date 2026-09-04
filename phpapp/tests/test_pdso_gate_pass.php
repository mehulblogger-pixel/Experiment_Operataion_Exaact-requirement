<?php
// Mobilization gate pass (Stage 7). The final site-entry clearance: a gate pass
// CANNOT be issued while any required readiness item is open; once every
// required item is done it can be issued, and the job reads as cleared to deploy.
t_section('mobilization gate pass');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    pdso_gate_migrate();
    pdso_gate_migrate(); // idempotent

    // a deputation job with one REQUIRED mobilization checklist item, still open
    db()->prepare("INSERT INTO jobs (job_code,job_type,dep_status,inspection_start_date,sbu,created_at) VALUES ('GATE-JOB-1','DEPUTATION','MOB_PENDING','2026-11-01','IND',?)")->execute([date('c')]);
    $job = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO dep_checklist (job_id,phase,item,category,required,status,sort_order,updated_at) VALUES (?, 'MOB','Medical fitness','MEDICAL',1,'REQUIRED',1,?)")->execute([$job, date('c')]);

    // not ready → gate cannot be issued
    $g = mobilization_gate($job);
    t_ok(!$g['ready'], 'a job with a required item open is not ready');
    t_ok(!$g['cleared'], 'no gate pass in force yet');
    [$ok, $msg] = mobilization_gate_issue($job, 'EPC');
    t_ok(!$ok, 'a gate pass CANNOT be issued while a required item is open');
    t_ok(strpos($msg, 'Not cleared') !== false, 'the refusal explains it is not cleared');

    // clear the required item → ready → gate can be issued
    $item = (int)ops_val("SELECT id FROM dep_checklist WHERE job_id=? ORDER BY id LIMIT 1", [$job]);
    pdso_checklist_set($item, 'COMPLETED');
    t_ok(mobilization_gate($job)['ready'], 'once the required item is done, the job is ready');
    [$ok2, $msg2] = mobilization_gate_issue($job, 'EPC Technical Manager', 'Verified at gate');
    t_ok($ok2, 'a gate pass can now be issued');
    $g2 = mobilization_gate($job);
    t_ok($g2['cleared'], 'the job now reads as cleared to deploy');
    t_ok(!empty($g2['gate_pass']) && (string)$g2['gate_pass']['issued_by'] === 'EPC Technical Manager', 'the gate pass records who issued it');

    // issuing again is a harmless no-op
    [$ok3, $msg3] = mobilization_gate_issue($job, 'X');
    t_ok($ok3 && strpos($msg3, 'already') !== false, 're-issuing is idempotent');

    // revoke → no longer cleared
    mobilization_gate_revoke($job, 'EPC');
    t_ok(!mobilization_gate($job)['cleared'], 'after revoke the job is no longer cleared');

    // unknown job never errors
    t_ok(!mobilization_gate_issue(0, 'X')[0], 'issuing on an unknown job fails cleanly');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
