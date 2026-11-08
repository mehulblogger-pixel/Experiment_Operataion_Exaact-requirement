<?php
// ============================================================================
//  Company payments — applied once, never lost, refunded by the rules
//  (owner, 8 Nov 2026)
//
//  1. APPLY ONCE. Every payment a company makes (its plan, add-ons, AI actions)
//     is recorded as an order before the Razorpay window opens (saas_orders) and
//     applied from that record exactly once — by the page that comes back from
//     Razorpay, by Razorpay's own notice (webhook), or by the check below,
//     whichever arrives first.
//
//  2. NEVER LOST. The order is created with "capture at once", so if the
//     customer pays and then closes the browser, the money is with Razorpay and
//     nothing came back to switch the purchase on. Two things now catch it:
//       • Razorpay's notice (/razorpay-webhook, signed with the webhook secret);
//       • a check that asks Razorpay about every order still waiting — when the
//         company opens Your subscription, when a payment cannot be confirmed,
//         and every night for every company.
//     The check also finds a second payment for an order already paid (a
//     customer who paid twice) and lists it for a refund.
//
//  3. REFUNDS — the owner's rules:
//       • No refunds as a rule: every company has a 30-day free trial first.
//       • Bought by mistake (extra logins, a module, storage, the wrong period)
//         or paid twice: refunded if asked within 3 days of paying.
//       • A full refund ends what that payment bought; if it was the payment
//         that started the paid plan, the company keeps access to its data only
//         (view-only). A part refund ends exactly the refunded part, at once.
//     The money goes back through Razorpay, the payment is marked refunded, and
//     a GST credit note is issued against the tax invoice.
//
//  Platform owner only (superadmin_can()); no new permission.
// ============================================================================

// The owner's refund window, in days (8 Nov 2026: "within 3 days of purchase").
function saas_refund_days() {
    $v = function_exists('setting_get') ? trim((string) setting_get('saas_refund_days', '')) : '';
    return $v === '' ? 3 : max(0, (int) $v);
}

// Why a payment is refunded. 'anytime' = not bound by the 3-day window.
function saas_refund_reasons() {
    return [
        'mistake'  => ['label' => 'Bought by mistake — extra logins, a module, storage or the wrong period', 'anytime' => false],
        'duplicate'=> ['label' => 'Paid twice for the same thing', 'anytime' => false],
        'platform' => ['label' => 'Our error — a wrong price, or it could not be switched on', 'anytime' => true],
    ];
}

function saas_payments_migrate() {
    static $doneAt = -1;
    $ep = function_exists('db_epoch') ? db_epoch() : 0;
    if ($doneAt === $ep) return; $doneAt = $ep;
    if (function_exists('selfserve_migrate')) selfserve_migrate();
    if (!function_exists('db')) return;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    if (function_exists('ensure_column')) foreach ([
        'before_json' => 'TEXT', 'applied_via' => "VARCHAR(20) DEFAULT ''",
        'refunded_amount' => 'INT DEFAULT 0', 'checked_at' => "VARCHAR(30) DEFAULT ''",
    ] as $col => $def) { try { ensure_column('saas_orders', $col, $def); } catch (Throwable $e) {} }
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS saas_refunds (
            id $pk, order_id VARCHAR(80) DEFAULT '', payment_id VARCHAR(80) DEFAULT '', refund_id VARCHAR(80) DEFAULT '',
            amount INT DEFAULT 0, scope VARCHAR(10) DEFAULT '', reason VARCHAR(20) DEFAULT '', note VARCHAR(500) DEFAULT '',
            items_json TEXT, credit_note_no VARCHAR(30) DEFAULT '', status VARCHAR(12) DEFAULT 'STARTED', error VARCHAR(500) DEFAULT '',
            nonce VARCHAR(64) UNIQUE, by_name VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', done_at VARCHAR(30) DEFAULT '')");
        db()->exec("CREATE TABLE IF NOT EXISTS saas_payment_extras (
            id $pk, order_id VARCHAR(80) DEFAULT '', payment_id VARCHAR(80) UNIQUE, amount INT DEFAULT 0,
            status VARCHAR(12) DEFAULT 'OPEN', refund_id VARCHAR(80) DEFAULT '', found_at VARCHAR(30) DEFAULT '', refunded_at VARCHAR(30) DEFAULT '')");
    } catch (Throwable $e) { /* never block the boot chain */ }
}

// ---------------------------------------------------------------------------
//  1. Apply a paid order — once
// ---------------------------------------------------------------------------

// What the company has right now, so a refund can put back exactly what one
// payment changed.
function saas_plan_snapshot() {
    $g = fn($k) => function_exists('setting_get') ? (string) setting_get($k, '') : '';
    return [
        'paid_modules' => $g('saas_paid_modules'), 'seats' => (int) $g('saas_seat_limit'), 'floor' => (int) $g('saas_seat_floor'),
        'packs' => (int) $g('saas_storage_packs'), 'period' => $g('saas_paid_period'), 'from' => $g('saas_paid_from'),
        'until' => $g('billing_paid_until'), 'next' => $g('saas_next_plan'), 'converted_at' => $g('saas_converted_at'),
        'trial_until' => $g('saas_trial_until'),
        'ai_setting' => function_exists('ai_topup_setting') ? ai_topup_setting() : '',
        'ai_topup' => function_exists('ai_topup_month') ? ai_topup_month() : 0,
    ];
}

// Apply a claimed order (status PAID, just claimed by saas_order_claim()).
// $via: 'page' (the browser came back), 'webhook' or 'check'.
// Returns ['ok' => bool, 'msg' => what to tell the customer, 'back' => where].
function saas_order_fulfil(array $o, $paymentId, $via = 'page') {
    saas_payments_migrate();
    $orderId = (string) $o['order_id'];
    $kind = (string) $o['kind'];
    $fd = fn($d) => function_exists('fdate') ? fdate($d) : $d;
    $before = saas_plan_snapshot();
    $msg = 'Payment received.'; $back = '/subscription';
    if ($kind === 'plan') {
        $q = json_decode((string) $o['quote_json'], true);
        $q = saas_plan_change_apply(is_array($q) ? $q : [], $paymentId, $orderId);
        $msg = 'Payment received. ' . (!empty($q['converted']) ? 'Your free trial is now a paid plan. ' : '') . (($q['type'] ?? '') === 'RENEW'
            ? 'Your plan is renewed and now runs until ' . $fd($q['expires']) . '.'
            : 'Your plan is active until ' . $fd($q['expires'] ?? '') . '.');
    } elseif ($kind === 'add') {
        $mods = array_values(array_filter(array_map('trim', explode(',', (string) $o['modules']))));
        $r = saas_selfservice_apply($mods, (int) $o['seats'], (string) $o['period'], $paymentId, $orderId);
        $bits = [];
        if ($r['added'])        $bits[] = count($r['added']) . ' module' . (count($r['added']) === 1 ? '' : 's');
        if ($r['seat_add'] > 0) $bits[] = $r['seat_add'] . ' seat' . ($r['seat_add'] === 1 ? '' : 's');
        $msg = 'Payment received. Added ' . ($bits ? implode(' and ', $bits) : 'your purchase') . '. Your plan is active until ' . $fd($r['paid_until']) . '.';
    } elseif ($kind === 'ai') {
        $n = max(0, (int) $o['seats']);
        if (function_exists('ai_topup_add')) ai_topup_add($n);
        if (function_exists('billing_record_line')) billing_record_line(0, 'once', $n . ' AI actions', $paymentId, $orderId, date('Y-m-d'));
        if (function_exists('idems_log')) idems_log('setting', null, 'AI_TOPUP_PURCHASED', ['field' => 'ai_topup', 'new' => $n]);
        $msg = 'Payment received — ' . $n . ' more AI actions added for this month. You now have '
             . (function_exists('ai_pool_remaining') ? ai_pool_remaining() : $n) . ' left.';
        $back = '/ai-forms';
    } elseif ($kind === 'seats') {
        $r = billing_apply((int) $o['seats'], (string) $o['period'], $paymentId, $orderId);
        $msg = 'Payment received. Your plan now covers ' . $r['seats'] . ' ' . ($r['seats'] === 1 ? 'person' : 'people') . ', paid until ' . $fd($r['until']) . '.';
        $back = '/billing';
    }
    try { db()->prepare("UPDATE saas_orders SET before_json=?, applied_via=? WHERE order_id=?")->execute([json_encode($before), (string) $via, $orderId]); }
    catch (Throwable $e) {}
    if ($via !== 'page' && function_exists('idems_log'))
        idems_log('setting', null, 'PAYMENT_APPLIED_LATE', ['field' => $orderId, 'new' => $via]);
    return ['ok' => true, 'msg' => $msg, 'back' => $back];
}

// The page that comes back from Razorpay could not be trusted (or never came):
// what to tell the customer, honestly. The order is checked with Razorpay at
// once; the money is never kept for nothing.
function saas_payment_unconfirmed_msg($paymentId) {
    return 'We could not confirm this payment on this page. If money left your account, nothing is lost: '
         . 'we check every payment with Razorpay and switch on what was paid for — usually within minutes, at the latest overnight. '
         . 'If it cannot be switched on, it is refunded in full.'
         . ((string) $paymentId !== '' ? ' Your payment reference: ' . $paymentId . '.' : '');
}

// ---------------------------------------------------------------------------
//  2. Never lost — ask Razorpay
// ---------------------------------------------------------------------------

// The captured payments Razorpay holds for one order: [[id, amount (major)], …],
// or null when Razorpay could not be asked.
function rzp_order_payments($orderId, array $cfg = null) {
    $c = $cfg ?: (function_exists('billing_config') ? billing_config() : []);
    if (empty($c['key_id']) || empty($c['key_secret'])) return null;
    $r = rzp_http('GET', 'https://api.razorpay.com/v1/orders/' . rawurlencode((string) $orderId) . '/payments', '', $c['key_id'], $c['key_secret']);
    if (empty($r['ok'])) return null;
    $j = json_decode((string) ($r['body'] ?? ''), true);
    $out = [];
    foreach ((array) ($j['items'] ?? []) as $p)
        if (($p['status'] ?? '') === 'captured') $out[] = ['id' => (string) $p['id'], 'amount' => (int) round(((int) ($p['amount'] ?? 0)) / 100)];
    return $out;
}

// Check one order with Razorpay. Applies a captured payment to a waiting order
// (once), and records any second captured payment as an extra to refund.
// Returns 'applied', 'extra', 'none', 'waiting' or 'unreachable'.
function saas_order_check($orderId, $via = 'check', array $cfg = null) {
    saas_payments_migrate();
    $o = ops_one("SELECT * FROM saas_orders WHERE order_id=?", [(string) $orderId]);
    if (!$o) return 'none';
    $pays = rzp_order_payments($orderId, $cfg);
    try { db()->prepare("UPDATE saas_orders SET checked_at=? WHERE order_id=?")->execute([date('c'), (string) $orderId]); } catch (Throwable $e) {}
    if ($pays === null) return 'unreachable';
    $result = $o['status'] === 'CREATED' ? 'waiting' : 'none';
    foreach ($pays as $p) {
        if ($p['amount'] !== (int) $o['amount']) continue;   // never apply a payment that does not match what was priced
        $o = ops_one("SELECT * FROM saas_orders WHERE order_id=?", [(string) $orderId]);
        if ($o['status'] === 'CREATED') {
            $claimed = saas_order_claim($orderId, $p['id']);
            if ($claimed) { saas_order_fulfil($claimed, $p['id'], $via); $result = 'applied'; }
            continue;
        }
        if ((string) $o['payment_id'] !== $p['id'] && saas_payment_extra_add($orderId, $p['id'], $p['amount'])) $result = 'extra';
    }
    return $result;
}

// A second payment for an order that was already paid — the customer paid
// twice. Recorded once; it is refunded in full (nothing was given for it).
function saas_payment_extra_add($orderId, $paymentId, $amount) {
    saas_payments_migrate();
    try {
        if (ops_one("SELECT id FROM saas_payment_extras WHERE payment_id=?", [(string) $paymentId])) return false;
        db()->prepare("INSERT INTO saas_payment_extras (order_id,payment_id,amount,status,found_at) VALUES (?,?,?,'OPEN',?)")
            ->execute([(string) $orderId, (string) $paymentId, (int) $amount, date('c')]);
        if (function_exists('idems_log')) idems_log('setting', null, 'PAYMENT_EXTRA_FOUND', ['field' => (string) $orderId, 'new' => (string) $paymentId]);
        return true;
    } catch (Throwable $e) { return false; }
}

// Check this company's orders: every order still waiting (opened within the
// last 3 days, not asked about in the last 10 minutes), and the ones paid in
// the last 7 days for a second payment. Cheap when there is nothing to check.
function saas_orders_check_recent($via = 'check', array $cfg = null, $now = null, $limit = null) {
    // A page view asks about a few orders at most, so it never waits long on
    // Razorpay; the nightly run asks about all of them.
    if ($limit === null) $limit = $via === 'nightly' ? 0 : 3;
    saas_payments_migrate();
    $now = $now ?: time();
    $c = $cfg ?: (function_exists('billing_config') ? billing_config() : []);
    $out = ['checked' => 0, 'applied' => 0, 'extra' => 0];
    if (empty($c['key_id']) || empty($c['key_secret'])) return $out;
    try {
        $rows = ops_all("SELECT order_id, status, created_at, paid_at, checked_at FROM saas_orders
                         WHERE (status='CREATED' AND created_at > ?) OR (status IN ('PAID','PART_REFUNDED') AND paid_at > ?)",
                        [date('c', $now - 3 * 86400), date('c', $now - 7 * 86400)]);
    } catch (Throwable $e) { return $out; }
    foreach ((array) $rows as $r) {
        // A window just opened is still with the customer; and nobody is asked about twice in ten minutes.
        if ($r['status'] === 'CREATED' && strtotime((string) $r['created_at']) > $now - 120) continue;
        if ((string) $r['checked_at'] !== '' && strtotime((string) $r['checked_at']) > $now - 600) continue;
        // A paid order is looked at again only in the nightly run (a second payment is rare).
        if ($r['status'] !== 'CREATED' && $via !== 'nightly') continue;
        if ($limit > 0 && $out['checked'] >= $limit) break;
        $out['checked']++;
        $res = saas_order_check((string) $r['order_id'], $via === 'nightly' ? 'check' : $via, $c);
        if ($res === 'applied') $out['applied']++;
        if ($res === 'extra') $out['extra']++;
    }
    return $out;
}

// Nightly, from the control install: every company, then the control install itself.
function saas_payments_check_all(array $cfg = null) {
    $out = ['companies' => 0, 'applied' => 0, 'extra' => 0, 'lines' => []];
    if (function_exists('current_tenant') && current_tenant() !== '') return $out;
    $c = $cfg ?: (function_exists('billing_config') ? billing_config() : []);
    if (empty($c['key_id']) || empty($c['key_secret'])) return $out;
    $keys = array_map(fn($t) => (string) $t['tenant_key'], function_exists('saas_tenant_all') ? saas_tenant_all() : []);
    $reg = function_exists('tenant_registry') ? tenant_registry() : [];
    foreach ($keys as $key) {
        $path = (string) ($reg['tenants'][$key]['sqlite'] ?? '');
        if ($path !== '' && !is_file($path)) continue;           // never opened: nothing to check
        $prev = $_SESSION['saas_tenant'] ?? null;
        try {
            if (saas_enter_tenant($key)) {
                $rt = []; try { db(); $rt = $GLOBALS['__tenant'] ?? []; } catch (Throwable $e) {}
                if (strtolower((string) ($rt['key'] ?? '')) === strtolower($key) && ($rt['error'] ?? '') === '') {
                    $r = saas_orders_check_recent('nightly', $c);
                    $out['companies']++; $out['applied'] += $r['applied']; $out['extra'] += $r['extra'];
                    if ($r['applied']) $out['lines'][] = $key . ': ' . $r['applied'] . ' payment(s) that never came back were applied';
                    if ($r['extra'])   $out['lines'][] = $key . ': ' . $r['extra'] . ' second payment(s) found — refund them on Companies → Manage';
                }
            }
        } catch (Throwable $e) { $out['lines'][] = $key . ': payments could not be checked — ' . $e->getMessage(); }
        if ($prev === null) unset($_SESSION['saas_tenant']); else $_SESSION['saas_tenant'] = $prev;
        if (function_exists('db_reset')) db_reset();
        if (function_exists('licence_disabled')) licence_disabled(true);
    }
    $r = saas_orders_check_recent('nightly', $c);                 // the control install's own orders
    $out['applied'] += $r['applied']; $out['extra'] += $r['extra'];
    return $out;
}

// ---- Razorpay's notice (webhook) -------------------------------------------------
// Set up once in the Razorpay Dashboard → Webhooks: this address, the events
// "payment.captured" and "order.paid", and a secret that is also saved on
// Platform setup. The notice only says WHICH order to look at; what is applied
// is always read back from Razorpay with the platform's keys, so a forged or
// replayed notice can change nothing.
function rzp_webhook_url() {
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    return $host !== '' ? 'https://' . $host . '/razorpay-webhook' : '/razorpay-webhook';
}
function rzp_webhook_handle($raw, $signature) {
    $secret = function_exists('setting_get') ? (string) setting_get('rzp_webhook_secret', '') : '';
    if ($secret === '') return [503, 'The webhook secret is not set on Platform setup.'];
    if ($signature === '' || !hash_equals(hash_hmac('sha256', (string) $raw, $secret), (string) $signature)) return [400, 'Bad signature.'];
    $ev = json_decode((string) $raw, true);
    if (!is_array($ev)) return [400, 'Not JSON.'];
    $event = (string) ($ev['event'] ?? '');
    if (!in_array($event, ['payment.captured', 'order.paid'], true)) return [200, 'Ignored: ' . $event];
    $orderId = (string) ($ev['payload']['payment']['entity']['order_id'] ?? ($ev['payload']['order']['entity']['id'] ?? ''));
    if ($orderId === '') return [200, 'No order.'];
    $cfg = billing_config();
    // Which company's records hold this order: the note written when it was
    // created, read back from Razorpay (not from the notice).
    $r = rzp_http('GET', 'https://api.razorpay.com/v1/orders/' . rawurlencode($orderId), '', $cfg['key_id'], $cfg['key_secret']);
    if (empty($r['ok'])) return [502, 'Razorpay could not be asked about the order; please retry.'];
    $ord = json_decode((string) $r['body'], true);
    $ws = strtolower(trim((string) ($ord['notes']['ws'] ?? '')));
    $res = 'none';
    if ($ws !== '') {
        if (!function_exists('saas_tenant_get') || !saas_tenant_get($ws)) return [200, 'Unknown company.'];
        $prev = $_SESSION['saas_tenant'] ?? null;
        try {
            if (saas_enter_tenant($ws)) {
                $rt = []; try { db(); $rt = $GLOBALS['__tenant'] ?? []; } catch (Throwable $e) {}
                if (strtolower((string) ($rt['key'] ?? '')) === $ws && ($rt['error'] ?? '') === '') $res = saas_order_check($orderId, 'webhook', $cfg);
            }
        } finally {
            if ($prev === null) unset($_SESSION['saas_tenant']); else $_SESSION['saas_tenant'] = $prev;
            if (function_exists('db_reset')) db_reset();
        }
    } else {
        $res = saas_order_check($orderId, 'webhook', $cfg);
    }
    return $res === 'unreachable' ? [502, 'Razorpay could not be asked; please retry.'] : [200, 'OK: ' . $res];
}
function rzp_webhook_route() {
    $raw = (string) file_get_contents('php://input');
    $sig = (string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');
    try { [$code, $msg] = rzp_webhook_handle($raw, $sig); }
    catch (Throwable $e) { $code = 500; $msg = 'Error.'; if (function_exists('failure_record_auto')) failure_record_auto('Razorpay notice could not be handled', $e->getMessage()); }
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['ok' => $code === 200, 'message' => $msg]);
    exit;
}

// ---------------------------------------------------------------------------
//  3. Refunds
// ---------------------------------------------------------------------------

// What a payment bought, in words, for the lists.
function saas_order_label(array $o) {
    $q = json_decode((string) ($o['quote_json'] ?? ''), true);
    $mods = array_values(array_filter(array_map('trim', explode(',', (string) $o['modules']))));
    $lbl = function ($k) { return defined('PRODUCT_MODULES') && isset(PRODUCT_MODULES[$k]) ? PRODUCT_MODULES[$k][0] : $k; };
    switch ((string) $o['kind']) {
        case 'ai':    return (int) $o['seats'] . ' AI actions';
        case 'seats': return (int) $o['seats'] . ' seats (' . ($o['period'] === 'year' ? 'yearly' : 'monthly') . ')';
        case 'add':   return 'Added: ' . implode(', ', array_map($lbl, $mods)) . ((int) $o['seats'] ? ($mods ? ' + ' : '') . (int) $o['seats'] . ' logins' : '');
        default:
            $type = is_array($q) ? (string) ($q['type'] ?? '') : '';
            $t = ['NEW' => 'New plan', 'UPGRADE' => 'Plan change', 'RENEW' => 'Renewal'][$type] ?? 'Plan';
            $packs = is_array($q) ? (int) ($q['packs'] ?? 0) : 0;
            return $t . ': ' . implode(', ', array_map($lbl, $mods)) . ' · ' . (int) $o['seats'] . ' logins'
                 . ($packs ? ' · ' . $packs . ' storage pack' . ($packs === 1 ? '' : 's') : '') . ' · ' . ($o['period'] === 'year' ? 'yearly' : 'monthly');
    }
}

// Days since the payment.
function saas_order_age_days(array $o, $now = null) {
    $t = strtotime((string) ($o['paid_at'] ?: $o['created_at']));
    return $t ? (int) floor((($now ?: time()) - $t) / 86400) : 9999;
}

// What can still be refunded on an order (major units).
function saas_order_refundable(array $o) { return max(0, (int) $o['amount'] - (int) ($o['refunded_amount'] ?? 0)); }

function saas_active_users() {
    try { return (int) ops_val("SELECT COUNT(*) FROM users WHERE COALESCE(is_active,1)=1"); } catch (Throwable $e) { return 0; }
}

// A later payment that changed the plan, which a full refund of $o would undo too.
function saas_order_has_later_plan_payment(array $o) {
    try {
        return (bool) ops_one("SELECT id FROM saas_orders WHERE kind IN ('plan','add') AND status IN ('PAID','PART_REFUNDED')
                               AND paid_at > ? AND order_id <> ?", [(string) $o['paid_at'], (string) $o['order_id']]);
    } catch (Throwable $e) { return false; }
}

// Work out what a refund would do, without doing anything. $req:
//   scope 'full'|'part', reason, seats_remove, mods_remove[], packs_remove, amount.
// Returns ['ok', 'error', 'amount', 'suggest', 'effect' (words), 'plan' (what to set)].
function saas_refund_plan(array $o, array $req, $now = null) {
    $scope = ($req['scope'] ?? '') === 'part' ? 'part' : 'full';
    $reason = (string) ($req['reason'] ?? '');
    $reasons = saas_refund_reasons();
    $left = saas_order_refundable($o);
    $out = ['ok' => false, 'error' => '', 'amount' => 0, 'suggest' => $left, 'effect' => '', 'plan' => null, 'scope' => $scope];
    if (!in_array((string) $o['status'], ['PAID', 'PART_REFUNDED'], true)) return ['error' => 'This payment has already been refunded in full.'] + $out;
    if ($left <= 0) return ['error' => 'Nothing is left to refund on this payment.'] + $out;
    if (!isset($reasons[$reason])) return ['error' => 'Choose why the payment is refunded.'] + $out;
    $age = saas_order_age_days($o, $now);
    if (!$reasons[$reason]['anytime'] && $age > saas_refund_days())
        return ['error' => 'Paid ' . $age . ' days ago. A refund for "' . $reasons[$reason]['label'] . '" is only given within '
                          . saas_refund_days() . ' days of paying (your rule: no refunds — every company has a free trial first).'] + $out;
    $kind = (string) $o['kind'];
    $before = json_decode((string) ($o['before_json'] ?? ''), true);
    $q = json_decode((string) ($o['quote_json'] ?? ''), true); $q = is_array($q) ? $q : [];
    $users = saas_active_users();
    $today = date('Y-m-d', $now ?: time());
    $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));

    if ($kind === 'ai') {
        if ($scope === 'part') return ['error' => 'AI actions are refunded as a whole.'] + $out;
        $setting = is_array($before) ? (string) ($before['ai_setting'] ?? '') : '';
        return ['ok' => true, 'amount' => $left, 'suggest' => $left,
                'effect' => 'The ' . (int) $o['seats'] . ' AI actions are taken off this month\'s allowance (those already used stay used).',
                'plan' => ['ai_remove' => (int) $o['seats'], 'ai_setting' => $setting]] + $out;
    }
    if (!in_array($kind, ['plan', 'add'], true)) return ['error' => 'This kind of payment is not refunded here.'] + $out;

    $cur = saas_plan_current();
    if ($scope === 'full') {
        if (saas_order_has_later_plan_payment($o))
            return ['error' => 'A later payment changed this plan after this one. Refund the later payment first, or refund part of this one (choose what to take back).'] + $out;
        $type = (string) ($q['type'] ?? ($kind === 'add' ? 'ADD' : ''));
        $b = is_array($before) ? $before : null;
        // The payment that started the paid plan: refunding it in full leaves the
        // company its data only (view-only) — the owner's rule.
        $noPaidBefore = ($b !== null) ? ((string) $b['until'] === '' || (string) $b['until'] < $today) : ($type === 'NEW');
        if ($noPaidBefore)
            return ['ok' => true, 'amount' => $left, 'suggest' => $left,
                    'effect' => 'Everything this payment bought ends today. The company keeps access to its data only (view-only: it can open, print and export, but not save) until it chooses a plan again.',
                    'plan' => ['end' => true, 'until' => $yesterday]] + $out;
        if ($type === 'RENEW') {
            $until = $b !== null ? (string) $b['until'] : (string) ($q['cur_until'] ?? '');
            if ($until === '') return ['error' => 'The date before this renewal is not known. Refund part of it instead.'] + $out;
            return ['ok' => true, 'amount' => $left, 'suggest' => $left,
                    'effect' => 'The renewal is cancelled: the plan ends on ' . $until . ' as it did before this payment.',
                    'plan' => ['restore' => ['until' => $until, 'next' => $b !== null ? (string) $b['next'] : '']]] + $out;
        }
        if ($b === null) return ['error' => 'This payment was made before refunds were tracked, so what the company had before it is not known. Refund part of it: choose what to take back.'] + $out;
        if ((int) $b['seats'] > 0 && $users > (int) $b['seats'])
            return ['error' => 'Before this payment the company had ' . (int) $b['seats'] . ' logins; ' . $users . ' people are active now. '
                              . 'Deactivate ' . ($users - (int) $b['seats']) . ' first (the company\'s administrator, or you through "Log in as" → Users), then refund.'] + $out;
        return ['ok' => true, 'amount' => $left, 'suggest' => $left,
                'effect' => 'The company goes back to exactly what it had before this payment: ' . (int) $b['seats'] . ' logins, its earlier modules and storage, until ' . $b['until'] . '.',
                'plan' => ['restore' => $b]] + $out;
    }

    // ---- Part: take back exactly what is chosen, from what this payment added.
    $orderMods = array_values(array_filter(array_map('trim', explode(',', (string) $o['modules']))));
    $hadMods = is_array($before) ? array_values(array_filter(array_map('trim', explode(',', (string) $before['paid_modules'])))) : [];
    $addedMods = array_values(array_diff($orderMods, $hadMods));
    $wantMods = array_values(array_intersect(array_map('strtolower', (array) ($req['mods_remove'] ?? [])), $addedMods, $cur['mods']));
    $seatsAdded = is_array($before) && (string) $before['until'] >= $today ? max(0, (int) $o['seats'] - (int) $before['seats']) : (int) $o['seats'];
    $packsAdded = is_array($before) && (string) $before['until'] >= $today ? max(0, (int) ($q['packs'] ?? 0) - (int) $before['packs']) : (int) ($q['packs'] ?? 0);
    $seatsRm = max(0, (int) ($req['seats_remove'] ?? 0));
    $packsRm = max(0, (int) ($req['packs_remove'] ?? 0));
    if (!$wantMods && $seatsRm === 0 && $packsRm === 0) {
        // Our own error — e.g. a wrong price: the overcharge goes back and nothing
        // is taken away. Any other reason must name what is taken back.
        if ($reason !== 'platform') return ['error' => 'Choose what to take back: logins, a module or storage packs.'] + $out;
        $amount = (int) ($req['amount'] ?? 0);
        if ($amount < 1 || $amount > $left)
            return ['error' => 'Type the amount to return (between ₹1 and ₹' . number_format($left) . ').', 'suggest' => 0] + $out;
        return ['ok' => true, 'amount' => $amount, 'suggest' => 0,
                'effect' => 'Nothing is taken back — only the overcharge is returned. The plan stays exactly as it is.',
                'plan' => ['none' => true]] + $out;
    }
    if ($seatsRm > $seatsAdded) return ['error' => 'This payment added ' . $seatsAdded . ' logins; no more than that can be taken back.'] + $out;
    if ($packsRm > $packsAdded) return ['error' => 'This payment added ' . $packsAdded . ' storage packs; no more than that can be taken back.'] + $out;
    $newMods = array_values(array_diff($cur['mods'], $wantMods));
    $newSeats = (int) $cur['seats'] - $seatsRm;
    $newPacks = max(0, (int) $cur['packs'] - $packsRm);
    if (!$newMods) return ['error' => 'That would take back every module — refund the whole payment instead.'] + $out;
    if ($newSeats < 1) return ['error' => 'At least one login must remain.'] + $out;
    if ($users > $newSeats)
        return ['error' => $users . ' people are active and the company would keep ' . $newSeats . ' logins. Deactivate ' . ($users - $newSeats)
                          . ' first (the company\'s administrator, or you through "Log in as" → Users), then refund.'] + $out;
    // A suggested amount: the share of what this payment added that is taken back.
    $period = (string) $cur['period'] ?: (string) $o['period'];
    $val = fn($m, $s, $p) => (int) (saas_company_quote($m, $s, $period, $p)['total'] ?? 0);
    $removed = max(0, $val($cur['mods'], $cur['seats'], $cur['packs']) - $val($newMods, $newSeats, $newPacks));
    $addedVal = max(1, $val($orderMods, (int) $o['seats'], (int) ($q['packs'] ?? 0)) - (is_array($before) && (string) $before['until'] >= $today ? $val($hadMods, (int) $before['seats'], (int) $before['packs']) : 0));
    $suggest = max(1, min($left, (int) round($left * min(1, $removed / $addedVal))));
    $amount = (int) ($req['amount'] ?? 0);
    if (isset($req['amount']) && ($amount < 1 || $amount > $left))
        return ['error' => 'The amount must be between ₹1 and ₹' . number_format($left) . ' (what is left of this payment).', 'suggest' => $suggest] + $out;
    $bits = [];
    if ($wantMods) $bits[] = implode(', ', array_map(fn($k) => defined('PRODUCT_MODULES') && isset(PRODUCT_MODULES[$k]) ? PRODUCT_MODULES[$k][0] : $k, $wantMods));
    if ($seatsRm) $bits[] = $seatsRm . ' login' . ($seatsRm === 1 ? '' : 's');
    if ($packsRm) $bits[] = $packsRm . ' storage pack' . ($packsRm === 1 ? '' : 's');
    return ['ok' => true, 'amount' => isset($req['amount']) ? $amount : $suggest, 'suggest' => $suggest,
            'effect' => 'Taken back today: ' . implode(' and ', $bits) . '. The company keeps the rest of its plan until ' . $cur['until'] . '.',
            'plan' => ['part' => ['mods' => $newMods, 'seats' => $newSeats, 'packs' => $newPacks, 'mods_rm' => $wantMods, 'seats_rm' => $seatsRm, 'packs_rm' => $packsRm]]] + $out;
}

// Put the plan into the state a refund decided. Runs inside the company.
function saas_refund_apply_plan(array $p) {
    if (!empty($p['end'])) {
        setting_set('billing_paid_until', (string) $p['until']);
        setting_set('saas_next_plan', '');
        // The free trial is not given back: a full refund leaves the data only.
        $tu = (string) setting_get('saas_trial_until', '');
        if ($tu === '' || $tu > (string) $p['until']) setting_set('saas_trial_until', (string) $p['until']);
        return;
    }
    if (isset($p['restore'])) {
        $b = $p['restore'];
        if (isset($b['paid_modules'])) {
            $mods = array_values(array_filter(array_map('trim', explode(',', (string) $b['paid_modules']))));
            saas_plan_switch_to($mods, (int) $b['seats'], (int) $b['packs']);
            setting_set('saas_seat_floor', (string) (int) $b['floor']);
            setting_set('saas_paid_period', (string) $b['period']);
            setting_set('saas_paid_from', (string) $b['from']);
        }
        setting_set('billing_paid_until', (string) $b['until']);
        setting_set('saas_next_plan', (string) ($b['next'] ?? ''));
        return;
    }
    if (isset($p['part'])) {
        $x = $p['part'];
        saas_plan_switch_to($x['mods'], (int) $x['seats'], (int) $x['packs']);
        // A renewal already paid for must not bring the refunded part back.
        $n = function_exists('saas_plan_next') ? saas_plan_next() : null;
        if ($n) {
            $n['mods'] = array_values(array_diff((array) $n['mods'], (array) $x['mods_rm']));
            $n['seats'] = max(1, (int) $n['seats'] - (int) $x['seats_rm']);
            $n['packs'] = max(0, (int) ($n['packs'] ?? 0) - (int) $x['packs_rm']);
            setting_set('saas_next_plan', $n['mods'] ? json_encode($n) : '');
        }
        return;
    }
    if (isset($p['ai_remove']) && function_exists('ai_topup_setting') && function_exists('ai_topup_month')) {
        // Only this month's allowance can be taken back; a past month is over.
        if ((string) ($p['ai_setting'] ?? '') === '' || (string) $p['ai_setting'] === ai_topup_setting())
            setting_set(ai_topup_setting(), (string) max(0, ai_topup_month() - (int) $p['ai_remove']));
    }
}

// Ask Razorpay to return money. Returns ['ok', 'id', 'error'].
function rzp_refund($paymentId, $amountMajor, array $notes, array $cfg) {
    $body = json_encode(['amount' => (int) $amountMajor * 100, 'speed' => 'normal', 'notes' => $notes]);
    $r = rzp_http('POST', 'https://api.razorpay.com/v1/payments/' . rawurlencode((string) $paymentId) . '/refund', $body, $cfg['key_id'] ?? '', $cfg['key_secret'] ?? '');
    $j = json_decode((string) ($r['body'] ?? ''), true);
    if (empty($r['ok']) || empty($j['id']))
        return ['ok' => false, 'error' => (string) ($j['error']['description'] ?? ($r['error'] ?? 'Razorpay refused the refund.'))];
    return ['ok' => true, 'id' => (string) $j['id']];
}

// Refund a payment. Runs INSIDE the company's workspace; $cfg = the platform's
// payment keys (read on the control install). Money first: if Razorpay refuses,
// nothing is changed. Returns ['ok', 'msg', 'amount', 'credit_note'].
function saas_refund_execute($orderId, array $req, array $cfg, $byName, $nonce, $companyKey = '') {
    saas_payments_migrate();
    $o = ops_one("SELECT * FROM saas_orders WHERE order_id=?", [(string) $orderId]);
    if (!$o) return ['ok' => false, 'msg' => 'That payment is not known in this company.'];
    $plan = saas_refund_plan($o, $req);
    if (!$plan['ok']) return ['ok' => false, 'msg' => $plan['error']];
    $note = mb_substr(trim((string) ($req['note'] ?? '')), 0, 500);
    $amount = (int) $plan['amount'];
    try {
        db()->prepare("INSERT INTO saas_refunds (order_id,payment_id,amount,scope,reason,note,items_json,status,nonce,by_name,created_at)
                       VALUES (?,?,?,?,?,?,?,'STARTED',?,?,?)")
            ->execute([(string) $orderId, (string) $o['payment_id'], $amount, $plan['scope'], (string) $req['reason'], $note,
                       json_encode($plan['plan']), (string) $nonce, (string) $byName, date('c')]);
    } catch (Throwable $e) { return ['ok' => false, 'msg' => 'This refund was already sent (the form was submitted twice). Nothing was refunded twice.']; }
    $rid = (int) ops_val("SELECT id FROM saas_refunds WHERE nonce=?", [(string) $nonce]);
    $rf = rzp_refund((string) $o['payment_id'], $amount, ['order' => (string) $orderId, 'reason' => (string) $req['reason']], $cfg);
    if (!$rf['ok']) {
        db()->prepare("UPDATE saas_refunds SET status='FAILED', error=? WHERE id=?")->execute([mb_substr($rf['error'], 0, 500), $rid]);
        return ['ok' => false, 'msg' => 'Razorpay did not make the refund: ' . $rf['error'] . ' Nothing was changed for the company.'];
    }
    // The money is on its way back: now end what was refunded, and record it.
    saas_refund_apply_plan((array) $plan['plan']);
    $refunded = (int) ($o['refunded_amount'] ?? 0) + $amount;
    $status = $refunded >= (int) $o['amount'] ? 'REFUNDED' : 'PART_REFUNDED';
    db()->prepare("UPDATE saas_orders SET refunded_amount=?, status=? WHERE order_id=?")->execute([$refunded, $status, (string) $orderId]);
    $cn = '';
    if ((string) $o['invoice_no'] !== '' && function_exists('commercial_credit_note_issue'))
        $cn = commercial_credit_note_issue((string) $o['invoice_no'], $amount, saas_refund_reasons()[$req['reason']]['label'] . ($note !== '' ? ' — ' . $note : ''), $rf['id'], $companyKey);
    db()->prepare("UPDATE saas_refunds SET status='DONE', refund_id=?, credit_note_no=?, done_at=? WHERE id=?")->execute([$rf['id'], $cn, date('c'), $rid]);
    saas_refund_history_line($amount, 'Refund — ' . saas_order_label($o) . ($cn !== '' ? ' (credit note ' . $cn . ')' : ''), $rf['id'], (string) $orderId, $byName);
    if (function_exists('idems_log')) idems_log('setting', null, 'PAYMENT_REFUNDED', ['field' => (string) $orderId, 'new' => $amount . ' ' . $plan['scope'] . ' ' . $req['reason']]);
    return ['ok' => true, 'amount' => $amount, 'credit_note' => $cn, 'effect' => $plan['effect'],
            'msg' => '₹' . number_format($amount) . ' is being refunded to the customer\'s card / UPI (Razorpay: usually 5–7 working days). ' . $plan['effect']
                   . ($cn !== '' ? ' GST credit note ' . $cn . ' issued.' : '')];
}

// The refund in the company's Payment history, as a minus line.
function saas_refund_history_line($amount, $note, $refundId, $orderId, $byName) {
    try {
        if (function_exists('billing_migrate')) billing_migrate();
        if (function_exists('ensure_column')) ensure_column('billing_orders', 'note', "VARCHAR(200) DEFAULT ''");
        db()->prepare("INSERT INTO billing_orders (seats,period,amount,currency,order_id,payment_id,paid_until,by_user,created_at,note)
                       VALUES (0,'refund',?,?,?,?,'',?,?,?)")
            ->execute([-(int) $amount, (string) (function_exists('billing_config') ? (billing_config()['currency'] ?? 'INR') : 'INR'),
                       (string) $orderId, (string) $refundId, (string) $byName, date('c'), mb_substr((string) $note, 0, 200)]);
    } catch (Throwable $e) {}
}

// Refund a second payment for an order already paid — in full, at any time:
// nothing was given for it.
function saas_refund_extra($extraId, array $cfg, $byName) {
    saas_payments_migrate();
    $x = ops_one("SELECT * FROM saas_payment_extras WHERE id=?", [(int) $extraId]);
    if (!$x || $x['status'] !== 'OPEN') return ['ok' => false, 'msg' => 'That payment has already been refunded.'];
    $st = db()->prepare("UPDATE saas_payment_extras SET status='SENDING' WHERE id=? AND status='OPEN'");
    $st->execute([(int) $extraId]);
    if ($st->rowCount() !== 1) return ['ok' => false, 'msg' => 'That refund is already being sent.'];
    $rf = rzp_refund((string) $x['payment_id'], (int) $x['amount'], ['order' => (string) $x['order_id'], 'reason' => 'paid twice'], $cfg);
    if (!$rf['ok']) {
        db()->prepare("UPDATE saas_payment_extras SET status='OPEN' WHERE id=?")->execute([(int) $extraId]);
        return ['ok' => false, 'msg' => 'Razorpay did not make the refund: ' . $rf['error']];
    }
    db()->prepare("UPDATE saas_payment_extras SET status='REFUNDED', refund_id=?, refunded_at=? WHERE id=?")->execute([$rf['id'], date('c'), (int) $extraId]);
    saas_refund_history_line((int) $x['amount'], 'Refund — the same payment made twice', $rf['id'], (string) $x['order_id'], $byName);
    if (function_exists('idems_log')) idems_log('setting', null, 'PAYMENT_REFUNDED', ['field' => (string) $x['order_id'], 'new' => (int) $x['amount'] . ' paid-twice by ' . $byName]);
    return ['ok' => true, 'amount' => (int) $x['amount'], 'msg' => 'The second payment of ₹' . number_format((int) $x['amount']) . ' is being refunded in full (Razorpay: usually 5–7 working days). Nothing else changes.'];
}

// Everything the platform owner needs to see about one company's payments.
// Runs on the control install; opens the company and comes back.
function saas_company_payments($key) {
    $out = ['ok' => false, 'orders' => [], 'refunds' => [], 'extras' => [], 'users' => 0, 'current' => null];
    $key = strtolower(trim((string) $key));
    if ($key === '' || !function_exists('saas_enter_tenant')) return $out;
    $prev = $_SESSION['saas_tenant'] ?? null;
    try {
        if (saas_enter_tenant($key)) {
            $rt = []; try { db(); $rt = $GLOBALS['__tenant'] ?? []; } catch (Throwable $e) {}
            if (strtolower((string) ($rt['key'] ?? '')) === $key && ($rt['error'] ?? '') === '') {
                saas_payments_migrate();
                $out['orders']  = ops_all("SELECT * FROM saas_orders WHERE status IN ('PAID','PART_REFUNDED','REFUNDED') ORDER BY paid_at DESC LIMIT 50") ?: [];
                $out['refunds'] = ops_all("SELECT * FROM saas_refunds WHERE status='DONE' ORDER BY id DESC") ?: [];
                // A refund that was sent but never finished (the server stopped in
                // between) — Razorpay may already have returned the money.
                $out['stuck'] = ops_all("SELECT * FROM saas_refunds WHERE status='STARTED' AND created_at < ? ORDER BY id DESC", [date('c', time() - 120)]) ?: [];
                $out['extras']  = ops_all("SELECT * FROM saas_payment_extras ORDER BY id DESC") ?: [];
                $out['users']   = saas_active_users();
                $out['current'] = function_exists('saas_plan_current') ? saas_plan_current() : null;
                foreach ($out['orders'] as &$o) {
                    $o['label'] = saas_order_label($o);
                    $o['age'] = saas_order_age_days($o);
                    $o['left'] = saas_order_refundable($o);
                    $o['later'] = saas_order_has_later_plan_payment($o);
                    $b = json_decode((string) ($o['before_json'] ?? ''), true);
                    $mods = array_values(array_filter(array_map('trim', explode(',', (string) $o['modules']))));
                    $o['mods_added'] = is_array($b) ? array_values(array_diff($mods, array_filter(explode(',', (string) $b['paid_modules'])))) : $mods;
                }
                unset($o);
                $out['ok'] = true;
            }
        }
    } catch (Throwable $e) { $out['ok'] = false; }
    if ($prev === null) unset($_SESSION['saas_tenant']); else $_SESSION['saas_tenant'] = $prev;
    if (function_exists('db_reset')) db_reset();
    if (function_exists('licence_disabled')) licence_disabled(true);
    return $out;
}

// The policy, in the customer's words — shown before they pay.
function saas_refund_policy_text() {
    $trial = function_exists('selfserve_cfg') ? (int) (selfserve_cfg()['trial_days'] ?? 30) : 30;
    $d = saas_refund_days();
    return 'Payments are not refunded — every company gets a ' . $trial . '-day free trial of everything first. '
         . 'Bought something by mistake (extra logins, a module, storage or the wrong period) or paid twice? '
         . 'Tell your provider within ' . $d . ' day' . ($d === 1 ? '' : 's') . ' of paying and that part is refunded; what is refunded stops at once.';
}
