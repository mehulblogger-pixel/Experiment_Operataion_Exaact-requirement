<?php
// ============================================================================
//  MARKETPLACE ESCROW  (Step 1 — the lifecycle, gateway-OFF)
//
//  Trust between strangers on the marketplace comes from escrow: the client's money
//  is HELD when a job is booked and RELEASED to the professional only when the work
//  is proven done — which, in this system, means an approved inspection report backed
//  by online attendance timestamps. If the job is cancelled the money is REFUNDED; a
//  DISPUTE parks it until someone resolves it either way.
//
//  This step models that LIFECYCLE only. No money actually moves yet — the real hold
//  and settlement will run through a licensed aggregator's marketplace facility
//  (e.g. Razorpay Route) in Step 2. Every record therefore keeps slots for the
//  gateway references (order / transfer ids) that Step 2 will fill in.
//
//  SAFE BY DEFAULT: escrow is behind a master switch (escrow_enabled, default OFF).
//  While OFF the marketplace behaves exactly as today — no holds are created and no
//  screen changes for anyone.
// ============================================================================

function mkt_escrow_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    db()->exec("CREATE TABLE IF NOT EXISTS mkt_escrow (
        id $pk,
        engagement_id INT DEFAULT 0, requirement_id INT DEFAULT 0,
        client_party_id INT DEFAULT 0, client_name VARCHAR(200) DEFAULT '',
        pro_kind VARCHAR(16) DEFAULT '', pro_id INT DEFAULT 0, pro_name VARCHAR(200) DEFAULT '',
        amount REAL DEFAULT 0, commission REAL DEFAULT 0, net_to_pro REAL DEFAULT 0,
        currency VARCHAR(8) DEFAULT 'INR', status VARCHAR(12) DEFAULT 'HELD',
        dispute_reason VARCHAR(500) DEFAULT '', notes VARCHAR(500) DEFAULT '',
        gateway_order_id VARCHAR(64) DEFAULT '', gateway_transfer_id VARCHAR(64) DEFAULT '',
        held_at VARCHAR(30) DEFAULT '', released_at VARCHAR(30) DEFAULT '', refunded_at VARCHAR(30) DEFAULT '',
        created_by VARCHAR(150) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    if (function_exists('act_index')) {
        act_index('mkt_escrow', 'ix_escrow_eng', '(engagement_id)');
        act_index('mkt_escrow', 'ix_escrow_status', '(status)');
    }
}

/** Master switch — is escrow live on this install? Default OFF (marketplace unchanged). */
function mkt_escrow_enabled() { return (int)setting_get('escrow_enabled', 0) === 1; }
function mkt_escrow_set_enabled($on) { setting_set('escrow_enabled', $on ? 1 : 0); return true; }

/** Default platform commission % taken at release (Super-Admin owned, editable). */
function mkt_escrow_commission_pct() { return max(0.0, (float)setting_get('escrow_commission_pct', 0)); }

function mkt_escrow_statuses() { return ['HELD', 'DISPUTED', 'RELEASED', 'REFUNDED']; }

/** The status transitions escrow allows. Terminal states (RELEASED/REFUNDED) have none. */
function mkt_escrow_allowed_next($status) {
    switch (strtoupper((string)$status)) {
        case 'HELD':     return ['RELEASED', 'REFUNDED', 'DISPUTED'];
        case 'DISPUTED': return ['RELEASED', 'REFUNDED'];
        default:         return [];
    }
}

function mkt_escrow_get($id) { mkt_escrow_migrate(); return ops_one("SELECT * FROM mkt_escrow WHERE id=?", [(int)$id]) ?: null; }
/** The live (non-terminal) escrow for an engagement, if any. */
function mkt_escrow_for_engagement($engId) {
    mkt_escrow_migrate();
    return ops_one("SELECT * FROM mkt_escrow WHERE engagement_id=? AND status IN ('HELD','DISPUTED') ORDER BY id DESC LIMIT 1", [(int)$engId]) ?: null;
}

/**
 * Open (fund) an escrow hold for an engagement. In this gateway-OFF step this records
 * that the client's money is HELD; Step 2 fills the gateway_order_id when the real
 * capture happens. Commission defaults to the configured %. Returns [ok, message, id].
 * Idempotent per engagement — a live hold is returned rather than duplicated.
 */
function mkt_escrow_open($engId, $amount, array $in = []) {
    mkt_escrow_migrate();
    $engId = (int)$engId; $amount = round((float)$amount, 2);
    if ($engId <= 0) return [false, 'Unknown engagement.', 0];
    if ($amount <= 0) return [false, 'The hold amount must be greater than zero.', 0];
    $live = mkt_escrow_for_engagement($engId);
    if ($live) return [true, 'A hold already exists for this engagement.', (int)$live['id']];

    $commission = array_key_exists('commission', $in)
        ? max(0.0, round((float)$in['commission'], 2))
        : round($amount * mkt_escrow_commission_pct() / 100, 2);
    if ($commission > $amount) $commission = $amount;
    $net = round($amount - $commission, 2);
    $now = date('c');
    db()->prepare("INSERT INTO mkt_escrow
        (engagement_id,requirement_id,client_party_id,client_name,pro_kind,pro_id,pro_name,
         amount,commission,net_to_pro,currency,status,gateway_order_id,held_at,created_by,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?, 'HELD', ?, ?, ?, ?, ?)")
        ->execute([
            $engId, (int)($in['requirement_id'] ?? 0), (int)($in['client_party_id'] ?? 0), (string)($in['client_name'] ?? ''),
            (string)($in['pro_kind'] ?? ''), (int)($in['pro_id'] ?? 0), (string)($in['pro_name'] ?? ''),
            $amount, $commission, $net, (string)($in['currency'] ?? 'INR'),
            (string)($in['gateway_order_id'] ?? ''), $now, (string)($in['by'] ?? ''), $now, $now,
        ]);
    return [true, 'Client funds held in escrow.', (int)db()->lastInsertId()];
}

/** Internal: move an escrow to a new status if the transition is allowed. Returns [ok,msg]. */
function _mkt_escrow_transition($id, $to, array $set = []) {
    $row = mkt_escrow_get($id);
    if (!$row) return [false, 'That escrow hold was not found.'];
    $to = strtoupper((string)$to);
    if (strtoupper((string)$row['status']) === $to) return [true, 'No change.'];
    if (!in_array($to, mkt_escrow_allowed_next($row['status']), true)) {
        return [false, 'An escrow that is ' . strtolower((string)$row['status']) . ' cannot be ' . strtolower($to) . '.'];
    }
    $cols = ['status=?', 'updated_at=?']; $args = [$to, date('c')];
    foreach ($set as $c => $v) { $cols[] = "$c=?"; $args[] = $v; }
    $args[] = (int)$id;
    db()->prepare("UPDATE mkt_escrow SET " . implode(', ', $cols) . " WHERE id=?")->execute($args);
    return [true, 'Done.'];
}

/** Release the held funds to the professional (job proven done). HELD/DISPUTED → RELEASED. */
function mkt_escrow_release($id, $by = '', $note = '') {
    $row = mkt_escrow_get($id);
    $res = _mkt_escrow_transition($id, 'RELEASED', ['released_at' => date('c'), 'created_by' => (string)$by === '' ? ($row['created_by'] ?? '') : $by, 'notes' => (string)$note]);
    // Phase 2 — book the three DIFFERENT monies separately (never conflate them):
    // GMV = the facilitated service value; Connect revenue = only our commission;
    // PRO_PAYABLE = what the professional is owed; CLIENT_RECEIPT = cash in.
    if (!empty($res[0]) && $row && function_exists('mkt_ledger_post')) {
        $opts = ['context' => 'ESCROW', 'ref_id' => (int)$row['id'], 'currency' => (string)$row['currency'],
                 'client_party_id' => (int)$row['client_party_id'], 'pro_id' => (int)$row['pro_id'], 'by' => (string)$by];
        mkt_ledger_post('GMV',            (float)$row['amount'],     $opts + ['note' => 'Facilitated service value']);
        mkt_ledger_post('CLIENT_RECEIPT', (float)$row['amount'],     $opts + ['note' => 'Client funds settled']);
        mkt_ledger_post('CONNECT_REVENUE',(float)$row['commission'], $opts + ['subtype' => 'TXN_FEE', 'note' => 'Marketplace fee']);
        mkt_ledger_post('PRO_PAYABLE',    (float)$row['net_to_pro'], $opts + ['note' => 'Payable to professional']);
    }
    return $res;
}
/** Refund the held funds to the client (job cancelled / dispute upheld). HELD/DISPUTED → REFUNDED. */
function mkt_escrow_refund($id, $by = '', $reason = '') {
    return _mkt_escrow_transition($id, 'REFUNDED', ['refunded_at' => date('c'), 'dispute_reason' => (string)$reason]);
}
/** Raise a dispute — parks the held funds until resolved. HELD → DISPUTED. */
function mkt_escrow_dispute($id, $by = '', $reason = '') {
    return _mkt_escrow_transition($id, 'DISPUTED', ['dispute_reason' => (string)$reason]);
}
/** Resolve a dispute either way. DISPUTED → RELEASED | REFUNDED. */
function mkt_escrow_resolve($id, $outcome, $by = '', $note = '') {
    $outcome = strtoupper((string)$outcome) === 'REFUND' ? 'REFUNDED' : 'RELEASED';
    return $outcome === 'REFUNDED' ? mkt_escrow_refund($id, $by, $note) : mkt_escrow_release($id, $by, $note);
}

/**
 * The release TRIGGER this platform is built for: when an inspection report for an
 * engagement is approved/issued, the held funds are released to the professional.
 * A no-op while escrow is OFF, or when there is no live hold. Returns true if it
 * released a hold. (Wired into the report-issue flow in Step 2 alongside the gateway.)
 */
function mkt_escrow_on_report_approved($engId, $by = 'report approved') {
    if (!mkt_escrow_enabled()) return false;
    $live = mkt_escrow_for_engagement($engId);
    if (!$live || strtoupper((string)$live['status']) !== 'HELD') return false;
    [$ok] = mkt_escrow_release((int)$live['id'], $by, 'Auto-released on report approval.');
    return (bool)$ok;
}

/** All escrow rows for the management screen, newest first (optionally by status). */
function mkt_escrow_all($status = null, $limit = 200) {
    mkt_escrow_migrate();
    if ($status) return ops_all("SELECT * FROM mkt_escrow WHERE status=? ORDER BY id DESC LIMIT " . (int)$limit, [strtoupper((string)$status)]) ?: [];
    return ops_all("SELECT * FROM mkt_escrow ORDER BY id DESC LIMIT " . (int)$limit) ?: [];
}
/** Totals for the management header (money currently held, released, refunded). */
function mkt_escrow_totals() {
    mkt_escrow_migrate();
    $t = ['HELD' => 0.0, 'DISPUTED' => 0.0, 'RELEASED' => 0.0, 'REFUNDED' => 0.0];
    foreach (ops_all("SELECT status, COALESCE(SUM(amount),0) s FROM mkt_escrow GROUP BY status") ?: [] as $r) $t[strtoupper((string)$r['status'])] = (float)$r['s'];
    return $t;
}

/** Route handler — the escrow management screen (master / marketplace desk). Always returns true. */
function ops_mkt_escrow($method) {
    ops_require((function_exists('is_master') && is_master()) || (function_exists('connect_market_can') && connect_market_can()),
        'Only the marketplace desk can manage escrow.');
    mkt_escrow_migrate();
    if ($method === 'POST') {
        $act = (string)($_POST['action'] ?? '');
        $id  = (int)($_POST['id'] ?? 0);
        $by  = function_exists('user_name') && function_exists('current_user') ? (string)user_name(current_user()) : '';
        if ($act === 'toggle')       { mkt_escrow_set_enabled(!empty($_POST['escrow_enabled'])); flash('Escrow setting saved.'); }
        elseif ($act === 'settings') { setting_set('escrow_commission_pct', max(0, (float)($_POST['escrow_commission_pct'] ?? 0))); flash('Escrow settings saved.'); }
        elseif ($act === 'release')  { [$ok, $m] = mkt_escrow_release($id, $by); flash($m, $ok ? 'success' : 'error'); }
        elseif ($act === 'refund')   { [$ok, $m] = mkt_escrow_refund($id, $by, (string)($_POST['reason'] ?? '')); flash($m, $ok ? 'success' : 'error'); }
        elseif ($act === 'dispute')  { [$ok, $m] = mkt_escrow_dispute($id, $by, (string)($_POST['reason'] ?? '')); flash($m, $ok ? 'success' : 'error'); }
        elseif ($act === 'resolve')  { [$ok, $m] = mkt_escrow_resolve($id, (string)($_POST['outcome'] ?? 'RELEASE'), $by, (string)($_POST['reason'] ?? '')); flash($m, $ok ? 'success' : 'error'); }
        redirect('/marketplace-escrow');
    }
    view('ops/mkt_escrow', [
        'rows'          => mkt_escrow_all(),
        'totals'        => mkt_escrow_totals(),
        'enabled'       => mkt_escrow_enabled(),
        'commissionPct' => mkt_escrow_commission_pct(),
        'currency'      => function_exists('mkt_currency') ? mkt_currency() : '₹',
    ]);
    return true;
}
