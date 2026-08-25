<?php
// Module 21 — Hold / Witness points as first-class controls. The subsystem already
// exists and hard-blocks the Release Note (master override). This adds reusable
// summaries and LOUD warnings at the moments an inspection could be completed with a
// point still open (job close, day-by-day close) — advisory, no new hard block
// (decision A). The RN gate and the existing model are untouched.
t_section('Module 21 — hold/witness summaries + completion warnings');

$hwp   = file_get_contents(__DIR__ . '/../lib/hwpoints.php');
$close = file_get_contents(__DIR__ . '/../views/ops/job_close.php');
$jobv  = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
$idm   = file_get_contents(__DIR__ . '/../lib/idems.php');

t_ok(function_exists('hwp_job_summary') && function_exists('hwp_open_counts_for_jobs'),
    'the summary helpers exist');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('hwp_migrate')) hwp_migrate();
    $seq = 0;
    $ins = function ($job, $type, $status) use (&$seq) {
        $seq++;
        db()->prepare("INSERT INTO hw_points (job_id, point_type, status, source, dedupe_key, created_at) VALUES (?,?,?, 'MANUAL', ?, ?)")
            ->execute([$job, $type, $status, 'm21test-' . $job . '-' . $seq, date('c')]);
    };

    // Job 611001: two open (a hold + a witness) and one already cleared.
    $ins(611001, 'HOLD', 'OPEN');
    $ins(611001, 'WITNESS', 'OPEN');
    $ins(611001, 'HOLD', 'CLEARED');
    $s = hwp_job_summary(611001);
    t_ok($s['open'] === 2, 'the summary counts only OPEN points');
    t_ok(($s['by_type']['HOLD'] ?? 0) === 1 && ($s['by_type']['WITNESS'] ?? 0) === 1,
        'the summary breaks open points down by type');
    t_ok(stripos($s['label'], 'hold') !== false && stripos($s['label'], 'witness') !== false,
        'the summary renders a human label of the open points');

    // Job 611002: all cleared → nothing open.
    $ins(611002, 'HOLD', 'CLEARED');
    t_ok(hwp_job_summary(611002)['open'] === 0, 'a job whose points are all cleared shows nothing open');

    // Batched counts for a board: one query, only jobs with open points appear.
    $counts = hwp_open_counts_for_jobs([611001, 611002, 611003]);
    t_ok(($counts[611001] ?? 0) === 2 && !isset($counts[611002]) && !isset($counts[611003]),
        'the batched counter returns open counts keyed by job, omitting jobs with none');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// Warnings at the completion moments (advisory — no new hard block).
t_ok(strpos($close, 'hwp_job_summary((int)$job[\'id\'])') !== false
  && strpos($close, 'open hold / witness point') !== false,
    'the job-close screen warns loudly when open hold/witness points exist');
t_ok(strpos($jobv, 'hwp_open_count((int)$job[\'id\'])') !== false
  && strpos($jobv, 'should not proceed / despatch') !== false,
    'the day-by-day completion panel warns when open points exist');

// The ONE hard gate (Release Note) is preserved; no new hard block was added.
t_ok(strpos($idm, "hold / witness point' . (\$h === 1 ? '' : 's') . ' still open — close them first.") !== false,
    'the Release Note hard gate on open points is preserved');
t_ok(strpos($close, 'does not clear them') !== false,
    'the close warning is advisory (it explains closing does not clear the points)');

// No new permission.
t_ok(!preg_match('/can\(\x27(hwp|holdwitness)\./', $hwp), 'Module 21 introduces no new permission constant');
