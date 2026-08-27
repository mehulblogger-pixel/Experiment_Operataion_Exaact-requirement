<?php
// Revamp R9 — cost dual-write detector (read-only). Reimbursables can be keyed on
// two doors for one job: the coordinator's closure `expenses` and the inspector's
// `voucher`. job_profit() sums both, so the same trip on both sides is
// double-counted. The detector surfaces such jobs; it changes no figure.
t_section('cost dual-write detector (Revamp R9)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO jobs (job_code, closed_flag, created_at) VALUES ('J-DW-BOTH',1,?)")->execute([date('c')]);
    $both = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO jobs (job_code, closed_flag, created_at) VALUES ('J-DW-EXP',1,?)")->execute([date('c')]);
    $expOnly = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO jobs (job_code, closed_flag, created_at) VALUES ('J-DW-NONE',1,?)")->execute([date('c')]);
    $none = (int)db()->lastInsertId();

    // Reimbursables on BOTH sides for $both: closure expenses 500 + voucher 300.
    db()->prepare("INSERT INTO expenses (job_id, sbu, travel, local, food, lodging, misc, exp_date) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$both, '', 500, 0, 0, 0, 0, '2026-08-10']);
    db()->prepare("INSERT INTO voucher_entries (voucher_id, job_id, entry_date, day_type, row_total) VALUES (?,?,?,?,?)")
        ->execute([1, $both, '2026-08-10', 'WORK', 300]);
    // Only the closure-expenses side for $expOnly.
    db()->prepare("INSERT INTO expenses (job_id, sbu, travel, local, food, lodging, misc, exp_date) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$expOnly, '', 400, 0, 0, 0, 0, '2026-08-11']);

    $c = cost_sources($both);
    t_eq($c['expenses'], 500.0, 'the closure-expenses side is read');
    t_eq($c['voucher'], 300.0, 'the voucher side is read');
    t_ok($c['both_sided'] === true, 'reimbursables on both sides is flagged');
    t_eq($c['overlap'], 300.0, 'the likely double-counted amount is min(expenses, voucher)');

    t_ok(cost_dualwrite_flag($both) === true, 'the dual-write flag is true for a both-sided job');
    t_ok(cost_dualwrite_flag($expOnly) === false, 'a job with only closure expenses is NOT flagged');
    t_ok(cost_dualwrite_flag($none) === false, 'a job with no reimbursables is NOT flagged');

    $scan = cost_dualwrite_scan();
    t_ok((bool)array_filter($scan, fn($r) => $r['job_id'] === $both), 'the both-sided job appears on the reconciliation scan');
    t_ok(!array_filter($scan, fn($r) => $r['job_id'] === $expOnly), 'a one-sided job does not appear on the scan');

    ob_start(); cost_dualwrite_render($both); $html = ob_get_clean();
    t_ok(strpos($html, 'double-counted') !== false, 'the job-detail warning renders for a both-sided job');
    ob_start(); cost_dualwrite_render($expOnly); $none_html = ob_get_clean();
    t_eq($none_html, '', 'no warning renders for a one-sided job');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
