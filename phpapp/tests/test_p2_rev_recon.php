<?php
// Phase 2 §29 — recognised-revenue reconciliation (READ-ONLY) between the two money truths that
// coexist: a job's legacy invoice snapshot (jobs.invoice_amount) and the books ledger
// (invoices -> invoice_lines.job_id). It surfaces WHERE they disagree; it changes no displayed number
// and touches no row (converging the readers is §28, which is sign-off-gated). The legacy field's tax
// basis is ambiguous, so a job reconciles if its figure matches EITHER the net or the gross ledger
// total; it diverges only when it matches neither.
t_section('Phase 2 §29 — recognised-revenue reconciliation (read-only)');

t_ok(function_exists('revrecon_job') && function_exists('revrecon_scan') && function_exists('revrecon_count'),
     'the reconciliation helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/revrecon.php'") !== false, 'the revrecon lib is loaded by the front controller');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('books_migrate')) books_migrate();
    $pdo = db();
    $base = revrecon_count();

    // Job A: legacy matches the GROSS ledger total (18000 = 15000 net + 3000 tax) -> reconciles.
    $pdo->prepare("INSERT INTO jobs (job_code, invoice_amount, invoice_raised) VALUES ('J-A', 18000, 1)")->execute();
    $ja = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO invoices (invoice_no, status) VALUES ('IV-A','ISSUED')")->execute();
    $ia = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO invoice_lines (invoice_id, job_id, amount, line_total) VALUES (?,?, 15000, 18000)")->execute([$ia, $ja]);

    // Job B: legacy matches the NET ledger total (10000) -> reconciles.
    $pdo->prepare("INSERT INTO jobs (job_code, invoice_amount, invoice_raised) VALUES ('J-B', 10000, 1)")->execute();
    $jb = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO invoices (invoice_no, status) VALUES ('IV-B','ISSUED')")->execute();
    $ib = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO invoice_lines (invoice_id, job_id, amount, line_total) VALUES (?,?, 10000, 11800)")->execute([$ib, $jb]);

    // Job C: legacy 50000 but the ledger says 20000/23600 -> matches NEITHER -> diverges.
    $pdo->prepare("INSERT INTO jobs (job_code, invoice_amount, invoice_raised) VALUES ('J-C', 50000, 1)")->execute();
    $jc = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO invoices (invoice_no, status) VALUES ('IV-C','ISSUED')")->execute();
    $ic = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO invoice_lines (invoice_id, job_id, amount, line_total) VALUES (?,?, 20000, 23600)")->execute([$ic, $jc]);

    // Job D: legacy 9000 but its only invoice is CANCELLED -> ledger is 0 -> legacy_only, diverges.
    $pdo->prepare("INSERT INTO jobs (job_code, invoice_amount, invoice_raised) VALUES ('J-D', 9000, 1)")->execute();
    $jd = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO invoices (invoice_no, status) VALUES ('IV-D','CANCELLED')")->execute();
    $idd = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO invoice_lines (invoice_id, job_id, amount, line_total) VALUES (?,?, 9000, 10620)")->execute([$idd, $jd]);

    $rcA = revrecon_job($ja); $rcB = revrecon_job($jb); $rcC = revrecon_job($jc); $rcD = revrecon_job($jd);
    t_ok($rcA['diverges'] === false && $rcA['basis'] === 'gross', 'a job matching the gross ledger reconciles');
    t_ok($rcB['diverges'] === false && $rcB['basis'] === 'net', 'a job matching the net ledger reconciles');
    t_ok($rcC['diverges'] === true, 'a job matching neither basis is flagged');
    t_ok($rcD['diverges'] === true && $rcD['legacy_only'] === true, 'a legacy figure whose only invoice is cancelled is flagged (ledger is empty)');

    t_eq(revrecon_count(), $base + 2, 'exactly the two genuinely-divergent jobs are counted');
    $scanIds = array_map(fn($r) => $r['job_id'], revrecon_scan(50));
    t_ok(in_array($jc, $scanIds, true) && in_array($jd, $scanIds, true), 'the scan returns the divergent jobs');
    t_ok(!in_array($ja, $scanIds, true) && !in_array($jb, $scanIds, true), 'the reconciled jobs are not in the scan');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The health surface carries it as an advisory tile.
$src = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($src, "'rev_recon'") !== false, 'system-status shows a revenue-reconciliation tile');
