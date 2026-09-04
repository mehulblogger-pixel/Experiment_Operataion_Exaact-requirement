<?php
// DEMO-S06 — gap-closure showcase. One namespaced thread that lights up all eight Stage-0
// residual gaps, each asserted from live seeded data. This guards that the seed's own derived
// 10-point dashboard stays all-pass — i.e. every gap closure still holds end to end.
t_section('DEMO-S06 gap-closure showcase dashboard');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $r = seed_s06_load();
    t_ok(is_array($r['dashboard']) && count($r['dashboard']) === 10, 'the seed derives a 10-point dashboard (one per gap check)');
    $fails = array_values(array_filter($r['dashboard'], fn($d) => !$d[1]));
    t_ok($r['allpass'] === true, 'every gap check passes' . ($fails ? ' (failing: ' . implode('; ', array_column($fails, 0)) . ')' : ''));

    // Spot-check a couple directly against the seeded rows (independent of the dashboard array).
    $dep = ops_one("SELECT contract_number, engagement_id FROM jobs WHERE job_code='DEMO-S06-DEP-1'");
    t_ok($dep && trim((string)$dep['contract_number']) !== '' && (int)$dep['engagement_id'] > 0, 'Gap 1 — the deployment job carries both spine keys');
    $cand = (int)ops_val("SELECT id FROM candidates WHERE cand_code='DEMO-S06-CAND'");
    $sum = connect_person_summary('candidate', $cand);
    t_ok($sum['linked'] === true && ($sum['pools']['inspector'] ?? 0) > 0 && ($sum['credentials'] ?? 0) >= 2, 'Gap 8 — the person resolves across pools with cross-pool credentials');

    // Idempotent + clean removal.
    $removed = seed_s06_remove();
    t_ok($removed > 0, 'remove deletes the DEMO-S06 records');
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_requirements WHERE poster_name='DEMO-S06'"), 0, 'no DEMO-S06 requirements remain after removal');
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE email LIKE '%s06pro@demo.test'"), 0, 'no DEMO-S06 professionals remain after removal');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
