<?php
// Phase 3 — subscription lifecycle (cancel, grace, proration, coupons) + feature gates
// (OFF/TEST/LIVE) + the Tax-admin role. All Super-Admin configurable, nothing hard-coded.
t_section('subscription lifecycle, coupons, gates & tax-admin');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$enf0 = (int)setting_get('mkt_enforce', 0); $grace0 = (int)setting_get('sub_grace_days', 0);
$ta0 = (string)setting_get('tax_admin_emails', '');
try {
    mkt_subs_migrate(); mkt_billing_migrate();

    // ---- feature gates ----
    t_eq(mkt_gate('payments'), 'OFF', 'a feature defaults to OFF');
    t_ok(mkt_gate_set('payments', 'LIVE'), 'a gate can be set LIVE');
    t_ok(mkt_gate_is_live('payments'), 'the gate reads LIVE');
    mkt_gate_set('payments', 'TEST');
    t_ok(mkt_gate_is_test('payments') && !mkt_gate_is_live('payments'), 'the gate reads TEST');
    t_eq(mkt_gate('nonsense_feature'), 'OFF', 'an unknown feature is OFF');
    t_ok(mkt_gate_set('payments', 'WHATEVER') === false, 'an invalid state is rejected');
    mkt_gate_set('payments', 'OFF');

    // ---- coupons ----
    mkt_coupon_save(['code' => 'SAVE20', 'kind' => 'PERCENT', 'value' => 20, 'is_active' => 1]);
    [$net, $disc] = mkt_coupon_apply('SAVE20', 1000);
    t_eq($net, 800.0, '20% coupon nets ₹800 on ₹1,000');
    t_eq($disc, 200.0, 'the discount is ₹200');
    t_eq((int)mkt_coupon_get('SAVE20')['used'], 1, 'a redemption is counted');

    mkt_coupon_save(['code' => 'FLAT100', 'kind' => 'FIXED', 'value' => 100, 'is_active' => 1]);
    [$n2] = mkt_coupon_apply('FLAT100', 500);
    t_eq($n2, 400.0, 'a ₹100 fixed coupon nets ₹400 on ₹500');

    // Audience scope — a client coupon does not apply to a professional.
    mkt_coupon_save(['code' => 'CLIENTONLY', 'kind' => 'PERCENT', 'value' => 50, 'audience' => 'CLIENT', 'is_active' => 1]);
    [$noPro, $dPro] = mkt_coupon_apply('CLIENTONLY', 1000, 'PRO');
    t_eq($dPro, 0.0, 'a client-only coupon gives no discount to a professional');
    [, $dCli] = mkt_coupon_apply('CLIENTONLY', 1000, 'CLIENT');
    t_eq($dCli, 500.0, 'the client-only coupon applies for a client');

    // An unknown code changes nothing.
    [$same, $z] = mkt_coupon_apply('NOPE', 1000);
    t_ok($same === 1000.0 && $z === 0.0, 'an unknown coupon leaves the amount unchanged');

    // ---- subscribe with a coupon ----
    $growth = null; foreach (mkt_plans_all('CLIENT') as $p) if ($p['code'] === 'CL_GROWTH') $growth = $p;
    [$sok, , $sid] = mkt_subscribe('CLIENT', 424242, (int)$growth['id'], 'MONTH', 'tester', 'SAVE20');
    $sub = ops_one("SELECT * FROM mkt_subscriptions WHERE id=?", [(int)$sid]);
    t_eq((float)$sub['amount'], round((float)$growth['price_month'] * 0.8, 2), 'the subscription is charged the discounted amount');
    t_eq((string)$sub['coupon_code'], 'SAVE20', 'the coupon is recorded on the subscription');

    // ---- cancel at period end ----
    [$cok, $cmsg] = mkt_sub_cancel('CLIENT', 424242, 'END');
    t_ok($cok, 'cancel at period end succeeds: ' . $cmsg);
    $sub = ops_one("SELECT * FROM mkt_subscriptions WHERE id=?", [(int)$sid]);
    t_eq((int)$sub['auto_renew'], 0, 'auto-renew is off after cancel');
    t_ok(mkt_active_sub('CLIENT', 424242) !== null, 'access continues until expiry after an at-period-end cancel');

    // ---- cancel immediately ----
    mkt_subscribe('CLIENT', 424243, (int)$growth['id'], 'MONTH', 'tester');
    mkt_sub_cancel('CLIENT', 424243, 'NOW');
    t_ok(mkt_active_sub('CLIENT', 424243) === null, 'an immediate cancel ends access at once');

    // ---- grace window ----
    setting_set('mkt_enforce', 1); setting_set('sub_grace_days', 5);
    [, , $gid] = mkt_subscribe('CLIENT', 424244, (int)$growth['id'], 'MONTH', 'tester');
    db()->prepare("UPDATE mkt_subscriptions SET expires_at=? WHERE id=?")->execute([date('Y-m-d', strtotime('-2 days')), (int)$gid]);
    t_ok(mkt_active_sub('CLIENT', 424244) === null, 'the subscription has expired');
    t_ok(mkt_sub_in_grace('CLIENT', 424244) === true, 'but it is within the 5-day grace window');
    t_ok(mkt_has_access('CLIENT', 424244) === true, 'access is retained during grace');
    setting_set('sub_grace_days', 0);
    t_ok(mkt_has_access('CLIENT', 424244) === false, 'with grace 0, the expired subscriber loses access');

    // ---- upgrade with proration ----
    setting_set('sub_grace_days', 0);
    [, , $uid] = mkt_subscribe('CLIENT', 424245, (int)$growth['id'], 'MONTH', 'tester'); // full-month, just started
    $credit = mkt_sub_proration_credit('CLIENT', 424245);
    t_ok($credit > 0, 'unused time yields a prorated credit');
    $pro = null; foreach (mkt_plans_all('CLIENT') as $p) if ($p['code'] === 'CL_PRO') $pro = $p;
    [$chok, $chmsg, $newId] = mkt_sub_change('CLIENT', 424245, (int)$pro['id'], 'MONTH');
    t_ok($chok, 'the plan change succeeds: ' . $chmsg);
    $newSub = ops_one("SELECT * FROM mkt_subscriptions WHERE id=?", [(int)$newId]);
    t_ok((float)$newSub['proration_credit'] > 0, 'the credit is recorded on the new subscription');
    t_ok((float)$newSub['amount'] < (float)$pro['price_month'], 'the new charge is reduced by the prorated credit');

    // ---- tax-admin role ----
    setting_set('tax_admin_emails', 'ca@firm.com, tax@yourco.com');
    t_ok(in_array('ca@firm.com', mkt_tax_admins(), true), 'a tax admin e-mail is stored and listed');
} finally {
    setting_set('mkt_enforce', $enf0); setting_set('sub_grace_days', $grace0); setting_set('tax_admin_emails', $ta0);
    if ($own && db()->inTransaction()) db()->rollBack();
}
