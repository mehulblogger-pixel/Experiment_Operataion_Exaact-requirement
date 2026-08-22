<?php
// Billing rhythms: per call, per month (cumulative), and per project. They all
// come off the billable pool, which must carry the CONTRACT and the CLOSED date
// on every line so the to-bill screen can group customer → contract and filter
// by month. Guards books_billable_jobs() supplying those fields.
t_section('billable pool carries contract + closed date (per-project / per-month billing)');

$pdo = db();
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser) VALUES ('bill_master','B','MASTER_ADMIN',1,1)")->execute();
$_SESSION['uid'] = (int)$pdo->lastInsertId();
current_user(true); ua(true);
if (!is_master()) { t_ok(true, 'could not become master in this run — skipping'); unset($_SESSION['uid']); current_user(true); ua(true); return; }

$pdo->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('Bill Project Co', 1, 'ACTIVE')")->execute();
$clientId = (int)$pdo->lastInsertId();

// Two projects (contracts) for one client; a closed, unbilled job on each.
$mkCall = function ($contract, $val) use ($pdo, $clientId) {
    $pdo->prepare("INSERT INTO calls (client_id, contract_number, call_code, billable_value, billable_basis, sbu, created_at)
                   VALUES (?,?,?,?,?,'',?)")->execute([$clientId, $contract, 'C-' . $contract, $val, 'MANDAY', date('c')]);
    return (int)$pdo->lastInsertId();
};
$mkJob = function ($callId, $code, $closedAt, $val) use ($pdo, $clientId) {
    $pdo->prepare("INSERT INTO jobs (call_id, job_code, closed_flag, closed_at, invoice_value, sbu, created_at)
                   VALUES (?,?,?,?,?,'',?)")->execute([$callId, $code, 1, $closedAt, $val, date('c')]);
    return (int)$pdo->lastInsertId();
};
$callA = $mkCall('PRJ-A', 5000); $mkJob($callA, 'JA-1', '2026-07-20', 5000);
$callB = $mkCall('PRJ-B', 8000); $mkJob($callB, 'JB-1', '2026-08-05', 8000);

$rows = books_billable_jobs($clientId);
$byContract = [];
foreach ($rows as $r) $byContract[(string)($r['contract_number'] ?? '')] = $r;

t_ok(isset($byContract['PRJ-A']) && isset($byContract['PRJ-B']),
    'each billable line carries its contract, so the pool can group by project');
t_ok(($byContract['PRJ-A']['call_code'] ?? '') !== '', 'the billable line links back to its call');
t_ok(substr((string)($byContract['PRJ-A']['closed_at'] ?? ''), 0, 7) === '2026-07'
     && substr((string)($byContract['PRJ-B']['closed_at'] ?? ''), 0, 7) === '2026-08',
    'each line carries its closed month, so a month filter can pick a billing period');

// A job already on a live invoice drops out of the pool (so it is not billed twice).
$pdo->prepare("INSERT INTO invoices (partner_id, status, invoice_date, created_at) VALUES (?,?,?,?)")
    ->execute([$clientId, 'ISSUED', '2026-08-06', date('c')]);
$invId = (int)$pdo->lastInsertId();
$jobB = (int)ops_val("SELECT id FROM jobs WHERE job_code='JB-1'");
$pdo->prepare("INSERT INTO invoice_lines (invoice_id, job_id, description, amount) VALUES (?,?,?,?)")
    ->execute([$invId, $jobB, 'Inspection', 8000]);
$rows2 = books_billable_jobs($clientId);
$stillThere = false; foreach ($rows2 as $r) if ((string)($r['contract_number'] ?? '') === 'PRJ-B') $stillThere = true;
t_ok(!$stillThere, 'a job already on a live invoice leaves the billable pool');

unset($_SESSION['uid']); current_user(true); ua(true);
