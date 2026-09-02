<?php
// DEMO-S05 — convergence & reconciliation seed. One namespaced thread that lights up
// the read-only dual-truth detectors (P9 revenue, P10 cost, P11 candidate pool) with
// live drifting data alongside reconciled control rows. This guards that the seed's
// own derived dashboard stays 10/10 — i.e. each detector still flags the seeded drift
// and still leaves the matching control rows alone.
t_section('DEMO-S05 convergence seed dashboard');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $r = seed_s05_load();
    t_ok(is_array($r['dashboard']) && count($r['dashboard']) === 10, 'the seed derives a 10-point dashboard');
    $fails = array_values(array_filter($r['dashboard'], fn($d) => !$d[1]));
    t_ok($r['allpass'] === true, 'every DEMO-S05 detector check passes' . ($fails ? ' (failing: ' . implode('; ', array_column($fails, 0)) . ')' : ''));

    // Spot-check the underlying detectors directly against the seeded rows.
    $revBad = (int)ops_val("SELECT id FROM jobs WHERE job_code='DEMO-S05-REV-DRIFT'");
    $revOk  = (int)ops_val("SELECT id FROM jobs WHERE job_code='DEMO-S05-REV-OK'");
    t_ok(revrecon_job($revBad)['diverges'] === true, 'the revenue-drift job diverges');
    t_ok(revrecon_job($revOk)['diverges'] === false, 'the reconciled revenue control does not diverge');

    $costBad = (int)ops_val("SELECT id FROM jobs WHERE job_code='DEMO-S05-COST-DRIFT'");
    $costOk  = (int)ops_val("SELECT id FROM jobs WHERE job_code='DEMO-S05-COST-OK'");
    t_ok(costrecon_job($costBad)['diverges'] === true, 'the cost-drift job diverges');
    t_ok(costrecon_job($costOk)['diverges'] === false, 'the reconciled cost control does not diverge');

    // The candidate/professional overlap resolves across the leading-0 mobile difference.
    $cand = ops_one("SELECT * FROM candidates WHERE cand_code='DEMO-S05-CAND-1'");
    candpool_pro_index(true);
    $m = candpool_pro_matches($cand);
    t_ok(count($m) >= 1 && $m[0]['reason'] === 'mobile', 'the seeded candidate matches its marketplace twin by mobile');

    // Idempotent + clean removal.
    $removed = seed_s05_remove();
    t_ok($removed > 0, 'remove deletes the DEMO-S05 records');
    t_eq((int)ops_val("SELECT COUNT(*) FROM jobs WHERE job_code LIKE 'DEMO-S05-%'"), 0, 'no DEMO-S05 jobs remain after removal');
    t_eq((int)ops_val("SELECT COUNT(*) FROM candidates WHERE cand_code LIKE 'DEMO-S05-%'"), 0, 'no DEMO-S05 candidates remain after removal');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    if (function_exists('candpool_pro_index')) candpool_pro_index(true);
}
