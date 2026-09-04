<?php
// Engagement-entity parity (revamp §3). The first-class Engagement (engagements +
// a nullable engagement_id) dual-reads with the contract_number STRING. Before a
// reader switches to the id, parity must hold: every threaded record carries a
// matching engagement_id. This guards the backfill → parity gate.
t_section('engagement entity ⇄ contract_number parity');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    engagement_migrate();
    $cn = 'PARITY-TEST-0001';

    // two records threaded by the same contract_number, no engagement_id yet
    db()->prepare("INSERT INTO calls (call_code,contract_number,status,created_by,created_at) VALUES ('PC-1',?, 'OPEN','t',?)")->execute([$cn, date('c')]);
    db()->prepare("INSERT INTO jobs (job_code,contract_number,sbu,created_at) VALUES ('PJ-1',?, 'IND', ?)")->execute([$cn, date('c')]);

    // before backfill they are unstamped for this contract
    $before = engagement_parity();
    $unstamped0 = $before['by_table']['calls']['unstamped'] + $before['by_table']['jobs']['unstamped'];
    t_ok($unstamped0 >= 2, 'new threaded records start unstamped (engagement_id missing)');

    // backfill creates the engagement and stamps the records
    $bf = engagement_backfill();
    t_ok($bf['stamped'] >= 2, 'backfill stamps the threaded records');
    $eid = engagement_id_for($cn);
    t_ok($eid > 0, 'an engagement now exists for the contract_number');
    t_eq((int)ops_val("SELECT engagement_id FROM calls WHERE call_code='PC-1'"), $eid, 'the call carries the engagement_id');
    t_eq((int)ops_val("SELECT engagement_id FROM jobs WHERE job_code='PJ-1'"), $eid, 'the job carries the same engagement_id (one engagement)');

    // dual-read: the id resolves back to the same spine as the string
    $byId = engagement_by_id($eid);
    t_ok($byId !== null && $byId['contract_number'] === $cn, 'engagement_by_id resolves to the same contract_number spine');

    // parity holds for these records now (no unstamped/mismatched among them)
    $after = engagement_parity();
    t_eq($after['by_table']['calls']['mismatched'], 0, 'no mismatched calls after backfill');
    t_eq($after['by_table']['jobs']['mismatched'], 0, 'no mismatched jobs after backfill');

    // a deliberately WRONG engagement_id is caught as a mismatch
    db()->prepare("INSERT INTO engagements (engagement_key,partner_id,opened_at,created_at) VALUES ('OTHER-KEY',0,?,?)")->execute([date('Y-m-d'), date('c')]);
    $other = (int)ops_val("SELECT id FROM engagements WHERE engagement_key='OTHER-KEY'");
    db()->prepare("UPDATE jobs SET engagement_id=? WHERE job_code='PJ-1'")->execute([$other]);
    t_ok(engagement_parity()['by_table']['jobs']['mismatched'] >= 1, 'a job pointing at the wrong engagement is flagged mismatched');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
