<?php
// Database connection, schema creation, admin bootstrap and first-run seed.

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $cfg = require __DIR__ . '/../config.php';
    $d = $cfg['db'];
    if ($d['driver'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . $cfg['sqlite_path']);
    } else {
        $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $d['user'], $d['pass']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function db_driver() {
    $cfg = require __DIR__ . '/../config.php';
    return $cfg['db']['driver'];
}

// Auto-increment primary key clause for both engines.
function pk_clause() {
    return db_driver() === 'sqlite'
        ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
        : 'INT AUTO_INCREMENT PRIMARY KEY';
}

// ---- Columns that hold an uploaded file ------------------------------------
//  Uploads are held in the row, base64-encoded, so a move between servers takes
//  the files with the records. Base64 costs a third more than the raw bytes,
//  and MySQL's MEDIUMTEXT stops at 16,777,215 — so a file at the 12 MB upload
//  limit encodes to 16,777,216 characters and misses by a single byte. Worse,
//  MySQL outside strict mode TRUNCATES rather than complains: the file is
//  stored broken and nobody finds out until somebody opens it months later.
//  SQLite has no such ceiling, which is why this never showed up in testing.
//  LONGTEXT (4 GB) removes the ceiling; the upload limit stays the real cap.
const FILE_COLUMNS = [
    ['job_bills',        'file_data'],
    ['person_documents', 'file_data'],
    ['quote_files',      'file_data'],
    ['crm_templates',    'file_data'],
    ['report_files',     'data'],
    ['report_templates', 'file_data'],
    ['endorsement_files','data'],
    ['vouchers',         'supporting_file'],
];

// True while any of them is still the narrow type. Asserted by the boot probe,
// because a column that merely needs widening is not a *missing* column and
// nothing else would ever notice.
function file_columns_pending() {
    if (db_driver() === 'sqlite') return false;      // TEXT is unbounded there
    try {
        foreach (FILE_COLUMNS as [$t, $c]) {
            $q = db()->prepare("SHOW COLUMNS FROM `$t` LIKE ?");
            $q->execute([$c]);
            $row = $q->fetch();
            if ($row && stripos((string)($row['Type'] ?? ''), 'longtext') === false) return true;
        }
    } catch (Throwable $e) { return false; }          // table not built yet
    return false;
}

function widen_file_columns() {
    if (db_driver() === 'sqlite') return;
    foreach (FILE_COLUMNS as [$t, $c]) {
        try { db()->exec("ALTER TABLE `$t` MODIFY `$c` LONGTEXT"); }
        catch (Throwable $e) { /* table not built yet, or already wide */ }
    }
}

function ensure_schema() {
    $pdo = db();
    $pk = pk_clause();
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id $pk, username VARCHAR(150) UNIQUE, password_hash VARCHAR(255),
            first_name VARCHAR(120) DEFAULT '', last_name VARCHAR(120) DEFAULT '',
            email VARCHAR(200) DEFAULT '', role VARCHAR(40) DEFAULT 'ADMIN',
            is_superuser INT DEFAULT 0, is_active INT DEFAULT 1)",
        "CREATE TABLE IF NOT EXISTS business_partners (
            id $pk, code VARCHAR(60), legal_name VARCHAR(255), display_name VARCHAR(255) DEFAULT '',
            is_client INT DEFAULT 0, is_vendor INT DEFAULT 0, is_subcontractor INT DEFAULT 0,
            client_type VARCHAR(40) DEFAULT '', industry VARCHAR(40) DEFAULT '',
            ownership_type VARCHAR(40) DEFAULT '', status VARCHAR(20) DEFAULT 'ACTIVE',
            parent_id INT NULL, gstin VARCHAR(20) DEFAULT '', pan VARCHAR(15) DEFAULT '',
            cin VARCHAR(30) DEFAULT '', tan VARCHAR(15) DEFAULT '', msme_udyam VARCHAR(40) DEFAULT '',
            state VARCHAR(60) DEFAULT '', website VARCHAR(255) DEFAULT '', description TEXT,
            created_at VARCHAR(30) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS partner_contacts (
            id $pk, partner_id INT, address_id INT NULL, name VARCHAR(150), designation VARCHAR(120) DEFAULT '',
            department VARCHAR(120) DEFAULT '', email VARCHAR(200) DEFAULT '',
            mobile VARCHAR(40) DEFAULT '', phone VARCHAR(40) DEFAULT '', is_primary INT DEFAULT 0)",
        "CREATE TABLE IF NOT EXISTS partner_addresses (
            id $pk, partner_id INT, address_type VARCHAR(30) DEFAULT 'REGISTERED',
            label VARCHAR(150) DEFAULT '', line1 VARCHAR(255) DEFAULT '', line2 VARCHAR(255) DEFAULT '',
            city VARCHAR(120) DEFAULT '', state VARCHAR(120) DEFAULT '', pincode VARCHAR(15) DEFAULT '',
            country VARCHAR(80) DEFAULT 'India', is_primary INT DEFAULT 0)",
        "CREATE TABLE IF NOT EXISTS partner_registrations (
            id $pk, partner_id INT, doc_type VARCHAR(20) DEFAULT 'GSTIN', number VARCHAR(60) DEFAULT '',
            valid_to VARCHAR(20) DEFAULT '', notes VARCHAR(200) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS partner_notes (
            id $pk, partner_id INT, note TEXT, author_name VARCHAR(150) DEFAULT '',
            created_at VARCHAR(30) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS partner_contracts (
            id $pk, partner_id INT, contract_number VARCHAR(64), title VARCHAR(200) DEFAULT '',
            value DECIMAL(16,2) NULL, start_date VARCHAR(20) DEFAULT '', end_date VARCHAR(20) DEFAULT '',
            is_active INT DEFAULT 1, notes VARCHAR(255) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS partner_purchase_orders (
            id $pk, partner_id INT, contract_id INT NULL, po_number VARCHAR(64) DEFAULT '',
            po_type VARCHAR(20) DEFAULT 'REGULAR', title VARCHAR(200) DEFAULT '', value DECIMAL(16,2) NULL,
            start_date VARCHAR(20) DEFAULT '', end_date VARCHAR(20) DEFAULT '', is_active INT DEFAULT 1,
            notes VARCHAR(255) DEFAULT '')",
        "CREATE TABLE IF NOT EXISTS po_line_items (
            id $pk, purchase_order_id INT, description VARCHAR(200), item_type VARCHAR(20) DEFAULT 'MANDAY',
            quantity DECIMAL(12,2) DEFAULT 0, rate DECIMAL(12,2) NULL, consumed DECIMAL(12,2) DEFAULT 0)",
        "CREATE TABLE IF NOT EXISTS partner_relationships (
            id $pk, partner_id INT, related_id INT, relation_type VARCHAR(20) DEFAULT 'OTHER',
            notes VARCHAR(255) DEFAULT '')",
    ];
    foreach ($tables as $sql) $pdo->exec($sql);
}

// Add a column to an existing table if it's missing (safe on upgrades).
function ensure_column($table, $col, $def) {
    $pdo = db();
    if (db_driver() === 'sqlite') {
        foreach ($pdo->query("PRAGMA table_info($table)")->fetchAll() as $c) {
            if ($c['name'] === $col) return;
        }
    } else {
        $q = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $q->execute([$col]);
        if ($q->fetch()) return;
    }
    try {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
    } catch (Throwable $e) {
        // Idempotent: if the column already exists (e.g. two requests both ran
        // boot() at once, or the check raced the add), that's fine — swallow the
        // "duplicate column" error (MySQL 1060 / SQLSTATE 42S21). Re-throw the rest.
        $m = $e->getMessage();
        if (stripos($m, 'duplicate column') === false && strpos($m, '1060') === false && strpos($m, '42S21') === false) throw $e;
    }
}

// Idempotent: create tables, apply column migrations.
function migrate() {
    ensure_schema();
    ensure_column('partner_contacts', 'address_id', 'INT NULL');
}

function ensure_admin() {
    $cfg = require __DIR__ . '/../config.php';
    $pdo = db();
    $u = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $u->execute([$cfg['admin']['user']]);
    $hash = password_hash($cfg['admin']['pass'], PASSWORD_DEFAULT);
    if ($row = $u->fetch()) {
        $pdo->prepare("UPDATE users SET password_hash=?, is_superuser=1, is_active=1, role='ADMIN' WHERE id=?")
            ->execute([$hash, $row['id']]);
    } else {
        $pdo->prepare("INSERT INTO users (username,password_hash,role,is_superuser,is_active,email)
            VALUES (?,?,?,1,1,?)")
            ->execute([$cfg['admin']['user'], $hash, 'ADMIN', 'admin@mghaiapps.com']);
    }
}

function auto_seed() {
    $pdo = db();
    // Seeded once, and remembered. Without the flag this ran again the moment
    // the table was empty — which is exactly the state right after somebody
    // deliberately cleared the clients and vendors, so they came straight back
    // and the delete looked as though it had not worked.
    try { if (ops_val("SELECT svalue FROM settings WHERE skey='partners_seeded'")) return; } catch (Throwable $e) {}
    $n = (int)$pdo->query("SELECT COUNT(*) FROM business_partners")->fetchColumn();
    if ($n > 0) { @setting_set('partners_seeded', '1'); return; }
    $file = __DIR__ . '/../data/seed_data.json';
    if (!is_file($file)) return;
    $data = json_decode(file_get_contents($file), true);
    $byNorm = [];
    $ins = $pdo->prepare("INSERT INTO business_partners (code,legal_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,?, 'ACTIVE', ?)");
    $findMax = function($token) use ($pdo) {
        $q = $pdo->prepare("SELECT code FROM business_partners WHERE code LIKE ? ORDER BY code DESC LIMIT 1");
        $q->execute(["GEN-$token-%"]);
        $last = $q->fetchColumn();
        return $last ? ((int)substr($last, strrpos($last, '-') + 1)) + 1 : 1;
    };
    $ensure = function($name, $isClient, $isVendor) use (&$byNorm, $pdo, $ins, $findMax) {
        $key = normalize_name($name);
        if (isset($byNorm[$key])) {
            $id = $byNorm[$key];
            $pdo->prepare("UPDATE business_partners SET is_client=is_client|?, is_vendor=is_vendor|? WHERE id=?")
                ->execute([$isClient, $isVendor, $id]);
            return;
        }
        $token = short_token($name);
        $seq = $findMax($token);
        $code = sprintf("GEN-%s-%04d", $token, $seq);
        $ins->execute([$code, substr($name, 0, 255), $isClient, $isVendor, date('c')]);
        $byNorm[$key] = $pdo->lastInsertId();
    };
    $pdo->beginTransaction();
    foreach (($data['clients'] ?? []) as $name) $ensure($name, 1, 0);
    foreach (($data['vendors'] ?? []) as $row) $ensure(is_array($row) ? $row['name'] : $row, 0, 1);
    $pdo->commit();
}

function boot() {
    migrate();
    if (function_exists('ops_migrate')) { ops_migrate(); ops_seed(); }
    if (function_exists('lk_migrate')) { lk_migrate(); lk_seed(); }
    if (function_exists('access_migrate')) access_migrate();
    if (function_exists('crm_migrate')) crm_migrate();   // after lookups exist (masters)
    if (function_exists('idems_migrate')) idems_migrate();   // IDEMS report engine
    if (function_exists('orgadmin_migrate')) orgadmin_migrate();   // office tree + heads
    if (function_exists('contracts_migrate')) contracts_migrate();  // contract validity gates
    if (function_exists('security_migrate')) security_migrate();    // password age, second factor
    if (function_exists('compliance_migrate')) compliance_migrate(); // incident register, consent, data requests
    if (function_exists('costing_migrate')) costing_migrate();       // salary + overhead allocation to Business Units
    if (function_exists('joblock_migrate')) joblock_migrate();       // close-on-time lock
    if (function_exists('po_migrate')) po_migrate();                 // an order remembers its quotation
    widen_file_columns();                                            // uploads need LONGTEXT, not MEDIUMTEXT
    if (function_exists('bills_migrate')) bills_migrate();             // chargeable expenses + their bills
    if (function_exists('competence_migrate')) competence_migrate();   // required certificates gate allocation
    if (function_exists('equipment_migrate')) equipment_migrate();     // measuring & test equipment, §6.2
    if (function_exists('competence_spine_migrate')) competence_spine_migrate();  // authorisation matrix, §6.1
    if (function_exists('impartiality_migrate')) impartiality_migrate();  // §4.1 threats & declarations
    if (function_exists('identity_migrate')) identity_migrate();       // passports & IDs, held under DPDP guardrails
    if (function_exists('complaints_migrate')) complaints_migrate();   // §7.5 complaints, §7.6 appeals
    if (function_exists('capa_migrate')) capa_migrate();               // §8.7 nonconformity & corrective action
    if (function_exists('audits_migrate')) audits_migrate();           // §8.8 internal audit, §8.9 management review
    if (function_exists('datacontrol_migrate')) datacontrol_migrate(); // §7.11 control of data & information (2026)
    if (function_exists('trust_migrate')) trust_migrate();             // evidence bound to place and time
    if (function_exists('portal_migrate')) portal_migrate();           // the client's own sign-in, its own table
    if (function_exists('sched_migrate')) sched_migrate();           // engagement shapes, holidays by office, visits
    // Register every remaining dropdown as an editable master list. Runs last:
    // it needs the base lists seeded and the CRM/IDEMS constants loaded.
    if (function_exists('lk_register_module_lists')) lk_register_module_lists();
    ensure_admin();
    auto_seed();
}
