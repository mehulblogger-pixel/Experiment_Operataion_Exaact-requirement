<?php
// Module 22 — Complaints. A read-only derived STAGE (where it is in the flow) and one
// consolidated SLA badge, both from the existing columns — plus the previously-missing
// behavioural coverage of the SLA clocks and the close gate. Lifecycle unchanged.
t_section('Module 22 — complaint stage, SLA badge, and the close gate');

$lib = file_get_contents(__DIR__ . '/../lib/complaints.php');
$det = file_get_contents(__DIR__ . '/../views/ops/complaint_detail.php');
$reg = file_get_contents(__DIR__ . '/../views/ops/complaints.php');

t_ok(function_exists('cmp_stage') && function_exists('cmp_sla'), 'the stage + SLA helpers exist');

// ---- Stage derivation across the flow ----
$stage = fn($c) => cmp_stage($c)['key'];
t_ok($stage(['status'=>'OPEN']) === 'RECEIVED', 'a fresh complaint is at Received');
t_ok($stage(['status'=>'OPEN', 'acknowledged_on'=>'2026-08-02']) === 'ACKNOWLEDGED', 'once acknowledged, it is at Acknowledged');
t_ok($stage(['status'=>'OPEN', 'acknowledged_on'=>'2026-08-02', 'validity'=>'VALID']) === 'TRIAGED', 'a validity decision moves it to Triaged');
t_ok($stage(['status'=>'OPEN', 'acknowledged_on'=>'x', 'validity'=>'VALID', 'root_cause'=>'gap']) === 'INVESTIGATED', 'a root cause moves it to Investigated');
t_ok($stage(['status'=>'OPEN', 'acknowledged_on'=>'x', 'validity'=>'VALID', 'root_cause'=>'gap', 'outcome'=>'NOT_UPHELD']) === 'DECIDED', 'an outcome moves it to Decided');
// Upheld needs a corrective action: with a capa_ref it advances, without it stays at Decided.
$upheld = ['status'=>'OPEN', 'acknowledged_on'=>'x', 'validity'=>'VALID', 'root_cause'=>'gap', 'outcome'=>'UPHELD'];
t_ok($stage($upheld) === 'DECIDED' && cmp_stage($upheld)['next'] === 'Raise the corrective action',
    'an upheld complaint waits at Decided until a corrective action is raised');
t_ok($stage(array_merge($upheld, ['capa_ref'=>'CAPA-1'])) === 'CORRECTIVE', 'a capa_ref advances an upheld complaint to Corrective action');
t_ok($stage(array_merge($upheld, ['capa_ref'=>'CAPA-1', 'notified_on'=>'2026-08-20'])) === 'RESPONDED', 'notifying the complainant moves it to Complainant told');
t_ok($stage(['status'=>'CLOSED']) === 'CLOSED', 'a closed complaint is at Closed');

// ---- SLA badge ----
t_ok(cmp_sla(['status'=>'OPEN', 'received_on'=>date('Y-m-d')])['status'] === 'ON_TRACK', 'a just-received complaint is On track');
$old = date('Y-m-d', strtotime('-' . (cmp_ack_days() + 5) . ' days'));
t_ok(cmp_sla(['status'=>'OPEN', 'received_on'=>$old])['status'] === 'ACK_OVERDUE', 'past the ack deadline with no acknowledgement → Acknowledgement overdue');
$oldD = date('Y-m-d', strtotime('-' . (cmp_decide_days() + 5) . ' days'));
t_ok(cmp_sla(['status'=>'OPEN', 'received_on'=>$oldD, 'acknowledged_on'=>$oldD, 'outcome'=>'PENDING'])['status'] === 'DECIDE_OVERDUE',
    'acknowledged but past the decide deadline → Decision overdue');
t_ok(cmp_sla(['status'=>'CLOSED', 'received_on'=>'2026-08-01', 'acknowledged_on'=>'2026-08-02', 'decided_on'=>'2026-08-10'])['status'] === 'MET',
    'a closed complaint handled within the deadlines shows SLA met');
t_ok(cmp_sla(['status'=>'CLOSED', 'received_on'=>'2026-08-01', 'acknowledged_on'=>'2026-09-30', 'decided_on'=>'2026-09-30'])['status'] === 'MET_LATE',
    'a closed complaint that had breached shows SLA met (late)');
t_ok(cmp_sla(['status'=>'OPEN', 'received_on'=>''])['status'] === 'ON_TRACK', 'a blank received date is not treated as overdue');

// ---- The close gate (previously untested) ----
$m0 = cmp_close_missing(['status'=>'OPEN', 'validity'=>'PENDING', 'outcome'=>'PENDING']);
t_ok(count($m0) >= 3, 'a barely-started complaint lists several closure requirements');
// Fully handled, not-upheld → nothing missing.
$done = ['acknowledged_on'=>'x', 'validity'=>'VALID', 'outcome'=>'NOT_UPHELD', 'decided_by'=>'Mgr', 'notified_on'=>'y', 'anonymous'=>0, 'complainant_email'=>'a@b.c'];
t_ok(cmp_close_missing($done) === [], 'a fully-handled, not-upheld complaint has nothing left to close');
// Upheld with no CAPA → the corrective-action requirement remains.
$upClose = array_merge($done, ['outcome'=>'UPHELD']);
t_ok((bool)array_filter(cmp_close_missing($upClose), fn($m) => stripos($m, 'corrective action') !== false),
    'an upheld complaint cannot close without a recorded corrective action');
t_ok(cmp_close_missing(array_merge($upClose, ['capa_ref'=>'CAPA-9'])) === [], 'adding the corrective-action reference clears the gate');

// ---- Surfaces + preserved gate ----
t_ok(strpos($det, 'cmp_stage($c)') !== false && strpos($det, 'cmp_sla($c)') !== false, 'the detail screen shows the stage strip and SLA badge');
t_ok(strpos($reg, 'cmp_stage($c)') !== false && strpos($reg, 'cmp_sla($c)') !== false, 'the register shows the stage + SLA per row');
t_ok(function_exists('cmp_decide_block'), 'the §7.5.4 decide impartiality gate is intact');
t_ok(!preg_match('/can\(\x27complaints\.(stage|sla)/', $lib), 'Module 22 introduces no new permission constant');
