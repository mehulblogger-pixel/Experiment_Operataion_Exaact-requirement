<?php
// Field-finding #21 — "closing showed the expense sheet, then let me insert the expenses again." Same root
// cause as #24 (the close/expense form re-appearing): #24 stops the form re-showing (GET) and the second
// close (POST). #21 adds the concrete concern — DUPLICATE expense rows.
//
// Expenses Phase 1 (owner, 17 Nov 2026 — doc 36, "Profit counts a cost once"): closing the day no longer
// writes a separate `expenses` row at all. The day's expenses are the claim's own lines (voucher_entries),
// and confirming a day REPLACES that day's travel / allowance lines, so a re-close, a resend or a second
// confirm can never double the engineer's claim. The behavioural proof is in
// tests/test_expenses_phase1_dayclose.php; this file keeps the source guard.
t_section('Field #21 — closure expenses recorded exactly once (no duplicate on re-close)');

$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
$exl = file_get_contents(__DIR__ . '/../lib/expenses.php');
t_ok(strpos($ops, 'Field-finding #21') !== false, 'the closure-expense guard is documented in the handler');
$h = strpos($ops, "if (\$route === 'job-close')");
$end = strpos($ops, "if (\$route === 'job-visit-close'", $h);
$body = substr($ops, $h, $end - $h);
t_ok(strpos($body, 'INSERT INTO expenses') === false, 'Phase 1 · the job close writes no `expenses` row any more (one record, counted once)');
t_ok(strpos($body, 'exp_day_confirm($expIns') !== false, 'Phase 1 · the job close confirms the day on the claim instead');
t_ok(strpos($exl, "DELETE FROM voucher_entries WHERE voucher_id=? AND job_id=? AND entry_date=? AND source='DAY'") !== false,
    'Phase 1 · confirming a day replaces its travel / allowance lines — never adds them twice');
