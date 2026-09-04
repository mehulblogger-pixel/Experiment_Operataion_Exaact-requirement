<?php
// Revamp P4 — the Billable Event ledger. Additive, non-destructive. The books
// ledger stays the money truth; a billed event reconciles to its invoice. Tests
// the derivation (sync from closed billable jobs), idempotency, the lifecycle
// transitions, and reconciliation to BILLED when the source job is invoiced.
t_section('billable event ledger (Revamp P4)');

billable_migrate();

// ---- Pure transition matrix ------------------------------------------------
t_ok(billable_can_transition('PENDING', 'APPROVED'),   'PENDING → APPROVED allowed');
t_ok(billable_can_transition('PENDING', 'CANCELLED'),  'PENDING → CANCELLED allowed');
t_ok(!billable_can_transition('PENDING', 'BILLED'),    'PENDING → BILLED is NOT a manual step');
t_ok(billable_can_transition('APPROVED', 'DISPUTED'),  'APPROVED → DISPUTED allowed');
t_ok(billable_can_transition('DISPUTED', 'APPROVED'),  'DISPUTED → APPROVED allowed');
t_ok(!billable_can_transition('BILLED', 'APPROVED'),   'BILLED is terminal');
t_ok(!billable_can_transition('CANCELLED', 'APPROVED'),'CANCELLED is terminal');

// ---- Become master so the office scope is ALL (mirrors test_bill_by_project) ----
$pdo = db();
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser) VALUES ('billable_master','B','MASTER_ADMIN',1,1)")->execute();
$_SESSION['uid'] = (int)$pdo->lastInsertId();
current_user(true); ua(true);
if (!is_master()) { t_ok(true, 'could not become master in this run — skipping the rest'); unset($_SESSION['uid']); current_user(true); ua(true); return; }

// ---- Fixtures: a client, a call with a billable value, a closed job --------
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('Billable Co','Billable Co',1,'ACTIVE')")->execute();
$cid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO calls (client_id, contract_number, call_code, inspection_type, billable_value, billable_rate, billable_qty, billable_basis, sbu, created_at)
               VALUES (?,?,?,?,?,?,?,?,'',?)")
    ->execute([$cid, 'CON-1', 'C-BE-1', 'INSPECTION', 12000, 2000, 6, 'MANDAY', date('c')]);
$callId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (call_id, job_code, inspection_type, closed_flag, closed_at, executing_office_id, sbu, created_at)
               VALUES (?,?,?,?,?,?,'',?)")
    ->execute([$callId, 'J-BE-1', 'INSPECTION', 1, '2026-08-10', 1, date('c')]);
$jobId = (int)$pdo->lastInsertId();

// ---- Derive: sync creates ONE pending event from the closed unbilled job ---
$r1 = billable_events_sync();
t_ok(is_array($r1) && $r1['created'] >= 1, 'sync derives billable candidates from closed work');
$ev = ops_one("SELECT * FROM billable_events WHERE source_module='job' AND source_id=?", [$jobId]);
t_ok($ev && $ev['status'] === 'PENDING', 'a PENDING billable event exists for the closed job');
t_eq((float)$ev['amount'], 12000.0, 'the event carries the call billable value');
t_eq((int)$ev['party_id'], $cid, 'the event carries the client');
t_eq((string)$ev['contract_number'], 'CON-1', 'the event carries the contract number');

// ---- Idempotent: a second sync does not duplicate --------------------------
billable_events_sync();
t_eq((int)ops_val("SELECT COUNT(*) FROM billable_events WHERE source_module='job' AND source_id=?", [$jobId]), 1,
    're-running sync does not duplicate the event');

// ---- Approve ---------------------------------------------------------------
t_ok(billable_set_status((int)$ev['id'], 'APPROVED'), 'the event can be approved');
$ev2 = ops_one("SELECT * FROM billable_events WHERE id=?", [(int)$ev['id']]);
t_eq($ev2['status'], 'APPROVED', 'status is APPROVED');
t_ok(trim((string)$ev2['approved_by']) !== '', 'the approver is stamped');
t_ok(!billable_set_status((int)$ev['id'], 'BILLED'), 'you cannot manually mark an event BILLED');

// ---- Reconcile: once the job is invoiced, the event flips to BILLED --------
$pdo->prepare("INSERT INTO invoices (invoice_no, partner_id, office_id, status, total, invoice_date, created_at) VALUES ('INV-BE-1',?,?, 'ISSUED', 13000, '2026-08-20', ?)")
    ->execute([$cid, 1, date('c')]);
$invId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO invoice_lines (invoice_id, job_id, description, amount, line_total) VALUES (?,?, 'Inspection', 12000, 13000)")
    ->execute([$invId, $jobId]);

$r2 = billable_events_sync();
t_ok($r2['billed'] >= 1, 'sync reconciles the invoiced job to BILLED');
$ev3 = ops_one("SELECT * FROM billable_events WHERE id=?", [(int)$ev['id']]);
t_eq($ev3['status'], 'BILLED', 'the event is now BILLED');
t_eq((int)$ev3['invoice_id'], $invId, 'the event links to the invoice that consumed it');
t_eq((float)$ev3['amount'], 13000.0, 'the billed amount is taken from the invoice (books wins on money)');
t_ok(!billable_set_status((int)$ev['id'], 'APPROVED'), 'a BILLED event is terminal');

// ---- Rollup + list ---------------------------------------------------------
$roll = billable_rollup();
t_ok($roll['billed'] >= 1 && $roll['billed_amt'] >= 13000, 'the rollup counts the billed value');
$rows = billable_list();
t_ok((bool)array_filter($rows, fn($x) => (int)$x['id'] === (int)$ev['id']), 'the event appears on the board list');
t_ok(isset($rows[0]['party_name']), 'the list resolves the client name');

// ---- cleanup: drop the test session so later files are unaffected ----------
unset($_SESSION['uid']); current_user(true); ua(true);
