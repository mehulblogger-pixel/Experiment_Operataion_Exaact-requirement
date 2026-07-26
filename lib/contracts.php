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

    // Dates decide first — an expired contract is expired regardless of quantity.
    if ($out['end_date'] !== '') {
        $left = days_between($today, $out['end_date']);
        $out['days_left'] = $left;
        if ($left !== null && $left < 0)  { $out['state'] = 'EXPIRED';  return $out; }
        if ($left !== null && $left <= contract_warn_days()) $out['state'] = 'EXPIRING';
    }
    if ($qtyTotal !== null) {
        if ($out['qty_left'] !== null && $out['qty_left'] <= 0) { $out['state'] = 'EXHAUSTED'; return $out; }
        if ($out['state'] === 'NONE' || $out['state'] === 'OK') {
            $pct = $qtyTotal > 0 ? ($out['qty_left'] / $qtyTotal) : 1;
            if ($pct <= 0.1) $out['state'] = 'QTY_LOW';
        }
    }
    if ($out['state'] === 'NONE' && ($out['end_date'] !== '' || $qtyTotal !== null)) $out['state'] = 'OK';
    return $out;
}
function contract_state_blocks($state) { return in_array($state, ['EXPIRED', 'EXHAUSTED'], true); }

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
