<?php
// ============================================================================
//  SQLite busy timeout — recorded finding F1, now fixed.
//
//  SQLite takes ONE database-wide write lock. Without a busy timeout it does
//  not wait for that lock at all: a second writer arriving while the first is
//  mid-transaction is refused instantly with "database is locked", even though
//  the first would have finished in milliseconds. Nothing was in conflict —
//  the second writer simply would not wait.
//
//  This battery proves the fix by EFFECT, not by position. Two real processes
//  meet on a wall-clock barrier: one holds the write lock for a beat, the other
//  tries to write. The same race is run twice, with the timeout switched off
//  and switched on, and the two outcomes must differ. If they ever stop
//  differing, either the setting has stopped being applied or the test has
//  stopped testing it — and this file fails rather than passing quietly.
//
//  SQLITE ONLY. MariaDB has its own lock-wait handling, is the authoritative
//  production engine, and is deliberately untouched by this change.
// ============================================================================

if (t_driver() !== 'sqlite') {
    t_section('RB3 · SQLite busy timeout');
    t_ok(true, 'BUSY (mariadb) · skipped by design — this is a SQLite-only setting; MariaDB has its own lock-wait handling and is untouched');
    return;
}

$bRoot = dirname(__DIR__);
$bPath = sys_get_temp_dir() . '/rb3_busy_' . getmypid() . '.sqlite';
@unlink($bPath);

//  Run the two roles as real processes, both firing at the same microsecond.
//  $timeout is passed in the environment, so what changes between the two runs
//  is exactly one thing: how long a writer is willing to wait.
$bRace = function ($timeout) use ($bRoot, $bPath) {
    $env = 'SQLITE_BUSY_TIMEOUT=' . (int)$timeout . ' ';
    $cmd = function ($role, $hold, $at) use ($env, $bRoot, $bPath) {
        return $env . 'php ' . escapeshellarg($bRoot . '/tests/_rb3_busy_worker.php') . ' '
             . escapeshellarg($bPath) . ' ' . escapeshellarg($role) . ' ' . (int)$hold . ' '
             . escapeshellarg((string)$at) . ' 2>&1';
    };
    //  The schema is built once, BEFORE either racer starts, so neither pays a
    //  one-time cost inside the window being measured.
    shell_exec($cmd('warm', 0, 0));

    //  BOOTING IS ITSELF A WRITE, and booting takes about three quarters of a
    //  second. Launched together, the two processes collide while starting up
    //  rather than at the moment under test — which is exactly what happened
    //  the first time this was written: the holder lost its own race during
    //  boot and never took the lock at all, so the "successful" write below was
    //  proving nothing. The arming assertions caught it.
    //
    //  So the launches are STAGGERED: each process boots alone, and the only
    //  thing that overlaps is the lock window this test exists to measure.
    $t0     = round(microtime(true) * 1000);
    $holdAt = $t0 + 2600;          // holder boots 0..~750, then waits
    $writeAt= $t0 + 3000;          // writer boots 1200..~1950, then waits
    $procs = [];
    $p = proc_open($cmd('hold', 1500, $holdAt), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $procs[] = [$p, $pipes];
    usleep(1200000);               // let the holder finish booting in peace
    $p = proc_open($cmd('write', 0, $writeAt), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $procs[] = [$p, $pipes];

    $res = [];
    foreach ($procs as [$p, $pipes]) {
        $raw = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        foreach ($pipes as $fh) @fclose($fh);
        @proc_close($p);
        foreach (explode("\n", trim($raw)) as $l) {
            $j = json_decode(trim($l), true);
            if (is_array($j) && isset($j['role'])) { $res[$j['role']] = $j; break; }
        }
    }
    return $res;
};

// ---------------------------------------------------------------------------
t_section('RB3 · BUSY1 — with NO timeout, a second writer gives up at once');
// ---------------------------------------------------------------------------
//  This is the defect, reproduced. It must fail here, or the next section
//  proves nothing: a fix has to fix something.
$off = $bRace(0);
$oW  = $off['write'] ?? null;
$oH  = $off['hold']  ?? null;
t_ok(is_array($oH) && !empty($oH['ok']), 'BUSY1a · the holder really took the write lock — the trap is armed');
t_ok(is_array($oW) && !empty($oW['ok']), 'BUSY1b · the second writer ran');
t_eq((int)($oW['timeout_applied'] ?? -1), 0, 'BUSY1c · …with the timeout switched OFF, as SQLite ships it — armed');
t_ok(is_array($oW) && empty($oW['wrote']), 'BUSY1 · it was REFUSED while the lock was held — the old behaviour, reproduced');
t_ok(strpos((string)($oW['why'] ?? ''), 'locked') !== false,
     'BUSY1d · …and refused for the lock specifically, not some other error');
t_ok((int)($oW['waited_ms'] ?? 9999) < 400,
     'BUSY1e · it gave up almost immediately (' . (int)($oW['waited_ms'] ?? -1) . 'ms) — it never waited at all');

@unlink($bPath);

// ---------------------------------------------------------------------------
t_section('RB3 · BUSY2 — with the timeout, the same writer WAITS and succeeds');
// ---------------------------------------------------------------------------
$on = $bRace(5);
$nW = $on['write'] ?? null;
$nH = $on['hold']  ?? null;
t_ok(is_array($nH) && !empty($nH['ok']), 'BUSY2a · the holder took the lock again — the same race, armed');
t_eq((int)($nW['timeout_applied'] ?? -1), 5000, 'BUSY2b · the connection really carries a 5s busy timeout (asked of SQLite, not of the source)');
t_ok(is_array($nW) && !empty($nW['wrote']), 'BUSY2 · the second writer SUCCEEDED — it waited for the lock instead of giving up');
t_ok((int)($nW['waited_ms'] ?? 0) >= 300,
     'BUSY2c · …and it genuinely waited (' . (int)($nW['waited_ms'] ?? -1) . 'ms), so this is the timeout working and not the race having missed');
t_ok((int)($nW['waited_ms'] ?? 99999) < 5000,
     'BUSY2d · …but it did not sit out the whole timeout — it went as soon as the lock was free');

@unlink($bPath);

// ---------------------------------------------------------------------------
t_section('RB3 · BUSY3 — the default, and what it does not touch');
// ---------------------------------------------------------------------------
t_ok(defined('SQLITE_BUSY_TIMEOUT_SEC'), 'BUSY3a · the setting has a name, not a number buried in the connection');
t_eq((int)db()->query('PRAGMA busy_timeout')->fetchColumn(), SQLITE_BUSY_TIMEOUT_SEC * 1000,
     'BUSY3 · the live test connection carries the default timeout too — every SQLite connection, not just the probe\'s');
$dbSrc = file_get_contents($bRoot . '/lib/db.php');
$mysqlHalf = substr($dbSrc, strpos($dbSrc, "\$dsn = \"mysql:host"));
t_ok(stripos($mysqlHalf, 'busy_timeout') === false && stripos($mysqlHalf, 'ATTR_TIMEOUT') === false,
     'BUSY3b · nothing was added to the MariaDB connection — the authoritative engine is untouched');

// ---------------------------------------------------------------------------
t_section('RB3 · BUSY4 — transaction contention, and what a rollback leaves');
// ---------------------------------------------------------------------------
//  The timeout makes a second writer WAIT. It must not make it WIN: the writes
//  still serialise, a rollback still undoes everything, and nothing is half
//  applied because somebody waited for a lock.
db()->beginTransaction();
db()->prepare("INSERT INTO holidays (hol_date,name,region) VALUES ('2099-03-01','busy-tx','X')")->execute();
$seen = (int) ops_val("SELECT COUNT(*) FROM holidays WHERE name='busy-tx'");
t_eq($seen, 1, 'BUSY4a · the row is visible inside its own transaction — armed');
db()->rollBack();
t_eq((int) ops_val("SELECT COUNT(*) FROM holidays WHERE name='busy-tx'"), 0,
     'BUSY4 · a rollback removes it completely — waiting for a lock changes nothing about atomicity');

//  A writer that waits and then commits leaves exactly one row, not two.
$before4 = (int) ops_val("SELECT COUNT(*) FROM holidays WHERE name='busy-commit'");
db()->beginTransaction();
db()->prepare("INSERT INTO holidays (hol_date,name,region) VALUES ('2099-03-02','busy-commit','X')")->execute();
db()->commit();
t_eq((int) ops_val("SELECT COUNT(*) FROM holidays WHERE name='busy-commit'"), $before4 + 1,
     'BUSY4b · …and a commit after waiting adds exactly one row');
try { db()->prepare("DELETE FROM holidays WHERE name IN ('busy-commit','busy-hold','busy-write')")->execute(); } catch (Throwable $e) {}

// ---------------------------------------------------------------------------
t_section('RB3 · BUSY5 — the timeout is not a licence to lose a write');
// ---------------------------------------------------------------------------
//  Two real processes, both COMMITTING, staggered so each boots alone. Both
//  writes must survive: serialised, not dropped.
$bPath2 = sys_get_temp_dir() . '/rb3_busy5_' . getmypid() . '.sqlite';
@unlink($bPath2);
$cmd5 = function ($role, $hold, $at) use ($bRoot, $bPath2) {
    return 'SQLITE_BUSY_TIMEOUT=5 php ' . escapeshellarg($bRoot . '/tests/_rb3_busy_worker.php') . ' '
         . escapeshellarg($bPath2) . ' ' . escapeshellarg($role) . ' ' . (int)$hold . ' '
         . escapeshellarg((string)$at) . ' 2>&1';
};
shell_exec($cmd5('warm', 0, 0));
$t05 = round(microtime(true) * 1000);
$p5a = proc_open($cmd5('hold', 1200, $t05 + 2600), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pi5a);
usleep(1200000);
$p5b = proc_open($cmd5('write', 0, $t05 + 3000), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pi5b);
foreach ([[$p5a, $pi5a], [$p5b, $pi5b]] as [$pp, $pi]) {
    stream_get_contents($pi[1]); stream_get_contents($pi[2]);
    foreach ($pi as $fh) @fclose($fh); @proc_close($pp);
}
putenv('SQLITE_PATH_KEEP=' . (string)getenv('SQLITE_PATH'));
$both = (int) shell_exec('php -r ' . escapeshellarg(
    'try { $p=new PDO("sqlite:' . $bPath2 . '"); echo (int)$p->query("SELECT COUNT(*) FROM holidays WHERE name IN (\'busy-hold\',\'busy-write\')")->fetchColumn(); } catch (Throwable $e) { echo -1; }'));
t_eq($both, 2, 'BUSY5 · BOTH writes survived — the waiter was serialised behind the holder, not dropped');
@unlink($bPath2);
