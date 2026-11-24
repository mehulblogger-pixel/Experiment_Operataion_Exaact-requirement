<?php
// Once a report of a kind for a P.O. is approved/issued, the items are settled and
// another cannot be prepared for the SAME P.O. — but a different P.O. (same vendor,
// same day) is fine. Guards idems_po_locked().
t_section('P.O. lock: no second report for a settled P.O.');

$pdo = db();
$typeId = idems_build_fire_extinguisher_report();
$pdo->prepare("INSERT INTO business_partners (legal_name,is_client,created_at) VALUES ('POClient',1,?)")->execute([date('c')]);
$cid = (int)$pdo->lastInsertId();

$mk = function ($po, $status) use ($pdo, $typeId, $cid) {
    $pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,client_id,po_ref,irn,status,finalized,data,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$typeId, 'FEXT', $cid, $po, 'POL-' . $po . '-' . $status, $status,
                   $status === 'ISSUED' ? 1 : 0, '[]', date('c')]);
    return (int)$pdo->lastInsertId();
};

$base = ['report_type_id'=>$typeId, 'client_id'=>$cid, 'po_ref'=>'PO-100'];

// No existing report → not locked.
t_ok(idems_po_locked($base) === null, 'a P.O. with no prior report is not locked');

// A DRAFT report for the P.O. does not settle it (only approved/issued do).
$mk('PO-100', 'DRAFT');
t_ok(idems_po_locked($base) === null, 'a draft report does not settle the P.O.');

// An APPROVED report settles it → locked.
$apprId = $mk('PO-100', 'APPROVED');
$lock = idems_po_locked($base);
t_ok(is_array($lock) && (int)$lock['id'] === $apprId, 'an approved report settles the P.O. (locked)');
t_eq($lock['status'], 'APPROVED', 'the lock reports the settling status');

// A DIFFERENT P.O. (same client/type) is NOT locked — several P.O.s per vendor/day.
t_ok(idems_po_locked(['report_type_id'=>$typeId,'client_id'=>$cid,'po_ref'=>'PO-200']) === null,
     'a different P.O. is free to report (multi-PO same vendor/day)');

// A report with no P.O. is never locked (nothing to key the items on).
t_ok(idems_po_locked(['report_type_id'=>$typeId,'client_id'=>$cid,'po_ref'=>'']) === null,
     'a report with no P.O. is not locked');

// Excluding the settling report itself (e.g. when editing it) does not self-lock.
t_ok(idems_po_locked($base, $apprId) === null, 'excluding the settling report itself clears the lock');

// A different report TYPE for the same P.O. is independent.
t_ok(idems_po_locked(['report_type_id'=>$typeId + 99999,'client_id'=>$cid,'po_ref'=>'PO-100']) === null,
     'a different report type on the same P.O. is independent');

// ---------------------------------------------------------------------------
// TPIA Phase 1 / D4 (owner, 25 Nov 2026) — the lock is keyed on the inspection
// call. PO-4587 has two lots; each lot is its own call. Scenario 5: Lot 1's
// report approved, then a Lot 2 report on a different call → allowed (refused
// before this change). Scenario 6: a second report on the SAME call and PO →
// still refused. A man-day job reporting each day may file day 5's own report.
t_section('P.O. lock keyed on the inspection call (TPIA D4)');
$pdo->prepare("INSERT INTO calls (call_code, client_id, supply_po_ref, offered_scope, status, created_at) VALUES ('POL-L1',?,?,?,'OPEN',?)")
    ->execute([$cid, 'PO-4587', 'Lot 1', date('c')]);
$call1 = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO calls (call_code, client_id, supply_po_ref, offered_scope, status, created_at) VALUES ('POL-L2',?,?,?,'OPEN',?)")
    ->execute([$cid, 'PO-4587', 'Lot 2', date('c')]);
$call2 = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, call_id, reporting_frequency, scheduled_date, inspection_start_date, inspection_end_date, created_at) VALUES ('POL-J1',?,?,?,?,?,?)")
    ->execute([$call1, 'PER_DAY', '2026-11-03', '2026-11-03', '2026-11-12', date('c')]);
$job1 = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,client_id,po_ref,call_id,job_id,inspection_date,irn,status,finalized,data,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,0,'[]',?)")
    ->execute([$typeId, 'FEXT', $cid, 'PO-4587', $call1, $job1, '2026-11-03', 'POL-LOT1-D1', 'APPROVED', date('c')]);
$lot1 = (int)$pdo->lastInsertId();
$f1 = ['report_type_id' => $typeId, 'client_id' => $cid, 'po_ref' => 'PO-4587'];
$lk = idems_po_locked($f1 + ['call_id' => $call1]);
t_ok(is_array($lk) && (int)$lk['id'] === $lot1, 'scenario 6: a second report on the SAME call and PO is still refused');
t_ok(idems_po_locked($f1 + ['call_id' => $call2]) === null, 'scenario 5: a Lot 2 report on a DIFFERENT call under PO-4587 is allowed');
t_ok(is_array(idems_po_locked($f1 + ['job_id' => $job1])), 'the call is found through the job when only the job is given');
t_ok(idems_po_locked($f1 + ['call_id' => $call1, 'job_id' => $job1, 'inspection_date' => '2026-11-07']) === null,
     'a man-day job reporting each day: day 5 (Inspector Y) may file its own report on the same call');
t_ok(is_array(idems_po_locked($f1 + ['call_id' => $call1, 'job_id' => $job1, 'inspection_date' => '2026-11-03'])),
     '… but a second report for the SAME day is still refused');
$pdo->prepare("UPDATE jobs SET reporting_frequency='NOREPORT' WHERE id=?")->execute([$job1]);
t_ok(is_array(idems_po_locked($f1 + ['call_id' => $call1, 'job_id' => $job1, 'inspection_date' => '2026-11-07'])),
     'a job that owes no reports gets no day exemption — the call stays settled');
t_ok(is_array(idems_po_locked($f1)), 'a report with no call keeps the old key (P.O. + client + type)');
$src = file_get_contents(__DIR__ . '/../lib/idems.php');
t_ok(strpos($src, '$poLock && !is_master()') !== false, 'the master override is unchanged');
