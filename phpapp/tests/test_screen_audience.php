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

// ---- Reading the money on a job, versus recording it ------------------------
t_section('who may read the invoice panel, and who may record on it');

$coord = $mk('COORDINATOR', 'sa_coord');
$insp2 = $mk('INSPECTOR',   'sa_insp');

// The complaint: the Operation Manager could not see which of their jobs were
// invoiced, because the panel was gated on the inter-office credit permission
// they do not hold. They hold the money-figure permissions, which is the thing
// that should have decided it.
$as($mgr);
t_ok(!can('data.credit'), 'an operation manager does not hold the inter-office credit permission');
t_ok(can('data.revenue'), 'an operation manager does hold data.revenue');
t_ok(job_invoice_can_view(),    'an operation manager can now read the invoice panel');
t_ok(!job_invoice_can_record(), 'an operation manager cannot record invoice or payment');

// The other half of the same bug: the coordinator could edit what the manager
// could not even see. They keep that, because they hold data.credit — this
// change takes nothing away from anybody.
$as($coord);
t_ok(job_invoice_can_view() && job_invoice_can_record(),
     'a coordinator keeps both reading and recording — nothing is taken away');

$as($fin);
t_ok(job_invoice_can_view() && job_invoice_can_record(), 'finance reads and records');

$as($mast);
t_ok(job_invoice_can_view() && job_invoice_can_record(), 'a master admin reads and records');

// An inspector is trusted with neither, exactly as before.
$as($insp2);
t_ok(!job_invoice_can_view(),   'an inspector still cannot read the invoice panel');
t_ok(!job_invoice_can_record(), 'an inspector still cannot record against it');

// ---- Finance checking whether the reports are issued ------------------------
t_section('finance may check that reports are issued, and nothing more');

// The compact report check is for somebody in the Money tab who does NOT get
// the full report panel. Printing both to the same person would be the exact
// duplication this pass exists to remove.
$reportCheckShows = fn() => screen_shows('job.money') && !screen_shows('job.qa');

$as($fin);
t_ok($reportCheckShows(), 'finance is shown the compact "reports issued?" check');
t_ok(!screen_shows('job.qa'), 'finance is still not shown QAPs, hold points or the write-it buttons');

$as($mgr);
t_ok(!$reportCheckShows(), 'operations is not shown the compact check as well as the full panel');
$as($coord);
t_ok(!$reportCheckShows(), 'a coordinator is not shown it twice either');
$as($mast);
t_ok(!$reportCheckShows(), 'a master admin is not shown it twice either');

// The order of report and invoice is NOT fixed: where the order requires
// payment in advance the invoice is raised before the work is done, so "no
// report yet" is the normal state of a correct advance invoice. The check must
// read as information and never as a gate.
$view = file_get_contents(dirname(__DIR__) . '/views/ops/job_detail.php');
t_ok(strpos($view, 'For information. The invoice is not held back by this') !== false,
     'the check states it is information, not a condition on invoicing');
t_ok(strpos($view, 'the invoice comes first by design') !== false,
     'a job on an advance says so, rather than reading as a missing report');

unset($_SESSION['uid']); current_user(true); ua(true);
