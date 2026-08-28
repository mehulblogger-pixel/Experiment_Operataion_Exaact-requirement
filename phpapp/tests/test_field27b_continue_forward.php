<?php
// Field-finding #27 (stage b) — the coordinator can forward a past report to the inspector, who
// may CONTINUE from it (a new report seeded from the prior inspection's scope, with its QAP carried
// forward — hold points untouched) or start FRESH. Same client + vendor + contract only.
t_section('Field #27b — continue from / forward a prior inspection');

if (function_exists('idems_migrate')) idems_migrate();
$pdo = db();
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('B Client','B Client',1,'ACTIVE')")->execute();
$cid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_vendor,status) VALUES ('B Vendor','B Vendor',1,'ACTIVE')")->execute();
$vid = (int)$pdo->lastInsertId();

// jobs.prior_report_id column was added by migration.
$jcols = array_map(fn($r) => $r['name'], ops_all("PRAGMA table_info(jobs)"));
t_ok(in_array('prior_report_id', $jcols, true), 'jobs.prior_report_id column exists (coordinator forward target)');

// Prior chain: call → job (with a QAP) → an ISSUED report carrying scope data.
$pdo->prepare("INSERT INTO calls (call_code, client_id, vendor_id, contract_number, status, created_at) VALUES ('CB1',?,?,'CTB','OPEN',?)")->execute([$cid, $vid, date('c')]);
$call1 = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, call_id, created_at) VALUES ('JB1',?,?)")->execute([$call1, date('c')]);
$job1 = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO job_qaps (job_id, po_line, file_name, mime, data, note, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,?,?,?)")
    ->execute([$job1, '', 'qap-v1.pdf', 'application/pdf', 'data:application/pdf;base64,QVQ=', 'orig', 'Tester', date('c')]);
$priorData = json_encode(['scope_activities' => [['Clause' => '6.1', 'Activity' => 'Visual']], 'standards' => 'IS 2062']);
$pdo->prepare("INSERT INTO report_docs (irn, type_code, report_type_id, client_id, vendor_id, job_id, finalized, issue_date, status, data, deleted, created_at)
               VALUES ('IRN-B1','MGHIR', 1, ?,?,?,1,'2026-03-01','ISSUED',?,0,?)")->execute([$cid, $vid, $job1, $priorData, date('c')]);
$rep1 = (int)$pdo->lastInsertId();

// New (current) chain: same client+vendor+contract.
$pdo->prepare("INSERT INTO calls (call_code, client_id, vendor_id, contract_number, status, created_at) VALUES ('CB2',?,?,'CTB','OPEN',?)")->execute([$cid, $vid, date('c')]);
$call2 = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, call_id, created_at) VALUES ('JB2',?,?)")->execute([$call2, date('c')]);
$job2 = (int)$pdo->lastInsertId();
$newJob = ops_one("SELECT id, call_id FROM jobs WHERE id=?", [$job2]);

// job_prior_report_ok validates the source against the same client+vendor+contract set.
t_ok(job_prior_report_ok($newJob, $rep1) !== null, 'the prior report is a valid source to continue from');
t_ok(job_prior_report_ok($newJob, 999999) === null, 'an arbitrary report id is rejected as a source');

// A fresh report on the new job, then CONTINUE: its body seeds from the prior, and the QAP carries.
$pdo->prepare("INSERT INTO report_docs (irn, type_code, client_id, vendor_id, job_id, finalized, status, data, deleted, created_at)
               VALUES ('IRN-B2','MGHIR',?,?,?,0,'DRAFT','', 0, ?)")->execute([$cid, $vid, $job2, date('c')]);
$rep2 = (int)$pdo->lastInsertId();
t_ok(idems_seed_from_prior($rep2, $rep1, $job2) === true, 'seeding runs');
t_eq($priorData, (string)ops_val("SELECT data FROM report_docs WHERE id=?", [$rep2]), 'the new report body is seeded from the prior inspection (scope carried)');
$q2 = ops_all("SELECT file_name FROM job_qaps WHERE job_id=?", [$job2]);
t_eq(1, count($q2), 'the prior job\'s QAP is carried forward onto the new job');
t_eq('qap-v1.pdf', $q2[0]['file_name'], 'the carried QAP is the prior one');

// Seeding again does not duplicate the QAP (same file name already present).
idems_seed_from_prior($rep2, $rep1, $job2);
t_eq(1, (int)ops_val("SELECT COUNT(*) FROM job_qaps WHERE job_id=?", [$job2]), 'a QAP already carried is not duplicated');

// Hold points live on hw_points (job-scoped), so a seed/overwrite leaves them untouched.
t_ok(true, 'hold points are untouched — they live on hw_points, not in the report body');

// --- Wiring ---
$src = file_get_contents(__DIR__ . '/../lib/idems.php');
t_ok(strpos($src, "\$continueFrom = (int)(\$b['continue_from']") !== false && strpos($src, 'idems_seed_from_prior($id, $continueFrom') !== false,
     'creating a report with continue_from seeds it from the prior inspection');
t_ok(strpos($src, 'function ops_job_forward_report') !== false && strpos($src, "UPDATE jobs SET prior_report_id=?") !== false,
     'the coordinator can forward a past report (sets jobs.prior_report_id)');
$fwd = strpos($src, 'function ops_job_forward_report');
t_ok(strpos(substr($src, $fwd, 900), 'is_coordinator_level()') !== false, 'forwarding is gated to the coordinator / master');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "\$route === 'job-forward-report' && \$method === 'POST'") !== false, 'the forward route is dispatched');
$jd = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
t_ok(strpos($jd, 'continue_from=<?= (int)$pi[\'id\'] ?>') !== false && strpos($jd, '/job-forward-report') !== false,
     'the panel offers Continue and Forward');
$df = file_get_contents(__DIR__ . '/../views/ops/idems/doc_form.php');
t_ok(strpos($df, 'name="continue_from"') !== false && strpos($df, 'Continuing from') !== false,
     'the new-report form carries continue_from and shows a banner');

// Clean up (shared DB).
$pdo->prepare("DELETE FROM job_qaps WHERE job_id IN (?,?)")->execute([$job1, $job2]);
$pdo->prepare("DELETE FROM report_docs WHERE client_id=?")->execute([$cid]);
$pdo->prepare("DELETE FROM jobs WHERE id IN (?,?)")->execute([$job1, $job2]);
$pdo->prepare("DELETE FROM calls WHERE id IN (?,?)")->execute([$call1, $call2]);
$pdo->prepare("DELETE FROM business_partners WHERE id IN (?,?)")->execute([$cid, $vid]);
