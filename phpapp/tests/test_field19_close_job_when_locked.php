<?php
// Field-finding #19 — once a job's report is submitted, approved and locked (issued), the job
// screen must plainly offer "Close the job". The job detail's "now band" now has a dedicated
// step for an approved/issued (locked) report that leads with the Close action, instead of the
// generic "issue it, then close" shown while a report is still a draft.
t_section('Field #19 — offer "Close the job" once the report is approved/issued (locked)');

$pdo = db();
$pdo->prepare("INSERT INTO jobs (job_code, created_at) VALUES ('J-F19', ?)")->execute([date('c')]);
$jobDraft = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, created_at) VALUES ('J-F19L', ?)")->execute([date('c')]);
$jobLocked = (int)$pdo->lastInsertId();

// A DRAFT report on the first job; an ISSUED (finalized) report on the second.
$pdo->prepare("INSERT INTO report_docs (irn, job_id, status, finalized, deleted, created_at) VALUES ('IRN-D', ?, 'DRAFT', 0, 0, ?)")->execute([$jobDraft, date('c')]);
$pdo->prepare("INSERT INTO report_docs (irn, job_id, status, finalized, deleted, created_at) VALUES ('IRN-L', ?, 'ISSUED', 1, 0, ?)")->execute([$jobLocked, date('c')]);

// The "locked report" detector the now-band uses.
$lockedQ = fn($jid) => (int)ops_val("SELECT COUNT(*) FROM report_docs WHERE job_id=? AND deleted=0 AND (finalized=1 OR status IN ('APPROVED','ISSUED'))", [$jid]);
t_eq(0, $lockedQ($jobDraft),  'a job with only a DRAFT report is not yet "report locked"');
t_ok($lockedQ($jobLocked) >= 1, 'a job with an ISSUED/finalized report is "report locked"');

// APPROVED (approved but not yet issued) also counts as locked-and-closeable.
$pdo->prepare("INSERT INTO jobs (job_code, created_at) VALUES ('J-F19A', ?)")->execute([date('c')]);
$jobApproved = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO report_docs (irn, job_id, status, finalized, deleted, created_at) VALUES ('IRN-A', ?, 'APPROVED', 0, 0, ?)")->execute([$jobApproved, date('c')]);
t_ok($lockedQ($jobApproved) >= 1, 'an APPROVED report also counts (submitted, approved and locked)');

// The now-band offers "Close the job" in a dedicated locked-report branch.
$jd = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
t_ok(strpos($jd, '$jLockedReport = ') !== false
     && strpos($jd, "status IN ('APPROVED','ISSUED')") !== false,
     'the now-band detects an approved/issued (locked) report');
$branch = strpos($jd, 'elseif ($jLockedReport > 0):');
t_ok($branch !== false, 'there is a dedicated now-band step for a locked report');
$blk = substr($jd, $branch, 700);
t_ok(strpos($blk, 'The report is approved and issued') !== false
     && strpos($blk, '/job-close?id=') !== false,
     'the locked-report step leads with "Close the job"');

// Clean up (shared DB).
$pdo->prepare("DELETE FROM report_docs WHERE job_id IN (?,?,?)")->execute([$jobDraft, $jobLocked, $jobApproved]);
$pdo->prepare("DELETE FROM jobs WHERE id IN (?,?,?)")->execute([$jobDraft, $jobLocked, $jobApproved]);
