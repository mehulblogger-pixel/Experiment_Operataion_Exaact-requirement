<?php
// ============================================================================
//  BACKUP & RESTORE — a safety net every workspace owns, and can see.
//
//  Modelled on the Books app's backup engine, adapted to this app's SQL model.
//  A backup is a complete, compressed snapshot of a workspace's ENTIRE database
//  (every table, every row) written as one .json.gz file.
//
//  The one rule that makes it trustworthy:  BACKUPS ARE STORED ABOVE THE WEB
//  ROOT — in a folder next to (not inside) the app folder. So the update method
//  that deletes every file in the app folder and re-uploads a fresh copy cannot
//  touch the backups. They survive uploads, and a workspace can be restored from
//  one in a couple of clicks.
//
//  Works for both storage kinds:
//   • a MySQL workspace  — a downloadable copy of its data, and a restore point;
//   • a file (SQLite) workspace — the same, PLUS a real off-folder safety copy
//     that outlives a "delete everything and re-upload" update.
//
//  Design points carried over from Books:
//   • per-workspace folders            • gzip (~80% smaller)
//   • keep the last 15                 • 1-hour throttle on automatic backups
//   • a safety snapshot before every restore/import (never a one-way door)
//   • shown to each workspace's admin, not hidden in a super-admin console.
//
//  No exec()/shell — pure PHP file writes — so it works on locked-down shared
//  hosting (cPanel / mPanel / MilesWeb).
// ============================================================================

const BACKUP_MAX_KEEP    = 15;      // keep the newest N snapshots per workspace
const BACKUP_THROTTLE_S  = 3600;    // automatic backups no more often than hourly

// Who this backup belongs to: the workspace key, or a fixed name on the control
// install (which holds the routing directory — well worth backing up).
function backup_tenant_id() {
    $k = function_exists('current_tenant') ? (string) current_tenant() : '';
    $k = $k === '' ? '__control' : $k;
    return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $k);
}

// The root folder for ALL workspaces' backups. Preference order:
//   1) an operator-set absolute path (setting: backup_dir), if writable;
//   2) a folder ABOVE the app folder (outside the web root) — the safe default,
//      untouched by any upload into the app folder;
//   3) last resort: a folder inside the app folder (still useful for download,
//      though an upload could clear it) — flagged so the UI can warn.
// Returns [path, safe_bool].
function backup_root() {
    $tryMake = function ($dir) {
        if ($dir === '') return false;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) return false;
        if (!is_writable($dir)) return false;
        // Belt-and-braces: forbid web listing/serving if this ever sits in-root.
        $ht = $dir . '/.htaccess';
        if (!is_file($ht)) @file_put_contents($ht, "Options -Indexes\nRequire all denied\nOrder deny,allow\nDeny from all\n");
        return true;
    };
    $set = function_exists('setting_get') ? trim((string) setting_get('backup_dir', '')) : '';
    if ($set !== '' && $tryMake($set)) return [$set, true];

    $aboveRoot = dirname(dirname(__DIR__)) . '/exaact_backups';   // sibling of the app folder → above web root
    if ($tryMake($aboveRoot)) return [$aboveRoot, true];

    $inApp = dirname(__DIR__) . '/data-backups';                  // fallback, inside the app folder
    if ($tryMake($inApp)) return [$inApp, false];

    return ['', false];
}

// This workspace's own backup folder, and whether it is in the safe (off-folder)
// location. Returns [dir, safe_bool].
function backup_dir() {
    [$root, $safe] = backup_root();
    if ($root === '') return ['', false];
    $dir = $root . '/' . backup_tenant_id();
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) return ['', $safe];
    return [$dir, $safe];
}

// ---- The live database, generically (works on SQLite and MySQL) ------------
function backup_tables_list(PDO $pdo = null) {
    $pdo = $pdo ?: db();
    $drv = function_exists('db_driver') ? db_driver() : 'sqlite';
    try {
        if ($drv === 'sqlite') {
            $rows = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Throwable $e) { return []; }
    return array_values(array_filter((array) $rows, fn($n) => (string) $n !== ''));
}

function backup_table_columns(PDO $pdo, $table) {
    $drv = function_exists('db_driver') ? db_driver() : 'sqlite';
    $out = [];
    try {
        if ($drv === 'sqlite') {
            foreach ($pdo->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")')->fetchAll(PDO::FETCH_ASSOC) as $r)
                $out[] = (string) $r['name'];
        } else {
            foreach ($pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')->fetchAll(PDO::FETCH_ASSOC) as $r)
                $out[] = (string) $r['Field'];
        }
    } catch (Throwable $e) {}
    return $out;
}

// ---- Create a snapshot ------------------------------------------------------
// Returns ['id'=>filename, ...meta] on success, ['error'=>...] on failure,
// or ['skipped'=>true] when throttled.
function backup_create($reason = 'manual', $force = false) {
    [$dir, $safe] = backup_dir();
    if ($dir === '') return ['error' => 'No writable place to store backups was found on the server.'];
    $reason = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $reason)) ?: 'manual';

    if (!$force && _backup_recent_exists($dir, $reason, BACKUP_THROTTLE_S)) return ['skipped' => true];

    $pdo = db();
    $tables = backup_tables_list($pdo);
    if (!$tables) return ['error' => 'No tables were found to back up.'];

    $data = []; $counts = []; $total = 0;
    foreach ($tables as $t) {
        try {
            $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '', $t) . '`')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { $rows = []; }
        $data[$t] = $rows; $counts[$t] = count($rows); $total += count($rows);
    }

    $now = date('Y-m-d H:i:s');
    $payload = [
        'app'        => 'EXAACT',
        'version'    => 1,
        'created_at' => $now,
        'reason'     => $reason,
        'tenant'     => backup_tenant_id(),
        'company'    => function_exists('current_tenant_company') ? (string) current_tenant_company() : '',
        'driver'     => function_exists('db_driver') ? db_driver() : '',
        'tables'     => array_keys($data),
        'counts'     => $counts,
        'total_rows' => $total,
        'data'       => $data,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return ['error' => 'Could not encode the backup (a value could not be serialised).'];

    $ts = date('Ymd_His');
    $name = $ts . '_' . $reason . '.json.gz';
    $body = function_exists('gzencode') ? gzencode($json, 6) : $json;
    if ($body === false) { $body = $json; $name = $ts . '_' . $reason . '.json'; }
    $path = $dir . '/' . $name;
    if (@file_put_contents($path, $body) === false) return ['error' => 'Could not write the backup file.'];
    @chmod($path, 0640);
    _backup_cleanup($dir);

    return ['id' => $name, 'created_at' => $now, 'reason' => $reason, 'total_rows' => $total,
            'size_kb' => round(strlen($body) / 1024, 1), 'safe' => $safe, 'compressed' => str_ends_with($name, '.gz')];
}

// ---- List --------------------------------------------------------------------
function backup_list() {
    [$dir, $safe] = backup_dir();
    if ($dir === '') return [];
    $files = array_merge(glob($dir . '/*.json.gz') ?: [], glob($dir . '/*.json') ?: []);
    if (!$files) return [];
    // De-dupe a legacy .json that also has a .json.gz.
    $files = array_filter($files, fn($f) => str_ends_with($f, '.gz') || !in_array($f . '.gz', $files, true));
    usort($files, fn($a, $b) => strcmp(basename($b), basename($a)));   // newest first
    $out = [];
    foreach ($files as $f) {
        $meta = _backup_meta_only($f);
        $out[] = [
            'id'         => basename($f),
            'created_at' => $meta['created_at'] ?? date('Y-m-d H:i:s', (int) @filemtime($f)),
            'reason'     => $meta['reason'] ?? 'unknown',
            'total_rows' => $meta['total_rows'] ?? array_sum((array) ($meta['counts'] ?? [])),
            'size_kb'    => round((int) @filesize($f) / 1024, 1),
            'compressed' => str_ends_with($f, '.gz'),
        ];
    }
    return array_slice($out, 0, BACKUP_MAX_KEEP);
}

// Validate a backup id and resolve it to a path inside THIS workspace's folder.
function _backup_path($id) {
    if (!preg_match('/^[0-9]{8}_[0-9]{6}_[a-z0-9_]+\.json(\.gz)?$/', (string) $id)) return '';
    [$dir] = backup_dir();
    if ($dir === '') return '';
    $path = $dir . '/' . $id;
    return is_file($path) ? $path : '';
}

function _backup_read($path) {
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    if (str_ends_with($path, '.gz') && function_exists('gzdecode')) {
        $d = @gzdecode($raw);
        return $d !== false ? $d : $raw;
    }
    return $raw;
}

// Cheap header read: decode just enough for the list (whole file — snapshots are
// small once gzipped; the data array is ignored by the caller).
function _backup_meta_only($path) {
    $raw = _backup_read($path);
    if ($raw === null) return [];
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

// ---- Restore ----------------------------------------------------------------
// Replaces every table present in the snapshot with the snapshot's rows. Takes a
// safety snapshot first, runs inside a transaction, and rolls back on any error.
function backup_restore($id) {
    $path = _backup_path($id);
    if ($path === '') return ['error' => 'That backup could not be found.'];
    $raw = _backup_read($path);
    $backup = $raw ? json_decode($raw, true) : null;
    if (!is_array($backup) || !isset($backup['data']) || !is_array($backup['data']))
        return ['error' => 'That backup file is not readable.'];

    // Never a one-way door: snapshot the current state before overwriting it.
    backup_create('pre_restore', true);

    $pdo = db();
    $drv = function_exists('db_driver') ? db_driver() : 'sqlite';
    $live = backup_tables_list($pdo);
    $restored = 0; $tablesDone = 0;
    if ($drv !== 'sqlite') { try { $pdo->exec('SET FOREIGN_KEY_CHECKS=0'); } catch (Throwable $e) {} }
    $pdo->beginTransaction();
    try {
        foreach ($backup['data'] as $table => $rows) {
            if (!in_array($table, $live, true) || !is_array($rows)) continue;   // only tables that still exist
            $cols = backup_table_columns($pdo, $table);
            if (!$cols) continue;
            $tb = '`' . str_replace('`', '', $table) . '`';
            $pdo->exec('DELETE FROM ' . $tb);
            if (!$rows) { $tablesDone++; continue; }
            $collist = implode(',', array_map(fn($c) => '`' . str_replace('`', '', $c) . '`', $cols));
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $ins = $pdo->prepare('INSERT INTO ' . $tb . ' (' . $collist . ') VALUES (' . $ph . ')');
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $vals = [];
                foreach ($cols as $c) $vals[] = array_key_exists($c, $row) ? $row[$c] : null;
                $ins->execute($vals);
                $restored++;
            }
            $tablesDone++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        try { $pdo->rollBack(); } catch (Throwable $e2) {}
        if ($drv !== 'sqlite') { try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $e3) {} }
        return ['error' => 'Restore failed and nothing was changed: ' . $e->getMessage()];
    }
    if ($drv !== 'sqlite') { try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $e) {} }
    if (function_exists('licence_disabled')) licence_disabled(true);   // settings may have changed
    return ['restored' => $restored, 'tables' => $tablesDone, 'created_at' => $backup['created_at'] ?? ''];
}

// Raw bytes for download (always valid JSON — decompressed if needed).
function backup_export($id) {
    $path = _backup_path($id);
    if ($path === '') return null;
    return _backup_read($path);
}

// Restore from an uploaded backup file's JSON (a workspace can carry its data to
// another install, or recover from a copy kept off-server).
function backup_import_json($jsonString) {
    $backup = json_decode((string) $jsonString, true);
    if (!is_array($backup) || !isset($backup['data']) || !is_array($backup['data']))
        return ['error' => 'That file is not a valid EXAACT backup.'];
    // Persist it into this workspace's folder, then restore from it (which also
    // takes its own pre-restore safety snapshot).
    [$dir] = backup_dir();
    if ($dir === '') return ['error' => 'No writable place to store backups was found.'];
    $name = date('Ymd_His') . '_clientimport.json.gz';
    $json = json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $body = function_exists('gzencode') ? gzencode($json, 6) : $json;
    if ($body === false) { $body = $json; $name = str_replace('.json.gz', '.json', $name); }
    if (@file_put_contents($dir . '/' . $name, $body) === false) return ['error' => 'Could not store the uploaded backup.'];
    _backup_cleanup($dir);
    return backup_restore($name);
}

// ---- Automatic daily backup (throttled) ------------------------------------
// Called from the boot chain for a signed-in workspace. Cheap: one glob, once a
// day per session. Silent — never breaks a page if storage is unavailable.
function backup_auto_daily() {
    if (php_sapi_name() === 'cli') return;
    if (!function_exists('current_user') || !current_user()) return;   // only for a signed-in workspace
    if (isset($_SESSION['bk_day']) && $_SESSION['bk_day'] === date('Ymd')) return;
    $_SESSION['bk_day'] = date('Ymd');
    try {
        [$dir] = backup_dir();
        if ($dir === '') return;
        $today = glob($dir . '/' . date('Ymd') . '_*_daily.json*') ?: [];
        if (!$today) backup_create('daily', true);
    } catch (Throwable $e) {}
}

// ---- Housekeeping -----------------------------------------------------------
function _backup_recent_exists($dir, $reason, $seconds) {
    $files = array_merge(glob($dir . '/*.json.gz') ?: [], glob($dir . '/*.json') ?: []);
    $cut = time() - $seconds;
    foreach ($files as $f) if (@filemtime($f) >= $cut && strpos(basename($f), '_' . $reason) !== false) return true;
    return false;
}

function _backup_cleanup($dir) {
    $files = array_merge(glob($dir . '/*.json.gz') ?: [], glob($dir . '/*.json') ?: []);
    if (count($files) <= BACKUP_MAX_KEEP) return;
    usort($files, fn($a, $b) => strcmp(basename($a), basename($b)));   // oldest first
    foreach (array_slice($files, 0, count($files) - BACKUP_MAX_KEEP) as $f) @unlink($f);
}

// ============================================================================
//  The user-facing screen  (route: /backup)
// ============================================================================
function ops_backup($method) {
    ops_require((function_exists('is_master') && is_master()) || (function_exists('can') && can('settings.manage')),
        'Only a workspace administrator can manage backups.');

    if ($method === 'POST') {
        // CSRF is verified centrally in the front controller before dispatch.
        $do = (string) ($_POST['do'] ?? '');

        if ($do === 'backup_now') {
            $r = backup_create('manual', true);
            if (!empty($r['error'])) flash('Could not create a backup: ' . $r['error'], 'error');
            else flash('Backup created — ' . (int) ($r['total_rows'] ?? 0) . ' records saved'
                . (!empty($r['safe']) ? ' safely outside the app folder.' : '. (Note: stored inside the app folder — set a safe backup location for full upload protection.)'));
            redirect('/backup');
        }
        if ($do === 'restore') {
            $r = backup_restore((string) ($_POST['id'] ?? ''));
            if (!empty($r['error'])) flash('Restore did not run: ' . $r['error'], 'error');
            else flash('Restored ' . (int) $r['restored'] . ' records across ' . (int) $r['tables']
                . ' tables from the backup. (A safety copy of the previous state was saved first.)');
            redirect('/backup');
        }
        if ($do === 'import' && !empty($_FILES['file']['tmp_name']) && (int) $_FILES['file']['error'] === 0) {
            $raw = @file_get_contents($_FILES['file']['tmp_name']);
            if ($raw !== false && str_ends_with(strtolower((string) $_FILES['file']['name']), '.gz') && function_exists('gzdecode')) {
                $d = @gzdecode($raw); if ($d !== false) $raw = $d;
            }
            $r = backup_import_json($raw);
            if (!empty($r['error'])) flash('Import failed: ' . $r['error'], 'error');
            else flash('Imported and restored ' . (int) ($r['restored'] ?? 0) . ' records from the uploaded backup.');
            redirect('/backup');
        }
        redirect('/backup');
    }

    // Download a backup file (GET ?download=<id>).
    $dl = (string) ($_GET['download'] ?? '');
    if ($dl !== '') {
        $bytes = backup_export($dl);
        if ($bytes === null) { flash('That backup could not be found.', 'error'); redirect('/backup'); }
        $fname = preg_replace('/\.gz$/', '', $dl);
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    [$dir, $safe] = backup_dir();
    view('ops/backup', [
        'backups'  => backup_list(),
        'safe'     => $safe,
        'dir'      => $dir,
        'storage'  => (function_exists('current_tenant') && function_exists('saas_tenant_storage_info') && current_tenant() !== '')
                        ? saas_tenant_storage_info(current_tenant()) : null,
    ]);
}
