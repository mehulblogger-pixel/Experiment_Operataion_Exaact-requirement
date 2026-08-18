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
