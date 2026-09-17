<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #12
//
//  X1  act_set_cond_key() returned STORED whenever PDO did not throw. An UPDATE
//      matching no row does not throw, so a nonexistent id and a cross-workspace
//      id both reported STORED having written nothing, anywhere.
//
//  X2  The four error channels were claimed to be workspace-keyed, but only the
//      CORE channel was ever exercised: the other three were empty when checked,
//      so their isolation assertions passed vacuously.
//
//  X3  The #11 suite's "workspace switching" assigned $GLOBALS['__db_epoch'] and
//      never switched database. Tenant A and Tenant B were the same database.
//
//  Everything below switches workspace through the application's REAL path —
//  db(true) after repointing the connection — and proves the database identity
//  changed before making any claim about isolation.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #12 — real workspaces, real persistence');

$engine = db_driver();
$root   = dirname(__DIR__);
$homeSqlite = (string) getenv('SQLITE_PATH');
$homeMysql  = (string) getenv('DB_NAME');
$made = [];

//  X3 — the real switch. Repoint the connection the way the application does and
//  let db(true) rebuild it from config; db() then re-reads config.php, so this is
//  the same path "Log in as", provisioning and the cron tenant sweep take.
$enterWs = function ($ws) use ($engine, &$made) {
    if ($engine === 'sqlite') {
        putenv('SQLITE_PATH=' . $ws);
    } else {
        if (!in_array($ws, $made, true) && $ws !== getenv('DB_NAME')) {
            db()->exec("CREATE DATABASE IF NOT EXISTS `" . $ws . "`");
            $made[] = $ws;
        }
        putenv('DB_NAME=' . $ws);
    }
    db(true); db();
};
//  Identity read from the LIVE connection, never from what we think we set.
$whoAmI = function () use ($root, $engine) {
    $cfg = require $root . '/config.php';
    return $engine === 'sqlite' ? (string) $cfg['sqlite_path'] : (string) ops_val("SELECT DATABASE()");
};
$WS_A = $engine === 'sqlite' ? $homeSqlite : $homeMysql;
$WS_B = $engine === 'sqlite' ? sys_get_temp_dir() . '/c12_b_' . getmypid() . '.sqlite' : 'c12_b_' . getmypid();
$WS_C = $engine === 'sqlite' ? sys_get_temp_dir() . '/c12_c_' . getmypid() . '.sqlite' : 'c12_c_' . getmypid();
if ($engine === 'sqlite') { @unlink($WS_B); @unlink($WS_C); }
$ready = function () { boot(); act_migrate(); act_cond_column_ready(); act_cond_index_ready(); };
$blockIns = function ($mark) use ($engine) {
    db()->exec($engine === 'sqlite'
        ? "CREATE TRIGGER c12b BEFORE INSERT ON activities BEGIN SELECT RAISE(ABORT, '$mark'); END"
        : "CREATE TRIGGER c12b BEFORE INSERT ON activities FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '$mark'");
};
$blockUpd = function ($mark) use ($engine) {
    db()->exec($engine === 'sqlite'
        ? "CREATE TRIGGER c12u BEFORE UPDATE ON activities BEGIN SELECT RAISE(ABORT, '$mark'); END"
        : "CREATE TRIGGER c12u BEFORE UPDATE ON activities FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '$mark'");
};
$unblock = function ($n) { try { db()->exec("DROP TRIGGER $n"); } catch (Throwable $e) {} };

// ---------------------------------------------------------------------------
//  C12.1 · X3 — the workspaces are REAL and DIFFERENT, proved before anything
// ---------------------------------------------------------------------------
t_section('C12.1 · X3 · real workspace switching — ' . strtoupper($engine));
$enterWs($WS_A); act_migrate(); $idA = $whoAmI();
$enterWs($WS_B); $ready();      $idB = $whoAmI();
$enterWs($WS_C); $ready();      $idC = $whoAmI();
t_ok($idA !== '' && $idB !== '' && $idC !== '', 'C12.1 · every workspace names the database it is really on');
t_ok($idA !== $idB, 'C12.1 · *** DB A is not DB B *** — the test fails if they are the same');
t_ok($idB !== $idC, 'C12.1 · DB B is not DB C');
t_ok($idA !== $idC, 'C12.1 · DB A is not DB C');
//  every switch verified by reading the LIVE connection back
$seq = [];
foreach (['A'=>$WS_A, 'B'=>$WS_B, 'A2'=>$WS_A, 'B2'=>$WS_B, 'C'=>$WS_C, 'A3'=>$WS_A] as $tag => $ws) {
    $enterWs($ws); $seq[$tag] = ['db' => $whoAmI(), 'epoch' => db_epoch()];
}
t_eq($seq['A']['db'],  $idA, 'C12.1 · A → B → A → B → C → A · each hop lands on the right database (A)');
t_eq($seq['B2']['db'], $idB, 'C12.1 · …and on B when it should');
t_eq($seq['C']['db'],  $idC, 'C12.1 · …and on C when it should');
t_eq($seq['A3']['db'], $idA, 'C12.1 · …and back on A at the end');
t_eq(count(array_unique(array_column($seq, 'epoch'))), 6, 'C12.1 · six real switches gave six distinct epochs — no reuse');

// ---------------------------------------------------------------------------
//  C12.2 · X1 — STORED means the value is really on the record
// ---------------------------------------------------------------------------
t_section('C12.2 · X1 · STORED must mean persisted');
$enterWs($WS_A);
$id = act_log('LEAD', 760001, 'SYSTEM', 'C12 real row', ['auto'=>1]);
t_ok($id > 0, 'C12.2 · precondition · the target record genuinely exists');
t_eq(act_set_cond_key($id, 'C12_OK_001'), ACT_COND_STORED, 'C12.2 · a real write reports STORED');
t_eq((string) ops_val("SELECT cond_key FROM activities WHERE id=?", [$id]), 'C12_OK_001',
     'C12.2 · and the value is READ BACK from the database — not inferred');

//  the MariaDB trap: re-writing the SAME value reports zero affected rows there.
//  A rowCount()-based fix would call this a failure. It is a success.
t_eq(act_set_cond_key($id, 'C12_OK_001'), ACT_COND_STORED,
     'C12.2 · an IDEMPOTENT re-write is still STORED — rowCount() would have lied here on MariaDB');
t_eq((string) ops_val("SELECT cond_key FROM activities WHERE id=?", [$id]), 'C12_OK_001', 'C12.2 · value still right');

//  zero-row: the id does not exist
$ghost = 76600000 + random_int(1, 9999);
t_ok(!ops_one("SELECT id FROM activities WHERE id=?", [$ghost]), 'C12.2 · precondition · the ghost id really does not exist');
$resG = act_set_cond_key($ghost, 'C12_GHOST');
t_ok($resG !== ACT_COND_STORED, 'C12.2 · *** a zero-row UPDATE is NOT STORED ***');
t_eq($resG, ACT_COND_FAILED,    'C12.2 · it reports FAILED');
t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE cond_key=?", ['C12_GHOST']), 0, 'C12.2 · and nothing was written');
t_ok(strpos(act_optional_error(), 'no such activity row') !== false, 'C12.2 · with a reason that names the cause');

// ---------------------------------------------------------------------------
//  C12.3 · X1 — a CROSS-WORKSPACE id must never report STORED
// ---------------------------------------------------------------------------
t_section('C12.3 · X1 · cross-workspace persistence');
$enterWs($WS_B);
t_eq($whoAmI(), $idB, 'C12.3 · precondition · workspace B is genuinely active');
t_ok(!ops_one("SELECT id FROM activities WHERE id=?", [$id]), 'C12.3 · precondition · A\'s row id does not exist in B');
$resX = act_set_cond_key($id, 'C12_CROSS');
t_ok($resX !== ACT_COND_STORED, 'C12.3 · *** a cross-workspace id is NOT STORED ***');
t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE cond_key=?", ['C12_CROSS']), 0, 'C12.3 · nothing written in B');
$enterWs($WS_A);
t_eq((string) ops_val("SELECT cond_key FROM activities WHERE id=?", [$id]), 'C12_OK_001',
     'C12.3 · and A\'s row was never touched from B — isolation intact');

// ---------------------------------------------------------------------------
//  C12.4 · X2 — FOUR CHANNELS, EACH PROVED ON ITS OWN
//
//  The #11 suite asserted the column, index and write channels were empty in B —
//  but A had only ever produced a CORE error, so those channels were empty
//  anyway. The assertions could not fail. Here each channel is given a REAL
//  error in A first, and the fixture proves it is there before the switch.
// ---------------------------------------------------------------------------
$blockIdx = function () use ($engine) {
    if ($engine === 'sqlite') { db()->exec("CREATE TABLE idx_act_cond (x INT)"); return 0; }
    $have = (int) ops_val("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics
                           WHERE table_schema=DATABASE() AND table_name='activities'");
    $n = 64 - $have; $f = [];
    for ($i = 1; $i <= $n; $i++) $f[] = "ADD INDEX c12fill$i (auto)";
    if ($f) db()->exec("ALTER TABLE activities " . implode(', ', $f));
    return $n;
};
$unblockIdx = function ($n) use ($engine) {
    if ($engine === 'sqlite') { try { db()->exec("DROP TABLE idx_act_cond"); } catch (Throwable $e) {} return; }
    $d = []; for ($i = 1; $i <= $n; $i++) $d[] = "DROP INDEX c12fill$i";
    if ($d) { try { db()->exec("ALTER TABLE activities " . implode(', ', $d)); } catch (Throwable $e) {} }
};
$dropIdx = function () use ($engine) {
    try { db()->exec($engine === 'sqlite' ? "DROP INDEX IF EXISTS idx_act_cond" : "DROP INDEX idx_act_cond ON activities"); } catch (Throwable $e) {}
};
$hideTable = function () use ($engine) {
    db()->exec($engine === 'sqlite' ? "ALTER TABLE activities RENAME TO c12_hidden" : "RENAME TABLE activities TO c12_hidden");
};
$showTable = function () use ($engine) {
    db()->exec($engine === 'sqlite' ? "ALTER TABLE c12_hidden RENAME TO activities" : "RENAME TABLE c12_hidden TO activities");
};

//  Each entry PRODUCES a genuine error in that channel and RETURNS it, captured
//  BEFORE any repair — the first version of this fixture repaired the schema
//  inside make(), and a successful repair CLEARS the error it had just produced,
//  so the fixture defeated itself and the assertion read an empty channel. That
//  is the very false-green X2 is about, reproduced by accident while testing it.
$channels = [
    'core' => [
        'read' => fn() => act_last_error(),
        'make' => function ($mark) use ($blockIns, $unblock) {
            $blockIns($mark);
            try { act_log('LEAD', 765001, 'SYSTEM', 'c12 channel core'); } finally { $unblock('c12b'); }
            return act_last_error();
        },
        'repair' => function () {},
    ],
    'column' => [
        'read' => fn() => act_optional_error(),
        'make' => function ($mark) use ($dropIdx, $hideTable, $showTable) {
            $dropIdx();
            try { db()->exec("ALTER TABLE activities DROP COLUMN cond_key"); } catch (Throwable $e) {}
            $hideTable();
            try { act_cond_column_ready(); $e = act_optional_error(); } finally { $showTable(); }
            return $e;
        },
        'repair' => function () { act_cond_column_ready(); act_cond_index_ready(); },
    ],
    'index' => [
        'read' => fn() => act_optional_index_error(),
        'make' => function ($mark) use ($dropIdx, $blockIdx, $unblockIdx) {
            $dropIdx(); $n = $blockIdx();
            try { act_cond_index_ready(); $e = act_optional_index_error(); } finally { $unblockIdx($n); }
            return $e;
        },
        'repair' => function () { act_cond_index_ready(); },
    ],
    'write' => [
        'read' => fn() => act_optional_error(),
        'make' => function ($mark) use ($blockUpd, $unblock) {
            $row = act_log('LEAD', 765002, 'SYSTEM', 'c12 channel write', ['auto'=>1]);
            $blockUpd($mark);
            try { act_set_cond_key($row, 'C12_CH'); } finally { $unblock('c12u'); }
            return act_optional_error();
        },
        'repair' => function () {},
    ],
];

foreach ($channels as $name => $ch) {
    t_section('C12.4 · X2 · the ' . strtoupper($name) . ' channel, on its own');

    //  1 · A produces a genuine error IN THIS CHANNEL, captured before repair.
    $enterWs($WS_A);
    $inA = (string) $ch['make']('C12_' . strtoupper($name) . '_A');
    t_ok($inA !== '', "C12.4 $name · A really produced an error in THIS channel — the fixture is not empty");
    $ch['repair']();

    //  2 · B cannot see it. This is meaningful only because step 1 proved the
    //      channel was non-empty in A — the #11 suite skipped that and checked a
    //      channel that had never held anything.
    $enterWs($WS_B);
    t_eq($whoAmI(), $idB, "C12.4 $name · workspace B is genuinely active");
    t_eq((string) $ch['read'](), '', "C12.4 $name · *** B cannot see A's " . $name . " error ***");

    //  3 · B records its OWN in the same channel.
    $inB = (string) $ch['make']('C12_' . strtoupper($name) . '_B');
    t_ok($inB !== '', "C12.4 $name · B records its own error in the same channel");
    $ch['repair']();

    //  4 · and re-entering A never yields B's. (Per the #11 contract a real
    //      re-entry is a new epoch, so A reads empty — asserted as such, not
    //      assumed.)
    $enterWs($WS_A);
    $back = (string) $ch['read']();
    t_ok(strpos($back, 'C12_' . strtoupper($name) . '_B') === false, "C12.4 $name · and B's never appears in A");
    $ch['repair']();
}

// ---------------------------------------------------------------------------
//  Clean up — restore the original workspace and remove what this suite made
// ---------------------------------------------------------------------------
$enterWs($WS_A);
db()->exec("DELETE FROM activities WHERE subject LIKE 'C12 %' OR subject LIKE 'c12 %'");
act_cond_column_ready(); act_cond_index_ready();
if ($engine === 'sqlite') { @unlink($WS_B); @unlink($WS_C); }
else { foreach ([$WS_B, $WS_C] as $d) { try { db()->exec("DROP DATABASE IF EXISTS `" . $d . "`"); } catch (Throwable $e) {} } }
t_eq($whoAmI(), $idA, 'C12 · the original workspace is restored');
t_ok(act_has_cond_column() && act_has_cond_index(), 'C12 · and its spine is left whole');
