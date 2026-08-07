<?php
// ============================================================================
//  MGH Books — inbound receiver for the ERP connector
//
//  This is the SERVER side of the contract in the ERP's lib/booksbridge.php.
//  Drop it into the MGH Books application; the ERP pushes here and reads status
//  back from here. Zero dependencies, one PDO (SQLite for dev, MySQL for prod),
//  and idempotent by the ERP's external id so nothing is ever created twice.
//
//  Everything is namespaced bkrecv_* so it can live alongside anything without a
//  name clash, and so the ERP's own test harness can call these handlers
//  directly to PROVE the wire format matches — the mapper's output on one side,
//  this receiver's input on the other, checked against the same field names.
//
//  Three verbs, matching what the connector sends:
//    action=set     kind=PARTY                 -> upsert a customer
//    action=import  kind=INVOICE|QUOTE|CREDITNOTE|RECEIPT -> record a document
//    action=status  ext_id=ERP-INV-<n>         -> paid/outstanding/IRN back
//
//  The reply shape the connector expects:
//    set/import : { "ok": true, "ref": "BK-INV-42" }
//    status     : { "ok": true, "status": { irn, status, paid, outstanding } }
//    any failure: { "ok": false, "error": "..." }  with a non-2xx code
// ============================================================================

// ---- Storage --------------------------------------------------------------
// One PDO, injectable so tests can hand in a throwaway database. Production opens
// its own from the environment (Books ships its own config; this mirrors the
// zero-config-file, env-driven style a shared host can satisfy).
function bkrecv_db(?PDO $inject = null) {
    static $pdo = null;
    if ($inject !== null) { $pdo = $inject; bkrecv_migrate(); return $pdo; }
    if ($pdo instanceof PDO) return $pdo;
    $driver = getenv('BOOKS_DB_DRIVER') ?: 'sqlite';
    if ($driver === 'mysql') {
        $host = getenv('BOOKS_DB_HOST') ?: '127.0.0.1';
        $name = getenv('BOOKS_DB_NAME') ?: 'mgh_books';
        $user = getenv('BOOKS_DB_USER') ?: 'root';
        $pass = getenv('BOOKS_DB_PASS') ?: '';
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } else {
        $path = getenv('BOOKS_SQLITE_PATH') ?: (__DIR__ . '/../data/books.sqlite');
        if (!is_dir(dirname($path))) @mkdir(dirname($path), 0770, true);
        $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    bkrecv_migrate();
    return $pdo;
}

function bkrecv_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pdo = bkrecv_db();
    $isSqlite = strpos((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false;
    $pk = $isSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $pdo->exec("CREATE TABLE IF NOT EXISTS bk_parties (
        id $pk, ext_id VARCHAR(80) UNIQUE, type VARCHAR(20) DEFAULT 'customer',
        name VARCHAR(200) DEFAULT '', legal VARCHAR(200) DEFAULT '', gstin VARCHAR(20) DEFAULT '',
        state VARCHAR(60) DEFAULT '', email VARCHAR(200) DEFAULT '', phone VARCHAR(40) DEFAULT '',
        ref VARCHAR(80) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS bk_documents (
        id $pk, ext_id VARCHAR(80) UNIQUE, kind VARCHAR(16) DEFAULT '',
        doc_no VARCHAR(60) DEFAULT '', doc_date VARCHAR(20) DEFAULT '',
        party_ext VARCHAR(80) DEFAULT '', party_name VARCHAR(200) DEFAULT '',
        gstin VARCHAR(20) DEFAULT '', against_ext VARCHAR(80) DEFAULT '',
        total DECIMAL(16,2) DEFAULT 0, tax DECIMAL(16,2) DEFAULT 0,
        irn VARCHAR(80) DEFAULT '', status VARCHAR(20) DEFAULT '',
        paid DECIMAL(16,2) DEFAULT 0, outstanding DECIMAL(16,2) DEFAULT 0,
        has_pdf INT DEFAULT 0, payload MEDIUMTEXT,
        ref VARCHAR(80) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
}

function bkrecv_one($sql, $args = []) { $s = bkrecv_db()->prepare($sql); $s->execute($args); $r = $s->fetch(PDO::FETCH_ASSOC); return $r ?: null; }
function bkrecv_all($sql, $args = []) { $s = bkrecv_db()->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_ASSOC) ?: []; }

// ---- Auth -----------------------------------------------------------------
// The shared token, configured on both sides. hash_equals so the compare does
// not leak length by timing. An unset server token refuses everything rather
// than accidentally running open.
function bkrecv_auth($token) {
    $expected = (string)(getenv('BOOKS_API_TOKEN') ?: '');
    if ($expected === '') return false;
    return is_string($token) && $token !== '' && hash_equals($expected, $token);
}

// ---- set: PARTY -----------------------------------------------------------
// Upsert a customer by the ERP external id. Returns the Books reference.
function bkrecv_set_party(array $d) {
    $ext = trim((string)($d['ext_id'] ?? ''));
    if ($ext === '') return ['ok' => false, 'error' => 'party has no ext_id'];
    $now = date('c');
    $existing = bkrecv_one("SELECT id, ref FROM bk_parties WHERE ext_id=?", [$ext]);
    if ($existing) {
        bkrecv_db()->prepare("UPDATE bk_parties SET type=?, name=?, legal=?, gstin=?, state=?, email=?, phone=?, updated_at=? WHERE id=?")
            ->execute([(string)($d['type'] ?? 'customer'), (string)($d['name'] ?? ''), (string)($d['legal'] ?? ''),
                (string)($d['gstin'] ?? ''), (string)($d['state'] ?? ''), (string)($d['email'] ?? ''),
                (string)($d['phone'] ?? ''), $now, (int)$existing['id']]);
        return ['ok' => true, 'ref' => (string)$existing['ref']];
    }
    bkrecv_db()->prepare("INSERT INTO bk_parties (ext_id, type, name, legal, gstin, state, email, phone, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$ext, (string)($d['type'] ?? 'customer'), (string)($d['name'] ?? ''), (string)($d['legal'] ?? ''),
            (string)($d['gstin'] ?? ''), (string)($d['state'] ?? ''), (string)($d['email'] ?? ''), (string)($d['phone'] ?? ''), $now, $now]);
    $id = (int)bkrecv_db()->lastInsertId();
    $ref = 'BK-CUST-' . $id;
    bkrecv_db()->prepare("UPDATE bk_parties SET ref=? WHERE id=?")->execute([$ref, $id]);
    return ['ok' => true, 'ref' => $ref];
}

// ---- import: documents ----------------------------------------------------
// Create-or-update a document by the ERP external id (idempotent). Routes by kind
// and keeps the money truth — an invoice's paid/outstanding, moved by receipts and
// credit notes — so the status the ERP reads back is Books' own, not a guess.
function bkrecv_import($kind, array $d) {
    $kind = strtoupper((string)$kind);
    $ext  = trim((string)($d['ext_id'] ?? ''));
    if ($ext === '') return ['ok' => false, 'error' => 'document has no ext_id'];
    if (!in_array($kind, ['INVOICE', 'QUOTE', 'CREDITNOTE', 'RECEIPT'], true))
        return ['ok' => false, 'error' => "unknown kind $kind"];

    // Idempotent: a document already imported returns its existing reference.
    $existing = bkrecv_one("SELECT id, ref FROM bk_documents WHERE ext_id=?", [$ext]);

    if ($kind === 'INVOICE') {
        $total = round((float)($d['total'] ?? 0), 2);
        $tax   = round((float)($d['cgst'] ?? 0) + (float)($d['sgst'] ?? 0) + (float)($d['igst'] ?? 0), 2);
        $irn   = 'IRN-' . strtoupper(substr(sha1($ext . '|' . (string)($d['invoice_no'] ?? '')), 0, 12));
        if ($existing) {
            // Re-import keeps the payment history; only the header is refreshed.
            bkrecv_db()->prepare("UPDATE bk_documents SET doc_no=?, doc_date=?, party_ext=?, party_name=?, gstin=?, total=?, tax=?, payload=?, updated_at=? WHERE id=?")
                ->execute([(string)($d['invoice_no'] ?? ''), (string)($d['date'] ?? ''), (string)($d['party_ext'] ?? ''),
                    (string)($d['party_name'] ?? ''), (string)($d['gstin'] ?? ''), $total, $tax,
                    json_encode($d, JSON_UNESCAPED_UNICODE), date('c'), (int)$existing['id']]);
            bkrecv_recompute_invoice((int)$existing['id']);
            return ['ok' => true, 'ref' => (string)$existing['ref']];
        }
        bkrecv_db()->prepare("INSERT INTO bk_documents (ext_id, kind, doc_no, doc_date, party_ext, party_name, gstin, total, tax, irn, status, paid, outstanding, payload, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$ext, 'INVOICE', (string)($d['invoice_no'] ?? ''), (string)($d['date'] ?? ''),
                (string)($d['party_ext'] ?? ''), (string)($d['party_name'] ?? ''), (string)($d['gstin'] ?? ''),
                $total, $tax, $irn, 'UNPAID', 0, $total, json_encode($d, JSON_UNESCAPED_UNICODE), date('c'), date('c')]);
        $id = (int)bkrecv_db()->lastInsertId();
        $ref = 'BK-INV-' . $id;
        bkrecv_db()->prepare("UPDATE bk_documents SET ref=? WHERE id=?")->execute([$ref, $id]);
        return ['ok' => true, 'ref' => $ref];
    }

    if ($kind === 'QUOTE') {
        $ref = bkrecv_store_simple($existing, $ext, 'QUOTE', [
            'doc_no' => (string)($d['quote_no'] ?? ''), 'doc_date' => (string)($d['date'] ?? ''),
            'party_ext' => (string)($d['party_ext'] ?? ''), 'party_name' => (string)($d['party_name'] ?? ''),
            'total' => round((float)($d['total'] ?? 0), 2), 'has_pdf' => trim((string)($d['pdf_b64'] ?? '')) !== '' ? 1 : 0,
        ], $d, 'BK-QT-');
        return ['ok' => true, 'ref' => $ref];
    }

    if ($kind === 'RECEIPT') {
        $ref = bkrecv_store_simple($existing, $ext, 'RECEIPT', [
            'doc_no' => (string)($d['receipt_no'] ?? ''), 'doc_date' => (string)($d['date'] ?? ''),
            'party_ext' => (string)($d['party_ext'] ?? ''), 'party_name' => (string)($d['party_name'] ?? ''),
            'total' => round((float)($d['amount'] ?? 0), 2),
        ], $d, 'BK-RCP-');
        if (!$existing) bkrecv_apply_receipt((string)($d['party_ext'] ?? ''), round((float)($d['amount'] ?? 0), 2));
        return ['ok' => true, 'ref' => $ref];
    }

    // CREDITNOTE
    $amt = round((float)($d['amount'] ?? 0), 2);
    $ref = bkrecv_store_simple($existing, $ext, 'CREDITNOTE', [
        'doc_no' => (string)($d['cn_no'] ?? ''), 'doc_date' => (string)($d['date'] ?? ''),
        'party_ext' => (string)($d['party_ext'] ?? ''), 'party_name' => (string)($d['party_name'] ?? ''),
        'against_ext' => (string)($d['against_inv'] ?? ''), 'total' => $amt,
    ], $d, 'BK-CN-');
    if (!$existing) bkrecv_apply_creditnote((string)($d['against_inv'] ?? ''), $amt);
    return ['ok' => true, 'ref' => $ref];
}

// Upsert a non-invoice document by ext_id and return its ref.
function bkrecv_store_simple($existing, $ext, $kind, array $cols, array $payload, $refPrefix) {
    $cols = array_merge(['doc_no' => '', 'doc_date' => '', 'party_ext' => '', 'party_name' => '',
        'against_ext' => '', 'total' => 0, 'has_pdf' => 0], $cols);
    if ($existing) {
        bkrecv_db()->prepare("UPDATE bk_documents SET doc_no=?, doc_date=?, party_ext=?, party_name=?, against_ext=?, total=?, has_pdf=?, payload=?, updated_at=? WHERE id=?")
            ->execute([$cols['doc_no'], $cols['doc_date'], $cols['party_ext'], $cols['party_name'], $cols['against_ext'],
                $cols['total'], (int)$cols['has_pdf'], json_encode($payload, JSON_UNESCAPED_UNICODE), date('c'), (int)$existing['id']]);
        return (string)$existing['ref'];
    }
    bkrecv_db()->prepare("INSERT INTO bk_documents (ext_id, kind, doc_no, doc_date, party_ext, party_name, against_ext, total, has_pdf, status, payload, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$ext, $kind, $cols['doc_no'], $cols['doc_date'], $cols['party_ext'], $cols['party_name'],
            $cols['against_ext'], $cols['total'], (int)$cols['has_pdf'], 'RECORDED',
            json_encode($payload, JSON_UNESCAPED_UNICODE), date('c'), date('c')]);
    $id = (int)bkrecv_db()->lastInsertId();
    $ref = $refPrefix . $id;
    bkrecv_db()->prepare("UPDATE bk_documents SET ref=? WHERE id=?")->execute([$ref, $id]);
    return $ref;
}

// Money in: reduce the party's open invoices, oldest first, and restate each.
function bkrecv_apply_receipt($partyExt, $amount) {
    if ($partyExt === '' || $amount <= 0) return;
    $left = $amount;
    foreach (bkrecv_all("SELECT id, outstanding FROM bk_documents WHERE kind='INVOICE' AND party_ext=? AND outstanding > 0 ORDER BY id", [$partyExt]) as $inv) {
        if ($left <= 0) break;
        $take = min($left, (float)$inv['outstanding']);
        bkrecv_db()->prepare("UPDATE bk_documents SET paid = paid + ?, outstanding = outstanding - ?, updated_at=? WHERE id=?")
            ->execute([$take, $take, date('c'), (int)$inv['id']]);
        bkrecv_restatus((int)$inv['id']);
        $left = round($left - $take, 2);
    }
}

// A credit note reduces the invoice it names.
function bkrecv_apply_creditnote($againstExt, $amount) {
    if ($againstExt === '' || $amount <= 0) return;
    $inv = bkrecv_one("SELECT id, outstanding FROM bk_documents WHERE kind='INVOICE' AND ext_id=?", [$againstExt]);
    if (!$inv) return;
    $take = min($amount, (float)$inv['outstanding']);
    bkrecv_db()->prepare("UPDATE bk_documents SET outstanding = outstanding - ?, updated_at=? WHERE id=?")
        ->execute([$take, date('c'), (int)$inv['id']]);
    bkrecv_restatus((int)$inv['id']);
}

// Recompute an invoice's paid/outstanding from scratch after a header re-import.
function bkrecv_recompute_invoice($id) {
    $inv = bkrecv_one("SELECT ext_id, total, paid FROM bk_documents WHERE id=?", [$id]);
    if (!$inv) return;
    // total may have changed; keep paid, clamp outstanding to >= 0.
    $out = max(0, round((float)$inv['total'] - (float)$inv['paid'], 2));
    bkrecv_db()->prepare("UPDATE bk_documents SET outstanding=?, updated_at=? WHERE id=?")->execute([$out, date('c'), $id]);
    bkrecv_restatus($id);
}

// Set the paid/part/unpaid label from the figures.
function bkrecv_restatus($id) {
    $inv = bkrecv_one("SELECT paid, outstanding FROM bk_documents WHERE id=?", [$id]);
    if (!$inv) return;
    $paid = (float)$inv['paid']; $out = (float)$inv['outstanding'];
    $status = $out <= 0.005 ? 'PAID' : ($paid > 0.005 ? 'PART_PAID' : 'UNPAID');
    bkrecv_db()->prepare("UPDATE bk_documents SET status=? WHERE id=?")->execute([$status, $id]);
}

// ---- status ---------------------------------------------------------------
// The money truth for one invoice, by its ERP external id, in the shape the ERP's
// books_apply_status() consumes.
function bkrecv_status($extId) {
    $extId = trim((string)$extId);
    if ($extId === '') return ['ok' => false, 'error' => 'ext_id required'];
    $inv = bkrecv_one("SELECT irn, status, paid, outstanding FROM bk_documents WHERE kind='INVOICE' AND ext_id=?", [$extId]);
    if (!$inv) return ['ok' => false, 'error' => 'not found'];
    return ['ok' => true, 'status' => [
        'irn'         => (string)$inv['irn'],
        'status'      => (string)$inv['status'],
        'paid'        => (float)$inv['paid'],
        'outstanding' => (float)$inv['outstanding'],
    ]];
}

// ---- Dispatch -------------------------------------------------------------
// One entry point the HTTP front (api.php) and the tests both call, so they
// exercise identical logic. Returns [httpCode, responseArray].
function bkrecv_dispatch($action, $kind, array $data, $extId = '') {
    $action = strtolower((string)$action);
    if ($action === 'status') {
        $r = bkrecv_status($extId !== '' ? $extId : (string)($data['ext_id'] ?? ''));
        return [$r['ok'] ? 200 : 404, $r];
    }
    if ($action === 'set') {
        $r = bkrecv_set_party($data);
        return [$r['ok'] ? 200 : 400, $r];
    }
    if ($action === 'import') {
        $r = bkrecv_import($kind, $data);
        return [$r['ok'] ? 200 : 400, $r];
    }
    return [400, ['ok' => false, 'error' => 'unknown action']];
}
