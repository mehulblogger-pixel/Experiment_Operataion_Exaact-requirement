<?php
// ============================================================================
//  The ERP's own self-service purchase (/buy, /buy-verify) -- bound to the order.
//
//  A Razorpay signature proves that payment P belongs to order O. It proves
//  nothing about seats or period. The old verify branch read BOTH from $_POST
//  a second time, and recorded no payment id, so:
//    * a 1-seat / 1-month payment could be verified into 500 seats for a year
//    * the same valid POST re-issued and re-extended the licence for ever
//    * a failed licence record was swallowed, leaving a licence with no trace
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('self-service purchase — bound to the order, applied once');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
$snap = []; foreach (['rzp_key_id','rzp_key_secret','billing_price_user_month','billing_price_user_year','billing_currency'] as $k) $snap[$k] = setting_get($k, '');
try {
    // lk_issue() signs with LICENCE_PRIVKEY or ../licence-private.pem; the
    // harness has neither, so make an EC key for this process only.
    $__pk = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($__pk, $__pem);
    $__hadKey = getenv('LICENCE_PRIVKEY');
    putenv('LICENCE_PRIVKEY=' . $__pem);
    licissue_migrate(); billing_migrate();
    setting_set('rzp_key_id', 'rzp_test_x'); setting_set('rzp_key_secret', 'unit-secret');
    setting_set('billing_price_user_month', '500'); setting_set('billing_price_user_year', '5000'); setting_set('billing_currency', 'INR');
    // Razorpay, stubbed: an order is created with whatever amount we send.
    $GLOBALS['__rzp_transport'] = function ($m, $url, $body) {
        $j = json_decode((string)$body, true) ?: [];
        return [200, json_encode(['id' => 'order_T' . substr(sha1($body . microtime(true)), 0, 8), 'amount' => (int)($j['amount'] ?? 0)])];
    };
    $sign = fn($o, $p) => hash_hmac('sha256', $o . '|' . $p, 'unit-secret');

    // The workspace that is buying: one existing licence, 2 seats, expiring soon.
    $install = 'inst-' . bin2hex(random_bytes(4));
    db()->prepare("INSERT INTO issued_licences (ref,customer,install_id,seats,exp,grace,mods,key_text,by_user,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?)")
       ->execute(['LIC-OLD', 'Buyer Co', $install, 2, date('Y-m-d', strtotime('+10 days')), 14, 'sales', 'k', 'test', date('c')]);
    $rec = ops_one("SELECT * FROM issued_licences WHERE install_id=? ORDER BY id DESC LIMIT 1", [$install]);
    $count = fn() => (int)ops_val("SELECT COUNT(*) FROM issued_licences WHERE install_id=?", [$install]);

    // ---- the cheapest possible order: 1 seat, 1 month --------------------
    $amt = billing_amount(1, 'month');
    $ord = rzp_create_order($amt['paise'], 'r', []);
    t_ok(!empty($ord['ok']), 'an order is created');
    $oid = buy_order_open($install, $amt, $ord);
    $row = ops_one("SELECT * FROM billing_orders WHERE id=?", [$oid]);
    t_eq([$row['status'], (int)$row['seats'], $row['period'], (int)$row['amount'], $row['install_id']],
         ['PENDING', 1, 'month', 500, $install], 'the order is recorded PENDING with the seats, period and amount it was priced for');

    // ---- 1. the signature is valid; the form claims 500 seats for a year ---
    // buy_apply_payment() takes no seats/period at all: there is nothing to lie to.
    [$ok, $title] = buy_apply_payment($install, $rec, $ord['id'], 'pay_1', $sign($ord['id'], 'pay_1'));
    t_ok($ok, 'a genuine payment is applied: ' . $title);
    $lic = ops_one("SELECT * FROM issued_licences WHERE install_id=? ORDER BY id DESC LIMIT 1", [$install]);
    t_eq((int)$lic['seats'], 1, 'THE LICENCE CARRIES THE SEATS THE ORDER WAS PRICED FOR — 1, not whatever the form says');
    t_eq($lic['exp'], date('Y-m-d', strtotime($rec['exp'] . ' +1 month')), '...for one MONTH, extended from the current expiry');
    t_eq([$lic['order_id'], $lic['payment_id'], (int)$lic['amount']], [$ord['id'], 'pay_1', 500],
         '...and records the order, the payment and the amount paid');
    t_eq(ops_one("SELECT status FROM billing_orders WHERE id=?", [$oid])['status'], 'PAID', 'the order is marked PAID');

    // ---- 2. replay: the same valid triple again ---------------------------
    $before = $count();
    [$ok2, $title2] = buy_apply_payment($install, $lic, $ord['id'], 'pay_1', $sign($ord['id'], 'pay_1'));
    t_eq($ok2, false, 'the same payment presented again is refused: ' . $title2);
    t_eq($count(), $before, '...and issues nothing');
    t_eq(ops_one("SELECT * FROM issued_licences WHERE install_id=? ORDER BY id DESC LIMIT 1", [$install])['exp'], $lic['exp'],
         '...and the expiry does not move again');

    // ---- 3. an order this workspace never started -----------------------
    [$ok3, $title3] = buy_apply_payment($install, $lic, 'order_SOMEONE_ELSES', 'pay_9', $sign('order_SOMEONE_ELSES', 'pay_9'));
    t_eq($ok3, false, 'a validly-signed payment for an order this workspace never created is refused: ' . $title3);

    // ---- 4. a forged signature ----------------------------------------
    $amt2 = billing_amount(3, 'year'); $ord2 = rzp_create_order($amt2['paise'], 'r2', []); $oid2 = buy_order_open($install, $amt2, $ord2);
    [$ok4] = buy_apply_payment($install, $lic, $ord2['id'], 'pay_2', 'forged');
    t_eq($ok4, false, 'a forged signature is refused');
    t_eq(ops_one("SELECT status FROM billing_orders WHERE id=?", [$oid2])['status'], 'FAILED', '...and the order is marked FAILED, not left PENDING');
    [$ok4b] = buy_apply_payment($install, $lic, $ord2['id'], 'pay_2', $sign($ord2['id'], 'pay_2'));
    t_eq($ok4b, false, '...so a later genuine signature on that same order cannot revive it');

    // ---- 5. the marketplace flow claims PAID atomically -----------------
    if (function_exists('mkt_pay_migrate')) {
        mkt_pay_migrate();
        db()->prepare("INSERT INTO mkt_orders (subscriber_kind,subscriber_id,purpose,ref_id,period,amount,currency,order_id,status,created_by,created_at)
                       VALUES ('CLIENT',1,'SUB',1,'MONTH',0,'INR','order_M1','PENDING','t',?)")->execute([date('c')]);
        [$m1] = mkt_pay_verify('order_M1', 'pay_M1', $sign('order_M1', 'pay_M1'));
        t_ok($m1, 'marketplace: the first verification of an order succeeds');
        [$m2, $mm] = mkt_pay_verify('order_M1', 'pay_M1', $sign('order_M1', 'pay_M1'));
        t_eq($m2, false, 'marketplace: the second is refused: ' . $mm);
    }
} finally {
    putenv('LICENCE_PRIVKEY' . ($__hadKey === false ? '' : '=' . $__hadKey));
    unset($GLOBALS['__rzp_transport']);
    foreach ($snap as $k => $v) setting_set($k, $v);
    if ($own && db()->inTransaction()) db()->rollBack();
}
