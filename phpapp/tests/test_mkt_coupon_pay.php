<?php
// ============================================================================
//  Marketplace gaps found while mapping onboarding (22 Oct 2026), now fixed:
//   1. a coupon was ignored whenever online payment was on — the buyer paid the
//      full price; now the discount is taken off what is charged, recorded on the
//      order, and the coupon counted as used only once the payment is confirmed;
//   2. the professional sign-up ignored the marketplace add-on switch that the
//      company / agency sign-up obeys.
// ============================================================================

t_section('Marketplace — coupons with online payment; professional sign-up switch');

$keep = [];
foreach (['rzp_key_id', 'rzp_key_secret', 'billing_currency', 'marketplace_addon', 'connect_enabled'] as $k) $keep[$k] = (string) setting_get($k, '');
setting_set('rzp_key_id', 'rzp_test_x'); setting_set('rzp_key_secret', 'secret_for_test'); setting_set('billing_currency', 'INR');
$GLOBALS['__rzp_transport'] = function ($m, $url, $body) {
    $j = json_decode((string) $body, true) ?: [];
    return [200, json_encode(['id' => 'order_cp' . mt_rand(100000, 999999), 'amount' => (int) ($j['amount'] ?? 0)])];
};

[$ok] = mkt_plan_save(['name' => 'Coupon Test Plan ' . getmypid(), 'audience' => 'CLIENT', 'price_month' => 1000, 'is_active' => 1]);
$planId = (int) ops_val("SELECT id FROM mkt_plans WHERE name=?", ['Coupon Test Plan ' . getmypid()]);
t_ok($planId > 0, 'a priced client plan exists (1,000 a month)');
$code = 'TB' . getmypid();
mkt_coupon_save(['code' => $code, 'kind' => 'PERCENT', 'value' => 30, 'is_active' => 1]);
$sub = 990000 + (getmypid() % 9000);

$start = mkt_pay_start('CLIENT', $sub, 'SUB', $planId, 'MONTH', 'Tester', $code);
t_eq($start['mode'] ?? '', 'pay', 'with online payment on, choosing the plan opens the payment');
$ord = ops_one("SELECT * FROM mkt_orders WHERE id=?", [(int) ($start['row_id'] ?? 0)]);
t_eq((float) $ord['amount'], 700.0, 'the 30% coupon is taken off the amount charged (it used to charge the full 1,000)');
t_eq((string) $ord['coupon_code'], $code, 'the coupon is recorded on the order');
t_eq((int) ops_val("SELECT used FROM mkt_coupons WHERE code=?", [$code]), 0, 'the coupon is not counted as used before the payment is confirmed');

$pay = 'pay_cp' . getmypid();
$sig = hash_hmac('sha256', $ord['order_id'] . '|' . $pay, 'secret_for_test');
[$vok, $vmsg] = mkt_pay_verify($ord['order_id'], $pay, $sig);
t_ok($vok, 'the confirmed payment activates the plan');
$s = ops_one("SELECT * FROM mkt_subscriptions WHERE subscriber_kind='CLIENT' AND subscriber_id=? ORDER BY id DESC LIMIT 1", [$sub]);
t_ok($s && (string) $s['coupon_code'] === $code && (float) $s['discount'] === 300.0, 'the subscription records the coupon and the 300 discount');
t_eq((float) $s['amount'], 700.0, 'and the amount actually paid');
t_eq((int) ops_val("SELECT used FROM mkt_coupons WHERE code=?", [$code]), 1, 'the coupon is counted as used once');

$start2 = mkt_pay_start('CLIENT', $sub + 1, 'SUB', $planId, 'MONTH', 'Tester', 'NOSUCHCODE');
$ord2 = ops_one("SELECT * FROM mkt_orders WHERE id=?", [(int) ($start2['row_id'] ?? 0)]);
t_ok($ord2 && (float) $ord2['amount'] === 1000.0 && (string) $ord2['coupon_code'] === '', 'an unknown coupon changes nothing');

// ---- 2. the professional sign-up obeys the marketplace add-on switch ---------------
setting_set('connect_enabled', '1');
setting_set('marketplace_addon', '1');
t_ok(connect_pro_portal_on(), 'marketplace on: professionals can register');
setting_set('marketplace_addon', '0');
t_ok(!connect_pro_portal_on(), 'marketplace add-on off: the professional sign-up is closed, like the company / agency sign-up');

unset($GLOBALS['__rzp_transport']);
foreach ($keep as $k => $v) setting_set($k, $v);
