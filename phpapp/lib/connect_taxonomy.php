<?php
// ============================================================================
//  CONNECT — Industry & Competency Taxonomy  (slice K0, additive & read-only)
//
//  The backbone master data for the manpower marketplace folded into EXAACT:
//  sectors → equipment → materials → disciplines → inspection stages →
//  standards → certifications. Adopted wholesale from the MGH Inspect Connect
//  blueprint (Part B), which already defines and seeds it. See
//  docs/connect/02-identities-and-taxonomy.md.
//
//  ADDITIVE CONTRACT (like every revamp slice):
//   - New tables only, all prefixed `cx_` so nothing existing is touched.
//   - Idempotent CREATE TABLE IF NOT EXISTS + insert-if-empty seed.
//   - Read-only screen; changes no existing route, view, permission or status.
//   - Master data is seeded from data/connect_taxonomy.json, never hard-coded
//     in application logic (admin-extensible at runtime later).
// ============================================================================

/** The nine versioned master tables — additive, `cx_` namespaced. */
function connect_taxonomy_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    db()->exec("CREATE TABLE IF NOT EXISTS cx_sectors (
        id $pk, code VARCHAR(40) DEFAULT '', name VARCHAR(200) DEFAULT '',
        detail VARCHAR(400) DEFAULT '', sort_order INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_equipment_groups (
        id $pk, code VARCHAR(40) DEFAULT '', name VARCHAR(200) DEFAULT '', sort_order INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_equipment_types (
        id $pk, group_code VARCHAR(40) DEFAULT '', name VARCHAR(240) DEFAULT '', sort_order INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_materials (
        id $pk, code VARCHAR(40) DEFAULT '', name VARCHAR(200) DEFAULT '',
        grades VARCHAR(400) DEFAULT '', sort_order INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_disciplines (
        id $pk, code VARCHAR(40) DEFAULT '', name VARCHAR(200) DEFAULT '',
        methods VARCHAR(600) DEFAULT '', sort_order INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_inspection_stages (
        id $pk, seq INT DEFAULT 0, code VARCHAR(40) DEFAULT '', name VARCHAR(200) DEFAULT '')");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_standards (
        id $pk, family VARCHAR(60) DEFAULT '', codes VARCHAR(1000) DEFAULT '', sort_order INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_certifications_registry (
        id $pk, code VARCHAR(60) DEFAULT '', name VARCHAR(240) DEFAULT '',
        issuer VARCHAR(120) DEFAULT '', verify_route VARCHAR(40) DEFAULT '',
        register VARCHAR(240) DEFAULT '', sort_order INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_taxonomy_versions (
        id $pk, version VARCHAR(20) DEFAULT '', source VARCHAR(240) DEFAULT '',
        note VARCHAR(600) DEFAULT '', imported_at VARCHAR(30) DEFAULT '')");
}

/** The seed file path (bundled data asset, adopted from the blueprint). */
function connect_taxonomy_file() {
    return __DIR__ . '/../data/connect_taxonomy.json';
}

/** Decode the seed file once; [] if missing/broken (never fatal). */
function connect_taxonomy_data() {
    static $cache = null; if ($cache !== null) return $cache;
    $f = connect_taxonomy_file();
    if (!is_file($f)) return $cache = [];
    $j = json_decode((string)@file_get_contents($f), true);
    return $cache = (is_array($j) ? $j : []);
}

/** True when a table already holds rows (seed is insert-if-empty, idempotent). */
function connect_tx_has_rows($table) {
    try { return (int)db()->query("SELECT COUNT(*) FROM $table")->fetchColumn() > 0; }
    catch (Throwable $e) { return false; }
}

/**
 * Import the master data. Idempotent: each table is filled only when empty, so
 * a re-run (boot, cron, test) is a no-op and never duplicates or overwrites.
 * Admin edits made later are therefore never clobbered by a reseed.
 */
function connect_taxonomy_seed() {
    connect_taxonomy_migrate();
    $d = connect_taxonomy_data();
    if (!$d) return;
    $pdo = db();

    if (!connect_tx_has_rows('cx_sectors') && !empty($d['sectors'])) {
        $st = $pdo->prepare("INSERT INTO cx_sectors (code,name,detail,sort_order) VALUES (?,?,?,?)");
        $i = 0; foreach ($d['sectors'] as $r) $st->execute([$r['code'] ?? '', $r['name'] ?? '', $r['detail'] ?? '', ++$i]);
    }
    if (!connect_tx_has_rows('cx_equipment_groups') && !empty($d['equipment_groups'])) {
        $g = $pdo->prepare("INSERT INTO cx_equipment_groups (code,name,sort_order) VALUES (?,?,?)");
        $t = $pdo->prepare("INSERT INTO cx_equipment_types (group_code,name,sort_order) VALUES (?,?,?)");
        $i = 0; foreach ($d['equipment_groups'] as $r) {
            $g->execute([$r['code'] ?? '', $r['name'] ?? '', ++$i]);
            $j = 0; foreach (($r['types'] ?? []) as $ty) $t->execute([$r['code'] ?? '', (string)$ty, ++$j]);
        }
    }
    if (!connect_tx_has_rows('cx_materials') && !empty($d['materials'])) {
        $st = $pdo->prepare("INSERT INTO cx_materials (code,name,grades,sort_order) VALUES (?,?,?,?)");
        $i = 0; foreach ($d['materials'] as $r) $st->execute([$r['code'] ?? '', $r['name'] ?? '', $r['grades'] ?? '', ++$i]);
    }
    if (!connect_tx_has_rows('cx_disciplines') && !empty($d['disciplines'])) {
        $st = $pdo->prepare("INSERT INTO cx_disciplines (code,name,methods,sort_order) VALUES (?,?,?,?)");
        $i = 0; foreach ($d['disciplines'] as $r) {
            $m = isset($r['methods']) && is_array($r['methods']) ? implode(', ', $r['methods']) : '';
            $st->execute([$r['code'] ?? '', $r['name'] ?? '', $m, ++$i]);
        }
    }
    if (!connect_tx_has_rows('cx_inspection_stages') && !empty($d['inspection_stages'])) {
        $st = $pdo->prepare("INSERT INTO cx_inspection_stages (seq,code,name) VALUES (?,?,?)");
        foreach ($d['inspection_stages'] as $r) $st->execute([(int)($r['seq'] ?? 0), $r['code'] ?? '', $r['name'] ?? '']);
    }
    if (!connect_tx_has_rows('cx_standards') && !empty($d['standards'])) {
        $st = $pdo->prepare("INSERT INTO cx_standards (family,codes,sort_order) VALUES (?,?,?)");
        $i = 0; foreach ($d['standards'] as $r) {
            $c = isset($r['codes']) && is_array($r['codes']) ? implode(', ', $r['codes']) : (string)($r['codes'] ?? '');
            $st->execute([$r['family'] ?? '', $c, ++$i]);
        }
    }
    if (!connect_tx_has_rows('cx_certifications_registry') && !empty($d['certifications_registry'])) {
        $st = $pdo->prepare("INSERT INTO cx_certifications_registry (code,name,issuer,verify_route,register,sort_order) VALUES (?,?,?,?,?,?)");
        $i = 0; foreach ($d['certifications_registry'] as $r)
            $st->execute([$r['code'] ?? '', $r['name'] ?? '', $r['issuer'] ?? '', $r['verify_route'] ?? '', $r['register'] ?? '', ++$i]);
    }
    if (!connect_tx_has_rows('cx_taxonomy_versions')) {
        $st = $pdo->prepare("INSERT INTO cx_taxonomy_versions (version,source,note,imported_at) VALUES (?,?,?,?)");
        $st->execute([$d['version'] ?? '', $d['source'] ?? '', $d['note'] ?? '', date('c')]);
    }
}

/** Small read helper: rows of a cx_ table, ordered for display. */
function connect_tx_rows($table, $order = 'sort_order, id') {
    try { return db()->query("SELECT * FROM $table ORDER BY $order")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
    catch (Throwable $e) { return []; }
}

/** Counts across the taxonomy, for the summary card and tests. */
function connect_taxonomy_summary() {
    $count = function($t) { try { return (int)db()->query("SELECT COUNT(*) FROM $t")->fetchColumn(); } catch (Throwable $e) { return 0; } };
    return [
        'sectors'          => $count('cx_sectors'),
        'equipment_groups' => $count('cx_equipment_groups'),
        'equipment_types'  => $count('cx_equipment_types'),
        'materials'        => $count('cx_materials'),
        'disciplines'      => $count('cx_disciplines'),
        'stages'           => $count('cx_inspection_stages'),
        'standards'        => $count('cx_standards'),
        'certifications'   => $count('cx_certifications_registry'),
        'version'          => (function(){ try { return (string)db()->query("SELECT version FROM cx_taxonomy_versions ORDER BY id DESC")->fetchColumn(); } catch (Throwable $e) { return ''; } })(),
    ];
}

/** Read gate — reuses existing helpers; introduces NO new permission. */
function connect_taxonomy_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('is_master') && is_master()) return true;
    if (function_exists('is_admin_level') && is_admin_level()) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    return false;
}

/** Read-only screen: the marketplace's industry taxonomy at a glance. */
function ops_connect_taxonomy($method) {
    ops_require(connect_taxonomy_can(),
        'This industry taxonomy is available to coordinators, managers and admins.');
    connect_taxonomy_seed(); // idempotent — ensures the data is present to view
    view('ops/connect_taxonomy', [
        'summary'   => connect_taxonomy_summary(),
        'sectors'   => connect_tx_rows('cx_sectors'),
        'groups'    => connect_tx_rows('cx_equipment_groups'),
        'materials' => connect_tx_rows('cx_materials'),
        'disc'      => connect_tx_rows('cx_disciplines'),
        'stages'    => connect_tx_rows('cx_inspection_stages', 'seq, id'),
        'standards' => connect_tx_rows('cx_standards'),
        'certs'     => connect_tx_rows('cx_certifications_registry'),
    ]);
    return true;
}
