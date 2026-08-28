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

    // Every master carries is_active (soft on/off — deactivate without breaking
    // references) and is_system (seeded row — only Super Admin may hard-delete,
    // mirroring the Lookups rule). Nothing here is hard-coded in logic: the rows
    // are runtime data an admin can add to, edit, switch off or delete.
    db()->exec("CREATE TABLE IF NOT EXISTS cx_qualification_levels (
        id $pk, code VARCHAR(40) DEFAULT '', band VARCHAR(30) DEFAULT '',
        nsqf_level INT DEFAULT 0, name VARCHAR(200) DEFAULT '',
        detail VARCHAR(500) DEFAULT '', sort_order INT DEFAULT 0,
        is_active INT DEFAULT 1, is_system INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_job_families (
        id $pk, code VARCHAR(40) DEFAULT '', name VARCHAR(200) DEFAULT '',
        detail VARCHAR(500) DEFAULT '', nsqf_min INT DEFAULT 0, nsqf_max INT DEFAULT 0,
        sort_order INT DEFAULT 0, is_active INT DEFAULT 1, is_system INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_roles (
        id $pk, code VARCHAR(40) DEFAULT '', family_code VARCHAR(40) DEFAULT '',
        name VARCHAR(200) DEFAULT '', aka VARCHAR(300) DEFAULT '',
        min_qual_band VARCHAR(30) DEFAULT '', typical_certs VARCHAR(400) DEFAULT '',
        sort_order INT DEFAULT 0, is_active INT DEFAULT 1, is_system INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_iti_trades (
        id $pk, code VARCHAR(40) DEFAULT '', name VARCHAR(200) DEFAULT '',
        category VARCHAR(60) DEFAULT '', duration VARCHAR(40) DEFAULT '', sort_order INT DEFAULT 0,
        is_active INT DEFAULT 1, is_system INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_prof_certifications (
        id $pk, code VARCHAR(60) DEFAULT '', name VARCHAR(240) DEFAULT '',
        body VARCHAR(120) DEFAULT '', domain VARCHAR(160) DEFAULT '', sort_order INT DEFAULT 0,
        is_active INT DEFAULT 1, is_system INT DEFAULT 0)");
    db()->exec("CREATE TABLE IF NOT EXISTS cx_qualtax_versions (
        id $pk, version VARCHAR(20) DEFAULT '', source VARCHAR(300) DEFAULT '',
        note VARCHAR(800) DEFAULT '', imported_at VARCHAR(30) DEFAULT '')");

    // Upgrade path for an install created by an earlier build of this slice
    // (before is_active/is_system existed). Idempotent.
    if (function_exists('ensure_column')) {
        foreach (['cx_qualification_levels','cx_job_families','cx_roles','cx_iti_trades','cx_prof_certifications'] as $t) {
            try { ensure_column($t, 'is_active', 'INT DEFAULT 1'); ensure_column($t, 'is_system', 'INT DEFAULT 0'); }
            catch (Throwable $e) {}
        }
    }

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

    // Seeded rows are marked is_system=1 (built-in) — an admin may still edit or
    // switch them off, but only the Super Admin may hard-delete them.
    if (!connect_qtx_has_rows('cx_qualification_levels') && !empty($d['qualification_levels'])) {
        $st = $pdo->prepare("INSERT INTO cx_qualification_levels (code,band,nsqf_level,name,detail,sort_order,is_system) VALUES (?,?,?,?,?,?,1)");
        $i = 0; foreach ($d['qualification_levels'] as $r)
            $st->execute([$r['code'] ?? '', $r['band'] ?? '', (int)($r['nsqf_level'] ?? 0), $r['name'] ?? '', $r['detail'] ?? '', ++$i]);
    }
    if (!connect_qtx_has_rows('cx_job_families') && !empty($d['job_families'])) {
        $st = $pdo->prepare("INSERT INTO cx_job_families (code,name,detail,nsqf_min,nsqf_max,sort_order,is_system) VALUES (?,?,?,?,?,?,1)");
        $i = 0; foreach ($d['job_families'] as $r)
            $st->execute([$r['code'] ?? '', $r['name'] ?? '', $r['detail'] ?? '', (int)($r['nsqf_min'] ?? 0), (int)($r['nsqf_max'] ?? 0), ++$i]);
    }
    if (!connect_qtx_has_rows('cx_roles') && !empty($d['roles'])) {
        $st = $pdo->prepare("INSERT INTO cx_roles (code,family_code,name,aka,min_qual_band,typical_certs,sort_order,is_system) VALUES (?,?,?,?,?,?,?,1)");
        $i = 0; foreach ($d['roles'] as $r)
            $st->execute([$r['code'] ?? '', $r['family'] ?? '', $r['name'] ?? '', $r['aka'] ?? '', $r['min_qual_band'] ?? '', $r['typical_certs'] ?? '', ++$i]);
    }
    if (!connect_qtx_has_rows('cx_iti_trades') && !empty($d['iti_trades'])) {
        $st = $pdo->prepare("INSERT INTO cx_iti_trades (code,name,category,duration,sort_order,is_system) VALUES (?,?,?,?,?,1)");
        $i = 0; foreach ($d['iti_trades'] as $r)
            $st->execute([$r['code'] ?? '', $r['name'] ?? '', $r['category'] ?? '', $r['duration'] ?? '', ++$i]);
    }
    if (!connect_qtx_has_rows('cx_prof_certifications') && !empty($d['prof_certifications'])) {
        $st = $pdo->prepare("INSERT INTO cx_prof_certifications (code,name,body,domain,sort_order,is_system) VALUES (?,?,?,?,?,1)");
        $i = 0; foreach ($d['prof_certifications'] as $r)
            $st->execute([$r['code'] ?? '', $r['name'] ?? '', $r['body'] ?? '', $r['domain'] ?? '', ++$i]);
    }
    if (!connect_qtx_has_rows('cx_qualtax_versions')) {
        $st = $pdo->prepare("INSERT INTO cx_qualtax_versions (version,source,note,imported_at) VALUES (?,?,?,?)");
        $st->execute([$d['version'] ?? '', $d['source'] ?? '', $d['note'] ?? '', date('c')]);
    }
}

/**
 * Small read helper: rows of a cx_ table, ordered for display.
 *  $activeOnly = true  → only switched-on rows (what live consumers see).
 *  $activeOnly = false → every row incl. deactivated ones (the admin editor).
 * The is_active filter is applied defensively (older rows may predate the column).
 */
function connect_qtx_rows($table, $order = 'sort_order, id', $activeOnly = true) {
    $where = $activeOnly ? "WHERE COALESCE(is_active,1)=1" : '';
    try { return db()->query("SELECT * FROM $table $where ORDER BY $order")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
    catch (Throwable $e) {
        // Column may not exist on a very old row set — fall back to unfiltered.
        try { return db()->query("SELECT * FROM $table ORDER BY $order")->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e2) { return []; }
    }
}

/** Roles belonging to one job family, ordered. Active-only by default. */
function connect_qtx_roles_for_family($familyCode, $activeOnly = true) {
    $where = $activeOnly ? "AND COALESCE(is_active,1)=1" : '';
    try {
        $st = db()->prepare("SELECT * FROM cx_roles WHERE family_code=? $where ORDER BY sort_order, id");
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

/** Counts across the qualification taxonomy — the summary card and tests. Counts
 *  active rows (what the marketplace actually uses); pass false for the total. */
function connect_qualtax_summary($activeOnly = true) {
    $flt = $activeOnly ? " WHERE COALESCE(is_active,1)=1" : '';
    $count = function ($t) use ($flt) {
        try { return (int)db()->query("SELECT COUNT(*) FROM $t$flt")->fetchColumn(); }
        catch (Throwable $e) { try { return (int)db()->query("SELECT COUNT(*) FROM $t")->fetchColumn(); } catch (Throwable $e2) { return 0; } }
    };
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
 * requirement builder. Pure read; active-only; safe when tables are empty.
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

// ============================================================================
//  CONFIGURABILITY — the taxonomy is runtime data, not hard-coded. An admin can
//  add, edit, switch off or delete every family, role, level, ITI trade and
//  certification. Gated exactly like Lookups (permission-matrix row 12): manage
//  = is_admin_level(); hard-delete of a built-in (seeded) row = is_master() only.
//  No new permission is introduced.
// ============================================================================

/** The editable masters, one small schema each. `fields` are the editable
 *  columns and how to render them; `select` names a lookup-into for options. */
function connect_qualtax_kinds() {
    return [
        'family' => ['table' => 'cx_job_families', 'label' => 'Job family', 'plural' => 'Job families',
            'fields' => [
                'code'     => ['label' => 'Code', 'type' => 'code',   'required' => true],
                'name'     => ['label' => 'Name', 'type' => 'text',   'required' => true],
                'detail'   => ['label' => 'Description', 'type' => 'text'],
                'nsqf_min' => ['label' => 'NSQF min', 'type' => 'int'],
                'nsqf_max' => ['label' => 'NSQF max', 'type' => 'int'],
            ]],
        'role' => ['table' => 'cx_roles', 'label' => 'Role', 'plural' => 'Roles',
            'fields' => [
                'code'          => ['label' => 'Code', 'type' => 'code', 'required' => true],
                'family_code'   => ['label' => 'Job family', 'type' => 'select', 'select' => 'family', 'required' => true],
                'name'          => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'aka'           => ['label' => 'Also known as', 'type' => 'text'],
                'min_qual_band' => ['label' => 'Minimum entry band', 'type' => 'band'],
                'typical_certs' => ['label' => 'Typical certifications', 'type' => 'text'],
            ]],
        'level' => ['table' => 'cx_qualification_levels', 'label' => 'Qualification level', 'plural' => 'Qualification levels',
            'fields' => [
                'code'       => ['label' => 'Code', 'type' => 'code', 'required' => true],
                'band'       => ['label' => 'Band', 'type' => 'band', 'required' => true],
                'nsqf_level' => ['label' => 'NSQF level', 'type' => 'int'],
                'name'       => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'detail'     => ['label' => 'Description', 'type' => 'text'],
            ]],
        'trade' => ['table' => 'cx_iti_trades', 'label' => 'ITI trade', 'plural' => 'ITI trades',
            'fields' => [
                'code'     => ['label' => 'Code', 'type' => 'code', 'required' => true],
                'name'     => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'category' => ['label' => 'Category', 'type' => 'text'],
                'duration' => ['label' => 'Duration', 'type' => 'text'],
            ]],
        'cert' => ['table' => 'cx_prof_certifications', 'label' => 'Certification', 'plural' => 'Certifications',
            'fields' => [
                'code'   => ['label' => 'Code', 'type' => 'code', 'required' => true],
                'name'   => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'body'   => ['label' => 'Issuing body', 'type' => 'text'],
                'domain' => ['label' => 'Domain', 'type' => 'text'],
            ]],
    ];
}

/** The qualification bands offered in the editor (also runtime, from the ladder). */
function connect_qualtax_bands() {
    return ['SCHOOL','ITI','APPRENTICE','VOCATIONAL','DIPLOMA','DEGREE','PG','DOCTORATE','PROFESSIONAL'];
}

/** Manage gate — mirrors Lookups: admins configure the masters. No new permission. */
function connect_qualtax_manage_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    return function_exists('is_admin_level') && is_admin_level();
}

/** Normalise a code: UPPER_SNAKE, safe as a stable key. */
function connect_qualtax_norm_code($raw) {
    $c = strtoupper(trim((string)$raw));
    $c = preg_replace('/[^A-Z0-9]+/', '_', $c);
    return trim($c, '_');
}

/** Read one row of a kind by id, or null. */
function connect_qualtax_row($kind, $id) {
    $k = connect_qualtax_kinds()[$kind] ?? null; if (!$k) return null;
    try { return ops_one("SELECT * FROM {$k['table']} WHERE id=?", [(int)$id]) ?: null; }
    catch (Throwable $e) { return null; }
}

/**
 * Create or update one master row from posted data. Validates required fields and
 * a unique code. Returns [ok, message]. Admin-gated by the caller.
 */
function connect_qualtax_save($kind, $id, $post) {
    $k = connect_qualtax_kinds()[$kind] ?? null;
    if (!$k) return [false, 'Unknown item type.'];
    $id = (int)$id; $table = $k['table'];

    $vals = [];
    foreach ($k['fields'] as $col => $f) {
        $raw = $post[$col] ?? '';
        switch ($f['type']) {
            case 'int':  $vals[$col] = (int)$raw; break;
            case 'code': $vals[$col] = connect_qualtax_norm_code($raw); break;
            case 'band': $vals[$col] = in_array(strtoupper((string)$raw), connect_qualtax_bands(), true) ? strtoupper((string)$raw) : ''; break;
            default:     $vals[$col] = trim((string)$raw);
        }
        if (!empty($f['required']) && ($vals[$col] === '' || $vals[$col] === null)) {
            return [false, ($f['label'] ?? $col) . ' is required.'];
        }
    }

    // Code must be unique within the table (excluding this row on edit).
    if (isset($vals['code'])) {
        $dupe = ops_val("SELECT COUNT(*) FROM $table WHERE code=? AND id<>?", [$vals['code'], $id]);
        if ((int)$dupe > 0) return [false, 'That code is already in use — pick another.'];
    }
    // A role must point at a family that exists.
    if ($kind === 'role' && !empty($vals['family_code'])) {
        if ((int)ops_val("SELECT COUNT(*) FROM cx_job_families WHERE code=?", [$vals['family_code']]) === 0)
            return [false, 'Pick an existing job family for the role.'];
    }

    if ($id > 0) {
        $set = implode(', ', array_map(fn($c) => "$c=?", array_keys($vals)));
        $args = array_values($vals); $args[] = $id;
        db()->prepare("UPDATE $table SET $set WHERE id=?")->execute($args);
        return [true, $k['label'] . ' updated.'];
    }
    // New row — admin-added, so is_system=0 (freely deletable), placed at the end.
    $next = (int)ops_val("SELECT COALESCE(MAX(sort_order),0)+1 FROM $table");
    $vals['sort_order'] = $next; $vals['is_active'] = 1; $vals['is_system'] = 0;
    $cols = implode(',', array_keys($vals));
    $ph   = implode(',', array_fill(0, count($vals), '?'));
    db()->prepare("INSERT INTO $table ($cols) VALUES ($ph)")->execute(array_values($vals));
    return [true, $k['label'] . ' added.'];
}

/** Switch a row on/off (soft — keeps it and any references intact). */
function connect_qualtax_toggle($kind, $id) {
    $k = connect_qualtax_kinds()[$kind] ?? null; if (!$k) return [false, 'Unknown item type.'];
    $row = connect_qualtax_row($kind, $id); if (!$row) return [false, 'Item not found.'];
    $to = ((int)($row['is_active'] ?? 1) === 1) ? 0 : 1;
    db()->prepare("UPDATE {$k['table']} SET is_active=? WHERE id=?")->execute([$to, (int)$id]);
    return [true, $k['label'] . ($to ? ' switched on.' : ' switched off.')];
}

/** Hard-delete a row. Built-in (seeded) rows need Super Admin; custom rows: admin. */
function connect_qualtax_delete($kind, $id) {
    $k = connect_qualtax_kinds()[$kind] ?? null; if (!$k) return [false, 'Unknown item type.'];
    $row = connect_qualtax_row($kind, $id); if (!$row) return [false, 'Item not found.'];
    $isSystem = (int)($row['is_system'] ?? 0) === 1;
    if ($isSystem && !(function_exists('is_master') && is_master()))
        return [false, 'Only the Super Admin can delete a built-in item. You can switch it off instead.'];
    // A family in use by a role is switched off rather than deleted, to avoid
    // orphaning roles silently.
    if ($kind === 'family' && (int)ops_val("SELECT COUNT(*) FROM cx_roles WHERE family_code=?", [$row['code']]) > 0) {
        db()->prepare("UPDATE cx_job_families SET is_active=0 WHERE id=?")->execute([(int)$id]);
        return [true, 'That family still has roles, so it was switched off instead of deleted.'];
    }
    db()->prepare("DELETE FROM {$k['table']} WHERE id=?")->execute([(int)$id]);
    return [true, $k['label'] . ' deleted.'];
}

/** Read gate — reuses existing helpers; introduces NO new permission (mirrors K0). */
function connect_qualtax_can() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;
    if (function_exists('is_master') && is_master()) return true;
    if (function_exists('is_admin_level') && is_admin_level()) return true;
    if (function_exists('is_coordinator_level') && is_coordinator_level()) return true;
    return false;
}

/**
 * The screen: the ITI→MBA qualification & role taxonomy. Read-only for
 * coordinators; a full add / edit / switch-off / delete editor for admins. POST
 * actions (admin-gated) mutate the runtime masters — nothing here is hard-coded.
 */
function ops_connect_qualifications($method) {
    ops_require(connect_qualtax_can(),
        'This qualification taxonomy is available to coordinators, managers and admins.');
    connect_qualtax_seed(); // idempotent — ensures the data is present to view
    $canManage = connect_qualtax_manage_can();

    if ($method === 'POST') {
        ops_require($canManage, 'Only admins can configure the qualification taxonomy.');
        $action = (string)($_POST['action'] ?? '');
        $kind   = (string)($_POST['kind'] ?? '');
        $id     = (int)($_POST['id'] ?? 0);
        if (!isset(connect_qualtax_kinds()[$kind])) { flash('Unknown item type.', 'error'); redirect('/connect-qualifications'); }
        if ($action === 'save')        [$ok, $msg] = connect_qualtax_save($kind, $id, $_POST);
        elseif ($action === 'toggle')  [$ok, $msg] = connect_qualtax_toggle($kind, $id);
        elseif ($action === 'delete')  [$ok, $msg] = connect_qualtax_delete($kind, $id);
        else { $ok = false; $msg = 'Unknown action.'; }
        flash($msg, $ok ? 'success' : 'error');
        redirect('/connect-qualifications' . ($kind ? '#' . $kind : ''));
    }

    // The "at a glance" sections always show the live, switched-on taxonomy. The
    // admin Configure panel (in the view) lists every row incl. switched-off ones
    // itself, via connect_qtx_rows(..., false).
    view('ops/connect_qualifications', [
        'summary'   => connect_qualtax_summary(),
        'families'  => connect_qtx_rows('cx_job_families'),
        'levels'    => connect_qtx_rows('cx_qualification_levels'),
        'trades'    => connect_qtx_rows('cx_iti_trades'),
        'certs'     => connect_qtx_rows('cx_prof_certifications'),
        'canManage' => $canManage,
        'kinds'     => connect_qualtax_kinds(),
        'bands'     => connect_qualtax_bands(),
    ]);
    return true;
}
