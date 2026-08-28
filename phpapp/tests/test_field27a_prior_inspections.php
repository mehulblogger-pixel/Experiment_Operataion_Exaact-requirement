<?php
// Field-finding #27 (stage a) — for a repeat inspection, show the last inspection at the SAME
// client + vendor + contract, so the next inspector (and the coordinator) can open the past
// report. Only ISSUED reports count; the current job is excluded; a different vendor or a
// different contract does not match.
t_section('Field #27a — prior inspections at the same client + vendor + contract');

$pdo = db();
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('Repeat Client','Repeat Client',1,'ACTIVE')")->execute();
$cid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_vendor,status) VALUES ('Repeat Vendor','Repeat Vendor',1,'ACTIVE')")->execute();
$vid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_vendor,status) VALUES ('Other Vendor','Other Vendor',1,'ACTIVE')")->execute();
$vid2 = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO inspectors (name, staff_kind, status) VALUES ('Prior Inspector','ASSET','ACTIVE')")->execute();
$insId = (int)$pdo->lastInsertId();

$mkChain = function ($contract, $vendor, $finalized, $issue) use ($pdo, $cid, $insId) {
    $pdo->prepare("INSERT INTO calls (call_code, client_id, vendor_id, contract_number, status, created_at) VALUES (?,?,?,?, 'OPEN', ?)")
        ->execute(['CALL-' . uniqid(), $cid, $vendor, $contract, date('c')]);
    $callId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (job_code, call_id, inspector_id, created_at) VALUES (?,?,?,?)")
        ->execute(['JOB-' . uniqid(), $callId, $insId, date('c')]);
    $jobId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_docs (irn, type_code, client_id, vendor_id, job_id, inspector_id, finalized, issue_date, status, deleted, created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,0,?)")
        ->execute(['IRN-' . uniqid(), 'MGHIR', $cid, $vendor, $jobId, $insId, $finalized, $issue, $finalized ? 'ISSUED' : 'DRAFT', date('c')]);
    return [$callId, $jobId, (int)$pdo->lastInsertId()];
};

// Two prior ISSUED inspections under contract CT-1 at the repeat vendor (different dates).
[, , $rep1] = $mkChain('CT-1', $vid, 1, '2026-01-10');
[, , $rep2] = $mkChain('CT-1', $vid, 1, '2026-05-20');
// Noise: a DRAFT (not issued), a different vendor, a different contract.
$mkChain('CT-1', $vid, 0, '2026-06-01');           // draft — must not show
$mkChain('CT-1', $vid2, 1, '2026-04-01');          // other vendor — must not show
$mkChain('CT-2', $vid, 1, '2026-04-15');           // other contract — must not show

// The NEW (current) repeat job under the same client+vendor+contract.
[, $newJobId, ] = $mkChain('CT-1', $vid, 0, '');
$newJob = ops_one("SELECT id, call_id FROM jobs WHERE id=?", [$newJobId]);

$hist = job_prior_inspections($newJob);
$ids = array_map(fn($r) => (int)$r['id'], $hist);
t_eq(count($hist), 2, 'only the two issued reports at the same client+vendor+contract are returned');
t_ok(in_array($rep1, $ids, true) && in_array($rep2, $ids, true), 'both matching issued reports are listed');
t_eq((int)$hist[0]['id'], $rep2, 'newest inspection is first (ordered by date)');

// The current job's own report (if any) is excluded, and drafts/other vendor/other contract never appear.
t_ok(count($ids) === 2, 'a draft, a different vendor and a different contract are all excluded');

// A job with no vendor on its call yields no history (nothing to match).
$pdo->prepare("INSERT INTO calls (call_code, client_id, vendor_id, contract_number, status, created_at) VALUES ('CALL-NOVEN',?,NULL,'CT-1','OPEN',?)")->execute([$cid, date('c')]);
$ncall = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, call_id, created_at) VALUES ('JOB-NOVEN',?,?)")->execute([$ncall, date('c')]);
$nvJob = ops_one("SELECT id, call_id FROM jobs WHERE id=?", [(int)$pdo->lastInsertId()]);
t_eq([], job_prior_inspections($nvJob), 'a job with no vendor has no vendor-history');

// The panel is wired into the job detail (read-only, gated to idems viewers).
$jd = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
t_ok(strpos($jd, 'job_prior_inspections($job)') !== false && strpos($jd, 'Previous inspections at this vendor') !== false,
     'the job detail shows the prior-inspection panel');
t_ok(strpos($jd, "can('mod.idems.view') || can('mod.idems.edit')") !== false, 'the panel is gated to report viewers');

// Clean up (shared DB).
$pdo->prepare("DELETE FROM report_docs WHERE client_id=?")->execute([$cid]);
$pdo->prepare("DELETE FROM jobs WHERE call_id IN (SELECT id FROM calls WHERE client_id=?)")->execute([$cid]);
$pdo->prepare("DELETE FROM calls WHERE client_id=?")->execute([$cid]);
$pdo->prepare("DELETE FROM inspectors WHERE id=?")->execute([$insId]);
$pdo->prepare("DELETE FROM business_partners WHERE id IN (?,?,?)")->execute([$cid, $vid, $vid2]);
