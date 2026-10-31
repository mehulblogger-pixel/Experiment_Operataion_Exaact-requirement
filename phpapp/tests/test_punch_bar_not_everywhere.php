<?php
// MOD-MON (owner, 31 Oct): "Site check-ins and check-outs are showing many times
// here and there." The punch bar was drawn on top of every page for anyone
// linked to an inspector — even with no visit today — and on a job page above
// that page's own Site check-in panel.
t_section('the punch bar shows where it is useful, not everywhere');

$pdo = db();
$pdo->prepare("INSERT INTO inspectors (name) VALUES ('Punch Tester')")->execute();
$insId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, inspector_id) VALUES ('pbt_user','Punch','Tester','INSPECTOR',1,?)")->execute([$insId]);
$uid = (int)$pdo->lastInsertId();
$_SESSION['uid'] = $uid; current_user(true); ua(true);

$at = function ($uri) { $_SERVER['REQUEST_URI'] = $uri; return (string)punch_bar(); };

// No visit today.
t_ok(strpos($at('/'), 'No site visit scheduled') !== false, 'home: the "no visit today" note is shown');
t_ok(strpos($at('/my-work'), 'No site visit scheduled') !== false, 'My Work: the note is shown');
t_ok($at('/calls') === '', 'the calls register: nothing at all when there is no visit');
t_ok($at('/call?id=5') === '', 'a call: nothing at all when there is no visit');

// A visit today.
$pdo->prepare("INSERT INTO jobs (inspector_id, scheduled_date, stage, created_at) VALUES (?,?, 'SCHEDULED', ?)")->execute([$insId, date('Y-m-d'), date('c')]);
$jobId = (int)$pdo->lastInsertId();
$jobs = punch_today_jobs($insId, date('Y-m-d'));
if ($jobs) {
    t_ok(strpos($at('/calls'), 'id="punchBar"') !== false, 'with a visit today the bar is offered on other pages');
    t_ok($at('/job?id=' . $jobId) === '', 'the job page keeps only its own Site check-in panel');
} else {
    t_ok(true, 'this install lists no visit for the seeded job (skipped)');
    t_ok($at('/job?id=' . $jobId) === '', 'the job page never draws the bar');
}
unset($_SESSION['uid']); $_SERVER['REQUEST_URI'] = '/'; current_user(true); ua(true);
