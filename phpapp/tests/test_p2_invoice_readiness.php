<?php
// Phase 2 §33 — invoice readiness. Raising an invoice can go wrong quietly: billed before the report is
// issued, before the client accepted a release, or beyond the contract value. invoice_readiness()
// assembles a READY/NOT-READY verdict from signals that already exist. Advisory by default; it blocks
// the billing action only when invoice_gate_strict is set (mirrors the §10 issuance gate). Read-only.
t_section('Phase 2 §33 — invoice readiness (advisory; strict-gated)');

t_ok(function_exists('invoice_readiness') && function_exists('invoice_readiness_block') && function_exists('invoice_readiness_render'),
     'the readiness helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/invready.php'") !== false, 'the invready lib is loaded by the front controller');

// An open job is never ready — closing comes first.
$openJob = ['id' => 0, 'closed_flag' => 0];
$ro = invoice_readiness($openJob);
t_ok($ro['ready'] === false, 'an open job is not ready to invoice');
t_ok($ro['blockers'][0]['code'] === 'closed', 'the first blocker is that the job is not closed');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$savedStrict = setting_get('invoice_gate_strict', '');
$savedAccept = setting_get('rn_require_client_acceptance', '');
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('idems_migrate')) idems_migrate();
    if (function_exists('books_migrate')) books_migrate();
    if (function_exists('contracts_migrate')) contracts_migrate();
    setting_set('rn_require_client_acceptance', '');   // isolate from the acceptance check here
    $pdo = db();

    // A closed job with a contract and one ISSUED report -> ready.
    $pdo->prepare("INSERT INTO partner_contracts (contract_number, value, open_status) VALUES ('CN-IR', 100000, 'OPEN')")->execute();
    $pdo->prepare("INSERT INTO jobs (job_code, closed_flag, contract_number, invoice_value) VALUES ('J-IR', 1, 'CN-IR', 40000)")->execute();
    $jid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_docs (job_id, type_code, status, deleted) VALUES (?, 'FIR','ISSUED', 0)")->execute([$jid]);
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [$jid]);
    $r = invoice_readiness($job);
    t_ok($r['ready'] === true, 'a closed job with an issued report and room under the contract is ready');

    // Add a still-in-draft report -> a blocker.
    $pdo->prepare("INSERT INTO report_docs (job_id, type_code, status, deleted) VALUES (?, 'FIR','DRAFT', 0)")->execute([$jid]);
    $r2 = invoice_readiness($job);
    t_ok($r2['ready'] === false, 'an unissued report makes the job not ready');
    $codes = array_column($r2['blockers'], 'code');
    t_ok(in_array('reports_issued', $codes, true), 'the blocker is the unissued report');

    // Remove the draft; push billed-so-far over the contract value -> a WARNING (not a block).
    $pdo->prepare("DELETE FROM report_docs WHERE job_id=? AND status='DRAFT'")->execute([$jid]);
    $pdo->prepare("INSERT INTO invoices (invoice_no, contract_number, status, total) VALUES ('IV-IR','CN-IR','ISSUED', 90000)")->execute();
    $r3 = invoice_readiness($job);   // 90000 billed + 40000 this job = 130000 > 100000
    $warnCodes = array_column($r3['warnings'], 'code');
    t_ok(in_array('contract_value', $warnCodes, true), 'over-contract-value is surfaced as a warning');
    t_ok($r3['ready'] === true, 'a warning does not, by itself, make the job not-ready (advisory)');

    // The strict gate: block only when the setting is on AND a blocker fails.
    setting_set('invoice_gate_strict', '');
    t_eq(invoice_readiness_block($job), '', 'with the gate off, billing is never blocked');
    setting_set('invoice_gate_strict', '1');
    t_eq(invoice_readiness_block($job), '', 'a ready job is not blocked even under the strict gate');
    // Re-introduce a blocker under the strict gate.
    $pdo->prepare("INSERT INTO report_docs (job_id, type_code, status, deleted) VALUES (?, 'FIR','DRAFT', 0)")->execute([$jid]);
    t_ok(invoice_readiness_block($job) !== '', 'under the strict gate, a failing check blocks billing with a message');
} finally {
    setting_set('invoice_gate_strict', (string)$savedStrict);
    setting_set('rn_require_client_acceptance', (string)$savedAccept);
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The strict gate is wired into the billing action, and the panel onto the job screen + governance registry.
$src = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($src, 'invoice_readiness_block($job)') !== false, 'the job-bill handler consults the strict gate');
$jv = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
t_ok(strpos($jv, 'invoice_readiness_render') !== false, 'the job screen shows the readiness panel');
t_ok(setting_meta('invoice_gate_strict') !== null, 'the strict-gate setting is documented in the §47 governance registry');
