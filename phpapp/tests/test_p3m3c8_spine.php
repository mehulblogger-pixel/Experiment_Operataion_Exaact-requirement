<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #8 — THE SHARED AUDIT SPINE MUST NOT DEPEND ON
//  OPTIONAL METADATA  (S1)
//
//  Correction #7 put cond_key into the INSERT inside act_log() — the one
//  function all SEVENTY-SIX audit call sites in this application use. Two of
//  them pass a cond_key. The other seventy-four gained a precondition for a
//  feature they do not use, and when it was not met they wrote NOTHING and
//  reported success to their callers.
//
//      OPTIONAL AUDIT METADATA IS NEVER A PRECONDITION FOR THE CORE EVENT.
//
//  Nothing here is mocked. The column is really dropped, on whichever engine is
//  running, and the migration is really made to fail.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #8 — the core audit row always survives');

$pdo = db(); act_migrate(); hreq_migrate(); appr_migrate();
$mine = ['u'=>[], 'o'=>[], 'rule'=>[], 'rq'=>[], 'h'=>[], 'act'=>[]];
$origSess = $_SESSION;
$engine = db_driver();
//  This suite has to make migration guards re-evaluate, which means moving the
//  database epoch. db() keeps its OWN counter and assigns $GLOBALS['__db_epoch']
//  from it on every db(true); if a raw value collides with one that counter later
//  hands out, a guard that ran against THIS database believes it has already run
//  against the NEXT one — and a workspace switched into later in the run gets a
//  half-built schema. That is exactly what happened: five failures in
//  test_saas_login_as, on both engines, from "no such column: must_change_pwd".
//  So the epoch is moved into a range that counter can never reach, and put back.
$epoch0 = db_epoch();
$bump = 0;
$bumpEpoch = function () use (&$bump) { $GLOBALS['__db_epoch'] = 900000 + (++$bump); };

$rows = fn($kind, $id) => (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind=? AND entity_id=?", [$kind, (int)$id]);
$dropIndex = function () use ($pdo, $engine) {
    try { $pdo->exec($engine === 'sqlite' ? "DROP INDEX IF EXISTS idx_act_cond" : "DROP INDEX idx_act_cond ON activities"); }
    catch (Throwable $e) {}
};
//  t_columns() returns the LIST of column names. Asked as
//  t_columns('activities', ['cond_key']) it returns a non-empty array whatever
//  the answer is, so the check passes even when the column is gone. That exact
//  mistake is live in correction #7's own C7.0 and is fixed there too.
$hasCond = fn() => in_array('cond_key', t_columns('activities'), true);

// ---------------------------------------------------------------------------
//  C8.1 · TEST A — the normal case, so the later ones cannot pass vacuously
// ---------------------------------------------------------------------------
t_section('C8.1 · TEST A · with the column present, everything behaves as before');
t_ok($hasCond(), 'C8.1 A · cond_key is present to begin with');
$idA = act_log('LEAD', 880001, 'SYSTEM', 'C8 test A — a normal audit row', ['auto'=>1, 'cond_key'=>'PC|TEST|A|1']);
$mine['act'][] = $idA;
t_ok($idA > 0, 'C8.1 A · act_log() reports the row it wrote');
t_eq($rows('LEAD', 880001), 1, 'C8.1 A · the core row exists');
t_eq((string) ops_val("SELECT cond_key FROM activities WHERE id=?", [$idA]), 'PC|TEST|A|1',
     'C8.1 A · and the optional metadata was genuinely stored');
t_eq(act_last_error(), '', 'C8.1 A · no core audit error is recorded');
t_ok(act_optional_ready() === true, 'C8.1 A · optional metadata reports itself usable');

// ---------------------------------------------------------------------------
//  C8.2 · TESTS B, E-I — THE COLUMN IS REALLY GONE
// ---------------------------------------------------------------------------
t_section('C8.2 · TEST B/E-I · with the column absent, every module still writes');
$dropIndex();
$pdo->exec("ALTER TABLE activities DROP COLUMN cond_key");
//  The epoch is deliberately NOT moved. Moving it would let the optional
//  migration re-add the column, which models a host that CAN run DDL — the
//  opposite of the condition under test. Leaving the caches as they are models
//  the production case this defect is about: the process believes the column is
//  there, and it is not.
t_ok(!$hasCond(), 'C8.2 · cond_key is genuinely absent — this is the real condition, not a mock');

//  §6 — a core failure must never look like success, so the return value is
//  checked as well as the row.
$cases = [
    'E · CRM (lead)'         => ['LEAD',           880002],
    'E · CRM (opportunity)'  => ['OPPORTUNITY',    880003],
    'F · quotation'          => ['QUOTE',          880004],
    'F · money (invoice)'    => ['INVOICE',        880005],
    'H · operations (job)'   => ['JOB',            880006],
    'I · quality (NCR)'      => ['NCR',            880007],
    'I · marketplace'        => ['CONTRACT',       880008],
    'I · platform (partner)' => ['PARTNER',        880009],
];
foreach ($cases as $lab => [$kind, $eid]) {
    $id = act_log($kind, $eid, 'SYSTEM', 'C8 test B — ' . $lab, ['auto'=>1]);
    $mine['act'][] = $id;
    t_ok($id > 0, "C8.2 $lab · act_log() reports success");
    t_eq($rows($kind, $eid), 1, "C8.2 $lab · and the audit row IS THERE");
    t_eq(act_last_error(), '', "C8.2 $lab · with no core audit error");
}
//  …including a caller that DOES pass a condition key.
$idB = act_log('LEAD', 880010, 'SYSTEM', 'C8 test B — a caller that passes a key', ['auto'=>1, 'cond_key'=>'PC|TEST|B|1']);
$mine['act'][] = $idB;
t_ok($idB > 0, 'C8.2 B · a caller passing cond_key still gets its core row written');
t_eq($rows('LEAD', 880010), 1, 'C8.2 B · the core row exists even though the metadata could not be stored');
t_ok(act_set_cond_key($idB, 'PC|TEST|B|1') === false,
     'C8.2 B · and the system does NOT claim the metadata was stored — no false success');
t_ok(act_optional_error() !== '',    'C8.2 B · and says why — the failure is observable, not swallowed');
t_ok(strpos(act_optional_error(), 'cond_key') !== false, 'C8.2 B · naming the metadata that could not be stored');

// ---------------------------------------------------------------------------
//  C8.3 · TEST G — a REAL recruitment approval action, end to end
// ---------------------------------------------------------------------------
t_section('C8.3 · TEST G · a genuine approval refusal still reaches the audit trail');
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (895,'C8',1)")->execute(); $mine['o'][]=895; } catch (Throwable $e) {}
$mk = function ($un,$role,$su,$p,$em) use ($pdo,&$mine) {
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,role,is_active,is_superuser,home_office_id,scope_offices,permissions,email)
                   VALUES (?,'C8','U',?,1,?,895,'895',?,?)")->execute([$un,$role,$su?1:0,$p,$em]);
    $id=(int)$pdo->lastInsertId(); $mine['u'][]=$id; return $id;
};
$HR = 'mod.hiring.view,mod.hiring.edit';
$uR = $mk('c8r','BRANCH_MANAGER',0,$HR,'c8r@t.test');
$uA = $mk('c8a','SBU_HEAD',0,$HR,'c8a@t.test');
$uM = $mk('c8m','ADMIN',1,'','c8m@t.test');
$_SESSION['uid']=$uM; current_user(true); ua(true);
$rid = appr_rule_save(0,['name'=>'C8','entity'=>'HIRING_REQUEST','code'=>'C8','applies_department'=>'']);
$mine['rule'][]=$rid;
appr_level_save(['rule_id'=>$rid,'seq'=>1,'label'=>'L','approver_user_id'=>$uA,'sla_days'=>5,'reminder_days'=>1]);
$_SESSION['uid']=$uR; current_user(true); ua(true);
[$ok,,$h] = hreq_save(0,['job_title'=>'C8','quantity'=>1,'office_id'=>895,'priority'=>'NORMAL','approval_required'=>1]);
$mine['h'][]=$h; hreq_submit($h); $req = hreq_approval($h); $mine['rq'][]=(int)$req['id'];
$pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([$h]);
$before = $rows('APPROVAL_POLICY', $rid);
$why = appr_email_requester($req, 'approved', '');
t_eq($why, 'ENTITY_UNRESOLVED', 'C8.3 G · the approval genuinely refused to notify — a real action ran');
t_eq($rows('APPROVAL_POLICY', $rid), $before + 1,
     'C8.3 G · and its audit row was written, although cond_key could not be stored');

// ---------------------------------------------------------------------------
//  C8.4 · §11 — the fallback removes the metadata dependency, NOT the controls
// ---------------------------------------------------------------------------
t_section('C8.4 · the fallback path keeps actor identity and context');
$_SESSION['uid']=$uA; current_user(true); ua(true);
$idS = act_log('LEAD', 880020, 'SYSTEM', 'C8 security — who wrote this?', ['auto'=>1]);
$mine['act'][] = $idS;
$r = ops_one("SELECT * FROM activities WHERE id=?", [$idS]);
t_ok(is_array($r), 'C8.4 · the row exists');
t_ok(trim((string)$r['created_by']) !== '' && trim((string)$r['created_by']) !== 'system',
     'C8.4 · the ACTOR is still recorded — the fallback did not become anonymous');
t_eq((int)$r['office_id'], 895, 'C8.4 · the actor\'s branch context is still recorded');
t_eq((int)$r['auto'], 1, 'C8.4 · the caller\'s own options are still honoured');

// ---------------------------------------------------------------------------
//  C8.5 · TEST C/D + §9 — migration failure, observability, and real retry
// ---------------------------------------------------------------------------
t_section('C8.5 · TEST C/D · a failed optional migration is not latched as success');
//  CASE 2 — make the migration genuinely fail. The table is moved out from under
//  it, which is what a half-applied deploy or an interrupted restore looks like.
//  act_log() is NOT called while it is away: this section is about the migration.
$pdo->exec($engine === 'sqlite'
    ? "ALTER TABLE activities RENAME TO activities_c8tmp"
    : "RENAME TABLE activities TO activities_c8tmp");
try {
    $bumpEpoch();
    t_ok(act_migrate_optional() === false, 'C8.5 C · the optional migration FAILED, and said so');
    t_ok(act_optional_error() !== '',      'C8.5 C · the failure is observable, not swallowed');
    t_ok(act_optional_ready() === false,   'C8.5 C · CASE 2 · the guard does NOT report success');
} finally {
    $pdo->exec($engine === 'sqlite'
        ? "ALTER TABLE activities_c8tmp RENAME TO activities"
        : "RENAME TABLE activities_c8tmp TO activities");
}
t_ok(t_table_exists('activities'), 'C8.5 · the table is back');
//  CASE 3 — the condition is restored; the migration must be RETRIED and succeed.
t_ok(act_migrate_optional() === true, 'C8.5 D · CASE 3 · the migration is retried after failure and SUCCEEDS');
t_ok($hasCond(),                      'C8.5 D · cond_key is available again');
t_eq(act_optional_error(), '',        'C8.5 D · and the recorded failure is cleared');
//  …and normal condition-key behaviour resumes.
$idD = act_log('LEAD', 880030, 'SYSTEM', 'C8 test D — after the retry', ['auto'=>1, 'cond_key'=>'PC|TEST|D|1']);
$mine['act'][] = $idD;
t_eq((string) ops_val("SELECT cond_key FROM activities WHERE id=?", [$idD]), 'PC|TEST|D|1',
     'C8.5 D · condition metadata is stored again — the retry genuinely restored the feature');

// ---------------------------------------------------------------------------
//  C8.6 · §6 / §9 — A CORE FAILURE MUST BE OBSERVABLE, AND THE GUARD MUST NOT
//  LATCH ON ONE
//
//  The failure is real: the table is replaced by a VIEW of itself. A view cannot
//  be inserted into and cannot be indexed, so the core INSERT fails the way a
//  broken or half-restored schema makes it fail — no mock, no injected flag.
//  cond_key is removed first, so that after the repair the ONLY way it can come
//  back is if act_migrate() genuinely retried instead of latching.
// ---------------------------------------------------------------------------
t_section('C8.6 · a core audit failure is visible, and the migration retries');
$viewOk = true;
try { $pdo->exec($engine === 'sqlite' ? "DROP INDEX IF EXISTS idx_act_cond" : "DROP INDEX idx_act_cond ON activities"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE activities DROP COLUMN cond_key"); } catch (Throwable $e) { $viewOk = false; }
t_ok(!$hasCond(), 'C8.6 · cond_key removed, so its return can only come from a real retry');
try {
    $pdo->exec($engine === 'sqlite' ? "ALTER TABLE activities RENAME TO activities_c8v" : "RENAME TABLE activities TO activities_c8v");
    $pdo->exec("CREATE VIEW activities AS SELECT * FROM activities_c8v");
    $bumpEpoch();

    //  §9 CASE 2 / S1-M9 — the optional migration cannot run, and says so rather
    //  than claiming the metadata was stored.
    t_ok(act_migrate_optional() === false, 'C8.6 · the optional migration genuinely fails against a view');
    t_ok(act_set_cond_key($idA, 'PC|TEST|NOPE|1') === false,
         'C8.6 · and the optional write does NOT claim a success it did not achieve');

    //  §6 / S1-M2 — the CORE write fails, and that failure is observable.
    $GLOBALS['__act_last_error'] = '';
    $idFail = act_log('LEAD', 880040, 'SYSTEM', 'C8 test — a write that cannot succeed', ['auto'=>1]);
    t_eq($idFail, 0,                'C8.6 · act_log() reports the core failure rather than a row id');
    t_ok(act_last_error() !== '',   'C8.6 · and the failure is RECORDED — it does not look like nothing happened');
} finally {
    try { $pdo->exec("DROP VIEW activities"); } catch (Throwable $e) {}
    try { $pdo->exec($engine === 'sqlite' ? "ALTER TABLE activities_c8v RENAME TO activities" : "RENAME TABLE activities_c8v TO activities"); } catch (Throwable $e) {}
}
t_ok(t_table_exists('activities'), 'C8.6 · the real table is back');
//  §9 CASE 3 / S1-M3 — THE EPOCH IS NOT BUMPED HERE. If act_migrate() had
//  latched its guard during the failure it would now return early and cond_key
//  would stay missing for ever. It comes back only because the migration retried.
$idAfter = act_log('LEAD', 880041, 'SYSTEM', 'C8 test — after the repair', ['auto'=>1]);
$mine['act'][] = $idAfter;
t_ok($idAfter > 0,  'C8.6 · CASE 3 · auditing works again after the schema is repaired');
t_eq(act_last_error(), '', 'C8.6 · and no core error is recorded any more');
t_ok($hasCond(),    'C8.6 · CASE 3 · cond_key is BACK — the guard did not latch on the failure');

// ---------------------------------------------------------------------------
//  Clean up
// ---------------------------------------------------------------------------
$_SESSION = $origSess; current_user(true); ua(true);
foreach (array_unique($mine['rq']) as $r) {
    $pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_requests WHERE id=?")->execute([(int)$r]);
}
foreach ($mine['rule'] as $r) {
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='APPROVAL_POLICY' AND entity_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_levels WHERE rule_id=?")->execute([(int)$r]);
    $pdo->prepare("DELETE FROM recruit_approval_rules WHERE id=?")->execute([(int)$r]);
}
foreach ($mine['h'] as $x) {
    $pdo->prepare("DELETE FROM activities WHERE entity_kind='HIRING_REQUEST' AND entity_id=?")->execute([(int)$x]);
    $pdo->prepare("DELETE FROM hiring_requests WHERE id=?")->execute([(int)$x]);
}
foreach (array_filter(array_unique($mine['act'])) as $a) $pdo->prepare("DELETE FROM activities WHERE id=?")->execute([(int)$a]);
foreach ($mine['u'] as $x) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$x]);
foreach ($mine['o'] as $x) $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([(int)$x]);
$GLOBALS['__db_epoch'] = $epoch0;      // hand the epoch back exactly as it was found
t_eq(db_epoch(), $epoch0, 'C8 · the database epoch is restored — no later suite inherits a moved guard');
$left = (int) ops_val("SELECT COUNT(*) FROM activities WHERE subject LIKE 'C8 %'");
t_eq($left, 0, 'C8 · every activity row this suite created has been cleaned up');
t_ok($hasCond(), 'C8 · and the spine is left with its optional column intact');
