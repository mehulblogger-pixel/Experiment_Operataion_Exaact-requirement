<?php
// ============================================================================
//  Test bootstrap — boot the REAL application against a throwaway database.
//
//  Nothing here touches production. It points the app at a fresh SQLite file in
//  the system temp dir (via the DB_DRIVER / SQLITE_PATH overrides config.php
//  already honours), requires every library the front controller requires, in
//  the same order, and runs boot() so all migrations + seed run. A full clean
//  boot on an empty database is, by itself, a strong smoke test: it catches
//  migration errors, missing functions and schema drift.
//
//  These files are never referenced by index.php, so they cannot affect a live
//  install. Run with:  php tests/run.php
// ============================================================================

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);  // libs assume a web context
ini_set('display_errors', '1');

$root = dirname(__DIR__);                                          // the phpapp/ folder

// M15 — the suite can be pointed at the PRODUCTION engine.
//
// Production is MySQL/MariaDB; SQLite is the fast local stand-in. Until M15 the
// suite could only ever run on SQLite, so "MySQL not executed" followed every
// milestone report. Setting DB_DRIVER=mysql (with DB_HOST/DB_NAME/DB_USER/DB_PASS)
// before running now points the WHOLE suite at a real server instead.
//
// The SQLite path is untouched and remains the default, so an ordinary
// `php tests/run.php` behaves exactly as it always has. The MySQL path drops
// every table first, because these tests assume they are starting from nothing —
// which is also why it must NEVER be aimed at a database anyone cares about.
if (strtolower((string) getenv('DB_DRIVER')) === 'mysql') {
    $name = (string) getenv('DB_NAME');
    if ($name === '') { fwrite(STDERR, "bootstrap: DB_DRIVER=mysql needs DB_NAME\n"); exit(2); }
    $dsn = 'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';dbname=' . $name . ';charset=utf8mb4';
    try { $wipe = new PDO($dsn, (string) getenv('DB_USER'), (string) getenv('DB_PASS')); }
    catch (Throwable $e) { fwrite(STDERR, "bootstrap: cannot reach MySQL — " . $e->getMessage() . "\n"); exit(2); }
    $wipe->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($wipe->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $wipe->exec('DROP TABLE IF EXISTS `' . $t . '`');
    }
    $wipe->exec('SET FOREIGN_KEY_CHECKS=1');
    $wipe = null;
    $GLOBALS['__test_engine'] = 'mysql';
    // Nineteen test files ask "did the runner boot me, or am I being run on my
    // own?" by checking $GLOBALS['__test_db']. That flag must mean the same thing
    // on both engines, or every one of them re-requires the harness and dies on
    // a redeclare — so it is set here too. Nothing unlinks it: the shutdown
    // handler that deletes a throwaway file is registered on the SQLite path
    // alone, and this value is a database name, not a path.
    $GLOBALS['__test_db'] = 'mysql:' . $name;
} else {
    $tmp  = sys_get_temp_dir() . '/mgh_test_' . getmypid() . '_' . substr(md5($root), 0, 6) . '.sqlite';
    @unlink($tmp);
    putenv('DB_DRIVER=sqlite');
    putenv('SQLITE_PATH=' . $tmp);
    $GLOBALS['__test_db'] = $tmp;
    $GLOBALS['__test_engine'] = 'sqlite';
    register_shutdown_function(function () { if (!empty($GLOBALS['__test_db'])) @unlink($GLOBALS['__test_db']); });
}

// Minimal web-context globals a few helpers read while migrating/seeding.
$_SERVER['REMOTE_ADDR']     = $_SERVER['REMOTE_ADDR']     ?? '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? 'mgh-tests';
$_SERVER['REQUEST_URI']     = $_SERVER['REQUEST_URI']     ?? '/';
$_SERVER['HTTP_HOST']       = $_SERVER['HTTP_HOST']       ?? 'localhost';
if (!isset($_SESSION)) $_SESSION = [];                            // no real session under CLI

// Require exactly what the front controller requires, discovered from index.php
// itself so this never drifts out of step with the app.
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \\. '(/lib/[a-z0-9_]+\\.php)';#i", $idx, $m);
if (empty($m[1])) { fwrite(STDERR, "bootstrap: could not read the lib require list from index.php\n"); exit(2); }
foreach ($m[1] as $rel) { require_once $root . $rel; }

// Build the whole schema + seed on the throwaway database.
boot();

fwrite(STDOUT, "bootstrap: app booted on a throwaway " . strtoupper($GLOBALS['__test_engine']) . " db (" . count($m[1]) . " libs)\n");
