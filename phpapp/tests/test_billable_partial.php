<?php
// Gap-7 (EXTEND) — partial / progress billing. A billable event was all-or-nothing (one whole
// amount, one BILLED). billable_bill_partial() records milestone bills against an approved event,
// accumulating billed_amount and logging each bill; the event flips to BILLED only once the
// cumulative reaches the amount. Additive: an event never part-billed behaves exactly as before.
t_section('partial / progress billing (Gap 7)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    billable_migrate();
    $seq = 770000;
    $mk = function ($amount, $status = 'APPROVED', $module = 'timesheet') use (&$seq) {
        $sid = ++$seq;
        db()->prepare("INSERT INTO billable_events (source_module, source_kind, source_id, party_id, amount, derived_amount, status, created_at)
                       VALUES (?,?,?,?,?,?,?,?)")->execute([$module, 'TIMESHEET_APPROVED', $sid, 1, $amount, $amount, $status, date('c')]);
        return (int)db()->lastInsertId();
    };

    // remaining helper
    $e = ops_one("SELECT * FROM billable_events WHERE id=?", [$mk(1000)]);
    t_eq(billable_remaining($e), 1000.0, 'a fresh approved event has its whole amount remaining');

    // first milestone: 400 of 1000 → still APPROVED, 600 remaining, logged
    $id = $mk(1000);
    [$ok1, $m1] = billable_bill_partial($id, 400, 'INV-M1');
    t_ok($ok1 === true, 'a partial bill is recorded: ' . $m1);
    $row = ops_one("SELECT * FROM billable_events WHERE id=?", [$id]);
    t_eq(round((float)$row['billed_amount'], 2), 400.0, 'billed_amount accumulates the milestone');
    t_eq((string)$row['status'], 'APPROVED', 'the event stays APPROVED while part-billed');
    t_eq(billable_remaining($row), 600.0, 'the remaining is the balance');
    t_eq(count(billable_bills_for($id)), 1, 'the milestone bill is logged');

    // over-bill guard: cannot bill more than the remaining
    t_ok(billable_bill_partial($id, 900, 'INV-X')[0] === false, 'billing more than the remaining is refused');

    // final milestone: 600 → fully billed, status BILLED
    [$ok2] = billable_bill_partial($id, 600, 'INV-M2');
    $row = ops_one("SELECT * FROM billable_events WHERE id=?", [$id]);
    t_ok($ok2 === true, 'the final milestone is recorded');
    t_eq(round((float)$row['billed_amount'], 2), 1000.0, 'billed_amount reaches the full amount');
    t_eq((string)$row['status'], 'BILLED', 'the event is BILLED once fully billed');
    t_eq(count(billable_bills_for($id)), 2, 'both milestone bills are logged');

    // guards: a non-approved event and a job-source event are refused
    t_ok(billable_bill_partial($mk(500, 'PENDING'), 100, 'INV-P')[0] === false, 'a pending event cannot be part-billed');
    t_ok(billable_bill_partial($mk(500, 'APPROVED', 'job'), 100, 'INV-J')[0] === false, 'a job-source event reconciles via its books invoice, not attestation');
    t_ok(billable_bill_partial($id, 0, 'INV-Z')[0] === false, 'a zero/blank bill is refused');

    // the full-bill path stays consistent: mark_billed settles billed_amount + logs the bill
    $fid = $mk(750);
    t_ok(billable_mark_billed($fid, 'INV-FULL') === true, 'the full-bill path still works');
    $frow = ops_one("SELECT * FROM billable_events WHERE id=?", [$fid]);
    t_eq(round((float)$frow['billed_amount'], 2), 750.0, 'a full bill settles billed_amount to the whole amount');
    t_eq((string)$frow['status'], 'BILLED', 'a full bill marks the event BILLED');
    t_eq(count(billable_bills_for($fid)), 1, 'the full bill is logged too');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
