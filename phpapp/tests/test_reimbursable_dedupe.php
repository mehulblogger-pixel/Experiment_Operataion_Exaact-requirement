<?php
// P8 (C + B) — reimbursable de-duplication. Reimbursables can be keyed on two doors for one
// job (closure expenses + inspector voucher); job_profit sums both. Option C is the worklist
// (cost_dualwrite_scan) to reconcile per job; Option B is the mode-gated netting that, when ON
// (off by default), subtracts the detected overlap in job_profit. The safety contract: the
// DEFAULT changes no figure, and the toggle never mutates the source expense/voucher rows.
t_section('reimbursable de-duplication (P8 · C + B)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $mode0 = reimbursable_dedupe_mode();

    db()->prepare("INSERT INTO jobs (job_code, closed_flag, created_at) VALUES ('J-DEDUP', 1, ?)")->execute([date('c')]);
    $both = (int)db()->lastInsertId();
    // Reimbursables on BOTH doors: closure expenses 500 + voucher 300 → overlap min = 300.
    db()->prepare("INSERT INTO expenses (job_id, sbu, travel, local, food, lodging, misc, exp_date) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$both, '', 500, 0, 0, 0, 0, '2026-08-10']);
    db()->prepare("INSERT INTO voucher_entries (voucher_id, job_id, entry_date, day_type, row_total) VALUES (?,?,?,?,?)")
        ->execute([1, $both, '2026-08-10', 'WORK', 300]);
    // A one-sided job: closure expenses only → no overlap.
    db()->prepare("INSERT INTO jobs (job_code, closed_flag, created_at) VALUES ('J-DEDUP-ONE', 1, ?)")->execute([date('c')]);
    $one = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO expenses (job_id, sbu, travel, local, food, lodging, misc, exp_date) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$one, '', 400, 0, 0, 0, 0, '2026-08-11']);

    // default is 'off' — accurate total, nothing netted
    reimbursable_dedupe_set_mode('off');
    t_eq(reimbursable_dedupe_mode(), 'off', 'the default handling is off (count both doors)');
    t_eq(reimbursable_dedupe_amount(500, 300), 0.0, 'off: nothing is netted');

    // net mode nets min(expenses, voucher) for a both-sided job, and nothing when one-sided
    reimbursable_dedupe_set_mode('net');
    t_eq(reimbursable_dedupe_amount(500, 300), 300.0, 'net: the overlap is min(expenses, voucher)');
    t_eq(reimbursable_dedupe_amount(400, 0), 0.0, 'net: a one-sided job nets nothing');

    // job_profit reflects the mode: dedupe 0 under off, 300 under net, and cost drops by 300 (+ its contingency)
    $rowBoth = ops_one("SELECT * FROM jobs WHERE id=?", [$both]);
    reimbursable_dedupe_set_mode('off');
    $pOff = job_profit($rowBoth);
    reimbursable_dedupe_set_mode('net');
    $pNet = job_profit($rowBoth);
    t_eq(round($pOff['dedupe'], 2), 0.0, 'job_profit nets nothing under off');
    t_eq(round($pNet['dedupe'], 2), 300.0, 'job_profit nets the 300 overlap under net');
    t_ok($pNet['cost'] < $pOff['cost'], 'netting lowers the job cost');
    t_ok(abs(($pOff['cost'] - $pNet['cost']) - 300 * (1 + $pOff['contingency_pct'] / 100)) < 0.5,
        'the cost drop equals the overlap plus its contingency');
    // a one-sided job is unaffected by the mode
    $rowOne = ops_one("SELECT * FROM jobs WHERE id=?", [$one]);
    t_eq(round(job_profit($rowOne)['dedupe'], 2), 0.0, 'a one-sided job nets nothing even under net mode');

    // the Option-C worklist finds the both-sided job and not the one-sided one
    $scan = cost_dualwrite_scan();
    t_ok((bool)array_filter($scan, fn($r) => (int)$r['job_id'] === $both), 'the worklist lists the both-sided job');
    t_ok(!array_filter($scan, fn($r) => (int)$r['job_id'] === $one), 'the worklist excludes a one-sided job');
    $sum = cost_dualwrite_summary();
    t_ok($sum['jobs'] >= 1 && $sum['overlap_total'] >= 300, 'the summary counts the job and its overlap');

    // an invalid mode is rejected; the toggle NEVER mutates the source expense/voucher rows
    t_ok(reimbursable_dedupe_set_mode('bogus') === false, 'an invalid handling is rejected');
    t_eq((float)ops_val("SELECT COALESCE(SUM(travel),0) FROM expenses WHERE job_id=?", [$both]), 500.0, 'the closure expense row is never mutated');
    t_eq((float)ops_val("SELECT COALESCE(SUM(row_total),0) FROM voucher_entries WHERE job_id=?", [$both]), 300.0, 'the voucher row is never mutated');

    reimbursable_dedupe_set_mode($mode0);
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    if (function_exists('reimbursable_dedupe_set_mode')) reimbursable_dedupe_set_mode($mode0 ?? 'off');
}
