<?php
// Phase 3 §26 — the canonical persisted task.
//
// The read-time aggregators (ops_pending_tasks, attention_summary) already answer "what is waiting on
// me / on the business" by DERIVING counts from the modules — quotes to approve, reports to vet, AR
// overdue. What they cannot hold is a human-authored, assignable, due-dated item you tick off: "call
// the client about the revised scope", "chase the calibration cert". This adds exactly that one thing.
//
// It does NOT replace the aggregators. They keep deriving their counts; this stores the individual
// mutable items and feeds ONE derived count ("my tasks") back into ops_pending_tasks, so the My Work
// badge stays unified. Non-destructive and additive: a new table, no change to any existing one.

function tasks_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = pk_clause();
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_tasks (
        id $pk,
        title VARCHAR(255) DEFAULT '', detail TEXT,
        created_by INT NULL, created_by_name VARCHAR(150) DEFAULT '',
        assigned_to INT NULL, assigned_to_name VARCHAR(150) DEFAULT '',
        due_on VARCHAR(20) DEFAULT '',
        entity_kind VARCHAR(30) DEFAULT '', entity_id INT NULL,
        office_id INT NULL, sbu VARCHAR(20) DEFAULT '',
        status VARCHAR(20) DEFAULT 'OPEN',
        done_at VARCHAR(30) DEFAULT '', done_by INT NULL,
        created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
}

// Who a task may be assigned to. Assigning to yourself is always allowed; assigning to someone else is
// a coordinator-level act (you are handing them work). Returns [id => name] a coordinator may pick from,
// scoped to their branch(es) — mirrors the list scope, so no cross-office reach.
function task_assignees() {
    tasks_migrate();
    if (!(function_exists('is_coordinator_level') && is_coordinator_level())) return [];
    try {
        [$w, $a] = function_exists('scope_clause') ? scope_clause('home_office_id', null) : ['1', []];
        $rows = ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 AND ($w) ORDER BY first_name, username", $a) ?: [];
    } catch (Throwable $e) { $rows = ops_all("SELECT id, first_name, last_name, username FROM users WHERE is_active=1 ORDER BY first_name, username") ?: []; }
    $out = [];
    foreach ($rows as $r) {
        $nm = trim(trim((string)($r['first_name'] ?? '') . ' ' . (string)($r['last_name'] ?? ''))) ?: (string)($r['username'] ?? '');
        $out[(int)$r['id']] = $nm;
    }
    return $out;
}

// Create a task. Assignable to self always; to someone else only at coordinator level (otherwise it
// falls back to yourself). Optionally links to a record (entity_kind/entity_id). Returns the new id, or 0.
function task_create($title, array $o = []) {
    tasks_migrate();
    $u = function_exists('current_user') ? current_user() : null;
    if (!$u) return 0;
    $title = trim((string)$title);
    if ($title === '') return 0;
    $myId   = (int)($u['id'] ?? 0);
    $myName = function_exists('user_name') ? trim((string)user_name($u)) : trim((string)($u['username'] ?? ''));

    $assignTo = (int)($o['assigned_to'] ?? 0) ?: $myId;
    if ($assignTo !== $myId && !(function_exists('is_coordinator_level') && is_coordinator_level())) $assignTo = $myId;
    $assignName = $assignTo === $myId ? $myName : (task_assignees()[$assignTo] ?? '');
    if ($assignTo !== $myId && $assignName === '') { $assignTo = $myId; $assignName = $myName; }  // outside scope → keep it

    $office = $o['office_id'] ?? ($u['home_office_id'] ?? null);
    $now = date('c');
    tasks_migrate();
    db()->prepare("INSERT INTO user_tasks
        (title, detail, created_by, created_by_name, assigned_to, assigned_to_name, due_on,
         entity_kind, entity_id, office_id, sbu, status, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?, 'OPEN', ?, ?)")
        ->execute([
            $title, trim((string)($o['detail'] ?? '')), $myId, $myName, $assignTo, $assignName,
            trim((string)($o['due_on'] ?? '')), trim((string)($o['entity_kind'] ?? '')),
            ($o['entity_id'] ?? null) ? (int)$o['entity_id'] : null,
            $office ? (int)$office : null, trim((string)($o['sbu'] ?? '')), $now, $now]);
    $id = (int)db()->lastInsertId();
    if (!empty($o['entity_kind']) && !empty($o['entity_id']) && function_exists('act_log'))
        try { act_log($o['entity_kind'], (int)$o['entity_id'], 'TASK_ADDED', 'Task: ' . $title); } catch (Throwable $e) {}
    return $id;
}

// Open tasks assigned to a user (default: me), soonest due first (undated last).
function task_mine($uid = 0) {
    tasks_migrate();
    $uid = (int)$uid ?: (int)(current_user()['id'] ?? 0);
    if (!$uid) return [];
    try {
        return ops_all("SELECT * FROM user_tasks WHERE assigned_to=? AND status='OPEN'
                        ORDER BY (CASE WHEN COALESCE(due_on,'')='' THEN 1 ELSE 0 END), due_on, id", [$uid]) ?: [];
    } catch (Throwable $e) { return []; }
}
function task_open_count($uid = 0) {
    tasks_migrate();
    $uid = (int)$uid ?: (int)(current_user()['id'] ?? 0);
    if (!$uid) return 0;
    try { return (int) ops_val("SELECT COUNT(*) FROM user_tasks WHERE assigned_to=? AND status='OPEN'", [$uid]); }
    catch (Throwable $e) { return 0; }
}

// A viewer may act on a task if it is theirs (assignee or author) or they are coordinator-level.
function task_may_act($task) {
    $u = current_user(); if (!$u) return false;
    $me = (int)($u['id'] ?? 0);
    return $me && ((int)($task['assigned_to'] ?? 0) === $me || (int)($task['created_by'] ?? 0) === $me
        || (function_exists('is_coordinator_level') && is_coordinator_level()));
}

function task_done($id) {
    tasks_migrate();
    $t = ops_one("SELECT * FROM user_tasks WHERE id=?", [(int)$id]); if (!$t || !task_may_act($t)) return false;
    db()->prepare("UPDATE user_tasks SET status='DONE', done_at=?, done_by=?, updated_at=? WHERE id=?")
        ->execute([date('c'), (int)(current_user()['id'] ?? 0), date('c'), (int)$id]);
    return true;
}
function task_reopen($id) {
    tasks_migrate();
    $t = ops_one("SELECT * FROM user_tasks WHERE id=?", [(int)$id]); if (!$t || !task_may_act($t)) return false;
    db()->prepare("UPDATE user_tasks SET status='OPEN', done_at='', done_by=NULL, updated_at=? WHERE id=?")
        ->execute([date('c'), (int)$id]);
    return true;
}

// Tasks attached to a record — for a "Tasks" panel on a job/report/etc. The host page is already
// access-controlled, so this just reads by the link.
function task_for_entity($kind, $id, $includeDone = false) {
    tasks_migrate();
    $kind = trim((string)$kind); $id = (int)$id;
    if ($kind === '' || !$id) return [];
    try {
        $w = $includeDone ? '' : " AND status='OPEN'";
        return ops_all("SELECT * FROM user_tasks WHERE entity_kind=? AND entity_id=?$w
                        ORDER BY status, (CASE WHEN COALESCE(due_on,'')='' THEN 1 ELSE 0 END), due_on, id", [$kind, $id]) ?: [];
    } catch (Throwable $e) { return []; }
}

// A small read-only "Tasks" panel for a record page (job, report, …), with an inline add form.
function task_render_for_entity($kind, $id, $heading = 'Tasks') {
    if (!function_exists('task_for_entity')) return;
    $rows = task_for_entity($kind, $id, true);
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $csrf = function_exists('csrf_token') ? csrf_token() : '';
    echo '<div class="panel"><h3 class="tab-sub" style="margin-top:0">' . $e($heading)
       . ' <span class="muted" style="font-weight:400;font-size:12px">— your follow-ups on this record</span></h3>';
    if ($rows) {
        echo '<div style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px">';
        foreach ($rows as $t) {
            $done = strtoupper((string)$t['status']) === 'DONE';
            echo '<div style="display:flex;align-items:center;gap:9px;font-size:13.5px' . ($done ? ';opacity:.55' : '') . '">'
               . '<span class="pill ' . ($done ? 'p-ok' : 'p-warn') . '" style="font-size:10px">' . ($done ? '✓' : '•') . '</span>'
               . '<span' . ($done ? ' style="text-decoration:line-through"' : '') . '>' . $e($t['title'])
               . ($t['due_on'] ? ' <span class="muted">· due ' . $e($t['due_on']) . '</span>' : '')
               . ' <span class="muted">· ' . $e($t['assigned_to_name'] ?: '—') . '</span></span></div>';
        }
        echo '</div>';
    }
    echo '<form method="post" action="/tasks" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">'
       . '<input type="hidden" name="_csrf" value="' . $e($csrf) . '">'
       . '<input type="hidden" name="do" value="add">'
       . '<input type="hidden" name="entity_kind" value="' . $e($kind) . '">'
       . '<input type="hidden" name="entity_id" value="' . (int)$id . '">'
       . '<input type="hidden" name="back" value="1">'
       . '<input name="title" placeholder="Add a follow-up…" required style="flex:1;min-width:180px">'
       . '<input type="date" name="due_on" title="Due date">'
       . '<button class="btn small" type="submit">Add</button></form></div>';
}

// The /tasks screen — my open tasks + a create form; POST also handles add / done / reopen.
function ops_tasks($method) {
    ops_require(function_exists('current_user') && current_user(), 'Please sign in.');
    tasks_migrate();
    if ($method === 'POST') {
        $do = $_POST['do'] ?? 'add';
        $backToRecord = !empty($_POST['back']) && !empty($_POST['entity_kind']) && !empty($_POST['entity_id']);
        if ($do === 'add') {
            $id = task_create($_POST['title'] ?? '', [
                'detail' => $_POST['detail'] ?? '', 'due_on' => $_POST['due_on'] ?? '',
                'assigned_to' => (int)($_POST['assigned_to'] ?? 0),
                'entity_kind' => $_POST['entity_kind'] ?? '', 'entity_id' => (int)($_POST['entity_id'] ?? 0)]);
            flash($id ? 'Task added.' : 'A task needs a title.', $id ? 'success' : 'error');
        } elseif ($do === 'done')   { task_done((int)($_POST['id'] ?? 0));   flash('Task marked done.'); }
        elseif ($do === 'reopen')   { task_reopen((int)($_POST['id'] ?? 0)); flash('Task reopened.'); }
        if ($backToRecord) redirect(route_for_entity($_POST['entity_kind'], (int)$_POST['entity_id']));
        redirect('/tasks');
    }
    view('ops/tasks', [
        'open'      => task_mine(),
        'assignees' => task_assignees(),
        'recentDone'=> (function () { try { return ops_all("SELECT * FROM user_tasks WHERE assigned_to=? AND status='DONE' ORDER BY done_at DESC LIMIT 8", [(int)(current_user()['id'] ?? 0)]) ?: []; } catch (Throwable $e) { return []; } })(),
    ]);
}

// Best-effort route back to a linked record, so a task added from a job returns to that job.
function route_for_entity($kind, $id) {
    $map = ['JOB'=>'/job?id=', 'REPORT'=>'/document?id=', 'CALL'=>'/call?id=', 'NCR'=>'/ncr-item?id=',
            'CAPA'=>'/capa-item?id=', 'COMPLAINT'=>'/complaint?id=', 'CANDIDATE'=>'/candidate?id='];
    $k = strtoupper((string)$kind);
    return isset($map[$k]) ? $map[$k] . (int)$id : '/tasks';
}
