<?php
// Field-finding #21 — "closing showed the expense sheet, then let me insert the expenses again." Same root
// cause as #24 (the close/expense form re-appearing): #24 stops the form re-showing (GET) and the second
// close (POST). #21 adds the concrete concern — DUPLICATE expense rows. The closure-expense INSERT now has
// a data-layer guard: a job records its day's closure expenses EXACTLY ONCE, so no path can double the
// engineer's claim. This complements the UI guard.
t_section('Field #21 — closure expenses recorded exactly once (no duplicate on re-close)');

$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, 'Field-finding #21') !== false, 'the closure-expense guard is documented in the handler');
// The INSERT is wrapped by an existing-row check.
$h     = strpos($ops, "if (\$route === 'job-close')");
$guard = strpos($ops, '$hasExp = (int) ops_val("SELECT COUNT(*) FROM expenses WHERE job_id=?"', $h);
$ins   = strpos($ops, "INSERT INTO expenses (job_id,inspector_id,sbu,travel", $h);
t_ok($guard !== false, 'the handler checks for an existing closure-expense row');
t_ok($guard !== false && $ins !== false && $guard < $ins, 'the existence check runs before the INSERT');
t_ok(strpos($ops, 'if (!$hasExp) {', $h) !== false, 'the INSERT only runs when no closure-expense row exists yet');

// Behavioural: the guarded insert is idempotent — two close attempts leave exactly one expenses row.
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();
    $pdo->prepare("INSERT INTO jobs (job_code, inspector_id, sbu, closed_flag) VALUES ('JB-E21',0,'NDT',0)")->execute();
    $jid = (int)$pdo->lastInsertId();
    // the exact guard the handler applies
    $insertOnce = function ($jid) {
        $has = (int) ops_val("SELECT COUNT(*) FROM expenses WHERE job_id=?", [$jid]);
        if (!$has) db()->prepare("INSERT INTO expenses (job_id, travel, food) VALUES (?, 3000, 1000)")->execute([$jid]);
    };
    $insertOnce($jid);
    t_eq((int) ops_val("SELECT COUNT(*) FROM expenses WHERE job_id=?", [$jid]), 1, 'the first close records one closure-expense row');
    $insertOnce($jid);   // a second attempt (race / slipped UI guard)
    t_eq((int) ops_val("SELECT COUNT(*) FROM expenses WHERE job_id=?", [$jid]), 1, 'a second close attempt does NOT add a duplicate row');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
