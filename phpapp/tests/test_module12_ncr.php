<?php
// Module 12 — NCR (toward a Quality Case). Surface & fix: the per-job/report NCR
// register filter (which was generated on the job chip but ignored by the handler),
// a job-scoped fetch for the Job-360 Quality panel, and reachability gating. The
// mature NCR lifecycle and its gates are untouched.
t_section('Module 12 — per-job NCR filter + Job-360 Quality surfacing');

$ncr  = file_get_contents(__DIR__ . '/../lib/ncr.php');
$jobv = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');

t_ok(function_exists('ncr_for_job') && function_exists('ncr_reachable'), 'the surfacing helpers exist');

// The register handler now reads ?job= / ?report= and scopes on them.
t_ok(strpos($ncr, "\$scopeJob    = (int)(\$_GET['job'] ?? 0)") !== false
  && strpos($ncr, "\$scopeReport = (int)(\$_GET['report'] ?? 0)") !== false,
    'the NCR register now honours ?job= and ?report=');
t_ok(strpos($ncr, "'n.job_id = ?'") !== false && strpos($ncr, "'n.report_doc_id = ?'") !== false,
    'the scope is applied as a WHERE clause on job_id / report_doc_id');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ncr_migrate')) ncr_migrate();
    // Two NCRs on job 900001, one on job 900002.
    $mk = function ($job, $report, $sev, $status) {
        db()->prepare("INSERT INTO nonconformities (ref, source, job_id, report_doc_id, severity, status, title, detected_on, created_at)
                       VALUES (?, 'JOB', ?, ?, ?, ?, 'Test finding', '2026-08-01', ?)")
            ->execute(['NCR-T-' . uniqid(), $job, $report, $sev, $status, date('c')]);
        return (int)db()->lastInsertId();
    };
    $a = $mk(900001, 0, 'MAJOR', 'OPEN');
    $b = $mk(900001, 555, 'MINOR', 'CLOSED');
    $c = $mk(900002, 0, 'MINOR', 'OPEN');

    // ncr_for_job returns only that job's NCRs (open + closed), open first.
    $j1 = ncr_for_job(900001);
    $ids1 = array_map(fn($r) => (int)$r['id'], $j1);
    t_ok(count($j1) === 2 && in_array($a, $ids1, true) && in_array($b, $ids1, true) && !in_array($c, $ids1, true),
        'ncr_for_job returns exactly that job\'s nonconformities (open and closed)');
    t_ok((int)$j1[0]['id'] === $a, 'open nonconformities sort before closed ones');

    // The register filter (ncr_all with a job scope) matches.
    $reg = ncr_all('all', 'n.job_id = ?', [900001]);
    t_ok(count($reg) === 2, 'the register scoped to a job returns only that job\'s NCRs');
    // Report scope.
    $rep = ncr_all('all', 'n.report_doc_id = ?', [555]);
    t_ok(count($rep) === 1 && (int)$rep[0]['id'] === $b, 'the register scoped to a report returns only that report\'s NCRs');

    // A clean job → empty (no fatal).
    t_ok(ncr_for_job(999999) === [], 'a job with no nonconformities returns an empty list');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The Job-360 Quality panel renders the job's NCRs and gates on reachability.
t_ok(strpos($jobv, 'ncr_reachable()') !== false && strpos($jobv, 'ncr_for_job((int)$job[\'id\'])') !== false,
    'the job screen shows a Quality panel gated on NCR reachability');
t_ok(strpos($jobv, 'Quality — nonconformities') !== false && strpos($jobv, '/ncr-item?id=') !== false,
    'the Quality panel lists the findings and links to each NCR');
t_ok(strpos($jobv, '/ncr?job=<?= (int)$job[\'id\'] ?>') !== false,
    'the panel links to the (now working) job-scoped register');

// Preserved: the lifecycle/closure gate and the NCR<->CAPA coupling are untouched.
t_ok(function_exists('ncr_close_missing') && strpos($ncr, "const NCR_STATUS") !== false,
    'the NCR lifecycle constants and closure gate are intact');
t_ok(!preg_match('/can\(\x27ncr\.quality/', $ncr), 'Module 12 introduces no new permission constant');
