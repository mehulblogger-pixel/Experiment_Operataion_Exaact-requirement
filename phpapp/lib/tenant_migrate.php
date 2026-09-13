<?php
// ============================================================================
//  MOVE A WORKSPACE'S DATA FROM A FILE (SQLite) INTO MYSQL — safely.
//
//  Why this exists
//  ---------------
//  A workspace can be created "file-backed": all of its data lives in a single
//  file inside the app folder, named  tenant-<key>.sqlite . That is the simplest
//  storage, but it has one sharp edge for the way this product is updated on
//  managed hosting: when an operator updates by DELETING every file in the app
//  folder and re-uploading a fresh copy, that data file is deleted with the rest
//  — and, unlike a MySQL workspace, the data itself lived in the file, so it is
//  genuinely gone. (A MySQL workspace keeps its data in the database server,
//  which a file upload can never touch.)
//
//  This tool copies a file-backed workspace's ENTIRE contents into a proper
//  MySQL database and then repoints the workspace's routing at MySQL. From then
//  on the workspace is upload-proof: its data lives in the database server.
//
//  Safety principles (do no harm)
//  ------------------------------
//   • The source file is only ever READ. It is never modified or deleted here,
//     so it remains a perfect backup after the move.
//   • Routing is repointed ONLY after every table has been copied and each
//     table's row count in MySQL matches the file. If anything fails, routing is
//     left untouched and the workspace keeps running on its file exactly as
//     before — no half-migrated state, no data loss.
//   • No exec()/shell is used, so it works on locked-down shared hosting
//     (cPanel / mPanel / MilesWeb) where the old separate-process tools silently
//     did nothing.
//   • Re-runnable: each target table is emptied immediately before it is filled,
//     so running the move twice yields the same result rather than duplicates.
// ============================================================================

// A data folder ABOVE the web root (beside the app folder), so a workspace file
// stored there is NOT deleted by the "delete every file, then re-upload" update
// method. Falls back to the app folder only if nothing above the root is
// writable. Returns [dir, safe_bool].
function tenant_safe_data_dir() {
    $mk = function ($d) {
        if ($d === '') return false;
        if (!is_dir($d) && !@mkdir($d, 0750, true)) return false;
        if (!is_writable($d)) return false;
        $ht = $d . '/.htaccess';
        if (!is_file($ht)) @file_put_contents($ht, "Options -Indexes\nRequire all denied\nOrder deny,allow\nDeny from all\n");
        return true;
    };
    $above = dirname(dirname(__DIR__)) . '/exaact_data';   // sibling of the app folder → above web root
    if ($mk($above)) return [$above, true];
    $inApp = dirname(__DIR__) . '/data';                    // fallback, inside the app folder (backups still protect it)
    if ($mk($inApp)) return [$inApp, false];
    return [dirname(__DIR__), false];                       // last resort: the app folder root (legacy)
}

// The default storage-file path for a NEW file-backed workspace — placed in the
// safe off-folder location when available. [path, safe_bool].
function tenant_default_sqlite_path($key) {
    $key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower(trim((string) $key)));
    [$dir, $safe] = tenant_safe_data_dir();
    return [$dir . '/tenant-' . $key . '.sqlite', $safe];
}

// Choose storage for a NEW workspace automatically — no technical choice for the
// operator. Prefers a real MySQL database when the server can create one itself;
// otherwise a file in the safe off-folder location. Returns
// [$db, $kind] where $db is a route array and $kind is 'mysql'|'sqlite'.
function tenant_auto_storage($key) {
    if (function_exists('saas_can_autocreate_db') && saas_can_autocreate_db()
        && function_exists('saas_mysql_provision_db') && function_exists('saas_db_admin_config')) {
        try { return [saas_mysql_provision_db($key, saas_db_admin_config()), 'mysql']; }
        catch (Throwable $e) { /* fall through to a safe file */ }
    }
    [$path] = tenant_default_sqlite_path($key);
    return [['sqlite' => $path], 'sqlite'];
}

// ---- Storage facts about a workspace, for the console -----------------------
// Returns: ['type' => 'sqlite'|'mysql'|'unconfigured'|'unknown',
//           'path' => sqlite file path (sqlite only),
//           'in_app_folder' => bool, 'exists' => bool, 'size' => bytes,
//           'tables' => int, 'rows' => int, 'at_risk' => bool, 'label' => str].
function saas_tenant_storage_info($key) {
    $key = strtolower(trim((string) $key));
    $out = ['type' => 'unknown', 'path' => '', 'in_app_folder' => false, 'exists' => false,
            'size' => 0, 'tables' => 0, 'rows' => 0, 'at_risk' => false, 'label' => 'Unknown'];
    if ($key === '') return $out;

    // Prefer the durable control-DB routing; fall back to the registry file.
    $route = null;
    if (function_exists('saas_tenant_get')) {
        $t = saas_tenant_get($key);
        if ($t && !empty($t['route_json'])) { $r = json_decode((string) $t['route_json'], true); if (is_array($r)) $route = $r; }
    }
    if ($route === null && function_exists('tenant_registry')) {
        $reg = tenant_registry();
        $e = $reg['tenants'][$key] ?? null;
        if (is_array($e)) {
            if (!empty($e['sqlite'])) $route = ['sqlite' => $e['sqlite']];
            elseif (!empty($e['db'])) $route = (array) $e['db'];
        }
    }
    if ($route === null) { $out['type'] = 'unconfigured'; $out['label'] = 'Not wired up yet'; return $out; }

    if (!empty($route['sqlite'])) {
        $path = (string) $route['sqlite'];
        $out['type'] = 'sqlite';
        $out['path'] = $path;
        $appBase = realpath(dirname(__DIR__));
        $real = realpath($path);
        $out['in_app_folder'] = ($real && $appBase && strncmp($real, $appBase, strlen($appBase)) === 0);
        if ($real && is_file($real)) {
            $out['exists'] = true;
            $out['size'] = (int) @filesize($real);
            try {
                $src = new PDO('sqlite:' . $real, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $tables = _mig_sqlite_tables($src);
                $out['tables'] = count($tables);
                foreach ($tables as $t) {
                    try { $out['rows'] += (int) $src->query('SELECT COUNT(*) FROM "' . str_replace('"', '""', $t) . '"')->fetchColumn(); }
                    catch (Throwable $e) {}
                }
            } catch (Throwable $e) {}
        }
        // At risk = a real file that sits inside the app folder (would be deleted
        // by a "delete every file, then re-upload" update).
        $out['at_risk'] = $out['exists'] && $out['in_app_folder'];
        $out['label'] = 'File (SQLite)' . ($out['at_risk'] ? ' — in the app folder' : '');
        return $out;
    }

    if (!empty($route['name'])) {
        $out['type'] = 'mysql';
        $out['label'] = 'MySQL database';
        $out['at_risk'] = false;
        return $out;
    }
    $out['type'] = 'unconfigured'; $out['label'] = 'Not wired up yet';
    return $out;
}

// ---- SQLite introspection ---------------------------------------------------
function _mig_sqlite_tables(PDO $src) {
    $rows = $src->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
                ->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_filter((array) $rows, fn($n) => (string) $n !== ''));
}

// Column meta for a table: [ ['name'=>, 'type'=>, 'notnull'=>bool, 'pk'=>int], ... ]
function _mig_sqlite_columns(PDO $src, $table) {
    $q = $src->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")');
    $cols = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cols[] = ['name' => (string) $r['name'], 'type' => (string) $r['type'],
                   'notnull' => (int) $r['notnull'] === 1, 'pk' => (int) $r['pk']];
    }
    return $cols;
}

// Non-primary indexes worth recreating (unique constraints and created indexes),
// so MySQL upserts / uniqueness behave exactly as they did on the file.
// Returns [ ['name'=>, 'unique'=>bool, 'cols'=>[...]], ... ].
function _mig_sqlite_indexes(PDO $src, $table) {
    $out = [];
    try { $list = $src->query('PRAGMA index_list("' . str_replace('"', '""', $table) . '")')->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { return $out; }
    foreach ((array) $list as $ix) {
        $name = (string) ($ix['name'] ?? '');
        $origin = (string) ($ix['origin'] ?? 'c');   // 'c' created, 'u' unique constraint, 'pk' primary key
        if ($name === '' || $origin === 'pk') continue;             // PK is done in CREATE TABLE
        if (strpos($name, 'sqlite_autoindex') === 0 && $origin !== 'u') continue;
        try { $info = $src->query('PRAGMA index_info("' . str_replace('"', '""', $name) . '")')->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $e) { continue; }
        $cols = [];
        foreach ((array) $info as $ci) { $c = (string) ($ci['name'] ?? ''); if ($c !== '') $cols[] = $c; }
        if (!$cols) continue;
        $out[] = ['name' => $name, 'unique' => (int) ($ix['unique'] ?? 0) === 1, 'cols' => $cols];
    }
    return $out;
}

// ---- SQLite type  ->  MySQL type (pure; unit-tested) ------------------------
function _mig_my_type($sqliteType, $isPk = false) {
    $t = strtoupper(trim((string) $sqliteType));
    if ($t !== '' && strpos($t, 'INT') !== false)  return 'BIGINT';
    if ($t !== '' && (strpos($t, 'REAL') !== false || strpos($t, 'FLOA') !== false || strpos($t, 'DOUB') !== false)) return 'DOUBLE';
    if ($t !== '' && (strpos($t, 'DEC') !== false || strpos($t, 'NUMERIC') !== false)) return 'DECIMAL(20,6)';
    // A primary-key column must be indexable and length-bounded in MySQL.
    if ($isPk) return 'VARCHAR(191)';
    if ($t !== '' && strpos($t, 'BLOB') !== false) return 'LONGBLOB';
    return 'LONGTEXT';   // TEXT / CHAR / CLOB / DATE / BOOL / untyped — text-safe default
}

// True when a MySQL index over this column needs a length prefix (text types).
function _mig_needs_prefix($myType) {
    $t = strtoupper((string) $myType);
    return strpos($t, 'TEXT') !== false || strpos($t, 'BLOB') !== false;
}

// A `col`(191) fragment for an index, prefixing only text/blob columns.
function _mig_index_col_frag($col, $myType) {
    $frag = '`' . str_replace('`', '', $col) . '`';
    return $frag . (_mig_needs_prefix($myType) ? '(191)' : '');
}

// CREATE TABLE for MySQL from SQLite column meta (pure; unit-tested).
function _mig_create_table_sql($table, array $cols) {
    $tb = str_replace('`', '', (string) $table);
    $pk = [];
    foreach ($cols as $c) if ((int) $c['pk'] > 0) $pk[(int) $c['pk']] = $c['name'];
    ksort($pk);
    $pk = array_values($pk);
    $typeOf = [];
    $defs = [];
    foreach ($cols as $c) {
        $isPk = in_array($c['name'], $pk, true);
        $my = _mig_my_type($c['type'], $isPk);
        $typeOf[$c['name']] = $my;
        $null = ($isPk || $c['notnull']) ? ' NOT NULL' : ' NULL';
        $defs[] = '`' . str_replace('`', '', $c['name']) . '` ' . $my . $null;
    }
    if ($pk) {
        $parts = array_map(fn($c) => _mig_index_col_frag($c, $typeOf[$c] ?? 'LONGTEXT'), $pk);
        $defs[] = 'PRIMARY KEY (' . implode(',', $parts) . ')';
    }
    return 'CREATE TABLE IF NOT EXISTS `' . $tb . '` (' . implode(', ', $defs)
         . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
}

// CREATE INDEX for MySQL from a SQLite index (pure; unit-tested).
function _mig_index_sql($table, array $index, array $typeOf) {
    $tb = str_replace('`', '', (string) $table);
    $parts = array_map(fn($c) => _mig_index_col_frag($c, $typeOf[$c] ?? 'LONGTEXT'), $index['cols']);
    // A short, collision-free index name scoped by the table.
    $idxName = substr($tb . '_' . preg_replace('/[^A-Za-z0-9_]/', '_', (string) $index['name']), 0, 60);
    return 'CREATE ' . (!empty($index['unique']) ? 'UNIQUE ' : '') . 'INDEX `' . $idxName . '` ON `' . $tb . '` ('
         . implode(',', $parts) . ')';
}

// ---- The copy (driver-generic; unit-tested against SQLite->SQLite) ----------
// Empties the target table, then streams every row from source to target.
// Returns the number of rows written.
function _mig_copy_table(PDO $src, PDO $dst, $table, array $colNames) {
    $tb = str_replace('`', '', (string) $table);
    $collist = implode(',', array_map(fn($c) => '`' . str_replace('`', '', $c) . '`', $colNames));
    $srcList = implode(',', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $colNames));
    $dst->exec('DELETE FROM `' . $tb . '`');
    $sel = $src->query('SELECT ' . $srcList . ' FROM "' . str_replace('"', '""', $tb) . '"');
    $ph  = implode(',', array_fill(0, count($colNames), '?'));
    $ins = $dst->prepare('INSERT INTO `' . $tb . '` (' . $collist . ') VALUES (' . $ph . ')');
    $n = 0; $inTx = false;
    try { $dst->beginTransaction(); $inTx = true; } catch (Throwable $e) {}
    while ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
        $vals = [];
        foreach ($colNames as $c) $vals[] = array_key_exists($c, $row) ? $row[$c] : null;
        $ins->execute($vals);
        if (++$n % 500 === 0 && $inTx) { $dst->commit(); $dst->beginTransaction(); }
    }
    if ($inTx) $dst->commit();
    return $n;
}

// Build the registry entry that repoints a workspace from a file to MySQL,
// preserving its company name / status / pending owner (pure; unit-tested).
function _mig_route_entry($existing, array $db) {
    $entry = is_array($existing) ? $existing : [];
    unset($entry['sqlite']);
    $entry['company'] = (string) ($entry['company'] ?? '');
    $entry['status']  = (string) ($entry['status'] ?? 'active') ?: 'active';
    $entry['db'] = ['host' => (string) ($db['host'] ?? 'localhost') ?: 'localhost',
                    'name' => (string) ($db['name'] ?? ''),
                    'user' => (string) ($db['user'] ?? ''),
                    'pass' => (string) ($db['pass'] ?? '')];
    return $entry;
}

// ---- The whole move ---------------------------------------------------------
// $my = ['host','name','user','pass'] — a MySQL database that already exists and
// is empty (freshly created). Returns:
//   ['ok'=>bool, 'error'=>str, 'created'=>int tables, 'rows'=>int,
//    'tables'=>[ ['name'=>, 'src'=>, 'dst'=>, 'ok'=>bool], ... ]]
function saas_tenant_migrate_to_mysql($key, array $my) {
    $key = strtolower(trim((string) $key));
    $res = ['ok' => false, 'error' => '', 'created' => 0, 'rows' => 0, 'tables' => []];

    // 1) The workspace must currently be file-backed, and the file must exist.
    $info = saas_tenant_storage_info($key);
    if ($info['type'] !== 'sqlite') { $res['error'] = 'This workspace is not file-backed — nothing to move.'; return $res; }
    if (!$info['exists']) { $res['error'] = 'The workspace data file could not be found on the server.'; return $res; }
    $srcPath = (string) $info['path'];

    // 2) Validate the MySQL target details.
    $host = trim((string) ($my['host'] ?? 'localhost')) ?: 'localhost';
    $name = trim((string) ($my['name'] ?? ''));
    $user = trim((string) ($my['user'] ?? ''));
    $pass = (string) ($my['pass'] ?? '');
    if ($name === '' || $user === '') { $res['error'] = 'A MySQL database name and user are both required.'; return $res; }

    // 3) Open both ends.
    try { $src = new PDO('sqlite:' . realpath($srcPath), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    catch (Throwable $e) { $res['error'] = 'Could not open the workspace data file: ' . $e->getMessage(); return $res; }
    try {
        $dst = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
        try { $dst->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch (Throwable $e) {}
        try { $dst->exec("SET SESSION sql_mode = 'PIPES_AS_CONCAT'"); } catch (Throwable $e) {}
        try { $dst->exec('SET FOREIGN_KEY_CHECKS=0'); } catch (Throwable $e) {}
    } catch (Throwable $e) {
        $res['error'] = 'Could not connect to the MySQL database. Please check the details. (' . $e->getMessage() . ')';
        return $res;
    }

    // 4) Copy structure + data, table by table. Any failure aborts the move and
    //    leaves routing untouched (the workspace stays on its file).
    $tables = _mig_sqlite_tables($src);
    if (!$tables) { $res['error'] = 'The workspace data file has no tables to move.'; return $res; }
    $indexJobs = [];
    foreach ($tables as $t) {
        $cols = _mig_sqlite_columns($src, $t);
        if (!$cols) continue;
        $colNames = array_map(fn($c) => $c['name'], $cols);
        try {
            $dst->exec(_mig_create_table_sql($t, $cols));
            $srcN = (int) $src->query('SELECT COUNT(*) FROM "' . str_replace('"', '""', $t) . '"')->fetchColumn();
            $dstN = _mig_copy_table($src, $dst, $t, $colNames);
            if ($dstN !== $srcN) {
                $res['error'] = "Row count mismatch on “{$t}” (file {$srcN}, MySQL {$dstN}). "
                              . 'Nothing was switched over — the workspace is still safely on its file.';
                return $res;
            }
            $res['tables'][] = ['name' => $t, 'src' => $srcN, 'dst' => $dstN, 'ok' => true];
            $res['created']++; $res['rows'] += $dstN;
            // Defer index creation until after all data is in, so a unique index
            // can never trip mid-copy.
            $typeOf = [];
            foreach ($cols as $c) $typeOf[$c['name']] = _mig_my_type($c['type'], (int) $c['pk'] > 0);
            foreach (_mig_sqlite_indexes($src, $t) as $ix) $indexJobs[] = [$t, $ix, $typeOf];
        } catch (Throwable $e) {
            $res['error'] = "Could not move table “{$t}”: " . $e->getMessage()
                          . ' Nothing was switched over — the workspace is still safely on its file.';
            return $res;
        }
    }
    // Indexes are a best-effort optimisation; a failure here does not endanger
    // the data that is already faithfully copied, so we note it and carry on.
    foreach ($indexJobs as [$t, $ix, $typeOf]) {
        try { $dst->exec(_mig_index_sql($t, $ix, $typeOf)); } catch (Throwable $e) {}
    }
    try { $dst->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $e) {}

    // 5) Everything copied and every count matched — repoint routing to MySQL.
    //    Durable store (control DB) first, then the cache file.
    $db = ['host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass];
    if (function_exists('saas_tenant_upsert')) {
        try { saas_tenant_upsert($key, ['route_json' => json_encode($db)]); } catch (Throwable $e) {}
    }
    if (function_exists('tenant_registry') && function_exists('tenant_registry_write')) {
        try {
            $reg = tenant_registry();
            $reg['tenants'][$key] = _mig_route_entry($reg['tenants'][$key] ?? null, $db);
            $werr = tenant_registry_write($reg);
            if ($werr !== '') {
                $res['error'] = 'The data was copied to MySQL, but the routing file could not be updated ('
                              . $werr . '). The workspace is still opening from its file for now.';
                return $res;
            }
        } catch (Throwable $e) {
            $res['error'] = 'The data was copied to MySQL, but routing could not be updated: ' . $e->getMessage();
            return $res;
        }
    }

    $res['ok'] = true;
    return $res;
}
