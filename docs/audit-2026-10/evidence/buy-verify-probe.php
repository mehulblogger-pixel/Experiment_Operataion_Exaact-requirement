<?php
/**
 * EXAACT audit — the ERP's own self-service purchase (/buy, /buy-verify).
 *
 * BEFORE (a reading of the old verify branch, which no longer exists to run):
 *   the Razorpay signature was verified over order_id|payment_id -- correctly
 *   -- and then seats and period were read FROM THE POST, and no payment id was
 *   recorded anywhere. So a 1-seat, 1-month payment with its valid signature
 *   could be verified into 500 seats for a year, and the same POST re-issued
 *   and re-extended the licence on every repeat. The INSERT recording the
 *   licence sat in a swallowed try/catch.
 *
 * AFTER (this script, against the real code with Razorpay stubbed): the order
 * is recorded when created, verification takes seats/period/amount from that
 * row, and PAID is claimed exactly once.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/buy-verify-probe.php
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';
// lk_issue() signs with LICENCE_PRIVKEY or ../licence-private.pem; the
// harness has neither, so make an EC key for this process only.
$__pk = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
openssl_pkey_export($__pk, $__pem);
$__hadKey = getenv('LICENCE_PRIVKEY');
putenv('LICENCE_PRIVKEY=' . $__pem);
licissue_migrate(); billing_migrate();
setting_set('rzp_key_id', 'rzp_test_x'); setting_set('rzp_key_secret', 'probe-secret');
setting_set('billing_price_user_month', '500'); setting_set('billing_price_user_year', '5000');
$GLOBALS['__rzp_transport'] = fn($m, $u, $b) => [200, json_encode(['id' => 'order_PROBE', 'amount' => (int)(json_decode($b, true)['amount'] ?? 0)])];
$sign = fn($o, $p) => hash_hmac('sha256', "$o|$p", 'probe-secret');

$install = 'inst-probe';
db()->prepare("INSERT INTO issued_licences (ref,customer,install_id,seats,exp,grace,mods,key_text,by_user,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
   ->execute(['LIC-OLD', 'Probe Co', $install, 2, date('Y-m-d', strtotime('+10 days')), 14, 'sales', 'k', 'probe', date('c')]);
$rec = ops_one("SELECT * FROM issued_licences WHERE install_id=? ORDER BY id DESC LIMIT 1", [$install]);

echo "SELF-SERVICE PURCHASE — IS THE LICENCE BOUND TO WHAT WAS PAID FOR?\n" . str_repeat('=', 78) . "\n";
$amt = billing_amount(1, 'month'); $ord = rzp_create_order($amt['paise'], 'r', []); buy_order_open($install, $amt, $ord);
printf("Order created and recorded: 1 seat, 1 month, Rs %d\n\n", $amt['major']);

echo "1. A valid signature, while the browser form claims seats=500 & period=year\n";
echo "   (buy_apply_payment() does not even accept seats/period -- the form cannot be believed)\n";
[$ok, $t] = buy_apply_payment($install, $rec, 'order_PROBE', 'pay_A', $sign('order_PROBE', 'pay_A'));
$lic = ops_one("SELECT seats, exp, order_id, payment_id, amount FROM issued_licences WHERE install_id=? ORDER BY id DESC LIMIT 1", [$install]);
printf("   %s -> licence issued for %d seat(s) to %s, order %s, payment %s, Rs %d\n\n", $t, $lic['seats'], $lic['exp'], $lic['order_id'], $lic['payment_id'], $lic['amount']);

echo "2. The same valid POST, replayed\n";
$n = ops_val("SELECT COUNT(*) FROM issued_licences WHERE install_id=?", [$install]);
[$ok2, $t2] = buy_apply_payment($install, $lic + $rec, 'order_PROBE', 'pay_A', $sign('order_PROBE', 'pay_A'));
$n2 = ops_val("SELECT COUNT(*) FROM issued_licences WHERE install_id=?", [$install]);
printf("   %s -> licences before %d, after %d\n\n", $t2, $n, $n2);

echo "3. A validly signed payment for an order this workspace never started\n";
[$ok3, $t3] = buy_apply_payment($install, $rec, 'order_NOT_OURS', 'pay_B', $sign('order_NOT_OURS', 'pay_B'));
printf("   %s\n\n", $t3);
echo str_repeat('=', 78) . "\n";
echo ((int)$lic['seats'] === 1 && $n2 == $n && !$ok2 && !$ok3)
    ? "RESULT: the licence carries what the ORDER was priced for; a replay issues nothing;\n        an unknown order is refused.\n"
    : "RESULT: *** a guard did not hold -- see above ***\n";
