<?php
// §28 (P10) — the cost reader switch. The twin of the revenue reader switch: one setting
// decides which figure the cost readers (the one job_profit engine) use for a job's
// sub-contractor cost as the committed cost ledger becomes the source of truth. The safety
// contract is identical: the DEFAULT ('reconciled') moves no figure that has not been proven
// equal, and the legacy field (jobs.subcon_cost) is never destroyed.
t_section('cost reader switch (§28 / P10)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $mode0 = cost_reader_mode();  // restore later

    db()->prepare("INSERT INTO calls (call_code, created_at) VALUES ('C-CRS', ?)")->execute([date('c')]);
    $callId = (int)db()->lastInsertId();
    $mkJob = function ($code, $subcon) use ($callId) {
        db()->prepare("INSERT INTO jobs (call_id, job_code, sbu, closed_flag, subcon_cost, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$callId, $code, 'IND', 1, $subcon, date('c')]);
        return (int)db()->lastInsertId();
    };
    $mkLedger = function ($jobId, $amount) {
        db()->prepare("INSERT INTO cost_allocations (yr,mon,office_id,sbu,source_kind,source_id,job_id,source_label,basis,amount,created_at)
                       VALUES (2026,8,1,'IND','SUBCON',?,?,'sub','THE_JOB_IT_WAS_FOR',?,?)")
            ->execute([$jobId, $jobId, $amount, date('c')]);
    };

    // reconciled job (field 1000 == committed 1000), diverging (3000 vs 1200), legacy-only (700, no run)
    $ok  = $mkJob('J-CRS-OK', 1000);  $mkLedger($ok, 1000);
    $bad = $mkJob('J-CRS-BAD', 3000); $mkLedger($bad, 1200);
    $leg = $mkJob('J-CRS-LEG', 700);
    costrecon_ledger_all(true);  // refresh the request cache after seeding the ledger

    $rowOk  = ops_one("SELECT * FROM jobs WHERE id=?", [$ok]);
    $rowBad = ops_one("SELECT * FROM jobs WHERE id=?", [$bad]);
    $rowLeg = ops_one("SELECT * FROM jobs WHERE id=?", [$leg]);

    cost_reader_set_mode('reconciled');
    t_eq(cost_reader_mode(), 'reconciled', 'the default cost reader mode is reconciled');
    t_eq(job_subcon_cost($rowOk),  1000.0, 'reconciled: a reconciled job shows the (equal) figure');
    t_eq(job_subcon_cost($rowBad), 3000.0, 'reconciled: a DIVERGING job keeps its legacy field (no unproven change)');
    t_eq(job_subcon_cost($rowLeg),  700.0, 'reconciled: a legacy-only job keeps its field');

    cost_reader_set_mode('legacy');
    t_eq(job_subcon_cost($rowBad), 3000.0, 'legacy: always the field');
    t_eq(job_subcon_cost($rowOk),  1000.0, 'legacy: always the field (reconciled job)');

    cost_reader_set_mode('ledger');
    t_eq(job_subcon_cost($rowBad), 1200.0, 'ledger: a diverging job shows the committed cost-run figure');
    t_eq(job_subcon_cost($rowOk),  1000.0, 'ledger: a reconciled job is unchanged');
    t_eq(job_subcon_cost($rowLeg),  700.0, 'ledger: a job never cost-run keeps its field (cost never zeroed)');

    // the request-cached map returns the committed figure; the precomputed path agrees
    $map = costrecon_ledger_all();
    t_eq($map[$bad], 1200.0, 'the request-cached ledger map carries the committed figure');
    t_eq(job_subcon_cost($rowBad, $map[$bad]), 1200.0, 'passing a precomputed ledger figure matches the cached read');

    // job_profit's subcon reflects the mode (the whole point: the profit engine moves)
    cost_reader_set_mode('legacy');
    $pLegacy = job_profit($rowBad);
    cost_reader_set_mode('ledger');
    $pLedger = job_profit($rowBad);
    t_eq(round($pLegacy['subcon'], 2), 3000.0, 'job_profit uses the legacy field under legacy mode');
    t_eq(round($pLedger['subcon'], 2), 1200.0, 'job_profit uses the committed ledger under ledger mode');

    // invalid mode rejected; field never mutated
    t_ok(cost_reader_set_mode('nope') === false, 'an invalid mode is rejected');
    cost_reader_set_mode('ledger');
    t_eq((float)ops_val("SELECT subcon_cost FROM jobs WHERE id=?", [$bad]), 3000.0, 'the legacy field is never destroyed');

    cost_reader_set_mode($mode0);  // restore
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    if (function_exists('costrecon_ledger_all')) costrecon_ledger_all(true);  // drop test rows from the cache
}
