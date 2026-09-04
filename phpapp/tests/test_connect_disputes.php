<?php
// Connect K9b — disputes & mediation. Asserts a concern can be raised and moved
// through its lifecycle with the guards, and — the point of M14 — that a FINDING
// dispute never touches the professional's fee.
t_section('connect disputes (K9b)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $rid = cx_requirement_create(['title' => 'Coating inspector — tank farm'], true);

    // Raise a process concern.
    $d1 = cx_dispute_raise($rid, ['raised_by_side' => 'CLIENT', 'category' => 'PROCESS', 'subject' => 'Report late', 'detail' => 'Two days over TAT']);
    t_ok($d1 > 0, 'a concern can be raised');
    t_eq('OPEN', cx_dispute_get($d1)['status'], 'a new concern starts OPEN');
    t_ok((int)cx_dispute_get($d1)['affects_fee'] === 1, 'a process concern can affect the fee');

    // A subject is required.
    t_eq(0, cx_dispute_raise($rid, ['category' => 'PROCESS', 'subject' => '']), 'a concern needs a subject');

    // Lifecycle + guards.
    t_ok(cx_dispute_transition($d1, 'WITHDRAWN'), 'OPEN → WITHDRAWN is allowed');
    $d2 = cx_dispute_raise($rid, ['category' => 'COMMERCIAL', 'subject' => 'Waiting charges']);
    t_ok(cx_dispute_transition($d2, 'UNDER_REVIEW'), 'OPEN → UNDER_REVIEW is allowed');
    t_ok(!cx_dispute_transition($d2, 'OPEN'), 'UNDER_REVIEW → OPEN is refused');
    t_ok(cx_dispute_transition($d2, 'RESOLVED', 'Waiting charges applied per terms'), 'UNDER_REVIEW → RESOLVED records the outcome');
    t_eq('Waiting charges applied per terms', (string)cx_dispute_get($d2)['resolution'], 'the resolution note is stored');
    t_ok(!cx_dispute_transition($d2, 'UNDER_REVIEW'), 'a RESOLVED concern is terminal');

    // The M14 rule: a FINDING dispute never withholds the fee.
    $d3 = cx_dispute_raise($rid, ['category' => 'FINDING', 'subject' => 'We disagree with the NCR']);
    t_ok($d3 > 0, 'a finding dispute can be raised');
    t_eq(0, (int)cx_dispute_get($d3)['affects_fee'], 'a FINDING dispute is fee-protected (never withholds the professional\'s fee)');
    t_ok(cx_dispute_affects_fee('COMMERCIAL') && !cx_dispute_affects_fee('FINDING'), 'only non-finding categories can bear on the fee');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
