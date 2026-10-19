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
    static $doneAt = -1; if ($doneAt === db_epoch()) return; $doneAt = db_epoch();
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
    db()->prepare("UPDATE mkt_subscriptions SET auto_renew=0, cancel_at=?, pending_plan_id=0, pending_period='' WHERE id=?")->execute([(string)$sub['expires_at'], (int)$sub['id']]);
    return [true, 'Subscription will not renew — access continues until ' . $sub['expires_at'] . '.'];
}

/** The prorated credit for the UNUSED remainder of the current subscription (major units). */
function mkt_sub_proration_credit($kind, $subId, $onDate = null) {
    $sub = function_exists('mkt_active_sub') ? mkt_active_sub($kind, $subId) : null;
    // The period is worth what was paid for it plus any credit carried into it from
    // an earlier upgrade -- so a second upgrade does not lose the first one's credit.
    $worth = $sub ? (float)$sub['amount'] + (float)($sub['proration_credit'] ?? 0) : 0.0;
    if (!$sub || (string)$sub['expires_at'] === '' || $worth <= 0) return 0.0;
    $today = strtotime(substr((string)($onDate ?: date('Y-m-d')), 0, 10));
    $start = strtotime((string)$sub['started_at']); $end = strtotime((string)$sub['expires_at']);
    if ($end <= $start || $today >= $end) return 0.0;
    $total = ($end - $start) / 86400; $remaining = ($end - max($today, $start)) / 86400;
    return round($worth * ($remaining / $total), 2);
}

// ---------------------------------------------------------------------------
//  CHANGING PLAN — the owner's rule, 2026-10-19 (Q-L, GAP-039)
//
//  * UPGRADE (a plan worth more per month, or the same plan monthly -> yearly)
//    starts TODAY. The unused value of the current plan is taken off the new
//    price; if it is worth more than the new price, the excess is given as extra
//    days on the new plan, so nothing paid is ever lost.
//  * DOWNGRADE starts when the paid period ENDS. Nothing is charged now and
//    nothing is refunded; a free plan then starts by itself, a priced one is due
//    and is paid for then.
//  * RENEW (the same plan and period again) starts when the current one ends --
//    buying early used to cancel the current plan and throw its days away.
//  Same practice as the large subscription billers (immediate upgrade with
//  credit, downgrade at period end).
// ---------------------------------------------------------------------------
function mkt_plan_monthly_value(array $plan, $period) {
    return strtoupper((string)$period) === 'YEAR' ? (float)$plan['price_annual'] / 12 : (float)$plan['price_month'];
}
function mkt_period_end($start, $period) {
    return date('Y-m-d', strtotime($start . (strtoupper((string)$period) === 'YEAR' ? ' +1 year' : ' +1 month')));
}
/** Days added to an upgrade when the credit is worth more than the new price. */
function mkt_credit_extra_days($credit, $price, $start, $end) {
    if ($credit <= $price + 0.005 || $price <= 0) return 0;
    $days = max(1, (int)round((strtotime($end) - strtotime($start)) / 86400));
    return (int)floor(($credit - $price) / ($price / $days));
}
/**
 * What choosing this plan would do, before anything is charged or written:
 * ['ok','type' => NEW|RENEW|UPGRADE|DOWNGRADE,'list','credit','price' (charged now),
 *  'due' (charged later, downgrade only),'starts','expires','extra_days','current','plan','msg'].
 */
function mkt_plan_change_quote($kind, $subId, $planId, $period = 'MONTH', $onDate = null) {
    mkt_billing_migrate();
    $kind = strtoupper((string)$kind) === 'PRO' ? 'PRO' : 'CLIENT';
    $period = strtoupper((string)$period) === 'YEAR' ? 'YEAR' : 'MONTH';
    $today = substr((string)($onDate ?: date('Y-m-d')), 0, 10);
    $plan = function_exists('mkt_plan_get') ? mkt_plan_get($planId) : null;
    if (!$plan) return ['ok' => false, 'msg' => 'Choose a valid plan.'];
    $list = round($period === 'YEAR' ? (float)$plan['price_annual'] : (float)$plan['price_month'], 2);
    $cur = mkt_active_sub($kind, $subId);
    $q = ['ok' => true, 'plan' => $plan, 'period' => $period, 'list' => $list, 'credit' => 0.0, 'price' => $list,
          'due' => 0.0, 'extra_days' => 0, 'current' => $cur];
    $fmt = fn($n) => number_format((float)$n, 2);
    if (!$cur) return array_merge($q, ['type' => 'NEW', 'starts' => $today, 'expires' => mkt_period_end($today, $period),
        'msg' => 'Starts today.']);
    $curPeriod = strtoupper((string)$cur['period']) === 'YEAR' ? 'YEAR' : 'MONTH';
    $after = date('Y-m-d', strtotime($cur['expires_at'] . ' +1 day'));
    if ((int)$cur['plan_id'] === (int)$plan['id'] && $curPeriod === $period)
        return array_merge($q, ['type' => 'RENEW', 'starts' => $after, 'expires' => mkt_period_end($after, $period),
            'msg' => 'Extends your plan: the new period starts on ' . $after . ', when this one ends. No days are lost.']);
    $curPlan = function_exists('mkt_plan_get') ? mkt_plan_get((int)$cur['plan_id']) : null;
    $curVal = $curPlan ? mkt_plan_monthly_value($curPlan, $curPeriod) : 0.0;
    $up = mkt_plan_monthly_value($plan, $period) > $curVal + 0.005
        || ((int)$cur['plan_id'] === (int)$plan['id'] && $curPeriod === 'MONTH' && $period === 'YEAR');
    if (!$up)
        return array_merge($q, ['type' => 'DOWNGRADE', 'price' => 0.0, 'due' => $list, 'starts' => $after,
            'expires' => mkt_period_end($after, $period),
            'msg' => 'Starts on ' . $after . ', when your paid period ends. Nothing is charged now'
                   . ($list > 0 ? '; you pay ' . $fmt($list) . ' for it then.' : '.')]);
    $credit = mkt_sub_proration_credit($kind, $subId, $today);
    $net = max(0.0, round($list - $credit, 2));
    $end = mkt_period_end($today, $period);
    $extra = mkt_credit_extra_days($credit, $list, $today, $end);
    if ($extra > 0) $end = date('Y-m-d', strtotime($end . ' +' . $extra . ' days'));
    return array_merge($q, ['type' => 'UPGRADE', 'credit' => round($credit, 2), 'price' => $net, 'starts' => $today,
        'expires' => $end, 'extra_days' => $extra,
        'msg' => 'Starts today. Your unused time is worth ' . $fmt($credit) . ' and is taken off, so you pay ' . $fmt($net)
               . ($extra > 0 ? ', and the remaining ' . $extra . ' days are added to the new plan' : '') . '.']);
}
/**
 * Do it. $paidCredit is the credit fixed when a payment was started (null on the
 * no-payment path, where it is worked out now). A PAID change is never left
 * waiting: a paid downgrade simply starts when the current period ends.
 * Returns [ok, message, subscription id].
 */
function mkt_plan_change_apply($kind, $subId, $planId, $period = 'MONTH', $by = '', $coupon = '', $paidCredit = null) {
    $q = mkt_plan_change_quote($kind, $subId, $planId, $period);
    if (empty($q['ok'])) return [false, $q['msg'], 0];
    if ((int)$subId <= 0) return [false, 'Unknown subscriber.', 0];
    $kind = strtoupper((string)$kind) === 'PRO' ? 'PRO' : 'CLIENT';
    $plan = $q['plan']; $paid = $paidCredit !== null;
    if ($q['type'] === 'DOWNGRADE' && !$paid) {
        db()->prepare("UPDATE mkt_subscriptions SET pending_plan_id=?, pending_period=? WHERE id=?")
            ->execute([(int)$plan['id'], $q['period'], (int)$q['current']['id']]);
        return [true, 'Your plan changes to ' . $plan['name'] . ' on ' . $q['starts'] . '. '
            . ($q['due'] > 0 ? 'Nothing is charged now; you will pay ' . number_format($q['due'], 2) . ' for it then.' : 'Nothing is charged.'),
            (int)$q['current']['id']];
    }
    $list = $q['list']; $discount = 0.0; $code = '';
    if (trim((string)$coupon) !== '' && function_exists('mkt_coupon_apply')) {
        [$list, $discount] = mkt_coupon_apply($coupon, $list, $kind);
        if ($discount > 0) $code = strtoupper(trim((string)$coupon));
    }
    $credit = $q['type'] === 'UPGRADE' ? ($paid ? (float)$paidCredit : (float)$q['credit']) : 0.0;
    $amount = max(0.0, round($list - $credit, 2));
    $starts = $q['starts']; $expires = $q['expires'];
    if ($q['type'] === 'UPGRADE') {
        $expires = mkt_period_end($starts, $q['period']);
        $extra = mkt_credit_extra_days($credit, $list, $starts, $expires);
        if ($extra > 0) $expires = date('Y-m-d', strtotime($expires . ' +' . $extra . ' days'));
    }
    // Whatever was scheduled before is superseded by this choice.
    db()->prepare("UPDATE mkt_subscriptions SET pending_plan_id=0, pending_period='' WHERE subscriber_kind=? AND subscriber_id=? AND COALESCE(pending_plan_id,0)<>0")
        ->execute([$kind, (int)$subId]);
    if ($q['type'] === 'UPGRADE' && $q['current'])
        db()->prepare("UPDATE mkt_subscriptions SET status='CANCELLED', auto_renew=0, cancel_at=? WHERE id=?")
            ->execute([date('Y-m-d'), (int)$q['current']['id']]);
    db()->prepare("INSERT INTO mkt_subscriptions (subscriber_kind,subscriber_id,plan_id,plan_code,period,amount,started_at,expires_at,status,coupon_code,discount,proration_credit,auto_renew,created_by,created_at)
                   VALUES (?,?,?,?,?,?,?,?, 'ACTIVE', ?,?,?, 1, ?, ?)")
        ->execute([$kind, (int)$subId, (int)$plan['id'], (string)$plan['code'], $q['period'], $amount, $starts, $expires,
                   $code, round($discount, 2), round($credit, 2), (string)$by, date('c')]);
    $id = (int)db()->lastInsertId();
    $msg = $q['type'] === 'UPGRADE'
        ? 'Upgraded to ' . $plan['name'] . ' from today, until ' . $expires . '.' . ($credit > 0 ? ' Your unused time (' . number_format($credit, 2) . ') was taken off.' : '')
        : ($starts > date('Y-m-d') ? $plan['name'] . ' is booked from ' . $starts . ' to ' . $expires . ', straight after your current period.'
                                   : 'Subscribed to ' . $plan['name'] . ' until ' . $expires . '.');
    return [true, $msg, $id];
}
/** One plain sentence for a plan card: what choosing it now would do. '' when there is no current plan. */
function mkt_plan_change_hint($kind, $subId, $planId, $period) {
    if ((int)$subId <= 0 || !mkt_active_sub($kind, $subId)) return '';
    $q = mkt_plan_change_quote($kind, $subId, $planId, $period);
    if (empty($q['ok'])) return '';
    $label = ['UPGRADE' => 'Upgrade', 'DOWNGRADE' => 'Downgrade', 'RENEW' => 'Renew', 'NEW' => 'Start'][$q['type']] ?? '';
    return ($period === 'YEAR' ? 'Yearly' : 'Monthly') . ' — ' . $label . ': ' . $q['msg'];
}

/** Withdraw a scheduled downgrade and keep the current plan. */
function mkt_plan_change_cancel($kind, $subId) {
    mkt_billing_migrate();
    db()->prepare("UPDATE mkt_subscriptions SET pending_plan_id=0, pending_period='' WHERE subscriber_kind=? AND subscriber_id=? AND COALESCE(pending_plan_id,0)<>0")
        ->execute([strtoupper((string)$kind) === 'PRO' ? 'PRO' : 'CLIENT', (int)$subId]);
    return [true, 'The plan change is withdrawn. Your current plan carries on.'];
}
/** The scheduled change waiting on the subscriber's current plan: [plan, period, starts] or null. */
function mkt_plan_change_pending($kind, $subId) {
    mkt_billing_migrate();
    $r = ops_one("SELECT * FROM mkt_subscriptions WHERE subscriber_kind=? AND subscriber_id=? AND status='ACTIVE' AND COALESCE(pending_plan_id,0)<>0 ORDER BY id DESC LIMIT 1",
        [strtoupper((string)$kind) === 'PRO' ? 'PRO' : 'CLIENT', (int)$subId]);
    if (!$r) return null;
    $p = function_exists('mkt_plan_get') ? mkt_plan_get((int)$r['pending_plan_id']) : null;
    if (!$p) return null;
    $per = strtoupper((string)$r['pending_period']) === 'YEAR' ? 'YEAR' : 'MONTH';
    return ['plan' => $p, 'period' => $per, 'starts' => date('Y-m-d', strtotime($r['expires_at'] . ' +1 day')),
            'price' => round($per === 'YEAR' ? (float)$p['price_annual'] : (float)$p['price_month'], 2), 'from_sub' => $r];
}
/**
 * Called when a subscriber has no live plan: if a downgrade to a FREE plan was
 * scheduled and its day has come, start it now and return it. A priced one waits
 * to be paid for (the plans page shows it as due).
 */
function mkt_plan_change_due($kind, $subId) {
    $p = mkt_plan_change_pending($kind, $subId);
    if (!$p || $p['price'] > 0 || $p['starts'] > date('Y-m-d')) return null;
    db()->prepare("UPDATE mkt_subscriptions SET pending_plan_id=0, pending_period='' WHERE id=?")->execute([(int)$p['from_sub']['id']]);
    $t = date('Y-m-d');
    db()->prepare("INSERT INTO mkt_subscriptions (subscriber_kind,subscriber_id,plan_id,plan_code,period,amount,started_at,expires_at,status,auto_renew,created_by,created_at)
                   VALUES (?,?,?,?,?,0,?,?, 'ACTIVE', 1, 'scheduled change', ?)")
        ->execute([$kind, (int)$subId, (int)$p['plan']['id'], (string)$p['plan']['code'], $p['period'], $t, mkt_period_end($t, $p['period']), date('c')]);
    return ops_one("SELECT * FROM mkt_subscriptions WHERE id=?", [(int)db()->lastInsertId()]) ?: null;
}

/**
 * Upgrade or downgrade to a new plan. The unused value of the current subscription is
 * credited against the new plan's price (proration). Returns [ok, message, new_id].
 */
function mkt_sub_change($kind, $subId, $newPlanId, $period = 'MONTH', $by = '') {
    mkt_billing_migrate();
    $plan = function_exists('mkt_plan_get') ? mkt_plan_get($newPlanId) : null;
    if (!$plan) return [false, 'Choose a valid plan.', 0];
    // Kept for its callers; the rule itself lives in mkt_plan_change_apply().
    return mkt_plan_change_apply($kind, $subId, (int)$newPlanId, $period, $by);
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
