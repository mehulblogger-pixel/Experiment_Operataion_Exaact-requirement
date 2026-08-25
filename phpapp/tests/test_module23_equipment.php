<?php
// Module 23 — Equipment 360. A read-only reverse lookup (which released reports rested on
// an instrument) plus a calibration-impact verdict that FLAGS work for controlled quality
// review when the certificate it rested on was later found bad or a coverage gap exists —
// never auto-invalidating, and never over-flagging ordinary expiry after the work.
t_section('Module 23 — equipment calibration impact');

$lib  = file_get_contents(__DIR__ . '/../lib/equipment.php');
$view = file_get_contents(__DIR__ . '/../views/ops/equipment_form.php');

t_ok(function_exists('reports_using_equipment'), 'reports_using_equipment() exists');
t_ok(function_exists('equipment_calibration_impact'), 'equipment_calibration_impact() exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    equipment_migrate();
    $pdo = db();

    // An instrument with a certificate that was valid across mid-2026.
    $pdo->prepare("INSERT INTO equipment (code, name, status, cal_interval_months, created_at) VALUES ('IMP-1','Gauge','ACTIVE',12,?)")->execute([date('c')]);
    $eqId = (int)$pdo->lastInsertId();
    $mkCal = function ($cert, $from, $to, $result) use ($pdo, $eqId) {
        $pdo->prepare("INSERT INTO equipment_calibrations (equipment_id, cert_no, cal_date, valid_to, result, uploaded_at) VALUES (?,?,?,?,?,?)")
            ->execute([$eqId, $cert, $from, $to, $result, date('c')]);
        return (int)$pdo->lastInsertId();
    };
    $goodCal = $mkCal('C-GOOD', '2026-01-01', '2026-12-31', 'PASS');

    // Helper: make a report_doc + link it to the instrument with a stamped certificate.
    $mkDoc = function ($irn, $status, $finalized, $inspDate) use ($pdo) {
        $pdo->prepare("INSERT INTO report_docs (irn, status, finalized, inspection_date, created_at) VALUES (?,?,?,?,?)")
            ->execute([$irn, $status, $finalized, $inspDate, date('c')]);
        return (int)$pdo->lastInsertId();
    };
    $link = function ($docId, $usedOn, $calId) use ($pdo, $eqId) {
        $pdo->prepare("INSERT INTO report_equipment (report_doc_id, equipment_id, used_on, calibration_id, added_at) VALUES (?,?,?,?,?)")
            ->execute([$docId, $eqId, $usedOn, $calId, date('c')]);
    };

    // (1) An ISSUED report that rested on a valid PASS certificate on its work date → Covered.
    $dOK = $mkDoc('R-OK', 'ISSUED', 1, '2026-06-15');
    $link($dOK, '2026-06-15', $goodCal);

    // (2) A DRAFT resting on the same good cert → listed, not counted as impact.
    $dDraft = $mkDoc('R-DRAFT', 'DRAFT', 0, '2026-06-16');
    $link($dDraft, '2026-06-16', $goodCal);

    $imp = equipment_calibration_impact($eqId);
    $byIrn = [];
    foreach ($imp['reports'] as $r) $byIrn[$r['irn']] = $r;

    t_eq(count($imp['reports']), 2, 'both the issued and the draft report are listed by the reverse lookup');
    t_eq($byIrn['R-OK']['verdict'], 'OK', 'an issued report resting on a valid certificate is Covered, not flagged');
    t_ok($byIrn['R-DRAFT']['released'] === false, 'a draft is marked not-released');
    t_eq($imp['released'], 1, 'only the issued report counts as released');
    t_eq($imp['review'], 0, 'a clean instrument raises no review flags');

    // (3) Expiry AFTER the work must not retroactively flag correctly-covered work.
    // The good cert already expires 2026-12-31; today (test clock) is well past mid-2026,
    // yet R-OK rested on a cert valid ON 2026-06-15 → still Covered (asserted above).

    // (4) The certificate the issued report rested on is later found bad (flipped to FAIL).
    $pdo->prepare("UPDATE equipment_calibrations SET result='FAIL' WHERE id=?")->execute([$goodCal]);
    $imp2 = equipment_calibration_impact($eqId);
    $byIrn2 = []; foreach ($imp2['reports'] as $r) $byIrn2[$r['irn']] = $r;
    t_eq($byIrn2['R-OK']['verdict'], 'REVIEW', 'an issued report whose certificate was later marked FAIL is flagged for review');
    t_eq($imp2['review'], 1, 'the released FAIL-basis report is counted as impact');
    // restore
    $pdo->prepare("UPDATE equipment_calibrations SET result='PASS' WHERE id=?")->execute([$goodCal]);

    // (5) A coverage gap: an issued report dated where NO certificate was in force → Review.
    $dGap = $mkDoc('R-GAP', 'ISSUED', 1, '2025-03-01');   // before any certificate
    $link($dGap, '2025-03-01', null);
    $imp3 = equipment_calibration_impact($eqId);
    $byIrn3 = []; foreach ($imp3['reports'] as $r) $byIrn3[$r['irn']] = $r;
    t_eq($byIrn3['R-GAP']['verdict'], 'REVIEW', 'an issued report with no certificate in force on its work date is flagged');
    t_ok(strpos($byIrn3['R-GAP']['why'], 'no valid certificate') !== false, 'the gap flag explains why');

    // (6) The certificate the report rested on is removed (revoked) → Review.
    $dRev = $mkDoc('R-REV', 'APPROVED', 0, '2026-06-20');
    $link($dRev, '2026-06-20', 999999);   // stamped against a cert id that does not exist
    $imp4 = equipment_calibration_impact($eqId);
    $byIrn4 = []; foreach ($imp4['reports'] as $r) $byIrn4[$r['irn']] = $r;
    t_eq($byIrn4['R-REV']['verdict'], 'REVIEW', 'a report whose stamped certificate is gone is flagged (approved counts as released)');

    // (7) An instrument never used → empty lookup, no error.
    $pdo->prepare("INSERT INTO equipment (code, name, status, created_at) VALUES ('IMP-2','Unused','ACTIVE',?)")->execute([date('c')]);
    $eq2 = (int)$pdo->lastInsertId();
    $imp5 = equipment_calibration_impact($eq2);
    t_ok($imp5['reports'] === [] && $imp5['review'] === 0, 'an unused instrument has an empty, unflagged impact');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// Read-only, and the hard block is untouched.
t_ok(strpos($lib, 'REVIEW never means "invalidated"') !== false, 'the impact verdict is documented as flag-not-invalidate');
t_ok(strpos($view, 'Nothing is auto-invalidated') !== false, 'the equipment screen states nothing is auto-invalidated');
t_ok(strpos($lib, 'function report_equipment_block') !== false, 'the finalise hard block is still present, unchanged');
t_ok(!preg_match('/can\(\x27(equipment|calibration)\.[a-z]+\x27\)/', $lib) || strpos($lib, 'master.manage') !== false,
     'Module 23 adds no new permission constant (reuses equipment_can_manage / master.manage)');
t_ok(strpos($lib, 'Used on " . (int)$impact[\'released\']') !== false, 'the expiry reminder carries the blast-radius count');
