<?php
// Phase 2 §39 — the unified QUALITY CASE, as a read-only view over the existing NCR / CAPA /
// Complaint modules. Given any anchor it assembles the linked chain from the foreign keys those
// modules already carry (nonconformities.complaint_id/.capa_id, capa.complaint_id) plus the
// corrective-action outcome (root cause / effectiveness / closure). Non-destructive: the modules
// keep their own tables; nothing is merged or written.
t_section('Phase 2 §39 — quality-case umbrella (read-only over existing modules)');

t_ok(function_exists('quality_case') && function_exists('quality_case_render'), 'quality_case + render helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/qualitycase.php'") !== false, 'the quality-case lib is loaded by the front controller');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('ncr_migrate')) ncr_migrate();
    if (function_exists('capa_migrate')) capa_migrate();
    if (function_exists('complaints_migrate')) complaints_migrate();
    $pdo = db();

    // A complaint -> an NCR raised from it -> a CAPA, with a root cause + effectiveness + closure.
    $pdo->prepare("INSERT INTO complaints (ref, subject, status) VALUES ('CMP-QC-1','Wrong dimensions reported','OPEN')")->execute();
    $cmpId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO capa (ref, title, complaint_id, status, root_cause, effective, verified_on) VALUES ('CAPA-QC-1','Fix the gauge process',?, 'CLOSED','Gauge out of calibration','YES','2026-08-20')")->execute([$cmpId]);
    $capaId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO nonconformities (ref, title, complaint_id, capa_id, status) VALUES ('NCR-QC-1','Dimensional deviation',?,?, 'CLOSED')")->execute([$cmpId, $capaId]);
    $ncrId = (int)$pdo->lastInsertId();

    // From the NCR, the whole case is assembled.
    $case = quality_case('NCR', $ncrId);
    $kinds = array_column($case['members'], 'kind');
    t_ok(in_array('NCR', $kinds, true), 'the NCR is in the case');
    t_ok(in_array('COMPLAINT', $kinds, true), 'the originating complaint is linked in');
    t_ok(in_array('CAPA', $kinds, true), 'the corrective action is linked in');
    t_ok(count($case['members']) === 3, 'the case has exactly the three linked members (no duplicates)');

    // The outcome is read from the corrective action.
    t_ok($case['outcome']['has_capa'] === true, 'the case knows it has a corrective action');
    t_ok($case['outcome']['rca'] === true, 'a recorded root cause is detected');
    t_eq($case['outcome']['effective'], 'YES', 'the effectiveness verdict is surfaced');
    t_ok($case['outcome']['closed'] === true, 'the closure (verified) is detected');

    // Anchoring from the CAPA or the complaint gives the same three members.
    $fromCapa = quality_case('CAPA', $capaId);
    $fromCmp  = quality_case('COMPLAINT', $cmpId);
    t_ok(count($fromCapa['members']) === 3 && count($fromCmp['members']) === 3, 'anchoring from CAPA or complaint assembles the same case');

    // A standalone NCR with no links has no case to consolidate (render stays silent).
    $pdo->prepare("INSERT INTO nonconformities (ref, title, status) VALUES ('NCR-SOLO','Lonely finding','OPEN')")->execute();
    $solo = (int)$pdo->lastInsertId();
    $soloCase = quality_case('NCR', $solo);
    t_ok(count($soloCase['members']) === 1, 'a standalone NCR is a one-member case (nothing to link)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// wiring
$ncrv = file_get_contents(__DIR__ . '/../views/ops/ncr_detail.php');
$capv = file_get_contents(__DIR__ . '/../views/ops/capa_detail.php');
t_ok(strpos($ncrv, "quality_case_render('NCR'") !== false, 'the NCR detail shows the quality case');
t_ok(strpos($capv, "quality_case_render('CAPA'") !== false, 'the CAPA detail shows the quality case');
