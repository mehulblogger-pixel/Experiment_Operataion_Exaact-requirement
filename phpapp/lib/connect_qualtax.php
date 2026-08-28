<?php
// ============================================================================
//  CONNECT — Qualification & Role Taxonomy  (slice K13 / backlog #2, additive)
//
//  The layered TALENT ontology for the ITSN marketplace — the answer to the
//  motto "a single platform for everyone from ITI to Engineers to MBA". It sits
//  BESIDE the K0 industry taxonomy (sector/equipment/discipline) and generalises
//  the pool beyond inspection:
//
//     job family  →  role  →  qualification ladder  →  ITI trade  →  prof. cert.
//
//  Inspection is kept as ONE vertical (job family INSP), not the whole world.
//  The ladder is anchored on India's NSQF (National Skills Qualifications
//  Framework), so ITI, apprenticeship, diploma, degree, PG/MBA and doctorate all
//  sit on one comparable scale.
//
//  ADDITIVE CONTRACT (identical discipline to K0):
//   - New tables only, all `cx_` namespaced; nothing existing is touched.
//   - Idempotent CREATE TABLE IF NOT EXISTS + insert-if-empty seed, so boot /
//     cron / test re-runs are no-ops and never clobber admin edits.
//   - Master data lives in data/connect_qualifications.json, not in code.
//   - Read-only screen; no new route replaces an old one, no new permission,
//     no new object status. It reads and displays only.
// ============================================================================

/** The qualification-taxonomy master tables — additive, `cx_` namespaced. */
function connect_qualtax_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    db()->exec("CREATE TABLE IF NOT EXISTS cx_qualification_levels (
        id $pk, code VARCHAR(40) DEFAULT '', band VARCHAR(30) DEFAULT '',
        nsqf_level INT DEFAULT 0, name VARCHAR(200) DEFAULT '',
        detail VARCHAR(500) DEFAULT '', sort_order INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_job_families (
        id $pk, code VARCHAR(40) DEFAULT '', name VARCHAR(200) DEFAULT '',
        detail VARCHAR(500) DEFAULT '', nsqf_min INT DEFAULT 0, nsqf_max INT DEFAULT 0,
        sort_order INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_roles (
        id $pk, code VARCHAR(40) DEFAULT '', family_code VARCHAR(40) DEFAULT '',
        name VARCHAR(200) DEFAULT '', aka VARCHAR(300) DEFAULT '',
        min_qual_band VARCHAR(30) DEFAULT '', typical_certs VARCHAR(400) DEFAULT '',
        sort_order INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_iti_trades (
        id $pk, code VARCHAR(40) DEFAULT '', name VARCHAR(200) DEFAULT '',
        category VARCHAR(60) DEFAULT '', duration VARCHAR(40) DEFAULT '', sort_order INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_prof_certifications (
        id $pk, code VARCHAR(60) DEFAULT '', name VARCHAR(240) DEFAULT '',
        body VARCHAR(120) DEFAULT '', domain VARCHAR(160) DEFAULT '', sort_order INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_qualtax_versions (
        id $pk, version VARCHAR(20) DEFAULT '', source VARCHAR(300) DEFAULT '',
        note VARCHAR(800) DEFAULT '', imported_at VARCHAR(30) DEFAULT '')");

    connect_qualtax_augment_professional();
}

/**
 * Additive profile capture on the self-registered professional (A1) — the columns
 * that let a person state where they sit on the ladder. All optional, all default
 * empty, so existing rows and the register/edit flow are unchanged. Guarded so it
 * is a harmless no-op when the professional table is not yet created (boot order):
 * boot calls it again after connect_pro_migrate, when the table exists.
 */
function connect_qualtax_augment_professional() {
    if (!function_exists('ensure_column')) return;
    try {
        if ((int)db()->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='cx_professionals'")->fetchColumn() === 0
            && stripos((string)(defined('DB_DRIVER') ? DB_DRIVER : ($GLOBALS['DB_DRIVER'] ?? '')), 'sqlite') !== false) {
            return; // table not there yet under SQLite — added on the post-pro-migrate pass
        }
    } catch (Throwable $e) { /* non-sqlite: fall through and let ensure_column probe */ }
    try {
        ensure_column('cx_professionals', 'job_family_code', "VARCHAR(40) DEFAULT ''");
        ensure_column('cx_professionals', 'role_code',       "VARCHAR(40) DEFAULT ''");
        ensure_column('cx_professionals', 'qual_level_code', "VARCHAR(40) DEFAULT ''");
        ensure_column('cx_professionals', 'iti_trade_code',  "VARCHAR(40) DEFAULT ''");
        ensure_column('cx_professionals', 'cert_codes',      "VARCHAR(400) DEFAULT ''");
        ensure_column('cx_professionals', 'years_experience', 'INT DEFAULT 0');
    } catch (Throwable $e) { /* table appears later in boot; augmented on the next pass */ }
}

/** The seed file path (bundled data asset). */
function connect_qualtax_file() {
    return __DIR__ . '/../data/connect_qualifications.json';
}

/** Decode the seed file once; [] if missing/broken (never fatal). */
function connect_qualtax_data() {
    static $cache = null; if ($cache !== null) return $cache;
    $f = connect_qualtax_file();
    if (!is_file($f)) return $cache = [];
    $j = json_decode((string)@file_get_contents($f), true);
    return $cache = (is_array($j) ? $j : []);
}

/** True when a table already holds rows (seed is insert-if-empty, idempotent). */
function connect_qtx_has_rows($table) {
    try { return (int)db()->query("SELECT COUNT(*) FROM $table")->fetchColumn() > 0; }
    catch (Throwable $e) { return false; }
}

/**
 * Import the master data. Idempotent: each table is filled only when empty, so a
 * re-run (boot, cron, test) is a no-op and never duplicates or clobbers an admin
 * edit made later.
 */
function connect_qualtax_seed() {
    connect_qualtax_migrate();
    $d = connect_qualtax_data();
    if (!$d) return;
    $pdo = db();

    if (!connect_qtx_has_rows('cx_qualification_levels') && !empty($d['qualification_levels'])) {
        $st = $pdo->prepare("INSERT INTO cx_qualification_levels (code,band,nsqf_level,name,detail,sort_order) VALUES (?,?,?,?,?,?)");
        $i = 0; foreach ($d['qualification_levels'] as $r)
            $st->execute([$r['code'] ?? '', $r['band'] ?? '', (int)($r['nsqf_level'] ?? 0), $r['name'] ?? '', $r['detail'] ?? '', ++$i]);
    }
    if (!connect_qtx_has_rows('cx_job_families') && !empty($d['job_families'])) {
        $st = $pdo->prepare("INSERT INTO cx_job_families (code,name,detail,nsqf_min,nsqf_max,sort_order) VALUES (?,?,?,?,?,?)");
        $i = 0; foreach ($d['job_families'] as $r)
            $st->execute([$r['code'] ?? '', $r['name'] ?? '', $r['detail'] ?? '', (int)($r['nsqf_min'] ?? 0), (int)($r['nsqf_max'] ?? 0), ++$i]);
    }
    if (!connect_qtx_has_rows('cx_roles') && !empty($d['roles'])) {
        $st = $pdo->prepare("INSERT INTO cx_roles (code,family_code,name,aka,min_qual_band,typical_certs,sort_order) VALUES (?,?,?,?,?,?,?)");
        $i = 0; foreach ($d['roles'] as $r)
            $st->execute([$r['code'] ?? '', $r['family'] ?? '', $r['name'] ?? '', $r['aka'] ?? '', $r['min_qual_band'] ?? '', $r['typical_certs'] ?? '', ++$i]);
    }
    if (!connect_qtx_has_rows('cx_iti_trades') && !empty($d['iti_trades'])) {
        $st = $pdo->prepare("INSERT INTO cx_iti_trades (code,name,category,duration,sort_order) VALUES (?,?,?,?,?)");
        $i = 0; foreach ($d['iti_trades'] as $r)
            $st->execute([$r['code'] ?? '', $r['name'] ?? '', $r['category'] ?? '', $r['duration'] ?? '', ++$i]);
    }
    if (!connect_qtx_has_rows('cx_prof_certifications') && !empty($d['prof_certifications'])) {
        $st = $pdo->prepare("INSERT INTO cx_prof_certifications (code,name,body,domain,sort_order) VALUES (?,?,?,?,?)");
        $i = 0; foreach ($d['prof_certifications'] as $r)
            $st->execute([$r['code'] ?? '', $r['name'] ?? '', $r['body'] ?? '', $r['domain'] ?? '', ++$i]);
    }
    if (!connect_qtx_has_rows('cx_qualtax_versions')) {
        $st = $pdo->prepare("INSERT INTO cx_qualtax_versions (version,source,note,imported_at) VALUES (?,?,?,?)");
        $st->execute([$d['version'] ?? '', $d['source'] ?? '', $d['note'] ?? '', date('c')]);
    }
}

/** Small read helper: rows of a cx_ table, ordered for display. */
function connect_qtx_rows($table, $order = 'sort_order, id') {
    try { return db()->query("SELECT * FROM $table ORDER BY $order")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
    catch (Throwable $e) { return []; }
}

/** Roles belonging to one job family, ordered. */
function connect_qtx_roles_for_family($familyCode) {
    try {
        $st = db()->prepare("SELECT * FROM cx_roles WHERE family_code=? ORDER BY sort_order, id");
        $st->execute([(string)$familyCode]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

/** Human label for a qualification band (the ladder's tiers). */
function connect_qtx_band_label($band) {
    static $m = [
        'SCHOOL' => 'School', 'ITI' => 'ITI', 'APPRENTICE' => 'Apprenticeship',
        'VOCATIONAL' => 'Vocational', 'DIPLOMA' => 'Diploma', 'DEGREE' => 'Degree',
        'PG' => 'Post-graduate / MBA', 'DOCTORATE' => 'Doctorate', 'PROFESSIONAL' => 'Professional',
    ];
    return $m[strtoupper((string)$band)] ?? ucfirst(strtolower((string)$band));
}

/** Counts across the qualification taxonomy — the summary card and tests. */
function connect_qualtax_summary() {
    $count = function ($t) { try { return (int)db()->query("SELECT COUNT(*) FROM $t")->fetchColumn(); } catch (Throwable $e) { return 0; } };
    return [
        'families'       => $count('cx_job_families'),
        'roles'          => $count('cx_roles'),
        'levels'         => $count('cx_qualification_levels'),
        'iti_trades'     => $count('cx_iti_trades'),
        'certifications' => $count('cx_prof_certifications'),
        'version'        => (function () { try { return (string)db()->query("SELECT version FROM cx_qualtax_versions ORDER BY id DESC")->fetchColumn(); } catch (Throwable $e) { return ''; } })(),
    ];
}

/**
 * Options for the profile-capture selects, keyed for the pro portal (A1) and any
 * requirement builder. Pure read; safe when tables are empty.
 */
function connect_qualtax_options() {
    return [
        'families'   => connect_qtx_rows('cx_job_families'),
        'roles'      => connect_qtx_rows('cx_roles'),
        'levels'     => connect_qtx_rows('cx_qualification_levels'),
        'iti_trades' => connect_qtx_rows('cx_iti_trades'),
        'certs'      => connect_qtx_rows('cx_prof_certifications'),
    ];
}

/** Read gate — reuses existing helpers; introduces NO new permission (mirrors K0). */
function connect_qualtax_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('is_master') && is_master()) return true;
    if (function_exists('is_admin_level') && is_admin_level()) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    return false;
}

/** Read-only screen: the ITI→MBA qualification & role taxonomy at a glance. */
function ops_connect_qualifications($method) {
    ops_require(connect_qualtax_can(),
        'This qualification taxonomy is available to coordinators, managers and admins.');
    connect_qualtax_seed(); // idempotent — ensures the data is present to view
    view('ops/connect_qualifications', [
        'summary'  => connect_qualtax_summary(),
        'families' => connect_qtx_rows('cx_job_families'),
        'levels'   => connect_qtx_rows('cx_qualification_levels'),
        'trades'   => connect_qtx_rows('cx_iti_trades'),
        'certs'    => connect_qtx_rows('cx_prof_certifications'),
    ]);
    return true;
}
