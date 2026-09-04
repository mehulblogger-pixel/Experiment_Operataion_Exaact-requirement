<?php
// ============================================================================
//  MARKETPLACE CREDIT PACKS  (Slice 4 — top-ups when a plan limit is exhausted)
//
//  A subscriber's plan gives them N of a thing per month (posts, unlocks, reports,
//  applications…). When they run out, instead of forcing a plan upgrade they can buy
//  a CREDIT PACK — a configurable bundle that adds credits for a metric. Credits are
//  a wallet: they don't reset monthly, they're consumed only after the plan quota is
//  used up, and they carry over.
//
//  Super-Admin owned & fully configurable (packs, prices, sizes) — nothing hard-coded.
//  SAFE BY DEFAULT: like the rest of the marketplace, this only ever bites when the
//  master enforcement switch (mkt_enforce) is ON. While OFF the marketplace is open,
//  so credits are never needed and never consumed.
// ============================================================================

function mkt_credits_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    // The configurable catalogue of packs (Super-Admin owned).
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_credit_packs (
        id $pk,
        code VARCHAR(40) DEFAULT '', audience VARCHAR(12) DEFAULT 'CLIENT',
        name VARCHAR(120) DEFAULT '',
        metric VARCHAR(24) DEFAULT '', credits INT DEFAULT 0, price REAL DEFAULT 0,
        is_active INT DEFAULT 1, sort INT DEFAULT 0,
        created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    // A subscriber's running wallet balance per metric.
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_credit_balance (
        id $pk,
        subscriber_kind VARCHAR(10) DEFAULT 'CLIENT', subscriber_id INT DEFAULT 0,
        metric VARCHAR(24) DEFAULT '', balance INT DEFAULT 0,
        updated_at VARCHAR(30) DEFAULT '')");
    // An audit log of every pack purchase.
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_credit_purchases (
        id $pk,
        subscriber_kind VARCHAR(10) DEFAULT 'CLIENT', subscriber_id INT DEFAULT 0,
        pack_id INT DEFAULT 0, pack_code VARCHAR(40) DEFAULT '',
        metric VARCHAR(24) DEFAULT '', credits INT DEFAULT 0, price REAL DEFAULT 0,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) {
        act_index('mkt_credit_balance', 'ix_cbal_who', '(subscriber_kind, subscriber_id, metric)');
        act_index('mkt_credit_purchases', 'ix_cbuy_who', '(subscriber_kind, subscriber_id)');
    }
    try { if ((int)ops_val("SELECT COUNT(*) FROM mkt_credit_packs") === 0) mkt_credit_packs_seed_defaults(); } catch (Throwable $e) {}
}

/** A sensible starter catalogue (the Super Admin edits freely afterwards). */
function mkt_credit_packs_seed_defaults() {
    $now = date('c');
    $rows = [
        // code            audience  name                       metric          credits price sort
        ['CL_POSTS_10',   'CLIENT', '+10 job posts',           'posts',         10,  499, 1],
        ['CL_UNLOCK_50',  'CLIENT', '+50 contact unlocks',     'unlocks',       50,  499, 2],
        ['CL_REPORTS_20', 'CLIENT', '+20 on-platform reports', 'reports',       20,  999, 3],
        ['PR_APPLY_10',   'PRO',    '+10 applications',        'applications',  10,   99, 1],
    ];
    foreach ($rows as [$code, $aud, $name, $metric, $credits, $price, $sort]) {
        db()->prepare("INSERT INTO mkt_credit_packs (code,audience,name,metric,credits,price,is_active,sort,created_at,updated_at)
                       VALUES (?,?,?,?,?,?,1,?,?,?)")
            ->execute([$code, mkt_audience_norm($aud), $name, $metric, (int)$credits, (float)$price, $sort, $now, $now]);
    }
}

function _mkt_c_kind($k) { return strtoupper((string)$k) === 'PRO' ? 'PRO' : 'CLIENT'; }

/** All packs (optionally one audience), ordered for display. Active-only optional. */
function mkt_credit_packs_all($audience = null, $activeOnly = false) {
    mkt_credits_migrate();
    $where = []; $args = [];
    if ($audience) { $where[] = 'audience=?'; $args[] = mkt_audience_norm($audience); }
    if ($activeOnly) $where[] = 'is_active=1';
    $sql = "SELECT * FROM mkt_credit_packs" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY audience, sort, id";
    return ops_all($sql, $args) ?: [];
}
function mkt_credit_pack_get($id) { mkt_credits_migrate(); return ops_one("SELECT * FROM mkt_credit_packs WHERE id=?", [(int)$id]) ?: null; }

/** Create or update a pack from posted fields. Returns [ok, message]. */
function mkt_credit_pack_save(array $in) {
    mkt_credits_migrate();
    $id     = (int)($in['id'] ?? 0);
    $aud    = mkt_audience_norm($in['audience'] ?? 'CLIENT');
    $name   = trim((string)($in['name'] ?? ''));
    if ($name === '') return [false, 'Give the pack a name.'];
    $metric = trim((string)($in['metric'] ?? ''));
    $keys   = function_exists('mkt_limit_keys') ? mkt_limit_keys() : [];
    if ($metric === '' || !isset($keys[$metric])) return [false, 'Choose which limit the pack tops up.'];
    $credits = max(1, (int)($in['credits'] ?? 0));
    $price   = max(0, (float)($in['price'] ?? 0));
    $code    = trim((string)($in['code'] ?? '')) ?: strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 20));
    $active  = !empty($in['is_active']) ? 1 : 0;
    $sort    = (int)($in['sort'] ?? 0);
    $now = date('c');
    if ($id > 0 && mkt_credit_pack_get($id)) {
        db()->prepare("UPDATE mkt_credit_packs SET code=?, audience=?, name=?, metric=?, credits=?, price=?, is_active=?, sort=?, updated_at=? WHERE id=?")
            ->execute([$code, $aud, $name, $metric, $credits, $price, $active, $sort, $now, $id]);
        return [true, 'Credit pack updated.'];
    }
    db()->prepare("INSERT INTO mkt_credit_packs (code,audience,name,metric,credits,price,is_active,sort,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$code, $aud, $name, $metric, $credits, $price, $active, $sort, $now, $now]);
    return [true, 'Credit pack added.'];
}
function mkt_credit_pack_delete($id) { mkt_credits_migrate(); db()->prepare("DELETE FROM mkt_credit_packs WHERE id=?")->execute([(int)$id]); return true; }

/** The subscriber's remaining credit balance for a metric (a top-up wallet). */
function mkt_credits_balance($kind, $subId, $metric) {
    mkt_credits_migrate();
    return (int) (ops_val("SELECT balance FROM mkt_credit_balance WHERE subscriber_kind=? AND subscriber_id=? AND metric=?",
        [_mkt_c_kind($kind), (int)$subId, (string)$metric]) ?? 0);
}

/** Add credits to the wallet (internal; used by a pack purchase). */
function mkt_credits_add($kind, $subId, $metric, $n) {
    mkt_credits_migrate();
    $kind = _mkt_c_kind($kind); $subId = (int)$subId; $metric = (string)$metric; $n = (int)$n;
    if ($n === 0) return;
    $has = ops_val("SELECT id FROM mkt_credit_balance WHERE subscriber_kind=? AND subscriber_id=? AND metric=?", [$kind, $subId, $metric]);
    if ($has) db()->prepare("UPDATE mkt_credit_balance SET balance=balance+?, updated_at=? WHERE id=?")->execute([$n, date('c'), (int)$has]);
    else db()->prepare("INSERT INTO mkt_credit_balance (subscriber_kind,subscriber_id,metric,balance,updated_at) VALUES (?,?,?,?,?)")->execute([$kind, $subId, $metric, $n, date('c')]);
}

/** Consume up to $n credits from the wallet. Returns how many were actually drawn. */
function mkt_credits_consume($kind, $subId, $metric, $n = 1) {
    mkt_credits_migrate();
    $kind = _mkt_c_kind($kind); $subId = (int)$subId; $metric = (string)$metric; $n = (int)$n;
    $bal = mkt_credits_balance($kind, $subId, $metric);
    $take = max(0, min($n, $bal));
    if ($take > 0) db()->prepare("UPDATE mkt_credit_balance SET balance=balance-?, updated_at=? WHERE subscriber_kind=? AND subscriber_id=? AND metric=?")
        ->execute([$take, date('c'), $kind, $subId, $metric]);
    return $take;
}

/** Buy a credit pack — adds the credits to the wallet and logs the purchase. [ok,msg,id]. */
function mkt_credit_buy($kind, $subId, $packId, $by = '') {
    mkt_credits_migrate();
    $kind = _mkt_c_kind($kind); $subId = (int)$subId;
    $pack = mkt_credit_pack_get($packId);
    if (!$pack || empty($pack['is_active'])) return [false, 'That credit pack is not available.', 0];
    if ($subId <= 0) return [false, 'Unknown subscriber.', 0];
    if (mkt_audience_norm($pack['audience']) !== $kind) return [false, 'That pack is not for this account type.', 0];
    mkt_credits_add($kind, $subId, (string)$pack['metric'], (int)$pack['credits']);
    db()->prepare("INSERT INTO mkt_credit_purchases (subscriber_kind,subscriber_id,pack_id,pack_code,metric,credits,price,created_by,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$kind, $subId, (int)$pack['id'], (string)$pack['code'], (string)$pack['metric'], (int)$pack['credits'], (float)$pack['price'], (string)$by, date('c')]);
    return [true, 'Added ' . (int)$pack['credits'] . ' ' . $pack['metric'] . ' credits.', (int)db()->lastInsertId()];
}
