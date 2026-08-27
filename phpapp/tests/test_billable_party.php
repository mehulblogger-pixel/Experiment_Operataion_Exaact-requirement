<?php
// Revamp P4 — per-client unbilled figure for Customer-360. Read-only over the
// billable ledger; counts PENDING + APPROVED (not yet invoiced) candidates for
// one client. Additive, non-destructive.
t_section('billable per-client rollup (Customer-360)');

billable_migrate();
t_eq(billable_party_rollup(0), ['pending' => 0, 'approved' => 0, 'unbilled_amt' => 0.0], 'a zero party id returns an empty rollup');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('BP Co',1,'ACTIVE')")->execute();
    $a = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('Other Co',1,'ACTIVE')")->execute();
    $b = (int)db()->lastInsertId();

    // Two pending, one approved for A (all unbilled); a billed one for A must NOT count.
    billable_event_upsert('job', 'JOB_CLOSED', 9001, ['party_id' => $a, 'amount' => 1000]);
    billable_event_upsert('job', 'JOB_CLOSED', 9002, ['party_id' => $a, 'amount' => 2000]);
    $ap = billable_event_upsert('job', 'JOB_CLOSED', 9003, ['party_id' => $a, 'amount' => 3000]);
    billable_set_status($ap, 'APPROVED');
    $billed = billable_event_upsert('job', 'JOB_CLOSED', 9004, ['party_id' => $a, 'amount' => 5000]);
    db()->prepare("UPDATE billable_events SET status='BILLED' WHERE id=?")->execute([$billed]);   // simulate reconciliation
    // One for the other client — must not bleed into A's figure.
    billable_event_upsert('job', 'JOB_CLOSED', 9005, ['party_id' => $b, 'amount' => 7000]);

    $r = billable_party_rollup($a);
    t_eq($r['pending'], 2, 'client A has two pending candidates');
    t_eq($r['approved'], 1, 'client A has one approved candidate');
    t_eq($r['unbilled_amt'], 6000.0, 'unbilled = pending + approved amounts (billed excluded)');

    $rb = billable_party_rollup($b);
    t_eq($rb['unbilled_amt'], 7000.0, "another client's figure is independent");
    t_eq($rb['pending'], 1, "the other client's own count is right");
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
