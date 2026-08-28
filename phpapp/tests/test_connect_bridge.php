<?php
// Connect — the award → engagement → invoice bridge. Asserts an AWARDED
// requirement can be turned into a PENDING billable event in the EXISTING P4
// ledger (reusing the invoicing engine), idempotently, with the right figure —
// and that an un-awarded requirement cannot.
t_section('connect award→invoice bridge');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('Bridgestone Ltd',1,'ACTIVE')")->execute();
    $client = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspectors (name,status,created_at) VALUES ('Bridge Pro','ACTIVE',?)")->execute([date('c')]);
    $pro = (int)db()->lastInsertId();

    // A requirement with 2 positions and a day-rate work type.
    $rid = cx_requirement_create(['title' => 'Shutdown UT crew', 'poster_party_id' => $client, 'positions' => 2, 'work_type' => 'shutdown'], true);

    // Not awarded yet → no billable.
    t_eq(0, connect_engagement_billable($rid), 'an un-awarded requirement cannot be sent to billing');

    // Award it to the pro at a bid of 4000/day.
    $a = cx_application_add($rid, ['inspector_id' => $pro, 'proposed_rate' => 4000]);
    cx_application_transition($a, 'SHORTLISTED');
    cx_requirement_transition($rid, 'SHORTLISTING');
    cx_requirement_award($rid, $a);

    // Send to billing → a PENDING billable event in the existing ledger.
    $ev = connect_engagement_billable($rid);
    t_ok($ev > 0, 'an awarded engagement becomes a billable event');
    $row = ops_one("SELECT * FROM billable_events WHERE id=?", [$ev]);
    t_eq('connect', $row['source_module'], 'it is recorded under the connect source');
    t_eq('MARKETPLACE_AWARD', $row['source_kind'], 'with the marketplace-award kind');
    t_eq('PENDING', $row['status'], 'it starts PENDING for finance to approve/invoice');
    t_eq(8000.0, (float)$row['amount'], '2 positions × ₹4000 = ₹8000 billable');
    t_eq($client, (int)$row['party_id'], 'the client to bill is carried through');

    // Idempotent — sending again returns the same event, no duplicate.
    $ev2 = connect_engagement_billable($rid);
    t_eq($ev, $ev2, 'sending to billing again is idempotent (no duplicate event)');
    t_eq(1, (int)ops_val("SELECT COUNT(*) FROM billable_events WHERE source_module='connect' AND source_id=?", [$rid]), 'exactly one billable event exists for the engagement');

    // The reader can find it.
    t_ok(connect_engagement_billable_row($rid) !== null, 'the engagement billable row is retrievable');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
