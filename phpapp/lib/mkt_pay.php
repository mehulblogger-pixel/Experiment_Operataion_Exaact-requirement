<?php
// ============================================================================
//  MARKETPLACE PAYMENT CAPTURE  (Slice 6 — take money for subscriptions & credit packs)
//
//  Subscribing to a plan (mkt_subs) and buying a credit pack (mkt_credits) both
//  RECORD a paid period/purchase. This layer collects the actual money first, using
//  the app's existing Razorpay engine (rzp_create_order / rzp_verify_signature in
//  lib/billing.php). The flow mirrors seat-billing exactly:
//    1. start  → create a Razorpay order, record a PENDING mkt_order
//    2. checkout page → Razorpay collects the card, hands back a signed result
//    3. verify → confirm the HMAC signature, THEN activate (subscribe / add credits)
//
//  A zero price (a free plan, or the freelancer launch-promo) activates at once with
//  no charge. A PRICED plan or pack with no Razorpay keys is REFUSED, not granted
//  free: until 19 Oct 2026 it activated immediately "so the marketplace keeps working
//  until keys are added", which meant that with charging switched on and the keys
//  missing (never entered, or removed), every paid plan and credit pack was handed
//  out for nothing and no revenue was recorded. The admin plans screen now says when
//  priced items cannot be bought.
//
//  The money never touches the platform balance-sheet dishonestly: this is the
//  customer paying the platform for their subscription/top-up, recorded per order.
// ============================================================================

function mkt_pay_migrate() {
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_orders (
        id $pk,
        subscriber_kind VARCHAR(10) DEFAULT 'CLIENT', subscriber_id INT DEFAULT 0,
        purpose VARCHAR(8) DEFAULT 'SUB', ref_id INT DEFAULT 0, period VARCHAR(8) DEFAULT 'MONTH',
        amount REAL DEFAULT 0, currency VARCHAR(8) DEFAULT 'INR',
        order_id VARCHAR(64) DEFAULT '', payment_id VARCHAR(64) DEFAULT '',
        status VARCHAR(10) DEFAULT 'PENDING',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', paid_at VARCHAR(30) DEFAULT '')");
    // The upgrade credit fixed when the payment started, so what is applied on
    // confirmation is exactly what the customer was shown and paid for.
    if (function_exists('ensure_column')) ensure_column('mkt_orders', 'proration_credit', 'REAL DEFAULT 0');
    // The coupon the buyer gave when the payment started (counted as used only when paid).
    if (function_exists('ensure_column')) ensure_column('mkt_orders', 'coupon_code', "VARCHAR(40) DEFAULT ''");
    if (function_exists('act_index')) act_index('mkt_orders', 'ix_mord_who', '(subscriber_kind, subscriber_id, status)');
}

/** What a buyer is told when a priced item cannot be paid for (no keys). */
function mkt_pay_unconfigured_msg() {
    return 'Online payment is not set up on this marketplace yet, so a paid plan or credit pack cannot be bought right now. Please contact us to arrange it.';
}
/** Priced plans or packs that exist while payment is not configured — for the admin screen. */
function mkt_pay_priced_items_blocked() {
    if (mkt_pay_configured()) return [];
    $out = [];
    if (function_exists('mkt_plans_all'))
        foreach (mkt_plans_all() as $p)
            if (!empty($p['is_active']) && ((float)$p['price_month'] > 0 || (float)$p['price_annual'] > 0)) $out[] = 'plan ' . $p['name'];
    if (function_exists('mkt_credit_packs_all'))
        foreach (mkt_credit_packs_all(null, true) as $p)
            if ((float)$p['price'] > 0) $out[] = 'pack ' . $p['name'];
    return $out;
}
/** Is online payment capture available (Razorpay keys present)? */
function mkt_pay_configured() { return function_exists('billing_configured') && billing_configured(); }

function _mkt_pay_kind($k) { return strtoupper((string)$k) === 'PRO' ? 'PRO' : 'CLIENT'; }

/** The price (major units) of what's being bought. SUB → plan month/annual; PACK → pack price. */
function mkt_pay_price($purpose, $refId, $period = 'MONTH') {
    if (strtoupper($purpose) === 'PACK') {
        $pack = function_exists('mkt_credit_pack_get') ? mkt_credit_pack_get($refId) : null;
        return $pack ? (float)$pack['price'] : 0.0;
    }
    $plan = function_exists('mkt_plan_get') ? mkt_plan_get($refId) : null;
    if (!$plan) return 0.0;
    return strtoupper($period) === 'YEAR' ? (float)$plan['price_annual'] : (float)$plan['price_month'];
}

/**
 * Begin a purchase. Returns one of:
 *   ['mode'=>'free']          → price is 0 (free plan / promo) — caller activates now
 *   ['mode'=>'pay','row_id'=>N] → a Razorpay order is waiting — send to the checkout page
 *   ['mode'=>'error','msg'=>…] → could not create the order
 */
function mkt_pay_start($kind, $subId, $purpose, $refId, $period = 'MONTH', $by = '', $coupon = '') {
    mkt_pay_migrate();
    $kind = _mkt_pay_kind($kind); $subId = (int)$subId;
    $purpose = strtoupper($purpose) === 'PACK' ? 'PACK' : 'SUB';
    $period = strtoupper($period) === 'YEAR' ? 'YEAR' : 'MONTH';
    $amount = mkt_pay_price($purpose, $refId, $period);
    $credit = 0.0;
    // A plan change is priced by the rule (owner, 2026-10-19): an upgrade is the new
    // price less the unused time; a downgrade is not charged now at all.
    if ($purpose === 'SUB' && function_exists('mkt_plan_change_quote')) {
        $q = mkt_plan_change_quote($kind, $subId, $refId, $period);
        if (empty($q['ok'])) return ['mode' => 'error', 'msg' => $q['msg']];
        if ($q['type'] === 'DOWNGRADE') return ['mode' => 'scheduled', 'msg' => $q['msg']];
        $amount = (float)$q['price']; $credit = (float)$q['credit'];
        // A coupon takes its discount off the list price exactly as the plan record
        // will (mkt_plan_change_apply), so what is charged is what is recorded. It
        // used to be ignored whenever online payment was on.
        $c = trim((string)$coupon) !== '' && function_exists('mkt_coupon_valid') ? mkt_coupon_valid($coupon, $kind) : null;
        if ($c) {
            $list = (float)($q['list'] ?? $amount);
            $disc = strtoupper((string)$c['kind']) === 'FIXED' ? (float)$c['value'] : round($list * (float)$c['value'] / 100, 2);
            $disc = min(max(0.0, $disc), $list);
            $amount = max(0.0, round($list - $disc - $credit, 2));
            $coupon = strtoupper(trim((string)$coupon));
        } else { $coupon = ''; }
    } else { $coupon = ''; }
    if ($amount <= 0) return ['mode' => 'free'];
    if (!mkt_pay_configured()) return ['mode' => 'error', 'msg' => mkt_pay_unconfigured_msg()];

    $cfg = billing_config();
    $paise = (int) round($amount * 100);
    $receipt = 'mkt-' . strtolower($purpose) . '-' . $subId . '-' . date('ymdHis');
    $ord = rzp_create_order($paise, $receipt, ['kind' => $kind, 'purpose' => $purpose, 'ref' => (string)$refId]);
    if (empty($ord['ok'])) return ['mode' => 'error', 'msg' => $ord['error'] ?? 'Could not start the payment.'];

    db()->prepare("INSERT INTO mkt_orders (subscriber_kind,subscriber_id,purpose,ref_id,period,amount,currency,order_id,status,created_by,created_at,proration_credit,coupon_code)
                   VALUES (?,?,?,?,?,?,?,?, 'PENDING', ?, ?, ?, ?)")
        ->execute([$kind, $subId, $purpose, (int)$refId, $period, $amount, (string)$cfg['currency'], (string)$ord['id'], (string)$by, date('c'), round($credit, 2), $coupon]);
    return ['mode' => 'pay', 'row_id' => (int)db()->lastInsertId()];
}

/** A pending order row for this subscriber (defence-in-depth: scoped to the buyer). */
function mkt_pay_order($rowId, $kind = null, $subId = null) {
    mkt_pay_migrate();
    $row = ops_one("SELECT * FROM mkt_orders WHERE id=?", [(int)$rowId]);
    if (!$row) return null;
    if ($kind !== null && _mkt_pay_kind($kind) !== (string)$row['subscriber_kind']) return null;
    if ($subId !== null && (int)$subId !== (int)$row['subscriber_id']) return null;
    return $row;
}

/**
 * Verify a Razorpay callback and, on success, ACTIVATE the purchase (subscribe or add
 * credits) exactly once. The pending order is the source of truth for WHAT to grant —
 * the browser is never trusted for that. Returns [ok, message].
 */
function mkt_pay_verify($orderId, $paymentId, $signature) {
    mkt_pay_migrate();
    $row = ops_one("SELECT * FROM mkt_orders WHERE order_id=? AND status='PENDING'", [(string)$orderId]);
    if (!$row) return [false, 'This payment was already processed, or the order is unknown.'];
    if (!function_exists('rzp_verify_signature') || !rzp_verify_signature($orderId, $paymentId, $signature)) {
        db()->prepare("UPDATE mkt_orders SET status='FAILED', payment_id=?, paid_at=? WHERE id=?")->execute([(string)$paymentId, date('c'), (int)$row['id']]);
        return [false, 'The payment could not be verified. If money was deducted it will be refunded.'];
    }
    // Mark PAID first so a double callback cannot activate twice.
    // Conditional, so exactly one caller turns PENDING into PAID: the SELECT
    // above and this UPDATE were not atomic, and two verifications of the same
    // order arriving together could both pass the SELECT and both apply.
    $u = db()->prepare("UPDATE mkt_orders SET status='PAID', payment_id=?, paid_at=? WHERE id=? AND status='PENDING'");
    $u->execute([(string)$paymentId, date('c'), (int)$row['id']]);
    if ($u->rowCount() !== 1) return [false, 'This payment was already processed.'];

    $kind = (string)$row['subscriber_kind']; $subId = (int)$row['subscriber_id']; $by = (string)$row['created_by'];
    $isPack = (string)$row['purpose'] === 'PACK';
    // Phase 2 — record the payment as Connect revenue (its own stream), never as GMV.
    if (function_exists('mkt_ledger_post') && (float)$row['amount'] > 0) {
        mkt_ledger_post('CONNECT_REVENUE', (float)$row['amount'], [
            'subtype' => $isPack ? 'CREDIT_PACK' : 'SUBSCRIPTION', 'context' => 'ORDER', 'ref_id' => (int)$row['id'],
            'client_party_id' => $kind === 'CLIENT' ? $subId : 0, 'pro_id' => $kind === 'PRO' ? $subId : 0,
            'currency' => (string)$row['currency'], 'note' => $isPack ? 'Credit pack purchase' : 'Subscription payment', 'by' => $by,
        ]);
    }
    if ($isPack) {
        [$ok, $msg] = function_exists('mkt_credit_buy') ? mkt_credit_buy($kind, $subId, (int)$row['ref_id'], $by) : [true, 'Credits added.'];
        return [true, 'Payment received. ' . $msg];
    }
    [$ok, $msg] = function_exists('mkt_plan_change_apply')
        ? mkt_plan_change_apply($kind, $subId, (int)$row['ref_id'], (string)$row['period'], $by, (string)($row['coupon_code'] ?? ''), (float)($row['proration_credit'] ?? 0))
        : (function_exists('mkt_subscribe') ? mkt_subscribe($kind, $subId, (int)$row['ref_id'], (string)$row['period'], $by) : [true, 'Subscribed.']);
    return [true, 'Payment received. ' . $msg];
}

/**
 * Render a self-contained Razorpay checkout page (no app chrome, so it works the same
 * in the client portal and the professional area). $postAction is the verify route the
 * signed result is submitted to; $cancelUrl returns to the plans page.
 */
function mkt_pay_render_checkout(array $order, $postAction, $cancelUrl, $label = '') {
    $cfg = billing_config();
    $sym = ($order['currency'] ?? 'INR') === 'INR' ? '₹' : (($order['currency'] ?? '') . ' ');
    $amountMajor = (float)$order['amount'];
    $paise = (int) round($amountMajor * 100);
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $csrf = function_exists('csrf_token') ? csrf_token() : '';
    $brand = function_exists('app_name') ? app_name() : 'Marketplace';
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Complete your payment</title>';
    echo '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:520px;margin:44px auto;padding:26px;border:1px solid #e2ddd6;border-radius:12px;color:#333">';
    echo '<div style="font-weight:700;color:#0a5c5c;margin-bottom:6px">' . $e($brand) . '</div>';
    echo '<h2 style="margin:0 0 12px">Complete your payment</h2>';
    echo '<p style="color:#555">' . $e($label ?: 'Marketplace purchase') . ' — <b>' . $e($sym . number_format($amountMajor)) . '</b>. The secure Razorpay window should open automatically.</p>';
    echo '<button id="paybtn" type="button" style="padding:10px 16px;background:#0a5c5c;color:#fff;border:0;border-radius:8px;font-weight:600;cursor:pointer">Pay ' . $e($sym . number_format($amountMajor)) . '</button>';
    echo ' <a href="' . $e($cancelUrl) . '" style="margin-left:8px;color:#555">Cancel</a>';
    echo '<p style="color:#888;font-size:12.5px;margin-top:14px">Your card details go straight to Razorpay — this application never sees them.</p>';
    echo '<form method="post" action="' . $e($postAction) . '" id="vf" style="display:none">';
    echo '<input type="hidden" name="_csrf" value="' . $e($csrf) . '">';
    echo '<input type="hidden" name="razorpay_order_id" id="fo"><input type="hidden" name="razorpay_payment_id" id="fp"><input type="hidden" name="razorpay_signature" id="fs"></form>';
    echo '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>';
    echo '<script>var o={key:' . json_encode($cfg['key_id'] ?? '') . ',amount:' . $paise . ',currency:' . json_encode($order['currency'] ?? 'INR')
       . ',name:' . json_encode($brand) . ',description:' . json_encode($label ?: 'Marketplace purchase') . ',order_id:' . json_encode($order['order_id'] ?? '')
       . ',handler:function(r){document.getElementById("fo").value=r.razorpay_order_id;document.getElementById("fp").value=r.razorpay_payment_id;document.getElementById("fs").value=r.razorpay_signature;document.getElementById("vf").submit();}'
       . ',modal:{ondismiss:function(){}},theme:{color:"#0a5c5c"}};'
       . 'function op(){try{(new Razorpay(o)).open()}catch(e){alert("Could not open the payment window. Please reload.")}}'
       . 'document.getElementById("paybtn").addEventListener("click",op);window.addEventListener("load",function(){setTimeout(op,300)});</script>';
    echo '</div>';
    exit;
}

/** A short human label for a purchase, for the checkout page. */
function mkt_pay_label($purpose, $refId, $period = 'MONTH') {
    if (strtoupper($purpose) === 'PACK') {
        $p = function_exists('mkt_credit_pack_get') ? mkt_credit_pack_get($refId) : null;
        return $p ? (string)$p['name'] : 'Credit pack';
    }
    $p = function_exists('mkt_plan_get') ? mkt_plan_get($refId) : null;
    if (!$p) return 'Subscription';
    return $p['name'] . ' (' . (strtoupper($period) === 'YEAR' ? 'yearly' : 'monthly') . ')';
}
