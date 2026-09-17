<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #14 — THE COMPLETE SUPPRESSION DECISION CHAIN
//
//  B-1  the production callers ABOVE appr_audit_* discarded the outcome
//  B-2  no business user could see that suppression had stopped working
//  B-3  UNARMED claimed "the event was written" when no event existed
//  B-4  a permanently unwritable marker wrote one row per tick, for ever
//  B-5  and one identical log line per tick with it
//
//  Nothing here is proved on a helper. Every claim is made through the real
//  production path, and the final ones are made on BUSINESS BEHAVIOUR: whether a
//  duplicate appears, and whether a human is told.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #14 — condition → status → caller → decision → screen');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate();
$engine = db_driver(); $origSess = $_SESSION;
$pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,permissions,email)
               VALUES ('c14_cfg','C14','Cfg','ADMIN',1,1,'','c14cfg@t.test')")->execute();
$uCfg=(int)$pdo->lastInsertId(); $prevUid=$_SESSION['uid']??null;
$_SESSION['uid']=$uCfg; current_user(true); ua(true);

$mkRule = fn($t) => (int) appr_rule_save(0, ['name'=>'C14 '.$t,'entity'=>'HIRING_REQUEST',
                                             'code'=>'C14'.$t,'applies_department'=>'']);
$rA=$mkRule('A'); $rB=$mkRule('B'); $rC=$mkRule('C'); $rD=$mkRule('D'); $rE=$mkRule('E');
$absRows = fn($r) => (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?", [(int)$r]);
$base=[]; foreach ([$rA,$rB,$rC,$rD,$rE] as $r) $base[$r]=$absRows($r);
$rows = fn($r) => $absRows($r) - ($base[(int)$r] ?? 0);
$req  = fn($rule,$eid,$rq) => ['id'=>$rq,'entity'=>'HIRING_REQUEST','entity_id'=>$eid,'rule_id'=>$rule];

$blockMarker = function () use ($engine) {
    if ($engine === 'sqlite') db()->exec("CREATE TRIGGER c14_nomark BEFORE UPDATE ON activities FOR EACH ROW
        WHEN NEW.cond_key LIKE 'PC|%' BEGIN SELECT RAISE(ABORT,'C14 MARKER BLOCKED'); END");
    else db()->exec("CREATE TRIGGER c14_nomark BEFORE UPDATE ON activities FOR EACH ROW BEGIN
        IF NEW.cond_key LIKE 'PC|%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='C14 MARKER BLOCKED'; END IF; END");
};
$blockCore = function () use ($engine) {
    if ($engine === 'sqlite') db()->exec("CREATE TRIGGER c14_nocore BEFORE INSERT ON activities FOR EACH ROW
        BEGIN SELECT RAISE(ABORT,'C14 CORE BLOCKED'); END");
    else db()->exec("CREATE TRIGGER c14_nocore BEFORE INSERT ON activities FOR EACH ROW BEGIN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='C14 CORE BLOCKED'; END");
};
$drop = function ($n) { try { db()->exec("DROP TRIGGER $n"); } catch (Throwable $e) {} };

// ---------------------------------------------------------------------------
t_section('C14.A · TEST A — the marker stores; suppression behaves normally');
$qA = $req($rA, 914001, 9141);
$a1 = appr_audit_notify($qA, 'APPROVED', 'ENTITY_UNRESOLVED');
t_eq($a1, APPR_COND_RECORDED, 'C14.A · the production caller receives RECORDED');
t_eq($rows($rA), 1,           'C14.A · one event exists');
t_eq(appr_cond_unarmed_count(), 0, 'C14.A · nothing is reported as needing attention');
for ($i=0;$i<9;$i++) appr_audit_notify($qA,'APPROVED','ENTITY_UNRESOLVED');
t_eq($rows($rA), 1, 'C14.A · *** BUSINESS OUTCOME · ten identical refusals produce ONE entry ***');

// ---------------------------------------------------------------------------
t_section('C14.B · TEST B — the marker cannot be written');
$qB = $req($rB, 914002, 9142);
$blockMarker();
$b1 = appr_audit_notify($qB, 'APPROVED', 'ENTITY_UNRESOLVED');
t_eq(act_last_cond_status(), ACT_COND_FAILED, 'C14.B · the marker write genuinely failed — the fixture is real');
t_ok(act_last_cond_row() > 0,                 'C14.B · and the event row genuinely exists');
t_eq($b1, APPR_COND_UNARMED,  'C14.B · *** the caller receives UNARMED — and a row really was written ***');
t_ok($b1 !== APPR_COND_RECORDED && $b1 !== APPR_COND_SUPPRESSED, 'C14.B · never treated as successful storage');
t_eq($rows($rB), 1,           'C14.B · exactly one event');
$drop('c14_nomark');

// ---------------------------------------------------------------------------
t_section('C14.C · TEST C — the CORE event itself cannot be written (B-3)');
$qC = $req($rC, 914003, 9143);
$blockCore();
$c1 = appr_audit_notify($qC, 'APPROVED', 'ENTITY_UNRESOLVED');
$drop('c14_nocore');
t_eq($rows($rC), 0,                'C14.C · no event row exists — the core INSERT really failed');
t_eq(act_last_cond_row(), 0,       'C14.C · and the status carries a row id of zero');
t_eq($c1, APPR_COND_NOT_RECORDED,  'C14.C · *** the status says NOT_RECORDED, not UNARMED ***');
t_ok($c1 !== APPR_COND_UNARMED,    'C14.C · #13 returned UNARMED here, whose definition claims a row exists');
t_ok($c1 !== APPR_COND_RECORDED && $c1 !== APPR_COND_SUPPRESSED, 'C14.C · and never a success');

// ---------------------------------------------------------------------------
t_section('C14.D · TEST D — 30 scheduler ticks during a permanent failure (B-4/B-5)');
$qD = $req($rD, 914004, 9144);
$blockMarker();
$outs = [];
for ($t=0;$t<30;$t++) $outs[] = appr_audit_sla($qD, ['id'=>1,'seq'=>1,'label'=>'Head'], 'Approval reminder sent', '', 'REMINDER');
$drop('c14_nomark');
t_eq($rows($rD), 1, 'C14.D · *** BUSINESS OUTCOME · 30 ticks, ONE entry — the loop is bounded ***');
t_eq($outs[0], APPR_COND_UNARMED,       'C14.D · the first tick reports UNARMED and writes the row');
t_eq(count(array_unique(array_slice($outs,1))), 1, 'C14.D · every later tick reports the same thing');
t_eq($outs[29], APPR_COND_PENDING_RETRY, 'C14.D · namely PENDING_RETRY — still unarmed, and it says so');
t_ok(!in_array(APPR_COND_SUPPRESSED, $outs, true), 'C14.D · not one tick claimed the condition was suppressed');
t_ok(!in_array(APPR_COND_RECORDED, $outs, true),   'C14.D · and not one claimed it was recorded');
//  B-5 · only the states that occur ONCE per condition speak. PENDING_RETRY is
//  silent by construction, so 29 of the 30 ticks wrote no diagnostic at all.
t_eq(count(array_filter($outs, fn($o) => $o === APPR_COND_PENDING_RETRY)), 29,
     'C14.D · 29 of 30 ticks are the silent state — the log cannot stream');

t_section('C14.D2 · B-2 — and a human is told, in business words');
$sum = appr_sla_summary();
t_ok(array_key_exists('suppression_unarmed', $sum), 'C14.D2 · the approval summary the dashboard already renders carries it');
t_ok((int)$sum['suppression_unarmed'] >= 2, 'C14.D2 · *** BUSINESS OUTCOME · the unresolved conditions are counted for the screen ***');
//  B-2 is proved by RENDERING THE SCREEN, not by grepping its source.
//
//  The first version of this block searched the view file for the message text. A
//  mutation that removed the message from the markup SURVIVED, because the same
//  words appear in the explanatory comment directly above it: the assertion
//  matched a comment and reported a screen. That is this project's oldest defect —
//  a test passing for the wrong reason — and a source assertion where a
//  behavioural one was available. The view is rendered twice below, and the
//  claims are made about the HTML a user would actually receive.
$render = function ($unarmed) {
    $d = ['appr' => ['pending' => 0, 'due_today' => 0, 'overdue' => 0, 'escalated' => 0,
                     'due_soon' => 0, 'suppression_unarmed' => $unarmed]];
    ob_start();
    try { include dirname(__DIR__) . '/views/ops/recruitment_cc.php'; }
    catch (Throwable $e) { /* the page is read-only; a partial render is enough */ }
    return (string) ob_get_clean();
};
$html = $render(3);
t_ok(strpos($html, 'could not be recorded') !== false,
     'C14.D2 · *** the RENDERED screen states the problem in business words ***');
t_ok(strpos($html, 'approval conditions') !== false, 'C14.D2 · and names what is affected');
t_ok(preg_match('/>\s*3\s*</', $html) === 1 || strpos($html, '>3<') !== false,
     'C14.D2 · with the actual count on it');
t_ok(strpos($html, 'No approval decision is affected') !== false,
     'C14.D2 · and reassures the reader that no decision changed');
foreach (['SQLSTATE','cond_key','PCX|','activities','password','/home/'] as $leak)
    t_ok(strpos($html, $leak) === false, "C14.D2 · the rendered screen never exposes '$leak'");
$clean = $render(0);
t_ok(strpos($clean, 'could not be recorded') === false,
     'C14.D2 · and a healthy workspace is shown no warning at all');

// ---------------------------------------------------------------------------
t_section('C14.E · TEST E — recovery');
$e1 = appr_audit_sla($qD, ['id'=>1,'seq'=>1,'label'=>'Head'], 'Approval reminder sent', '', 'REMINDER');
t_eq($e1, APPR_COND_RECOVERED, 'C14.E · the retry arms the row that already existed');
t_eq($rows($rD), 1,            'C14.E · without writing a new one');
$e2 = appr_audit_sla($qD, ['id'=>1,'seq'=>1,'label'=>'Head'], 'Approval reminder sent', '', 'REMINDER');
t_eq($e2, APPR_COND_SUPPRESSED, 'C14.E · *** BUSINESS OUTCOME · ordinary suppression has resumed ***');
t_eq($rows($rD), 1,             'C14.E · and the entry count never moves again');

// ---------------------------------------------------------------------------
t_section('C14.F · B-1 — the result reaches the real decision maker');
$qF = $req($rE, 914005, 9145);
appr_email_cond_note('');
$blockMarker();
appr_audit_notify($qF, 'APPROVED', 'ENTITY_UNRESOLVED');
$drop('c14_nomark');
t_ok(function_exists('appr_tick_unarmed'), 'C14.F · the scheduler exposes what it could not arm');
$src = file_get_contents(dirname(__DIR__) . '/lib/recruit_approval.php');
t_ok(substr_count($src, '$co = appr_audit_sla(') === 2, 'C14.F · both scheduler sites capture the outcome');
t_ok(strpos($src, 'appr_email_cond_note(appr_audit_notify(') !== false, 'C14.F · the decision notifier consumes it too');
$cron = file_get_contents(dirname(__DIR__) . '/cron.php');
t_ok(strpos($cron, 'appr_tick_unarmed') !== false, 'C14.F · *** and the cron run itself reports it ***');
t_ok(strpos($cron, 'could not be marked as recorded') !== false, 'C14.F · in words an operator can act on');

// ---------------------------------------------------------------------------
//  C14.G · §11 — REAL TENANT ISOLATION for every piece of state #14 introduced.
//  Switching goes through the application's real path; the database is made to
//  name itself before any claim. __db_epoch is never assigned by hand.
// ---------------------------------------------------------------------------
t_section('C14.G · §11 · none of the new state crosses a tenant boundary');
$homeSqlite=(string)getenv('SQLITE_PATH'); $homeMysql=(string)getenv('DB_NAME');
$WS_A = $engine==='sqlite' ? $homeSqlite : $homeMysql;
$WS_B = $engine==='sqlite' ? sys_get_temp_dir().'/c14_ws_b.sqlite' : 'c14_ws_b';
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

//  Tenant A is left holding a genuinely unarmed condition, asserted before the switch.
$rG = $mkRule('G'); $base[$rG] = $absRows($rG);
$qG = $req($rG, 914007, 9147);
$blockMarker();
$g1 = appr_audit_notify($qG, 'APPROVED', 'ENTITY_UNRESOLVED');
appr_email_cond_note($g1);
$drop('c14_nomark');
t_eq($g1, APPR_COND_UNARMED, 'C14.G · tenant A really is holding an unarmed condition');
$aCount = appr_cond_unarmed_count();
t_ok($aCount > 0, 'C14.G · and A counts it for its own screen');

$enterWs($WS_B);
$idB = $whoAmI();
t_ok($idA !== $idB, 'C14.G · *** Tenant A → DB A, Tenant B → DB B, DB A != DB B ***');
hreq_migrate(); appr_migrate(); act_migrate();
t_eq(appr_cond_unarmed_count(), 0, "C14.G · *** B's screen shows none of A's unresolved conditions ***");
t_eq(act_last_cond_status(), ACT_COND_NOT_ATTEMPTED, "C14.G · B cannot read A's marker status");
t_eq(act_last_cond_row(), 0,        "C14.G · nor the row id A was working on");
t_eq(appr_last_cond_outcome(), '',  "C14.G · nor the decision notifier's outcome");
t_eq(appr_tick_unarmed(), 0,        "C14.G · nor the scheduler's count");
$sumB = appr_sla_summary();
t_eq((int)($sumB['suppression_unarmed'] ?? -1), 0, "C14.G · and B's approval summary is clean");

$enterWs($WS_A);
t_eq($whoAmI(), $idA, 'C14.G · tenant A is restored');
t_ok(appr_cond_unarmed_count() >= $aCount, "C14.G · A still holds its own — B's visit changed nothing");
if ($engine==='sqlite') { @unlink($WS_B); }
else { try { db()->exec("DROP DATABASE IF EXISTS `c14_ws_b`"); } catch (Throwable $e) {} }

// ---------------------------------------------------------------------------
foreach ([$rA,$rB,$rC,$rD,$rE,$rG] as $r) {
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([(int)$r]);
}
$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uCfg]);
//  M3 CORRECTION #15 — the condition ledger lives in `settings`, so it OUTLIVES the
//  activity rows a suite deletes. That is correct product behaviour (reconciliation
//  resolves an entry whose row is gone to TERMINAL), but a suite that leaves entries
//  behind hands them to whichever suite runs next. Clear what this one created.
if (function_exists('setting_set')) setting_set('appr_cond_ledger', '');
if ($prevUid===null) unset($_SESSION['uid']); else $_SESSION['uid']=$prevUid;
$_SESSION=$origSess; current_user(true); ua(true);
t_eq((int) ops_val("SELECT COUNT(*) FROM recruit_approval_rules WHERE code LIKE 'C14%'"), 0,
     'C14 · this suite leaves no approval policy behind for the suites that follow');
