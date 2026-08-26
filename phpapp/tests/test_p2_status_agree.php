<?php
// Phase 2 §46 — status standardisation. A call carries a legacy `status` and a canonical `op_status`;
// once op_status is set the legacy value is ignored, so the two can silently drift (one says CLOSED,
// the other OPEN). Per the spec ("do not allow two statuses to silently disagree; if they disagree,
// flag the record for repair"), a terminality mismatch is now detected and surfaced as a §7.11
// data-integrity check (reusing the Module 29 engine). Read-only; nothing is changed automatically.
t_section('Phase 2 §46 — call status disagreement detection');

t_ok(function_exists('call_status_disagrees') && function_exists('calls_status_disagreement_count'), 'the disagreement helpers exist');

// The rule: a mismatch on "is it finished" (CLOSED/CANCELLED) is a disagreement; anything else is not.
t_ok(call_status_disagrees(['op_status' => 'CLOSED', 'status' => 'OPEN']) === true,  'op=CLOSED vs legacy=OPEN is a disagreement');
t_ok(call_status_disagrees(['op_status' => 'RECEIVED', 'status' => 'CLOSED']) === true, 'op=open vs legacy=CLOSED is a disagreement');
t_ok(call_status_disagrees(['op_status' => 'CLOSED', 'status' => 'CLOSED']) === false, 'both finished → agree');
t_ok(call_status_disagrees(['op_status' => 'CANCELLED', 'status' => 'CANCELLED']) === false, 'both cancelled → agree');
t_ok(call_status_disagrees(['op_status' => 'ASSIGNED', 'status' => 'ALLOCATED']) === false, 'both in-progress → not a disagreement (benign)');
t_ok(call_status_disagrees(['op_status' => '', 'status' => 'CLOSED']) === false, 'with no canonical op_status there is nothing to disagree with');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('tosrm_migrate')) tosrm_migrate();
    $pdo = db();
    $base = calls_status_disagreement_count();

    // A disagreeing call: op_status=CLOSED but legacy status still OPEN.
    $pdo->prepare("INSERT INTO calls (call_code, status, op_status) VALUES ('CALL-DIS','OPEN','CLOSED')")->execute();
    // An agreeing call: both finished.
    $pdo->prepare("INSERT INTO calls (call_code, status, op_status) VALUES ('CALL-OK','CLOSED','CLOSED')")->execute();
    // A call with no op_status (legacy only) — never a disagreement.
    $pdo->prepare("INSERT INTO calls (call_code, status, op_status) VALUES ('CALL-LEG','CLOSED','')")->execute();

    t_eq(calls_status_disagreement_count(), $base + 1, 'exactly the one terminality-mismatched call is counted');

    // It surfaces as a §7.11 integrity check (Module 29), and fails (found > 0) while the mismatch exists.
    if (function_exists('integrity_checks')) {
        $checks = integrity_checks();
        $row = null; foreach ($checks as $c) if ($c['key'] === 'call_status_agree') $row = $c;
        t_ok($row !== null, 'a call-status-agreement integrity check is registered');
        t_ok($row['found'] >= 1 && $row['ok'] === false, 'the check flags the disagreeing record for repair');
    }
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// wiring
$dc = file_get_contents(__DIR__ . '/../lib/datacontrol.php');
t_ok(strpos($dc, "'call_status_agree'") !== false, 'the integrity engine includes the status-agreement check');
