<?php
// A REAL separate process that writes ONE condition record. Used by the final
// stabilisation suite to prove concurrency with actual, independent database
// connections rather than sequential calls inside one process.
//
// It deliberately does NOT use tests/bootstrap.php: that bootstrap DROPS EVERY
// TABLE on the MySQL path and creates a fresh file on the SQLite path, either of
// which would destroy the very database the concurrency test is watching. It
// attaches to the database the parent is using, exactly as the front controller
// would, and it creates nothing.
//
//   php tests/_fs_worker.php <condition-key> [delay-ms]
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'mgh-fs-worker';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) { require_once $root . $rel; }
$key   = (string) ($argv[1] ?? '');
$delay = (int) ($argv[2] ?? 0);
if ($key === '') { fwrite(STDERR, "no key\n"); exit(2); }
if ($delay > 0) usleep($delay * 1000);
try {
    appr_cond_rec_put(appr_cond_fingerprint($key),
                      ['st' => APPR_COND_UNARMED, 'k' => $key, 'row' => 1, 'why' => 'worker']);
    echo "OK $key\n";
} catch (Throwable $e) { fwrite(STDERR, "worker failed: " . $e->getMessage() . "\n"); exit(3); }
