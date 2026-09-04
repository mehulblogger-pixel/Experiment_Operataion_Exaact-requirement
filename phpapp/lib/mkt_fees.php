<?php
// ============================================================================
//  MARKETPLACE FEE-RULE ENGINE  (Phase 1 — configurable transaction fees)
//
//  Connect's marketplace/transaction fee is never a hard-coded "2%". It is a rule:
//  WHO pays (client / professional), HOW it's computed (percent / fixed / both),
//  on WHAT base (service value excluding GST, or gross), with a floor and ceiling,
//  effective-dated like every other rule. Every computation records the exact base
//  and rule version it used, so a settlement is reproducible (spec §7, §35).
//
//  CONFIG ONLY — computing a fee moves no money. Seeded values (2% each side, on the
//  ex-GST value) are editable defaults, not a commitment.
// ============================================================================

function mkt_fees_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_fee_rules (
        id $pk,
        code VARCHAR(40) DEFAULT '', name VARCHAR(160) DEFAULT '',
        payer VARCHAR(8) DEFAULT 'CLIENT', method VARCHAR(18) DEFAULT 'PERCENT',
        percent REAL DEFAULT 0, fixed REAL DEFAULT 0, min_fee REAL DEFAULT 0, max_fee REAL DEFAULT 0,
        base VARCHAR(10) DEFAULT 'EX_GST', category VARCHAR(40) DEFAULT '',
        effective_from VARCHAR(10) DEFAULT '', effective_until VARCHAR(10) DEFAULT '',
        status VARCHAR(10) DEFAULT 'ACTIVE', priority INT DEFAULT 0,
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) act_index('mkt_fee_rules', 'ix_fee_lookup', '(payer, status, category)');
    try { if ((int)ops_val("SELECT COUNT(*) FROM mkt_fee_rules") === 0) mkt_fees_seed_defaults(); } catch (Throwable $e) {}
}

function mkt_fee_payers() { return ['CLIENT' => 'Client', 'PRO' => 'Professional']; }
function mkt_fee_methods() { return ['PERCENT' => 'Percentage', 'FIXED' => 'Fixed', 'PERCENT_PLUS_FIXED' => 'Percentage + fixed']; }
function mkt_fee_bases() { return ['EX_GST' => 'Service value excl. GST', 'GROSS' => 'Gross service value']; }

function mkt_fees_seed_defaults() {
    $from = date('Y-m-d', strtotime('2026-04-01'));
    mkt_fee_save(['code' => 'CLIENT_STD', 'name' => 'Client marketplace fee', 'payer' => 'CLIENT', 'method' => 'PERCENT', 'percent' => 2, 'base' => 'EX_GST', 'effective_from' => $from], 'seed');
    mkt_fee_save(['code' => 'PRO_STD',   'name' => 'Professional marketplace fee', 'payer' => 'PRO', 'method' => 'PERCENT', 'percent' => 2, 'base' => 'EX_GST', 'effective_from' => $from], 'seed');
}

function mkt_fee_rules_all($activeOnly = false) {
    mkt_fees_migrate();
    $w = $activeOnly ? " WHERE status='ACTIVE'" : '';
    return ops_all("SELECT * FROM mkt_fee_rules$w ORDER BY payer, priority DESC, id") ?: [];
}
function mkt_fee_get($id) { mkt_fees_migrate(); return ops_one("SELECT * FROM mkt_fee_rules WHERE id=?", [(int)$id]) ?: null; }

/** Create or update a fee rule from posted fields. Returns [ok, message]. */
function mkt_fee_save(array $in, $by = '') {
    mkt_fees_migrate();
    $id     = (int)($in['id'] ?? 0);
    $payer  = strtoupper((string)($in['payer'] ?? 'CLIENT')) === 'PRO' ? 'PRO' : 'CLIENT';
    $method = strtoupper((string)($in['method'] ?? 'PERCENT'));
    if (!isset(mkt_fee_methods()[$method])) $method = 'PERCENT';
    $base   = strtoupper((string)($in['base'] ?? 'EX_GST')) === 'GROSS' ? 'GROSS' : 'EX_GST';
    $name   = trim((string)($in['name'] ?? '')); if ($name === '') return [false, 'Give the fee rule a name.'];
    $code   = trim((string)($in['code'] ?? '')) ?: strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 20));
    $percent = max(0.0, (float)($in['percent'] ?? 0));
    $fixed   = max(0.0, (float)($in['fixed'] ?? 0));
    $minFee  = max(0.0, (float)($in['min_fee'] ?? 0));
    $maxFee  = max(0.0, (float)($in['max_fee'] ?? 0));
    $from    = substr((string)($in['effective_from'] ?? date('Y-m-d')), 0, 10);
    $cat     = trim((string)($in['category'] ?? ''));
    $prio    = (int)($in['priority'] ?? 0);
    $now = date('c');
    if ($id > 0 && mkt_fee_get($id)) {
        db()->prepare("UPDATE mkt_fee_rules SET code=?,name=?,payer=?,method=?,percent=?,fixed=?,min_fee=?,max_fee=?,base=?,category=?,effective_from=?,priority=?,updated_at=? WHERE id=?")
            ->execute([$code, $name, $payer, $method, $percent, $fixed, $minFee, $maxFee, $base, $cat, $from, $prio, $now, $id]);
        return [true, 'Fee rule updated.'];
    }
    db()->prepare("INSERT INTO mkt_fee_rules (code,name,payer,method,percent,fixed,min_fee,max_fee,base,category,effective_from,status,priority,created_by,created_at,updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?, 'ACTIVE', ?, ?, ?, ?)")
        ->execute([$code, $name, $payer, $method, $percent, $fixed, $minFee, $maxFee, $base, $cat, $from, $prio, (string)$by, $now, $now]);
    return [true, 'Fee rule added.'];
}
function mkt_fee_retire($id) { mkt_fees_migrate(); db()->prepare("UPDATE mkt_fee_rules SET status='RETIRED', updated_at=? WHERE id=?")->execute([date('c'), (int)$id]); return true; }

/** The fee rule for a payer in force on a date, honouring category (exact, then default). */
function mkt_fee_resolve($payer, $onDate = null, $category = '') {
    mkt_fees_migrate();
    $payer = strtoupper((string)$payer) === 'PRO' ? 'PRO' : 'CLIENT';
    $d = substr((string)($onDate ?: date('Y-m-d')), 0, 10);
    $cat = trim((string)$category);
    if ($cat !== '') {
        $r = ops_one("SELECT * FROM mkt_fee_rules WHERE payer=? AND status='ACTIVE' AND category=? AND effective_from<=? AND (effective_until='' OR effective_until>=?) ORDER BY priority DESC, effective_from DESC LIMIT 1",
            [$payer, $cat, $d, $d]);
        if ($r) return $r;
    }
    return ops_one("SELECT * FROM mkt_fee_rules WHERE payer=? AND status='ACTIVE' AND category='' AND effective_from<=? AND (effective_until='' OR effective_until>=?) ORDER BY priority DESC, effective_from DESC LIMIT 1",
        [$payer, $d, $d]) ?: null;
}

/**
 * Compute a payer's marketplace fee on a base amount. Applies the rule's method, floor
 * and ceiling, and records the actual base used and the rule version — so it is fully
 * reproducible. Returns a snapshot array, or null when no fee rule applies (no fee).
 *   $baseAmount is the service value that matches the rule's `base` (caller supplies the
 *   ex-GST or gross figure per what the rule expects).
 */
function mkt_fee_compute($payer, $baseAmount, $onDate = null, $category = '') {
    $rule = mkt_fee_resolve($payer, $onDate, $category);
    if (!$rule) return null;
    $base = round((float)$baseAmount, 2);
    $fee = 0.0;
    if ($rule['method'] === 'FIXED')                $fee = (float)$rule['fixed'];
    elseif ($rule['method'] === 'PERCENT_PLUS_FIXED') $fee = $base * (float)$rule['percent'] / 100 + (float)$rule['fixed'];
    else                                             $fee = $base * (float)$rule['percent'] / 100;
    if ((float)$rule['min_fee'] > 0 && $fee < (float)$rule['min_fee']) $fee = (float)$rule['min_fee'];
    if ((float)$rule['max_fee'] > 0 && $fee > (float)$rule['max_fee']) $fee = (float)$rule['max_fee'];
    $fee = round($fee, 2);
    return [
        'fee' => $fee, 'payer' => (strtoupper((string)$payer) === 'PRO' ? 'PRO' : 'CLIENT'),
        'base_amount' => $base, 'base' => (string)$rule['base'], 'method' => (string)$rule['method'],
        'percent' => (float)$rule['percent'], 'fixed' => (float)$rule['fixed'],
        'fee_rule_id' => (int)$rule['id'], 'fee_rule_code' => (string)$rule['code'],
        'resolved_on' => substr((string)($onDate ?: date('Y-m-d')), 0, 10),
    ];
}
