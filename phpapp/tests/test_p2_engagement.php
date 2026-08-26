<?php
// Phase 2 §25 — the ENGAGEMENT as a read-only grouping over the existing contract_number. The whole
// sales→ops→finance spine already threads one string (contract_number) through quotations, calls,
// jobs and invoices (reports hang off the jobs). engagement() is the one resolver that returns that
// full spine with rollup counts. Read-only: no new table, no new status, nothing written.
t_section('Phase 2 §25 — engagement grouping over contract_number');

t_ok(function_exists('engagement') && function_exists('engagement_render'), 'the engagement resolver + render helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/engagement.php'") !== false, 'the engagement lib is loaded by the front controller');

// An empty / blank contract number yields an empty engagement, never an error.
$blank = engagement('');
t_ok($blank['members'] === [] && $blank['rollup']['jobs'] === 0, 'a blank contract number gives an empty engagement');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('crm_migrate')) crm_migrate();
    if (function_exists('idems_migrate')) idems_migrate();
    if (function_exists('books_migrate')) books_migrate();
    if (function_exists('contracts_migrate')) contracts_migrate();
    $pdo = db();
    $NO = 'CN-ENG-1';

    $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, open_status) VALUES (7001,?, 'OPEN')")->execute([$NO]);
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, contract_number, status) VALUES ('Q-1',0,?, 'ACCEPTED')")->execute([$NO]);
    $pdo->prepare("INSERT INTO calls (call_code, contract_number, status, op_status) VALUES ('CALL-1',?, 'OPEN','RECEIVED')")->execute([$NO]);
    $pdo->prepare("INSERT INTO calls (call_code, contract_number, status, op_status) VALUES ('CALL-2',?, 'CLOSED','CLOSED')")->execute([$NO]);
    $pdo->prepare("INSERT INTO jobs (job_code, contract_number, closed_flag) VALUES ('JOB-1',?, 0)")->execute([$NO]);
    $jobId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (job_code, contract_number, closed_flag) VALUES ('JOB-2',?, 1)")->execute([$NO]);
    $pdo->prepare("INSERT INTO report_docs (job_id, irn, type_code, status, deleted) VALUES (?, 'IRN-1','FIR','ISSUED',0)")->execute([$jobId]);
    $pdo->prepare("INSERT INTO invoices (invoice_no, contract_number, status, total) VALUES ('INV-1',?, 'ISSUED', 15000)")->execute([$NO]);
    $pdo->prepare("INSERT INTO invoices (invoice_no, contract_number, status, total) VALUES ('INV-2',?, 'CANCELLED', 9999)")->execute([$NO]);
    // A different contract — must never leak into this engagement.
    $pdo->prepare("INSERT INTO jobs (job_code, contract_number, closed_flag) VALUES ('JOB-X','CN-OTHER', 0)")->execute();

    $eng = engagement($NO);
    $r = $eng['rollup'];
    t_eq($r['quotes'], 1, 'the quote is grouped in');
    t_eq($r['calls'], 2, 'both calls are grouped in');
    t_eq($r['open_calls'], 1, 'exactly the one open call is counted (the CLOSED one is not)');
    t_eq($r['jobs'], 2, 'both jobs are grouped in');
    t_eq($r['open_jobs'], 1, 'exactly the one open job is counted');
    t_eq($r['reports'], 1, 'the report hanging off the job is found via job_id');
    t_eq($r['invoices'], 2, 'both invoices are grouped in');
    t_eq((int)$r['billed'], 15000, 'the billed total excludes the cancelled invoice');

    $kinds = array_count_values(array_column($eng['members'], 'kind'));
    t_ok(($kinds['REPORT'] ?? 0) === 1 && ($kinds['INVOICE'] ?? 0) === 2, 'the member list carries every spine object type');
    $jobCodes = array_map(fn($m) => $m['ref'], array_filter($eng['members'], fn($m) => $m['kind'] === 'JOB'));
    t_ok(!in_array('JOB-X', $jobCodes, true), 'another contract\'s job does not leak into this engagement');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

$v = file_get_contents(__DIR__ . '/../views/ops/contract_detail.php');
t_ok(strpos($v, 'engagement_render') !== false, 'the contract detail shows the engagement panel');
