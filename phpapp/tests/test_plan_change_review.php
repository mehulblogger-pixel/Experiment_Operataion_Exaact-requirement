<?php
// Owner, 31 Oct 2026 (test book ONB-051, ONB-049): "I have already subscribed to
// 3 modules and paid; when I add one more the payment shows the price of all
// four." The amount charged was already the new plan minus the unused part of
// the old one — but the payment page showed one figure and no reason for it.
// It now shows a review first: every line, the credit, what is due now, and the
// single end date the whole plan then has.
t_section('Plan change — what is charged, and the review shown before paying');

$keys = ['saas_paid_modules', 'billing_paid_until', 'saas_paid_from', 'saas_paid_period', 'saas_seat_limit', 'saas_storage_packs', 'saas_next_plan', 'billing_price_user_month', 'billing_price_user_year'];
foreach (PRODUCT_MODULES as $k => $m) { $keys[] = 'saas_price_mod_' . $k . '_month'; $keys[] = 'saas_price_mod_' . $k . '_year'; }
$keep = []; foreach ($keys as $k) $keep[$k] = (string) setting_get($k, '');
try {
    t_set_prices(1000, 500);                     // module ₹10,000/yr, login ₹5,000/yr
    $all = selfserve_all_modules();
    $three = array_slice($all, 0, 3); $four = array_slice($all, 0, 4); sort($three); sort($four);
    $today = date('Y-m-d');
    // Three modules, 2 logins, paid yearly six days ago.
    $from = date('Y-m-d', strtotime('-6 days'));
    setting_set('saas_paid_modules', implode(',', $three)); setting_set('saas_seat_limit', '2');
    setting_set('saas_paid_period', 'year'); setting_set('saas_paid_from', $from);
    setting_set('billing_paid_until', date('Y-m-d', strtotime($from . ' +1 year')));
    setting_set('saas_storage_packs', '0'); setting_set('saas_next_plan', '');

    $q = saas_plan_change_quote($four, 2, 'year', $today);
    t_eq($q['type'], 'UPGRADE', 'adding a fourth module is an upgrade');
    t_eq($q['list'], 4 * 10000 + 2 * 5000, 'the full price of the new plan is all four modules + 2 logins for a year (₹50,000)');
    t_ok($q['credit'] > 39000 && $q['credit'] < 40000, 'the unused 359 days of the old plan (₹40,000) are credited: ₹' . $q['credit']);
    t_ok($q['price'] > 10000 && $q['price'] < 11000, 'so what is charged is about the price of the one added module (₹10,000) plus six days used: ₹' . $q['price']);
    t_eq($q['expires'], date('Y-m-d', strtotime($today . ' +1 year')), 'ONB-049: the whole plan then ends on ONE day, a year from today');

    $r = saas_plan_review($q);
    t_eq($r['head'], 'Adding to your plan', 'the review says what this payment is');
    t_eq(count(array_filter($r['lines'], fn($l) => strpos($l['label'], 'seat') === false)), 4, 'it lists each of the four modules');
    t_ok($r['credit'] === $q['credit'] && $r['price'] === $q['price'], 'it shows the credit and the amount due now');
    t_ok(strpos($r['when'], 'Everything ends on that one day') !== false && strpos($r['when'], '359 unused days') !== false,
         'and says the whole plan ends on one day, with the unused days credited');

    // Paying again for the same plan is a renewal from the current end date.
    $q2 = saas_plan_change_quote($three, 2, 'year', $today);
    $r2 = saas_plan_review($q2);
    t_ok($q2['type'] === 'RENEW' && strpos($r2['when'], 'This payment adds the next year') !== false, 'the same plan again: the review says it adds the next year, from the current end date');

    // The payment page shows it and waits for the Pay button.
    $v = (string) file_get_contents(__DIR__ . '/../views/ops/billing_pay.php');
    t_ok(strpos($v, 'Less: unused part of your current plan') !== false && strpos($v, '<?php if (!is_array($rv)): ?>window.addEventListener') !== false,
         'the payment page shows the review and does not open Razorpay before it is read');
} finally { foreach ($keep as $k => $v) setting_set($k, $v); }
