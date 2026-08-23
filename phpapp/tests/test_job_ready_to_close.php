<?php
// A job is "ready to close" once its work is done — an issued (finalised) report on
// it, or a job that needs no report. This drives the inspector's "jobs to close"
// dashboard task and the My-Jobs close popup. A job with only a draft report is not
// yet ready.
t_section('a job is ready to close once its report is issued');

$pdo = db();
if (function_exists('idems_migrate')) { try { idems_migrate(); } catch (Throwable $e) {} }

$pdo->prepare("INSERT INTO jobs (job_code, reporting_frequency, created_at) VALUES ('RTC-1','FINAL',?)")->execute([date('c')]);
$jobId = (int)$pdo->lastInsertId();

// No report yet → not ready.
t_ok(job_has_issued_report($jobId) === false, 'a job with no report is not ready to close');

// A DRAFT report → still not ready.
$pdo->prepare("INSERT INTO report_docs (irn,type_code,status,job_id,finalized,data,created_at) VALUES ('RTC-IR','IR','DRAFT',?,0,'[]',?)")->execute([$jobId, date('c')]);
t_ok(job_has_issued_report($jobId) === false, 'a draft report does not make a job ready to close');

// An ISSUED (finalised) report → ready.
$pdo->prepare("UPDATE report_docs SET finalized=1, status='ISSUED' WHERE job_id=?")->execute([$jobId]);
t_ok(job_has_issued_report($jobId) === true, 'an issued report makes the job ready to close');

// A soft-deleted issued report does not count.
$pdo->prepare("INSERT INTO jobs (job_code, reporting_frequency, created_at) VALUES ('RTC-2','FINAL',?)")->execute([date('c')]);
$jobId2 = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO report_docs (irn,type_code,status,job_id,finalized,deleted,data,created_at) VALUES ('RTC-IR2','IR','ISSUED',?,1,1,'[]',?)")->execute([$jobId2, date('c')]);
t_ok(job_has_issued_report($jobId2) === false, 'a deleted report does not make a job ready to close');

// The My-Jobs "ready to close" popup and its trigger are wired into the view.
$src = file_get_contents(__DIR__ . '/../views/ops/my_jobs.php');
t_ok(strpos($src, "'toclose'") !== false, 'My Jobs has a "ready to close" filter');
t_ok(strpos($src, 'jobclose-open') !== false && strpos($src, 'jcxOverlay') !== false, 'the close-with-expenses popup is present');
t_ok(strpos($src, 'action=\'/job-close?id=') !== false || strpos($src, '/job-close?id=') !== false, 'the popup submits to the job-close handler');
