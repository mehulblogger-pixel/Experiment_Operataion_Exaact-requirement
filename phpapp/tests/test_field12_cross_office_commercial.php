<?php
// Field-finding #12 — commercial visibility on a CROSS-OFFICE job. Owner's rule: the EXECUTING office sees
// only its inter-office credit + an Invoiced/Not-invoiced capsule (no amount); every other commercial figure
// (client billing, invoice value, revenue, profit) is the CONTRACTING office's. Same-office jobs are FULL.
t_section('Field #12 — cross-office job: executing office sees credit + invoiced capsule only');

t_ok(function_exists('job_commercial_view'), 'the commercial-view helper exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$prevUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();
    $ahm = (int)(ops_val("SELECT id FROM offices WHERE is_ahmedabad=1 LIMIT 1") ?: 0);
    if (!$ahm) { $pdo->prepare("INSERT INTO offices (name,is_ahmedabad,is_active) VALUES ('AHM',1,1)")->execute(); $ahm = (int)$pdo->lastInsertId(); }
    $pdo->prepare("INSERT INTO offices (name,is_active) VALUES ('MUM',1)")->execute();
    $mum = (int)$pdo->lastInsertId();

    // Cross-office job: executed in Ahmedabad, contracted by Mumbai, credit 15000, client invoice 60000.
    $pdo->prepare("INSERT INTO calls (call_code, ibo_office_id, executing_office_id) VALUES ('C12',?,?)")->execute([$mum, $ahm]);
    $cid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, contracting_office_id, expected_credit, invoice_amount, invoice_raised) VALUES ('J12',?,?,?,15000,60000,0)")->execute([$cid, $ahm, $mum]);
    $job = ops_one("SELECT * FROM jobs WHERE job_code='J12'");
    // Same-office job in Ahmedabad.
    $pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, contracting_office_id) VALUES ('J12S',?,?)")->execute([$ahm, $ahm]);
    $jobSame = ops_one("SELECT * FROM jobs WHERE job_code='J12S'");

    $asUser = function ($pdo, $home, $super = 0) {
        $pdo->prepare("INSERT INTO users (username, role, home_office_id, is_superuser, is_active) VALUES (?,?,?,?,1)")->execute(['u' . uniqid(), 'BRANCH_MANAGER', $home, $super]);
        $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); if (function_exists('ua')) ua(true);
    };

    // Contracting office (Mumbai) → FULL.
    $asUser($pdo, $mum);
    t_eq(job_commercial_view($job), 'FULL', 'the contracting office sees FULL commercial');

    // Executing office (Ahmedabad) → CREDIT_ONLY, and the credit is its inter-office credit.
    $asUser($pdo, $ahm);
    t_eq(job_commercial_view($job), 'CREDIT_ONLY', 'the executing office sees CREDIT_ONLY on a cross-office job');
    t_eq((int) job_money($job)['credit'], 15000, 'the credit the executing office sees is its inter-office credit');

    // Same-office job → FULL even for that office (no split).
    t_eq(job_commercial_view($jobSame), 'FULL', 'a same-office job is FULL for its office');

    // Master → FULL regardless.
    $asUser($pdo, $ahm, 1);
    t_eq(job_commercial_view($job), 'FULL', 'a master sees FULL');
} finally {
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The view gates the commercial panels on FULL and shows the credit capsule otherwise.
$jd = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
t_ok(strpos($jd, "job_commercial_view(\$job)") !== false, 'the job screen computes the commercial view');
t_ok(substr_count($jd, "\$commView === 'FULL'") >= 3, 'the bills, profitability and invoice panels are all gated on FULL');
t_ok(strpos($jd, 'Your inter-office credit') !== false, 'the executing office gets the credit capsule');
t_ok(strpos($jd, 'Not invoiced') !== false && strpos($jd, "'Invoiced'") !== false, 'the capsule shows an Invoiced / Not-invoiced status (no amount)');
