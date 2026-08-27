<?php
// Phase 3 §26 — the canonical persisted task. The read-time aggregators emit derived counts; this adds
// the one thing they cannot hold: a human-authored, assignable, due-dated item you tick off. It feeds a
// single "my tasks" count back into ops_pending_tasks and never replaces the aggregators. Self-contained.
t_section('Phase 3 §26 — canonical persisted task (create / assign / done / reopen)');

t_ok(function_exists('task_create') && function_exists('task_mine') && function_exists('task_done')
     && function_exists('task_open_count') && function_exists('ops_tasks'), 'the task helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/tasks.php'") !== false, 'the tasks lib is loaded by the front controller');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "task_open_count(\$myId)") !== false, 'the persisted-task count is fed into ops_pending_tasks');
t_ok(strpos($ops, "case \$route === 'tasks'") !== false, 'the /tasks route is dispatched');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$prevUid = $_SESSION['uid'] ?? null;
try {
    tasks_migrate();
    $pdo = db();
    // Two plain (non-coordinator) users — role INSPECTOR, since users.role defaults to ADMIN — and a
    // coordinator who is allowed to hand out work.
    $pdo->prepare("INSERT INTO users (username, role, is_active, home_office_id) VALUES ('t26_me','INSPECTOR',1,1)")->execute();
    $me = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (username, role, is_active, home_office_id) VALUES ('t26_other','INSPECTOR',1,1)")->execute();
    $other = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (username, role, is_active, home_office_id) VALUES ('t26_coord','COORDINATOR',1,1)")->execute();
    $coord = (int)$pdo->lastInsertId();

    // --- as a plain user: create for myself; cannot hand work to someone else ---
    $_SESSION['uid'] = $me; current_user(true); if (function_exists('ua')) ua(true);
    t_ok(task_open_count() === 0, 'a fresh user has no open tasks');
    $id = task_create('Chase the calibration cert', ['due_on' => '2026-09-01']);
    t_ok($id > 0, 'a task is created');
    t_eq(task_open_count(), 1, 'it counts as one open task for me');

    // A blank title creates nothing.
    t_ok(task_create('   ') === 0, 'a blank title creates no task');

    // A plain user trying to assign to someone else falls back to themselves (no silent hand-off).
    $id2 = task_create('Mine really', ['assigned_to' => $other]);
    $row = ops_one("SELECT assigned_to FROM user_tasks WHERE id=?", [$id2]);
    t_eq((int)$row['assigned_to'], $me, 'a non-coordinator cannot assign work to another user');

    // --- as a coordinator: handing work to another user in scope IS allowed ---
    $_SESSION['uid'] = $coord; current_user(true); if (function_exists('ua')) ua(true);
    $id3 = task_create('Please re-verify the gauge', ['assigned_to' => $other]);
    $row3 = ops_one("SELECT assigned_to FROM user_tasks WHERE id=?", [$id3]);
    t_eq((int)$row3['assigned_to'], $other, 'a coordinator can assign work to another user in scope');
    // back to me for the remaining assertions
    $_SESSION['uid'] = $me; current_user(true); if (function_exists('ua')) ua(true);

    // Done → drops out of the open list and count; reopen brings it back.
    t_ok(task_done($id), 'the task can be marked done');
    t_eq(task_open_count(), 1, 'a done task leaves the open count (one remains)');
    t_ok(task_reopen($id), 'the task can be reopened');
    t_eq(task_open_count(), 2, 'reopening restores it to the open count');

    // Another user cannot act on my task, and sees only what's assigned to them (the coordinator's task),
    // never my own tasks.
    $_SESSION['uid'] = $other; current_user(true); if (function_exists('ua')) ua(true);
    t_ok(task_done($id) === false, "another user cannot complete someone else's task");
    t_eq(task_open_count(), 1, 'the other user sees only their own assigned task, not mine');

    // --- entity linkage: a task attached to a job shows on that job ---
    $_SESSION['uid'] = $me; current_user(true); if (function_exists('ua')) ua(true);
    $jid = 4242;
    task_create('Re-shoot the weld photo', ['entity_kind' => 'JOB', 'entity_id' => $jid]);
    $forJob = task_for_entity('JOB', $jid);
    t_ok(count($forJob) === 1 && $forJob[0]['title'] === 'Re-shoot the weld photo', 'a task links to and lists under its record');
    t_eq(route_for_entity('JOB', $jid), '/job?id=' . $jid, 'the record link routes back to the job');
} finally {
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}

// wiring
$view = @file_get_contents(__DIR__ . '/../views/ops/tasks.php');
t_ok($view && strpos($view, 'My tasks') !== false, 'the /tasks screen renders');
