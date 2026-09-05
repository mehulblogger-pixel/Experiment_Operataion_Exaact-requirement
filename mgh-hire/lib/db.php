<?php
// =========================================================================
//  MGH Hire — database connection, self-building schema, first-run seed.
//
//  There is no separate install step. The first time any page is opened the
//  app notices the database is empty and builds every table, the default
//  hiring pipeline and the administrator account. The same routine migrates
//  an existing database in one safe, repeatable pass.
// =========================================================================

function cfg() {
    static $c = null;
    if ($c === null) $c = require __DIR__ . '/../config.php';
    return $c;
}

function db_driver() { return cfg()['db']['driver']; }

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = cfg();
    $d = $c['db'];
    if ($d['driver'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['sqlite_path']);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4";
        $attempt = 0;
        while (true) {
            try { $pdo = new PDO($dsn, $d['user'], $d['pass']); break; }
            catch (PDOException $e) {
                $m = $e->getMessage();
                $transient = strpos($m,'1040')!==false || strpos($m,'2002')!==false || strpos($m,'1203')!==false;
                if (!$transient || ++$attempt >= 3) throw $e;
                usleep($attempt * 350000);
            }
        }
        try { $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch (Throwable $e) {}
        try { $pdo->exec("SET SESSION sql_mode = ''"); } catch (Throwable $e) {}
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function pk() {
    return db_driver() === 'sqlite'
        ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
        : 'INT AUTO_INCREMENT PRIMARY KEY';
}
// LONGTEXT on MySQL so a base64 attachment never silently truncates; TEXT on SQLite.
function longtext() { return db_driver() === 'sqlite' ? 'TEXT' : 'LONGTEXT'; }

// ---- Has the schema been built yet? --------------------------------------
function db_ready() {
    try {
        db()->query("SELECT 1 FROM settings LIMIT 1");
        return true;
    } catch (Throwable $e) { return false; }
}

// ---- Build + migrate the whole schema (safe to run repeatedly) -----------
function db_build() {
    $p  = db();
    $ID = pk();
    $LT = longtext();

    $p->exec("CREATE TABLE IF NOT EXISTS settings (
        skey VARCHAR(80) PRIMARY KEY,
        sval $LT
    )");

    $p->exec("CREATE TABLE IF NOT EXISTS users (
        id $ID,
        name VARCHAR(160) DEFAULT '',
        email VARCHAR(200) DEFAULT '',
        username VARCHAR(120) DEFAULT '',
        pass_hash VARCHAR(255) DEFAULT '',
        role VARCHAR(40) DEFAULT 'recruiter',
        active INT DEFAULT 1,
        created_at VARCHAR(30) DEFAULT ''
    )");

    // Configurable pipeline stages. gate marks a stage that needs a decision
    // (approve / reject) rather than a plain move.
    $p->exec("CREATE TABLE IF NOT EXISTS stages (
        id $ID,
        seq INT DEFAULT 0,
        name VARCHAR(120) DEFAULT '',
        kind VARCHAR(30) DEFAULT 'step',
        active INT DEFAULT 1
    )");

    // Staff Requisition Form (SRF) — a vacancy.
    $p->exec("CREATE TABLE IF NOT EXISTS requisitions (
        id $ID,
        code VARCHAR(40) DEFAULT '',
        title VARCHAR(200) DEFAULT '',
        department VARCHAR(160) DEFAULT '',
        location VARCHAR(160) DEFAULT '',
        positions INT DEFAULT 1,
        employment_type VARCHAR(60) DEFAULT 'Permanent',
        grade VARCHAR(80) DEFAULT '',
        org_verified INT DEFAULT 0,
        justification $LT,
        status VARCHAR(30) DEFAULT 'draft',
        raised_by VARCHAR(160) DEFAULT '',
        approved_by VARCHAR(160) DEFAULT '',
        created_at VARCHAR(30) DEFAULT ''
    )");

    // Candidates against a requisition.
    $p->exec("CREATE TABLE IF NOT EXISTS candidates (
        id $ID,
        code VARCHAR(40) DEFAULT '',
        requisition_id INT DEFAULT 0,
        name VARCHAR(200) DEFAULT '',
        email VARCHAR(200) DEFAULT '',
        phone VARCHAR(60) DEFAULT '',
        source VARCHAR(80) DEFAULT '',
        stage_id INT DEFAULT 0,
        status VARCHAR(30) DEFAULT 'active',
        current_ctc VARCHAR(60) DEFAULT '',
        expected_ctc VARCHAR(60) DEFAULT '',
        notice_period VARCHAR(60) DEFAULT '',
        cv_name VARCHAR(200) DEFAULT '',
        cv_data $LT,
        notes $LT,
        created_at VARCHAR(30) DEFAULT ''
    )");

    // The audit timeline — every stage move and decision.
    $p->exec("CREATE TABLE IF NOT EXISTS candidate_events (
        id $ID,
        candidate_id INT DEFAULT 0,
        from_stage INT DEFAULT 0,
        to_stage INT DEFAULT 0,
        action VARCHAR(60) DEFAULT '',
        decision VARCHAR(30) DEFAULT '',
        remarks $LT,
        actor VARCHAR(160) DEFAULT '',
        created_at VARCHAR(30) DEFAULT ''
    )");

    // Interviews (L1 / L2 / any round).
    $p->exec("CREATE TABLE IF NOT EXISTS interviews (
        id $ID,
        candidate_id INT DEFAULT 0,
        round VARCHAR(60) DEFAULT 'L1',
        scheduled_at VARCHAR(30) DEFAULT '',
        panel VARCHAR(240) DEFAULT '',
        mode VARCHAR(40) DEFAULT 'In person',
        result VARCHAR(30) DEFAULT '',
        rating INT DEFAULT 0,
        feedback $LT,
        created_at VARCHAR(30) DEFAULT ''
    )");

    // Documents collected from the candidate.
    $p->exec("CREATE TABLE IF NOT EXISTS documents (
        id $ID,
        candidate_id INT DEFAULT 0,
        doc_type VARCHAR(120) DEFAULT '',
        file_name VARCHAR(200) DEFAULT '',
        file_data $LT,
        uploaded_by VARCHAR(160) DEFAULT '',
        created_at VARCHAR(30) DEFAULT ''
    )");

    // Offer + salary structure.
    $p->exec("CREATE TABLE IF NOT EXISTS offers (
        id $ID,
        candidate_id INT DEFAULT 0,
        ctc VARCHAR(60) DEFAULT '',
        components $LT,
        joining_date VARCHAR(30) DEFAULT '',
        status VARCHAR(30) DEFAULT 'draft',
        issued_at VARCHAR(30) DEFAULT '',
        accepted_at VARCHAR(30) DEFAULT '',
        created_at VARCHAR(30) DEFAULT ''
    )");

    db_seed();
}

// ---- First-run seed: admin, default settings, default pipeline -----------
function db_seed() {
    $p = db();

    // Administrator (from config), only if there are no users yet.
    $n = (int)$p->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
    if ($n === 0) {
        $a = cfg()['admin'];
        $p->prepare("INSERT INTO users (name,email,username,pass_hash,role,active,created_at)
                     VALUES (?,?,?,?,?,?,?)")
          ->execute([
              $a['name'] ?? 'Administrator', '', $a['user'] ?? 'admin',
              password_hash($a['pass'] ?? 'admin12345', PASSWORD_DEFAULT),
              'admin', 1, date('c'),
          ]);
    }

    // Default branding / settings.
    $defaults = [
        'product_name' => 'MGH Hire',
        'company_name' => '',
        'brand_color'  => '#4f46e5',
        'accent_color' => '#0ea5e9',
        'logo_data'    => '',
        'currency'     => '₹',
        'seq_req'      => '0',
        'seq_cand'     => '0',
    ];
    foreach ($defaults as $k => $v) setting_default($k, $v);

    // Default pipeline — the client's approved Recruitment & Selection flow,
    // shipped as an editable template. 'gate' = a decision point.
    $c = (int)$p->query("SELECT COUNT(*) c FROM stages")->fetch()['c'];
    if ($c === 0) {
        $stages = [
            ['Requisition Approved', 'gate'],
            ['Organogram Verified',  'gate'],
            ['Sourcing',             'step'],
            ['CV Screening',         'gate'],
            ['HOD Shortlisting',     'gate'],
            ['L1 Interview',         'interview'],
            ['L2 Interview',         'interview'],
            ['Document Collection',  'step'],
            ['Salary Structure',     'step'],
            ['HR Discussion',        'step'],
            ['Candidate Approval',   'gate'],
            ['Medical Examination',  'step'],
            ['Reference Verification','step'],
            ['Medical Fitness Clearance','gate'],
            ['One-Pager Approval',   'gate'],
            ['Offer Released',       'offer'],
            ['Offer Accepted',       'gate'],
            ['Onboarding',           'terminal'],
        ];
        $seq = 10;
        $ins = $p->prepare("INSERT INTO stages (seq,name,kind,active) VALUES (?,?,?,1)");
        foreach ($stages as $s) { $ins->execute([$seq, $s[0], $s[1]]); $seq += 10; }
    }
}

// =========================================================================
//  Settings helpers
// =========================================================================
function setting($key, $fallback = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query("SELECT skey,sval FROM settings")->fetchAll() as $r)
            $cache[$r['skey']] = $r['sval'];
    }
    return $cache[$key] ?? $fallback;
}
function setting_default($key, $val) {
    $r = db()->prepare("SELECT 1 FROM settings WHERE skey=?");
    $r->execute([$key]);
    if (!$r->fetch()) db()->prepare("INSERT INTO settings (skey,sval) VALUES (?,?)")->execute([$key,$val]);
}
function setting_set($key, $val) {
    if (db_driver() === 'sqlite') {
        db()->prepare("INSERT INTO settings (skey,sval) VALUES (?,?)
                       ON CONFLICT(skey) DO UPDATE SET sval=excluded.sval")->execute([$key,$val]);
    } else {
        db()->prepare("INSERT INTO settings (skey,sval) VALUES (?,?)
                       ON DUPLICATE KEY UPDATE sval=VALUES(sval)")->execute([$key,$val]);
    }
}

// Next code in a per-object sequence, e.g. SRF-0007 / CAN-0031.
function next_code($prefix, $seq_key) {
    $n = (int)setting($seq_key, '0') + 1;
    setting_set($seq_key, (string)$n);
    return $prefix . '-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}
