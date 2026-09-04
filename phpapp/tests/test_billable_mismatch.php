<?php
// Billing-mismatch flag (Stage 7). When a billed event's invoiced amount drifts
// from what the work EARNED (derived_amount), the drift is surfaced, not erased.
// The books ledger stays the money truth; this only flags for reconciliation.
t_section('billing mismatch flag');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    billable_migrate();

    // derivation preserves the earned amount
    $id = billable_event_upsert('timesheet', 'TIMESHEET_APPROVED', 900001, [
        'party_id' => 4242, 'service_type' => 'Inspection', 'qty' => 10, 'rate' => 1000, 'amount' => 10000, 'calc_rule' => 'test',
    ]);
    t_ok($id > 0, 'a billable event is derived');
    t_eq((float)ops_val("SELECT derived_amount FROM billable_events WHERE id=?", [$id]), 10000.0, 'derivation stores the earned amount');
    // check only THIS row (the global count may include other tests' real drifts)
    $mine = function () use ($id) { foreach (billable_mismatch() as $r) if ((int)$r['id'] === $id) return $r; return null; };

    // no mismatch while nothing is billed
    t_ok($mine() === null, 'nothing billed → not flagged');

    // it gets billed at the SAME amount → still no mismatch
    db()->prepare("UPDATE billable_events SET status='BILLED', amount=10000 WHERE id=?")->execute([$id]);
    t_ok($mine() === null, 'billed at the earned amount → not flagged');

    // the invoice reconciles it to a DIFFERENT amount (the drift the sync would erase)
    db()->prepare("UPDATE billable_events SET amount=8500 WHERE id=?")->execute([$id]);
    $found = $mine();
    t_ok($found !== null, 'a billed event whose invoice differs is flagged');
    t_eq($found['earned'], 10000.0, 'the earned amount is preserved for comparison');
    t_eq($found['billed'], 8500.0, 'the billed amount is what the invoice carries');
    t_eq($found['variance'], -1500.0, 'the variance is billed − earned');

    // within the rounding tolerance → not flagged
    db()->prepare("UPDATE billable_events SET amount=10000.5 WHERE id=?")->execute([$id]);
    t_ok($mine() === null, 'a sub-tolerance rounding difference is not a mismatch');

    // a CANCELLED/PENDING event is never a mismatch (only BILLED)
    db()->prepare("UPDATE billable_events SET status='CANCELLED', amount=8500 WHERE id=?")->execute([$id]);
    t_ok($mine() === null, 'only BILLED events are checked for drift');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
