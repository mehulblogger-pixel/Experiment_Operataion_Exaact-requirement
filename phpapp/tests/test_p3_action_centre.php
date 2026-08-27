<?php
// Phase 3 §19 — the Action Centre. My Work shows the derived "waiting on me" buckets as count cards;
// action_centre() unifies them with my individual §26 tasks into ONE priority-ordered "do this next"
// list — the thing count cards cannot do (an overdue written-down task must out-rank a routine approval).
// It reuses ops_pending_tasks() and task_mine() and computes no new counts. Self-contained.
t_section('Phase 3 §19 — Action Centre (unified, prioritised do-next list)');

t_ok(function_exists('action_centre'), 'the action_centre aggregator exists');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "'actions'") !== false && strpos($ops, 'action_centre(10)') !== false, 'My Work passes the prioritised actions to the view');
$view = file_get_contents(__DIR__ . '/../views/ops/my_work.php');
t_ok(strpos($view, 'Next actions') !== false, 'My Work renders the Next actions band');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$prevUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    tasks_migrate();
    $pdo = db();
    // A plain inspector so no management approval buckets muddy the list.
    $pdo->prepare("INSERT INTO users (username, role, is_active, home_office_id) VALUES ('t19','INSPECTOR',1,1)")->execute();
    $me = (int)$pdo->lastInsertId();
    $_SESSION['uid'] = $me; current_user(true); if (function_exists('ua')) ua(true);

    // No tasks yet → an inspector with nothing waiting has an empty action list.
    t_ok(action_centre() === [], 'nothing waiting → an empty action list');

    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $today     = date('Y-m-d');
    $nextWeek  = date('Y-m-d', strtotime('+7 day'));
    task_create('OVERDUE — chase the cert', ['due_on' => $yesterday]);
    task_create('LATER — tidy the file',     ['due_on' => $nextWeek]);
    task_create('TODAY — call the client',   ['due_on' => $today]);
    task_create('UNDATED — someday');

    $a = action_centre();
    t_eq(count($a), 4, 'all four of my tasks are in the action list');
    // Priority order: overdue, then today, then dated-later, then undated.
    t_ok(strpos($a[0]['title'], 'OVERDUE') === 0, 'the overdue task is first');
    t_ok($a[0]['overdue'] === true && $a[0]['tone'] === 'bad', 'the overdue task is flagged');
    t_ok(strpos($a[1]['title'], 'TODAY') === 0, 'the due-today task is second');
    t_ok(strpos($a[3]['title'], 'UNDATED') === 0, 'the undated task is last');

    // A task linked to a record routes to that record, not to /tasks.
    task_create('Re-shoot weld', ['entity_kind' => 'JOB', 'entity_id' => 77, 'due_on' => $yesterday]);
    $a2 = action_centre();
    $linked = null; foreach ($a2 as $x) if ($x['title'] === 'Re-shoot weld') $linked = $x;
    t_ok($linked && $linked['href'] === '/job?id=77', 'a record-linked task routes to its record');

    // The limit is honoured.
    t_ok(count(action_centre(2)) === 2, 'the limit caps the list');
} finally {
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}
