<?php
// Phase 2 §30 (P0 Critical) — historical financial reproducibility. job_profit() recomputed old jobs
// from TODAY's salary / office overhead % / working-days, so historical profit drifted silently. A job
// now freezes its rate basis (daily-base, oh%, contingency%) — at close, or via the nightly backfill —
// and job_profit() prefers the snapshot. Freezing equals the live value at freeze time (changes no
// displayed number), but from then on the profit is reproducible. Open/un-snapshotted jobs are untouched.
t_section('Phase 2 §30 — job cost basis is frozen (reproducible profit)');

t_ok(function_exists('job_cost_snapshot') && function_exists('jobs_backfill_cost_basis'), 'snapshot + backfill helpers exist');
$src = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($src, "trim((string)(\$job['cost_basis_at'] ?? '')) !== ''") !== false, 'job_profit prefers the frozen basis when present');
t_ok(strpos($src, 'job_cost_snapshot((int)$job[\'id\'])') !== false, 'a job freezes its basis at close');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();

    $pdo->prepare("INSERT INTO offices (name, code, overhead_pct, contingency_pct) VALUES ('R-Office','RO',10,5)")->execute();
    $off = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO inspectors (name, status, salary_ctc, agency_cost) VALUES ('R-Insp','ACTIVE',1200000,0)")->execute();
    $ins = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, inspector_id, mandays, invoice_value, closed_flag)
                   VALUES ('JOB-REPRO',?,?,1,100000,0)")->execute([$off, $ins]);
    $jid = (int)$pdo->lastInsertId();
    $fetch = fn() => ops_one("SELECT * FROM jobs WHERE id=?", [$jid]);

    // Live profit before freezing.
    $p1 = job_profit($fetch());
    t_ok($p1['labour'] > 0 && $p1['overhead'] > 0, 'a live job computes labour + overhead from current rates');

    // Freeze the basis, then confirm the frozen profit equals the live value at freeze time.
    t_ok(job_cost_snapshot($jid) === true, 'job_cost_snapshot freezes the basis');
    $frozen = $fetch();
    t_ok(trim((string)$frozen['cost_basis_at']) !== '', 'the job now carries a frozen cost basis');
    $p2 = job_profit($frozen);
    t_eq(round($p2['labour'], 2),      round($p1['labour'], 2),      'freezing changes no displayed labour (equals the live value)');
    t_eq(round($p2['overhead'], 2),    round($p1['overhead'], 2),    'freezing changes no displayed overhead');
    t_eq(round($p2['contingency'], 2), round($p1['contingency'], 2), 'freezing changes no displayed contingency');

    // Now CHANGE the rates that used to make profit drift.
    $pdo->prepare("UPDATE inspectors SET salary_ctc=2400000 WHERE id=?")->execute([$ins]);       // doubled salary
    $pdo->prepare("UPDATE offices SET overhead_pct=50, contingency_pct=20 WHERE id=?")->execute([$off]); // 5x overhead

    // The FROZEN job is unaffected — reproducible.
    $p3 = job_profit($fetch());
    t_eq(round($p3['labour'], 2),      round($p1['labour'], 2),      'a frozen job\'s labour does NOT drift when salary changes');
    t_eq(round($p3['overhead'], 2),    round($p1['overhead'], 2),    'a frozen job\'s overhead does NOT drift when the office % changes');
    t_eq(round($p3['contingency'], 2), round($p1['contingency'], 2), 'a frozen job\'s contingency does NOT drift');
    t_eq(round($p3['profit'], 2),      round($p1['profit'], 2),      'a frozen job\'s PROFIT is fully reproducible');

    // A live (un-frozen) copy of the SAME job DOES drift with the new rates — proving the engine
    // still works and the snapshot is what confers reproducibility.
    $live = $fetch(); $live['cost_basis_at'] = ''; $live['cost_daily_base'] = 0; $live['cost_oh_pct'] = 0; $live['cost_contingency_pct'] = 0;
    $pLive = job_profit($live);
    t_ok(round($pLive['labour'], 2) > round($p1['labour'], 2), 'an un-frozen job DOES recompute (drifts) when salary rises — the bug the snapshot fixes');

    // Backfill freezes an already-closed job that had no basis, at its current value.
    $pdo->prepare("INSERT INTO jobs (job_code, executing_office_id, inspector_id, mandays, invoice_value, closed_flag)
                   VALUES ('JOB-BF',?,?,2,50000,1)")->execute([$off, $ins]);
    $bfId = (int)$pdo->lastInsertId();
    $bfLiveBefore = job_profit(ops_one("SELECT * FROM jobs WHERE id=?", [$bfId]));
    $n = jobs_backfill_cost_basis();
    t_ok($n >= 1, 'the nightly backfill freezes closed jobs lacking a basis');
    $bfAfter = ops_one("SELECT * FROM jobs WHERE id=?", [$bfId]);
    t_ok(trim((string)$bfAfter['cost_basis_at']) !== '', 'the backfilled job is now frozen');
    t_eq(round(job_profit($bfAfter)['labour'], 2), round($bfLiveBefore['labour'], 2), 'backfill freezes at the current value — no number changes today');

    // Idempotent: re-snapshot leaves a frozen job alone.
    t_ok(job_cost_snapshot($jid) === false, 'a job already frozen is not re-frozen (idempotent)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
