<?php
// Phase 2 §9 — return-to-inspector. A return carried only a single free-text note; the inspector saw
// "a comment", not WHICH section/field to fix or BY WHEN. Add structured correction detail (area +
// deadline) alongside the note, on both the vetting return and the approver reject/sendback, surfaced
// on the return banner. Additive: the note is unchanged; the new fields are optional.
t_section('Phase 2 §9 — structured return-to-inspector detail');

t_ok(function_exists('idems_latest_return'), 'idems_latest_return() exists');
$idm  = file_get_contents(__DIR__ . '/../lib/idems.php');
$view = file_get_contents(__DIR__ . '/../views/ops/idems/doc_detail.php');
t_ok(strpos($idm, "ensure_column('report_vetting', 'correction_area'") !== false && strpos($idm, "ensure_column('report_approvals', 'correction_area'") !== false,
    'both return paths gain the structured correction columns');
t_ok(strpos($view, "name=\"correction_area\"") !== false && strpos($view, "name=\"correction_deadline\"") !== false,
    'the return forms capture the section/field and deadline');
t_ok(strpos($view, 'What to correct:') !== false && strpos($view, 'Correct by:') !== false,
    'the return banner shows what to correct and by when');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('idems_migrate')) idems_migrate();
    $pdo = db();
    $pdo->prepare("INSERT INTO report_docs (type_code, status, deleted, created_at) VALUES ('IC','DRAFT',0,?)")->execute([date('c')]);
    $docId = (int)$pdo->lastInsertId();

    // A vetting RETURN with structured detail.
    $pdo->prepare("INSERT INTO report_vetting (report_doc_id, stage, action, note, correction_area, correction_deadline, acted_by, acted_at)
                   VALUES (?, 'VET','RETURNED','Dimensions do not match the drawing','Section 3 — Dimensional table','2026-09-10','QA Manager',?)")
        ->execute([$docId, date('c')]);
    $ret = idems_latest_return(['id' => $docId]);
    t_ok($ret !== null, 'the return is found');
    t_eq($ret['kind'], 'vetting', 'it is recognised as a vetting return');
    t_eq($ret['reason'], 'Dimensions do not match the drawing', 'the free-text reason is preserved');
    t_eq($ret['area'], 'Section 3 — Dimensional table', 'the structured section/field is captured');
    t_eq($ret['deadline'], '2026-09-10', 'the correction deadline is captured');

    // An approver REJECT with structured detail wins if it is newer.
    $pdo->prepare("INSERT INTO report_approvals (report_doc_id, level, status, remarks, correction_area, correction_deadline, acted_by, acted_at)
                   VALUES (?, 1, 'SENTBACK','Fix the material grade','Header — Material grade','2026-09-12','Branch Head', ?)")
        ->execute([$docId, date('c', time() + 60)]);
    $ret2 = idems_latest_return(['id' => $docId]);
    t_eq($ret2['kind'], 'sendback', 'the newer approver send-back is surfaced');
    t_eq($ret2['area'], 'Header — Material grade', 'the approver correction area is captured');
    t_eq($ret2['deadline'], '2026-09-12', 'the approver deadline is captured');

    // A legacy return with no structured detail still works (empty area/deadline, note intact).
    $pdo->prepare("INSERT INTO report_docs (type_code, status, deleted, created_at) VALUES ('IC','DRAFT',0,?)")->execute([date('c')]);
    $d2 = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_vetting (report_doc_id, stage, action, note, acted_by, acted_at) VALUES (?,'VET','RETURNED','old style note','X',?)")->execute([$d2, date('c')]);
    $retLegacy = idems_latest_return(['id' => $d2]);
    t_eq($retLegacy['area'], '', 'a legacy return has an empty correction area (no crash)');
    t_eq($retLegacy['reason'], 'old style note', 'the legacy note is still shown');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
