<?php
// Phase 2 §32 — inter-office settlement, a READ-ONLY roll-up of what the profit engine already computes
// per job. On a cross-office job the executing office is owed a credit (job_money()'s expected_credit)
// by the contracting office, and each job carries a credit_received flag. This aggregates that into a
// per-office-pair matrix (owed / settled / outstanding) from the SAME fields and the SAME cross rule —
// no new number, no row changed.
t_section('Phase 2 §32 — inter-office settlement matrix (read-only)');

t_ok(function_exists('settlement_matrix') && function_exists('settlement_outstanding_total') && function_exists('settlement_render'),
     'the settlement helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/settlement.php'") !== false, 'the settlement lib is loaded by the front controller');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$savedUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();
    // A master session so scope allows every office's jobs.
    $pdo->prepare("INSERT INTO users (username, first_name, is_active, is_superuser, role) VALUES ('settlemaster','S',1,1,'MASTER_ADMIN')")->execute();
    $uid = (int)$pdo->lastInsertId();
    $_SESSION['uid'] = $uid; current_user(true); ua(true);

    // Offices.
    $pdo->prepare("INSERT INTO offices (name) VALUES ('Ahmedabad')")->execute(); $A = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO offices (name) VALUES ('Mumbai')")->execute();    $B = (int)$pdo->lastInsertId();

    $base = settlement_outstanding_total();
    $baseRows = count(settlement_matrix());

    // Cross-office closed jobs: A holds the contract, B does the work, B is owed the credit.
    //  - job 1: credit 5000, settled.
    //  - job 2: credit 3000, unsettled.
    $pdo->prepare("INSERT INTO jobs (job_code, closed_flag, contracting_office_id, executing_office_id, expected_credit, credit_received) VALUES ('JS-1',1,?,?,5000,1)")->execute([$A,$B]);
    $pdo->prepare("INSERT INTO jobs (job_code, closed_flag, contracting_office_id, executing_office_id, expected_credit, credit_received) VALUES ('JS-2',1,?,?,3000,0)")->execute([$A,$B]);
    // A same-office job (A->A) with a leftover credit — must be ignored.
    $pdo->prepare("INSERT INTO jobs (job_code, closed_flag, contracting_office_id, executing_office_id, expected_credit, credit_received) VALUES ('JS-3',1,?,?,9999,0)")->execute([$A,$A]);
    // An OPEN cross-office job — not closed, must be ignored.
    $pdo->prepare("INSERT INTO jobs (job_code, closed_flag, contracting_office_id, executing_office_id, expected_credit, credit_received) VALUES ('JS-4',0,?,?,7000,0)")->execute([$A,$B]);
    // The reverse direction: B holds, A works, credit 2000 unsettled — a distinct pair.
    $pdo->prepare("INSERT INTO jobs (job_code, closed_flag, contracting_office_id, executing_office_id, expected_credit, credit_received) VALUES ('JS-5',1,?,?,2000,0)")->execute([$B,$A]);

    $m = settlement_matrix();
    // Locate the A->B pair.
    $ab = null; $ba = null;
    foreach ($m as $p) { if ($p['from_office_id'] === $A && $p['to_office_id'] === $B) $ab = $p; if ($p['from_office_id'] === $B && $p['to_office_id'] === $A) $ba = $p; }
    t_ok($ab !== null, 'the A->B office pair appears');
    t_eq((int)$ab['owed'], 8000, 'the pair total credit is the sum of both cross-office jobs');
    t_eq((int)$ab['settled'], 5000, 'the settled figure is the credit-received job');
    t_eq((int)$ab['outstanding'], 3000, 'the outstanding figure is the unsettled job');
    t_eq((int)$ab['jobs'], 2, 'both cross-office jobs count toward the pair');
    t_eq((int)$ab['jobs_open'], 1, 'one job is still open (unsettled)');
    t_ok($ba !== null && (int)$ba['outstanding'] === 2000, 'the reverse direction is a distinct pair with its own outstanding');

    // The same-office and open jobs never appear.
    $anyBadOwed = false; foreach ($m as $p) if ((int)$p['owed'] === 9999 || (int)$p['owed'] === 7000) $anyBadOwed = true;
    t_ok(!$anyBadOwed, 'the same-office leftover and the open job are excluded');

    t_eq((int)(settlement_outstanding_total() - $base), 5000, 'the outstanding total sums both pairs (3000 + 2000)');

    ob_start(); settlement_render(); $html = ob_get_clean();
    t_ok(strpos($html, 'Inter-office settlement') !== false, 'the settlement panel renders');
} finally {
    if ($savedUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $savedUid;
    current_user(true); ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}

$v = file_get_contents(__DIR__ . '/../views/ops/invoicing.php');
t_ok(strpos($v, 'settlement_render') !== false, 'the invoicing screen shows the settlement panel');
