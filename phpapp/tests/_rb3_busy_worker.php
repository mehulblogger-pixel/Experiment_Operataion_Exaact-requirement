<?php
// ============================================================================
//  SQLite busy-timeout probe — one role per process.
//
//    php tests/_rb3_busy_worker.php <sqlite-path> <role> <hold-ms> <start-at-ms>
//
//  roles
//    warm    boot the database so the schema exists, then exit
//    hold    take the single database-wide write lock and keep it <hold-ms>
//    write   wait for the barrier, then try ONE write and report what happened
//
//  The writer reports whether it succeeded and how long it waited, so the test
//  can tell "it waited for the lock and then worked" from "it gave up at once".
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'rb3-busy';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];

$path   = (string)($argv[1] ?? '');
$role   = (string)($argv[2] ?? '');
$holdMs = (int)   ($argv[3] ?? 0);
$startAt= (float) ($argv[4] ?? 0);

putenv('DB_DRIVER=sqlite'); putenv('SQLITE_PATH=' . $path);

$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$out = ['role' => $role, 'ok' => false];
try {
    db(true); db(); boot();                       // one-time cost, paid BEFORE the barrier
    $out['timeout_applied'] = (int) db()->query('PRAGMA busy_timeout')->fetchColumn();

    if ($role === 'warm') { $out['ok'] = true; echo json_encode($out) . "\n"; exit; }

    //  Everybody converges on the same wall-clock microsecond.
    if ($startAt > 0) { $w = $startAt - microtime(true) * 1000; if ($w > 0) usleep((int)($w * 1000)); }

    if ($role === 'hold') {
        db()->beginTransaction();
        db()->prepare("INSERT INTO holidays (hol_date,name,region) VALUES ('2099-01-01','busy-hold','X')")->execute();
        usleep($holdMs * 1000);                   // keep the write lock this long
        db()->commit();
        $out['ok'] = true;
    } elseif ($role === 'write') {
        $t0 = microtime(true);
        try {
            db()->prepare("INSERT INTO holidays (hol_date,name,region) VALUES ('2099-01-02','busy-write','X')")->execute();
            $out['wrote'] = true;
        } catch (Throwable $e) {
            $out['wrote'] = false;
            $out['why']   = strtolower($e->getMessage());
        }
        $out['waited_ms'] = (int) round((microtime(true) - $t0) * 1000);
        $out['ok'] = true;
    }
} catch (Throwable $e) { $out['error'] = $e->getMessage(); }
echo json_encode($out) . "\n";
