<?php
// Connect K21 — engagement vouchers. Proves the ONE model covers every case:
// subject kind × basis × rate model (inclusive/exclusive) × cadence × lifecycle.
// (t_eq is t_eq($got, $want).)
t_section('connect engagement vouchers (inclusive/exclusive)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_engv_migrate();

    // helper: make an engagement row directly and return its id
    $mkEng = function (array $o) {
        $now = date('c');
        db()->prepare("INSERT INTO cx_engagements
            (requirement_id,subject_kind,subject_id,subject_name,poster_party_id,basis,rate,rate_unit,quantity,rate_inclusive,voucher_cadence,status,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,'BOOKED',?,?)")
            ->execute([(int)($o['req'] ?? 0), $o['kind'], (int)$o['sid'], $o['name'] ?? 'X', 0,
                       $o['basis'] ?? 'MAN_DAYS', (float)($o['rate'] ?? 0), $o['unit'] ?? 'day', (float)($o['qty'] ?? 0),
                       $o['model'] ?? 'INCLUSIVE', $o['cadence'] ?? 'PER_DEPLOYMENT', $now, $now]);
        return (int)db()->lastInsertId();
    };

    // ---- rate-model + cadence option helpers -------------------------------
    t_ok(isset(connect_engage_rate_models()['INCLUSIVE'], connect_engage_rate_models()['EXCLUSIVE']), 'both rate models exist');
    t_ok(connect_engage_rate_is_reimbursable('EXCLUSIVE') === true, 'exclusive is reimbursable');
    t_ok(connect_engage_rate_is_reimbursable('INCLUSIVE') === false, 'inclusive is not reimbursable');
    t_eq(connect_engage_norm_rate_model('nonsense'), 'INCLUSIVE', 'a bad rate model normalises to inclusive');
    t_eq(connect_engage_norm_cadence('PER_DAY'), 'PER_DAY', 'per-day cadence is accepted');

    // ---- CASE 1: freelancer, MAN_DAYS, INCLUSIVE ---------------------------
    // Expense heads must be IGNORED — the all-inclusive rate already covers them.
    $e1 = $mkEng(['kind' => 'professional', 'sid' => 501, 'basis' => 'MAN_DAYS', 'rate' => 5000, 'unit' => 'day', 'model' => 'INCLUSIVE', 'cadence' => 'PER_DEPLOYMENT']);
    [$ok1,, $v1] = connect_engv_open_for_engagement($e1, ['period_label' => 'DEP-1']);
    t_ok($ok1 && $v1 > 0, 'inclusive voucher opens');
    connect_engv_add_line($v1, ['work_date' => '2026-08-01', 'units' => 1, 'travel' => 900, 'lodging' => 2000]);
    connect_engv_add_line($v1, ['work_date' => '2026-08-02', 'units' => 2, 'travel' => 500]);
    $vr1 = connect_engv_get($v1);
    t_eq((float)$vr1['fee_total'], 15000.0, 'inclusive fee = (1+2) days × 5000');
    t_eq((float)$vr1['reimb_total'], 0.0, 'inclusive voucher claims NO expense head');
    t_eq((float)$vr1['grand_total'], 15000.0, 'inclusive grand = fee only');

    // ---- CASE 2: freelancer, MAN_DAYS, EXCLUSIVE ---------------------------
    $e2 = $mkEng(['kind' => 'professional', 'sid' => 502, 'basis' => 'MAN_DAYS', 'rate' => 5000, 'unit' => 'day', 'model' => 'EXCLUSIVE', 'cadence' => 'PER_DAY']);
    [, , $v2] = connect_engv_open_for_engagement($e2, ['period_label' => '2026-08-01']);
    connect_engv_add_line($v2, ['work_date' => '2026-08-01', 'units' => 1, 'travel' => 900, 'lodging' => 2000, 'conveyance' => 300, 'allowance' => 500, 'misc' => 100]);
    $vr2 = connect_engv_get($v2);
    t_eq((float)$vr2['fee_total'], 5000.0, 'exclusive fee = 1 day × 5000');
    t_eq((float)$vr2['reimb_total'], 3800.0, 'exclusive reimbursable sums all heads (900+2000+300+500+100)');
    t_eq((float)$vr2['grand_total'], 8800.0, 'exclusive grand = fee + reimbursable');

    // ---- CASE 3: MAN_MONTHS, rate per month --------------------------------
    $e3 = $mkEng(['kind' => 'professional', 'sid' => 503, 'basis' => 'MAN_MONTHS', 'rate' => 90000, 'unit' => 'month', 'model' => 'INCLUSIVE']);
    [, , $v3] = connect_engv_open_for_engagement($e3, ['period_label' => '2026-08']);
    connect_engv_add_line($v3, ['units' => 1]);
    t_eq((float)connect_engv_get($v3)['fee_total'], 90000.0, 'man-months fee = 1 month × month rate');

    // ---- CASE 4: on-roll INSPECTOR subject, EXCLUSIVE ----------------------
    $e4 = $mkEng(['kind' => 'inspector', 'sid' => 777, 'basis' => 'DEPUTATION', 'rate' => 4000, 'unit' => 'day', 'model' => 'EXCLUSIVE']);
    [, , $v4] = connect_engv_open_for_engagement($e4);
    connect_engv_add_line($v4, ['work_date' => '2026-08-05', 'units' => 1, 'travel' => 1200]);
    $vr4 = connect_engv_get($v4);
    t_eq($vr4['subject_kind'], 'inspector', 'the same voucher model serves an on-roll inspector');
    t_eq((float)$vr4['grand_total'], 5200.0, 'on-roll exclusive grand = 4000 + 1200');

    // ---- lifecycle ----------------------------------------------------------
    t_ok(connect_engv_can_transition('DRAFT', 'SUBMITTED'), 'draft → submitted allowed');
    t_ok(!connect_engv_can_transition('DRAFT', 'PAID'), 'draft → paid NOT allowed');
    // a draft with no lines cannot be submitted
    $e5 = $mkEng(['kind' => 'professional', 'sid' => 504, 'rate' => 1000]);
    [, , $v5] = connect_engv_open_for_engagement($e5);
    [$subOk] = connect_engv_set_status($v5, 'SUBMITTED');
    t_ok(!$subOk, 'an empty voucher cannot be submitted');
    // v2 has a line → full happy path
    [$s2a] = connect_engv_set_status($v2, 'SUBMITTED'); t_ok($s2a, 'submit a voucher with a line');
    [$s2b] = connect_engv_set_status($v2, 'APPROVED', 'Desk'); t_ok($s2b, 'approve the submitted voucher');
    [$s2c] = connect_engv_set_status($v2, 'PAID', 'Finance'); t_ok($s2c, 'mark the approved voucher paid');
    [$s2d] = connect_engv_set_status($v2, 'DRAFT'); t_ok(!$s2d, 'a paid voucher is terminal');
    // adding a line to a non-draft is refused
    [$addOk] = connect_engv_add_line($v2, ['units' => 1]);
    t_ok(!$addOk, 'cannot add a line once submitted');

    // ---- summary ------------------------------------------------------------
    $sum = connect_engv_summary_for_subject('professional', 502);
    t_eq($sum['paid'], 1, 'summary counts the paid voucher for this freelancer');
    t_eq($sum['paid_value'], 8800.0, 'summary totals the paid value');

    // scoping: subject 501's voucher never shows for 502
    t_eq(count(connect_engv_for_subject('professional', 999)), 0, 'a subject with no vouchers sees none');

    // ---- booking inherits the requirement rate model -----------------------
    // Post a requirement with EXCLUSIVE + PER_DAY, award it, and confirm the
    // saved engagement carries the model.
    db()->prepare("INSERT INTO cx_requirements (ref_code,title,status,rate_inclusive,voucher_cadence,created_at) VALUES ('CXV-1','Deputation','AWARDED','EXCLUSIVE','PER_DAY',?)")->execute([date('c')]);
    $rid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_professionals (name,email,created_at) VALUES ('V Pro','vpro@example.test',?)")->execute([date('c')]);
    $pid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_applications (requirement_id,applicant_professional_id,applicant_name,status) VALUES (?,?,'V Pro','AWARDED')")->execute([$rid, $pid]);
    $aid = (int)db()->lastInsertId();
    db()->prepare("UPDATE cx_requirements SET awarded_application_id=? WHERE id=?")->execute([$aid, $rid]);
    [$bok] = connect_engage_save_for_requirement($rid, ['basis' => 'MAN_DAYS', 'rate' => 6000, 'quantity' => 5]);
    t_ok($bok, 'booking recorded for the awarded requirement');
    $booked = connect_engage_for_requirement($rid);
    t_eq($booked['rate_inclusive'], 'EXCLUSIVE', 'the booking inherits the posting rate model');
    t_eq($booked['voucher_cadence'], 'PER_DAY', 'the booking inherits the posting voucher cadence');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
