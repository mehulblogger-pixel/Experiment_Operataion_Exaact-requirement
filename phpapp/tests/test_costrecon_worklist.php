<?php
// Revamp P8 — sub-contractor cost reconciliation worklist (read-only). The cost-side
// twin of the revenue reconciliation worklist (§29). A job's sub-contractor cost is
// written twice — the legacy figure on the job (jobs.subcon_cost) and the SUBCON row a
// committed cost run allocates to the ledger (cost_allocations). This surfaces where the
// two copies disagree, so finance can drive it to green. Changes no figure.
t_section('cost reconciliation worklist (Revamp P8)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('CR Co',1,'ACTIVE')")->execute();
    $cid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (client_id, call_code, created_at) VALUES (?,?,?)")->execute([$cid, 'C-CR', date('c')]);
    $callId = (int)db()->lastInsertId();

    $mkJob = function ($code, $subcon) use ($callId) {
        db()->prepare("INSERT INTO jobs (call_id, job_code, sbu, closed_flag, subcon_cost, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$callId, $code, 'IND', 1, $subcon, date('c')]);
        return (int)db()->lastInsertId();
    };
    // A committed ledger SUBCON allocation for a job (mimics costing_run commit).
    $mkLedger = function ($jobId, $amount) {
        db()->prepare("INSERT INTO cost_allocations (yr,mon,office_id,sbu,source_kind,source_id,source_label,basis,amount,created_at)
                       VALUES (2026,8,1,'IND','SUBCON',?,'sub','THE_JOB_IT_WAS_FOR',?,?)")
            ->execute([$jobId, $amount, date('c')]);
        // the SUBCON row is attributed to its job
        db()->prepare("UPDATE cost_allocations SET job_id=? WHERE source_kind='SUBCON' AND source_id=? AND job_id IS NULL")
            ->execute([$jobId, $jobId]);
    };

    // A: job subcon 1000, ledger committed 1000 → reconciles.
    $ok = $mkJob('J-CR-OK', 1000); $mkLedger($ok, 1000);
    // B: job subcon 5000, but the ledger committed 2000 (job edited after the run) → diverges.
    $bad = $mkJob('J-CR-BAD', 5000); $mkLedger($bad, 2000);
    // C: job subcon 800, no cost run ever committed → legacy-only divergence.
    $legOnly = $mkJob('J-CR-LEGONLY', 800);
    // D: no job figure, but a ledger SUBCON row survives → ledger-only divergence.
    $ledOnly = $mkJob('J-CR-LEDONLY', 0); $mkLedger($ledOnly, 650);

    $cj_ok  = costrecon_job($ok);
    t_ok($cj_ok['diverges'] === false, 'a job whose subcon cost matches the committed ledger reconciles');
    $cj_bad = costrecon_job($bad);
    t_ok($cj_bad['diverges'] === true, 'a job whose figure disagrees with the ledger diverges');
    $cj_leg = costrecon_job($legOnly);
    t_ok($cj_leg['diverges'] === true && $cj_leg['legacy_only'] === true, 'a subcon cost with no committed run diverges (legacy-only)');
    $cj_led = costrecon_job($ledOnly);
    t_ok($cj_led['diverges'] === true && $cj_led['ledger_only'] === true, 'a committed ledger row with no job figure diverges (ledger-only)');

    $sum = costrecon_summary();
    t_ok($sum['candidates'] >= 4, 'the summary considers all candidate jobs');
    t_ok($sum['reconciled'] >= 1, 'the reconciled count includes the matching job');
    t_ok($sum['diverging'] >= 3, 'the diverging count includes all three mismatches');
    t_ok($sum['green'] === false, 'green is false while divergences exist');

    $list = costrecon_list(200);
    $codes = array_column($list, 'job_code');
    t_ok(in_array('J-CR-BAD', $codes, true) && in_array('J-CR-LEGONLY', $codes, true) && in_array('J-CR-LEDONLY', $codes, true),
        'the worklist lists every diverging job');
    t_ok(!in_array('J-CR-OK', $codes, true), 'a reconciled job is not on the worklist');
    $badRow = null; foreach ($list as $r) if ($r['job_code'] === 'J-CR-BAD') $badRow = $r;
    t_ok($badRow && $badRow['client_name'] === 'CR Co' && $badRow['reason'] !== '', 'worklist rows carry client + a plain-language reason');

    // It is READ-ONLY: running the detector changed no job figure and no ledger row.
    t_eq((float)ops_val("SELECT subcon_cost FROM jobs WHERE id=?", [$bad]), 5000.0, 'the detector leaves the job figure untouched');
    t_eq((float)ops_val("SELECT COALESCE(SUM(amount),0) FROM cost_allocations WHERE job_id=? AND source_kind='SUBCON'", [$bad]), 2000.0,
        'the detector leaves the ledger untouched');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
