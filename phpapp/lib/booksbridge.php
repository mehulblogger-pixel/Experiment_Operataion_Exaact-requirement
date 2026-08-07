<?php
// ============================================================================
//  MGH Books bridge — the ERP → Books data flow
//
//  The rule that governs everything: Books is OPTIONAL. A customer who has not
//  connected Books loses nothing — the ERP keeps its own invoicing. Connect
//  Books and it becomes the finance system of record: billable events flow ERP →
//  Books, and Books' invoice number / paid status flow back.
//
//  This is the SPINE, built to the same contract the live Ads Pro connector
//  follows (lib/adssync.php):
//    1. An outbox, never a live call — a save queues and returns; the queue is
//       drained by cron, so Books being down never blocks the ERP.
//    2. Idempotent with a loop-breaker — each item carries a hash of its payload;
//       a push identical to the last is dropped before it leaves.
//    3. Bounded retry, visible failure — a failed push is retried a fixed number
//       of times, then stands as an explained failure rather than a silent loss.
//    4. Direction is one-way here (ERP → Books); status flows back separately.
//
//  Gated twice: the Money/Books module must be licensed AND the per-customer
//  "Books connected" switch must be on. Off by default for everyone.
// ============================================================================

const BOOKS_RETRY_MAX = 6;   // ~6 cron runs before a failure stands

function booksbridge_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = db(); $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS books_outbox (
            id $pk, kind VARCHAR(20) DEFAULT '', local_id INT DEFAULT 0,
            payload MEDIUMTEXT, push_hash VARCHAR(64) DEFAULT '',
            status VARCHAR(12) DEFAULT 'PENDING', attempts INT DEFAULT 0,
            ext_ref VARCHAR(80) DEFAULT '', last_error VARCHAR(400) DEFAULT '',
            queued_at VARCHAR(30) DEFAULT '', sent_at VARCHAR(30) DEFAULT '', queued_by VARCHAR(150) DEFAULT '')");
    } catch (Throwable $e) { /* never let a table add break boot */ }
    // Where a record ended up in Books, stamped back so the desk sees it went and
    // nothing is billed twice.
    if (function_exists('ensure_column')) {
        ensure_column('invoices', 'books_ref', "VARCHAR(80) DEFAULT ''");
        ensure_column('business_partners', 'books_ref', "VARCHAR(80) DEFAULT ''");
        // Status flowing back FROM Books: its invoice number / IRN, and the paid
        // vs outstanding truth, so operations sees it without recomputing.
        ensure_column('invoices', 'books_irn', "VARCHAR(80) DEFAULT ''");
        ensure_column('invoices', 'books_status', "VARCHAR(20) DEFAULT ''");
        ensure_column('invoices', 'books_paid', "DECIMAL(16,2) DEFAULT 0");
        ensure_column('invoices', 'books_outstanding', "DECIMAL(16,2) DEFAULT 0");
        ensure_column('invoices', 'books_synced_at', "VARCHAR(30) DEFAULT ''");
    }
    if (function_exists('act_index')) { try { act_index('books_outbox', 'idx_bxo_go', '(status, id)'); } catch (Throwable $e) {} }
}

function booksbridge_try($fn, $fb = null) { try { return $fn(); } catch (Throwable $e) { return $fb; } }

// ---- Gates ---------------------------------------------------------------
// The Money module must be licensed for Books to be connectable at all.
function books_licensed() {
    if (!function_exists('licence_enabled')) return true;
    // 'money' is the invoicing/finance module in PRODUCT_MODULES; fall back to
    // permissive if the key is unknown on an older licence table.
    return licence_enabled('money') || licence_enabled('invoicing') || licence_enabled('books');
}
// The per-customer switch. Off by default — the ERP invoices itself.
function books_connected() {
    return books_licensed() && (string)setting_get('books_connected', '0') === '1';
}
// A dry run marks pushes delivered without contacting Books — for testing the
// wiring, and used by the automated tests. Real delivery needs a URL.
function books_dryrun()  { return (string)setting_get('books_dryrun', '0') === '1'; }
function books_api_url() { return rtrim((string)setting_get('books_api_url', ''), '/'); }
function books_api_token(){ return (string)setting_get('books_api_token', ''); }
function books_configured() { return books_dryrun() || (books_api_url() !== '' && books_api_token() !== ''); }

// ---- The outbox ----------------------------------------------------------
// Queue a record for Books. Idempotent per (kind, local_id): an identical payload
// to the one already delivered is dropped (loop-breaker); a still-pending item is
// updated in place rather than duplicated.
function books_bridge_queue($kind, $localId, array $payload) {
    booksbridge_migrate();
    if (!books_connected()) return 0;               // not connected → the ERP keeps its own record
    $localId = (int)$localId;
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $hash = hash('sha256', $kind . '|' . $localId . '|' . $json);
    // Loop-breaker: the last delivered push for this record was identical → skip.
    $lastDone = booksbridge_try(fn() => ops_one(
        "SELECT push_hash FROM books_outbox WHERE kind=? AND local_id=? AND status='DONE' ORDER BY id DESC LIMIT 1",
        [$kind, $localId]));
    if ($lastDone && hash_equals((string)$lastDone['push_hash'], $hash)) return 0;
    // A pending item for the same record is updated, not duplicated.
    $open = booksbridge_try(fn() => ops_one(
        "SELECT id FROM books_outbox WHERE kind=? AND local_id=? AND status IN ('PENDING','FAILED') ORDER BY id DESC LIMIT 1",
        [$kind, $localId]));
    if ($open) {
        db()->prepare("UPDATE books_outbox SET payload=?, push_hash=?, status='PENDING', last_error='', queued_at=? WHERE id=?")
            ->execute([$json, $hash, date('c'), (int)$open['id']]);
        return (int)$open['id'];
    }
    db()->prepare("INSERT INTO books_outbox (kind, local_id, payload, push_hash, status, queued_at, queued_by)
                   VALUES (?,?,?,?,'PENDING',?,?)")
        ->execute([$kind, $localId, $json, $hash, date('c'), function_exists('user_name') ? user_name(current_user()) : 'system']);
    return (int)db()->lastInsertId();
}

// ---- Mapping: ERP records → the Books import shape -----------------------
// Books' api.php accepts import/set/create_user with a documented JSON format.
// These build the payloads; the exact field names are aligned at wire-up time.
function books_map_party($pid) {
    $p = ops_one("SELECT * FROM business_partners WHERE id=?", [(int)$pid]);
    if (!$p) return null;
    return [
        'type'    => 'customer',
        'ext_id'  => 'ERP-BP-' . (int)$p['id'],
        'name'    => trim((string)($p['display_name'] ?? '')) ?: (string)($p['legal_name'] ?? ''),
        'legal'   => (string)($p['legal_name'] ?? ''),
        'gstin'   => (string)($p['gstin'] ?? ''),
        'state'   => (string)($p['state'] ?? ''),
        'email'   => (string)($p['email'] ?? ''),
        'phone'   => (string)($p['phone'] ?? ''),
    ];
}
function books_map_invoice($invId) {
    $inv = function_exists('books_invoice') ? books_invoice((int)$invId) : ops_one("SELECT * FROM invoices WHERE id=?", [(int)$invId]);
    if (!$inv) return null;
    $lines = booksbridge_try(fn() => ops_all("SELECT * FROM invoice_lines WHERE invoice_id=? ORDER BY id", [(int)$invId]), []) ?: [];
    $mapLine = fn($l) => [
        'description' => (string)($l['description'] ?? ''),
        'hsn_sac'     => (string)($l['hsn_sac'] ?? ''),
        'qty'         => (float)($l['qty'] ?? 0),
        'rate'        => (float)($l['rate'] ?? 0),
        'amount'      => (float)($l['amount'] ?? 0),
    ];
    return [
        'ext_id'      => 'ERP-INV-' . (int)$inv['id'],
        'invoice_no'  => (string)($inv['invoice_no'] ?? ''),
        'date'        => (string)($inv['invoice_date'] ?? ''),
        'party_ext'   => 'ERP-BP-' . (int)($inv['partner_id'] ?? 0),
        'party_name'  => (string)($inv['partner_name'] ?? ''),
        'gstin'       => (string)($inv['gstin'] ?? ''),
        'place_of_supply' => (string)($inv['place_of_supply'] ?? ''),
        'is_igst'     => (int)($inv['is_igst'] ?? 0),
        'subtotal'    => (float)($inv['subtotal'] ?? 0),
        'cgst'        => (float)($inv['cgst'] ?? 0), 'sgst' => (float)($inv['sgst'] ?? 0), 'igst' => (float)($inv['igst'] ?? 0),
        'total'       => (float)($inv['total'] ?? 0),
        'lines'       => array_map($mapLine, $lines),
    ];
}

// ---- Triggers (called from the ERP when connected) -----------------------
// An issued invoice is the billable event that goes to Books. Called from
// books_issue(); a no-op unless connected, so the standalone path is untouched.
function books_bridge_on_invoice_issued($invId) {
    if (!books_connected()) return;
    $party = (int)booksbridge_try(fn() => ops_val("SELECT partner_id FROM invoices WHERE id=?", [(int)$invId]), 0);
    if ($party) { $mp = books_map_party($party); if ($mp) books_bridge_queue('PARTY', $party, $mp); }
    $mi = books_map_invoice($invId);
    if ($mi) books_bridge_queue('INVOICE', (int)$invId, $mi);
}

// ---- Delivery ------------------------------------------------------------
// POST one payload to Books. In a dry run it succeeds without contacting Books.
// Returns [ok(bool), ext_ref(string), error(string)].
function books_bridge_deliver($kind, array $payload) {
    if (books_dryrun()) return [true, 'DRYRUN-' . substr(md5(json_encode($payload)), 0, 8), ''];
    $url = books_api_url();
    if ($url === '' || books_api_token() === '') return [false, '', 'Books connection is not configured'];
    $action = $kind === 'INVOICE' ? 'import' : 'set';
    $body = json_encode(['action' => $action, 'kind' => $kind, 'token' => books_api_token(), 'data' => $payload]);
    try {
        if (function_exists('curl_init')) {
            $ch = curl_init($url . '/api.php');
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . books_api_token()],
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8]);
            $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
            if ($res === false) return [false, '', 'Network: ' . $err];
            if ($code < 200 || $code >= 300) return [false, '', 'Books returned HTTP ' . $code];
            $j = json_decode((string)$res, true);
            if (is_array($j) && !empty($j['ok'])) return [true, (string)($j['ref'] ?? ''), ''];
            return [false, '', 'Books rejected: ' . substr((string)$res, 0, 200)];
        }
        return [false, '', 'No HTTP client available'];
    } catch (Throwable $e) { return [false, '', $e->getMessage()]; }
}

// Drain the queue: deliver pending and retryable-failed items, bounded. Returns
// counts. Safe to call from cron every run; does nothing unless connected.
function books_bridge_drain($limit = 100) {
    booksbridge_migrate();
    $out = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
    if (!books_connected() || !books_configured()) return $out;
    $rows = booksbridge_try(fn() => ops_all(
        "SELECT * FROM books_outbox WHERE status='PENDING' OR (status='FAILED' AND attempts < " . BOOKS_RETRY_MAX . ")
         ORDER BY id LIMIT " . (int)$limit), []) ?: [];
    foreach ($rows as $r) {
        $payload = json_decode((string)$r['payload'], true);
        if (!is_array($payload)) { db()->prepare("UPDATE books_outbox SET status='FAILED', attempts=attempts+1, last_error='bad payload' WHERE id=?")->execute([(int)$r['id']]); $out['failed']++; continue; }
        [$ok, $ref, $err] = books_bridge_deliver((string)$r['kind'], $payload);
        if ($ok) {
            db()->prepare("UPDATE books_outbox SET status='DONE', attempts=attempts+1, ext_ref=?, last_error='', sent_at=? WHERE id=?")
                ->execute([$ref, date('c'), (int)$r['id']]);
            books_bridge_stamp((string)$r['kind'], (int)$r['local_id'], $ref);
            $out['sent']++;
        } else {
            db()->prepare("UPDATE books_outbox SET status='FAILED', attempts=attempts+1, last_error=? WHERE id=?")
                ->execute([substr($err, 0, 400), (int)$r['id']]);
            $out['failed']++;
        }
    }
    return $out;
}

// Stamp the Books reference back onto the ERP record, so the desk sees it went.
function books_bridge_stamp($kind, $localId, $ref) {
    if ($ref === '') return;
    try {
        if ($kind === 'INVOICE') db()->prepare("UPDATE invoices SET books_ref=? WHERE id=?")->execute([$ref, (int)$localId]);
        elseif ($kind === 'PARTY') db()->prepare("UPDATE business_partners SET books_ref=? WHERE id=?")->execute([$ref, (int)$localId]);
    } catch (Throwable $e) { /* stamp is a convenience */ }
}

// ---- Status back (Books -> ERP) ------------------------------------------
// Books owns the paid/outstanding truth once connected. This writes the status
// Books reports back onto the ERP invoice — the ERP never recomputes it, so the
// two can never disagree. Pure and directly testable.
function books_apply_status($invId, array $st) {
    booksbridge_migrate();
    $invId = (int)$invId;
    if (!$invId || !ops_val("SELECT 1 FROM invoices WHERE id=? LIMIT 1", [$invId])) return false;
    db()->prepare("UPDATE invoices SET books_irn=?, books_status=?, books_paid=?, books_outstanding=?, books_synced_at=? WHERE id=?")
        ->execute([
            substr((string)($st['irn'] ?? ''), 0, 80),
            substr((string)($st['status'] ?? ''), 0, 20),
            (float)($st['paid'] ?? 0),
            (float)($st['outstanding'] ?? 0),
            date('c'), $invId,
        ]);
    return true;
}

// The Books view of one ERP invoice, for the detail screen. Null when the invoice
// has not been sent to Books.
function books_invoice_status($invId) {
    $r = booksbridge_try(fn() => ops_one(
        "SELECT books_ref, books_irn, books_status, books_paid, books_outstanding, books_synced_at
         FROM invoices WHERE id=?", [(int)$invId]));
    if (!$r || trim((string)$r['books_ref']) === '') return null;
    return $r;
}

// Pull the current status for invoices already sent to Books. Dry run and the
// disconnected state are clean no-ops. Live: asks Books for the status of the
// external ids it holds and applies each. Returns a count.
function books_bridge_pull_status($limit = 200) {
    booksbridge_migrate();
    if (!books_connected() || !books_configured() || books_dryrun()) return 0;
    $rows = booksbridge_try(fn() => ops_all(
        "SELECT id FROM invoices WHERE books_ref <> '' ORDER BY id DESC LIMIT " . (int)$limit), []) ?: [];
    $n = 0;
    foreach ($rows as $r) {
        $st = books_fetch_status('ERP-INV-' . (int)$r['id']);
        if (is_array($st) && $st) { books_apply_status((int)$r['id'], $st); $n++; }
    }
    return $n;
}

// Ask Books for the status of one external id. Live only.
function books_fetch_status($extId) {
    $url = books_api_url();
    if ($url === '' || books_api_token() === '' || !function_exists('curl_init')) return null;
    try {
        $ch = curl_init($url . '/api.php?action=status&ext_id=' . rawurlencode($extId));
        curl_setopt_array($ch, [CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . books_api_token()],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8]);
        $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($res === false || $code < 200 || $code >= 300) return null;
        $j = json_decode((string)$res, true);
        return (is_array($j) && !empty($j['ok'])) ? ($j['status'] ?? null) : null;
    } catch (Throwable $e) { return null; }
}

// ---- Admin view helpers --------------------------------------------------
function books_outbox_counts() {
    booksbridge_migrate();
    $out = [];
    foreach (['PENDING', 'DONE', 'FAILED'] as $s)
        $out[$s] = (int)booksbridge_try(fn() => ops_val("SELECT COUNT(*) FROM books_outbox WHERE status=?", [$s]), 0);
    $out['stuck'] = (int)booksbridge_try(fn() => ops_val("SELECT COUNT(*) FROM books_outbox WHERE status='FAILED' AND attempts >= " . BOOKS_RETRY_MAX), 0);
    return $out;
}
function books_outbox_rows($status = 'PENDING', $n = 50) {
    booksbridge_migrate();
    return booksbridge_try(fn() => ops_all("SELECT * FROM books_outbox WHERE status=? ORDER BY id DESC LIMIT " . (int)$n, [$status]), []) ?: [];
}

// ---- Settings screen -----------------------------------------------------
function ops_books_bridge($route, $method) {
    ops_require(can('settings.manage') || is_master(), 'Only an administrator can configure the Books connection.');
    booksbridge_migrate();
    if ($route === 'books-bridge-save' && $method === 'POST') {
        setting_set('books_connected', !empty($_POST['books_connected']) ? '1' : '0');
        setting_set('books_dryrun',    !empty($_POST['books_dryrun']) ? '1' : '0');
        setting_set('books_api_url',   trim((string)($_POST['books_api_url'] ?? '')));
        if (($_POST['books_api_token'] ?? '') !== '') setting_set('books_api_token', trim((string)$_POST['books_api_token']));
        flash('Books connection settings saved.' . (books_connected() ? '' : ' (Connection is off — the ' . Tl('report') . ' app keeps its own invoicing.)'));
        redirect('/books-bridge');
    }
    if ($route === 'books-bridge-drain' && $method === 'POST') {
        $r = books_bridge_drain();
        flash("Books queue drained: {$r['sent']} sent, {$r['failed']} failed.");
        redirect('/books-bridge');
    }
    view('ops/books_bridge', [
        'connected' => books_connected(), 'licensed' => books_licensed(),
        'dryrun' => books_dryrun(), 'url' => books_api_url(), 'hasToken' => books_api_token() !== '',
        'configured' => books_configured(), 'counts' => books_outbox_counts(),
        'pending' => books_outbox_rows('PENDING', 30), 'failed' => books_outbox_rows('FAILED', 30),
    ]);
    return true;
}
