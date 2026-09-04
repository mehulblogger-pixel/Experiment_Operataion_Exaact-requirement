<?php
// ============================================================================
//  FINANCIAL LEDGER + STATE MACHINE  (Phase 2 — GMV vs revenue vs provider cost)
//
//  The master spec's #1 accounting risk: never confuse the three different monies.
//    • GMV               — the underlying professional service value we facilitate.
//    • Connect revenue    — ONLY our own fees (subscriptions, packs, marketplace fee…).
//    • Payment-provider fee — Razorpay/Route charges: an EXPENSE, never our revenue.
//  Plus the money owed to professionals (PRO_PAYABLE) and cash from clients
//  (CLIENT_RECEIPT). This ledger records every money event under its correct category
//  so those quantities can never be summed into one another.
//
//  It also defines ONE canonical financial STATE MACHINE (replacing scattered
//  "paid = true" flags) that every money-bearing record can adopt.
//
//  This is a classification ledger (a full double-entry GL is Phase 7). Posting is
//  idempotent per (context, ref, category) so a repeated event never double-counts.
// ============================================================================

function mkt_ledger_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_ledger (
        id $pk,
        category VARCHAR(20) DEFAULT '', subtype VARCHAR(24) DEFAULT '',
        amount REAL DEFAULT 0, currency VARCHAR(8) DEFAULT 'INR', is_metric INT DEFAULT 0,
        context VARCHAR(24) DEFAULT '', ref_id INT DEFAULT 0,
        client_party_id INT DEFAULT 0, pro_id INT DEFAULT 0,
        occurred_on VARCHAR(10) DEFAULT '', note VARCHAR(300) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) {
        act_index('mkt_ledger', 'ix_led_cat', '(category, occurred_on)');
        act_index('mkt_ledger', 'ix_led_ref', '(context, ref_id, category)');
    }
}

/**
 * The money categories, and whether each is a METRIC (GMV — a facilitated-value figure,
 * not our cash) or a real financial line. Keeping GMV as a metric is what stops it being
 * booked as revenue.
 */
function mkt_ledger_categories() {
    return [
        'GMV'             => ['label' => 'GMV (facilitated)',     'metric' => true],
        'CONNECT_REVENUE' => ['label' => 'Connect revenue',        'metric' => false],
        'PROVIDER_FEE'    => ['label' => 'Payment-provider fee',   'metric' => false],
        'PRO_PAYABLE'     => ['label' => 'Payable to professionals','metric' => false],
        'CLIENT_RECEIPT'  => ['label' => 'Received from clients',  'metric' => false],
        'REFUND'          => ['label' => 'Refunds',               'metric' => false],
        'TAX_OUTPUT'      => ['label' => 'Output GST',            'metric' => false],
        'TDS'             => ['label' => 'TDS',                   'metric' => false],
        'TCS'             => ['label' => 'GST-TCS',               'metric' => false],
    ];
}
/** The revenue streams a CONNECT_REVENUE line can belong to. */
function mkt_revenue_streams() {
    return ['SUBSCRIPTION' => 'Subscriptions', 'CREDIT_PACK' => 'Credit packs', 'TXN_FEE' => 'Marketplace fee',
            'REPORTING' => 'Reporting', 'RANKING' => 'Ranking & visibility'];
}

function mkt_ledger_exists($context, $refId, $category) {
    mkt_ledger_migrate();
    return (bool) ops_val("SELECT id FROM mkt_ledger WHERE context=? AND ref_id=? AND category=? LIMIT 1",
        [(string)$context, (int)$refId, strtoupper((string)$category)]);
}

/**
 * Post a money event under its category. Idempotent per (context, ref, category): a
 * repeated post for the same event is ignored. Returns the row id, or 0 if skipped.
 */
function mkt_ledger_post($category, $amount, array $in = []) {
    mkt_ledger_migrate();
    $cats = mkt_ledger_categories();
    $category = strtoupper((string)$category);
    if (!isset($cats[$category])) return 0;
    $context = (string)($in['context'] ?? ''); $refId = (int)($in['ref_id'] ?? 0);
    if ($context !== '' && $refId > 0 && mkt_ledger_exists($context, $refId, $category)) return 0; // idempotent
    $metric = $cats[$category]['metric'] ? 1 : 0;
    db()->prepare("INSERT INTO mkt_ledger (category,subtype,amount,currency,is_metric,context,ref_id,client_party_id,pro_id,occurred_on,note,created_by,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$category, (string)($in['subtype'] ?? ''), round((float)$amount, 2), (string)($in['currency'] ?? 'INR'), $metric,
                   $context, $refId, (int)($in['client_party_id'] ?? 0), (int)($in['pro_id'] ?? 0),
                   substr((string)($in['occurred_on'] ?? date('Y-m-d')), 0, 10), (string)($in['note'] ?? ''),
                   (string)($in['by'] ?? ''), date('c')]);
    return (int)db()->lastInsertId();
}

/** Total by category over an optional [from,to] date window. */
function mkt_ledger_totals($from = null, $to = null) {
    mkt_ledger_migrate();
    $w = ''; $args = [];
    if ($from) { $w .= " AND occurred_on>=?"; $args[] = substr((string)$from, 0, 10); }
    if ($to)   { $w .= " AND occurred_on<=?"; $args[] = substr((string)$to, 0, 10); }
    $t = []; foreach (array_keys(mkt_ledger_categories()) as $c) $t[$c] = 0.0;
    foreach (ops_all("SELECT category, COALESCE(SUM(amount),0) s FROM mkt_ledger WHERE 1=1$w GROUP BY category", $args) ?: [] as $r)
        $t[strtoupper((string)$r['category'])] = (float)$r['s'];
    return $t;
}
/** Connect revenue split by stream. */
function mkt_ledger_revenue_by_stream($from = null, $to = null) {
    mkt_ledger_migrate();
    $w = ''; $args = [];
    if ($from) { $w .= " AND occurred_on>=?"; $args[] = substr((string)$from, 0, 10); }
    if ($to)   { $w .= " AND occurred_on<=?"; $args[] = substr((string)$to, 0, 10); }
    $out = []; foreach (array_keys(mkt_revenue_streams()) as $s) $out[$s] = 0.0;
    foreach (ops_all("SELECT subtype, COALESCE(SUM(amount),0) s FROM mkt_ledger WHERE category='CONNECT_REVENUE'$w GROUP BY subtype", $args) ?: [] as $r)
        $out[strtoupper((string)$r['subtype'])] = (float)$r['s'];
    return $out;
}
function mkt_ledger_recent($limit = 40) {
    mkt_ledger_migrate();
    return ops_all("SELECT * FROM mkt_ledger ORDER BY id DESC LIMIT " . (int)$limit) ?: [];
}
/** The take-rate = Connect revenue ÷ GMV (0 when no GMV). */
function mkt_take_rate($from = null, $to = null) {
    $t = mkt_ledger_totals($from, $to);
    return ($t['GMV'] ?? 0) > 0 ? round(($t['CONNECT_REVENUE'] ?? 0) / $t['GMV'] * 100, 2) : 0.0;
}

// ---------------------------------------------------------------------------
//  Canonical financial state machine (spec §59) — one vocabulary, not a bool.
// ---------------------------------------------------------------------------
function mkt_finstates() {
    return ['CREATED','PAYMENT_PENDING','PAID','PARTIALLY_PAID','PAYMENT_FAILED','UNDER_REVIEW',
            'ELIGIBLE_FOR_SETTLEMENT','SETTLEMENT_PENDING','SETTLED','PARTIALLY_SETTLED',
            'REFUND_PENDING','PARTIALLY_REFUNDED','REFUNDED','REVERSED','DISPUTED','CHARGEBACK','CLOSED'];
}
/** Allowed forward transitions. Terminal states (SETTLED/REFUNDED/REVERSED/CLOSED) have none. */
function mkt_finstate_next($state) {
    $m = [
        'CREATED'                 => ['PAYMENT_PENDING','PAID','CLOSED'],
        'PAYMENT_PENDING'         => ['PAID','PARTIALLY_PAID','PAYMENT_FAILED','CLOSED'],
        'PAYMENT_FAILED'          => ['PAYMENT_PENDING','CLOSED'],
        'PARTIALLY_PAID'          => ['PAID','REFUND_PENDING','DISPUTED','CLOSED'],
        'PAID'                    => ['UNDER_REVIEW','ELIGIBLE_FOR_SETTLEMENT','REFUND_PENDING','DISPUTED','CHARGEBACK'],
        'UNDER_REVIEW'            => ['ELIGIBLE_FOR_SETTLEMENT','DISPUTED','REFUND_PENDING'],
        'ELIGIBLE_FOR_SETTLEMENT' => ['SETTLEMENT_PENDING','SETTLED','DISPUTED'],
        'SETTLEMENT_PENDING'      => ['SETTLED','PARTIALLY_SETTLED','DISPUTED'],
        'PARTIALLY_SETTLED'       => ['SETTLED','DISPUTED'],
        'REFUND_PENDING'          => ['PARTIALLY_REFUNDED','REFUNDED','REVERSED'],
        'PARTIALLY_REFUNDED'      => ['REFUNDED','CLOSED'],
        'DISPUTED'                => ['ELIGIBLE_FOR_SETTLEMENT','REFUND_PENDING','CHARGEBACK','CLOSED'],
        'CHARGEBACK'              => ['REVERSED','CLOSED'],
    ];
    return $m[strtoupper((string)$state)] ?? [];
}
function mkt_finstate_can($from, $to) { return in_array(strtoupper((string)$to), mkt_finstate_next($from), true); }
function mkt_finstate_is_terminal($state) { return mkt_finstate_next($state) === [] && in_array(strtoupper((string)$state), mkt_finstates(), true); }

/** Route handler — the daily financial-control dashboard (master / marketplace desk). */
function ops_mkt_ledger($method) {
    ops_require((function_exists('is_master') && is_master()) || (function_exists('connect_market_can') && connect_market_can()),
        'Only the marketplace desk can see financial control.');
    mkt_ledger_migrate();
    $from = ($_GET['from'] ?? '') !== '' ? (string)$_GET['from'] : date('Y-m-01');
    $to   = ($_GET['to'] ?? '') !== '' ? (string)$_GET['to'] : date('Y-m-d');
    view('ops/mkt_ledger', [
        'from' => $from, 'to' => $to,
        'totals'  => mkt_ledger_totals($from, $to),
        'streams' => mkt_ledger_revenue_by_stream($from, $to),
        'takeRate'=> mkt_take_rate($from, $to),
        'recent'  => mkt_ledger_recent(40),
        'cats'    => mkt_ledger_categories(),
        'streamLabels' => mkt_revenue_streams(),
        'currency'=> function_exists('mkt_currency') ? mkt_currency() : '₹',
    ]);
    return true;
}
