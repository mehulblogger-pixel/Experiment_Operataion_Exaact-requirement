<?php
// R9 — a call/job can be edited by two people at once; with last-write-wins the
// second save silently overwrites the first. The edit form now carries the row's
// version token as a baseline, and stale_edit_block() refuses a save whose baseline
// no longer matches the row (someone else saved in between). touch_row_version()
// stamps a fresh token on every save.
t_section('optimistic locking on call / job edit');

$pdo = db();
$pdo->prepare("INSERT INTO calls (call_code, status, created_at) VALUES ('OL-1','OPEN',?)")->execute([date('c')]);
$cid = (int)$pdo->lastInsertId();

// No baseline yet (older form, or never saved) → nothing to clash with → allowed.
t_ok(stale_edit_block('calls', $cid, '') === '', 'an empty baseline never blocks (backward compatible)');

// Stamp a version, as a save would.
touch_row_version('calls', $cid);
$v1 = (string) ops_val("SELECT updated_at FROM calls WHERE id=?", [$cid]);
t_ok($v1 !== '', 'a save stamps a version token');

// Editing against the current version is fine.
t_ok(stale_edit_block('calls', $cid, $v1) === '', 'saving against the current version is allowed');

// Someone else saves — the token moves.
touch_row_version('calls', $cid);
$v2 = (string) ops_val("SELECT updated_at FROM calls WHERE id=?", [$cid]);
t_ok($v2 !== $v1, 'a second save produces a different token');

// Now our stale baseline (v1) is refused.
$msg = stale_edit_block('calls', $cid, $v1);
t_ok($msg !== '' && stripos($msg, 'someone else') !== false, 'a stale baseline is refused with an explanation');
t_ok(stripos($msg, 'was not saved') !== false, 'the message reassures nothing was overwritten');

// Same behaviour for jobs.
$pdo->prepare("INSERT INTO jobs (job_code, created_at) VALUES ('OL-J1',?)")->execute([date('c')]);
$jid = (int)$pdo->lastInsertId();
touch_row_version('jobs', $jid);
$jv = (string) ops_val("SELECT updated_at FROM jobs WHERE id=?", [$jid]);
t_ok(stale_edit_block('jobs', $jid, $jv) === '', 'a job edit against the current version is allowed');
touch_row_version('jobs', $jid);
t_ok(stale_edit_block('jobs', $jid, $jv) !== '', 'a job edit against a stale version is refused');

// The table name is allow-listed — an unknown table is a no-op, never interpolated.
t_ok(stale_edit_block('users', 1, 'anything') === '', 'only calls/jobs are lockable (no arbitrary table names)');

// The handlers and forms are wired.
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "stale_edit_block('calls', \$call['id'], \$b['row_version'] ?? '')") !== false, 'call-edit checks the baseline');
t_ok(strpos($ops, "stale_edit_block('jobs', \$job['id'], \$b['row_version'] ?? '')") !== false, 'job-edit checks the baseline');
t_ok(strpos($ops, "touch_row_version('calls'") !== false && strpos($ops, "touch_row_version('jobs'") !== false, 'both saves stamp a new version');
t_ok(strpos(file_get_contents(__DIR__ . '/../views/ops/call_form.php'), 'name="row_version"') !== false, 'the call form carries the baseline');
t_ok(strpos(file_get_contents(__DIR__ . '/../views/ops/job_form.php'), 'name="row_version"') !== false, 'the job form carries the baseline');
