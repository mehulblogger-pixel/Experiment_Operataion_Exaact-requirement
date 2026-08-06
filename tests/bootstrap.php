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
$tmp  = sys_get_temp_dir() . '/mgh_test_' . getmypid() . '_' . substr(md5($root), 0, 6) . '.sqlite';
@unlink($tmp);
putenv('DB_DRIVER=sqlite');
putenv('SQLITE_PATH=' . $tmp);
$GLOBALS['__test_db'] = $tmp;
register_shutdown_function(function () { if (!empty($GLOBALS['__test_db'])) @unlink($GLOBALS['__test_db']); });

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

fwrite(STDOUT, "bootstrap: app booted on a throwaway SQLite db (" . count($m[1]) . " libs)\n");
