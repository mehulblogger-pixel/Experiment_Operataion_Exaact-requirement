<?php
// Owner, 8 Nov 2026 — payments applied once and never lost, and refunds by the
// owner's rules (lib/saas_payments.php). Runs end to end on a real practice
// company, with Razorpay played by a stand-in ($GLOBALS['__rzp_transport']).
t_section('Company payments: never lost, applied once, refunded by the owner\'s rules');

$root    = dirname(__DIR__);
$regFile = $root . '/tenants.php';
$hadReg  = is_file($regFile);
$regBak  = $hadReg ? file_get_contents($regFile) : null;
$prevSess = $_SESSION['saas_tenant'] ?? null;
file_put_contents($regFile, "<?php return " . var_export(['base_domain' => 'ops.example.com', 'aliases' => [], 'tenants' => []], true) . ";\n");
selfserve_migrate();

// ---- Razorpay, played by a stand-in ------------------------------------------
$RZ = ['payments' => [], 'orders' => [], 'refunds' => [], 'calls' => []];
$GLOBALS['__rzp_transport'] = function ($method, $url, $body, $user, $pass) use (&$RZ) {
    $RZ['calls'][] = [$method, $url, $body];
    if ($method === 'GET' && preg_match('#/v1/orders/([^/]+)/payments$#', $url, $m))
        return [200, json_encode(['items' => $RZ['payments'][$m[1]] ?? []])];
    if ($method === 'GET' && preg_match('#/v1/orders/([^/?]+)$#', $url, $m))
        return isset($RZ['orders'][$m[1]]) ? [200, json_encode($RZ['orders'][$m[1]])] : [404, '{}'];
    if ($method === 'POST' && preg_match('#/v1/payments/([^/]+)/refund$#', $url, $m)) {
        $id = 'rfnd_' . (count($RZ['refunds']) + 1);
        $RZ['refunds'][] = ['payment' => $m[1], 'amount' => (int) (json_decode($body, true)['amount'] ?? 0), 'id' => $id];
        return [200, json_encode(['id' => $id, 'status' => 'processed'])];
    }
    return [404, '{}'];
};
$captured = fn($id, $rupees) => ['id' => $id, 'status' => 'captured', 'amount' => $rupees * 100];

$res = saas_company_create(['company' => 'Refund Test Co ' . getmypid(), 'owner_email' => 'refund+' . getmypid() . '@selfserve.test',
    'owner_name' => 'R Owner', 'plan' => 'TRIAL', 'source' => 'self']);
t_ok(!empty($res['ok']), 'a self-serve company is created for the check');
$key = (string) ($res['key'] ?? '');
saas_tenant_upsert($key, ['source' => 'self']);
$ctlKeys = ['rzp_key_id' => (string) setting_get('rzp_key_id', ''), 'rzp_key_secret' => (string) setting_get('rzp_key_secret', ''),
            'rzp_webhook_secret' => (string) setting_get('rzp_webhook_secret', '')];
setting_set('rzp_key_id', 'rzp_test_STANDIN'); setting_set('rzp_key_secret', 'standin-secret');
$cfg = billing_config();

saas_enter_tenant($key);
saas_tenant_ensure_ready($key);
saas_payments_migrate();
setting_set('saas_billing_mode', 'self');                  // as a company that signed itself up has
setting_set('saas_trial_until', date('Y-m-d', strtotime('+20 days')));   // still inside its free trial
setting_set('rzp_key_id', 'rzp_test_STANDIN'); setting_set('rzp_key_secret', 'standin-secret');
t_set_prices(1000, 500);
$today = date('Y-m-d');
$plan = fn($mods, $seats) => ['type' => 'NEW', 'mods' => $mods, 'seats' => $seats, 'packs' => 0, 'period' => 'month',
    'starts' => $today, 'expires' => date('Y-m-d', strtotime('+1 month')), 'price' => 0];
$mkOrder = function ($id, $kind, array $mods, $seats, $amount, array $q = [], $agoSec = 600) {
    saas_order_create($id, $kind, $mods, $seats, 'month', $amount, $q, ['on' => false, 'gross' => $amount]);
    db()->prepare("UPDATE saas_orders SET created_at=? WHERE order_id=?")->execute([date('c', time() - $agoSec), $id]);
};

// ---- 1) A payment whose page never came back is found and applied, once ---------
$mkOrder('order_A', 'plan', ['operations', 'sales'], 5, 4500, $plan(['operations', 'sales'], 5));
$RZ['payments']['order_A'] = [$captured('pay_A', 4500)];
$r = saas_orders_check_recent('check');
$oA = ops_one("SELECT * FROM saas_orders WHERE order_id='order_A'");
t_eq($r['applied'], 1, 'a paid order whose page never came back is applied by the check');
t_eq($oA['status'], 'PAID', 'it is marked paid');
t_eq($oA['applied_via'], 'check', 'and records that the check applied it');
t_eq((string) setting_get('billing_paid_until', ''), date('Y-m-d', strtotime('+1 month')), 'the plan is switched on');
t_ok(is_array(json_decode((string) $oA['before_json'], true)), 'what the company had before is kept, for a refund');
db()->prepare("UPDATE saas_orders SET checked_at='' WHERE order_id='order_A'")->execute();
$r = saas_orders_check_recent('nightly');
t_eq($r['applied'], 0, 'checking again applies nothing twice');

// A payment of a different amount is never applied.
$mkOrder('order_B', 'plan', ['operations'], 2, 2000, $plan(['operations'], 2));
$RZ['payments']['order_B'] = [$captured('pay_B', 1999)];
saas_orders_check_recent('check');
t_eq((string) ops_val("SELECT status FROM saas_orders WHERE order_id='order_B'"), 'CREATED', 'a payment that does not match the recorded price is not applied');
// A window opened a moment ago is left with the customer.
$mkOrder('order_C', 'plan', ['operations'], 2, 2000, $plan(['operations'], 2), 30);
$RZ['payments']['order_C'] = [$captured('pay_C', 2000)];
saas_orders_check_recent('check');
t_eq((string) ops_val("SELECT status FROM saas_orders WHERE order_id='order_C'"), 'CREATED', 'a payment window opened a moment ago is not touched');
db()->exec("DELETE FROM saas_orders WHERE order_id IN ('order_B','order_C')");

// ---- 2) Paid twice for one order: found, refunded in full, nothing else changes -
$RZ['payments']['order_A'] = [$captured('pay_A', 4500), $captured('pay_A2', 4500)];
db()->prepare("UPDATE saas_orders SET checked_at='' WHERE order_id='order_A'")->execute();
$r = saas_orders_check_recent('nightly');
t_eq($r['extra'], 1, 'a second payment for an order already paid is found');
$x = ops_one("SELECT * FROM saas_payment_extras WHERE payment_id='pay_A2'");
$untilBefore = (string) setting_get('billing_paid_until', '');
$rx = saas_refund_extra((int) $x['id'], $cfg, 'tester');
t_ok($rx['ok'], 'it is refunded');
t_eq(end($RZ['refunds'])['amount'], 450000, 'in full, through Razorpay');
t_eq((string) ops_val("SELECT status FROM saas_payment_extras WHERE id=?", [(int) $x['id']]), 'REFUNDED', 'and marked refunded');
t_eq((string) setting_get('billing_paid_until', ''), $untilBefore, 'nothing else about the plan changes');
t_ok(!saas_refund_extra((int) $x['id'], $cfg, 'tester')['ok'], 'it cannot be refunded twice');

// ---- 3) AI actions: an order on record, applied once ----------------------------
$mkOrder('order_AI', 'ai', [], 100, 500);
$aiBefore = ai_topup_month();
$o = saas_order_claim('order_AI', 'pay_AI');
saas_order_fulfil($o, 'pay_AI', 'page');
t_eq(ai_topup_month(), $aiBefore + 100, 'AI actions bought are added');
t_ok(saas_order_claim('order_AI', 'pay_AI') === null, 'a repeated callback finds nothing to apply (it used to add them again)');

// ---- 4) Part refund: a module and logins bought by mistake ----------------------
$oA = ops_one("SELECT * FROM saas_orders WHERE order_id='order_A'");
$p = saas_refund_plan($oA, ['scope' => 'part', 'reason' => 'mistake', 'mods_remove' => ['sales'], 'seats_remove' => 2]);
t_ok($p['ok'], 'a part refund is worked out: ' . ($p['error'] ?? ''));
t_ok($p['suggest'] > 0 && $p['suggest'] < 4500, 'with a suggested amount for the part taken back (₹' . $p['suggest'] . ')');
$nonce = bin2hex(random_bytes(8));
$rr = saas_refund_execute('order_A', ['scope' => 'part', 'reason' => 'mistake', 'mods_remove' => ['sales'], 'seats_remove' => 2, 'amount' => 1500, 'note' => 'meant 3 logins'], $cfg, 'tester', $nonce, $key);
t_ok($rr['ok'], 'the part refund is made: ' . ($rr['msg'] ?? ''));
t_eq(end($RZ['refunds'])['amount'], 150000, 'Razorpay is asked to return exactly ₹1,500');
$cur = saas_plan_current();
t_ok(!in_array('sales', $cur['mods'], true) && in_array('operations', $cur['mods'], true), 'the module refunded stops at once; the rest stays');
t_eq((int) $cur['seats'], 3, 'the logins refunded are taken off');
t_eq((string) ops_val("SELECT status FROM saas_orders WHERE order_id='order_A'"), 'PART_REFUNDED', 'the payment is marked part refunded');
t_eq((int) ops_val("SELECT refunded_amount FROM saas_orders WHERE order_id='order_A'"), 1500, 'with what was refunded');
t_ok((bool) ops_one("SELECT id FROM billing_orders WHERE period='refund' AND amount=-1500"), 'the company\'s Payment history shows it as a minus line');
$rr2 = saas_refund_execute('order_A', ['scope' => 'part', 'reason' => 'mistake', 'seats_remove' => 1, 'amount' => 10], $cfg, 'tester', $nonce, $key);
t_ok(!$rr2['ok'] && strpos($rr2['msg'], 'twice') !== false, 'the same form sent twice refunds nothing twice');
$p = saas_refund_plan(ops_one("SELECT * FROM saas_orders WHERE order_id='order_A'"), ['scope' => 'part', 'reason' => 'mistake', 'seats_remove' => 3]);
t_ok(!$p['ok'], 'logins can never go below the people active, nor more than the payment added');

// ---- 5) The window: mistakes within 3 days; our own error any time ---------------
db()->prepare("UPDATE saas_orders SET paid_at=? WHERE order_id='order_A'")->execute([date('c', time() - 5 * 86400)]);
$oA = ops_one("SELECT * FROM saas_orders WHERE order_id='order_A'");
$p = saas_refund_plan($oA, ['scope' => 'full', 'reason' => 'mistake']);
t_ok(!$p['ok'] && strpos($p['error'], 'within 3 days') !== false, 'a mistake reported after 3 days is not refunded (the owner\'s rule)');
$p = saas_refund_plan($oA, ['scope' => 'full', 'reason' => 'duplicate']);
t_ok(!$p['ok'], 'nor a "paid twice" reported after 3 days');
$p = saas_refund_plan($oA, ['scope' => 'full', 'reason' => 'platform']);
t_ok($p['ok'], 'our own error can be refunded at any time');
$p = saas_refund_plan($oA, ['scope' => 'part', 'reason' => 'platform', 'amount' => 200]);
t_ok($p['ok'] && !empty($p['plan']['none']), 'an overcharge (our error) can be returned without taking anything back');
$p = saas_refund_plan($oA, ['scope' => 'part', 'reason' => 'platform']);
t_ok(!$p['ok'], 'but the amount must then be typed');

// ---- 6) Full refund of the payment that started the plan: data only -------------
$rr = saas_refund_execute('order_A', ['scope' => 'full', 'reason' => 'platform', 'note' => 'wrong price shown'], $cfg, 'tester', bin2hex(random_bytes(8)), $key);
t_ok($rr['ok'], 'the rest of the payment is refunded');
t_eq(end($RZ['refunds'])['amount'], 300000, 'exactly what was left (₹3,000)');
t_eq((string) ops_val("SELECT status FROM saas_orders WHERE order_id='order_A'"), 'REFUNDED', 'the payment is marked refunded');
t_ok((string) setting_get('billing_paid_until', '') < $today, 'the paid period ends today');
t_eq(saas_access_state()['mode'], 'locked', 'the company keeps its data only — view-only, the trial is not given back');
t_ok(!saas_refund_plan(ops_one("SELECT * FROM saas_orders WHERE order_id='order_A'"), ['scope' => 'full', 'reason' => 'platform'])['ok'], 'nothing more can be refunded');

// ---- 7) A later payment must be refunded first -----------------------------------
$mkOrder('order_D', 'plan', ['operations'], 2, 2000, $plan(['operations'], 2));
saas_order_fulfil(saas_order_claim('order_D', 'pay_D'), 'pay_D', 'page');
$q = $plan(['operations', 'sales'], 4); $q['type'] = 'UPGRADE'; $q['coterm'] = true;
$mkOrder('order_E', 'plan', ['operations', 'sales'], 4, 1200, $q);
db()->prepare("UPDATE saas_orders SET paid_at=? WHERE order_id='order_D'")->execute([date('c', time() - 60)]);
saas_order_fulfil(saas_order_claim('order_E', 'pay_E'), 'pay_E', 'page');
$p = saas_refund_plan(ops_one("SELECT * FROM saas_orders WHERE order_id='order_D'"), ['scope' => 'full', 'reason' => 'mistake']);
t_ok(!$p['ok'] && strpos($p['error'], 'later payment') !== false, 'a full refund waits while a later payment changed the plan');
$rr = saas_refund_execute('order_E', ['scope' => 'full', 'reason' => 'mistake'], $cfg, 'tester', bin2hex(random_bytes(8)), $key);
t_ok($rr['ok'], 'the later one (an addition) is refunded in full');
$cur = saas_plan_current();
t_ok(!in_array('sales', $cur['mods'], true) && (int) $cur['seats'] === 2, 'and the company is back to exactly what it had before it');
t_ok((string) setting_get('billing_paid_until', '') >= $today, 'its earlier plan keeps running');

// ---- 8) Money first: if Razorpay refuses, nothing changes ------------------------
$keep = $GLOBALS['__rzp_transport'];
$GLOBALS['__rzp_transport'] = fn($m, $u, $b, $us, $p) => [400, json_encode(['error' => ['description' => 'The amount is more than the payment']])];
$rr = saas_refund_execute('order_D', ['scope' => 'full', 'reason' => 'mistake'], $cfg, 'tester', bin2hex(random_bytes(8)), $key);
$GLOBALS['__rzp_transport'] = $keep;
t_ok(!$rr['ok'] && strpos($rr['msg'], 'Nothing was changed') !== false, 'when Razorpay refuses, the refund is not recorded');
t_eq((string) ops_val("SELECT status FROM saas_orders WHERE order_id='order_D'"), 'PAID', 'and the payment and plan stay as they were');

$pay = null;
saas_leave_tenant(); if (function_exists('db_reset')) db_reset();

// ---- 9) Razorpay's notice: signed, and only a pointer to an order ----------------
setting_set('rzp_webhook_secret', 'webhook-secret-123456');
saas_enter_tenant($key);
$mkOrder('order_W', 'ai', [], 50, 300);
saas_leave_tenant(); if (function_exists('db_reset')) db_reset();
$RZ['orders']['order_W'] = ['id' => 'order_W', 'notes' => ['ws' => $key]];
$RZ['payments']['order_W'] = [$captured('pay_W', 300)];
$body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_W', 'order_id' => 'order_W']]]]);
[$code] = rzp_webhook_handle($body, 'forged');
t_eq($code, 400, 'a notice without the right signature is refused');
[$code, $msg] = rzp_webhook_handle($body, hash_hmac('sha256', $body, 'webhook-secret-123456'));
t_eq($code, 200, 'a signed notice is accepted');
saas_enter_tenant($key);
$w = ops_one("SELECT status, applied_via FROM saas_orders WHERE order_id='order_W'");
saas_leave_tenant(); if (function_exists('db_reset')) db_reset();
t_eq($w['status'] . '/' . $w['applied_via'], 'PAID/webhook', 'the company\'s payment is applied from the notice (read back from Razorpay)');
[$code] = rzp_webhook_handle($body, hash_hmac('sha256', $body, 'webhook-secret-123456'));
t_eq($code, 200, 'the same notice again is harmless');

// The platform owner's view of the company's payments.
$pay = saas_company_payments($key);
t_ok($pay['ok'] && count($pay['orders']) >= 3, 'Super Admin reads the company\'s payments');
t_ok((bool) array_filter($pay['extras'], fn($x) => $x['status'] === 'REFUNDED'), 'including the second payment and its refund');

// The panel the platform owner sees (Companies → Manage → Payments & refunds).
$sk = htmlspecialchars($key, ENT_QUOTES); $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES); $modules = defined('PRODUCT_MODULES') ? PRODUCT_MODULES : [];
ob_start(); try { require $root . '/views/ops/_company_payments.php'; } catch (Throwable $ex) { echo 'THREW ' . $ex->getMessage(); } $html = ob_get_clean();
t_ok(strpos($html, 'THREW') === false && strpos($html, 'Payments &amp; refunds') !== false, 'the Payments & refunds panel draws');
t_ok(strpos($html, 'value="company_refund"') !== false && strpos($html, 'name="nonce"') !== false, 'a payment with money left has a Refund form, sent once');
t_ok(substr_count($html, 'value="company_refund"') === count(array_filter($pay['orders'], fn($o) => (int) $o['left'] > 0)), 'and a fully refunded one has none');
t_ok(strpos($html, 'value="company_refund_extra"') === false, 'a second payment already refunded is not offered again');
t_ok(strpos($html, 'within 3 days of paying') !== false || strpos($html, 'within <b>') !== false || strpos($html, 'within 3 day') !== false, 'the rule is stated where the refund is made');

// ---- 10) GST credit note ----------------------------------------------------------
$inv = ['invoice_no' => 'EX/2026-27/0099', 'date' => '2026-11-05', 'company_key' => $key, 'order_id' => 'o', 'payment_id' => 'pay_X',
        'period' => 'month', 'rate' => 18, 'mode' => 'exclusive', 'net' => 1000, 'tax' => 180, 'cgst' => 90, 'sgst' => 90, 'igst' => 0,
        'total' => 1180, 'pos' => '27', 'buyer' => ['name' => 'B', 'gstin' => '', 'address' => '', 'state' => '27'], 'supplier' => [], 'sac' => '997331'];
$cn = commercial_credit_note_issue_row($inv, 590, 'Bought by mistake', 'rfnd_X', $key);
t_ok((bool) preg_match('#^CN/\d{4}-\d{2}/\d{4}$#', $cn) && strlen($cn) <= 16, 'a credit note takes its own number (' . $cn . ', ≤ 16 characters)');
$row = commercial_register_get($cn);
t_ok($row && commercial_is_credit_note($row) && $row['against'] === 'EX/2026-27/0099', 'it is in the GST register, against the invoice');
t_eq([(int) $row['net'], (int) $row['cgst'], (int) $row['sgst'], (int) $row['total']], [500, 45, 45, 590], 'with the taxable value and tax reduced in proportion');
t_ok(commercial_register_amend($cn, ['name' => 'X'], 'r', 't') !== '', 'a credit note is never "corrected" itself');

// ---- 11) The wiring --------------------------------------------------------------
$src = fn($f) => (string) file_get_contents($root . '/' . $f);
foreach (['lib/saas_tenants.php', 'lib/pricing_admin.php', 'lib/billing.php', 'lib/licenceissue.php', 'lib/mkt_pay.php'] as $f)
    t_ok(stripos(preg_replace('#//.*#', '', $src($f)), 'refunded automatically') === false, $f . ' no longer promises an automatic refund');
t_ok(strpos($src('index.php'), "\$route === 'razorpay-webhook'") < strpos($src('index.php'), 'require_login();'), 'the notice address is public (in front of the sign-in)');
t_ok(strpos($src('index.php'), "\$route === 'razorpay-webhook'") < strpos($src('index.php'), 'superadmin_address_gate($route);'),
     'and is accepted even on the Super Admin address (whose gate turns away every other page)');
t_ok(strpos($src('lib/saas_payments.php'), "superadmin_main_address()") !== false, 'Platform setup shows the main address to give Razorpay');
t_ok(strpos($src('cron.php'), 'saas_payments_check_all()') !== false, 'the nightly job checks every company\'s payments');
t_ok(strpos($src('lib/platform_mail.php'), "'key' => 'webhook'") !== false, 'the go-live checklist asks for the webhook');
t_ok(strpos($src('views/ops/billing_pay.php'), 'saas_refund_policy_text()') !== false, 'the refund rule is shown before paying');

// Clean up.
saas_company_purge($key, 'test clean-up');
foreach ($ctlKeys as $k => $v) setting_set($k, $v);
unset($GLOBALS['__rzp_transport']);
if ($hadReg) file_put_contents($regFile, $regBak); else @unlink($regFile);
if ($prevSess === null) unset($_SESSION['saas_tenant']); else $_SESSION['saas_tenant'] = $prevSess;
if (function_exists('db_reset')) db_reset();
