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
        // Every CREATE TABLE in this application omits a charset, so each table
        // takes the DATABASE default. On a host where that default is latin1 —
        // still common on cPanel — a rupee sign, a curly quote or any non-Latin
        // name is silently replaced with "?" as it is written, and no amount of
        // reading it back correctly will recover it. This makes new tables land
        // as utf8mb4 whatever the database default says.
        //
        // A SHARED MySQL SERVER MOMENTARILY OUT OF SLOTS SHOULD NOT BE AN ERROR
        // PAGE. "Too many connections" (1040) and "can't connect" (2002) on a
        // crowded host clear within a second, so we wait a beat and try again
        // rather than turning a half-second spike into a dead screen. Crucially
        // the wait happens BEFORE we hold any connection, so retrying costs the
        // shared server nothing. A real fault — wrong password (1045), unknown
        // database (1049) — is not transient and is thrown at once, unretried.
        $attempt = 0;
        while (true) {
            try { $pdo = new PDO($dsn, $d['user'], $d['pass']); break; }
            catch (PDOException $e) {
                $m = $e->getMessage();
                $transient = strpos($m, '1040') !== false || strpos($m, '2002') !== false || strpos($m, '1203') !== false;
                if (!$transient || ++$attempt >= 3) throw $e;
                usleep($attempt * 350000);   // 0.35s, then 0.70s — no DB slot held meanwhile
            }
        }
        // Belt to the DSN's braces, and the part that actually fixes new tables:
        // set the session's default charset so CREATE TABLE without an explicit
        // charset inherits utf8mb4 rather than whatever the database was made
        // with. Wrapped because a locked-down host may refuse SET, and a refused
        // charset must not stop the application from starting.
        try { $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch (Throwable $e) {}
        // The whole application — schema, queries and the demo seed — is authored
        // against SQLite, which is typeless and lenient: an empty string '' is a
        // fine stand-in for a blank date or number. Modern MySQL/MariaDB defaults
        // to STRICT_TRANS_TABLES + NO_ZERO_DATE, which REJECT those rows outright,
        // so a seed block (or a real form save) that stores '' in a DATE/INT column
        // fails on production MySQL though it worked on the dev sandbox — the demo
        // "coverage" board showed exactly this (a handful of modules loading empty).
        // Relax the session to match the engine the code was written for. This is
        // a per-connection setting, so it changes nothing on the server itself, and
        // it is wrapped because a locked-down host may refuse SET.
        try { $pdo->exec("SET SESSION sql_mode = ''"); } catch (Throwable $e) {}
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

// The existing columns of a table, as a name => true map. Lets a dynamic
// INSERT/UPDATE skip a column that is missing on a partially-uploaded install
// (some files new, some old) instead of crashing the whole save with "Unknown
// column". The column is added on the next boot() migrate, so this is only a
// safety net for the brief window of a piecemeal upload.
function table_columns($table) {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $pdo = db(); $out = [];
    try {
        if (db_driver() === 'sqlite') {
            foreach ($pdo->query("PRAGMA table_info(`$table`)")->fetchAll() as $c) $out[(string)$c['name']] = true;
        } else {
            foreach ($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll() as $c) $out[(string)$c['Field']] = true;
        }
    } catch (Throwable $e) { /* unknown table → empty map, caller falls back */ }
    return $cache[$table] = $out;
}

// Keep only the field names that really exist as columns on $table. Returns the
// list unchanged when introspection finds nothing (so a mis-detected table never
// blanks a save). Use for dynamic column lists on partially-upgraded installs.
function existing_columns_only($table, array $fields) {
    $have = table_columns($table);
    if (!$have) return $fields;
    return array_values(array_filter($fields, fn($f) => isset($have[$f])));
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
        // Keep the admin a superuser and active, but do NOT overwrite the
        // password here. This used to re-hash the config password on every
        // boot, which meant an unrelated code upload could silently reset a
        // password the admin had chosen in the app. Applying the config
        // password is now a deliberate, change-detected step in
        // admin_sync_from_config(), so it happens when — and only when — the
        // config password actually changes.
        $pdo->prepare("UPDATE users SET is_superuser=1, is_active=1, role='ADMIN' WHERE id=?")
            ->execute([$row['id']]);
    } else {
        $pdo->prepare("INSERT INTO users (username,password_hash,role,is_superuser,is_active,email)
            VALUES (?,?,?,1,1,?)")
            ->execute([$cfg['admin']['user'], $hash, 'ADMIN', 'admin@mghaiapps.com']);
    }
}

// Apply the admin password from config.php the moment it CHANGES — reliably,
// and without the heavy schema probe. The recurring "config password won't let
// me in" came from ensure_admin() running only when the code fingerprint moved:
// edit the password in config.php and, if no code file's size or timestamp
// happened to change, nothing applied it and the old password stayed live.
// This runs on every request but does real work only when the config
// credentials differ from what was last applied (otherwise a single indexed
// settings read). It never touches a password changed inside the app, because
// that leaves the config signature unchanged.
function admin_sync_from_config() {
    try {
        $cfg  = require __DIR__ . '/../config.php';
        $user = (string) ($cfg['admin']['user'] ?? 'admin');
        $pass = (string) ($cfg['admin']['pass'] ?? '');
        if ($user === '' || $pass === '') return;
        $sig  = md5($user . "\x00" . $pass);
        $seen = null;
        try { $seen = ops_val("SELECT svalue FROM settings WHERE skey='admin_cfg_sig'"); }
        catch (Throwable $e) { return; }             // no settings table yet — boot() will handle it
        if ($seen === $sig) return;                  // config password unchanged since last applied
        $pdo  = db();
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $q = $pdo->prepare("SELECT id FROM users WHERE username=?");
        $q->execute([$user]);
        $id = $q->fetchColumn();
        if ($id) {
            $pdo->prepare("UPDATE users SET password_hash=?, is_superuser=1, is_active=1, role='ADMIN' WHERE id=?")->execute([$hash, $id]);
            try { $pdo->prepare("UPDATE users SET must_change_pwd=0 WHERE id=?")->execute([$id]); } catch (Throwable $e) {}
        } else {
            $pdo->prepare("INSERT INTO users (username,password_hash,role,is_superuser,is_active,email) VALUES (?,?, 'ADMIN', 1,1,?)")
                ->execute([$user, $hash, 'admin@mghaiapps.com']);
        }
        @setting_set('admin_cfg_sig', $sig);
        if (function_exists('idems_log')) idems_log('user', $id ?: null, 'ADMIN_CONFIG_SYNC', ['field' => $user]);
    } catch (Throwable $e) { /* never let this break the page */ }
}

// One-shot admin recovery from a file — for a non-technical admin who is locked
// out and cannot run a command. They create "reset-admin.txt" in the app folder
// (cPanel File Manager, point and click) with the new password on the first
// line; the next page load reads it, sets the password, and DELETES the file.
// Requires file access to the server, which is the same trust level as holding
// config.php, so it needs no in-app permission and shows nothing in the browser.
function admin_recovery_from_file() {
    $file = __DIR__ . '/../reset-admin.txt';
    if (!is_file($file)) return;                     // the common case: cheap and silent
    $new = trim((string) @file_get_contents($file));
    @unlink($file);                                  // gone whether or not the reset succeeds
    try {
        $cfg  = require __DIR__ . '/../config.php';
        $user = (string) ($cfg['admin']['user'] ?? 'admin');
        if ($new === '') $new = (string) ($cfg['admin']['pass'] ?? '');   // empty file = reset to config
        if ($new === '') return;
        $pdo  = db();
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $row  = $pdo->prepare("SELECT id FROM users WHERE username=?");
        $row->execute([$user]);
        if ($id = $row->fetchColumn()) {
            $pdo->prepare("UPDATE users SET password_hash=?, is_superuser=1, is_active=1 WHERE id=?")->execute([$hash, $id]);
            try { $pdo->prepare("UPDATE users SET must_change_pwd=0, pwd_changed_at=? WHERE id=?")->execute([date('c'), $id]); } catch (Throwable $e) {}
        } else {
            $pdo->prepare("INSERT INTO users (username,password_hash,role,is_superuser,is_active,email) VALUES (?,?, 'ADMIN', 1,1,?)")
                ->execute([$user, $hash, 'admin@example.com']);
        }
        if (function_exists('idems_log')) idems_log('user', null, 'ADMIN_RECOVERY_FILE', ['field' => $user]);
    } catch (Throwable $e) { /* never let recovery break the page */ }
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

// The whole schema build/upgrade, in one ordered pass. $withSeeds distinguishes
// the two callers that share this exact sequence:
//   boot()        — fresh install / full run: schema DDL *and* the seed data
//                   (master lists, expense heads, module dropdowns, demo rows).
//   migrate_all() — a code upgrade on an existing database: run every schema
//                   migration so any newly added table/column is created, but
//                   NEVER re-seed (re-seeding could resurrect master data an
//                   admin has deliberately cleared). The migrations are all
//                   idempotent DDL (ensure_column / ensure_table), so this is
//                   safe to run on every deploy.
// Keeping ONE sequence means a migration added in future is picked up by both
// paths automatically — the blind spot that left `team_role` unmigrated cannot
// recur.
function run_schema($withSeeds = true) {
    migrate();
    if (function_exists('ops_migrate')) { ops_migrate(); if ($withSeeds) ops_seed(); }
    if (function_exists('lk_migrate')) { lk_migrate(); if ($withSeeds) lk_seed(); }
    if (function_exists('access_migrate')) access_migrate();
    if (function_exists('crm_migrate')) crm_migrate();   // after lookups exist (masters)
    if (function_exists('idems_migrate')) idems_migrate();   // IDEMS report engine
    if (function_exists('urfe_migrate')) urfe_migrate();     // URFE — universal report foundation (shared field/section library, metadata)
    if (function_exists('uire_migrate')) uire_migrate();     // UIRE — universal inspection engine (inspection-type master + criteria library)
    if (function_exists('urade_migrate')) urade_migrate();   // URADE — universal release/acceptance engine (eligibility rules + release masters)
    if (function_exists('uvae_migrate')) uvae_migrate();     // UVAE — universal vendor assessment engine (assessment-type master + criteria library)
    if (function_exists('uvaae_migrate')) uvaae_migrate();   // UVAAE — universal vendor audit / MS-audit engine (audit-type master + audit criteria library + conclusion rules)
    if (function_exists('services_migrate')) services_migrate(); // Service Scope engine — service independence, activation & override (catalog + per-scope + optional dependencies)
    if (function_exists('pdso_migrate')) pdso_migrate();     // PDSO — project deputation & site operations (gap-filler on the existing deputation/job spine: lifecycle, mobilization, manpower, site registers, timesheet, client approval, deputation reports)
    if (function_exists('ncdca_migrate')) ncdca_migrate();   // NCDCA — universal issue engine (elevates NCR+CAPA: issue types, deviation/concession/waiver, dispute, due-date extension, issue reports)
    if (function_exists('attend_migrate')) attend_migrate();
    if (function_exists('cvp_migrate')) cvp_migrate();       // CVP Phase 10 — Client & Vendor Portal (confidentiality spine + vendor portal + external issue loop + notifications + governance)
    if (function_exists('tapi_migrate')) tapi_migrate();     // TAPI Phase 11 — Analytics & Performance Intelligence (KPI master + safe formula engine + metric registry)
    if (function_exists('tapi_score_migrate')) tapi_score_migrate();  // TAPI targets + management scorecard + threshold alerts
    if (function_exists('tapi_gov_migrate')) tapi_gov_migrate();      // TAPI KPI versioning + period snapshots/closure + Phase-12 metadata
    if (function_exists('tosrm_migrate_d')) tosrm_migrate_d(); // TOSRM Phase 9 — service-request lifecycle + assignment lifecycle + readiness/confirmation/competence + SLA/TAT/delay/recurring/capacity (chains A→B→C→D)
    elseif (function_exists('tosrm_migrate')) tosrm_migrate();
    if (function_exists('tosrm_xo_migrate')) tosrm_xo_migrate();  // TOSRM exception tools (folded into the request lifecycle)
    if (function_exists('pc_migrate')) pc_migrate();              // project costing header + role/cost-head lines
    if (function_exists('orgadmin_migrate')) orgadmin_migrate();   // office tree + heads
    if (function_exists('contracts_migrate')) contracts_migrate();  // contract validity gates
    if (function_exists('security_migrate')) security_migrate();    // password age, second factor
    if (function_exists('compliance_migrate')) compliance_migrate(); // incident register, consent, data requests
    if (function_exists('costing_migrate')) costing_migrate();       // salary + overhead allocation to Business Units
    if (function_exists('joblock_migrate')) joblock_migrate();       // close-on-time lock
    if (function_exists('po_migrate')) po_migrate();                 // an order remembers its quotation
    widen_file_columns();                                            // uploads need LONGTEXT, not MEDIUMTEXT
    if (function_exists('bills_migrate')) bills_migrate();             // chargeable expenses + their bills
    if (function_exists('billable_migrate')) billable_migrate();       // Revamp P4 — Billable Event ledger (operational→commercial bridge)
    if (function_exists('connect_taxonomy_migrate')) { connect_taxonomy_migrate(); connect_taxonomy_seed(); }  // Connect K0 — marketplace industry taxonomy (idempotent, insert-if-empty)
    if (function_exists('connect_qualtax_migrate')) { connect_qualtax_migrate(); connect_qualtax_seed(); }     // Connect K13 / #2 — qualification & role taxonomy (ITI→MBA ladder; idempotent, insert-if-empty)
    if (function_exists('connect_tax_graph_migrate')) { connect_tax_graph_migrate(); if (function_exists('connect_tax_generalize')) connect_tax_generalize(); }  // Connect K0+ — build the unified taxonomy graph from the flat masters (idempotent, marker-guarded)
    if (function_exists('connect_geo_migrate')) { connect_geo_migrate(); if (function_exists('connect_geo_seed')) connect_geo_seed(); }  // Connect K-GEO — structured location master + mobility columns (idempotent)
    if (function_exists('connect_cred_migrate')) connect_cred_migrate();  // Connect K0+ — passport certifications & project experience (idempotent)
    if (function_exists('connect_passport_migrate')) connect_passport_migrate();  // Connect K1 — inspectors.passport_token (public passport share key)
    if (function_exists('connect_market_migrate')) connect_market_migrate();      // Connect K2a — cx_requirements + cx_applications (marketplace)
    if (function_exists('connect_ratings_migrate')) connect_ratings_migrate();    // Connect K9 — cx_ratings (two-way marketplace ratings)
    if (function_exists('connect_disputes_migrate')) connect_disputes_migrate();  // Connect K9b — cx_disputes (marketplace disputes)
    if (function_exists('connect_govern_migrate')) connect_govern_migrate();      // Connect K10 — cx_terms + cx_readiness (Part-F F1/F3)
    if (function_exists('connect_pro_migrate')) connect_pro_migrate();            // Connect A1 — cx_professionals (self-registered freelancer pool)
    if (function_exists('connect_qualtax_augment_professional')) connect_qualtax_augment_professional();  // #2 — add ITI→MBA profile columns once cx_professionals exists
    if (function_exists('connect_geo_augment_professional')) connect_geo_augment_professional();  // Connect K-GEO — structured base/mobility columns on cx_professionals (after the table exists)
    if (function_exists('connect_verify_migrate')) connect_verify_migrate();                              // Connect K14 / #3 — cx_verifications + verified_at (after cx_professionals exists)
    if (function_exists('connect_privacy_migrate')) connect_privacy_migrate();                            // Connect K0+ — professional privacy columns + contact-reveal grants (after cx_professionals)
    if (function_exists('connect_hiring_migrate')) connect_hiring_migrate();                              // Connect K0+ — client saved-search table for the hiring home
    if (function_exists('connect_identity_migrate')) connect_identity_migrate();                          // Connect K0+ — professional↔inspector identity link ledger (after both person tables)
    if (function_exists('connect_client_bench_migrate')) connect_client_bench_migrate();                  // Connect K0+ — client private bench / roster (demand-side)
    if (function_exists('connect_deploy_migrate')) connect_deploy_migrate();                              // Connect K0+ — award→deployment bridge columns on jobs (after jobs + pdso)
    if (function_exists('connect_reqtemplates_migrate')) connect_reqtemplates_migrate();                  // Connect K0+ — saved requirement templates (client reuse)
    if (function_exists('connect_msg_migrate')) connect_msg_migrate();                                    // Connect K15 / #4 — cx_messages + cx_message_reads (in-app messaging)
    if (function_exists('connect_channels_migrate')) { connect_channels_migrate(); connect_channels_seed(); } // Connect K16 / #5 — channel templates + outbound log + consent (after cx_professionals)
    if (function_exists('connect_bench_migrate')) connect_bench_migrate();                                    // Connect K18 / #7 — cx_bench + cx_bench_alloc (agency private roster)
    if (function_exists('connect_engage_migrate')) connect_engage_migrate();                                  // Connect K20 — cx_engagements (booking basis on award)
    if (function_exists('connect_engv_migrate')) connect_engv_migrate();                                      // Connect K21 — cx_engagement_vouchers + lines (inclusive/exclusive claims)
    if (function_exists('connect_crew_migrate')) connect_crew_migrate();          // Connect M10 — cx_positions (crew manifest)
    if (function_exists('connect_org_migrate')) connect_org_migrate();            // Connect B0 — cx_organisations (org accounts + entitlements)
    if (function_exists('connect_cap_migrate')) connect_cap_migrate();            // Connect — cx_org_capabilities (multi-select company capabilities)
    if (function_exists('mkt_plans_migrate')) mkt_plans_migrate();                // Marketplace subscription plans & limits (Super-Admin owned)
    if (function_exists('mkt_subs_migrate')) mkt_subs_migrate();                  // Marketplace subscriptions, access & usage
    if (function_exists('mkt_credits_migrate')) mkt_credits_migrate();            // Marketplace credit packs (top-ups when limits exhaust)
    if (function_exists('mkt_pay_migrate')) mkt_pay_migrate();                    // Marketplace payment capture (Razorpay orders)
    if (function_exists('pdso_gate_migrate')) pdso_gate_migrate();                // Stage 7 — dep_gate_pass (mobilization gate pass)
    if (function_exists('books_migrate')) books_migrate();             // ensure the books ledger tables exist before anything stamps them
    if (function_exists('engagement_migrate')) engagement_migrate();   // Revamp — first-class Engagement entity (additive; dual-read with contract_number)
    if (function_exists('competence_migrate')) competence_migrate();   // required certificates gate allocation
    if (function_exists('equipment_migrate')) equipment_migrate();     // measuring & test equipment, §6.2
    if (function_exists('samples_migrate')) samples_migrate();         // §7.2 inspection items & samples
    if (function_exists('methods_migrate')) methods_migrate();         // §7.1 controlled method & standard library
    if (function_exists('drules_migrate')) drules_migrate();           // §7.4 decision rules & acceptance criteria
    if (function_exists('cdocs_migrate')) cdocs_migrate();             // §8.3 controlled documents
    if (function_exists('risks_migrate')) risks_migrate();             // §8.5 risks & opportunities
    if (function_exists('retention_migrate')) retention_migrate();     // §8.4 record-retention schedule
    if (function_exists('disclosure_migrate')) disclosure_migrate();   // §4.2 public-disclosure consent
    if (function_exists('sat_migrate')) sat_migrate();                 // ISO 9001 §9.1.2 customer satisfaction
    if (function_exists('competence_spine_migrate')) competence_spine_migrate();
    if (function_exists('competence_cycle_migrate')) competence_cycle_migrate();  // basis, review cycle, witnessing interval  // authorisation matrix, §6.1
    if (function_exists('impartiality_migrate')) impartiality_migrate();  // §4.1 threats & declarations
    if (function_exists('identity_migrate')) identity_migrate();
    if (function_exists('assets_migrate')) assets_migrate();           // kit issued to engineers — stamps, safety gear, devices
    if (function_exists('sitedoc_migrate')) sitedoc_migrate();         // what a site demands before the gate opens       // passports & IDs, held under DPDP guardrails
    if (function_exists('complaints_migrate')) complaints_migrate();   // §7.5 complaints, §7.6 appeals
    if (function_exists('capa_migrate')) capa_migrate();
    if (function_exists('ncr_migrate')) ncr_migrate();
    if (function_exists('hwp_migrate')) hwp_migrate();                 // hold / witness points — first-class intervention points
    if (function_exists('conf_migrate')) conf_migrate();
    if (function_exists('act_migrate')) act_migrate();
    if (function_exists('leads_migrate')) leads_migrate();               // leads, pipelines, configurable stages                   // the activity spine — Customer 360 reads this                // §4.2 undertakings, client NDAs, breaches                 // the event, before the corrective action               // §8.7 nonconformity & corrective action
    if (function_exists('opp_migrate')) opp_migrate();                 // opportunities — the deal, distinct from the quotation
    if (function_exists('books_migrate')) books_migrate();             // invoices, receipts, allocations, credit notes
    if (function_exists('booksbridge_migrate')) booksbridge_migrate(); // MGH Books connector: outbox + link columns
    if (function_exists('licissue_migrate')) licissue_migrate();       // Super Admin licence console: issued_licences
    if (function_exists('licbeat_migrate')) licbeat_migrate();         // self-hosted install heartbeats
    if (function_exists('cforms_migrate')) cforms_migrate();           // no-code custom forms + custom_records
    if (function_exists('billing_migrate')) billing_migrate();         // SaaS billing/subscriptions
    if (function_exists('form_tokens_migrate')) form_tokens_migrate(); // CSRF form tokens
    if (function_exists('login_attempts_migrate')) login_attempts_migrate(); // brute-force throttle
    if (function_exists('geofence_migrate')) geofence_migrate();       // site coordinates on party + job, geofenced attendance
    if (function_exists('dt_migrate')) dt_migrate();                   // per-user register settings (columns, page size)
    if (function_exists('gate_migrate')) gate_migrate();               // approval rules standing between a deal and a stage
    if (function_exists('ads_migrate')) ads_migrate();                 // Ads Pro link: imported leads, cached spend, sync log
    if (function_exists('ads_sync_migrate')) ads_sync_migrate();       // the outbox, and the link columns two-way sync needs
    if (function_exists('sso_migrate')) sso_migrate();                 // single sign-on handoffs, accepted and refused
    if (function_exists('audits_migrate')) audits_migrate();           // §8.8 internal audit, §8.9 management review
    if (function_exists('datacontrol_migrate')) datacontrol_migrate(); // §7.11 control of data & information (2026)
    if (function_exists('trust_migrate')) trust_migrate();             // evidence bound to place and time
    if (function_exists('portal_migrate')) portal_migrate();
    if (function_exists('rcr_migrate')) rcr_migrate();                   // the client's answer to an issued report           // the client's own sign-in, its own table
    if (function_exists('sched_migrate')) sched_migrate();           // engagement shapes, holidays by office, visits
    if (function_exists('req_groups_migrate')) req_groups_migrate();  // 1c — requisition deployment groups (headcount + reporting contact + site)
    if (function_exists('tally_migrate')) tally_migrate();             // what has already been handed to Tally
    if (function_exists('tasks_migrate')) tasks_migrate();             // Phase 3 §26 — persisted user tasks
    if (function_exists('webhookq_migrate')) webhookq_migrate();       // Phase 3 §50 — generic integration queue
    // Register every remaining dropdown as an editable master list. Runs last:
    // it needs the base lists seeded and the CRM/IDEMS constants loaded.
    if ($withSeeds && function_exists('lk_register_module_lists')) lk_register_module_lists();
    // Secondary indexes, last of all: every table it references now exists.
    if (function_exists('indexes_migrate')) indexes_migrate();
    ensure_admin();
    if ($withSeeds) auto_seed();
}

// Fresh install / full run — schema + seed data.
function boot() { run_schema(true); }

// Code upgrade on an existing database — schema DDL only, never re-seed.
function migrate_all() { run_schema(false); }
