<?php
// Module 04 — Calls. Present ONE user-facing lifecycle over the two status systems
// (legacy `status` + operational `op_status`). The lifecycle value already exists via
// tosrm_call_status(); this surfaces its label and stops the raw legacy status leaking
// to normal users. No new status, no writes, R6 rules untouched.
t_section('Module 04 — one user-facing call lifecycle');

$tos = file_get_contents(__DIR__ . '/../lib/tosrm.php');
$det = file_get_contents(__DIR__ . '/../views/ops/call_detail.php');
$reg = file_get_contents(__DIR__ . '/../views/ops/calls.php');

t_ok(function_exists('call_status_label'), 'the unified lifecycle-label helper exists');

// op_status set → it wins and is labelled from CALL_STATUSES.
t_ok(call_status_label(['op_status' => 'IN_PROGRESS', 'status' => 'ALLOCATED'])['label'] === (CALL_STATUSES['IN_PROGRESS'] ?? 'IN_PROGRESS'),
    'when op_status is set, it is the user-facing status');
t_ok(call_status_label(['op_status' => 'IN_PROGRESS'])['key'] === 'IN_PROGRESS', 'the key reflects op_status when set');

// op_status empty → derived from legacy, and labelled (never the raw legacy string, never blank).
$cases = [
    ['OPEN', 'RECEIVED'], ['FORWARDED', 'READY_TO_SCHEDULE'], ['ALLOCATED', 'ASSIGNED'], ['CLOSED', 'CLOSED'],
];
foreach ($cases as [$legacy, $expectKey]) {
    $lc = call_status_label(['op_status' => '', 'status' => $legacy]);
    t_ok($lc['key'] === $expectKey && $lc['label'] === (CALL_STATUSES[$expectKey] ?? $expectKey),
        "legacy $legacy is presented as the lifecycle label '" . (CALL_STATUSES[$expectKey] ?? $expectKey) . "'");
}

// Tone: terminal-bad, on-hold, done.
t_ok(call_status_label(['op_status' => 'REJECTED'])['tone'] === 'p-bad', 'a rejected call reads as a bad/terminal tone');
t_ok(call_status_label(['op_status' => 'ON_HOLD'])['tone'] === 'p-warn', 'on-hold reads as a warning tone');
t_ok(call_status_label(['op_status' => 'CLOSED'])['tone'] === 'p-ok', 'a closed call reads as a done tone');

// Unknown value → falls back to the raw string, not blank/fatal.
t_ok(call_status_label(['op_status' => 'WEIRD_X'])['label'] === 'WEIRD_X', 'an unknown status falls back to its raw value, never blank');

// The detail no longer leaks the raw legacy status to everyone; raw is admin-only.
t_ok(strpos($det, 'call_status_label($call)') !== false, 'the call detail shows the unified lifecycle label');
t_ok(strpos($det, "is_admin_level()") !== false && strpos($det, 'system: ') !== false,
    'the raw system status is shown only to admins, not leaked to normal users');
$rawLine = '<' . '?= e($call[' . "'status'" . ']) ?' . '></div>';   // the old bare-status line
t_ok(strpos($det, $rawLine) === false, 'the bare raw-legacy-status line is gone from the user-facing overview');

// The register shows the unified status pill (additively; the scheduling chips stay).
t_ok(strpos($reg, 'call_status_label($c)') !== false, 'the register shows the unified lifecycle status per row');
t_ok(strpos($reg, 'To schedule') !== false && strpos($reg, 'In progress') !== false,
    'the existing scheduling chips are preserved (additive)');

// Guardrails: derivation and the R6 transition rules are untouched.
t_ok(strpos($tos, 'function tosrm_derive_status') !== false && strpos($tos, 'function tosrm_can_transition') !== false,
    'the one-way derivation and the R6 transition rules are intact');
t_ok(!preg_match('/can\(\x27calls\.(lifecycle|stage)/', $tos), 'Module 04 introduces no new permission constant');
