<?php
// An assigned inspector owns their own job: they must be able to open it, upload
// its supporting documents/bills and CLOSE it (recording the day's expenses) even
// though they do not hold the jobs module. This guards the ownership check and the
// module-gate bypass that unblocks "Upload & close" from My Jobs.
t_section('an inspector can reach and close their own job');

$pdo = db();

// An inspector, linked to an INSPECTOR login (no jobs-module access).
$pdo->prepare("INSERT INTO inspectors (name) VALUES ('IJA Ravi')")->execute();
$insId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, inspector_id, is_active) VALUES ('ija_ravi','Ravi','INSPECTOR',?,1)")->execute([$insId]);
$uid = (int)$pdo->lastInsertId();

// Their own job, and someone else's job.
$pdo->prepare("INSERT INTO jobs (job_code, inspector_id, created_at) VALUES ('IJA-1', ?, ?)")->execute([$insId, date('c')]);
$myJob = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, inspector_id, created_at) VALUES ('IJA-2', ?, ?)")->execute([$insId + 999, date('c')]);
$notMine = (int)$pdo->lastInsertId();

$_SESSION['uid'] = $uid; current_user(true); ua(true);

// The inspector genuinely lacks the jobs module (the reason the gate blocked them).
t_ok(!can('mod.jobs.view'), 'the inspector does not hold the jobs module');

// Ownership is recognised for their own job only.
t_ok(job_owned_by_me($myJob) === true, 'the inspector owns their assigned job');
t_ok(job_owned_by_me($notMine) === false, 'the inspector does not own another engineer’s job');
t_ok(job_owned_by_me(0) === false, 'a missing job id is not owned');

// The module gate lets the owner through the job / close / upload routes without
// the jobs module (it returns instead of redirecting). We assert it does not block.
$reached = false;
$_GET['id'] = $myJob;
foreach (['job', 'job-close', 'job-qap-upload'] as $r) { ops_module_gate($r); }
$reached = true;   // if any had blocked, ops_require(false) would have exited before here
t_ok($reached, 'the gate lets the owning inspector open, upload to, and close their job');
unset($_GET['id']);

unset($_SESSION['uid']); current_user(true); ua(true);
