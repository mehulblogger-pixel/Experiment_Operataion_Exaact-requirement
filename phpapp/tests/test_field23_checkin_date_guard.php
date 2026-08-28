<?php
// Field-finding #23 — Mark IN / Mark OUT must be blocked when today is not the job's scheduled date
// (no marking a job on the wrong day). Implemented as an admin toggle (checkin_date_guard, off by
// default); when on, site_checkin refuses ENTRY/EXIT unless today == the job's scheduled_date. A job
// with no scheduled date is not date-pinned, so it is allowed.
t_section('Field #23 — attendance only on the job\'s scheduled date');

$pdo = db();
$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// A job scheduled for TOMORROW, and a matching inspector to punch as.
$pdo->prepare("INSERT INTO inspectors (name, staff_kind, status) VALUES ('Punchy', 'ASSET', 'ACTIVE')")->execute();
$insId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, inspector_id, scheduled_date, created_at) VALUES ('JOB-F23', ?, ?, ?)")->execute([$insId, $tomorrow, date('c')]);
$jobFuture = (int)$pdo->lastInsertId();
// A job scheduled for TODAY.
$pdo->prepare("INSERT INTO jobs (job_code, inspector_id, scheduled_date, created_at) VALUES ('JOB-F23T', ?, ?, ?)")->execute([$insId, $today, date('c')]);
$jobToday = (int)$pdo->lastInsertId();
// A job with NO scheduled date.
$pdo->prepare("INSERT INTO jobs (job_code, inspector_id, scheduled_date, created_at) VALUES ('JOB-F23N', ?, '', ?)")->execute([$insId, date('c')]);
$jobNone = (int)$pdo->lastInsertId();

$mark = fn($jid) => site_checkin($jid, ['kind' => 'ENTRY', 'gps' => ''], null, $insId);

// The guard is OFF by default — behaviour unchanged (the wrong-date job is NOT blocked on date;
// it proceeds to the normal GPS check).
setting_set('checkin_date_guard', '0');
t_ok(stripos($mark($jobFuture), 'scheduled for') === false, 'with the guard OFF, a wrong-date job is not blocked on date');

// Turn the guard ON.
setting_set('checkin_date_guard', '1');
t_ok(checkin_date_guard() === true, 'the date guard turns on via its setting');

// A job scheduled for another day is REFUSED, and the message names the scheduled date.
$msg = $mark($jobFuture);
t_ok(stripos($msg, 'scheduled') !== false && stripos($msg, 'only be marked on the scheduled date') !== false,
     'Mark IN is refused when today is not the scheduled date');

// A job scheduled for TODAY passes the date guard (it then reaches the normal GPS check, not the date block).
$msgToday = $mark($jobToday);
t_ok(stripos($msgToday, 'scheduled for') === false, 'a job scheduled for today is allowed past the date guard');

// A job with NO scheduled date is not date-pinned → allowed past the date guard.
$msgNone = $mark($jobNone);
t_ok(stripos($msgNone, 'scheduled for') === false, 'a job with no scheduled date is not blocked');

// The toggle lives on the check-in settings, and is saved by the handler.
$src = file_get_contents(__DIR__ . '/../lib/trust.php');
t_ok(strpos($src, "setting_set('checkin_date_guard'") !== false, 'the check-in settings save the date guard');
$view = file_get_contents(__DIR__ . '/../views/ops/evidence_review.php');
t_ok(strpos($view, 'name="date_guard"') !== false && stripos($view, 'Only on the scheduled date') !== false,
     'the settings screen offers the "only on the scheduled date" toggle');

// Restore + clean up (shared DB).
setting_set('checkin_date_guard', '0');
$pdo->prepare("DELETE FROM site_visits WHERE job_id IN (?,?,?)")->execute([$jobFuture, $jobToday, $jobNone]);
$pdo->prepare("DELETE FROM jobs WHERE id IN (?,?,?)")->execute([$jobFuture, $jobToday, $jobNone]);
$pdo->prepare("DELETE FROM inspectors WHERE id=?")->execute([$insId]);
