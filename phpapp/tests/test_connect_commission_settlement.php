<?php
// ============================================================================
//  Connect K21 — platform commission + settlement + report release.
//
//  The platform is a matchmaker: it takes a nominal commission on the FEE only
//  (not on reimbursed expenses), split 50/50 between client and professional,
//  and takes no responsibility for the settlement — so BOTH sides confirm
//  payment, and only then is the transaction "cleared", which releases the
//  inspection report to the client. (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('connect commission + settlement + report gate (K21)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_engv_migrate();

    $mkEng = function ($model = 'EXCLUSIVE') {
        $now = date('c');
        db()->prepare("INSERT INTO cx_engagements
            (requirement_id,subject_kind,subject_id,subject_name,poster_party_id,poster_name,basis,rate,rate_unit,quantity,rate_inclusive,voucher_cadence,status,created_at,updated_at)
            VALUES (0,'professional',9101,'Comm Test',44001,'Client C','MAN_DAYS',4000,'day',2,?, 'PER_DAY','BOOKED',?,?)")
            ->execute([$model, $now, $now]);
        return (int)db()->lastInsertId();
    };

    // ---- commission math: fee only, split 50/50 ----------------------------
    if (function_exists('setting_set')) setting_set('connect_commission_pct', 5);
    t_eq(connect_commission_pct(), 5.0, 'commission rate reads from the setting');

    $eng = $mkEng('EXCLUSIVE');
    [, , $vid] = connect_engv_open_for_engagement($eng, ['period_label' => 'COMM-1']);
    connect_engv_add_line($vid, ['work_date' => '2026-08-01', 'units' => 1, 'travel' => 1000]);
    connect_engv_add_line($vid, ['work_date' => '2026-08-02', 'units' => 1, 'lodging' => 2000]);
    $m = connect_engv_money(connect_engv_get($vid));
    t_eq($m['fee'], 8000.0, 'fee = 2 days × 4000');
    t_eq($m['reimb'], 3000.0, 'expenses = 1000 + 2000');
    t_eq($m['grand'], 11000.0, 'grand = fee + expenses');
    t_eq($m['commission'], 400.0, 'commission = 5% of the FEE only (not expenses)');
    t_eq($m['commission_client'], 200.0, 'client half of the commission');
    t_eq($m['commission_pro'], 200.0, 'professional half of the commission');
    t_eq($m['client_payable'], 11200.0, 'client pays grand + its commission half');
    t_eq($m['pro_net'], 10800.0, 'professional nets grand − its commission half');

    // ---- rate is configurable ---------------------------------------------
    if (function_exists('setting_set')) setting_set('connect_commission_pct', 10);
    connect_engv_recompute($vid);
    t_eq(connect_engv_money(connect_engv_get($vid))['commission'], 800.0, 'changing the rate to 10% recomputes the commission');
    if (function_exists('setting_set')) setting_set('connect_commission_pct', 5);
    connect_engv_recompute($vid);

    // ---- settlement needs BOTH sides, only after approval ------------------
    [$c0] = connect_engv_confirm($vid, 'client');
    t_ok($c0 === false, 'payment cannot be confirmed before the voucher is approved');
    connect_engv_set_status($vid, 'SUBMITTED');
    connect_engv_set_status($vid, 'APPROVED', 'Client C');
    t_ok(connect_engv_is_settled(connect_engv_get($vid)) === false, 'not settled on approval alone');
    t_ok(connect_engv_engagement_cleared($eng) === false, 'engagement not cleared yet');

    [$c1] = connect_engv_confirm($vid, 'client', 'Client C');
    t_ok($c1, 'the client confirms it has paid');
    t_ok(connect_engv_is_settled(connect_engv_get($vid)) === false, 'one side alone is not settled');
    t_ok(connect_engv_engagement_cleared($eng) === false, 'still not cleared with one confirmation');

    [$c2] = connect_engv_confirm($vid, 'pro', 'Comm Test');
    t_ok($c2, 'the professional confirms receipt');
    $vs = connect_engv_get($vid);
    t_ok(connect_engv_is_settled($vs) === true, 'both confirmations → settled');
    t_ok(trim((string)$vs['settled_at']) !== '', 'settled_at is stamped');
    t_eq(strtoupper((string)$vs['status']), 'PAID', 'a fully settled voucher moves to PAID');
    t_ok(connect_engv_engagement_cleared($eng) === true, 'the engagement is now cleared');

    // ---- report deliverable + gate ----------------------------------------
    // (No real HTTP upload under CLI — guard the add, store a row directly to
    //  test list/serve/delete, and prove the release gate is the clearance.)
    [$r0, $rm0] = connect_engv_report_add($eng, null);
    t_eq($rm0, 'Choose a file to upload.', 'the report upload asks for a file');
    db()->prepare("INSERT INTO cx_engagement_reports (engagement_id,requirement_id,poster_party_id,subject_kind,subject_id,title,file_name,mime,size,file_data,created_at)
                   VALUES (?,0,44001,'professional',9101,'Inspection report','ndt-report.pdf','application/pdf',12,?,?)")
        ->execute([$eng, base64_encode('PDFBYTES----'), date('c')]);
    $reps = connect_engv_reports($eng);
    t_eq(count($reps), 1, 'the report deliverable is listed');
    t_ok(!isset($reps[0]['file_data']), 'the report list carries metadata only');
    $rr = connect_engv_report_row((int)$reps[0]['id']);
    t_ok($rr && base64_decode((string)$rr['file_data']) === 'PDFBYTES----', 'the report row serves the exact bytes');

    // The gate: a client may download the report only when the engagement is
    // cleared. Here it IS cleared, so the gate is open; make a fresh un-cleared
    // engagement to prove it stays shut.
    t_ok(connect_engv_engagement_cleared($eng) === true, 'report release gate is OPEN once cleared');
    $eng2 = $mkEng('INCLUSIVE');
    [, , $vid2] = connect_engv_open_for_engagement($eng2, ['period_label' => 'COMM-2']);
    connect_engv_add_line($vid2, ['work_date' => '2026-08-05', 'units' => 1]);
    connect_engv_set_status($vid2, 'SUBMITTED');
    connect_engv_set_status($vid2, 'APPROVED', 'Client C');
    connect_engv_confirm($vid2, 'client', 'Client C');   // only one side
    t_ok(connect_engv_engagement_cleared($eng2) === false, 'report release gate stays SHUT until both confirm');

    connect_engv_report_delete((int)$reps[0]['id'], $eng);
    t_eq(count(connect_engv_reports($eng)), 0, 'a report deliverable can be removed');

} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
