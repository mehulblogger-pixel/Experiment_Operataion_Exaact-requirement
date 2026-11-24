<?php
// Stage 1 · B1 (owner, 24 Nov 2026 — "approve B0–B5, all as recommended"):
// ONE calculation per Operations figure (lib/opskpi.php), every card opens its
// register filtered by that same calculation, and the card's number equals the
// number of rows the filtered register shows.
t_section('Stage 1 B1 — one definition per Operations figure');

t_ok(function_exists('opk_call_defs') && function_exists('opk_job_defs'), 'the one-definition file (opskpi) is loaded');
$cd = opk_call_defs(); $jd = opk_job_defs();
foreach (['new', 'no_engineer', 'clarification', 'ready', 'wo_overdue', 'critical'] as $k)
    t_ok(isset($cd[$k]), "work-order figure '$k' is defined once");
foreach (['today', 'jobs_overdue', 'signoff', 'to_write'] as $k)
    t_ok(isset($jd[$k]), "job figure '$k' is defined once");
t_eq(opk_label('no_engineer'), 'No engineer yet', 'the card name is "No engineer yet"');
t_eq(opk_label('wo_overdue'), 'Work orders overdue', 'the card name is "Work orders overdue"');
t_eq(opk_label('jobs_overdue'), 'Jobs overdue', 'the card name is "Jobs overdue"');
t_eq(opk_label('signoff'), 'Reports awaiting sign-off', 'the card name is "Reports awaiting sign-off"');
t_eq(opk_label('to_write'), 'Reports to write', 'the card name is "Reports to write"');
t_eq(opk_href('no_engineer'), '/calls?f=no_engineer', 'the No-engineer card opens the filtered work-order register');
t_eq(opk_href('jobs_overdue'), '/jobs?f=jobs_overdue', 'the Jobs-overdue card opens the filtered jobs register');
t_eq(opk_href('nonsense'), '/operations', 'an unknown figure falls back to Operations (no broken link)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $past = date('Y-m-d', strtotime('-5 days')); $fut = date('Y-m-d', strtotime('+5 days')); $today = date('Y-m-d');
    db()->prepare("INSERT INTO offices (code, name) VALUES (?,?)")->execute(['S1B1', 'Stage1 B1 office']);
    $off = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspectors (name) VALUES (?)")->execute(['S1B1 Engineer']);
    $ins = (int)db()->lastInsertId();
    $call = function ($code, $status, $op = '', $req = '', $prio = '') use ($off) {
        db()->prepare("INSERT INTO calls (call_code, executing_office_id, ibo_office_id, status, op_status, inspection_required_date, priority, created_at) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$code, $off, $off, $status, $op, $req, $prio, 'x']);
        return (int)db()->lastInsertId();
    };
    $job = function ($code, $cid, array $f) use ($off) {
        $f += ['scheduled_date' => '', 'closed_flag' => 0, 'inspector_id' => null, 'stage' => '', 'assign_state' => '', 'accept_state' => '',
               'reporting_frequency' => 'NOREPORT', 'report_upload_date' => '', 'report_approval' => '', 'inspection_end_date' => ''];
        db()->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, scheduled_date, closed_flag, inspector_id, stage, assign_state, accept_state, reporting_frequency, report_upload_date, report_approval, inspection_end_date, created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$code, $cid, $off, $f['scheduled_date'], $f['closed_flag'], $f['inspector_id'], $f['stage'], $f['assign_state'], $f['accept_state'],
                       $f['reporting_frequency'], $f['report_upload_date'], $f['report_approval'], $f['inspection_end_date'], 'x']);
        return (int)db()->lastInsertId();
    };
    $A = $call('S1-A', 'OPEN', '', $past);                    // nobody on it, required date passed
    $B = $call('S1-B', 'OPEN', '', $past);  $job('S1-JB', $B, ['inspector_id' => $ins, 'scheduled_date' => $fut]);
    $C = $call('S1-C', 'OPEN');             $job('S1-JC', $C, ['inspector_id' => $ins, 'accept_state' => 'DECLINED']);
    $D = $call('S1-D', 'OPEN');             $job('S1-JD', $D, ['inspector_id' => $ins, 'assign_state' => 'RELEASED']);
    $E = $call('S1-E', 'OPEN', 'CANCELLED', $past);           // cancelled work order
    $F = $call('S1-F', 'OPEN');             $job('S1-JF', $F, ['inspector_id' => $ins, 'stage' => 'CANCELLED', 'scheduled_date' => $past]);
    $G = $call('S1-G', 'OPEN', '', $past);  $job('S1-JG', $G, ['inspector_id' => $ins, 'closed_flag' => 1, 'report_approval' => 'PENDING']);
    $H = $call('S1-H', 'OPEN', 'READY_TO_SCHEDULE', '', 'CRITICAL');
    $I = $call('S1-I', 'OPEN');
    $jOver = $job('S1-JI', $I, ['inspector_id' => $ins, 'scheduled_date' => $past, 'reporting_frequency' => 'DAILY']);
    $jToday = $job('S1-JT', $I, ['inspector_id' => $ins, 'scheduled_date' => $today, 'reporting_frequency' => 'DAILY', 'report_upload_date' => $today]);
    $mine = [$A, $B, $C, $D, $E, $F, $G, $H, $I];

    $ids = function ($key) use ($mine) {
        $in = implode(',', $mine);
        return array_map('intval', array_column(ops_all("SELECT c.id FROM calls c WHERE c.id IN ($in) AND " . opk_call_pred($key) . " ORDER BY c.id") ?: [], 'id'));
    };
    $jids = function ($key) use ($off) {
        return array_map('intval', array_column(ops_all("SELECT j.id FROM jobs j WHERE j.executing_office_id=? AND " . opk_job_pred($key) . " ORDER BY j.id", [$off]) ?: [], 'id'));
    };
    $ne = $ids('no_engineer');
    t_ok(in_array($A, $ne, true), 'No engineer yet: a work order with no job counts');
    t_ok(!in_array($B, $ne, true), 'No engineer yet: a work order with a person on a job does not count');
    t_ok(in_array($C, $ne, true), 'No engineer yet: a declined assignment goes back to the queue (B4)');
    t_ok(in_array($D, $ne, true), 'No engineer yet: a released hold goes back to the queue (B4)');
    t_ok(!in_array($E, $ne, true), 'No engineer yet: a cancelled work order is excluded (B2)');
    t_ok(in_array($F, $ne, true), 'No engineer yet: a work order whose only job was cancelled is waiting again');
    t_ok(!in_array($G, $ne, true), 'No engineer yet: a finished work order does not count');

    t_eq($ids('wo_overdue'), [$A, $B], 'Work orders overdue: live and unfinished with the required date passed (cancelled E and finished G excluded)');
    $new = $ids('new');
    t_ok(in_array($A, $new, true) && !in_array($B, $new, true), 'awaiting triage excludes a work order that already has a person on it (B2)');
    t_ok(!in_array($E, $new, true), 'awaiting triage excludes a cancelled work order (B2)');
    t_eq($ids('ready'), [$H], 'Ready to schedule reads the lifecycle status');
    t_eq($ids('critical'), [$H], 'Critical: flagged priority, still open');

    $jo = $jids('jobs_overdue');
    t_ok(in_array($jOver, $jo, true), 'Jobs overdue: an open job whose date passed counts');
    t_ok(!in_array($jToday, $jo, true), 'Jobs overdue: a job due today is not overdue');
    t_eq(count(array_intersect($jo, array_map('intval', array_column(ops_all("SELECT id FROM jobs WHERE job_code='S1-JF'") ?: [], 'id')))), 0, 'Jobs overdue: a cancelled job is never overdue (B2)');
    $tw = $jids('to_write');
    t_ok(in_array($jOver, $tw, true) && !in_array($jToday, $tw, true), 'Reports to write: an open job needing a report with none uploaded counts; one with an upload does not');
    $so = $jids('signoff');
    t_eq(count($so), 1, 'Reports awaiting sign-off: the one closed job whose sign-off is pending');
    t_eq($jids('today'), [$jToday], "Today's services: the job scheduled today");

    // Status reading (B2 groundwork): a legacy open order with a person reads ASSIGNED.
    $rowB = ops_one("SELECT c.*, " . opk_status_sql('c') . " opk_status FROM calls c WHERE c.id=?", [$B]);
    t_eq($rowB['opk_status'], 'ASSIGNED', 'a legacy open work order with a person on it reads Assigned, not Received');
    t_eq(tosrm_derive_status(ops_one("SELECT * FROM calls WHERE id=?", [$B])), 'ASSIGNED', 'tosrm_derive_status agrees with the SQL reading');
    t_eq(opk_progress_label(['status' => 'OPEN', 'op_status' => 'CANCELLED']), 'Cancelled', 'progress label: a cancelled work order reads Cancelled, not "To schedule"');

    // Card = rows: the figure counted for the home equals the rows the filtered register shows.
    // The suite has no front controller, so view() is the capturing stub other tests
    // use; the captured variables are then drawn through the real template.
    if (!function_exists('view')) { function view($tpl, $data = []) { $GLOBALS['__nv'] = $data; $GLOBALS['__nv_tpl'] = $tpl; } }
    $render = function ($route, $fn) {
        $GLOBALS['__nv'] = null; $GLOBALS['__nv_tpl'] = null;
        try { $fn($route, 'GET'); } catch (Throwable $e) { return 'THREW ' . $e->getMessage(); }
        $tpl = __DIR__ . '/../views/' . ($GLOBALS['__nv_tpl'] ?? '') . '.php';
        if (!is_array($GLOBALS['__nv']) || !is_file($tpl)) return '';
        ob_start();
        try { (function () use ($tpl) { extract($GLOBALS['__nv']); include $tpl; })(); } catch (Throwable $e) { echo 'THREW ' . $e->getMessage(); }
        return (string)ob_get_clean();
    };
    t_as_admin();
    foreach (['no_engineer', 'wo_overdue', 'ready', 'critical'] as $k) {
        $n = opk_count_calls($k);
        $_GET = ['f' => $k];
        $h = $render('calls', 'ops_calls');
        t_eq(count($GLOBALS['__nv']['rows'] ?? [-1]), $n, "card '" . opk_label($k) . "' ($n) = rows in its filtered register");
        t_ok(strpos($h, 'Showing: <b>' . e(opk_label($k))) !== false, "the filtered register says what it is showing ('" . opk_label($k) . "')");
    }
    foreach (['jobs_overdue', 'signoff', 'to_write', 'today'] as $k) {
        $n = opk_count_jobs($k);
        $_GET = ['f' => $k];
        $h = $render('jobs', 'ops_jobs');
        t_eq(count($GLOBALS['__nv']['rows'] ?? [-1]), $n, "card '" . opk_label($k) . "' ($n) = rows in its filtered register");
        t_ok(strpos($h, 'Showing: <b>' . e(opk_label($k))) !== false, "the filtered jobs register says what it is showing ('" . opk_label($k) . "')");
    }
    $_GET = ['f' => 'bogus'];
    $h = $render('calls', 'ops_calls');
    t_ok(strpos($h, 'Showing: <b>') === false, 'an unknown filter is ignored (the full register shows)');
    $_GET = [];
    t_as_nobody();

    // The home's "Waiting for a person" list is the same rule as the card.
    $pend = array_map(fn($r) => (int)$r['id'], tosrm_pending_scheduling([$off]));
    sort($pend);
    t_eq($pend, $ne, '"Waiting for a person" lists exactly the "No engineer yet" work orders');
    $m = tosrm_ops_metrics();
    t_eq((int)$m['unscheduled'], (int)$m['no_engineer'], 'the metrics array carries one number for No engineer yet');
    t_ok(array_key_exists('jobs_overdue', $m), 'the metrics array carries Jobs overdue');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
