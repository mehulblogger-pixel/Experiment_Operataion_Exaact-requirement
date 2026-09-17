<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #13 — THE CALLER MUST CONSUME THE RESULT
//
//  Y1  act_set_cond_key() was made truthful by #12, but act_log() discarded the
//      status ("status ignored"), so the four-valued contract had no reader
//      anywhere outside the tests. The truthfulness was inert.
//
//  Y2  Because appr_condition_seen() reads the marker back OUT OF THE DATABASE,
//      a marker that did not persist means the next identical permanent
//      condition finds nothing, concludes it has never been seen, and records
//      the event again — the repetition #7 stopped, silently resumed.
//
//  EVERY assertion below goes through a REAL PRODUCTION CALLER —
//  appr_audit_notify() and appr_audit_sla() — never through act_set_cond_key()
//  on its own. #12's mistake was proving the helper and stopping there.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #13 — the production caller consumes the status');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate();
$engine = db_driver();
$origSess = $_SESSION;
t_ok(in_array('cond_key', t_columns('activities'), true), 'C13.0 · the spine carries a condition key');

//  --- fixture: a superuser who may configure, and three real rules ----------
$pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,permissions,email)
               VALUES ('c13_cfg','C13','Cfg','ADMIN',1,1,'','c13cfg@t.test')")->execute();
$uCfg = (int) $pdo->lastInsertId();
$prevUid = $_SESSION['uid'] ?? null;
$_SESSION['uid'] = $uCfg; current_user(true); ua(true);

$mkRule = function ($tag) {
    return (int) appr_rule_save(0, ['name' => 'C13 ' . $tag, 'entity' => 'HIRING_REQUEST',
                                    'code' => 'C13' . $tag, 'applies_department' => '']);
};
$rA = $mkRule('A'); $rB = $mkRule('B'); $rS = $mkRule('S');
t_ok($rA > 0 && $rB > 0 && $rS > 0 && $rA !== $rB, 'C13.0 · three distinct approval policies exist to hang the events on');

//  A request whose SOURCE RECORD does not exist — the permanent condition this
//  whole chain is about. The policy still resolves, so there IS something
//  openable to write the event against; that is asserted, not assumed.
$req = function ($rule, $eid, $rq) {
    return ['id' => $rq, 'entity' => 'HIRING_REQUEST', 'entity_id' => $eid, 'rule_id' => $rule];
};
//  Creating a policy is itself an audited act, so each rule already carries one
//  APPROVAL_POLICY event before this suite writes anything. The first version of
//  this file counted absolute rows and was off by exactly one everywhere — it was
//  counting its own fixture. Counts are therefore taken as DELTAS from a baseline
//  captured after the fixture is built and before the first assertion.
$absRows = function ($rule) { return (int) ops_val(
    "SELECT COUNT(*) FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?", [(int)$rule]); };
$base = [];
$rowsOn = function ($rule) use ($absRows, &$base) { return $absRows($rule) - ($base[(int)$rule] ?? 0); };
$markersOn = function ($rule) { return (int) ops_val(
    "SELECT COUNT(*) FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=? AND cond_key<>''", [(int)$rule]); };
foreach ([$rA, $rB, $rS] as $r) $base[$r] = $absRows($r);
t_ok($base[$rA] > 0, 'C13.0 · each policy already carries its own creation event — the baseline is not zero');

//  The failure fixture: a genuine BEFORE UPDATE failure, engine-appropriate, so
//  the marker write really fails instead of being simulated.
$blockMarker = function () use ($engine) {
    if ($engine === 'sqlite') {
        db()->exec("CREATE TRIGGER c13_block BEFORE UPDATE ON activities FOR EACH ROW
                    WHEN NEW.cond_key LIKE 'PC|%' BEGIN SELECT RAISE(ABORT, 'C13 MARKER BLOCKED'); END");
    } else {
        db()->exec("CREATE TRIGGER c13_block BEFORE UPDATE ON activities FOR EACH ROW BEGIN
                    IF NEW.cond_key LIKE 'PC|%' THEN
                      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'C13 MARKER BLOCKED';
                    END IF; END");
    }
};
$unblockMarker = function () { try { db()->exec("DROP TRIGGER c13_block"); } catch (Throwable $e) {} };

// ---------------------------------------------------------------------------
//  C13.1 · §4 — STORED reaches the caller, and the caller takes the STORED path
// ---------------------------------------------------------------------------
t_section('C13.1 · §4 · the marker stores — the caller observes it and says so');
$rqA = $req($rA, 991301, 9131);
t_eq($rowsOn($rA), 0, 'C13.1 · nothing has been recorded against this policy yet');

$o1 = appr_audit_notify($rqA, 'APPROVED', 'ENTITY_UNRESOLVED');
t_eq(act_last_cond_status(), ACT_COND_STORED, 'C13.1 · act_log() really did store the marker');
t_eq($o1, APPR_COND_RECORDED,   'C13.1 · *** the PRODUCTION CALLER observed STORED and returned RECORDED ***');
t_eq($rowsOn($rA), 1,           'C13.1 · exactly one event was written');
t_eq($markersOn($rA), 1,        'C13.1 · and it carries its marker, so suppression is armed');

// ---------------------------------------------------------------------------
//  C13.2 · §5 Scenario A — the real duplicate-suppression cycle
// ---------------------------------------------------------------------------
t_section('C13.2 · §5 Scenario A · the identical condition is suppressed');
$o2 = appr_audit_notify($rqA, 'APPROVED', 'ENTITY_UNRESOLVED');
t_eq($o2, APPR_COND_SUPPRESSED, 'C13.2 · the repeat is suppressed, through the production caller');
t_eq($rowsOn($rA), 1,           'C13.2 · and still exactly one event exists');
for ($i = 0; $i < 8; $i++) appr_audit_notify($rqA, 'APPROVED', 'ENTITY_UNRESOLVED');
t_eq($rowsOn($rA), 1,           'C13.2 · ten identical refusals in total still leave ONE row (K1 stays fixed)');

// ---------------------------------------------------------------------------
//  C13.3 · §4 + §5 Scenario B — the marker fails; nothing may claim it stored
// ---------------------------------------------------------------------------
t_section('C13.3 · §5 Scenario B · the marker CANNOT be persisted');
$rqB = $req($rB, 991302, 9132);
t_eq($rowsOn($rB), 0, 'C13.3 · this policy starts clean');
$blockMarker();
$o3 = appr_audit_notify($rqB, 'APPROVED', 'ENTITY_UNRESOLVED');

//  The fixture is only meaningful if the write REALLY failed. Asserted first.
t_eq(act_last_cond_status(), ACT_COND_FAILED, 'C13.3 · the marker write genuinely failed — the fixture is real');
t_eq($o3, APPR_COND_UNARMED,  'C13.3 · *** the caller observed FAILED and did NOT take the stored path ***');
t_ok($o3 !== APPR_COND_RECORDED && $o3 !== APPR_COND_SUPPRESSED,
                              'C13.3 · and never reports the condition as recorded or suppressed');
t_eq($rowsOn($rB), 1,         'C13.3 · S1 holds — the EVENT itself was still written');
t_eq($markersOn($rB), 0,      'C13.3 · but it carries no marker, so suppression is genuinely not armed');

//  §5 B.6 — the application must not silently claim the first marker was stored.
//
//  M3 CORRECTION #14 · B-4 changed what happens NEXT, and these two assertions
//  were re-pointed at the corrected contract rather than weakened. #13's fail-safe
//  wrote a fresh event on every tick, which its own adversarial audit showed to be
//  H2 under another name. The rule it must still satisfy is unchanged and is
//  asserted first: the repeat is NEVER suppressed on the strength of a marker that
//  does not exist. What #14 adds is that it does not write a second row either —
//  it retries the marker on the row that already exists.
$o4 = appr_audit_notify($rqB, 'APPROVED', 'ENTITY_UNRESOLVED');
t_ok($o4 !== APPR_COND_SUPPRESSED,
     'C13.3 · *** the repeat is NOT suppressed on the strength of a marker that was never written ***');
t_ok($o4 !== APPR_COND_RECORDED,
     'C13.3 · and it is never reported as recorded');
t_eq($o4, APPR_COND_PENDING_RETRY, 'C13.3 · it reports PENDING_RETRY — still unarmed, and it says so');
t_eq($rowsOn($rB), 1,         'C13.3 · #14 · and NO second row was written — the loop is bounded at one');

// ---------------------------------------------------------------------------
//  C13.4 · recovery — once the marker CAN be written, suppression arms itself
// ---------------------------------------------------------------------------
t_section('C13.4 · recovery · the fail-safe is not a one-way door');
$unblockMarker();
$o5 = appr_audit_notify($rqB, 'APPROVED', 'ENTITY_UNRESOLVED');
t_eq($o5, APPR_COND_RECOVERED, 'C13.4 · #14 · the obstruction gone, the retry ARMS THE EXISTING ROW');
t_eq($rowsOn($rB), 1,          'C13.4 · #14 · and still no new row was needed to recover');
t_eq($markersOn($rB), 1,       'C13.4 · the row that was already there now carries its marker');
$o6 = appr_audit_notify($rqB, 'APPROVED', 'ENTITY_UNRESOLVED');
t_eq($o6, APPR_COND_SUPPRESSED, 'C13.4 · and every later identical refusal is suppressed again');
t_eq($rowsOn($rB), 1,           'C13.4 · the row count stops growing');

// ---------------------------------------------------------------------------
//  C13.5 · THE SIBLING — appr_audit_sla() is the second production caller
//
//  #12's whole lesson was fixing the reported instance and not its siblings.
//  The SLA writer passes a cond_key through the identical path, so it gets the
//  identical proof rather than being assumed to follow.
// ---------------------------------------------------------------------------
t_section('C13.5 · the OTHER production caller — appr_audit_sla()');
$rqS = $req($rS, 991303, 9133);
$step = ['id' => 1, 'seq' => 1, 'label' => 'Head'];
$s1 = appr_audit_sla($rqS, $step, 'Reminder', '', 'SLA_EVENT');
t_eq(act_last_cond_status(), ACT_COND_STORED, 'C13.5 · the SLA writer stored its marker');
t_eq($s1, APPR_COND_RECORDED,   'C13.5 · and it, too, consumes the status instead of dropping it');
t_eq($rowsOn($rS), 1,           'C13.5 · one SLA event written');
$s2 = appr_audit_sla($rqS, $step, 'Reminder', '', 'SLA_EVENT');
t_eq($s2, APPR_COND_SUPPRESSED, 'C13.5 · the daily repeat is suppressed (H2 stays fixed)');
t_eq($rowsOn($rS), 1,           'C13.5 · still one row');

$blockMarker();
$rqS2 = $req($rS, 991304, 9134);
$s3 = appr_audit_sla($rqS2, $step, 'Reminder', '', 'SLA_EVENT');
$unblockMarker();
t_eq(act_last_cond_status(), ACT_COND_FAILED, 'C13.5 · a genuinely failed SLA marker');
t_eq($s3, APPR_COND_UNARMED,    'C13.5 · *** the SLA writer also refuses to claim it was stored ***');

// ---------------------------------------------------------------------------
//  C13.6 · the status must never be inherited from a DIFFERENT event
// ---------------------------------------------------------------------------
t_section('C13.6 · a later event must not inherit the previous marker status');
t_eq(act_last_cond_status(), ACT_COND_FAILED, 'C13.6 · the last marker attempt failed');
act_log('LEAD', 991399, 'SYSTEM', 'C13 an ordinary event with no condition key');
t_eq(act_last_cond_status(), ACT_COND_NOT_ATTEMPTED,
     'C13.6 · *** an act_log() that writes no marker reports NOT_ATTEMPTED, not the previous STORED/FAILED ***');
$o7 = appr_audit_notify($req($rA, 991301, 9131), 'APPROVED', 'ENTITY_UNRESOLVED');
t_eq($o7, APPR_COND_SUPPRESSED, 'C13.6 · and a suppressed call reports suppression, never a stale status');

// ---------------------------------------------------------------------------
//  C13.7 · the status slot is WORKSPACE-KEYED, like every other channel
//
//  Found by mutation, not by reading: M-Y1-GLOBAL made the slot ignore the
//  workspace and survived every assertion above. I had added a new per-workspace
//  slot and never asserted it was one — the exact gap X2 found in the error
//  channels, repeated on the slot introduced to fix a different gap. Switching
//  goes through the application's real path (#12 · X3), and the database is made
//  to name itself before any claim is made.
// ---------------------------------------------------------------------------
t_section('C13.7 · a status belongs to the workspace that produced it');
$homeSqlite = (string) getenv('SQLITE_PATH'); $homeMysql = (string) getenv('DB_NAME');
$WS_A = $engine === 'sqlite' ? $homeSqlite : $homeMysql;
$WS_B = $engine === 'sqlite' ? sys_get_temp_dir() . '/c13_ws_b.sqlite' : 'c13_ws_b';
$enterWs = function ($ws) use ($engine) {
    if ($engine === 'sqlite') { putenv('SQLITE_PATH=' . $ws); }
    else { db()->exec("CREATE DATABASE IF NOT EXISTS `" . $ws . "`"); putenv('DB_NAME=' . $ws); }
    db(true); db();
};
$whoAmI = function () use ($engine) {
    $cfg = require dirname(__DIR__) . '/config.php';
    return $engine === 'sqlite' ? (string) $cfg['sqlite_path'] : (string) ops_val("SELECT DATABASE()");
};
$idA = $whoAmI();
appr_audit_notify($req($rS, 991305, 9135), 'APPROVED', 'ENTITY_UNRESOLVED');
t_eq(act_last_cond_status(), ACT_COND_STORED, 'C13.7 · workspace A ends holding a STORED status');

$enterWs($WS_B);
t_ok($whoAmI() !== $idA, 'C13.7 · workspace B is a genuinely different database');
t_eq(act_last_cond_status(), ACT_COND_NOT_ATTEMPTED,
     "C13.7 · *** B cannot read A's marker status — it reports NOT_ATTEMPTED ***");

$enterWs($WS_A);
t_eq($whoAmI(), $idA, 'C13.7 · and workspace A is restored');
t_eq(act_last_cond_status(), ACT_COND_NOT_ATTEMPTED,
     'C13.7 · a real re-entry is a new epoch, so A reads NOT_ATTEMPTED rather than a stale STORED');
if ($engine === 'sqlite') { @unlink($WS_B); }
else { try { db()->exec("DROP DATABASE IF EXISTS `c13_ws_b`"); } catch (Throwable $e) {} }

// ---------------------------------------------------------------------------
//  CLEAN UP — and clean up the RULES, not just their events.
//
//  The first version of this file deleted only the activity rows. The three
//  approval policies it creates apply to entity HIRING_REQUEST with an empty
//  department, i.e. to EVERY hiring request, and this suite sorts before
//  test_p3m3c3 and test_p3m3c4 — so those suites matched THIS suite's policy
//  instead of their own and the full regression died with a null step. The
//  focused run was green throughout: only the full, ordered run exposed it.
//  A fixture that survives its own suite is a fixture that tests other people's.
$unblockMarker();
db()->exec("DELETE FROM activities WHERE subject LIKE 'C13 %'");
foreach ([$rA, $rB, $rS] as $r) {
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([(int)$r]);
}
$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uCfg]);
if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
$_SESSION = $origSess; current_user(true); ua(true);
t_eq((int) ops_val("SELECT COUNT(*) FROM recruit_approval_rules WHERE code LIKE 'C13%'"), 0,
     'C13 · this suite leaves no approval policy behind for the suites that follow');
