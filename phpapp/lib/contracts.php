<?php
// ===========================================================================
//  Contract validity — dates and quantity
//
//  A won quotation is not a licence to inspect forever. It runs to an end
//  date, and an open order carries a quantity that gets used up. Both of them
//  used to be invisible at the moment they mattered — when a coordinator
//  schedules the next visit — so work could be booked against a contract that
//  had run out in either sense, and the first anyone knew was when the client
//  refused the invoice.
//
//  Three states, three different answers:
//
//    EXPIRING   the contract ends within the warning window. Everyone
//               involved is e-mailed once, and the screens say so. Nothing
//               is blocked — this is the month you use to get it renewed.
//
//    EXPIRED    the end date has passed. Scheduling is blocked. Only the
//               Super Admin can let a job through, against a written reason.
//
//    EXHAUSTED  the quantity sold has been used up. Scheduling is blocked.
//               The Branch Manager endorses the request first, then the
//               Super Admin grants it — two people, because over-serving an
//               order is a commercial decision, not an operational one.
//
//  Nothing here silently deletes or alters a contract. It gates the one
//  action that costs money — putting an engineer on site — and records who
//  decided otherwise.
// ===========================================================================

function contracts_migrate() {
    $pdo = db(); $pk = pk_clause();
    // Quantity sold on the contract, and how it is counted.
    ensure_column('partner_contracts', 'qty_total', 'DECIMAL(14,2) NULL');
    ensure_column('partner_contracts', 'qty_unit', "VARCHAR(30) DEFAULT ''");
    ensure_column('partner_contracts', 'expiry_notified', "VARCHAR(20) DEFAULT ''");
    ensure_column('partner_contracts', 'quotation_id', 'INT NULL');
    // Opening a new contract number is a two-signature act (a manager endorses,
    // the branch manager approves), and an idle contract is closed automatically.
    // These columns carry that lifecycle. Legacy rows default to OPEN so nothing
    // that already exists is suddenly treated as un-approved.
    ensure_column('partner_contracts', 'open_status',       "VARCHAR(20) DEFAULT 'OPEN'");   // PENDING · OPEN · REJECTED · CLOSED
    ensure_column('partner_contracts', 'branch_id',         'INT NULL');
    ensure_column('partner_contracts', 'requested_by',      "VARCHAR(150) DEFAULT ''");
    ensure_column('partner_contracts', 'requested_by_id',   'INT NULL');
    ensure_column('partner_contracts', 'requested_at',      "VARCHAR(30) DEFAULT ''");
    ensure_column('partner_contracts', 'mgr_endorsed_by',   "VARCHAR(150) DEFAULT ''");
    ensure_column('partner_contracts', 'mgr_endorsed_by_id','INT NULL');
    ensure_column('partner_contracts', 'mgr_endorsed_at',   "VARCHAR(30) DEFAULT ''");
    ensure_column('partner_contracts', 'bm_approved_by',    "VARCHAR(150) DEFAULT ''");
    ensure_column('partner_contracts', 'bm_approved_by_id', 'INT NULL');
    ensure_column('partner_contracts', 'bm_approved_at',    "VARCHAR(30) DEFAULT ''");
    ensure_column('partner_contracts', 'opened_at',         "VARCHAR(30) DEFAULT ''");
    ensure_column('partner_contracts', 'closed_at',         "VARCHAR(30) DEFAULT ''");
    ensure_column('partner_contracts', 'close_reason',      "VARCHAR(300) DEFAULT ''");
    ensure_column('partner_contracts', 'auto_closed',       'INT DEFAULT 0');
    ensure_column('partner_contracts', 'last_activity_at',  "VARCHAR(30) DEFAULT ''");
    ensure_column('partner_contracts', 'idle_notified',     "VARCHAR(20) DEFAULT ''");
    ensure_column('partner_contracts', 'coordinator_id',    'INT NULL');   // coordinator nominated at endorsement to own the calls raised from this contract
    // Field #1 — the registration DOCUMENT itself (the scanned GST/PAN/ISO/licence
    // certificate), attached to its registration row under Client Registration →
    // Registration. Stored in-row as base64, exactly like a lead/quote file.
    ensure_column('partner_registrations', 'file_name',   "VARCHAR(200) DEFAULT ''");
    ensure_column('partner_registrations', 'mime',        "VARCHAR(100) DEFAULT ''");
    ensure_column('partner_registrations', 'file_data',   'MEDIUMTEXT');
    ensure_column('partner_registrations', 'uploaded_by', "VARCHAR(150) DEFAULT ''");
    ensure_column('partner_registrations', 'uploaded_at', "VARCHAR(30) DEFAULT ''");
    // Field #3 — "Quantity sold" as a LIST of line items (man-days / man-months /
    // other), like a PO's lines, instead of one number. The contract's qty_total is
    // kept as the SUM of these, so every existing quantity gate keeps working unchanged.
    $pdo->exec("CREATE TABLE IF NOT EXISTS contract_line_items (
        id $pk, contract_id INT, description VARCHAR(200) DEFAULT '',
        unit VARCHAR(30) DEFAULT 'MANDAY', quantity DECIMAL(14,2) DEFAULT 0,
        consumed DECIMAL(14,2) DEFAULT 0, sort_order INT DEFAULT 0)");
    if (function_exists('act_index')) act_index('contract_line_items', 'idx_cli_contract', '(contract_id)');
    // An override is a written request to schedule anyway. It carries its own
    // two-step approval, so the same row records who asked, who endorsed and
    // who finally granted it.
    $pdo->exec("CREATE TABLE IF NOT EXISTS contract_overrides (
        id $pk, quotation_id INT NULL, contract_id INT NULL, call_id INT NULL,
        kind VARCHAR(20) DEFAULT 'EXPIRED',
        reason VARCHAR(500) DEFAULT '',
        status VARCHAR(20) DEFAULT 'PENDING',
        requested_by VARCHAR(150) DEFAULT '', requested_by_id INT NULL, requested_at VARCHAR(30) DEFAULT '',
        endorsed_by VARCHAR(150) DEFAULT '', endorsed_at VARCHAR(30) DEFAULT '', endorse_note VARCHAR(400) DEFAULT '',
        decided_by VARCHAR(150) DEFAULT '', decided_at VARCHAR(30) DEFAULT '', decide_note VARCHAR(400) DEFAULT '',
        valid_until VARCHAR(20) DEFAULT '', uses_allowed INT DEFAULT 1, uses_taken INT DEFAULT 0,
        created_at VARCHAR(30) DEFAULT '')");
}

const CONTRACT_STATES = [
    'NONE'      => 'No contract on file',
    'OK'        => 'In force',
    'EXPIRING'  => 'Expiring soon',
    'EXPIRED'   => 'Expired',
    'QTY_LOW'   => 'Quantity nearly used up',
    'EXHAUSTED' => 'Quantity exhausted',
];
const OVERRIDE_KINDS = ['EXPIRED' => 'Expired contract', 'EXHAUSTED' => 'Quantity exhausted'];
// Two different routes, because they are two different decisions.
//
//   EXPIRED    working past the end date is a matter of company risk, and one
//              person carries it: PENDING -> APPROVED. The Super Admin alone.
//
//   EXHAUSTED  serving more than the order was sold for costs money that
//              somebody has to own locally first: PENDING -> ENDORSED by the
//              Branch Manager -> APPROVED by the Super Admin.
//
// Either can be REFUSED at any step it has reached.
const OVERRIDE_STATUS = [
    'PENDING'  => 'Awaiting decision',
    'ENDORSED' => 'Awaiting Super Admin',
    'APPROVED' => 'Approved',
    'REFUSED'  => 'Refused',
];
// Does this kind need a Branch Manager to endorse it before the Super Admin?
function override_needs_endorsement($kind) { return $kind === 'EXHAUSTED'; }
// Who the request is sitting with right now, in words.
function override_status_text($row) {
    $st = $row['status'] ?? 'PENDING';
    if ($st === 'PENDING')
        return override_needs_endorsement($row['kind'] ?? '') ? 'Awaiting Branch Manager' : 'Awaiting Super Admin';
    return OVERRIDE_STATUS[$st] ?? $st;
}
// The rule, stated in one sentence, for whichever kind is in play.
function override_flow_text($kind) {
    return override_needs_endorsement($kind)
        ? 'The Branch Manager endorses that the extra work is genuinely needed, then the Super Admin decides whether the company will carry it.'
        : 'Only the Super Admin can allow work against an expired contract.';
}

// How many days ahead counts as "expiring soon". Configurable, defaults to a month.
function contract_warn_days() {
    $n = (int)setting_get('contract_warn_days', 30);
    return $n > 0 ? $n : 30;
}

// ---------------------------------------------------------------------------
//  Quantity: what was sold, and what has been used
// ---------------------------------------------------------------------------
// Total quantity on a quotation, from its line items. Only lines charged by a
// countable unit are summed — a lump sum has no quantity to exhaust.
function quote_qty_total($quoteId) {
    $quoteId = (int)$quoteId;
    if (!$quoteId) return null;
    $rows = ops_all("SELECT qty, unit FROM quote_lines WHERE quote_id=?", [$quoteId]);
    $sum = 0.0; $any = false;
    foreach ($rows as $r) {
        $u = strtoupper(trim((string)($r['unit'] ?? '')));
        if ($u === '' || $u === 'LUMP' || $u === 'LUMPSUM' || $u === 'LS') continue;
        $q = (float)($r['qty'] ?? 0);
        if ($q <= 0) continue;
        $sum += $q; $any = true;
    }
    return $any ? $sum : null;
}

// ---- Field #3 — contract quantity as a list of line items --------------------
// The lines on a contract (man-days / man-months / other), in order.
function contract_lines($contractId) {
    return ops_all("SELECT * FROM contract_line_items WHERE contract_id=? ORDER BY sort_order, id", [(int)$contractId]) ?: [];
}
// Keep partner_contracts.qty_total (and a dominant qty_unit label) in step with the
// sum of the line items, so every quantity gate that reads qty_total keeps working.
// With no line items the manually-typed qty_total is left as-is (backward compatible).
function contract_sync_qty($contractId) {
    $contractId = (int)$contractId;
    $n = (int)ops_val("SELECT COUNT(*) FROM contract_line_items WHERE contract_id=?", [$contractId]);
    if ($n === 0) return;
    $sum  = (float) ops_val("SELECT COALESCE(SUM(quantity),0) FROM contract_line_items WHERE contract_id=?", [$contractId]);
    $unit = (string) ops_val("SELECT unit FROM contract_line_items WHERE contract_id=? GROUP BY unit ORDER BY SUM(quantity) DESC LIMIT 1", [$contractId]);
    db()->prepare("UPDATE partner_contracts SET qty_total=?, qty_unit=? WHERE id=?")->execute([$sum, substr($unit, 0, 30), $contractId]);
}
// Replace a contract's line set with $lines (each ['description','unit','quantity']);
// blank rows are dropped. Consumed stays 0 — contract consumption is measured from the
// jobs, unchanged. Re-syncs qty_total afterwards. Returns the number of lines kept.
function contract_replace_lines($contractId, array $lines) {
    $contractId = (int)$contractId;
    db()->prepare("DELETE FROM contract_line_items WHERE contract_id=?")->execute([$contractId]);
    $ins = db()->prepare("INSERT INTO contract_line_items (contract_id,description,unit,quantity,consumed,sort_order) VALUES (?,?,?,?,0,?)");
    $kept = 0; $ord = 0;
    foreach ($lines as $ln) {
        $desc = trim((string)($ln['description'] ?? ''));
        $qty  = (float)($ln['quantity'] ?? 0);
        $unit = trim((string)($ln['unit'] ?? '')) ?: 'MANDAY';
        if ($desc === '' && $qty <= 0) continue;          // skip an empty row
        $ord += 10;
        $ins->execute([$contractId, substr($desc, 0, 200), substr($unit, 0, 30), $qty, $ord]);
        $kept++;
    }
    contract_sync_qty($contractId);
    return $kept;
}
// Build a line-item array from posted parallel arrays (desc[], qty[], unit[]).
function contract_lines_from_post(array $b) {
    $desc = (array)($b['cl_desc']  ?? []);
    $qty  = (array)($b['cl_qty']   ?? []);
    $unit = (array)($b['cl_unit']  ?? []);
    $out = [];
    foreach ($desc as $i => $d)
        $out[] = ['description' => (string)$d, 'quantity' => (float)($qty[$i] ?? 0), 'unit' => (string)($unit[$i] ?? 'MANDAY')];
    return $out;
}
// A contract's quantity lines seeded from a quotation's countable line items.
function contract_lines_from_quote($quoteId) {
    $rows = ops_all("SELECT description, service_type, location, unit, qty FROM quote_lines WHERE quote_id=? ORDER BY line_no, id", [(int)$quoteId]) ?: [];
    $out = [];
    foreach ($rows as $r) {
        $u = strtoupper(trim((string)($r['unit'] ?? '')));
        if ($u === '' || in_array($u, ['LUMP', 'LUMPSUM', 'LS'], true)) continue;   // a lump sum has no quantity
        $q = (float)($r['qty'] ?? 0);
        if ($q <= 0) continue;
        $d = trim((string)($r['description'] ?? '')) ?: trim((string)($r['service_type'] ?? '')) ?: 'Item';
        if (trim((string)($r['location'] ?? '')) !== '') $d .= ' — ' . $r['location'];
        $out[] = ['description' => $d, 'quantity' => $q, 'unit' => $u];
    }
    return $out;
}

// Quantity already consumed: man-days booked on jobs raised against this
// quotation, plus man-days on jobs hanging off its calls.
function quote_qty_used($quoteId) {
    $quoteId = (int)$quoteId;
    if (!$quoteId) return 0.0;
    $direct = (float)ops_val("SELECT COALESCE(SUM(mandays),0) FROM jobs WHERE quotation_id=?", [$quoteId]);
    $viaCall = (float)ops_val(
        "SELECT COALESCE(SUM(j.mandays),0) FROM jobs j
         JOIN calls c ON c.id=j.call_id
         WHERE c.quotation_id=? AND (j.quotation_id IS NULL OR j.quotation_id<>?)", [$quoteId, $quoteId]);
    return $direct + $viaCall;
}

// ---------------------------------------------------------------------------
//  The status of the commercial cover behind a quotation
// ---------------------------------------------------------------------------
function contract_state($quoteId, $today = null) {
    $today = $today ?: date('Y-m-d');
    $quoteId = (int)$quoteId;
    $out = ['state' => 'NONE', 'end_date' => '', 'days_left' => null,
            'qty_total' => null, 'qty_used' => 0.0, 'qty_left' => null,
            'contract_id' => null, 'contract_number' => '', 'quotation_id' => $quoteId];
    if (!$quoteId) return $out;
    $q = ops_one("SELECT id, quote_no, rev, contract_number, contract_id, client_id, status FROM quotations WHERE id=?", [$quoteId]);
    if (!$q) return $out;
    $out['contract_number'] = (string)($q['contract_number'] ?? '');

    $c = null;
    if (!empty($q['contract_id'])) $c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [(int)$q['contract_id']]);
    if (!$c && $out['contract_number'] !== '' && !empty($q['client_id']))
        $c = ops_one("SELECT * FROM partner_contracts WHERE partner_id=? AND contract_number=?",
                     [(int)$q['client_id'], $out['contract_number']]);
    if ($c) {
        $out['contract_id'] = (int)$c['id'];
        $out['end_date'] = (string)($c['end_date'] ?? '');
    }

    // Quantity: the contract's own figure wins, else the quotation's lines.
    // ?? because a database caught mid-upgrade has the row but not the column,
    // and a warning on every scheduling screen is not an acceptable way to
    // find that out.
    $cQty = $c ? ($c['qty_total'] ?? null) : null;
    $qtyTotal = ($cQty !== null && $cQty !== '') ? (float)$cQty : quote_qty_total($quoteId);
    $out['qty_total'] = $qtyTotal;
    $out['qty_used']  = quote_qty_used($quoteId);
    if ($qtyTotal !== null) $out['qty_left'] = round($qtyTotal - $out['qty_used'], 2);

    if ($out['end_date'] !== '') $out['days_left'] = days_between($today, $out['end_date']);
    return contract_classify($out);
}
// The pure verdict — extracted so every surface (the scheduling gate via
// contract_state(), and the Contract 360 via contract_state_row()) reads the
// SAME classification from the same thresholds. No second formula. $out must
// already carry end_date, days_left, qty_total and qty_left.
function contract_classify($out) {
    // Dates decide first — an expired contract is expired regardless of quantity.
    if ($out['end_date'] !== '' && $out['days_left'] !== null) {
        if ($out['days_left'] < 0)  { $out['state'] = 'EXPIRED';  return $out; }
        if ($out['days_left'] <= contract_warn_days()) $out['state'] = 'EXPIRING';
    }
    if ($out['qty_total'] !== null) {
        if ($out['qty_left'] !== null && $out['qty_left'] <= 0) { $out['state'] = 'EXHAUSTED'; return $out; }
        if ($out['state'] === 'NONE' || $out['state'] === 'OK') {
            $pct = $out['qty_total'] > 0 ? ($out['qty_left'] / $out['qty_total']) : 1;
            if ($pct <= 0.1) $out['state'] = 'QTY_LOW';
        }
    }
    if ($out['state'] === 'NONE' && ($out['end_date'] !== '' || $out['qty_total'] !== null)) $out['state'] = 'OK';
    return $out;
}
// Module 18 — the contract's live state for the 360, keyed off the contract ROW.
// When a quotation drives the gate we return the canonical engine's verdict (the
// exact one scheduling uses); a contract recorded directly on the client has no
// quote, so we feed the row's own end_date/qty through the SAME classifier.
function contract_state_row($c) {
    if (!empty($c['quotation_id'])) return contract_state((int)$c['quotation_id']);
    $out = ['state' => 'NONE', 'end_date' => (string)($c['end_date'] ?? ''), 'days_left' => null,
            'qty_total' => null, 'qty_used' => 0.0, 'qty_left' => null,
            'contract_id' => (int)($c['id'] ?? 0), 'contract_number' => (string)($c['contract_number'] ?? ''), 'quotation_id' => 0];
    $qt = $c['qty_total'] ?? null;
    if ($qt !== null && $qt !== '') { $out['qty_total'] = (float)$qt; $out['qty_left'] = (float)$qt; }
    if ($out['end_date'] !== '') $out['days_left'] = days_between(date('Y-m-d'), $out['end_date']);
    return contract_classify($out);
}
function contract_state_blocks($state) { return in_array($state, ['EXPIRED', 'EXHAUSTED'], true); }
// Module 34 — a canonical count of OPEN contracts inside the expiry warning window,
// so a dashboard can surface "expiring soon" without re-deriving the rule. Reuses
// contract_warn_days(). Advisory count (company-wide, like the rest of this file).
function contracts_expiring_count($withinDays = null) {
    $warn  = $withinDays !== null ? (int)$withinDays : (function_exists('contract_warn_days') ? contract_warn_days() : 30);
    $today = date('Y-m-d');
    $limit = date('Y-m-d', strtotime('+' . max(0, $warn) . ' days'));
    try {
        return (int)ops_val("SELECT COUNT(*) FROM partner_contracts
            WHERE COALESCE(open_status,'OPEN')='OPEN' AND COALESCE(end_date,'')<>''
              AND end_date >= ? AND end_date <= ?", [$today, $limit]);
    } catch (Throwable $e) { return 0; }
}
// A short human label + severity for a contract state, for the 360 banner.
function contract_state_label($state) {
    return [
        'EXPIRED'   => ['bad',  'Expired',        'This contract has passed its end date — scheduling is blocked until it is renewed or an override is granted.'],
        'EXHAUSTED' => ['bad',  'Quantity used up','The agreed quantity is fully consumed — scheduling is blocked until it is extended or an override is granted.'],
        'EXPIRING'  => ['warn', 'Expiring soon',  'This contract is within its expiry window — plan the renewal before it lapses.'],
        'QTY_LOW'   => ['warn', 'Quantity low',   'Less than 10% of the agreed quantity remains.'],
        'OK'        => ['ok',   'In force',       'Within its term and quantity.'],
        'NONE'      => ['mut',  'No term set',    'No end date or quantity is recorded, so no expiry/quantity gate applies.'],
    ][$state] ?? ['mut', $state, ''];
}

// ---------------------------------------------------------------------------
//  A contract and the quotation it came from are two views of one agreement
//
//  The contract number is settled in one of two places, and which one depends on
//  who happens to get there first. Accounts register it against the won
//  quotation; or somebody records the contract on the client's Contracts tab
//  because the paperwork arrived that way. Either way both sides have to end up
//  saying the same thing — otherwise the quotation shows "contract number
//  pending" forever while the contract sits on the client, and the expiry and
//  quantity gates read a contract that nothing is pointing at.
//
//  So there is one rule, in one place, and both screens call it.
// ---------------------------------------------------------------------------
function contract_link_quotation($contractId, $quotationId) {
    $contractId = (int)$contractId; $quotationId = (int)$quotationId;
    if (!$contractId || !$quotationId) return false;
    $c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [$contractId]);
    $q = ops_one("SELECT * FROM quotations WHERE id=?", [$quotationId]);
    if (!$c || !$q) return false;
    $pdo = db();
    // The contract points at the quotation it came from…
    $pdo->prepare("UPDATE partner_contracts SET quotation_id=? WHERE id=?")->execute([$quotationId, $contractId]);
    // …and the quotation carries the number and the contract it produced, which
    // is what every downstream screen actually reads.
    $pdo->prepare("UPDATE quotations SET contract_number=?, contract_id=?, client_id=COALESCE(client_id, ?) WHERE id=?")
        ->execute([(string)$c['contract_number'], $contractId, (int)$c['partner_id'], $quotationId]);
    // A revision chain shares one order, so the whole chain shares the number.
    $base = (int)($q['parent_id'] ?: $q['id']);
    try {
        $pdo->prepare("UPDATE quotations SET contract_number=?, contract_id=? WHERE (id=? OR parent_id=?) AND (contract_number='' OR contract_number IS NULL)")
            ->execute([(string)$c['contract_number'], $contractId, $base, $base]);
    } catch (Throwable $e) {}
    return true;
}
// The quotations this client could still have a contract raised against: current,
// not lost, and with no contract number yet. This is the list the Contracts tab
// offers, so a contract recorded there can say which order it belongs to.
// An order remembers the quotation it answers, so a revised quotation can be
// pulled through again rather than re-keyed. Added here because contracts.php
// is where the quote/contract/order relationship already lives.
function po_migrate() {
    static $done = false; if ($done) return; $done = true;
    ensure_column('partner_purchase_orders', 'quotation_id', 'INT NULL');
    ensure_column('partner_purchase_orders', 'lines_synced_at', "VARCHAR(30) DEFAULT ''");
}

// Copy every line of a quotation onto an order. Replaces what is there, because
// a revision is the same order re-priced — but refuses when any line has
// already been consumed, since that consumption was measured against the old
// quantities and silently moving them would make the balance a lie.
function po_pull_quote_lines($poId, $quoteId) {
    $poId = (int)$poId; $quoteId = (int)$quoteId;
    if (!$poId || !$quoteId) return ['ok' => false, 'error' => 'Nothing to copy from.'];
    $used = (float)ops_val("SELECT COALESCE(SUM(consumed),0) FROM po_line_items WHERE purchase_order_id=?", [$poId]);
    if ($used > 0) return ['ok' => false, 'error' =>
        'Some of this order has already been used (' . rtrim(rtrim(number_format($used, 2, '.', ''), '0'), '.')
        . ' unit(s) consumed). Replacing the lines now would leave the balances describing quantities that no '
        . 'longer exist. Add or correct the lines by hand instead.'];
    $lines = ops_all("SELECT * FROM quote_lines WHERE quote_id=? ORDER BY line_no, id", [$quoteId]);
    if (!$lines) return ['ok' => false, 'error' => 'That quotation has no line items to copy.'];
    db()->prepare("DELETE FROM po_line_items WHERE purchase_order_id=?")->execute([$poId]);
    $ins = db()->prepare("INSERT INTO po_line_items (purchase_order_id,description,item_type,quantity,rate,consumed) VALUES (?,?,?,?,?,0)");
    foreach ($lines as $l) {
        $desc = trim((string)($l['description'] ?? '')) ?: (trim((string)($l['service_type'] ?? '')) ?: 'Line ' . (int)$l['line_no']);
        if (trim((string)($l['location'] ?? '')) !== '') $desc .= ' — ' . $l['location'];
        $ins->execute([$poId, mb_substr($desc, 0, 200), (string)($l['unit'] ?? 'MANDAY'),
                       (float)($l['qty'] ?? 0), ($l['rate'] === null || $l['rate'] === '') ? null : (float)$l['rate']]);
    }
    db()->prepare("UPDATE partner_purchase_orders SET quotation_id=?, lines_synced_at=? WHERE id=?")
        ->execute([$quoteId, date('c'), $poId]);
    return ['ok' => true, 'count' => count($lines)];
}

// The quotation an order came from, and whether it has moved on since.
function po_quote_status($po) {
    $qid = (int)($po['quotation_id'] ?? 0);
    if (!$qid) return null;
    $q = ops_one("SELECT id, quote_no, rev, is_current, status FROM quotations WHERE id=?", [$qid]);
    if (!$q) return null;
    // The revision that is current now, which may not be the one this came from.
    $cur = ops_one("SELECT id, quote_no, rev FROM quotations WHERE quote_no=? AND is_current=1", [$q['quote_no']]);
    return ['from' => $q, 'current' => $cur,
            'stale' => $cur && (int)$cur['id'] !== $qid,
            'synced' => (string)($po['lines_synced_at'] ?? '')];
}

// Every current quotation for this client, for the purchase-order form. Unlike
// the contract list this one does NOT hide the quotations that already carry a
// contract number — a PO usually arrives against exactly those, and hiding them
// is what forced people to retype the lines by hand.
function quotations_for_po($clientId) {
    $clientId = (int)$clientId;
    if (!$clientId) return [];
    $rows = ops_all("SELECT q.id, q.quote_no, q.rev, q.status, q.subject, q.total_amount, q.sbu,
                            q.contract_id, q.contract_number
                     FROM quotations q
                     WHERE q.client_id=? AND q.is_current=1 AND q.status NOT IN ('LOST','REJECTED')
                     ORDER BY (q.status='ACCEPTED') DESC, q.id DESC", [$clientId]);
    foreach ($rows as &$r)
        $r['line_count'] = (int)ops_val("SELECT COUNT(*) FROM quote_lines WHERE quote_id=?", [$r['id']]);
    unset($r);
    return $rows;
}

function quotations_awaiting_contract($clientId) {
    $clientId = (int)$clientId;
    if (!$clientId) return [];
    return ops_all("SELECT id, quote_no, rev, status, subject, total_amount, created_at
                    FROM quotations
                    WHERE client_id=? AND is_current=1
                      AND (contract_number='' OR contract_number IS NULL)
                      AND status NOT IN ('LOST','REJECTED')
                    ORDER BY (status='ACCEPTED') DESC, id DESC", [$clientId]);
}

// ---------------------------------------------------------------------------
//  Overrides
// ---------------------------------------------------------------------------
// A granted, unused, still-valid override for this quotation and reason.
function override_live($quoteId, $kind) {
    $rows = ops_all("SELECT * FROM contract_overrides
                     WHERE quotation_id=? AND kind=? AND status='APPROVED'
                     ORDER BY id DESC", [(int)$quoteId, $kind]);
    $today = date('Y-m-d');
    foreach ($rows as $r) {
        if (($r['valid_until'] ?? '') !== '' && $r['valid_until'] < $today) continue;
        if ((int)$r['uses_allowed'] > 0 && (int)$r['uses_taken'] >= (int)$r['uses_allowed']) continue;
        return $r;
    }
    return null;
}
function override_open($quoteId, $kind) {
    return ops_one("SELECT * FROM contract_overrides
                    WHERE quotation_id=? AND kind=? AND status IN ('PENDING','ENDORSED')
                    ORDER BY id DESC", [(int)$quoteId, $kind]);
}
// The single question a scheduling screen needs answered.
//   ['allowed'=>bool, 'state'=>..., 'reason'=>text, 'override'=>row|null, 'pending'=>row|null]
function contract_gate($quoteId) {
    $st = contract_state($quoteId);
    $res = ['allowed' => true, 'state' => $st['state'], 'info' => $st, 'override' => null, 'pending' => null, 'reason' => ''];
    if (!contract_state_blocks($st['state'])) return $res;
    $kind = $st['state'] === 'EXPIRED' ? 'EXPIRED' : 'EXHAUSTED';
    $live = override_live($quoteId, $kind);
    if ($live) { $res['override'] = $live; $res['reason'] = 'Allowed by a granted exception.'; return $res; }
    $res['allowed'] = false;
    $res['pending'] = override_open($quoteId, $kind);
    $res['reason'] = $st['state'] === 'EXPIRED'
        ? 'The contract behind this order expired on ' . fdate($st['end_date'], 'an earlier date') . '.'
        : 'The quantity sold on this order has been used up (' . rtrim(rtrim(number_format((float)$st['qty_used'], 2), '0'), '.')
          . ' of ' . rtrim(rtrim(number_format((float)$st['qty_total'], 2), '0'), '.') . ').';
    return $res;
}
// Count an override against its allowance once work is actually booked.
function override_consume($row) {
    if (!$row) return;
    db()->prepare("UPDATE contract_overrides SET uses_taken=uses_taken+1 WHERE id=?")->execute([(int)$row['id']]);
}

// Who should be told about this quotation's contract? Owner, their reporting
// manager, and the head of the owning office.
function contract_notify_emails($quoteId) {
    $q = ops_one("SELECT owner_id, office_id FROM quotations WHERE id=?", [(int)$quoteId]);
    if (!$q) return [];
    $ids = [];
    if (!empty($q['owner_id'])) {
        $ids[] = (int)$q['owner_id'];
        $mgr = ops_val("SELECT reports_to_id FROM users WHERE id=?", [(int)$q['owner_id']]);
        if ($mgr) $ids[] = (int)$mgr;
    }
    if (!empty($q['office_id'])) {
        $head = ops_val("SELECT head_user_id FROM offices WHERE id=?", [(int)$q['office_id']]);
        if ($head) $ids[] = (int)$head;
    }
    $out = [];
    foreach (array_unique(array_filter($ids)) as $id) {
        $em = trim((string)ops_val("SELECT email FROM users WHERE id=? AND is_active=1", [$id]));
        if ($em !== '') $out[] = $em;
    }
    return array_values(array_unique($out));
}

// ---------------------------------------------------------------------------
//  Daily reminder — contracts running out of time
//
//  Sent once per contract per warning window, so a month of daily mail does
//  not train everyone to ignore it. Re-arms if the end date is extended.
// ---------------------------------------------------------------------------
function contracts_expiry_reminders($today = null) {
    $today = $today ?: date('Y-m-d');
    $horizon = date('Y-m-d', strtotime($today . ' +' . contract_warn_days() . ' days'));
    $rows = ops_all("SELECT c.*, q.id qid, q.quote_no, q.rev, q.client_name
                     FROM partner_contracts c
                     LEFT JOIN quotations q ON q.contract_id = c.id AND q.is_current=1
                     WHERE c.is_active=1 AND c.end_date<>'' AND c.end_date<=? AND c.end_date>=?",
                     [$horizon, $today]);
    $sent = 0;
    foreach ($rows as $c) {
        if (($c['expiry_notified'] ?? '') === $c['end_date']) continue;   // already warned for this end date
        $left = days_between($today, $c['end_date']);
        // contract_notify_emails() returns a list; manager_emails() returns a
        // ready-made comma-separated string. Normalise to one string here rather
        // than assuming either shape.
        $list = $c['qid'] ? contract_notify_emails((int)$c['qid']) : [];
        $to = $list ? implode(',', $list) : (string)manager_emails();
        if ($to !== '') {
            $subj = 'Contract expiring in ' . (int)$left . ' day(s): ' . $c['contract_number'];
            $body = "Contract " . $c['contract_number'] . ($c['title'] ? ' — ' . $c['title'] : '')
                . "\nClient: " . ($c['client_name'] ?: '—')
                . "\nEnds: " . fdate($c['end_date'])
                . "\n\nOnce it expires, no further inspection can be scheduled against it unless the Super Admin\n"
                . "grants an exception. Renew it, or extend the end date, before then.\n\n" . app_name();
            ops_mail($to, $subj, $body, '', 'contract_expiry');
            $sent++;
        }
        db()->prepare("UPDATE partner_contracts SET expiry_notified=? WHERE id=?")->execute([$c['end_date'], (int)$c['id']]);
    }
    return $sent;
}

// ---------------------------------------------------------------------------
//  Handler: request an exception, endorse it, grant it
//
//  Two people on purpose. Over-serving an order or working past its end date
//  is a commercial decision, so the Branch Manager endorses that it is genuinely
//  needed and the Super Admin decides whether the company will carry it.
// ---------------------------------------------------------------------------
function can_endorse_override() {
    return is_master() || in_array(current_user()['role'] ?? '', ['BRANCH_MANAGER','SBU_HEAD','BUSINESS_DIRECTOR'], true)
        || can('users.manage.branch');
}
function can_grant_override() { return is_master(); }

// ===========================================================================
//  Contract number — automatic generation, opening approval, idle auto-close
// ===========================================================================

// A structured, unique contract number: BRANCH / C / FY / 00001, e.g.
// AHM/C/25-26/00042. The branch and financial year make it readable and keep
// it unique across the company; the running number is the next free one.
function gen_contract_number($branchId = null) {
    $code = 'GEN';
    if ($branchId) {
        $o = ops_one("SELECT code, name FROM offices WHERE id=?", [(int)$branchId]);
        if ($o && trim((string)$o['code']) !== '') $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $o['code']));
    }
    $fy = function_exists('fy_label') && function_exists('current_fy') ? fy_label(current_fy()) : date('y');
    $prefix = $code . '/C/' . $fy . '/';
    for ($n = 1; $n < 100000; $n++) {
        $no = $prefix . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
        if (!ops_one("SELECT id FROM partner_contracts WHERE contract_number=?", [$no])) return $no;
    }
    return $prefix . date('YmdHis');
}

const CONTRACT_OPEN_STATES = [
    'PENDING'  => 'Pending approval',
    'OPEN'     => 'Open / in force',
    'REJECTED' => 'Opening rejected',
    'CLOSED'   => 'Closed',
];

// The two signatures that open a contract number. A manager (operations / SBU
// head / admin) endorses that it is genuinely needed; the branch manager
// approves that the branch will carry it. The same person cannot do both,
// unless they are the Master Admin standing in for a one-person branch.
function can_endorse_contract_open() {
    return is_master()
        || in_array(current_user()['role'] ?? '', ['OPERATION_MANAGER','SBU_HEAD','ADMIN','BUSINESS_DIRECTOR'], true)
        || can('users.manage.branch');
}
function can_approve_contract_open() {
    return is_master()
        || in_array(current_user()['role'] ?? '', ['BRANCH_MANAGER','BRANCH_APP_MANAGER'], true);
}

// Latest date anything happened against a contract: the quotation itself, any
// call or job raised under its number, or an invoice against it. Used to decide
// when a contract has gone quiet.
function contract_last_activity_date($c) {
    $dates = [];
    $push = function ($d) use (&$dates) { $d = trim((string)$d); if ($d !== '') $dates[] = substr($d, 0, 10); };
    $push($c['opened_at'] ?: ($c['start_date'] ?: $c['created_at'] ?? ''));
    $no = (string)$c['contract_number']; $cid = (int)$c['id'];
    try { $push(ops_val("SELECT MAX(updated_at) FROM quotations WHERE contract_id=? OR (contract_number<>'' AND contract_number=?)", [$cid, $no])); } catch (Throwable $e) {}
    try { $push(ops_val("SELECT MAX(created_at) FROM calls WHERE contract_number<>'' AND contract_number=?", [$no])); } catch (Throwable $e) {}
    try { $push(ops_val("SELECT MAX(created_at) FROM jobs  WHERE contract_number<>'' AND contract_number=?", [$no])); } catch (Throwable $e) {}
    try { $push(ops_val("SELECT MAX(inspection_required_date) FROM calls WHERE contract_number<>'' AND contract_number=?", [$no])); } catch (Throwable $e) {}
    if (!$dates) return '';
    sort($dates);
    return end($dates);
}

// Pending money / work still hanging off a contract, so an auto-close cannot
// quietly bury an unbilled or unpaid job. Returns a short human summary, or ''.
function contract_pending_summary($c) {
    $no = (string)$c['contract_number']; if ($no === '') return ''; $bits = [];
    try {
        $openCalls = (int)ops_val("SELECT COUNT(*) FROM calls WHERE contract_number=? AND COALESCE(status,'') NOT IN ('CLOSED','CANCELLED','COMPLETED')", [$no]);
        if ($openCalls) $bits[] = $openCalls . ' open ' . Tlp('call');
    } catch (Throwable $e) {}
    try {
        $openJobs = (int)ops_val("SELECT COUNT(*) FROM jobs WHERE contract_number=? AND COALESCE(closed_flag,0)=0", [$no]);
        if ($openJobs) $bits[] = $openJobs . ' open job(s)';
    } catch (Throwable $e) {}
    try {
        $inv = (int)ops_val("SELECT COUNT(*) FROM invoices WHERE contract_number=? AND COALESCE(status,'') <> 'CANCELLED'", [$no]);
        if ($inv) $bits[] = $inv . ' invoice(s) raised';
    } catch (Throwable $e) {}
    return $bits ? implode(', ', $bits) : '';
}

// ---------------------------------------------------------------------------
//  Daily sweep — a contract with no activity for two months is closed
//
//  A file that has gone quiet for two months is finished in practice; leaving
//  it "open" hides real exposure. It is closed automatically and, if it still
//  carries pending invoices or work, that is flagged to the people responsible
//  — the owner, the branch manager and back-office — rather than closed silently.
// ---------------------------------------------------------------------------
function contract_idle_days() { $d = (int)setting_get('contract_idle_close_days', 60); return $d > 0 ? $d : 60; }
// How many days before the idle-close a heads-up goes out (and the on-screen
// warning appears), so a close is never the first anyone hears of it.
function contract_idle_warn_days() { $d = (int)setting_get('contract_idle_warn_days', 14); return $d > 0 ? $d : 14; }

// How close an OPEN contract is to being auto-closed for inactivity — the SAME
// rule the cron enforces, exposed so it can be SHOWN before it acts. Returns
// ['due'=>bool, 'days_left'=>int, 'close_on'=>date, 'last'=>date, 'pending'=>str].
// 'due' is true once inside the warning window (days_left may be <=0 = overdue).
function contract_idle_status($c) {
    $os = strtoupper((string)($c['open_status'] ?? 'OPEN'));
    if ($os !== 'OPEN' || (int)($c['is_active'] ?? 1) !== 1) return ['due' => false];
    $last = contract_last_activity_date($c);
    if ($last === '') return ['due' => false];          // unknown → the cron leaves it too
    $closeOn  = date('Y-m-d', strtotime($last . ' +' . contract_idle_days() . ' days'));
    $daysLeft = (int)floor((strtotime($closeOn) - strtotime(date('Y-m-d'))) / 86400);
    return [
        'due'       => $daysLeft <= contract_idle_warn_days(),
        'days_left' => $daysLeft,
        'close_on'  => $closeOn,
        'last'      => $last,
        'pending'   => contract_pending_summary($c),
    ];
}

// A heads-up BEFORE the idle auto-close, so a contract is never closed out from
// under operations unannounced. One email per contract per warning window; it
// re-arms automatically if activity resumes and pushes the close date out (the
// flag is keyed on the activity date it was based on).
function contracts_idle_warn($today = null) {
    $today = $today ?: date('Y-m-d');
    $rows = ops_all("SELECT c.*, q.id qid, q.quote_no, q.client_name
                     FROM partner_contracts c
                     LEFT JOIN quotations q ON q.contract_id = c.id AND q.is_current=1
                     WHERE COALESCE(c.open_status,'OPEN')='OPEN' AND COALESCE(c.is_active,1)=1");
    $warned = 0;
    foreach ($rows as $c) {
        $st = contract_idle_status($c);
        if (empty($st['due']) || $st['days_left'] < 0) continue;    // not in window, or already due to close
        if ((string)($c['idle_notified'] ?? '') === (string)$st['last']) continue;   // already warned for this window
        $to = contract_responsible_emails($c);
        if ($to !== '') {
            $subj = 'Contract going idle — auto-closes ' . fdate($st['close_on']) . ': ' . $c['contract_number'];
            $body = "Contract " . $c['contract_number'] . ($c['title'] ? ' — ' . $c['title'] : '')
                . "\nClient: " . ($c['client_name'] ?: '—')
                . "\n\nNo activity since " . fdate($st['last']) . ". With no further " . Tlp('call') . " or "
                . Tlp('job') . " raised against it, it will auto-close on " . fdate($st['close_on'])
                . " (" . $st['days_left'] . " day(s) away)."
                . ($st['pending'] !== '' ? "\nStill pending: " . $st['pending'] . "." : "")
                . "\n\nTo keep it open, raise work against it. To let it close, no action is needed.\n\n" . app_name();
            ops_mail($to, $subj, $body, '', 'contract_idle_warn');
        }
        db()->prepare("UPDATE partner_contracts SET idle_notified=? WHERE id=?")->execute([(string)$st['last'], (int)$c['id']]);
        $warned++;
    }
    return $warned;
}

function contracts_idle_autoclose($today = null) {
    $today = $today ?: date('Y-m-d');
    $cut = date('Y-m-d', strtotime($today . ' -' . contract_idle_days() . ' days'));
    $rows = ops_all("SELECT c.*, q.id qid, q.quote_no, q.client_name
                     FROM partner_contracts c
                     LEFT JOIN quotations q ON q.contract_id = c.id AND q.is_current=1
                     WHERE COALESCE(c.open_status,'OPEN')='OPEN' AND COALESCE(c.is_active,1)=1");
    $closed = 0;
    foreach ($rows as $c) {
        $last = contract_last_activity_date($c);
        if ($last === '' || $last > $cut) continue;          // still active, or unknown → leave it
        $pending = contract_pending_summary($c);
        $reason = 'No activity since ' . fdate($last) . ' (' . contract_idle_days() . '+ days idle).'
                . ($pending !== '' ? ' Pending at close: ' . $pending . '.' : '');
        db()->prepare("UPDATE partner_contracts
                       SET open_status='CLOSED', is_active=0, auto_closed=1, closed_at=?, close_reason=?, last_activity_at=?
                       WHERE id=?")
           ->execute([date('c'), $reason, $last, (int)$c['id']]);
        if (function_exists('crm_log_change') && $c['qid']) {
            crm_log_change((int)$c['qid'], 'Contract ' . $c['contract_number'] . ' auto-closed — ' . $reason);
        }
        // Highlight to the responsible people only when something is still pending.
        if ($pending !== '') {
            $to = contract_responsible_emails($c);
            if ($to !== '') {
                $subj = 'Contract auto-closed with items still pending: ' . $c['contract_number'];
                $body = "Contract " . $c['contract_number'] . ($c['title'] ? ' — ' . $c['title'] : '')
                    . "\nClient: " . ($c['client_name'] ?: '—')
                    . "\n\n" . $reason
                    . "\n\nIt has been closed automatically after being idle. The pending items above still need to be\n"
                    . "settled or formally written off. Re-open the contract from the quotation if work continues.\n\n" . app_name();
                ops_mail($to, $subj, $body, '', 'contract_idle_close');
            }
        }
        db()->prepare("UPDATE partner_contracts SET idle_notified=? WHERE id=?")->execute([$today, (int)$c['id']]);
        $closed++;
    }
    return $closed;
}

// Owner + branch manager + accounts/back-office, deduplicated.
function contract_responsible_emails($c) {
    $emails = [];
    if (!empty($c['qid'])) foreach (contract_notify_emails((int)$c['qid']) as $e) $emails[] = $e;
    $mgr = (string)manager_emails();
    foreach (preg_split('/[,;]+/', $mgr) as $e) { $e = trim($e); if ($e !== '') $emails[] = $e; }
    try {
        $acc = ops_all("SELECT email FROM users WHERE role IN ('ACCOUNTANT','ACCOUNTS','ADMIN','MASTER_ADMIN','BRANCH_MANAGER') AND COALESCE(email,'')<>'' AND is_active=1");
        foreach ($acc as $r) $emails[] = $r['email'];
    } catch (Throwable $e) {}
    return implode(',', array_values(array_unique(array_filter($emails))));
}

// Find the current quotation a contract hangs off, for the thread + redirect.
function contract_quote_id($c) {
    $qid = 0;
    if (!empty($c['quotation_id'])) $qid = (int)$c['quotation_id'];
    if (!$qid) $qid = (int)ops_val("SELECT id FROM quotations WHERE contract_id=? AND is_current=1 ORDER BY id DESC LIMIT 1", [(int)$c['id']]);
    return $qid;
}

// ---------------------------------------------------------------------------
//  Handler: open a contract number under a two-signature approval
//
//  Registered by an accountant / coordinator as PENDING, endorsed by a manager,
//  approved by the branch manager — then it is OPEN and the order floats to
//  operations. Every step is written into the quotation thread with who and
//  when, so the full trail lives with the file.
// ---------------------------------------------------------------------------
// The endorse/approve hand-off, on its own screen — so the people who do it
// (Operation Manager / SBU head endorse, Branch Manager approves) reach the
// buttons without needing to open the quotation (which they may not have rights
// to view). Lists every contract still waiting to be opened, with the action
// each viewer can take inline. The write still goes through /contract-open.
function ops_contract_openings() {
    ops_require(can_endorse_contract_open() || can_approve_contract_open() || is_master(),
        'You are not part of the contract-opening approval.');
    $rows = ops_all(
        "SELECT pc.*, q.id quote_id, q.quote_no, q.rev, q.subject quote_subject,
                COALESCE(bp.display_name, bp.legal_name) client_name
         FROM partner_contracts pc
         LEFT JOIN quotations q ON q.id = pc.quotation_id
         LEFT JOIN business_partners bp ON bp.id = pc.partner_id
         WHERE COALESCE(pc.open_status,'') = 'PENDING'
         ORDER BY pc.requested_at, pc.id") ?: [];
    // A quote link is only useful to someone who can actually view quotes.
    $canSeeQuote = can('mod.quotes.view') || is_master();
    // The coordinators the endorsing manager can forward to — his own office's.
    $myOffice = (int)(current_user()['home_office_id'] ?? 0);
    $myCoordinators = $myOffice ? office_coordinators($myOffice) : [];
    view('ops/contract_openings', [
        'rows' => $rows,
        'canEndorse' => can_endorse_contract_open(),
        'canApprove' => can_approve_contract_open(),
        'canSeeQuote' => $canSeeQuote,
        'myCoordinators' => $myCoordinators,
        'myOfficeName' => $myOffice ? (string)ops_val("SELECT name FROM offices WHERE id=?", [$myOffice]) : '',
    ]);
    return true;
}

function ops_contract_open($route, $method) {
    if ($route !== 'contract-open' || $method !== 'POST') return false;
    $pdo = db();
    $c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [(int)($_POST['id'] ?? 0)]);
    if (!$c) { flash('That contract no longer exists.', 'error'); redirect('/quotes'); }
    $qid = contract_quote_id($c);
    $back = $qid ? ('/quote?id=' . $qid) : ('/partner?id=' . (int)$c['partner_id'] . '&tab=contracts');
    $do = $_POST['do'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $me = user_name(current_user());
    $meId = (int)(current_user()['id'] ?? 0);
    $status = $c['open_status'] ?: 'OPEN';

    if ($do === 'endorse') {
        ops_require(can_endorse_contract_open(), 'Only a manager can endorse opening a contract.');
        if ($status !== 'PENDING') { flash('This contract is not awaiting endorsement.', 'warning'); redirect($back); }
        if (trim((string)$c['mgr_endorsed_at']) !== '') { flash('Already endorsed.', 'warning'); redirect($back); }
        // The endorsing manager may nominate the coordinator who will own the
        // calls raised from this contract — an office has several, and naming one
        // here means the work does not later land in a shared inbox. Optional;
        // the coordinator can still be chosen (or changed) when a call is raised.
        $coordId = (int)($_POST['coordinator_id'] ?? 0) ?: null;
        $pdo->prepare("UPDATE partner_contracts SET mgr_endorsed_by=?, mgr_endorsed_by_id=?, mgr_endorsed_at=?, coordinator_id=? WHERE id=?")
            ->execute([$me, $meId, date('c'), $coordId, (int)$c['id']]);
        $coordNote = '';
        if ($coordId) { $cn = call_coordinator_name(['coordinator_id' => $coordId]); if ($cn !== '') $coordNote = ' Forwarded to ' . $cn . ' to coordinate.'; }
        if ($qid) crm_log_change($qid, 'Contract ' . $c['contract_number'] . ' opening endorsed by ' . $me . ($note !== '' ? ' — ' . $note : '') . '.' . $coordNote . ' Awaiting branch-manager approval.');
        flash('Endorsed — it is now with the branch manager for approval.' . $coordNote);
        redirect($back);
    }
    if ($do === 'approve') {
        ops_require(can_approve_contract_open(), 'Only the branch manager can approve opening a contract.');
        if ($status !== 'PENDING') { flash('This contract is not awaiting approval.', 'warning'); redirect($back); }
        if (trim((string)$c['mgr_endorsed_at']) === '' && !is_master()) {
            flash('It needs a manager to endorse it first.', 'error'); redirect($back);
        }
        // Two people on purpose: the approver must not be the endorser (the Master
        // Admin standing in for a one-person branch is the only exception).
        if (!is_master() && (int)$c['mgr_endorsed_by_id'] === $meId && $meId !== 0) {
            flash('The branch-manager approval must come from someone other than the endorser.', 'error'); redirect($back);
        }
        $pdo->prepare("UPDATE partner_contracts SET bm_approved_by=?, bm_approved_by_id=?, bm_approved_at=?, open_status='OPEN', is_active=1, opened_at=? WHERE id=?")
            ->execute([$me, $meId, date('c'), date('c'), (int)$c['id']]);
        if ($qid) crm_log_change($qid, 'Contract ' . $c['contract_number'] . ' OPENED — approved by ' . $me . ($note !== '' ? ' — ' . $note : '') . '. Order floated to operations.');
        // Now that it is open, hand the order to operations.
        if ($qid) { $q = crm_quote_get($qid); if ($q) crm_float_ops_packet($q); }
        flash('Contract ' . $c['contract_number'] . ' opened and floated to operations.');
        redirect($back);
    }
    if ($do === 'reject') {
        ops_require(can_endorse_contract_open() || can_approve_contract_open(), 'You cannot decide this request.');
        if ($status !== 'PENDING') { flash('This contract is not awaiting a decision.', 'warning'); redirect($back); }
        $pdo->prepare("UPDATE partner_contracts SET open_status='REJECTED', close_reason=?, closed_at=? WHERE id=?")
            ->execute(['Opening rejected by ' . $me . ($note !== '' ? ' — ' . $note : ''), date('c'), (int)$c['id']]);
        if ($qid) crm_log_change($qid, 'Contract ' . $c['contract_number'] . ' opening REJECTED by ' . $me . ($note !== '' ? ' — ' . $note : '') . '.');
        flash('Opening rejected.', 'warning');
        redirect($back);
    }
    // Re-open a closed / auto-closed contract → back to the PENDING approval path.
    if ($do === 'reopen') {
        ops_require(can('crm.contract.register') || is_master(), 'You cannot re-open a contract.');
        $pdo->prepare("UPDATE partner_contracts SET open_status='PENDING', is_active=1, auto_closed=0, closed_at='', close_reason='', mgr_endorsed_by='', mgr_endorsed_by_id=NULL, mgr_endorsed_at='', bm_approved_by='', bm_approved_by_id=NULL, bm_approved_at='', requested_by=?, requested_by_id=?, requested_at=? WHERE id=?")
            ->execute([$me, $meId, date('c'), (int)$c['id']]);
        if ($qid) crm_log_change($qid, 'Contract ' . $c['contract_number'] . ' re-opening requested by ' . $me . ($note !== '' ? ' — ' . $note : '') . '. Pending manager & branch-manager approval.');
        flash('Re-opening requested — pending manager & branch-manager approval.');
        redirect($back);
    }
    redirect($back);
}

function ops_contract_overrides($route, $method) {
    $pdo = db();

    if ($route === 'contract-override' && $method === 'POST') {
        $do = $_POST['do'] ?? '';
        $qid = (int)($_POST['quotation_id'] ?? 0);
        $back = $_POST['back'] ?? ('/quote?id=' . $qid);

        if ($do === 'request') {
            $kind = isset(OVERRIDE_KINDS[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'EXPIRED';
            $reason = trim($_POST['reason'] ?? '');
            if ($qid === 0 || $reason === '') { flash('Say why the exception is needed.', 'error'); redirect($back); }
            if (override_open($qid, $kind)) { flash('An exception is already being considered for this order.', 'warning'); redirect($back); }
            $st = contract_state($qid);
            $pdo->prepare("INSERT INTO contract_overrides
                (quotation_id, contract_id, call_id, kind, reason, status, requested_by, requested_by_id, requested_at, uses_allowed, created_at)
                VALUES (?,?,?,?,?, 'PENDING', ?,?,?,?,?)")
                ->execute([$qid, $st['contract_id'], (int)($_POST['call_id'] ?? 0) ?: null, $kind, $reason,
                           user_name(current_user()), current_user()['id'] ?? null, date('c'),
                           max(1, (int)($_POST['uses_allowed'] ?? 1)), date('c')]);
            // Tell the people who have to act on it — and only them. An expired
            // contract is the Super Admin's call, so the branch is not copied in
            // on a decision it cannot take.
            $sql = override_needs_endorsement($kind)
                ? "SELECT email FROM users WHERE is_active=1 AND (is_superuser=1 OR role IN ('BRANCH_MANAGER','SBU_HEAD','BUSINESS_DIRECTOR'))"
                : "SELECT email FROM users WHERE is_active=1 AND is_superuser=1";
            $to = [];
            foreach (ops_all($sql) as $u)
                if (trim((string)$u['email']) !== '') $to[] = $u['email'];
            if ($to) {
                $q = ops_one("SELECT quote_no, rev, client_name FROM quotations WHERE id=?", [$qid]);
                ops_mail(implode(',', array_unique($to)),
                    'Exception requested: ' . (OVERRIDE_KINDS[$kind] ?? $kind) . ' — ' . ($q ? quote_label($q) : ''),
                    user_name(current_user()) . " has asked to schedule work against " . ($q ? quote_label($q) : 'an order')
                    . " for " . ($q['client_name'] ?? '—') . ".\n\nReason given:\n" . $reason
                    . "\n\n" . override_flow_text($kind) . "\n\n" . app_name(),
                    '', 'contract_override');
            }
            flash('Exception requested. ' . override_flow_text($kind));
            redirect($back);
        }

        $row = ops_one("SELECT * FROM contract_overrides WHERE id=?", [(int)($_POST['id'] ?? 0)]);
        if (!$row) { flash('That request no longer exists.', 'error'); redirect('/contract-overrides'); }
        $note = trim($_POST['note'] ?? '');

        if ($do === 'endorse') {
            ops_require(can_endorse_override(), 'Only a Branch Manager or above can endorse an exception.');
            if (!override_needs_endorsement($row['kind'])) {
                flash('An expired contract goes straight to the Super Admin — there is nothing to endorse.', 'warning');
                redirect('/contract-overrides');
            }
            if ($row['status'] !== 'PENDING') { flash('That request has already moved on.', 'warning'); redirect('/contract-overrides'); }
            $pdo->prepare("UPDATE contract_overrides SET status='ENDORSED', endorsed_by=?, endorsed_at=?, endorse_note=? WHERE id=?")
                ->execute([user_name(current_user()), date('c'), $note, $row['id']]);
            flash('Endorsed — it is now with the Super Admin.');
        } elseif ($do === 'grant') {
            ops_require(can_grant_override(), 'Only the Super Admin can grant an exception.');
            // An exhausted order must be endorsed locally first; an expired one
            // comes straight here, because that risk is the Super Admin's alone.
            $ready = override_needs_endorsement($row['kind']) ? ($row['status'] === 'ENDORSED') : in_array($row['status'], ['PENDING','ENDORSED'], true);
            if (!$ready) { flash('It needs a Branch Manager to endorse it first.', 'error'); redirect('/contract-overrides'); }
            $uses = max(1, (int)($_POST['uses_allowed'] ?? $row['uses_allowed']));
            $until = trim($_POST['valid_until'] ?? '');
            $pdo->prepare("UPDATE contract_overrides SET status='APPROVED', decided_by=?, decided_at=?, decide_note=?, uses_allowed=?, valid_until=? WHERE id=?")
                ->execute([user_name(current_user()), date('c'), $note, $uses, $until, $row['id']]);
            flash('Granted for ' . $uses . ' allocation(s)' . ($until !== '' ? ' until ' . fdate($until) : '') . '.');
        } elseif ($do === 'refuse') {
            ops_require(can_endorse_override() || can_grant_override(), 'You cannot decide this request.');
            $pdo->prepare("UPDATE contract_overrides SET status='REFUSED', decided_by=?, decided_at=?, decide_note=? WHERE id=?")
                ->execute([user_name(current_user()), date('c'), $note, $row['id']]);
            flash('Refused.');
        }
        redirect('/contract-overrides');
    }

    // The queue.
    ops_require(can_endorse_override() || can_grant_override() || can('mod.calls.view'),
        'You cannot see contract exceptions.');
    $rows = ops_all("SELECT o.*, q.quote_no, q.rev, q.client_name, q.contract_number
                     FROM contract_overrides o LEFT JOIN quotations q ON q.id=o.quotation_id
                     ORDER BY CASE o.status WHEN 'PENDING' THEN 0 WHEN 'ENDORSED' THEN 1 ELSE 2 END, o.id DESC");
    view('ops/contract_overrides', ['rows' => $rows,
        'canEndorse' => can_endorse_override(), 'canGrant' => can_grant_override()]);
    return true;
}

// ---------------------------------------------------------------------------
//  Contract 360 — one screen that shows EVERYTHING about a contract: its
//  commercial terms and opening trail, the quotation it came from, its purchase
//  orders, every inspection call raised under it, the jobs on those calls, the
//  reports produced, and the money (invoiced / received / outstanding). Reachable
//  from search and the client's Contracts tab; drill in and use Back to step out.
// ---------------------------------------------------------------------------
function ops_contract_360() {
    ops_require(is_master()
        || (function_exists('can') && (can('mod.clients.view') || can('mod.vendors.view') || can('crm.contract.register') || can('data.credit') || can('finance.reconcile')))
        || (function_exists('is_coordinator_level') && is_coordinator_level()),
        'You do not have permission to view contracts.');
    $id = (int)($_GET['id'] ?? 0);
    $c = ops_one("SELECT pc.*, b.legal_name, b.display_name, o.name branch_name
                  FROM partner_contracts pc
                  LEFT JOIN business_partners b ON b.id=pc.partner_id
                  LEFT JOIN offices o ON o.id=pc.branch_id
                  WHERE pc.id=?", [$id]);
    if (!$c) { http_response_code(404); view('notfound'); return true; }
    $cno = (string)($c['contract_number'] ?? '');
    $hasNo = $cno !== '';

    $quote = !empty($c['quotation_id'])
        ? ops_one("SELECT id, quote_no, rev, status, subject, total_amount FROM quotations WHERE id=?", [(int)$c['quotation_id']])
        : null;

    $pos = ops_all("SELECT id, po_number, po_type, title, value, start_date, end_date FROM partner_purchase_orders WHERE contract_id=? ORDER BY id", [$id]) ?: [];

    // Calls raised under this contract number (the reliable join once registered).
    $calls = $hasNo ? (ops_all(
        "SELECT c.id, c.call_code, c.status, c.created_at, c.inspection_type, c.inspection_required_date,
                (SELECT COUNT(*) FROM jobs j WHERE j.call_id=c.id) job_count,
                (SELECT COALESCE(SUM(j.invoice_amount),0) FROM jobs j WHERE j.call_id=c.id AND j.invoice_raised=1) invoiced
         FROM calls c WHERE COALESCE(c.contract_number,'')=? ORDER BY c.id DESC", [$cno]) ?: []) : [];

    $jobs = $hasNo ? (ops_all(
        "SELECT j.id, j.job_code, j.stage, j.closed_flag, j.invoice_raised, j.invoice_amount, j.payment_received, j.payment_amount,
                i.name inspector_name, cl.call_code,
                (SELECT COUNT(*) FROM report_docs rd WHERE rd.job_id=j.id AND rd.deleted=0) report_count
         FROM jobs j JOIN calls cl ON cl.id=j.call_id LEFT JOIN inspectors i ON i.id=j.inspector_id
         WHERE COALESCE(cl.contract_number,'')=? ORDER BY j.id DESC", [$cno]) ?: []) : [];

    $reports = $hasNo ? (ops_all(
        "SELECT rd.id, rd.irn, rd.type_code, rd.status, rd.finalized, rd.job_id, j.job_code
         FROM report_docs rd JOIN jobs j ON j.id=rd.job_id JOIN calls cl ON cl.id=j.call_id
         WHERE COALESCE(cl.contract_number,'')=? AND rd.deleted=0 ORDER BY rd.id DESC", [$cno]) ?: []) : [];

    // Commercial rollup across the jobs.
    $invoiced = 0.0; $received = 0.0;
    foreach ($jobs as $j) {
        if (!empty($j['invoice_raised'])) $invoiced += (float)$j['invoice_amount'];
        if (!empty($j['payment_received'])) $received += (float)$j['payment_amount'];
    }
    $value = (float)($c['value'] ?? 0);
    $money = ['value' => $value, 'invoiced' => $invoiced, 'received' => $received,
              'outstanding' => max(0, $invoiced - $received),
              // Module 18 — the one figure that says a contract is under- or over-billed.
              'remaining' => round($value - $invoiced, 2)];

    view('ops/contract_detail', ['c' => $c, 'quote' => $quote, 'pos' => $pos, 'calls' => $calls,
        'jobs' => $jobs, 'reports' => $reports, 'money' => $money,
        // Field #3 — the contract's quantity line items, and whether this user may edit/delete it.
        'lines' => contract_lines($id),
        'canEditContract' => can('crm.contract.register') || is_master(),
        // Module 18 — the live expiry/quantity verdict the scheduling gate already uses,
        // now shown where the contract is actually read.
        'state' => contract_state_row($c),
        'canSeeMoney' => is_master() || (function_exists('can') && (can('data.credit') || can('data.revenue') || can('finance.reconcile')))]);
    return true;
}

// ---- Field #3 — edit / delete a contract -----------------------------------
// Edit a contract's header and its quantity line items. The contract NUMBER is
// never changed here — it identifies the contract and every call/job join reads
// it, so renaming would orphan the history. Accounts / back-office only.
function ops_contract_edit($method) {
    ops_require(can('crm.contract.register') || is_master(), 'Only Accounts / back-office can edit a contract.');
    if ($method !== 'POST') redirect('/');
    $id = (int)($_POST['id'] ?? 0);
    $c  = $id ? ops_one("SELECT * FROM partner_contracts WHERE id=?", [$id]) : null;
    if (!$c) { flash('That contract no longer exists.', 'error'); redirect('/'); }
    $b = $_POST;
    $value = ($b['value'] ?? '') !== '' ? (float)$b['value'] : null;
    db()->prepare("UPDATE partner_contracts SET title=?, sbu=?, value=?, start_date=?, end_date=?, notes=? WHERE id=?")
        ->execute([substr(trim((string)($b['title'] ?? '')), 0, 200), (string)($b['sbu'] ?? ''), $value,
                   (string)($b['start_date'] ?? ''), (string)($b['end_date'] ?? ''),
                   substr(trim((string)($b['notes'] ?? '')), 0, 255), $id]);
    // Quantity line items — replace with what was submitted; if none but a single
    // quantity was typed, keep that as the untracked total (backward compatible).
    $kept = contract_replace_lines($id, contract_lines_from_post($b));
    if ($kept === 0 && ($b['qty_total'] ?? '') !== '')
        db()->prepare("UPDATE partner_contracts SET qty_total=? WHERE id=?")->execute([(float)$b['qty_total'], $id]);
    if (function_exists('act_log')) act_log('PARTNER', (int)$c['partner_id'], 'SYSTEM',
        'Contract ' . ($c['contract_number'] ?? '') . ' edited');
    flash('Contract updated.');
    redirect('/contract?id=' . $id);
}

// Delete a contract — only while it is safe to remove: no calls/jobs raised under
// its number and no purchase orders linked (those make it part of the record; mark
// it closed instead). Detaches any quotation that named it, then removes it and its
// line items. Accounts / back-office only.
function ops_contract_delete($method) {
    ops_require(can('crm.contract.register') || is_master(), 'Only Accounts / back-office can delete a contract.');
    if ($method !== 'POST') redirect('/');
    $id = (int)($_POST['id'] ?? 0);
    $c  = $id ? ops_one("SELECT * FROM partner_contracts WHERE id=?", [$id]) : null;
    if (!$c) { flash('That contract no longer exists.', 'error'); redirect('/'); }
    $pid = (int)$c['partner_id'];
    $cno = (string)($c['contract_number'] ?? '');
    if ($cno !== '') {
        $callN = (int) ops_val("SELECT COUNT(*) FROM calls WHERE COALESCE(contract_number,'')=?", [$cno]);
        if ($callN) {
            flash('This contract has ' . $callN . ' call(s)/job(s) under it, so it is part of the record and cannot be deleted. Close it instead.', 'error');
            redirect('/contract?id=' . $id);
        }
    }
    $poN = (int) ops_val("SELECT COUNT(*) FROM partner_purchase_orders WHERE contract_id=?", [$id]);
    if ($poN) {
        flash('This contract has ' . $poN . ' purchase order(s) linked, so it cannot be deleted. Unlink them first.', 'error');
        redirect('/contract?id=' . $id);
    }
    db()->prepare("UPDATE quotations SET contract_id=NULL, contract_number='' WHERE contract_id=?")->execute([$id]);
    db()->prepare("DELETE FROM contract_line_items WHERE contract_id=?")->execute([$id]);
    db()->prepare("DELETE FROM partner_contracts WHERE id=?")->execute([$id]);
    if (function_exists('act_log')) act_log('PARTNER', $pid, 'SYSTEM', 'Contract ' . $cno . ' deleted');
    flash('Contract ' . $cno . ' deleted.');
    redirect('/partner?id=' . $pid . '&tab=contracts');
}
