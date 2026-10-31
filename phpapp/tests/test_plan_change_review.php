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
    $until = date('Y-m-d', strtotime($from . ' +1 year'));
    t_eq($q['type'], 'UPGRADE', 'adding a fourth module is an upgrade');
    // Owner's decision, 31 Oct 2026 — the Zoho way: only the difference, for the days left; the renewal date stays.
    t_ok(!empty($q['coterm']) && $q['expires'] === $until, 'ONB-049: the renewal date does not move — everything still ends on ' . $until);
    t_eq([$q['cur_list'], $q['diff']], [3 * 10000 + 2 * 5000, 10000], 'the difference is the added module\'s yearly price (₹10,000)');
    $left = (int) round((strtotime($until) - strtotime($today)) / 86400); $tot = (int) round((strtotime($until) - strtotime($from)) / 86400);
    t_eq($q['price'], (int) round(10000 * $left / $tot), 'and only for the ' . $left . ' days left: ₹' . $q['price']);

    $r = saas_plan_review($q);
    t_eq($r['head'], 'Adding to your plan', 'the review says what this payment is');
    t_eq(count(array_filter($r['lines'], fn($l) => strpos($l['label'], 'seat') === false)), 4, 'it lists each of the four modules');
    t_ok($r['price'] === $q['price'] && count($r['rows']) === 4 && $r['rows'][1][1] === -40000, 'it shows the new plan, minus the current plan (already paid), the difference and the days left');
    t_ok(strpos($r['when'], 'keeps its renewal date') !== false && strpos($r['when'], 'everything ends on that day') !== false,
         'and says the renewal date stays and everything ends on that day');
    $q2y = saas_plan_change_quote($four, 2, 'month', $today);
    t_ok(empty($q2y['coterm']), 'switching yearly → monthly is not an addition to the same period (a new period, as Zoho does)');

    // Paying again for the same plan is a renewal from the current end date.
    $q2 = saas_plan_change_quote($three, 2, 'year', $today);
    $r2 = saas_plan_review($q2);
    t_ok($q2['type'] === 'RENEW' && strpos($r2['when'], 'This payment adds the next year') !== false, 'the same plan again: the review says it adds the next year, from the current end date');

    // The payment page shows it and waits for the Pay button.
    $v = (string) file_get_contents(__DIR__ . '/../views/ops/billing_pay.php');
    t_ok(strpos($v, 'Less: unused part of your current plan') !== false && strpos($v, '<?php if (!is_array($rv)): ?>window.addEventListener') !== false,
         'the payment page shows the review and does not open Razorpay before it is read');
} finally { foreach ($keep as $k => $v) setting_set($k, $v); }
