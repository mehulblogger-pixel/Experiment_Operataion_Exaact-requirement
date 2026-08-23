<?php
// Role-first panels: each section of a record screen names the work it belongs
// to, and the audience table says who does that work. The safety rules matter
// more than the hiding — a mistake here must cost somebody a tidier screen,
// never a panel they needed.
t_section('role-first panels (who a section is for)');

$pdo = db();
$mk = function ($role, $u) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username, first_name, role, is_active, is_superuser) VALUES (?,?,?,1,?)")
        ->execute([$u, $role, $role, $role === 'MASTER_ADMIN' ? 1 : 0]);
    return (int)$pdo->lastInsertId();
};
$as = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };

$mgr  = $mk('OPERATION_MANAGER', 'sa_mgr');
$fin  = $mk('FINANCE',           'sa_fin');
$mast = $mk('MASTER_ADMIN',      'sa_master');

// Finance came to raise an invoice. Scheduling and QA are not their work, and
// were the two tabs standing between them and the panel they came for.
$as($fin);
t_ok(in_array('finance', screen_buckets(), true), 'a FINANCE login lands in the finance bucket');
t_ok(!screen_shows('job.schedule'), 'finance is not shown who goes on site on which day');
t_ok(!screen_shows('job.qa'),       'finance is not shown QAPs and hold points');
t_ok(screen_shows('job.money'),     'finance is shown the invoice panel');
t_ok(screen_shows('job.expenses'),  'finance is shown what the job cost');

// Operations keeps everything: this is their screen.
$as($mgr);
t_ok(in_array('ops', screen_buckets(), true), 'an operations manager lands in the ops bucket');
foreach (['job.schedule', 'job.qa', 'job.expenses', 'job.money'] as $k)
    t_ok(screen_shows($k), "operations keeps $k");

// A Master Admin does every kind of work by definition of the role.
$as($mast);
t_eq(screen_buckets(), ['ops', 'inspector', 'finance'], 'a master admin is in every bucket');
foreach (['job.schedule', 'job.qa', 'job.expenses', 'job.money'] as $k)
    t_ok(screen_shows($k), "a master admin keeps $k");

// RULE 1a: a panel nobody registered is shown to everybody. Forgetting to add a
// key to the table must not silently delete a section from the screen.
$as($fin);
t_ok(screen_shows('job.something.new'), 'an unregistered panel key is shown, not hidden');

// RULE 1b: somebody who fits no bucket at all sees everything, and falls back
// to the field-level can() gates exactly as before. A sales role opening a job
// out of curiosity must not get a blank screen.
unset($_SESSION['uid']); current_user(true); ua(true);
t_eq(screen_buckets(), [], 'a viewer in no bucket has no buckets');
foreach (['job.schedule', 'job.qa', 'job.money'] as $k)
    t_ok(screen_shows($k), "a viewer in no bucket still sees $k");

// RULE 2: this hides clutter, never secrets. The audience table must not be
// the only thing between somebody and a figure — the money panel it hides from
// an inspector is still gated by the permission where it renders.
$src = file_get_contents(dirname(__DIR__) . '/views/ops/job_detail.php');
t_ok(strpos($src, "can('data.credit') || can('finance.reconcile')") !== false,
     'the invoice panel keeps its own permission gate underneath the audience gate');

unset($_SESSION['uid']); current_user(true); ua(true);
