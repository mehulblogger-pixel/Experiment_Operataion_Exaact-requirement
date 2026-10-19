<?php
/**
 * EXAACT audit — what do the portal MONEY and DATE fields do with a WRONG
 * value, as opposed to an empty one?
 *
 * The empty-submission pass (evidence/portal-empty-submit.py) showed every
 * creating form refuses a blank. That is a different question from what happens
 * when a field is filled in WRONGLY: a negative rate, an absurd quantity, a
 * date that is not a date. These matter most on a marketplace voucher, because
 * a voucher line is what a client is asked to approve and pay.
 *
 * /pro/voucher passes $_POST straight into connect_engv_add_line() (see
 * lib/connect_pro.php, action 'add_line'), so this drives that function with
 * the values a hostile or fat-fingered request would send and reports exactly
 * what reaches the database.
 *
 * Read-only against a throwaway database.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/portal-bad-input.php
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';

connect_engv_migrate();
$now = date('c');

// A DRAFT voucher on a man-day engagement at Rs 2,500 a day.
db()->prepare("INSERT INTO cx_engagement_vouchers
    (subject_kind,subject_id,subject_name,basis,rate,rate_unit,rate_inclusive,status,period_label,created_at)
    VALUES ('PROFESSIONAL',1,'Probe Professional','MAN_DAYS',2500,'day','EXCLUSIVE','DRAFT','2026-09',?)")
    ->execute([$now]);
$V = (int)db()->lastInsertId();

printf("Voucher %d — man-days at Rs %s/day, EXCLUSIVE (expenses claimable)\n", $V, number_format(2500));
echo "period_label '2026-09', with period_start/period_end BLANK — which is what\n";
echo "connect_engv_open_for_engagement() actually produces, so the bounds have to\n";
echo "be derived from the label or a period check never fires in production.\n\n";
printf("%-34s %-10s %-12s %-14s %s\n", 'WHAT WAS POSTED', 'ACCEPTED', 'units STORED', 'fee STORED', 'work_date STORED');
printf("%s\n", str_repeat('-', 104));

$cases = [
  ['units = -5 (negative quantity)',        ['units' => '-5',      'work_date' => '2026-09-10']],
  ['units = 0',                             ['units' => '0',       'work_date' => '2026-09-10']],
  ['units = abc (not a number)',            ['units' => 'abc',     'work_date' => '2026-09-10']],
  ['units = 999999 (absurd quantity)',      ['units' => '999999',  'work_date' => '2026-09-10']],
  ['units = 1e9 (exponent notation)',       ['units' => '1e9',     'work_date' => '2026-09-10']],
  ['travel = -100 (negative expense)',      ['units' => '1', 'travel' => '-100', 'work_date' => '2026-09-10']],
  ['work_date = banana',                    ['units' => '1',       'work_date' => 'banana']],
  ['work_date = 2026-13-45 (impossible)',   ['units' => '1',       'work_date' => '2026-13-45']],
  ['work_date = 1899-01-01 (long past)',    ['units' => '1',       'work_date' => '1899-01-01']],
  ['work_date = 2099-01-01 (far future)',   ['units' => '1',       'work_date' => '2099-01-01']],
  ['work_date = <script>alert(1)</script>', ['units' => '1',       'work_date' => '<script>alert(1)</script>']],
];

$notes = [];
foreach ($cases as [$label, $in]) {
    [$ok, $msg, $id] = connect_engv_add_line($V, $in);
    $row = $id ? ops_one("SELECT units, fee, travel, work_date FROM cx_engagement_voucher_lines WHERE id=?", [$id]) : null;
    printf("%-34s %-10s %-12s %-14s %s\n", $label,
        $ok ? 'yes' : 'REFUSED',
        $row ? rtrim(rtrim(number_format((float)$row['units'], 2, '.', ''), '0'), '.') : '-',
        $row ? number_format((float)$row['fee'], 2) : '-',
        $row ? ('"' . $row['work_date'] . '"') : '-');
    if ($row && (float)$row['travel'] < 0) $notes[] = 'a NEGATIVE expense was stored';
    if ($row && $label[0] === 'u' && strpos($label, '-5') !== false && (float)$row['units'] != -5
        && (float)$row['units'] > 0) $notes[] = 'units -5 was silently CORRECTED to ' . (float)$row['units'];
    if ($row && strpos($label, 'banana') !== false && $row['work_date'] === 'banana')
        $notes[] = 'a work date of "banana" was stored verbatim';
    if ($row && strpos($label, '999999') !== false && (float)$row['units'] == 999999)
        $notes[] = 'a claim of 999,999 man-days was accepted (fee Rs ' . number_format((float)$row['fee'], 2) . ')';
    if ($row && strpos($label, 'script') !== false && strpos((string)$row['work_date'], '<script') !== false)
        $notes[] = 'a work date containing a <script> tag was stored verbatim';
}

$tot = ops_one("SELECT grand_total, fee_total, reimb_total FROM cx_engagement_vouchers WHERE id=?", [$V]);
printf("\nVoucher total after all of that: Rs %s  (fees %s + reimbursements %s)\n",
    number_format((float)$tot['grand_total'], 2),
    number_format((float)$tot['fee_total'], 2),
    number_format((float)$tot['reimb_total'], 2));

echo "\nWHAT THIS SHOWS\n" . str_repeat('-', 104) . "\n";
if ($notes) {
    foreach (array_unique($notes) as $n) echo "  * " . $n . "\n";
} else {
    echo "  Every wrong value was refused outright.\n";
}
echo "\nMITIGATION that is REAL and must be stated with any finding above: a voucher\n";
echo "moves DRAFT -> SUBMITTED -> APPROVED, and a human on the client side approves\n";
echo "it. None of this bills anybody by itself. The question is whether a document\n";
echo "that a client is asked to approve should be able to contain a date that is not\n";
echo "a date, and a quantity nobody could have worked.\n";
