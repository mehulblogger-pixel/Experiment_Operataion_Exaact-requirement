<?php
// ============================================================================
//  Changing marketplace plan — the owner's rule, 19 Oct 2026 (Q-L, GAP-039).
//  Upgrade: starts today, unused time credited (any excess becomes extra days).
//  Downgrade: starts when the paid period ends; nothing charged now, no refund.
//  Renew (same plan, same period): starts when the current one ends.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('marketplace — fair plan changes');
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$kid0 = (string)setting_get('rzp_key_id', ''); $sec0 = (string)setting_get('rzp_key_secret', '');
$transport0 = $GLOBALS['__rzp_transport'] ?? null;
try {
    mkt_plans_migrate(); mkt_subs_migrate(); mkt_billing_migrate(); mkt_pay_migrate();
    $plan = function ($code) { foreach (mkt_plans_all() as $p) if ($p['code'] === $code) return $p; return null; };
    $starter = $plan('CL_START'); $growth = $plan('CL_GROWTH'); $pro = $plan('CL_PRO');
    $prFree = $plan('PR_FREE'); $prPlus = $plan('PR_PLUS');
    t_ok($starter && $growth && $pro && $prFree && $prPlus, 'the seeded plans exist');
    $today = date('Y-m-d');
    $sub = function ($kind, $id, $p, $period, $start, $end, $amount) {
        db()->prepare("INSERT INTO mkt_subscriptions (subscriber_kind,subscriber_id,plan_id,plan_code,period,amount,started_at,expires_at,status,auto_renew,created_at) VALUES (?,?,?,?,?,?,?,?,'ACTIVE',1,?)")
            ->execute([$kind, $id, (int)$p['id'], $p['code'], $period, $amount, $start, $end, date('c')]);
        return (int)db()->lastInsertId();
    };
    $d = fn($n) => date('Y-m-d', strtotime("$n days"));
    setting_set('rzp_key_id', ''); setting_set('rzp_key_secret', '');

    // ---- NEW ---------------------------------------------------------------------
    $q = mkt_plan_change_quote('CLIENT', 7101, (int)$starter['id'], 'MONTH');
    t_eq([$q['type'], $q['starts'], (float)$q['price']], ['NEW', $today, (float)$starter['price_month']], 'no plan yet: starts today at the list price');

    // ---- UPGRADE with credit -----------------------------------------------------------
    $old = $sub('CLIENT', 7102, $starter, 'MONTH', $d(-15), $d(15), 999);
    $q = mkt_plan_change_quote('CLIENT', 7102, (int)$growth['id'], 'MONTH');
    t_eq($q['type'], 'UPGRADE', 'Starter → Growth is an upgrade');
    t_ok($q['credit'] > 400 && $q['credit'] < 600, 'half a month unused is worth about half of 999: ' . $q['credit']);
    t_eq((float)$q['price'], round((float)$growth['price_month'] - $q['credit'], 2), '...and is taken off the new price');
    [$ok, $msg, $nid] = mkt_plan_change_apply('CLIENT', 7102, (int)$growth['id'], 'MONTH', 't');
    t_ok($ok, 'the upgrade applies: ' . $msg);
    $n = ops_one("SELECT * FROM mkt_subscriptions WHERE id=?", [$nid]);
    t_eq([$n['started_at'], (float)$n['amount'], (float)$n['proration_credit']], [$today, (float)$q['price'], (float)$q['credit']], 'the new plan starts today, charged the net, with the credit recorded');
    t_eq(ops_val("SELECT status FROM mkt_subscriptions WHERE id=?", [$old]), 'CANCELLED', 'the old plan is closed');
    t_eq((int)mkt_active_sub('CLIENT', 7102)['plan_id'], (int)$growth['id'], 'Growth is the live plan');

    // ---- UPGRADE where the credit beats the new price → extra days ------------------------------
    $sub('CLIENT', 7103, $growth, 'YEAR', $today, $d(365), (float)$growth['price_annual']);
    $q = mkt_plan_change_quote('CLIENT', 7103, (int)$pro['id'], 'MONTH');
    t_eq($q['type'], 'UPGRADE', 'Growth yearly → Pro monthly is an upgrade (more per month)');
    t_eq((float)$q['price'], 0.0, 'a year of unused Growth more than pays for a month of Pro: nothing to pay');
    t_ok($q['extra_days'] > 60, 'the excess is not lost — it becomes extra days on Pro: ' . $q['extra_days']);
    [$ok,, $xid] = mkt_plan_change_apply('CLIENT', 7103, (int)$pro['id'], 'MONTH', 't');
    t_ok($ok && ops_val("SELECT expires_at FROM mkt_subscriptions WHERE id=?", [$xid]) > $d(60), '...and the new plan runs well past a month');

    // ---- DOWNGRADE: scheduled, not charged, withdrawable ------------------------------------
    $pid = $sub('CLIENT', 7104, $pro, 'MONTH', $d(-5), $d(25), 4999);
    $q = mkt_plan_change_quote('CLIENT', 7104, (int)$starter['id'], 'MONTH');
    t_eq([$q['type'], (float)$q['price'], (float)$q['due'], $q['starts']], ['DOWNGRADE', 0.0, (float)$starter['price_month'], $d(26)], 'Pro → Starter waits for the period end; nothing now, 999 then');
    [$ok, $msg] = mkt_plan_change_apply('CLIENT', 7104, (int)$starter['id'], 'MONTH', 't');
    t_ok($ok && strpos($msg, 'Nothing is charged now') !== false, 'the customer is told: ' . $msg);
    t_eq((int)mkt_active_sub('CLIENT', 7104)['plan_id'], (int)$pro['id'], 'Pro stays live for the days already paid');
    $pend = mkt_plan_change_pending('CLIENT', 7104);
    t_eq([(int)$pend['plan']['id'], $pend['starts']], [(int)$starter['id'], $d(26)], 'the change is recorded with its date');
    mkt_plan_change_cancel('CLIENT', 7104);
    t_eq(mkt_plan_change_pending('CLIENT', 7104), null, '"Keep my current plan instead" withdraws it');
    t_eq((int)ops_val("SELECT COUNT(*) FROM mkt_subscriptions WHERE subscriber_kind='CLIENT' AND subscriber_id=7104"), 1, '...and no subscription was created or refunded');

    // ---- a FREE downgrade starts by itself when its day comes ------------------------------------
    $exp = $sub('PRO', 7105, $prPlus, 'MONTH', $d(-31), $d(-1), 199);
    db()->prepare("UPDATE mkt_subscriptions SET pending_plan_id=?, pending_period='MONTH' WHERE id=?")->execute([(int)$prFree['id'], $exp]);
    $live = mkt_active_sub('PRO', 7105);
    t_ok($live && (int)$live['plan_id'] === (int)$prFree['id'] && $live['started_at'] === $today, 'the paid period ended: the free plan started by itself today');
    t_eq(mkt_plan_change_pending('PRO', 7105), null, '...and nothing is left pending');

    // ---- a PRICED downgrade is due, not started for free ------------------------------------------------
    $exp2 = $sub('CLIENT', 7106, $pro, 'MONTH', $d(-31), $d(-1), 4999);
    db()->prepare("UPDATE mkt_subscriptions SET pending_plan_id=?, pending_period='MONTH' WHERE id=?")->execute([(int)$starter['id'], $exp2]);
    t_eq(mkt_active_sub('CLIENT', 7106), null, 'a priced plan is never started without payment');
    t_ok(mkt_plan_change_pending('CLIENT', 7106)['starts'] <= $today, '...it shows as due');

    // ---- RENEW early: no days lost -------------------------------------------------------------
    $cur = $sub('CLIENT', 7107, $growth, 'MONTH', $d(-10), $d(20), 2499);
    $q = mkt_plan_change_quote('CLIENT', 7107, (int)$growth['id'], 'MONTH');
    t_eq([$q['type'], $q['starts']], ['RENEW', $d(21)], 'buying the same plan again starts the day after the current one ends');
    [$ok,, $rid] = mkt_plan_change_apply('CLIENT', 7107, (int)$growth['id'], 'MONTH', 't');
    t_eq(ops_val("SELECT status FROM mkt_subscriptions WHERE id=?", [$cur]), 'ACTIVE', 'the current period is not thrown away (it used to be cancelled)');
    t_eq((int)mkt_active_sub('CLIENT', 7107)['id'], $cur, 'today the current row is still the live one, not the future one');

    // ---- the paid route uses the same rule -------------------------------------------------------------
    $SECRET = 'secret_plan_change';
    $GLOBALS['__rzp_transport'] = function ($m, $u, $body, $usr, $pw) { $in = json_decode($body, true) ?: []; return [200, json_encode(['id' => 'order_PC_' . substr(md5($body . microtime()), 0, 8), 'amount' => (int)($in['amount'] ?? 0), 'currency' => 'INR'])]; };
    setting_set('rzp_key_id', 'rzp_test_pc'); setting_set('rzp_key_secret', $SECRET);
    $sub('CLIENT', 7108, $starter, 'MONTH', $d(-15), $d(15), 999);
    $qq = mkt_plan_change_quote('CLIENT', 7108, (int)$growth['id'], 'MONTH');
    $st = mkt_pay_start('CLIENT', 7108, 'SUB', (int)$growth['id'], 'MONTH', 't');
    t_eq($st['mode'], 'pay', 'an upgrade with something to pay goes to checkout');
    $ord = mkt_pay_order((int)$st['row_id'], 'CLIENT', 7108);
    t_eq([(float)$ord['amount'], (float)$ord['proration_credit']], [(float)$qq['price'], (float)$qq['credit']], 'the order is for the net price, with the credit fixed on it');
    [$vok] = mkt_pay_verify($ord['order_id'], 'pay_PC1', hash_hmac('sha256', $ord['order_id'] . '|pay_PC1', $SECRET));
    $live = mkt_active_sub('CLIENT', 7108);
    t_ok($vok && (int)$live['plan_id'] === (int)$growth['id'] && (float)$live['proration_credit'] === (float)$qq['credit'], 'paying applies the upgrade with exactly the credit that was shown');
    $sub('CLIENT', 7109, $pro, 'MONTH', $d(-5), $d(25), 4999);
    t_eq(mkt_pay_start('CLIENT', 7109, 'SUB', (int)$starter['id'], 'MONTH', 't')['mode'], 'scheduled', 'a downgrade never goes to checkout');

    // ---- the screens say so ---------------------------------------------------------------------
    $h = mkt_plan_change_hint('CLIENT', 7104, (int)$starter['id'], 'MONTH');
    t_ok(strpos($h, 'Downgrade') !== false && strpos($h, 'Nothing is charged now') !== false, 'a plan card explains the change before the click: ' . $h);
    foreach (['views/portal/plans.php', 'views/pro/plans.php'] as $v) {
        $s = file_get_contents(__DIR__ . '/../' . $v);
        t_ok(strpos($s, 'mkt_plan_change_pending') !== false && strpos($s, 'keep_plan') !== false && strpos($s, 'mkt_plan_change_hint') !== false, "$v shows the scheduled change, the keep button and the per-plan explanation");
    }
} finally {
    setting_set('rzp_key_id', $kid0); setting_set('rzp_key_secret', $sec0);
    if ($transport0 === null) unset($GLOBALS['__rzp_transport']); else $GLOBALS['__rzp_transport'] = $transport0;
    if ($own && db()->inTransaction()) db()->rollBack();
}
