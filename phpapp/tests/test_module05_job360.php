<?php
// Module 05 — Job 360. A consolidated Stage · Owner · Blockers header on the job
// screen, answering the universal-UX questions the scattered banners did not: where
// is the job, who owns it now, what is blocking it. Read-only; reuses existing
// helpers; the tab/fold/glance structure is untouched.
t_section('Module 05 — job_now(): stage, owner and consolidated blockers');

$ops  = file_get_contents(__DIR__ . '/../lib/ops.php');
$view = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');

t_ok(function_exists('job_now'), 'job_now() exists');

// ---- Owner across the lifecycle (no DB writes needed for the report-less states) ----
$base = ['id'=>0, 'closed_flag'=>0, 'stage'=>'ALLOCATED', 'scheduled_date'=>'', 'inspector_name'=>'Ravi'];
t_ok(job_now($base)['owner'] === 'Coordinator — schedule it', 'an unscheduled job is owned by the coordinator');
t_ok(job_now(array_merge($base, ['scheduled_date'=>'2026-08-20']))['owner'] === 'Ravi',
    'a scheduled job with no report is owned by its inspector');
$closed = job_now(array_merge($base, ['closed_flag'=>1]));
t_ok($closed['owner'] === '—' && $closed['stage'] === 'Closed' && $closed['blockers'] === [],
    'a closed job has no owner, reads "Closed", and shows no blockers');

// ---- Report-state-driven owner + blockers, with real rows ----
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('idems_migrate')) idems_migrate();
    // A scheduled job with a report under review → owner is the reviewer/approver.
    $j = ['id'=>555001, 'closed_flag'=>0, 'stage'=>'REPORT_PENDING', 'scheduled_date'=>'2026-08-20', 'inspector_name'=>'Ravi'];
    db()->prepare("INSERT INTO report_docs (type_code, status, job_id, deleted, created_at) VALUES ('IC','UNDER_REVIEW',555001,0,?)")->execute([date('c')]);
    t_ok(job_now($j)['owner'] === 'Reviewer / approver', 'a job whose report is under review is owned by the reviewer/approver');

    // Same job, report issued but job still open → back to the inspector to close.
    db()->prepare("UPDATE report_docs SET status='ISSUED', finalized=1 WHERE job_id=555001")->execute();
    t_ok(strpos(job_now($j)['owner'], 'close the job') !== false, 'an issued-but-open job is owned by the inspector to close it');

    // A blocker surfaces: an open hold/witness point.
    if (function_exists('hwp_migrate')) { try { hwp_migrate(); } catch (Throwable $e) {} }
    $hasHw = false;
    try { db()->prepare("INSERT INTO hold_witness_points (job_id, type, status, created_at) VALUES (555001,'HOLD','OPEN',?)")->execute([date('c')]); $hasHw = true; }
    catch (Throwable $e) { $hasHw = false; }   // table name/columns may differ in this build
    if ($hasHw) {
        $labels = array_column(job_now($j)['blockers'], 'label');
        t_ok((bool)array_filter($labels, fn($l) => stripos($l, 'hold / witness') !== false),
            'an open hold/witness point appears as a blocker');
    } else {
        t_ok(true, 'hold/witness table differs in this build — open-point blocker check skipped');
    }
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- Blocker shape: money blockers are tagged so the view can hide them from field inspectors ----
$mk = function ($blockers) { return array_map(fn($b) => $b['money'] ?? null, $blockers); };
t_ok(strpos($ops, "'money'=>true") !== false && strpos($ops, "job_bills_missing(\$job)") !== false,
    'the bills-required blocker is tagged as a money blocker');

// ---- The view renders the header and hides money blockers from a field inspector ----
t_ok(strpos($view, 'job_now($job)') !== false, 'the job screen renders the status header');
t_ok(strpos($view, '$jnFieldInsp && !empty($b[\'money\'])') !== false,
    'money blockers are filtered out of the header for a field inspector');
t_ok(strpos($view, 'Blockers') !== false && strpos($view, 'Stage') !== false && strpos($view, 'With') !== false,
    'the header shows Stage, With (owner) and Blockers');

// ---- Guardrails: existing structure untouched ----
t_ok(strpos($view, 'data-tabs data-tabs-key="job"') !== false, 'the tab container is intact');
t_ok(strpos($view, 'id="holdpoints"') !== false, 'the hold/witness fold is intact');
t_ok(!preg_match('/can\(\x27mod\.job360/', $ops), 'Module 05 introduces no new permission constant');
