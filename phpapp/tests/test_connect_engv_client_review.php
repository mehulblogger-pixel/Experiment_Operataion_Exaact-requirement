<?php
// ============================================================================
//  Connect K21 — the client reviews a voucher on a job THEY posted.
//
//  The marketplace is a matchmaker: the professional claims their fee + actual
//  expenses (with receipts) after the inspection, and the CLIENT who posted the
//  job reviews it — returning it to the professional for clarification, or
//  approving it. Ownership is by the voucher's poster_party_id. A returned
//  voucher carries the client's note and the professional can reopen it to
//  revise. (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('connect voucher client review loop (K21)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_engv_migrate();
    $clientA = 55001;   // the client who posted the job
    $clientB = 55002;   // a different client — must not touch A's voucher

    // A booked EXCLUSIVE engagement posted by client A + a submitted voucher.
    $now = date('c');
    db()->prepare("INSERT INTO cx_engagements
        (requirement_id,subject_kind,subject_id,subject_name,poster_party_id,poster_name,basis,rate,rate_unit,quantity,rate_inclusive,voucher_cadence,status,created_at,updated_at)
        VALUES (0,'professional',8001,'Ravi P',?, 'Client A','MAN_DAYS',4000,'day',3,'EXCLUSIVE','PER_DAY','BOOKED',?,?)")
        ->execute([$clientA, $now, $now]);
    $engId = (int)db()->lastInsertId();
    [, , $vid] = connect_engv_open_for_engagement($engId, ['period_label' => 'REV-1']);
    connect_engv_add_line($vid, ['work_date' => '2026-08-12', 'units' => 1, 'travel' => 900]);

    // ---- ownership ---------------------------------------------------------
    $v = connect_engv_get($vid);
    t_ok(connect_engv_owned_by_party($v, $clientA) === true, 'the posting client owns the voucher');
    t_ok(connect_engv_owned_by_party($v, $clientB) === false, 'a different client does NOT own it');
    t_ok(connect_engv_owned_by_party($v, 0) === false, 'a blank party never owns a voucher');
    $mine = connect_engv_for_poster_party($clientA);
    t_ok(count($mine) === 1 && (int)$mine[0]['id'] === (int)$vid, 'the client lists only its own posted-job vouchers');
    t_eq(count(connect_engv_for_poster_party($clientB)), 0, 'the other client sees none');

    // ---- the professional submits; the client returns it for clarification --
    connect_engv_set_status($vid, 'SUBMITTED');
    t_eq(strtoupper((string)connect_engv_get($vid)['status']), 'SUBMITTED', 'voucher is awaiting review');
    [$rok] = connect_engv_set_status($vid, 'REJECTED', 'Client · Client A', 'Please attach the cab bill for 12 Aug.');
    t_ok($rok, 'the client returns the voucher for clarification');
    $vr = connect_engv_get($vid);
    t_eq(strtoupper((string)$vr['status']), 'REJECTED', 'voucher is now returned');
    t_eq((string)$vr['decided_note'], 'Please attach the cab bill for 12 Aug.', 'the clarification note is recorded');
    t_eq((string)$vr['decided_by'], 'Client · Client A', 'the returning client is recorded');

    // ---- the professional reopens to revise (note clears), resubmits --------
    [$rok2] = connect_engv_set_status($vid, 'DRAFT');
    t_ok($rok2, 'the professional reopens the returned voucher to revise it');
    t_eq((string)connect_engv_get($vid)['decided_note'], '', 'reopening clears the prior clarification note');
    connect_engv_add_line($vid, ['work_date' => '2026-08-13', 'units' => 1, 'conveyance' => 300]);
    connect_engv_set_status($vid, 'SUBMITTED');

    // ---- the client approves ----------------------------------------------
    [$aok] = connect_engv_set_status($vid, 'APPROVED', 'Client · Client A');
    t_ok($aok, 'the client approves the resubmitted voucher');
    $va = connect_engv_get($vid);
    t_eq(strtoupper((string)$va['status']), 'APPROVED', 'voucher is approved');
    t_eq((string)$va['decided_by'], 'Client · Client A', 'the approving client is recorded');
    // fee = 2 days × 4000; expenses = 900 + 300
    t_eq((float)$va['fee_total'], 8000.0, 'fee reflects both days');
    t_eq((float)$va['reimb_total'], 1200.0, 'expenses reflect both receipts');
    t_eq((float)$va['grand_total'], 9200.0, 'grand total = fee + expenses');

} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
