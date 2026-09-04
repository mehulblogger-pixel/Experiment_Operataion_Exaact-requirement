<?php
// Slice 6 — marketplace payment capture (Razorpay for subscriptions & credit packs).
// The plan/pack is activated ONLY after a valid signed callback. No keys, or a zero
// price, falls back to immediate activation so nothing breaks before keys are added.
t_section('marketplace payment capture (Razorpay)');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$kid0 = (string)setting_get('rzp_key_id', ''); $sec0 = (string)setting_get('rzp_key_secret', '');
$transport0 = $GLOBALS['__rzp_transport'] ?? null;
try {
    mkt_plans_migrate(); mkt_subs_migrate(); mkt_credits_migrate(); mkt_pay_migrate();
    $SECRET = 'secret_test_key';

    // A fake Razorpay: every order-create returns a fixed order id + echoes the amount.
    $GLOBALS['__rzp_transport'] = function ($method, $url, $body, $user, $pass) {
        $in = json_decode($body, true) ?: [];
        return [200, json_encode(['id' => 'order_TEST_' . substr(md5($body . microtime()), 0, 8), 'amount' => (int)($in['amount'] ?? 0), 'currency' => 'INR'])];
    };
    $sign = fn($oid, $pid) => hash_hmac('sha256', $oid . '|' . $pid, $SECRET);

    // A client + a paid plan (Growth) and a free plan (PRO Free).
    db()->prepare("INSERT INTO business_partners (legal_name,is_client,status) VALUES ('Pay Co',1,'ACTIVE')")->execute();
    $party = (int)db()->lastInsertId();
    $growth = null; foreach (mkt_plans_all('CLIENT') as $p) if ($p['code'] === 'CL_GROWTH') $growth = $p;
    $free   = null; foreach (mkt_plans_all('PRO') as $p) if ($p['code'] === 'PR_FREE') $free = $p;
    t_ok($growth && (float)$growth['price_month'] > 0, 'a paid client plan exists');

    // ---- no keys configured → immediate activation (safe fallback) ----
    setting_set('rzp_key_id', ''); setting_set('rzp_key_secret', '');
    t_ok(mkt_pay_configured() === false, 'payment is not configured without keys');
    $start = mkt_pay_start('CLIENT', $party, 'SUB', (int)$growth['id'], 'MONTH');
    t_eq($start['mode'], 'unconfigured', 'no keys → caller activates directly (no charge)');

    // ---- keys configured ----
    setting_set('rzp_key_id', 'rzp_test_123'); setting_set('rzp_key_secret', $SECRET);
    t_ok(mkt_pay_configured() === true, 'payment is configured with keys');

    // A free/zero-price plan never charges.
    if ($free) { $fstart = mkt_pay_start('PRO', 999001, 'SUB', (int)$free['id'], 'MONTH'); t_eq($fstart['mode'], 'free', 'a zero-price plan needs no payment'); }

    // A paid plan → a pending Razorpay order, and NOTHING is subscribed yet.
    $start = mkt_pay_start('CLIENT', $party, 'SUB', (int)$growth['id'], 'YEAR', 'Pay Co');
    t_eq($start['mode'], 'pay', 'a paid plan starts a Razorpay order');
    $row = mkt_pay_order((int)$start['row_id'], 'CLIENT', $party);
    t_ok($row && (string)$row['status'] === 'PENDING', 'the order is recorded PENDING');
    t_eq((float)$row['amount'], (float)$growth['price_annual'], 'the order is for the annual price');
    t_ok(mkt_active_sub('CLIENT', $party) === null, 'no subscription is active before payment');

    // Wrong buyer cannot see the order (scoped).
    t_ok(mkt_pay_order((int)$start['row_id'], 'CLIENT', $party + 777) === null, 'the pending order is scoped to its buyer');

    // A forged/invalid signature is rejected and activates nothing.
    [$bad, $bmsg] = mkt_pay_verify((string)$row['order_id'], 'pay_X', 'deadbeef');
    t_ok($bad === false, 'a bad signature is rejected: ' . $bmsg);
    t_ok(mkt_active_sub('CLIENT', $party) === null, 'still no subscription after a rejected callback');
    t_eq((string)ops_val("SELECT status FROM mkt_orders WHERE id=?", [(int)$row['id']]), 'FAILED', 'the order is marked FAILED');

    // A fresh paid order + a VALID signature → subscription activates, order PAID.
    $start2 = mkt_pay_start('CLIENT', $party, 'SUB', (int)$growth['id'], 'MONTH', 'Pay Co');
    $row2 = mkt_pay_order((int)$start2['row_id']);
    $pid = 'pay_OK_1'; $sig = $sign((string)$row2['order_id'], $pid);
    [$ok, $msg] = mkt_pay_verify((string)$row2['order_id'], $pid, $sig);
    t_ok($ok === true, 'a valid signed callback is accepted: ' . $msg);
    $sub = mkt_active_sub('CLIENT', $party);
    t_ok($sub !== null, 'the subscription is now active');
    t_eq((string)mkt_current_plan('CLIENT', $party)['code'], 'CL_GROWTH', 'the paid plan is the active one');
    t_eq((string)ops_val("SELECT status FROM mkt_orders WHERE id=?", [(int)$row2['id']]), 'PAID', 'the order is marked PAID');

    // A replayed callback on the same order does nothing more.
    [$again, $amsg] = mkt_pay_verify((string)$row2['order_id'], $pid, $sig);
    t_ok($again === false, 'a replayed callback is refused (already processed)');

    // ---- a credit pack, paid ----
    $pack = mkt_credit_packs_all('CLIENT', true)[0];
    $ps = mkt_pay_start('CLIENT', $party, 'PACK', (int)$pack['id'], 'MONTH', 'Pay Co');
    t_eq($ps['mode'], 'pay', 'a priced credit pack starts a Razorpay order');
    $prow = mkt_pay_order((int)$ps['row_id']);
    $bal0 = mkt_credits_balance('CLIENT', $party, (string)$pack['metric']);
    $ppid = 'pay_OK_2'; [$pok, $pmsg] = mkt_pay_verify((string)$prow['order_id'], $ppid, $sign((string)$prow['order_id'], $ppid));
    t_ok($pok === true, 'the pack payment is accepted: ' . $pmsg);
    t_eq(mkt_credits_balance('CLIENT', $party, (string)$pack['metric']), $bal0 + (int)$pack['credits'], 'the credits are added only after payment');
} finally {
    if ($transport0 === null) unset($GLOBALS['__rzp_transport']); else $GLOBALS['__rzp_transport'] = $transport0;
    setting_set('rzp_key_id', $kid0); setting_set('rzp_key_secret', $sec0);
    if ($own && db()->inTransaction()) db()->rollBack();
}
