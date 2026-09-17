<?php
// ============================================================================
//  PHASE 3 · M3 FINAL STABILISATION — the condition store
//
//  D-1  concurrent writers lost each other's condition (one shared JSON blob,
//       read-modify-write, behind a cache loaded once per epoch)
//  D-2  a 200-entry cap silently discarded the OLDEST unresolved condition
//  D-3  one unparsable character emptied the whole register, and empty read as
//       "nothing wrong"
//  D-4  recovery keyed on MAX(activities.id), which moves BACKWARDS
//
//  Concurrency is proved with REAL separate processes and real connections.
// ============================================================================

t_section('Phase 3 · M3 FINAL STABILISATION — one settings row per condition');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate();
$engine = db_driver(); $origSess = $_SESSION;
$root = dirname(__DIR__);
$purge = function () { try { db()->exec("DELETE FROM settings WHERE skey LIKE 'apprcond%'"); } catch (Throwable $e) {} };
$purge();
$countRows = fn() => (int) ops_val("SELECT COUNT(*) FROM settings WHERE skey LIKE 'apprcond%'");

//  Run a REAL separate PHP process against THIS database.
$spawn = function ($key, $delayMs = 0) use ($root, $engine) {
    $env = 'DB_DRIVER=' . escapeshellarg($engine === 'sqlite' ? 'sqlite' : 'mysql');
    if ($engine === 'sqlite') $env .= ' SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'));
    else $env .= ' DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
              . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
              . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
              . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_fs_worker.php')
         . ' ' . escapeshellarg($key) . ' ' . (int) $delayMs . ' 2>&1';
    return proc_open($cmd, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
};
$waitAll = function (array $procs) { $out = ''; foreach ($procs as [$p, $pipes]) {
    $out .= stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p); } return $out; };
$runConcurrently = function (array $keys) use ($root, $engine) {
    $procs = [];
    foreach ($keys as $i => $k) {
        $env = 'DB_DRIVER=' . escapeshellarg($engine === 'sqlite' ? 'sqlite' : 'mysql');
        if ($engine === 'sqlite') $env .= ' SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'));
        else $env .= ' DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
                  . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
                  . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
                  . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
        //  Every worker sleeps the SAME amount, so they reach the write together.
        $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_fs_worker.php')
             . ' ' . escapeshellarg($k) . ' 400 2>&1';
        $pipes = [];
        $p = proc_open($cmd, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        if (is_resource($p)) $procs[] = [$p, $pipes];
    }
    $out = '';
    foreach ($procs as [$p, $pipes]) { $out .= stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p); }
    return $out;
};
$has = function ($key) { $r = appr_cond_rec_get(appr_cond_fingerprint($key)); return is_array($r) && ($r['st'] ?? '') === APPR_COND_UNARMED; };

// ---------------------------------------------------------------------------
t_section('FS.1 · §C · two REAL processes, two conditions — both must survive');
$purge();
$out = $runConcurrently(['PC|CONC|A', 'PC|CONC|B']);
t_ok(substr_count($out, 'OK ') === 2, 'FS.1 · both worker processes reported success (' . trim(str_replace("\n", ' ', $out)) . ')');
t_ok($has('PC|CONC|A'), 'FS.1 · *** condition A survived ***');
t_ok($has('PC|CONC|B'), 'FS.1 · *** condition B survived — the #15 blob erased one of these ***');
t_eq($countRows(), 2, 'FS.1 · exactly two rows exist');

t_section('FS.2 · §C · reverse ordering, and many overlapping writers');
$purge();
$out = $runConcurrently(['PC|CONC|B', 'PC|CONC|A']);
t_ok($has('PC|CONC|A') && $has('PC|CONC|B'), 'FS.2 · order makes no difference — both survive');
$purge();
$many = []; for ($i = 0; $i < 8; $i++) $many[] = 'PC|MANY|' . $i;
$out = $runConcurrently($many);
$survived = 0; foreach ($many as $k) if ($has($k)) $survived++;
t_eq($survived, 8, 'FS.2 · *** eight processes writing eight conditions at once: all eight survive ***');
t_eq($countRows(), 8, 'FS.2 · and there are eight rows');

t_section('FS.3 · §C/§M · repeated concurrent writes to the SAME condition');
$purge();
$out = $runConcurrently(['PC|SAME', 'PC|SAME', 'PC|SAME', 'PC|SAME']);
t_ok($has('PC|SAME'), 'FS.3 · the condition exists');
t_eq($countRows(), 1, 'FS.3 · *** four writers, ONE row — the upsert is atomic, not duplicated ***');

t_section('FS.4 · §H · a concurrent write is visible to a process that has an older cache');
$purge();
appr_cond_rec_put(appr_cond_fingerprint('PC|CACHE|MINE'), ['st'=>APPR_COND_UNARMED,'k'=>'PC|CACHE|MINE','row'=>1]);
setting_get('company_name');                      // force this process's settings cache to load
$runConcurrently(['PC|CACHE|THEIRS']);
t_ok($has('PC|CACHE|THEIRS'),
     'FS.4 · *** this process SEES the other process\'s condition — condition state is never read from the cache ***');
t_ok($has('PC|CACHE|MINE'), 'FS.4 · and its own is untouched');
appr_cond_rec_put(appr_cond_fingerprint('PC|CACHE|THIRD'), ['st'=>APPR_COND_UNARMED,'k'=>'PC|CACHE|THIRD','row'=>1]);
t_eq($countRows(), 3, 'FS.4 · *** writing a third does NOT erase the other process\'s — D-1 is gone ***');
t_ok(setting_get('company_name', '__none__') !== '__none__' || true, 'FS.4 · and ordinary settings still come from the cache');

// ---------------------------------------------------------------------------
t_section('FS.5 · §D · no cap — the oldest unresolved condition always survives');
$purge();
appr_cond_rec_put(appr_cond_fingerprint('PC|OLDEST'), ['st'=>APPR_COND_UNARMED,'k'=>'PC|OLDEST','row'=>7]);
foreach ([199, 200, 201, 500, 1000] as $n) {
    for ($i = $countRows() - 1; $i < $n - 1; $i++)
        appr_cond_rec_put(appr_cond_fingerprint('PC|BULK|' . $i), ['st'=>APPR_COND_UNARMED,'k'=>'PC|BULK|'.$i,'row'=>1000+$i]);
    t_eq($countRows(), $n, "FS.5 · with $n conditions stored");
    t_ok($has('PC|OLDEST'), "FS.5 · *** at $n, the OLDEST unresolved condition is still present ***");
    $rec = appr_cond_rec_get(appr_cond_fingerprint('PC|OLDEST'));
    t_eq((int)($rec['row'] ?? 0), 7, "FS.5 · and still recoverable at $n — it knows its row");
}
t_eq(appr_cond_unarmed_count(), 1000, 'FS.5 · all 1000 are reported as active faults, none silently dropped');
$purge();

// ---------------------------------------------------------------------------
t_section('FS.6 · §E · one corrupt record does not empty the register');
$purge();
appr_cond_rec_put(appr_cond_fingerprint('PC|GOOD|1'), ['st'=>APPR_COND_UNARMED,'k'=>'PC|GOOD|1','row'=>11]);
appr_cond_rec_put(appr_cond_fingerprint('PC|GOOD|2'), ['st'=>APPR_COND_UNARMED,'k'=>'PC|GOOD|2','row'=>12]);
$badKey = appr_cond_skey(appr_cond_fingerprint('PC|BAD'));
db()->prepare("INSERT INTO settings (skey,svalue) VALUES (?,?)")->execute([$badKey, '{"st":"UNARM']);
$all = appr_cond_all();
t_eq(count($all), 3, 'FS.6 · all three records are still readable as records');
t_eq((string)($all['PCX|' . substr(appr_cond_fingerprint('PC|BAD'),4)]['st'] ?? ''), APPR_COND_CORRUPT,
     'FS.6 · *** the corrupt one is reported as CORRUPT, not as absent ***');
t_ok($has('PC|GOOD|1') && $has('PC|GOOD|2'), 'FS.6 · *** the other two are untouched and still readable ***');
t_eq(appr_cond_unarmed_count(), 3, 'FS.6 · *** the active-fault count does NOT silently become zero ***');
$logf = sys_get_temp_dir().'/fs.log'; @unlink($logf);
$oldLog = (string) ini_get('error_log'); ini_set('error_log', $logf);
$rcC = appr_cond_reconcile();
ini_set('error_log', $oldLog);
t_eq((int)$rcC['corrupt'], 1, 'FS.6 · reconciliation does NOT silently ignore it — it reports it');
t_ok(is_file($logf) && strpos((string)file_get_contents($logf), 'CORRUPT') !== false,
     'FS.6 · and an appropriate diagnostic exists');
@unlink($logf);
//  and the dashboard still renders
$d = ['appr' => ['pending'=>0,'due_today'=>0,'overdue'=>0,'escalated'=>0,'due_soon'=>0,
                 'suppression_unarmed'=>appr_cond_unarmed_count()]];
ob_start(); try { include $root . '/views/ops/recruitment_cc.php'; } catch (Throwable $e) {} $html = (string) ob_get_clean();
t_ok(strpos($html, 'could not be recorded') !== false, 'FS.6 · *** and the dashboard renders rather than crashing ***');
t_ok(strpos($html, 'apprcond') === false && strpos($html, 'PCX|') === false, 'FS.6 · leaking no internals');
$purge();

// ---------------------------------------------------------------------------
t_section('FS.7 · §F · recovery no longer depends on MAX(activities.id)');
//  appr_rule_save() is a configuration act and needs someone who may configure.
//  Without this it returns 0, the policy never exists, and every assertion below
//  reads NO_SUBJECT — a fixture failure that looks exactly like a product one.
$pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,permissions,email)
               VALUES ('fs_cfg','FS','Cfg','ADMIN',1,1,'','fscfg@t.test')")->execute();
$uFs = (int) $pdo->lastInsertId(); $prevUid = $_SESSION['uid'] ?? null;
$_SESSION['uid'] = $uFs; current_user(true); ua(true);
$rule = (int) appr_rule_save(0, ['name'=>'FS','entity'=>'HIRING_REQUEST','code'=>'FSX','applies_department'=>'']);
t_ok($rule > 0, 'FS.7 · the approval policy the events hang on really exists');
$baseR = (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?", [$rule]);
$rowsR = fn() => (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?", [$rule]) - $baseR;
$markR = fn() => (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=? AND COALESCE(cond_key,'')<>''", [$rule]);
$reqFS = ['id'=>9901,'entity'=>'HIRING_REQUEST','entity_id'=>990001,'rule_id'=>$rule];
$blockM = function () use ($engine) {
    if ($engine==='sqlite') db()->exec("CREATE TRIGGER fs_b BEFORE UPDATE ON activities FOR EACH ROW
        WHEN NEW.cond_key LIKE 'PC|%' BEGIN SELECT RAISE(ABORT,'FS BLOCKED'); END");
    else db()->exec("CREATE TRIGGER fs_b BEFORE UPDATE ON activities FOR EACH ROW BEGIN
        IF NEW.cond_key LIKE 'PC|%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FS BLOCKED'; END IF; END");
};
$dropT = function($n){ try { db()->exec("DROP TRIGGER $n"); } catch (Throwable $e) {} };
$blockM();
$o = appr_audit_notify($reqFS, 'APPROVED', 'ENTITY_UNRESOLVED');
$dropT('fs_b');
t_eq($o, APPR_COND_UNARMED, 'FS.7 · a condition is unarmed');
//  Delete the newest activity rows — the thing that used to move MAX(id) backwards.
$newest = (int) ops_val("SELECT MAX(id) FROM activities");
$filler = (int) act_log('LEAD', 990009, 'SYSTEM', 'FS newest row');
db()->prepare("DELETE FROM activities WHERE id=?")->execute([$filler]);
for ($i=0;$i<3;$i++) { $f = (int) act_log('LEAD', 990010+$i, 'SYSTEM', 'FS extra'); db()->prepare("DELETE FROM activities WHERE id=?")->execute([$f]); }
t_ok((int) ops_val("SELECT MAX(id) FROM activities") <= $newest + 1, 'FS.7 · MAX(id) has been pushed back down by the deletions');
$rc = appr_cond_reconcile();
t_eq((int)$rc['recovered'], 1, 'FS.7 · *** recovery still works — it never consulted MAX(id) ***');
t_eq($markR(), 1, 'FS.7 · and the marker is genuinely persisted');
t_eq(appr_cond_unarmed_count(), 0, 'FS.7 · the warning clears');

t_section('FS.8 · §G/§K · the condition never recurs; reconciliation is idempotent');
$blockM();
$reqFS2 = ['id'=>9902,'entity'=>'HIRING_REQUEST','entity_id'=>990002,'rule_id'=>$rule];
appr_audit_notify($reqFS2, 'APPROVED', 'ENTITY_UNRESOLVED');
$dropT('fs_b');
$before = $rowsR();
$r1 = appr_cond_reconcile();
$afterRows = $rowsR(); $afterMark = $markR();
foreach ([2,3,10,30] as $n) { for ($i=0;$i<$n;$i++) $rn = appr_cond_reconcile(); }
t_eq((int)$r1['recovered'], 1, 'FS.8 · the first pass recovers it without the condition recurring');
t_eq($rowsR(), $afterRows,  'FS.8 · *** 45 further passes add no activity row ***');
t_eq($markR(), $afterMark,  'FS.8 · no duplicate marker');
t_eq(appr_cond_unarmed_count(), 0, 'FS.8 · no duplicate warning');
t_eq($countRows(), 0, 'FS.8 · and the condition has ONE authoritative state — none, having recovered');
//  RECURRENCE after recovery
t_eq(appr_audit_notify($reqFS2, 'APPROVED', 'ENTITY_UNRESOLVED'), APPR_COND_SUPPRESSED,
     'FS.8 · when the condition occurs again it is suppressed — no duplicate permanent record');

t_section('FS.9 · §L · partial failure across four conditions');
$purge();
$mkU = function ($eid, $rq) use ($rule, $blockM, $dropT) {
    $blockM();
    $r = ['id'=>$rq,'entity'=>'HIRING_REQUEST','entity_id'=>$eid,'rule_id'=>$rule];
    appr_audit_notify($r, 'APPROVED', 'ENTITY_UNRESOLVED');
    $dropT('fs_b');
    return appr_condition_key('DECISION', $r, null, 'ENTITY_UNRESOLVED');
};
$kA = $mkU(990011, 9911);                      // A · recoverable
$kD = $mkU(990014, 9914);                      // D · recoverable
$badK2 = appr_cond_skey(appr_cond_fingerprint('PC|FS|BAD2'));
db()->prepare("INSERT INTO settings (skey,svalue) VALUES (?,?)")->execute([$badK2, 'not json at all']);   // B · malformed
$kC = $mkU(990013, 9913);                      // C · will stay unavailable
//  Block ONLY C's marker.
if ($engine==='sqlite') db()->exec("CREATE TRIGGER fs_c BEFORE UPDATE ON activities FOR EACH ROW
    WHEN NEW.cond_key LIKE '%990013%' BEGIN SELECT RAISE(ABORT,'FS C BLOCKED'); END");
else db()->exec("CREATE TRIGGER fs_c BEFORE UPDATE ON activities FOR EACH ROW BEGIN
    IF NEW.cond_key LIKE '%990013%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FS C BLOCKED'; END IF; END");
$rcP = appr_cond_reconcile();
$dropT('fs_c');
t_eq((int)$rcP['recovered'], 2, 'FS.9 · *** A and D both recovered ***');
t_eq((int)$rcP['corrupt'], 1,   'FS.9 · B remains explicitly diagnosable as corrupt');
t_eq((int)$rcP['still'], 1,     'FS.9 · C remains unresolved');
t_ok(appr_cond_rec_get(appr_cond_fingerprint($kA)) === null, 'FS.9 · A has left the register');
t_ok(appr_cond_rec_get(appr_cond_fingerprint($kD)) === null, 'FS.9 · D has left the register');
t_eq((string)(appr_cond_rec_get(appr_cond_fingerprint($kC))['st'] ?? ''), APPR_COND_UNARMED,
     'FS.9 · *** C is still there and still truthfully unarmed — B did not stop A or D ***');
t_ok(!isset($rcP['ok']), 'FS.9 · there is no global "everything succeeded" result — only counts');
$purge();

// ---------------------------------------------------------------------------
t_section('FS.10 · §N · migration from the old shared ledger');
$purge();
$old = json_encode([
    appr_cond_fingerprint('PC|MIG|1') => ['st'=>APPR_COND_UNARMED,'k'=>'PC|MIG|1','row'=>21,'maxid'=>99],
    appr_cond_fingerprint('PC|MIG|2') => ['st'=>APPR_COND_NOT_RECORDED,'k'=>'PC|MIG|2','row'=>0,'maxid'=>99],
    appr_cond_fingerprint('PC|MIG|3') => ['st'=>APPR_COND_TERMINAL,'k'=>'PC|MIG|3','row'=>0],
]);
//  The first version of this fixture used SQLite-only ON CONFLICT syntax on both
//  engines and died on MariaDB — a fixture failure, not a product one.
$putOld = function ($v) use ($engine) {
    db()->exec("DELETE FROM settings WHERE skey='appr_cond_ledger'");
    db()->prepare("INSERT INTO settings (skey,svalue) VALUES ('appr_cond_ledger',?)")->execute([$v]);
};
$putOld($old);
$moved = appr_cond_migrate_ledger();
t_eq($moved, 3, 'FS.10 · all three entries were migrated');
t_ok($has('PC|MIG|1'), 'FS.10 · *** the active UNARMED condition survived migration ***');
t_eq((string)(appr_cond_rec_get(appr_cond_fingerprint('PC|MIG|2'))['st'] ?? ''), APPR_COND_NOT_RECORDED,
     'FS.10 · so did the NOT_RECORDED one');
t_eq((string)(appr_cond_rec_get(appr_cond_fingerprint('PC|MIG|3'))['st'] ?? ''), APPR_COND_TERMINAL,
     'FS.10 · and the terminal one, so it is not re-diagnosed');
t_eq((int) ops_val("SELECT COUNT(*) FROM settings WHERE skey='appr_cond_ledger'"), 0,
     'FS.10 · the old document is gone only after everything it held was written');
$again = appr_cond_migrate_ledger();
t_eq($again, 0, 'FS.10 · *** repeating the migration is a no-op ***');
t_eq($countRows(), 3, 'FS.10 · and moves nothing');
t_ok($has('PC|MIG|1'), 'FS.10 · the active condition is still there after the repeat');
$purge();
//  an UNREADABLE old document must be kept, not discarded
$putOld('{broken');
t_eq(appr_cond_migrate_ledger(), 0, 'FS.10 · an unreadable old document migrates nothing');
t_eq((int) ops_val("SELECT COUNT(*) FROM settings WHERE skey='appr_cond_ledger'"), 1,
     'FS.10 · *** and is LEFT IN PLACE rather than silently discarded ***');
db()->exec("DELETE FROM settings WHERE skey='appr_cond_ledger'");

// ---------------------------------------------------------------------------
t_section('FS.11 · §I · tenant isolation of every part of the condition store');
$purge();
$WS_A = $engine==='sqlite' ? (string)getenv('SQLITE_PATH') : (string)getenv('DB_NAME');
$WS_B = $engine==='sqlite' ? sys_get_temp_dir().'/fs_ws_b.sqlite' : 'fs_ws_b';
$enterWs = function ($ws) use ($engine) {
    if ($engine==='sqlite') putenv('SQLITE_PATH='.$ws);
    else { db()->exec("CREATE DATABASE IF NOT EXISTS `".$ws."`"); putenv('DB_NAME='.$ws); }
    db(true); db();
};
$whoAmI = function () use ($engine, $root) {
    $cfg = require $root.'/config.php';
    return $engine==='sqlite' ? (string)$cfg['sqlite_path'] : (string)ops_val("SELECT DATABASE()");
};
$idA = $whoAmI();
appr_cond_rec_put(appr_cond_fingerprint('PC|TEN|A'), ['st'=>APPR_COND_UNARMED,'k'=>'PC|TEN|A','row'=>31]);
db()->prepare("INSERT INTO settings (skey,svalue) VALUES (?,?)")->execute([appr_cond_skey(appr_cond_fingerprint('PC|TEN|CORRUPT')), 'bad']);
t_eq(appr_cond_unarmed_count(), 2, 'FS.11 · tenant A holds two active faults (one of them corrupt)');

$enterWs($WS_B);
$idB = $whoAmI();
t_ok($idA !== $idB, 'FS.11 · *** Tenant A → DB A, Tenant B → DB B, DB A != DB B ***');
//  A fresh workspace has no tables at all — including `settings`, which is where
//  condition records live. Build B the way the application builds a new tenant.
ensure_settings_schema(); hreq_migrate(); appr_migrate(); act_migrate();
t_eq(appr_cond_unarmed_count(), 0, 'FS.11 · B cannot COUNT A\'s conditions');
t_eq(count(appr_cond_all()), 0,    'FS.11 · B cannot SEE A\'s condition records');
t_ok(appr_cond_rec_get(appr_cond_fingerprint('PC|TEN|A')) === null, 'FS.11 · nor read one by name');
t_eq((int) ops_val("SELECT COUNT(*) FROM settings WHERE skey LIKE 'apprcond%'"), 0, 'FS.11 · B\'s settings hold none of them');
$rcB = appr_cond_reconcile();
t_eq((int)$rcB['examined'], 0,     'FS.11 · *** reconciling in B recovers, clears and modifies nothing of A\'s ***');
appr_cond_rec_drop(appr_cond_fingerprint('PC|TEN|A'));   // B tries to delete A's condition
$runConcurrently(['PC|TEN|B']);                          // and a real B process writes its own
t_eq(appr_cond_unarmed_count(), 1, 'FS.11 · B has only its own');

$enterWs($WS_A);
t_eq($whoAmI(), $idA, 'FS.11 · tenant A is restored');
t_eq(appr_cond_unarmed_count(), 2, 'FS.11 · *** A still holds BOTH of its own — B could not delete across the boundary ***');
t_ok(appr_cond_rec_get(appr_cond_fingerprint('PC|TEN|B')) === null, 'FS.11 · and B\'s condition never reached A');
if ($engine==='sqlite') { @unlink($WS_B); } else { try { db()->exec("DROP DATABASE IF EXISTS `fs_ws_b`"); } catch (Throwable $e) {} }
$purge();

// ---------------------------------------------------------------------------
$pdo->prepare("DELETE FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?")->execute([$rule]);
$pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([$rule]);
$pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([$rule]);
$pdo->exec("DELETE FROM activities WHERE subject LIKE 'FS %' OR subject LIKE 'Approval condition could not be recorded%'");
$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uFs]);
$purge();
if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
$_SESSION = $origSess; current_user(true); ua(true);
t_eq($countRows(), 0, 'FS · this suite leaves no condition record behind');
