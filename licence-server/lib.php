<?php
// ============================================================================
//  MGH Licence Server — the thing that turns a payment into a renewal
//
//  Deployed to id.mghaiapps.com. It holds the customers, what each has bought,
//  and until when. Every installation checks in and collects its current key;
//  a payment webhook extends the subscription, and the very next check-in
//  picks it up. Nobody types anything.
//
//  THE PRIVATE SIGNING KEY LIVES HERE, and this is a deliberate change from
//  what I first said. "Never put the private key on a server" is right about a
//  CUSTOMER'S server and about a git repository. It cannot be right about the
//  machine whose entire job is signing, because automatic renewal means a
//  machine has to do the signing. Every subscription business works this way.
//  What matters is that this is ONE well-guarded server rather than a key
//  copied onto every customer's machine:
//
//      * the key file lives OUTSIDE the web root and is never served
//      * the repository ships no key at all; it is generated on the box
//      * losing this box loses the ability to ISSUE, not the ability to RUN —
//        every customer keeps working on the key they already hold
//
//  WHAT IT IS NOT. It is not the identity hub, not the party master, and not a
//  billing engine. It answers one question — "what is this installation
//  entitled to today" — and does it in a few hundred lines that can be read in
//  one sitting. MGH ID can absorb it later; until then this is small enough to
//  trust.
// ============================================================================

const LS_DB      = __DIR__ . '/data/licences.sqlite';
const LS_KEYFILE = __DIR__ . '/../licence-signing-key.pem';   // outside the web root
const LS_GRACE   = 14;

function ls_db() {
    static $db = null;
    if ($db) return $db;
    if (!is_dir(dirname(LS_DB))) @mkdir(dirname(LS_DB), 0770, true);
    $db = new PDO('sqlite:' . LS_DB, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA journal_mode=WAL');
    ls_migrate($db);
    return $db;
}

function ls_migrate($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL, email TEXT DEFAULT '', phone TEXT DEFAULT '',
        gstin TEXT DEFAULT '', notes TEXT DEFAULT '', created_at TEXT DEFAULT '')");

    // One row per thing a customer runs. install_id is the credential that
    // installation presents; it is opaque and grants nothing but this answer.
    $db->exec("CREATE TABLE IF NOT EXISTS installs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER NOT NULL,
        install_id TEXT NOT NULL UNIQUE,
        label TEXT DEFAULT '',
        product TEXT DEFAULT 'crm',
        mods TEXT DEFAULT 'sales,admin',
        seats INTEGER DEFAULT 0,
        expires_on TEXT DEFAULT '',
        grace_days INTEGER DEFAULT 14,
        status TEXT DEFAULT 'ACTIVE',          -- ACTIVE | SUSPENDED
        created_at TEXT DEFAULT '',
        last_seen_at TEXT DEFAULT '', last_seen_ip TEXT DEFAULT '', seen_count INTEGER DEFAULT 0)");

    // Every payment that moved an expiry, and every renewal that resulted.
    // An accounts query six months from now must be answerable without guessing.
    $db->exec("CREATE TABLE IF NOT EXISTS ledger (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        install_id TEXT DEFAULT '', kind TEXT DEFAULT '',   -- PAYMENT | MANUAL | SUSPEND | RESUME
        amount REAL DEFAULT 0, currency TEXT DEFAULT 'INR',
        reference TEXT DEFAULT '', months INTEGER DEFAULT 0,
        was TEXT DEFAULT '', now TEXT DEFAULT '',
        detail TEXT DEFAULT '', at TEXT DEFAULT '')");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_inst ON installs(install_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_led ON ledger(install_id, at)");
}

function ls_now() { return date('c'); }
function ls_uuid() { return bin2hex(random_bytes(16)); }

// ---- Signing ----------------------------------------------------------------
// The key is made on this machine, once, and never leaves it. If it is missing
// the server says so plainly rather than issuing something unverifiable.
function ls_private_key() {
    if (!is_file(LS_KEYFILE)) return null;
    return file_get_contents(LS_KEYFILE);
}

function ls_make_keys() {
    if (is_file(LS_KEYFILE)) return ['err' => 'A signing key already exists. Overwriting it would invalidate every licence ever issued.'];
    $k = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if (!$k) return ['err' => 'Could not generate a key: ' . openssl_error_string()];
    openssl_pkey_export($k, $priv);
    if (@file_put_contents(LS_KEYFILE, $priv) === false)
        return ['err' => 'Could not write ' . LS_KEYFILE . ' — check the folder is writable by the web server.'];
    @chmod(LS_KEYFILE, 0600);
    return ['ok' => true, 'public' => openssl_pkey_get_details($k)['key']];
}

function ls_public_key() {
    $p = ls_private_key();
    if (!$p) return '';
    $k = openssl_pkey_get_private($p);
    return $k ? (openssl_pkey_get_details($k)['key'] ?? '') : '';
}

function ls_b64($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

// Mint the key for one installation, from its row. Deliberately derived from
// the database every time rather than stored: there is then exactly one place
// that decides what a customer is entitled to, and it is the row a human edits.
function ls_mint($row) {
    $priv = ls_private_key();
    if (!$priv) return ['err' => 'This server has no signing key yet. Open Setup and make one.'];

    $mods = array_values(array_filter(array_map('trim', explode(',', (string)$row['mods']))));
    if (!in_array('admin', $mods, true)) $mods[] = 'admin';

    $claims = [
        'cust'  => (string)$row['customer_name'],
        'ref'   => 'INST-' . (int)$row['id'],
        'mods'  => $mods,
        'seats' => (int)$row['seats'],
        'exp'   => (string)$row['expires_on'],
        'grace' => (int)($row['grace_days'] ?: LS_GRACE),
        'iss'   => date('Y-m-d'),
    ];
    $payload = ls_b64(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if (!openssl_sign($payload, $sig, $priv, OPENSSL_ALGO_SHA256))
        return ['err' => 'Signing failed: ' . openssl_error_string()];
    return ['ok' => true, 'key' => $payload . '.' . ls_b64($sig), 'claims' => $claims];
}

function ls_install($installId) {
    $q = ls_db()->prepare("SELECT i.*, c.name AS customer_name, c.email AS customer_email
                           FROM installs i JOIN customers c ON c.id = i.customer_id
                           WHERE i.install_id = ? LIMIT 1");
    $q->execute([(string)$installId]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ---- Extending --------------------------------------------------------------
// The one function that moves an expiry, so the ledger can never disagree with
// the subscription. Months are added to whichever is LATER — today, or the
// current expiry — so paying early extends rather than truncates, and paying
// late does not gift the lapsed period.
function ls_extend($installId, $months, $kind, $amount = 0, $reference = '', $detail = '') {
    $row = ls_install($installId);
    if (!$row) return ['err' => 'No installation with that id.'];
    $months = (int)$months;
    if ($months <= 0) return ['err' => 'Months must be a positive number.'];

    $was  = (string)$row['expires_on'];
    $from = ($was !== '' && strtotime($was) > time()) ? strtotime($was) : time();
    $new  = date('Y-m-d', strtotime('+' . $months . ' months', $from));

    ls_db()->prepare("UPDATE installs SET expires_on = ?, status = 'ACTIVE' WHERE install_id = ?")
           ->execute([$new, (string)$installId]);
    ls_db()->prepare("INSERT INTO ledger (install_id, kind, amount, reference, months, was, now, detail, at)
                      VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([(string)$installId, $kind, (float)$amount, (string)$reference, $months,
                      $was, $new, (string)$detail, ls_now()]);
    return ['ok' => true, 'was' => $was, 'now' => $new, 'customer' => $row['customer_name']];
}

function ls_ledger($installId = '', $n = 50) {
    if ($installId !== '') {
        $q = ls_db()->prepare("SELECT * FROM ledger WHERE install_id = ? ORDER BY id DESC LIMIT " . (int)$n);
        $q->execute([$installId]);
    } else {
        $q = ls_db()->query("SELECT * FROM ledger ORDER BY id DESC LIMIT " . (int)$n);
    }
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

function ls_installs() {
    return ls_db()->query("SELECT i.*, c.name AS customer_name, c.email AS customer_email
                           FROM installs i JOIN customers c ON c.id = i.customer_id
                           ORDER BY i.expires_on ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Days until expiry, negative once past. Null when no expiry is set.
function ls_days_left($row) {
    $e = trim((string)($row['expires_on'] ?? ''));
    if ($e === '') return null;
    return (int)floor((strtotime($e . ' 23:59:59') - time()) / 86400);
}

function ls_state($row) {
    if (($row['status'] ?? '') === 'SUSPENDED') return 'SUSPENDED';
    $d = ls_days_left($row);
    if ($d === null) return 'NO EXPIRY';
    if ($d >= 0) return 'ACTIVE';
    if ($d >= -(int)($row['grace_days'] ?: LS_GRACE)) return 'GRACE';
    return 'LAPSED';
}

// ---- Admin session ----------------------------------------------------------
// One password, held as a hash in a config file outside the web root. This
// server has one operator; anything more elaborate is a system to maintain
// rather than a control that helps.
const LS_CONFIG = __DIR__ . '/../licence-server-config.php';

function ls_config() {
    static $c = null;
    if ($c !== null) return $c;
    return $c = is_file(LS_CONFIG) ? (include LS_CONFIG) : [];
}

function ls_admin_hash()   { return (string)(ls_config()['admin_hash'] ?? ''); }
function ls_webhook_key()  { return (string)(ls_config()['razorpay_secret'] ?? ''); }

function ls_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('MGHLIC');
        session_start();
    }
    return !empty($_SESSION['ls_admin']);
}

function ls_require_login() {
    if (!ls_logged_in()) { header('Location: index.php?p=login'); exit; }
}

function ls_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function ls_csrf() {
    if (session_status() === PHP_SESSION_NONE) { session_name('MGHLIC'); session_start(); }
    if (empty($_SESSION['ls_csrf'])) $_SESSION['ls_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['ls_csrf'];
}
function ls_csrf_ok($t) {
    if (session_status() === PHP_SESSION_NONE) { session_name('MGHLIC'); session_start(); }
    return !empty($_SESSION['ls_csrf']) && hash_equals($_SESSION['ls_csrf'], (string)$t);
}
