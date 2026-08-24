<?php
// R2 — closing a job records days & expenses and LOCKS it (a financial effect).
// It must be gated on the permission designed for it — ops.job.close — not merely
// on holding the jobs module. The assigned engineer may close their OWN job
// (owner bypass); everyone else needs ops.job.close or master. Roles that only
// VIEW jobs (finance, SBU head, branch-app manager, asst. manager) may not close.
t_section('job-close is gated on ops.job.close (not just the jobs module)');

// The handler carries the guard, before the POST branch that performs the close.
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
$guardPos  = strpos($ops, "ops_require(is_master() || can('ops.job.close') || job_owned_by_me((int)\$job['id'])");
t_ok($guardPos !== false, 'job-close calls ops_require on ops.job.close / owner / master');
$routePos  = strpos($ops, "if (\$route === 'job-close') {");
$postPos   = strpos($ops, "if (\$method === 'POST') {", $routePos);
t_ok($routePos !== false && $guardPos > $routePos && $guardPos < $postPos,
    'the guard runs at the top of the route, before the POST close is handled');

$pdo = db();

// An inspector linked to a login, with their own job and someone else's job.
$pdo->prepare("INSERT INTO inspectors (name) VALUES ('JCG Meera')")->execute();
$insId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, inspector_id, is_active) VALUES ('jcg_insp','Meera','INSPECTOR',?,1)")->execute([$insId]);
$inspUid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, inspector_id, created_at) VALUES ('JCG-1', ?, ?)")->execute([$insId, date('c')]);
$myJob = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, inspector_id, created_at) VALUES ('JCG-2', ?, ?)")->execute([$insId + 4321, date('c')]);
$notMine = (int)$pdo->lastInsertId();

$mk = function($u, $role, $super = 0) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active, is_superuser) VALUES (?,?,?,1,?)")
        ->execute([$u, ucfirst(strtolower($role)), $role, $super]);
    return (int)$pdo->lastInsertId();
};
$coord = $mk('jcg_coord', 'COORDINATOR');
$bm    = $mk('jcg_bm',    'BRANCH_MANAGER');
$om    = $mk('jcg_om',    'OPERATION_MANAGER');
$fin   = $mk('jcg_fin',   'FINANCE');
$sbu   = $mk('jcg_sbu',   'SBU_HEAD');
$asst  = $mk('jcg_asst',  'ASST_MANAGER');
$bam   = $mk('jcg_bam',   'BRANCH_APP_MANAGER');
$mast  = $mk('jcg_master','MASTER_ADMIN', 1);

// The exact predicate the handler enforces.
$canClose = fn($jobId) => is_master() || can('ops.job.close') || job_owned_by_me($jobId);

// Roles that hold ops.job.close (or master) may close any job.
foreach ([[$coord,'a coordinator'],[$bm,'a branch manager'],[$om,'an operation manager'],[$mast,'a master admin']] as [$uid,$label]) {
    $_SESSION['uid'] = $uid; current_user(true); ua(true);
    t_ok($canClose($myJob) === true, "$label can close a job");
}

// Roles that only VIEW jobs cannot close — the permission is no longer inert.
foreach ([[$fin,'finance'],[$sbu,'an SBU head'],[$asst,'an asst. manager'],[$bam,'a branch-app manager']] as [$uid,$label]) {
    $_SESSION['uid'] = $uid; current_user(true); ua(true);
    t_ok(can('ops.job.close') === false, "$label does not hold ops.job.close");
    t_ok($canClose($myJob) === false, "$label cannot close a job they do not own");
}

// The assigned engineer may close their OWN job, but not another engineer's.
$_SESSION['uid'] = $inspUid; current_user(true); ua(true);
t_ok(can('ops.job.close') === false, 'the inspector holds no ops.job.close permission');
t_ok($canClose($myJob) === true,  'the assigned inspector may close their own job (owner bypass)');
t_ok($canClose($notMine) === false, 'the inspector may not close another engineer’s job');

unset($_SESSION['uid']); current_user(true); ua(true);
