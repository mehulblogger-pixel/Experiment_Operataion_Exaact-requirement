<?php
// Owner, 9 Nov 2026: "If I want to add only one module … or renew only one
// module … or add some seats … Suppose I have 25 seats and want to add 5 more
// then I need to input 30 … the customer may get confused." A company with a
// paid plan now gets: Add to your plan (only what is new; logins as a number to
// ADD), Your renewal (renew now / change what renews), and Rebuild.
t_section('Your subscription: add, renew, change what renews');
$keys = ['saas_paid_modules', 'billing_paid_until', 'saas_paid_from', 'saas_paid_period', 'saas_seat_limit', 'saas_storage_packs', 'saas_next_plan', 'billing_price_user_month', 'billing_price_user_year'];
foreach (PRODUCT_MODULES as $k => $m) { $keys[] = 'saas_price_mod_' . $k . '_month'; $keys[] = 'saas_price_mod_' . $k . '_year'; }
$keep = []; foreach ($keys as $k) $keep[$k] = (string) setting_get($k, '');
try {
    t_set_prices(1000, 500);                     // module ₹10,000/yr, login ₹5,000/yr
    $all = selfserve_all_modules(); sort($all);
    $two = array_slice($all, 0, 2); $new = $all[2];
    $from = date('Y-m-d', strtotime('-10 days'));
    setting_set('saas_paid_modules', implode(',', $two)); setting_set('saas_seat_limit', '25');
    setting_set('saas_paid_period', 'year'); setting_set('saas_paid_from', $from);
    setting_set('billing_paid_until', date('Y-m-d', strtotime($from . ' +1 year')));
    setting_set('saas_storage_packs', '0'); setting_set('saas_next_plan', '');

    // Adding 5 logins and one module — what the server builds from "add".
    $sel = array_merge($two, [$new]); sort($sel);
    $q = saas_plan_change_quote($sel, 25 + 5, 'year');
    t_ok($q['type'] === 'UPGRADE' && !empty($q['coterm']), 'adding is priced as an addition that keeps the renewal date');
    $d = saas_plan_delta_lines($sel, 30, 0, 'year');
    t_eq(count($d), 2, 'the payment page lists only the two things added');
    t_ok(strpos($d[0]['label'], 'Add ') === 0 && $d[0]['amount'] === 10000, 'the new module, ₹10,000 a year');
    t_ok(strpos($d[1]['label'], '5 more logins') === 0 && $d[1]['amount'] === 25000, '5 more logins, ₹25,000 a year (not "30 people")');
    t_eq(array_sum(array_column($d, 'amount')), (int) $q['diff'], 'and they add up to exactly what the server charges for a full year');
    $r = saas_plan_review($q);
    t_eq(count($r['rows']), 2, 'then the full-year total and the amount for the days left');

    // Keeping only one module at renewal is booked for the renewal day, nothing charged.
    $q = saas_plan_change_quote([$two[0]], 25, 'year');
    t_eq($q['type'], 'DOWNGRADE', 'keeping only one module from the renewal day is booked, nothing charged now');
    // Renewing the same plan in advance is a renewal.
    $q = saas_plan_change_quote($two, 25, 'year');
    t_eq($q['type'], 'RENEW', '"Renew now" renews the plan for the next period');

    // The screen.
    $pb = saas_price_book(); $current = saas_plan_current(); $next = null; $sub = ['seats_used' => 15];
    $access = ['mode' => 'paid']; $sym = '₹'; $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    $modLabel = fn($k) => PRODUCT_MODULES[$k][0] ?? $k;
    ob_start(); try { include __DIR__ . '/../views/ops/_subscription_paid.php'; } catch (Throwable $ex) { echo 'THREW ' . $ex->getMessage(); } $h = ob_get_clean();
    t_ok(strpos($h, 'THREW') === false && strpos($h, 'Add to your plan') !== false && strpos($h, 'Your renewal') !== false, 'the screen shows Add to your plan and Your renewal');
    t_ok(strpos($h, 'name="add_logins"') !== false && strpos($h, 'you have 25, 15 in use') !== false, 'logins are typed as the number to ADD, beside what the company has');
    t_eq(substr_count($h, 'In your plan'), 2, 'modules already bought are shown as in the plan, not as a choice');
    t_ok(strpos($h, 'value="' . $new . '"') !== false, 'a module not yet bought can be ticked to add');
    t_ok(strpos($h, 'name="keep_mods[]"') !== false && strpos($h, 'Renew now') !== false, 'renewal: renew now, or change what renews');
    t_ok(strpos($h, 'Rebuild the whole plan') !== false, 'and the full plan form is still there, folded away');
    $src = (string) file_get_contents(__DIR__ . '/../lib/saas_tenants.php');
    t_ok(strpos($src, "\$selSeats = (int) \$cur['seats'] + \$addL") !== false, 'the server adds the logins to what the company has');
} finally { foreach ($keep as $k => $v) setting_set($k, $v); }
