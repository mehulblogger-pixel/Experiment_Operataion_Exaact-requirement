<?php
// ============================================================================
//  Per-user self-service billing (Razorpay)
//
//  Both delivery models are per user (per seat): a self-hosted install and a
//  SaaS workspace both pay for a number of active people, monthly or yearly.
//  The seat LIMIT and its enforcement already live in licencekey.php; this file
//  adds the missing half — taking the money and turning a payment into paid
//  seats.
//
//  Flow: the admin picks a seat count and monthly/annual, the server creates a
//  Razorpay order, Razorpay's checkout collects the card, and on success the
//  browser hands back a payment id and signature. The server verifies the
//  signature (HMAC with the secret key — a forged callback cannot pass) and only
//  then grants the seats. A grant is seats + a paid-until date, which lk_state()
//  reads as a live, paid subscription on top of any signed key.
//
//  The price is configurable — set per user, per month and per year, in the
//  vendor's own currency, on the billing screen. Nothing is hard-coded.
// ============================================================================

function billing_config() {
    $kid = (string) setting_get('rzp_key_id', '');
    $sec = (string) setting_get('rzp_key_secret', '');
    return [
        'currency'    => (string) (setting_get('billing_currency', '') ?: 'INR'),
        'price_month' => (int) setting_get('billing_price_user_month', 0),   // major units (e.g. rupees)
        'price_year'  => (int) setting_get('billing_price_user_year', 0),
        'key_id'      => $kid,
        'key_secret'  => $sec,
        'enabled'     => $kid !== '' && $sec !== '',
    ];
}
function billing_configured() { return billing_config()['enabled']; }
function billing_can_manage() { return function_exists('is_master') && (is_master() || can('settings.manage')); }

// Amount for N seats over a period. Razorpay works in the smallest currency unit
// (paise for INR), so the API amount is major × 100.
function billing_amount($seats, $period) {
    $c = billing_config();
    $seats = max(1, (int) $seats);
    $period = $period === 'year' ? 'year' : 'month';
    $unit = $period === 'year' ? $c['price_year'] : $c['price_month'];
    $major = $seats * $unit;
    return ['seats' => $seats, 'period' => $period, 'unit' => $unit, 'currency' => $c['currency'],
            'major' => $major, 'paise' => $major * 100];
}

// HTTP to Razorpay. Overridable for testing via $GLOBALS['__rzp_transport'] —
// a callable ($method,$url,$body,$user,$pass) => [httpStatus, body].
function rzp_http($method, $url, $body, $user, $pass) {
    if (!empty($GLOBALS['__rzp_transport']) && is_callable($GLOBALS['__rzp_transport'])) {
        [$s, $b] = ($GLOBALS['__rzp_transport'])($method, $url, $body, $user, $pass);
        return ['ok' => $s >= 200 && $s < 300, 'status' => (int) $s, 'body' => (string) $b];
    }
    if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'This server has no cURL, so Razorpay cannot be reached.'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $body, CURLOPT_USERPWD => $user . ':' . $pass,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 25,
    ]);
    $b = curl_exec($ch);
    $s = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $e = curl_error($ch);
    curl_close($ch);
    if ($b === false) return ['ok' => false, 'error' => 'Could not reach Razorpay: ' . $e];
    return ['ok' => $s >= 200 && $s < 300, 'status' => $s, 'body' => (string) $b];
}

// Create a Razorpay order. Returns ['ok'=>bool, 'id'=>orderId, 'amount'=>paise, 'error'=>...].
function rzp_create_order($paise, $receipt, array $notes = []) {
    $c = billing_config();
    if (!$c['enabled']) return ['ok' => false, 'error' => 'Payment is not set up yet — add your Razorpay keys first.'];
    if ((int) $paise <= 0) return ['ok' => false, 'error' => 'The price per user is not set, so there is nothing to charge.'];
    $payload = json_encode(['amount' => (int) $paise, 'currency' => $c['currency'],
                            'receipt' => substr((string) $receipt, 0, 40), 'notes' => $notes, 'payment_capture' => 1]);
    $r = rzp_http('POST', 'https://api.razorpay.com/v1/orders', $payload, $c['key_id'], $c['key_secret']);
    if (!$r['ok']) {
        $msg = $r['error'] ?? '';
        if ($msg === '') { $j = json_decode($r['body'] ?? '', true); $msg = $j['error']['description'] ?? 'Razorpay refused the order.'; }
        return ['ok' => false, 'error' => $msg];
    }
    $j = json_decode($r['body'], true);
    if (!is_array($j) || empty($j['id'])) return ['ok' => false, 'error' => 'Razorpay did not return an order id.'];
    return ['ok' => true, 'id' => (string) $j['id'], 'amount' => (int) ($j['amount'] ?? $paise)];
}

// Verify the checkout signature Razorpay hands back. hash_hmac over
// "order_id|payment_id" with the secret key. A forged callback cannot match.
function rzp_verify_signature($orderId, $paymentId, $signature) {
    $c = billing_config();
    if ($c['key_secret'] === '') return false;
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $c['key_secret']);
    return hash_equals($expected, (string) $signature);
}

// Is a locally-stored paid seat grant TRUSTWORTHY on this install?
//
// The number of paid seats lives in the database. On our own SaaS servers the
// customer cannot touch that database, so it is safe. On a customer's OWN server
// (self-hosted) they could edit it to hand themselves free users — so there the
// seat count must come only from the cryptographically SIGNED licence key, which
// they cannot forge. This overlay therefore stands down on a self-hosted,
// licence-enforced install and lets the signed key govern.
function billing_self_managed() {
    if (function_exists('saas_enabled') && saas_enabled()) return true;   // our cloud — we own the DB
    if (function_exists('lk_enforcing') && lk_enforcing()) return false;  // sold + enforced = self-hosted
    return true;                                                          // dev / unlicensed — nothing is enforced anyway
}

// The live paid grant: seats and the date they are paid until. Empty on a
// self-hosted licensed install, where the signed key is the only authority.
function billing_grant() {
    if (!billing_self_managed()) return ['active' => false, 'seats' => 0, 'until' => '', 'managed' => false];
    $until = (string) setting_get('billing_paid_until', '');
    $seats = (int) setting_get('billing_seats', 0);
    return ['active' => $until !== '' && $until >= date('Y-m-d') && $seats > 0, 'seats' => $seats, 'until' => $until, 'managed' => true];
}

// Turn a verified payment into paid seats: set the plan to $seats, and extend
// the paid-until date by one period from the later of today and the current
// paid-until (so paying early stacks time rather than losing it).
function billing_apply($seats, $period, $paymentId = '', $orderId = '') {
    $seats = max(1, (int) $seats);
    $period = $period === 'year' ? 'year' : 'month';
    $cur = (string) setting_get('billing_paid_until', '');
    $from = ($cur !== '' && $cur >= date('Y-m-d')) ? $cur : date('Y-m-d');
    $until = date('Y-m-d', strtotime($from . ($period === 'year' ? ' +1 year' : ' +1 month')));
    setting_set('billing_seats', (string) $seats);
    setting_set('billing_paid_until', $until);
    billing_record($seats, $period, $paymentId, $orderId, $until);
    if (function_exists('lk_state')) lk_state(true);   // refresh the enforcement snapshot
    return ['seats' => $seats, 'until' => $until];
}

function billing_migrate() {
    static $done = false; if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS billing_orders (
            id " . (function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT') . ",
            seats INT DEFAULT 0, period VARCHAR(10) DEFAULT '', amount INT DEFAULT 0,
            currency VARCHAR(8) DEFAULT '', order_id VARCHAR(60) DEFAULT '', payment_id VARCHAR(60) DEFAULT '',
            paid_until VARCHAR(20) DEFAULT '', by_user VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) {}
}

function billing_record($seats, $period, $paymentId, $orderId, $until) {
    try {
        billing_migrate();
        $a = billing_amount($seats, $period);
        db()->prepare("INSERT INTO billing_orders (seats,period,amount,currency,order_id,payment_id,paid_until,by_user,created_at)
                       VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$seats, $period, $a['major'], $a['currency'], (string) $orderId, (string) $paymentId, $until,
                       function_exists('user_name') ? user_name(current_user()) : 'system', date('c')]);
    } catch (Throwable $e) {}
}

function billing_history($limit = 20) {
    try { billing_migrate(); return ops_all("SELECT * FROM billing_orders ORDER BY id DESC LIMIT " . (int) $limit); }
    catch (Throwable $e) { return []; }
}

// ---- The screen ------------------------------------------------------------
function ops_billing($route, $method) {
    ops_require(billing_can_manage(), 'You cannot manage billing.');

    if ($route === 'billing-config' && $method === 'POST') {
        ops_require(is_master(), 'Only the Master Admin can change the price and payment keys.');
        setting_set('billing_currency', strtoupper(substr(trim((string) ($_POST['currency'] ?? 'INR')), 0, 5)) ?: 'INR');
        setting_set('billing_price_user_month', (string) max(0, (int) ($_POST['price_month'] ?? 0)));
        setting_set('billing_price_user_year', (string) max(0, (int) ($_POST['price_year'] ?? 0)));
        setting_set('rzp_key_id', trim((string) ($_POST['rzp_key_id'] ?? '')));
        if (trim((string) ($_POST['rzp_key_secret'] ?? '')) !== '')      // blank = keep existing
            setting_set('rzp_key_secret', trim((string) $_POST['rzp_key_secret']));
        flash('Billing settings saved.');
        redirect('/billing');
    }

    // Step 1 → create the order and hand it to the checkout page.
    if ($route === 'billing-order' && $method === 'POST') {
        if (!billing_self_managed()) { flash('On this install the user count is set by your licence key — buying seats here would not take effect. Renew or upgrade your licence instead.', 'error'); redirect('/billing'); }
        if (!billing_configured()) { flash('Payment is not set up yet.', 'error'); redirect('/billing'); }
        $a = billing_amount((int) ($_POST['seats'] ?? 1), (string) ($_POST['period'] ?? 'month'));
        if ($a['paise'] <= 0) { flash('Set a price per user first.', 'error'); redirect('/billing'); }
        $ord = rzp_create_order($a['paise'], 'seats-' . $a['seats'] . '-' . date('ymdHis'),
            ['seats' => $a['seats'], 'period' => $a['period']]);
        if (empty($ord['ok'])) { flash('Could not start the payment: ' . $ord['error'], 'error'); redirect('/billing'); }
        view('ops/billing_pay', ['amt' => $a, 'order' => $ord, 'cfg' => billing_config()]);
        return;
    }

    // Step 2 → Razorpay called back. Verify, then grant the seats.
    if ($route === 'billing-verify' && $method === 'POST') {
        $orderId   = (string) ($_POST['razorpay_order_id'] ?? '');
        $paymentId = (string) ($_POST['razorpay_payment_id'] ?? '');
        $sig       = (string) ($_POST['razorpay_signature'] ?? '');
        $seats     = (int) ($_POST['seats'] ?? 0);
        $period    = (string) ($_POST['period'] ?? 'month');
        if (!rzp_verify_signature($orderId, $paymentId, $sig)) {
            flash('That payment could not be verified. If money was taken, it will be refunded automatically — nothing was activated.', 'error');
            redirect('/billing');
        }
        $r = billing_apply($seats, $period, $paymentId, $orderId);
        flash('Payment received. Your plan now covers ' . $r['seats'] . ' '
            . ($r['seats'] === 1 ? 'person' : 'people') . ', paid until ' . fdate($r['until']) . '.', 'success');
        redirect('/billing');
    }

    view('ops/billing', [
        'cfg'          => billing_config(),
        'grant'        => billing_grant(),
        'used'         => function_exists('lk_seats_used') ? lk_seats_used() : 0,
        'history'      => billing_history(),
        'self_managed' => billing_self_managed(),
        'lic'          => function_exists('lk_summary') ? lk_summary() : [],
    ]);
}
