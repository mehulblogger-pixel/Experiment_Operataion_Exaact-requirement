<?php
// ============================================================================
//  A voucher line is the document a client is asked to approve and PAY.
//  What it accepts therefore matters as much as who can reach it.
//
//  Before this validation existed, connect_engv_add_line() accepted:
//    units = -5      -> silently corrected to 1 and reported "Day added."
//    units = 999999  -> Rs 2,499,997,500 on one line
//    units = 1e9     -> Rs 2.5 trillion
//    units = abc     -> silently corrected to 1
//    travel = -100   -> silently zeroed, reported as added
//    work_date = banana / 2026-13-45 / 1899-01-01 / 2099-01-01 -> stored verbatim
//
//  None of it could be injected into a page (both views escape and truncate
//  work_date), and none of it bills anybody on its own -- a voucher goes
//  DRAFT -> SUBMITTED -> APPROVED and a human on the client side approves it.
//  The point is that a claim document should not be able to say those things.
//
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('voucher lines — what a claim document may say');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_engv_migrate();
    $now = date('c');
    $mkVoucher = function ($label, $ps = '', $pe = '') use ($now) {
        db()->prepare("INSERT INTO cx_engagement_vouchers
            (subject_kind,subject_id,subject_name,basis,rate,rate_unit,rate_inclusive,
             status,period_label,period_start,period_end,created_at)
            VALUES ('PROFESSIONAL',1,'T','MAN_DAYS',2500,'day','EXCLUSIVE','DRAFT',?,?,?,?)")
            ->execute([$label, $ps, $pe, $now]);
        return (int)db()->lastInsertId();
    };
    $V = $mkVoucher('2026-09');

    // ---- the quantity ----------------------------------------------------
    foreach ([
        ['-5',     'a negative quantity'],
        ['0',      'a zero quantity'],
        ['abc',    'a quantity that is not a number'],
        ['999999', 'a quantity nobody could have worked'],
        ['1e9',    'a quantity in exponent notation'],
    ] as [$u, $what]) {
        [$ok, $msg] = connect_engv_add_line($V, ['units' => $u, 'work_date' => '2026-09-10']);
        t_eq($ok, false, $what . ' (' . $u . ') is refused, not corrected in silence');
        t_ok($msg !== '' && stripos($msg, 'nothing was added') !== false,
             '...and says plainly that nothing was added: ' . $msg);
    }

    // ---- the expenses ----------------------------------------------------
    [$ok, $msg] = connect_engv_add_line($V, ['units' => 1, 'work_date' => '2026-09-10', 'travel' => '-100']);
    t_eq($ok, false, 'a negative expense is refused, not silently zeroed: ' . $msg);
    [$ok] = connect_engv_add_line($V, ['units' => 1, 'work_date' => '2026-09-10', 'travel' => 'abc']);
    t_eq($ok, false, 'an expense that is not a number is refused');

    // ---- the date --------------------------------------------------------
    foreach (['banana', '2026-13-45', '10/09/2026', '2026-9-10', '<script>alert(1)</script>'] as $bad) {
        [$ok] = connect_engv_add_line($V, ['units' => 1, 'work_date' => $bad]);
        t_eq($ok, false, 'a day of "' . $bad . '" is refused');
    }
    foreach (['1899-01-01', '2099-01-01'] as $bad) {
        [$ok, $msg] = connect_engv_add_line($V, ['units' => 1, 'work_date' => $bad]);
        t_eq($ok, false, 'a day in ' . substr($bad, 0, 4) . ' is refused: ' . $msg);
    }

    // ---- what must still WORK -------------------------------------------
    [$ok, $msg, $id] = connect_engv_add_line($V, ['units' => 1, 'work_date' => '2026-09-10', 'travel' => 1200]);
    t_eq($ok, true, 'an ordinary line is still accepted');
    $row = ops_one("SELECT units, fee, travel, work_date FROM cx_engagement_voucher_lines WHERE id=?", [$id]);
    t_eq((float)$row['units'], 1.0,       '...with the quantity it was given');
    t_eq((float)$row['fee'], 2500.0,      '...the fee it earns');
    t_eq((float)$row['travel'], 1200.0,   '...and the expense claimed');
    t_eq($row['work_date'], '2026-09-10', '...on the day given');

    [$ok] = connect_engv_add_line($V, ['units' => 1]);
    t_eq($ok, true, 'a line with NO day is still accepted — a period voucher need not name one');
    [$ok] = connect_engv_add_line($V, ['units' => '0.5', 'work_date' => '2026-09-11']);
    t_eq($ok, true, 'a half day is still accepted');
    [$ok] = connect_engv_add_line($V, ['units' => 31, 'work_date' => '2026-09-12']);
    t_eq($ok, true, 'a full month of days on one line is still accepted');

    // THE CASE MY FIRST ATTEMPT BROKE. A voucher opened with no period label
    // gets TODAY'S month by default (connect_engv_open_for_engagement), which
    // says nothing about which period the work belongs to. An earlier version
    // of this validation derived the bounds from that label and refused a day
    // outside it -- which would have stopped a professional filing last
    // month's days, ordinary practice, and broke a real test.
    $back = $mkVoucher(date('Y-m'));          // defaulted label = this month
    [$ok, $msg] = connect_engv_add_line($back, ['units' => 1, 'work_date' => date('Y-m-d', strtotime('-2 months'))]);
    t_eq($ok, true, 'back-dating two months on an undeclared period is ALLOWED: ' . $msg);

    // ...while an EXPLICIT period is still enforced, because that one was agreed.
    $bounded = $mkVoucher('2026-09', '2026-09-01', '2026-09-30');
    [$ok, $msg] = connect_engv_add_line($bounded, ['units' => 1, 'work_date' => '2026-08-20']);
    t_eq($ok, false, 'a day before an EXPLICIT period start is refused: ' . $msg);
    [$ok, $msg] = connect_engv_add_line($bounded, ['units' => 1, 'work_date' => '2026-10-02']);
    t_eq($ok, false, 'a day after an EXPLICIT period end is refused: ' . $msg);
    [$ok] = connect_engv_add_line($bounded, ['units' => 1, 'work_date' => '2026-09-15']);
    t_eq($ok, true, '...and a day inside it is accepted');

    // ---- and the professional is told the answer -------------------------
    // The route used to discard this function's return value, so every refusal
    // above -- including the pre-existing "Add lines only while the voucher is
    // a draft." -- was invisible to the one person filling the voucher in.
    $src = file_get_contents(__DIR__ . '/../lib/connect_pro.php');
    t_ok(preg_match('/\[\$lok, \$lmsg\] = connect_engv_add_line/', $src) === 1,
         '/pro/voucher captures what connect_engv_add_line() answers');
    t_ok(strpos($src, "connect_pro_flash_set(\$lmsg, \$lok ? 'ok' : 'err')") !== false,
         '...and shows it to the professional, tagged by the outcome');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
