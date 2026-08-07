<?php
// ============================================================================
//  MGH Books — inbound receiver for the ERP connector
//
//  This is the SERVER side of the contract in the ERP's lib/booksbridge.php.
//  Drop it into the MGH Books application; the ERP pushes here and reads status
//  back from here. Zero dependencies, idempotent by the ERP's external id.
//
//  ── The seam ──────────────────────────────────────────────────────────────
//  Two things vary between the reference build and a real Books deployment:
//  WHERE data is stored, and HOW a caller is authenticated. Both are behind an
//  adapter, so Books points them at its own tables and its own login WITHOUT
//  touching api.php and — this is the point — WITHOUT touching the calculation
//  engine. The arithmetic (how a receipt pays down invoices, how a credit note
//  reduces one, how paid/outstanding/status are derived) lives in this file, in
//  the bkrecv_* domain functions, and is written exactly once. An adapter only
//  reads and writes rows; it never computes a figure. So a wrong adapter can
//  lose or misfile a record, but it can never make the books disagree with the
//  ERP — the numbers are not the adapter's to change.
//
//    Storage : implement Bkrecv_Store (13 tiny row-level methods) and register
//              it with bkrecv_store($yourStore). Default: Bkrecv_PdoStore over
//              SQLite/MySQL, the reference tables bk_parties / bk_documents.
//    Login   : bkrecv_auth_handler(fn($token) => ...true/false...). Default:
//              a constant-time compare against the BOOKS_API_TOKEN env value.
//
//  Everything is namespaced bkrecv_* / Bkrecv_* so it can live alongside
//  anything without a clash, and so the ERP's test harness can drive these
//  handlers directly to prove the wire format matches.
//
//  Three verbs, matching what the connector sends:
//    action=set     kind=PARTY                                -> upsert a customer
//    action=import  kind=INVOICE|QUOTE|CREDITNOTE|RECEIPT     -> record a document
//    action=status  ext_id=ERP-INV-<n>                        -> paid/outstanding/IRN back
//
//  Reply shape the connector expects:
//    set/import : { "ok": true, "ref": "BK-INV-42" }
//    status     : { "ok": true, "status": { irn, status, paid, outstanding } }
//    any failure: { "ok": false, "error": "..." }  with a non-2xx code
// ============================================================================

// ---- The storage adapter contract -----------------------------------------
// Every method is a plain row read or write. NOTHING here computes money — the
// domain functions below do that and call these to persist the result. A Books
// adapter implements the same 13 methods against Books' own tables and is done.
interface Bkrecv_Store {
    // Parties (customers), keyed by the ERP external id (e.g. ERP-BP-7).
    public function partyByExt(string $ext): ?array;                 // ['id','ref'] or null
    public function partyUpsert(string $ext, array $d, ?array $existing): string; // returns the Books ref

    // Any document, keyed by the ERP external id, for the idempotency check.
    public function docByExt(string $ext): ?array;                   // ['id','ref','kind'] or null

    // Invoices — the only document that carries a running money balance.
    public function invoiceInsert(string $ext, array $d, float $total, float $tax, string $irn): string; // returns ref
    public function invoiceHeaderUpdate(int $id, array $d, float $total, float $tax): void;
    public function invoiceGet(int $id): ?array;                     // ['ext_id','total','paid','outstanding','irn','status']
    public function invoiceByExt(string $ext): ?array;               // ['id','irn','status','paid','outstanding']
    public function openInvoicesByParty(string $partyExt): array;    // [['id','outstanding'], …] oldest first
    public function invoicePay(int $id, float $take): void;          // paid += take, outstanding -= take
    public function invoiceSetOutstanding(int $id, float $out): void;
    public function invoiceSetStatus(int $id, string $status): void;

    // Non-invoice documents (quote / receipt / credit note): store and reference.
    public function simpleUpsert(string $kind, string $ext, array $cols, array $payload, string $refPrefix, ?array $existing): string;

    // Called once before first use so a store can create/verify its schema.
    public function ensureSchema(): void;
}

// The active store. Pass one to register it; call with nothing to read it.
function bkrecv_store(?Bkrecv_Store $inject = null): Bkrecv_Store {
    static $store = null;
    if ($inject !== null) { $store = $inject; $store->ensureSchema(); return $store; }
    if ($store instanceof Bkrecv_Store) return $store;
    $store = new Bkrecv_PdoStore();          // reference default
    $store->ensureSchema();
    return $store;
}

// ---- The login seam -------------------------------------------------------
// Register Books' own check with bkrecv_auth_handler(fn($token)=>bool). Default:
// constant-time compare against BOOKS_API_TOKEN (unset ⇒ refuse everything).
function bkrecv_auth_handler(?callable $fn = null): callable {
    static $handler = null;
    if ($fn !== null) { $handler = $fn; return $handler; }
    if ($handler === null) $handler = 'bkrecv_default_auth';
    return $handler;
}
function bkrecv_default_auth($token): bool {
    $expected = (string)(getenv('BOOKS_API_TOKEN') ?: '');
    if ($expected === '') return false;
    return is_string($token) && $token !== '' && hash_equals($expected, $token);
}
function bkrecv_auth($token): bool {
    return (bool)call_user_func(bkrecv_auth_handler(), $token);
}

// ============================================================================
//  The domain — the calculation engine. Backend-independent: it only ever
//  touches storage through bkrecv_store(). This is the code that must never be
//  duplicated per backend, and behind the seam it never is.
// ============================================================================

// ---- set: PARTY -----------------------------------------------------------
function bkrecv_set_party(array $d) {
    $ext = trim((string)($d['ext_id'] ?? ''));
    if ($ext === '') return ['ok' => false, 'error' => 'party has no ext_id'];
    $existing = bkrecv_store()->partyByExt($ext);
    $ref = bkrecv_store()->partyUpsert($ext, $d, $existing);
    return ['ok' => true, 'ref' => $ref];
}

// ---- import: documents ----------------------------------------------------
function bkrecv_import($kind, array $d) {
    $kind = strtoupper((string)$kind);
    $ext  = trim((string)($d['ext_id'] ?? ''));
    if ($ext === '') return ['ok' => false, 'error' => 'document has no ext_id'];
    if (!in_array($kind, ['INVOICE', 'QUOTE', 'CREDITNOTE', 'RECEIPT'], true))
        return ['ok' => false, 'error' => "unknown kind $kind"];

    $existing = bkrecv_store()->docByExt($ext);   // idempotency: already imported?

    if ($kind === 'INVOICE') {
        $total = round((float)($d['total'] ?? 0), 2);
        $tax   = round((float)($d['cgst'] ?? 0) + (float)($d['sgst'] ?? 0) + (float)($d['igst'] ?? 0), 2);
        $irn   = 'IRN-' . strtoupper(substr(sha1($ext . '|' . (string)($d['invoice_no'] ?? '')), 0, 12));
        if ($existing) {
            // Re-import refreshes the header but keeps every payment and credit
            // already booked. Outstanding moves ONLY by how much the total itself
            // changed — never a recompute from scratch, which would wipe credit
            // notes (they reduce outstanding without touching paid).
            $cur   = bkrecv_store()->invoiceGet((int)$existing['id']);
            $delta = $cur ? round($total - (float)$cur['total'], 2) : 0.0;
            bkrecv_store()->invoiceHeaderUpdate((int)$existing['id'], $d, $total, $tax);
            if ($cur) {
                $newOut = max(0, round((float)$cur['outstanding'] + $delta, 2));
                bkrecv_store()->invoiceSetOutstanding((int)$existing['id'], $newOut);
                bkrecv_restatus((int)$existing['id']);
            }
            return ['ok' => true, 'ref' => (string)$existing['ref']];
        }
        $ref = bkrecv_store()->invoiceInsert($ext, $d, $total, $tax, $irn);
        return ['ok' => true, 'ref' => $ref];
    }

    if ($kind === 'QUOTE') {
        $ref = bkrecv_store()->simpleUpsert('QUOTE', $ext, [
            'doc_no' => (string)($d['quote_no'] ?? ''), 'doc_date' => (string)($d['date'] ?? ''),
            'party_ext' => (string)($d['party_ext'] ?? ''), 'party_name' => (string)($d['party_name'] ?? ''),
            'total' => round((float)($d['total'] ?? 0), 2), 'has_pdf' => trim((string)($d['pdf_b64'] ?? '')) !== '' ? 1 : 0,
        ], $d, 'BK-QT-', $existing);
        return ['ok' => true, 'ref' => $ref];
    }

    if ($kind === 'RECEIPT') {
        $amt = round((float)($d['amount'] ?? 0), 2);
        $ref = bkrecv_store()->simpleUpsert('RECEIPT', $ext, [
            'doc_no' => (string)($d['receipt_no'] ?? ''), 'doc_date' => (string)($d['date'] ?? ''),
            'party_ext' => (string)($d['party_ext'] ?? ''), 'party_name' => (string)($d['party_name'] ?? ''),
            'total' => $amt,
        ], $d, 'BK-RCP-', $existing);
        if (!$existing) bkrecv_apply_receipt((string)($d['party_ext'] ?? ''), $amt);
        return ['ok' => true, 'ref' => $ref];
    }

    // CREDITNOTE
    $amt = round((float)($d['amount'] ?? 0), 2);
    $ref = bkrecv_store()->simpleUpsert('CREDITNOTE', $ext, [
        'doc_no' => (string)($d['cn_no'] ?? ''), 'doc_date' => (string)($d['date'] ?? ''),
        'party_ext' => (string)($d['party_ext'] ?? ''), 'party_name' => (string)($d['party_name'] ?? ''),
        'against_ext' => (string)($d['against_inv'] ?? ''), 'total' => $amt,
    ], $d, 'BK-CN-', $existing);
    if (!$existing) bkrecv_apply_creditnote((string)($d['against_inv'] ?? ''), $amt);
    return ['ok' => true, 'ref' => $ref];
}

// Money in: reduce the party's open invoices, oldest first, and restate each.
function bkrecv_apply_receipt($partyExt, $amount) {
    if ($partyExt === '' || $amount <= 0) return;
    $left = round((float)$amount, 2);
    foreach (bkrecv_store()->openInvoicesByParty($partyExt) as $inv) {
        if ($left <= 0) break;
        $take = min($left, (float)$inv['outstanding']);
        bkrecv_store()->invoicePay((int)$inv['id'], $take);
        bkrecv_restatus((int)$inv['id']);
        $left = round($left - $take, 2);
    }
}

// A credit note reduces the invoice it names.
function bkrecv_apply_creditnote($againstExt, $amount) {
    if ($againstExt === '' || $amount <= 0) return;
    $inv = bkrecv_store()->invoiceByExt($againstExt);
    if (!$inv) return;
    $take = min((float)$amount, (float)$inv['outstanding']);
    $newOut = round((float)$inv['outstanding'] - $take, 2);
    bkrecv_store()->invoiceSetOutstanding((int)$inv['id'], $newOut);
    bkrecv_restatus((int)$inv['id']);
}

// The paid/part/unpaid label, derived from the figures. One definition.
function bkrecv_restatus($id) {
    $inv = bkrecv_store()->invoiceGet((int)$id);
    if (!$inv) return;
    $paid = (float)$inv['paid']; $out = (float)$inv['outstanding'];
    $status = $out <= 0.005 ? 'PAID' : ($paid > 0.005 ? 'PART_PAID' : 'UNPAID');
    bkrecv_store()->invoiceSetStatus((int)$id, $status);
}

// ---- status ---------------------------------------------------------------
function bkrecv_status($extId) {
    $extId = trim((string)$extId);
    if ($extId === '') return ['ok' => false, 'error' => 'ext_id required'];
    $inv = bkrecv_store()->invoiceByExt($extId);
    if (!$inv) return ['ok' => false, 'error' => 'not found'];
    return ['ok' => true, 'status' => [
        'irn'         => (string)$inv['irn'],
        'status'      => (string)$inv['status'],
        'paid'        => (float)$inv['paid'],
        'outstanding' => (float)$inv['outstanding'],
    ]];
}

// ---- Dispatch -------------------------------------------------------------
// One entry point the HTTP front (api.php) and the tests both call.
function bkrecv_dispatch($action, $kind, array $data, $extId = '') {
    $action = strtolower((string)$action);
    if ($action === 'status') {
        $r = bkrecv_status($extId !== '' ? $extId : (string)($data['ext_id'] ?? ''));
        return [$r['ok'] ? 200 : 404, $r];
    }
    if ($action === 'set')    { $r = bkrecv_set_party($data);  return [$r['ok'] ? 200 : 400, $r]; }
    if ($action === 'import') { $r = bkrecv_import($kind, $data); return [$r['ok'] ? 200 : 400, $r]; }
    return [400, ['ok' => false, 'error' => 'unknown action']];
}

// ============================================================================
//  Bkrecv_PdoStore — the reference storage adapter (SQLite dev / MySQL prod).
//  This is what a Books adapter replaces. Notice it holds no arithmetic: every
//  method is a row read or a row write.
// ============================================================================
final class Bkrecv_PdoStore implements Bkrecv_Store {
    private PDO $pdo;
    private bool $ready = false;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?: self::openFromEnv();
    }
    private static function openFromEnv(): PDO {
        $driver = getenv('BOOKS_DB_DRIVER') ?: 'sqlite';
        if ($driver === 'mysql') {
            $host = getenv('BOOKS_DB_HOST') ?: '127.0.0.1';
            $name = getenv('BOOKS_DB_NAME') ?: 'mgh_books';
            $user = getenv('BOOKS_DB_USER') ?: 'root';
            $pass = getenv('BOOKS_DB_PASS') ?: '';
            return new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }
        $path = getenv('BOOKS_SQLITE_PATH') ?: (__DIR__ . '/../data/books.sqlite');
        if (!is_dir(dirname($path))) @mkdir(dirname($path), 0770, true);
        return new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    public function pdo(): PDO { return $this->pdo; }

    public function ensureSchema(): void {
        if ($this->ready) return; $this->ready = true;
        $isSqlite = strpos((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') !== false;
        $pk = $isSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS bk_parties (
            id $pk, ext_id VARCHAR(80) UNIQUE, type VARCHAR(20) DEFAULT 'customer',
            name VARCHAR(200) DEFAULT '', legal VARCHAR(200) DEFAULT '', gstin VARCHAR(20) DEFAULT '',
            state VARCHAR(60) DEFAULT '', email VARCHAR(200) DEFAULT '', phone VARCHAR(40) DEFAULT '',
            ref VARCHAR(80) DEFAULT '', created_at VARCHAR(30) DEFAULT '', updated_at VARCHAR(30) DEFAULT '')");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS bk_documents (
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
    private function one($sql, $a = []) { $s = $this->pdo->prepare($sql); $s->execute($a); $r = $s->fetch(PDO::FETCH_ASSOC); return $r ?: null; }
    private function all($sql, $a = []) { $s = $this->pdo->prepare($sql); $s->execute($a); return $s->fetchAll(PDO::FETCH_ASSOC) ?: []; }

    public function partyByExt(string $ext): ?array { return $this->one("SELECT id, ref FROM bk_parties WHERE ext_id=?", [$ext]); }
    public function partyUpsert(string $ext, array $d, ?array $existing): string {
        $now = date('c');
        if ($existing) {
            $this->pdo->prepare("UPDATE bk_parties SET type=?, name=?, legal=?, gstin=?, state=?, email=?, phone=?, updated_at=? WHERE id=?")
                ->execute([(string)($d['type'] ?? 'customer'), (string)($d['name'] ?? ''), (string)($d['legal'] ?? ''),
                    (string)($d['gstin'] ?? ''), (string)($d['state'] ?? ''), (string)($d['email'] ?? ''),
                    (string)($d['phone'] ?? ''), $now, (int)$existing['id']]);
            return (string)$existing['ref'];
        }
        $this->pdo->prepare("INSERT INTO bk_parties (ext_id, type, name, legal, gstin, state, email, phone, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$ext, (string)($d['type'] ?? 'customer'), (string)($d['name'] ?? ''), (string)($d['legal'] ?? ''),
                (string)($d['gstin'] ?? ''), (string)($d['state'] ?? ''), (string)($d['email'] ?? ''), (string)($d['phone'] ?? ''), $now, $now]);
        $id = (int)$this->pdo->lastInsertId(); $ref = 'BK-CUST-' . $id;
        $this->pdo->prepare("UPDATE bk_parties SET ref=? WHERE id=?")->execute([$ref, $id]);
        return $ref;
    }

    public function docByExt(string $ext): ?array { return $this->one("SELECT id, ref, kind FROM bk_documents WHERE ext_id=?", [$ext]); }

    public function invoiceInsert(string $ext, array $d, float $total, float $tax, string $irn): string {
        $this->pdo->prepare("INSERT INTO bk_documents (ext_id, kind, doc_no, doc_date, party_ext, party_name, gstin, total, tax, irn, status, paid, outstanding, payload, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$ext, 'INVOICE', (string)($d['invoice_no'] ?? ''), (string)($d['date'] ?? ''),
                (string)($d['party_ext'] ?? ''), (string)($d['party_name'] ?? ''), (string)($d['gstin'] ?? ''),
                $total, $tax, $irn, 'UNPAID', 0, $total, json_encode($d, JSON_UNESCAPED_UNICODE), date('c'), date('c')]);
        $id = (int)$this->pdo->lastInsertId(); $ref = 'BK-INV-' . $id;
        $this->pdo->prepare("UPDATE bk_documents SET ref=? WHERE id=?")->execute([$ref, $id]);
        return $ref;
    }
    public function invoiceHeaderUpdate(int $id, array $d, float $total, float $tax): void {
        $this->pdo->prepare("UPDATE bk_documents SET doc_no=?, doc_date=?, party_ext=?, party_name=?, gstin=?, total=?, tax=?, payload=?, updated_at=? WHERE id=?")
            ->execute([(string)($d['invoice_no'] ?? ''), (string)($d['date'] ?? ''), (string)($d['party_ext'] ?? ''),
                (string)($d['party_name'] ?? ''), (string)($d['gstin'] ?? ''), $total, $tax,
                json_encode($d, JSON_UNESCAPED_UNICODE), date('c'), $id]);
    }
    public function invoiceGet(int $id): ?array { return $this->one("SELECT ext_id, total, paid, outstanding, irn, status FROM bk_documents WHERE id=?", [$id]); }
    public function invoiceByExt(string $ext): ?array { return $this->one("SELECT id, irn, status, paid, outstanding FROM bk_documents WHERE kind='INVOICE' AND ext_id=?", [$ext]); }
    public function openInvoicesByParty(string $partyExt): array {
        return $this->all("SELECT id, outstanding FROM bk_documents WHERE kind='INVOICE' AND party_ext=? AND outstanding > 0 ORDER BY id", [$partyExt]);
    }
    public function invoicePay(int $id, float $take): void {
        $this->pdo->prepare("UPDATE bk_documents SET paid = paid + ?, outstanding = outstanding - ?, updated_at=? WHERE id=?")->execute([$take, $take, date('c'), $id]);
    }
    public function invoiceSetOutstanding(int $id, float $out): void {
        $this->pdo->prepare("UPDATE bk_documents SET outstanding=?, updated_at=? WHERE id=?")->execute([$out, date('c'), $id]);
    }
    public function invoiceSetStatus(int $id, string $status): void {
        $this->pdo->prepare("UPDATE bk_documents SET status=? WHERE id=?")->execute([$status, $id]);
    }

    public function simpleUpsert(string $kind, string $ext, array $cols, array $payload, string $refPrefix, ?array $existing): string {
        $cols = array_merge(['doc_no' => '', 'doc_date' => '', 'party_ext' => '', 'party_name' => '',
            'against_ext' => '', 'total' => 0, 'has_pdf' => 0], $cols);
        if ($existing) {
            $this->pdo->prepare("UPDATE bk_documents SET doc_no=?, doc_date=?, party_ext=?, party_name=?, against_ext=?, total=?, has_pdf=?, payload=?, updated_at=? WHERE id=?")
                ->execute([$cols['doc_no'], $cols['doc_date'], $cols['party_ext'], $cols['party_name'], $cols['against_ext'],
                    $cols['total'], (int)$cols['has_pdf'], json_encode($payload, JSON_UNESCAPED_UNICODE), date('c'), (int)$existing['id']]);
            return (string)$existing['ref'];
        }
        $this->pdo->prepare("INSERT INTO bk_documents (ext_id, kind, doc_no, doc_date, party_ext, party_name, against_ext, total, has_pdf, status, payload, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$ext, $kind, $cols['doc_no'], $cols['doc_date'], $cols['party_ext'], $cols['party_name'],
                $cols['against_ext'], $cols['total'], (int)$cols['has_pdf'], 'RECORDED',
                json_encode($payload, JSON_UNESCAPED_UNICODE), date('c'), date('c')]);
        $id = (int)$this->pdo->lastInsertId(); $ref = $refPrefix . $id;
        $this->pdo->prepare("UPDATE bk_documents SET ref=? WHERE id=?")->execute([$ref, $id]);
        return $ref;
    }
}

// ---- Back-compat shims ----------------------------------------------------
// The api.php front and the round-trip test still say bkrecv_db()/bkrecv_migrate().
// Injecting a PDO wraps it in the reference store; reading returns that PDO.
function bkrecv_db(?PDO $inject = null): PDO {
    if ($inject !== null) { bkrecv_store(new Bkrecv_PdoStore($inject)); return $inject; }
    $s = bkrecv_store();
    return $s instanceof Bkrecv_PdoStore ? $s->pdo() : (new Bkrecv_PdoStore())->pdo();
}
function bkrecv_migrate(): void { bkrecv_store()->ensureSchema(); }
