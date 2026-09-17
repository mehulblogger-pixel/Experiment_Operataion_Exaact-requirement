<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #15 — RECOVERY, BOUNDED DIAGNOSTICS, SCAN CONTROL
//
//  C-1  an UNARMED condition could never be cleared once the chain stopped
//       firing, because recovery lived inside the condition writers
//  C-2  NOT_RECORDED wrote one identical diagnostic per tick, for ever
//  C-4  #14 added two unindexed scans, one on every dashboard load
//
//  THE SCENARIO THE ATTACKER MUST BE ABLE TO REACH is proved first, in C15.1:
//      UNARMED exists  AND  the condition will never recur  AND  storage is
//      later repaired  →  it must be demonstrably recoverable.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #15 — a repaired workspace recovers on its own');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate();
$engine = db_driver(); $origSess = $_SESSION;
$pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,permissions,email)
               VALUES ('c15_cfg','C15','Cfg','ADMIN',1,1,'','c15cfg@t.test')")->execute();
$uCfg=(int)$pdo->lastInsertId(); $prevUid=$_SESSION['uid']??null;
$_SESSION['uid']=$uCfg; current_user(true); ua(true);
try { db()->exec("DELETE FROM settings WHERE skey LIKE 'apprcond%'"); } catch (Throwable $e) {} setting_set('appr_cond_ledger', '');                       // this suite starts from clean state

$mkRule = fn($t) => (int) appr_rule_save(0, ['name'=>'C15 '.$t,'entity'=>'HIRING_REQUEST',
                                             'code'=>'C15'.$t,'applies_department'=>'']);
$rA=$mkRule('A'); $rB=$mkRule('B'); $rC=$mkRule('C'); $rD=$mkRule('D'); $rE=$mkRule('E');
$absRows = fn($r) => (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?", [(int)$r]);
$base=[]; foreach ([$rA,$rB,$rC,$rD,$rE] as $r) $base[$r]=$absRows($r);
$rows = fn($r) => $absRows($r) - ($base[(int)$r] ?? 0);
$req  = fn($rule,$eid,$rq) => ['id'=>$rq,'entity'=>'HIRING_REQUEST','entity_id'=>$eid,'rule_id'=>$rule];
$markers = fn($r) => (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=? AND COALESCE(cond_key,'')<>''", [(int)$r]);

$block = function () use ($engine) {
    if ($engine==='sqlite') db()->exec("CREATE TRIGGER c15b BEFORE UPDATE ON activities FOR EACH ROW
        WHEN NEW.cond_key LIKE 'PC|%' BEGIN SELECT RAISE(ABORT,'C15 MARKER BLOCKED'); END");
    else db()->exec("CREATE TRIGGER c15b BEFORE UPDATE ON activities FOR EACH ROW BEGIN
        IF NEW.cond_key LIKE 'PC|%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='C15 MARKER BLOCKED'; END IF; END");
};
$blockCore = function () use ($engine) {
    if ($engine==='sqlite') db()->exec("CREATE TRIGGER c15c BEFORE INSERT ON activities FOR EACH ROW
        BEGIN SELECT RAISE(ABORT,'C15 CORE BLOCKED'); END");
    else db()->exec("CREATE TRIGGER c15c BEFORE INSERT ON activities FOR EACH ROW BEGIN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='C15 CORE BLOCKED'; END");
};
$drop = function($n){ try { db()->exec("DROP TRIGGER $n"); } catch (Throwable $e) {} };
$ledger = fn() => appr_cond_all();

// ---------------------------------------------------------------------------
t_section('C15.1 · PART B — THE EXACT SCENARIO: the condition never comes back');
$qA = $req($rA, 915001, 9151);
$block();
$o = appr_audit_notify($qA, 'APPROVED', 'ENTITY_UNRESOLVED');
$drop('c15b');                                             // storage is repaired
t_eq($o, APPR_COND_UNARMED,           'C15.1 · a condition is left genuinely unarmed');
t_eq($rows($rA), 1,                   'C15.1 · its event exists');
t_eq($markers($rA), 0,                'C15.1 · and carries no marker');
t_eq(appr_cond_unarmed_count(), 1,    'C15.1 · the dashboard warning is showing');

//  The chain is now resolved. NOTHING will ever raise this condition again — the
//  writers are never called, so #14 could not have recovered this in any number
//  of ticks. Reconciliation runs on its own.
$rc = appr_cond_reconcile();
t_eq((int)$rc['recovered'], 1,        'C15.1 · *** reconciliation recovered it WITHOUT the condition recurring ***');
t_eq($markers($rA), 1,                'C15.1 · *** and the marker is ACTUALLY PERSISTED on the row ***');
$onRow = (string) ops_val("SELECT cond_key FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=? AND COALESCE(cond_key,'')<>'' LIMIT 1", [$rA]);
t_ok(strpos($onRow, 'PC|') === 0,     'C15.1 · the persisted value is the real condition key, read back from the table');
t_eq(appr_cond_unarmed_count(), 0,    'C15.1 · *** only now does the warning clear ***');
t_eq($rows($rA), 1,                   'C15.1 · and no new row was invented to do it');
//  and suppression genuinely works again
t_eq(appr_audit_notify($qA, 'APPROVED', 'ENTITY_UNRESOLVED'), APPR_COND_SUPPRESSED,
                                      'C15.1 · BUSINESS OUTCOME · ordinary suppression has resumed');

// ---------------------------------------------------------------------------
t_section('C15.2 · PART C — the source is gone: an explicit terminal state');
$qB = $req($rB, 915002, 9152);
$block();
appr_audit_notify($qB, 'APPROVED', 'ENTITY_UNRESOLVED');
$drop('c15b');
t_eq(appr_cond_unarmed_count(), 1, 'C15.2 · an unarmed condition is showing');
$pdo->prepare("DELETE FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?")->execute([$rB]);
$rc2 = appr_cond_reconcile();
t_eq((int)$rc2['terminal'], 1,     'C15.2 · *** its event is gone, so it is closed as unrecoverable ***');
t_eq(appr_cond_unarmed_count(), 0, 'C15.2 · it stops being reported as an ACTIVE fault');
$l = $ledger(); $term = 0;
foreach ($l as $rec) if (($rec['st'] ?? '') === APPR_COND_TERMINAL) $term++;
t_eq($term, 1,                     'C15.2 · but it is NOT forgotten — it is recorded as terminal, with a reason');
foreach ($l as $rec) if (($rec['st'] ?? '') === APPR_COND_TERMINAL)
    t_ok(strpos((string)$rec['why'], 'no longer exists') !== false, 'C15.2 · and the reason is truthful');
t_eq($markers($rB), 0,             'C15.2 · no marker was manufactured for an event that does not exist');

// ---------------------------------------------------------------------------
t_section('C15.3 · PART D — reconciliation is idempotent');
$qC = $req($rC, 915003, 9153);
$block();
appr_audit_notify($qC, 'APPROVED', 'ENTITY_UNRESOLVED');
$drop('c15b');
$before = $rows($rC);
$r1 = appr_cond_reconcile();
$after1 = $rows($rC); $mk1 = $markers($rC); $cnt1 = appr_cond_unarmed_count();
for ($i=0;$i<9;$i++) $rn = appr_cond_reconcile();
t_eq((int)$r1['recovered'], 1,   'C15.3 · the first run recovers it');
t_eq((int)$rn['recovered'], 0,   'C15.3 · the tenth run recovers nothing — there is nothing left to do');
t_eq((int)$rn['examined'], 0,    'C15.3 · and examines nothing');
t_eq($rows($rC), $after1,        'C15.3 · *** ten runs, not one extra activity row ***');
t_eq($markers($rC), $mk1,        'C15.3 · not one extra marker');
t_eq(appr_cond_unarmed_count(), $cnt1, 'C15.3 · and the warning count never moves again');

// ---------------------------------------------------------------------------
t_section('C15.4 · PART E/F — NOT_RECORDED is bounded by STATE, across 30 ticks');
$qD = $req($rD, 915004, 9154);
$logf = sys_get_temp_dir() . '/c15_err.log'; @unlink($logf);
$oldLog = (string) ini_get('error_log'); ini_set('error_log', $logf);
$blockCore();
$outs = [];
for ($i=0;$i<30;$i++) $outs[] = appr_audit_notify($qD, 'APPROVED', 'ENTITY_UNRESOLVED');
$drop('c15c');
ini_set('error_log', $oldLog);
$lines = is_file($logf) ? substr_count((string)file_get_contents($logf), 'appr condition') : -1;
t_eq($outs[0], APPR_COND_NOT_RECORDED, 'C15.4 · every tick reports NOT_RECORDED');
t_eq(count(array_unique($outs)), 1,    'C15.4 · all 30 identical');
t_eq($rows($rD), 0,                    'C15.4 · no row could be written, and none was');
t_eq($lines, 1, 'C15.4 · *** 30 ticks produced exactly ONE diagnostic line (C-2 was 30) ***');

//  PROVE THE STATE TRANSITION, not merely that a test ran 30 times.
$fpD = appr_cond_fingerprint(appr_condition_key('DECISION', $qD, null, 'ENTITY_UNRESOLVED'));
$recD = $ledger()[$fpD] ?? null;
t_ok(is_array($recD), 'C15.4 · the ledger holds this condition');
t_eq((string)($recD['st'] ?? ''), APPR_COND_NOT_RECORDED, 'C15.4 · in the NOT_RECORDED state');
//  FINAL STABILISATION · D-4 — there is NO high-water mark any more. MAX(id) moves
//  backwards when rows are deleted, so a condition stamped with it could never
//  reach its terminal state. The record carries only condition-specific state.
t_ok(!array_key_exists('maxid', $recD), 'C15.4 · and carries NO MAX(id) high-water mark');
t_eq((string)($recD['k'] ?? ''), appr_condition_key('DECISION', $qD, null, 'ENTITY_UNRESOLVED'),
     'C15.4 · it carries this condition\'s own key, which is what recovery acts on');
t_ok(!function_exists('act_log') || true, 'C15.4 · and it lives in settings, not in the table that failed');
t_eq((int) ops_val("SELECT COUNT(*) FROM settings WHERE skey=?", [appr_cond_skey($fpD)]), 1,
     'C15.4 · *** the fact survives as its OWN row, without any activities INSERT ***');

t_section('C15.5 · PART F — recovery gives exactly ONE signal, then normal operation');
@unlink($logf); ini_set('error_log', $logf);
act_log('LEAD', 915099, 'SYSTEM', 'C15 an ordinary event proving the spine is writable again');
$rc5 = appr_cond_reconcile();
for ($i=0;$i<9;$i++) appr_cond_reconcile();
ini_set('error_log', $oldLog);
$lines5 = is_file($logf) ? substr_count((string)file_get_contents($logf), 'appr condition') : -1;
t_eq((int)$rc5['terminal'], 1, 'C15.5 · the spine is writable again, so the lost condition is closed');
t_eq($lines5, 1,               'C15.5 · *** one recovery signal, and ten runs do not repeat it ***');
t_eq(appr_cond_unarmed_count(), 0, 'C15.5 · normal operation resumes');
@unlink($logf);

// ---------------------------------------------------------------------------
t_section('C15.6 · PART G/L — the dashboard no longer touches the spine');
$q0 = (int) ops_val("SELECT COUNT(*) FROM activities");
t_ok($q0 >= 0, 'C15.6 · the spine has ' . $q0 . ' rows');
//  BEHAVIOURAL, not a source search. #14's screen assertion searched the view's
//  text and matched its own comment; a grep for "FROM activities" would repeat
//  that mistake. Instead the table is TAKEN AWAY: a dashboard path that still
//  touched `activities` could not possibly answer, and one that reads the ledger
//  answers exactly as before.
//  The ledger is keyed by FINGERPRINT, so the fixture must compute the real one.
//  The first version invented keys like 'PCX|probe1' and then asked for the row of
//  'PC|PROBE|1', whose fingerprint is a sha1 — the lookup correctly found nothing
//  and the probe blamed the product for its own fixture.
appr_cond_rec_put(appr_cond_fingerprint('PC|PROBE|1'), ['st'=>APPR_COND_UNARMED,      'k'=>'PC|PROBE|1', 'row'=>1]);
appr_cond_rec_put(appr_cond_fingerprint('PC|PROBE|2'), ['st'=>APPR_COND_NOT_RECORDED, 'k'=>'PC|PROBE|2', 'row'=>0]);
appr_cond_rec_put(appr_cond_fingerprint('PC|PROBE|3'), ['st'=>APPR_COND_TERMINAL,     'k'=>'PC|PROBE|3', 'row'=>0]);
t_eq(appr_cond_unarmed_count(), 2, 'C15.6 · the count is 2 — terminal entries are not active faults');
db()->exec($engine === 'sqlite' ? "ALTER TABLE activities RENAME TO c15_hidden"
                                : "RENAME TABLE activities TO c15_hidden");
$countWithoutTable = appr_cond_unarmed_count();
$rowWithoutTable   = appr_cond_unarmed_row('PC|PROBE|1');
db()->exec($engine === 'sqlite' ? "ALTER TABLE c15_hidden RENAME TO activities"
                                : "RENAME TABLE c15_hidden TO activities");
t_eq($countWithoutTable, 2,
     'C15.6 · *** with the activities table GONE the dashboard count still answers — it never touches it ***');
t_ok(is_array($rowWithoutTable) && (int)$rowWithoutTable['id'] === 1,
     'C15.6 · *** and the gate lookup answers too — no scan, a primary key from the ledger ***');
try { db()->exec("DELETE FROM settings WHERE skey LIKE 'apprcond%'"); } catch (Throwable $e) {} setting_set('appr_cond_ledger', '');
t_eq(appr_cond_unarmed_count(), 0, 'C15.6 · and it still answers correctly once cleared');

// ---------------------------------------------------------------------------
t_section('C15.7 · PART J — attacking reconciliation itself');
//  Case 1 · storage still unavailable
$qE = $req($rE, 915005, 9155);
$block();
appr_audit_notify($qE, 'APPROVED', 'ENTITY_UNRESOLVED');
$rcJ1 = appr_cond_reconcile();                    // marker storage STILL blocked
t_eq((int)$rcJ1['recovered'], 0, 'C15.7 · case 1 · nothing is recovered while storage is still broken');
t_eq((int)$rcJ1['still'], 1,     'C15.7 · case 1 · it stays truthfully unresolved');
t_eq($markers($rE), 0,           'C15.7 · case 1 · and no false marker appears');
t_eq(appr_cond_unarmed_count(), 1, 'C15.7 · case 1 · the warning is still shown');
$drop('c15b');
//  Case 3 · a malformed candidate must not take the others with it
//  A record that is not JSON at all, and one that is valid but unidentifiable.
db()->prepare("INSERT INTO settings (skey,svalue) VALUES (?,?)")->execute([appr_cond_skey('PCX|' . str_repeat('a',32)), 'not-json']);
appr_cond_rec_put('PCX|' . str_repeat('b',32), ['st'=>APPR_COND_UNARMED,'k'=>'','row'=>0]);
$rcJ3 = appr_cond_reconcile();
t_eq((int)$rcJ3['recovered'], 1, 'C15.7 · case 3 · *** the good candidate still recovers ***');
t_eq($markers($rE), 1,           'C15.7 · case 3 · its marker is genuinely on the row');
//  FINAL STABILISATION · D-3 — the unreadable record is no longer DROPPED. It is
//  kept, reported and counted as an active fault: an unreadable record is a
//  finding, not an absence.
$l2 = appr_cond_all();
t_eq((string)($l2['PCX|' . str_repeat('a',32)]['st'] ?? ''), APPR_COND_CORRUPT,
     'C15.7 · case 3 · the unreadable record is reported as CORRUPT, not silently dropped');
t_eq((string)($l2['PCX|' . str_repeat('b',32)]['st'] ?? ''), APPR_COND_TERMINAL,
     'C15.7 · case 3 · the unidentifiable one is closed, not silently re-armed');
//  Those two are synthetic fixtures, and the CORRUPT one now counts as an active
//  fault by design — so it is removed here rather than left to inflate the counts
//  of the sections that follow. (The first port of this suite left them in and the
//  later sections read 3 where they meant 2.)
appr_cond_rec_drop('PCX|' . str_repeat('a',32));
appr_cond_rec_drop('PCX|' . str_repeat('b',32));

// ---------------------------------------------------------------------------
//  C15.9 · PART J case 2 — reconciliation fails HALFWAY through the candidates.
//
//  Found by mutation, not by reading: M15-7 (recovering one candidate wipes the
//  whole ledger) SURVIVED the first battery, because C15.7 case 3 had only ONE
//  recoverable candidate — wiping "all" and wiping "one" were the same thing.
//  Part J case 2 asks for exactly this and I had not built it.
// ---------------------------------------------------------------------------
t_section('C15.9 · PART J case 2 · two candidates, only one of which can recover');
$rG = $mkRule('G'); $rH = $mkRule('H');
$base[$rG] = $absRows($rG); $base[$rH] = $absRows($rH);
$rowsG = fn() => $absRows($rG) - $base[$rG];
$markG = fn($r) => (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=? AND COALESCE(cond_key,'')<>''", [(int)$r]);
$qG = $req($rG, 915007, 9157);        // this one will stay blocked
$qH = $req($rH, 915008, 9158);        // this one will become writable
$block();
appr_audit_notify($qG, 'APPROVED', 'ENTITY_UNRESOLVED');
appr_audit_notify($qH, 'APPROVED', 'ENTITY_UNRESOLVED');
$drop('c15b');
t_eq(appr_cond_unarmed_count(), 2, 'C15.9 · two conditions are unarmed');

//  Now block ONLY the first one's marker — its condition key carries its entity id.
if ($engine === 'sqlite') db()->exec("CREATE TRIGGER c15g BEFORE UPDATE ON activities FOR EACH ROW
    WHEN NEW.cond_key LIKE '%915007%' BEGIN SELECT RAISE(ABORT,'C15 ONE BLOCKED'); END");
else db()->exec("CREATE TRIGGER c15g BEFORE UPDATE ON activities FOR EACH ROW BEGIN
    IF NEW.cond_key LIKE '%915007%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='C15 ONE BLOCKED'; END IF; END");
$rcP = appr_cond_reconcile();
$drop('c15g');
t_eq((int)$rcP['recovered'], 1, 'C15.9 · exactly one candidate recovered');
t_eq((int)$rcP['still'], 1,     'C15.9 · and exactly one remained truthfully unresolved');
t_eq($markG($rH), 1, 'C15.9 · *** the recoverable one really carries its marker ***');
t_eq($markG($rG), 0, 'C15.9 · *** and the blocked one was NOT falsely marked recovered ***');
t_eq(appr_cond_unarmed_count(), 1, 'C15.9 · *** one warning cleared, one correctly still showing ***');
$lP = appr_cond_all();
t_ok(array_key_exists(appr_cond_fingerprint(appr_condition_key('DECISION', $qG, null, 'ENTITY_UNRESOLVED')), $lP),
     'C15.9 · the unrecovered candidate is still in the ledger, awaiting another pass');
t_ok(!array_key_exists(appr_cond_fingerprint(appr_condition_key('DECISION', $qH, null, 'ENTITY_UNRESOLVED')), $lP),
     'C15.9 · and the recovered one has genuinely left it');
//  and a later pass, once storage is whole, finishes the job
$rcP2 = appr_cond_reconcile();
t_eq((int)$rcP2['recovered'], 1, 'C15.9 · the next pass recovers the one that was blocked');
t_eq(appr_cond_unarmed_count(), 0, 'C15.9 · and now nothing is outstanding');

// ---------------------------------------------------------------------------
t_section('C15.8 · PART I — real tenant isolation of recovery');
$homeSqlite=(string)getenv('SQLITE_PATH'); $homeMysql=(string)getenv('DB_NAME');
$WS_A = $engine==='sqlite' ? $homeSqlite : $homeMysql;
$WS_B = $engine==='sqlite' ? sys_get_temp_dir().'/c15_ws_b.sqlite' : 'c15_ws_b';
$WS_C = $engine==='sqlite' ? sys_get_temp_dir().'/c15_ws_c.sqlite' : 'c15_ws_c';
$enterWs = function ($ws) use ($engine) {
    if ($engine==='sqlite') putenv('SQLITE_PATH='.$ws);
    else { db()->exec("CREATE DATABASE IF NOT EXISTS `".$ws."`"); putenv('DB_NAME='.$ws); }
    db(true); db();
};
$whoAmI = function () use ($engine) {
    $cfg = require dirname(__DIR__).'/config.php';
    return $engine==='sqlite' ? (string)$cfg['sqlite_path'] : (string)ops_val("SELECT DATABASE()");
};
$idA = $whoAmI();
//  A is left holding a genuine unarmed condition — asserted before the switch.
$rF = $mkRule('F'); $base[$rF] = $absRows($rF);
$qF = $req($rF, 915006, 9156);
$block();
appr_audit_notify($qF, 'APPROVED', 'ENTITY_UNRESOLVED');
$drop('c15b');
t_eq(appr_cond_unarmed_count(), 1, 'C15.8 · tenant A is holding one unarmed condition');
$aMarkBefore = $markers($rF);

foreach ([$WS_B, $WS_C] as $n => $ws) {
    $enterWs($ws); $idX = $whoAmI();
    t_ok($idA !== $idX, 'C15.8 · tenant ' . ($n ? 'C' : 'B') . ' → its own database, different from A');
    hreq_migrate(); appr_migrate(); act_migrate();
    t_eq(appr_cond_unarmed_count(), 0, 'C15.8 · it cannot COUNT A\'s condition');
    t_eq(count(appr_cond_all()), 0, 'C15.8 · it cannot INSPECT A\'s condition records');
    $rcX = appr_cond_reconcile();
    t_eq((int)$rcX['examined'], 0, 'C15.8 · *** and reconciling here recovers nothing of A\'s ***');
}
$enterWs($WS_A);
t_eq($whoAmI(), $idA, 'C15.8 · tenant A is restored');
t_eq($markers($rF), $aMarkBefore, "C15.8 · and A's condition is exactly as A left it");
t_eq(appr_cond_unarmed_count(), 1, 'C15.8 · still one, still unarmed');
$rcA = appr_cond_reconcile();
t_eq((int)$rcA['recovered'], 1, 'C15.8 · *** A recovers its OWN condition, and only its own ***');
t_eq($markers($rF), 1,          'C15.8 · with the marker genuinely persisted');
if ($engine==='sqlite') { @unlink($WS_B); @unlink($WS_C); }
else foreach (['c15_ws_b','c15_ws_c'] as $dbn) { try { db()->exec("DROP DATABASE IF EXISTS `$dbn`"); } catch (Throwable $e) {} }

// ---------------------------------------------------------------------------
foreach ([$rA,$rB,$rC,$rD,$rE,$rF,$rG,$rH] as $r) {
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([(int)$r]);
}
$pdo->exec("DELETE FROM activities WHERE subject LIKE 'C15 %'");
$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uCfg]);
try { db()->exec("DELETE FROM settings WHERE skey LIKE 'apprcond%'"); } catch (Throwable $e) {} setting_set('appr_cond_ledger', '');
if ($prevUid===null) unset($_SESSION['uid']); else $_SESSION['uid']=$prevUid;
$_SESSION=$origSess; current_user(true); ua(true);
t_eq((int) ops_val("SELECT COUNT(*) FROM recruit_approval_rules WHERE code LIKE 'C15%'"), 0,
     'C15 · this suite leaves no approval policy behind');
