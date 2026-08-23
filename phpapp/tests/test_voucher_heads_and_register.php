<?php
// (a) Every inspector can always record an incidental expense — even under a narrow
//     entitlement, the voucher offers the "Others (specify)" catch-all head.
// (b) The calls register marks a call done when all its jobs are closed (matching
//     the call detail) and falls back to the report's inspector for the team column.
t_section('voucher always offers an incidental head; register status follows job closure');

$pdo = db();
if (function_exists('ops_seed_expense_masters')) { try { ops_seed_expense_masters(); } catch (Throwable $e) {} }

// --- (a) voucher_heads_for -----------------------------------------------------
$pdo->prepare("INSERT INTO inspectors (name) VALUES ('VHR Ravi')")->execute();
$insId = (int)$pdo->lastInsertId();
// Narrow entitlement: only FOOD and HOTEL — deliberately NOT 'OTHER'.
foreach (['FOOD', 'HOTEL'] as $hc)
    $pdo->prepare("INSERT INTO inspector_allowances (inspector_id, kind, code, allowed) VALUES (?, 'HEAD', ?, 1)")->execute([$insId, $hc]);
$codes = array_map(fn($h) => strtoupper((string)$h['code']), voucher_heads_for($insId));
t_ok(in_array('FOOD', $codes, true), 'the entitled Food head is offered');
t_ok(in_array('OTHER', $codes, true), 'an incidental "Others (specify)" head is ALWAYS offered, even off-entitlement');
t_ok(!in_array('KMTRAVEL', $codes, true), 'the computed travel head is never a manual column');

// An inspector with NO entitlements gets all active heads (incl. the catch-all).
$pdo->prepare("INSERT INTO inspectors (name) VALUES ('VHR Amit')")->execute();
$insId2 = (int)$pdo->lastInsertId();
$codes2 = array_map(fn($h) => strtoupper((string)$h['code']), voucher_heads_for($insId2));
t_ok(in_array('OTHER', $codes2, true) && in_array('FOOD', $codes2, true), 'an un-entitled inspector still sees the standard heads');

// --- (b) register status source -----------------------------------------------
$src = file_get_contents(__DIR__ . '/../views/ops/calls.php');
t_ok(strpos($src, "closed_count") !== false, 'the register uses the closed-job count for status');
t_ok(strpos($src, 'report_inspector') !== false, 'the register falls back to the report inspector for the team column');
t_ok(strpos($src, 'issued_reports') !== false, 'the register shows when reports have actually been issued');
