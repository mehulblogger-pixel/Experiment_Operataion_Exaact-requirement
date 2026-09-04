<?php
// ============================================================================
//  SUBSCRIPTION LIFECYCLE  (Phase 3 — cancel, grace, upgrade/downgrade, coupons)
//
//  Extends the prepaid subscription (mkt_subs) with the real lifecycle the spec (§3)
//  asks for: cancel at period end or immediately; a configurable GRACE window after
//  expiry; upgrade/downgrade with PRORATION of the unused time; and configurable
//  discount COUPONS. Everything is Super-Admin configurable; nothing is hard-coded.
// ============================================================================

function mkt_billing_migrate() {
    static $done = false; if ($done) return; $done = true;
    if (function_exists('mkt_subs_migrate')) mkt_subs_migrate();
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_coupons (
        id $pk, code VARCHAR(40) DEFAULT '', label VARCHAR(160) DEFAULT '',
        kind VARCHAR(8) DEFAULT 'PERCENT', value REAL DEFAULT 0, audience VARCHAR(8) DEFAULT '',
        valid_from VARCHAR(10) DEFAULT '', valid_until VARCHAR(10) DEFAULT '',
        max_uses INT DEFAULT 0, used INT DEFAULT 0, is_active INT DEFAULT 1,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) act_index('mkt_coupons', 'ix_coupon_code', '(code, is_active)');
}

/** Days of grace after expiry during which a lapsed subscriber keeps access. Default 0. */
function mkt_sub_grace_days() { return max(0, (int)setting_get('sub_grace_days', 0)); }

/** Is the subscriber inside the grace window (expired, but within grace_days)? */
function mkt_sub_in_grace($kind, $subId) {
    mkt_billing_migrate();
    $g = mkt_sub_grace_days(); if ($g <= 0) return false;
    $kind = strtoupper((string)$kind) === 'PRO' ? 'PRO' : 'CLIENT';
    $floor = date('Y-m-d', strtotime('-' . $g . ' days'));
    // A subscription that has expired within the last $g days and was not cancelled outright.
    return (bool) ops_one("SELECT id FROM mkt_subscriptions WHERE subscriber_kind=? AND subscriber_id=? AND status='ACTIVE'
                            AND expires_at<>'' AND expires_at<? AND expires_at>=? ORDER BY id DESC LIMIT 1",
        [$kind, (int)$subId, date('Y-m-d'), $floor]);
}

/**
 * Cancel a subscription. mode 'END' (default) stops the renewal but keeps access until
 * expiry; mode 'NOW' ends it immediately. Returns [ok, message].
 */
function mkt_sub_cancel($kind, $subId, $mode = 'END') {
    mkt_billing_migrate();
    $sub = function_exists('mkt_active_sub') ? mkt_active_sub($kind, $subId) : null;
    if (!$sub) return [false, 'No active subscription to cancel.'];
    if (strtoupper($mode) === 'NOW') {
        db()->prepare("UPDATE mkt_subscriptions SET status='CANCELLED', auto_renew=0, cancel_at=?, expires_at=? WHERE id=?")
            ->execute([date('Y-m-d'), date('Y-m-d'), (int)$sub['id']]);
        return [true, 'Subscription cancelled — access ends today.'];
    }
    db()->prepare("UPDATE mkt_subscriptions SET auto_renew=0, cancel_at=? WHERE id=?")->execute([(string)$sub['expires_at'], (int)$sub['id']]);
    return [true, 'Subscription will not renew — access continues until ' . $sub['expires_at'] . '.'];
}

/** The prorated credit for the UNUSED remainder of the current subscription (major units). */
function mkt_sub_proration_credit($kind, $subId, $onDate = null) {
    $sub = function_exists('mkt_active_sub') ? mkt_active_sub($kind, $subId) : null;
    if (!$sub || (string)$sub['expires_at'] === '' || (float)$sub['amount'] <= 0) return 0.0;
    $today = strtotime(substr((string)($onDate ?: date('Y-m-d')), 0, 10));
    $start = strtotime((string)$sub['started_at']); $end = strtotime((string)$sub['expires_at']);
    if ($end <= $start || $today >= $end) return 0.0;
    $total = ($end - $start) / 86400; $remaining = ($end - max($today, $start)) / 86400;
    return round((float)$sub['amount'] * ($remaining / $total), 2);
}

/**
 * Upgrade or downgrade to a new plan. The unused value of the current subscription is
 * credited against the new plan's price (proration). Returns [ok, message, new_id].
 */
function mkt_sub_change($kind, $subId, $newPlanId, $period = 'MONTH', $by = '') {
    mkt_billing_migrate();
    $plan = function_exists('mkt_plan_get') ? mkt_plan_get($newPlanId) : null;
    if (!$plan) return [false, 'Choose a valid plan.', 0];
    $credit = mkt_sub_proration_credit($kind, $subId);
    [$ok, $msg, $id] = mkt_subscribe($kind, $subId, (int)$newPlanId, $period, $by);
    if (!$ok) return [$ok, $msg, $id];
    if ($credit > 0 && $id > 0) {
        $row = ops_one("SELECT amount FROM mkt_subscriptions WHERE id=?", [(int)$id]);
        $net = max(0.0, round((float)$row['amount'] - $credit, 2));
        db()->prepare("UPDATE mkt_subscriptions SET amount=?, proration_credit=? WHERE id=?")->execute([$net, $credit, (int)$id]);
        $msg .= ' A prorated credit of ' . number_format($credit, 2) . ' was applied.';
    }
    return [true, $msg, $id];
}

// ---- Coupons --------------------------------------------------------------
function mkt_coupon_get($code) {
    mkt_billing_migrate();
    return ops_one("SELECT * FROM mkt_coupons WHERE code=? AND is_active=1", [strtoupper(trim((string)$code))]) ?: null;
}
function mkt_coupons_all() { mkt_billing_migrate(); return ops_all("SELECT * FROM mkt_coupons ORDER BY id DESC") ?: []; }

function mkt_coupon_save(array $in) {
    mkt_billing_migrate();
    $code = strtoupper(trim((string)($in['code'] ?? ''))); if ($code === '') return [false, 'Give the coupon a code.'];
    $kind = strtoupper((string)($in['kind'] ?? 'PERCENT')) === 'FIXED' ? 'FIXED' : 'PERCENT';
    $val  = max(0.0, (float)($in['value'] ?? 0));
    $aud  = strtoupper((string)($in['audience'] ?? '')); if (!in_array($aud, ['CLIENT', 'PRO'], true)) $aud = '';
    $now = date('c'); $id = (int)($in['id'] ?? 0);
    $args = [$code, (string)($in['label'] ?? ''), $kind, $val, $aud, substr((string)($in['valid_from'] ?? ''), 0, 10),
             substr((string)($in['valid_until'] ?? ''), 0, 10), max(0, (int)($in['max_uses'] ?? 0)), !empty($in['is_active']) ? 1 : (isset($in['is_active']) ? 0 : 1)];
    if ($id > 0 && ops_val("SELECT id FROM mkt_coupons WHERE id=?", [$id])) {
        db()->prepare("UPDATE mkt_coupons SET code=?,label=?,kind=?,value=?,audience=?,valid_from=?,valid_until=?,max_uses=?,is_active=?,updated_at=? WHERE id=?")
            ->execute(array_merge($args, [$now, $id]));
        return [true, 'Coupon updated.'];
    }
    db()->prepare("INSERT INTO mkt_coupons (code,label,kind,value,audience,valid_from,valid_until,max_uses,is_active,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute(array_merge($args, [$now, $now]));
    return [true, 'Coupon added.'];
}

/** Is a coupon usable for this audience on this date (validity, uses, scope)? */
function mkt_coupon_valid($code, $audience = '', $onDate = null) {
    $c = mkt_coupon_get($code); if (!$c) return null;
    $d = substr((string)($onDate ?: date('Y-m-d')), 0, 10);
    if ((string)$c['valid_from'] !== '' && $d < (string)$c['valid_from']) return null;
    if ((string)$c['valid_until'] !== '' && $d > (string)$c['valid_until']) return null;
    if ((int)$c['max_uses'] > 0 && (int)$c['used'] >= (int)$c['max_uses']) return null;
    if ((string)$c['audience'] !== '' && $audience !== '' && strtoupper((string)$c['audience']) !== strtoupper((string)$audience)) return null;
    return $c;
}

/**
 * Apply a coupon to an amount. Returns [net_amount, discount]. If the coupon is invalid
 * the amount is unchanged and discount is 0. Records one use when a real discount applies.
 */
function mkt_coupon_apply($code, $amount, $audience = '', $onDate = null) {
    $amount = round((float)$amount, 2);
    $c = mkt_coupon_valid($code, $audience, $onDate);
    if (!$c || $amount <= 0) return [$amount, 0.0];
    $disc = strtoupper((string)$c['kind']) === 'FIXED' ? (float)$c['value'] : round($amount * (float)$c['value'] / 100, 2);
    if ($disc > $amount) $disc = $amount;
    if ($disc <= 0) return [$amount, 0.0];
    db()->prepare("UPDATE mkt_coupons SET used=used+1, updated_at=? WHERE id=?")->execute([date('c'), (int)$c['id']]);
    return [round($amount - $disc, 2), round($disc, 2)];
}
