<?php
// ============================================================================
//  MARKETPLACE SUBSCRIPTIONS, ACCESS & USAGE  (Slice 3)
//
//  A client (hiring company) or a professional subscribes to a plan (prepaid, for
//  a month or a year). Access to the marketplace, and monthly usage limits, are
//  read from the plan they hold (mkt_plans, Slice 2). Freelancers inside the launch
//  free-promo get access with no payment.
//
//  SAFE BY DEFAULT: enforcement is behind a Super-Admin switch (mkt_enforce, default
//  OFF). While OFF the marketplace behaves exactly as today (open, unlimited) — so
//  turning subscriptions on is a deliberate business decision, never a surprise block.
// ============================================================================

function mkt_subs_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_subscriptions (
        id $pk,
        subscriber_kind VARCHAR(10) DEFAULT 'CLIENT', subscriber_id INT DEFAULT 0,
        plan_id INT DEFAULT 0, plan_code VARCHAR(40) DEFAULT '',
        period VARCHAR(8) DEFAULT 'MONTH', amount REAL DEFAULT 0,
        started_at VARCHAR(20) DEFAULT '', expires_at VARCHAR(20) DEFAULT '',
        status VARCHAR(12) DEFAULT 'ACTIVE',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_usage (
        id $pk,
        subscriber_kind VARCHAR(10) DEFAULT 'CLIENT', subscriber_id INT DEFAULT 0,
        ym VARCHAR(7) DEFAULT '', metric VARCHAR(24) DEFAULT '', used INT DEFAULT 0,
        updated_at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) {
        act_index('mkt_subscriptions', 'ix_sub_who', '(subscriber_kind, subscriber_id, status)');
        act_index('mkt_usage', 'ix_use_who', '(subscriber_kind, subscriber_id, ym, metric)');
    }
}

/** Master switch — is subscription/limit enforcement ON? Default OFF (open marketplace). */
function mkt_enforce_on() { return (int)setting_get('mkt_enforce', 0) === 1; }

function _mkt_kind($k) { return strtoupper((string)$k) === 'PRO' ? 'PRO' : 'CLIENT'; }

/**
 * Record a prepaid subscription. period MONTH → +1 month & price_month; YEAR →
 * +1 year & price_annual (which the Super Admin set to N months). Any prior active
 * subscription for the same subscriber is closed. Payment capture is handled
 * separately (this records the paid period). Returns [ok, message, id].
 */
function mkt_subscribe($kind, $subId, $planId, $period = 'MONTH', $by = '') {
    mkt_subs_migrate();
    $kind = _mkt_kind($kind); $subId = (int)$subId;
    $plan = function_exists('mkt_plan_get') ? mkt_plan_get($planId) : null;
    if (!$plan) return [false, 'Choose a valid plan.', 0];
    if ($subId <= 0) return [false, 'Unknown subscriber.', 0];
    $period = strtoupper($period) === 'YEAR' ? 'YEAR' : 'MONTH';
    $amount = $period === 'YEAR' ? (float)$plan['price_annual'] : (float)$plan['price_month'];
    $now = date('Y-m-d');
    $exp = $period === 'YEAR' ? date('Y-m-d', strtotime('+1 year')) : date('Y-m-d', strtotime('+1 month'));
    // Close any current active subscription for this subscriber.
    db()->prepare("UPDATE mkt_subscriptions SET status='CANCELLED' WHERE subscriber_kind=? AND subscriber_id=? AND status='ACTIVE'")->execute([$kind, $subId]);
    db()->prepare("INSERT INTO mkt_subscriptions (subscriber_kind,subscriber_id,plan_id,plan_code,period,amount,started_at,expires_at,status,created_by,created_at)
                   VALUES (?,?,?,?,?,?,?,?, 'ACTIVE', ?, ?)")
        ->execute([$kind, $subId, (int)$plan['id'], (string)$plan['code'], $period, $amount, $now, $exp, (string)$by, date('c')]);
    return [true, 'Subscribed to ' . $plan['name'] . ' until ' . $exp . '.', (int)db()->lastInsertId()];
}

/** The subscriber's current live subscription row (ACTIVE and not past expiry), or null. */
function mkt_active_sub($kind, $subId) {
    mkt_subs_migrate();
    return ops_one("SELECT * FROM mkt_subscriptions WHERE subscriber_kind=? AND subscriber_id=? AND status='ACTIVE' AND (expires_at='' OR expires_at>=?) ORDER BY id DESC LIMIT 1",
        [_mkt_kind($kind), (int)$subId, date('Y-m-d')]) ?: null;
}

/** The plan the subscriber currently holds (from their live subscription), or null. */
function mkt_current_plan($kind, $subId) {
    $s = mkt_active_sub($kind, $subId);
    return $s && function_exists('mkt_plan_get') ? mkt_plan_get((int)$s['plan_id']) : null;
}

/**
 * May this subscriber use the marketplace at all right now?
 *  - enforcement OFF        → always yes (open marketplace)
 *  - professional in promo  → yes (free launch window)
 *  - anyone with a live sub → yes
 *  - otherwise              → no (must subscribe)
 */
function mkt_has_access($kind, $subId) {
    if (!mkt_enforce_on()) return true;
    $kind = _mkt_kind($kind);
    if ($kind === 'PRO' && function_exists('mkt_pro_is_free') && mkt_pro_is_free()) return true;
    return (bool) mkt_active_sub($kind, $subId);
}

/** The plan limit for a usage metric. Returns -1 for "unlimited / not limited". */
function mkt_limit($kind, $subId, $metric) {
    if (!mkt_enforce_on()) return -1;                       // open marketplace
    $kind = _mkt_kind($kind);
    if ($kind === 'PRO' && function_exists('mkt_pro_is_free') && mkt_pro_is_free() && !mkt_active_sub($kind, $subId)) return -1; // promo = unlimited
    $plan = mkt_current_plan($kind, $subId);
    if (!$plan) return 0;                                   // no plan → nothing allowed (blocked)
    $lim = function_exists('mkt_plan_limits') ? mkt_plan_limits($plan) : [];
    $v = (int)($lim[$metric] ?? 0);
    return $v <= 0 ? -1 : $v;                                // 0 in the plan means unlimited for that metric
}

function _mkt_ym() { return date('Y-m'); }
function mkt_usage_used($kind, $subId, $metric) {
    mkt_subs_migrate();
    return (int) (ops_val("SELECT used FROM mkt_usage WHERE subscriber_kind=? AND subscriber_id=? AND ym=? AND metric=?",
        [_mkt_kind($kind), (int)$subId, _mkt_ym(), (string)$metric]) ?? 0);
}
/** Add to this month's usage of a metric (called after a successful post/apply/…). */
function mkt_usage_add($kind, $subId, $metric, $n = 1) {
    mkt_subs_migrate();
    $kind = _mkt_kind($kind); $subId = (int)$subId; $ym = _mkt_ym(); $metric = (string)$metric;
    $has = ops_val("SELECT id FROM mkt_usage WHERE subscriber_kind=? AND subscriber_id=? AND ym=? AND metric=?", [$kind, $subId, $ym, $metric]);
    if ($has) db()->prepare("UPDATE mkt_usage SET used=used+?, updated_at=? WHERE id=?")->execute([(int)$n, date('c'), (int)$has]);
    else db()->prepare("INSERT INTO mkt_usage (subscriber_kind,subscriber_id,ym,metric,used,updated_at) VALUES (?,?,?,?,?,?)")->execute([$kind, $subId, $ym, $metric, (int)$n, date('c')]);
}
/** How many of this metric are left this month. PHP_INT_MAX = unlimited. */
function mkt_usage_left($kind, $subId, $metric) {
    $lim = mkt_limit($kind, $subId, $metric);
    if ($lim < 0) return PHP_INT_MAX;
    return max(0, $lim - mkt_usage_used($kind, $subId, $metric));
}
/**
 * The gate the app calls before letting a subscriber do a limited action. When
 * enforcement is OFF this is always true, so existing flows never change. When ON:
 * they must have access AND have either plan quota left OR credit-pack top-ups.
 */
function mkt_can_use($kind, $subId, $metric) {
    if (!mkt_enforce_on()) return true;
    if (!mkt_has_access($kind, $subId)) return false;
    if (mkt_usage_left($kind, $subId, $metric) > 0) return true;
    // Plan quota is spent — a purchased credit pack (Slice 4) can still cover it.
    return function_exists('mkt_credits_balance') && mkt_credits_balance($kind, $subId, $metric) > 0;
}

/**
 * Book one unit of a limited action AFTER it has succeeded. This is the accounting
 * call the app makes in place of raw mkt_usage_add: it draws from the plan's monthly
 * quota first, and only when that is exhausted does it consume a credit-pack top-up.
 * While enforcement is OFF it simply tracks usage (nothing is ever blocked or drawn).
 */
function mkt_consume($kind, $subId, $metric, $n = 1) {
    $kind = _mkt_kind($kind); $subId = (int)$subId; $n = max(1, (int)$n);
    if (!mkt_enforce_on()) { mkt_usage_add($kind, $subId, $metric, $n); return; }
    $lim = mkt_limit($kind, $subId, $metric);
    if ($lim < 0) { mkt_usage_add($kind, $subId, $metric, $n); return; }   // unlimited plan — just track
    $planLeft = max(0, $lim - mkt_usage_used($kind, $subId, $metric));
    $fromPlan = min($n, $planLeft);
    if ($fromPlan > 0) mkt_usage_add($kind, $subId, $metric, $fromPlan);
    $rem = $n - $fromPlan;
    if ($rem > 0 && function_exists('mkt_credits_consume')) mkt_credits_consume($kind, $subId, $metric, $rem);
}
