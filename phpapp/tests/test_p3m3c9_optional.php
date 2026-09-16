<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #9
//
//  T1  C8.6 claimed to force a CORE INSERT failure by replacing the table with a
//      view. On SQLite a view cannot be inserted into, so it did. On MariaDB — the
//      authoritative engine — a view over one table is UPDATABLE: the INSERT
//      succeeded and the assertion passed only because CREATE INDEX threw first.
//      The claim was true; the mechanism was not the one it named.
//
//  T2  act_migrate_optional() ensured the COLUMN and the INDEX and returned one
//      boolean, so a failed index — a pure performance structure — reported the
//      whole feature unavailable and metadata was not written into a column that
//      existed and worked.
//
//          A USABLE COLUMN IS NOT AN INDEXED COLUMN.
//
//  Every failure below is produced by a real database fixture, and each test
//  names the operation that actually failed. Zero rows is never the proof.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #9 — a real core failure, and a real split');

$pdo = db(); act_migrate();
$engine = db_driver();
$mine = [];
$hasCol = fn() => act_has_cond_column();
$hasIdx = fn() => act_has_cond_index();
$rows   = fn($kind, $id) => (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind=? AND entity_id=?", [$kind, (int)$id]);
$epoch0 = db_epoch(); $bump = 0;
$bumpEpoch = function () use (&$bump) { $GLOBALS['__db_epoch'] = 940000 + (++$bump); };

// ---------------------------------------------------------------------------
//  C9.1 · T1 — A GENUINE CORE INSERT FAILURE, ON THIS ENGINE
//
//  Mechanism, stated per engine (§1, §9):
//    SQLite   BEFORE INSERT trigger raising RAISE(ABORT, …)
//    MariaDB  BEFORE INSERT trigger raising SIGNAL SQLSTATE '45000'
//  Both target the INSERT and nothing else: the table, the column and the index
//  are all left in place and are asserted to be healthy first, so neither the
//  optional migration nor the index can be the operation that failed.
// ---------------------------------------------------------------------------
t_section('C9.1 · T1 · the CORE INSERT itself fails — ' . strtoupper($engine));
$bumpEpoch();
t_ok($hasCol(), 'C9.1 · precondition · the cond_key COLUMN is present, so it cannot be the failure');
t_ok($hasIdx(), 'C9.1 · precondition · the cond_key INDEX is present, so it cannot be the failure');
$st0 = act_optional_state();
t_ok($st0['column'] === true && $st0['index'] === true,
     'C9.1 · precondition · optional migration reports BOTH healthy — nothing there is failing');
$ctrl = act_log('LEAD', 850001, 'SYSTEM', 'C9 control — before the trigger', ['auto'=>1]);
$mine[] = $ctrl;
t_ok($ctrl > 0, 'C9.1 · control · the core INSERT works right up to the moment it is blocked');

$MARK = 'C9 FORCED CORE INSERT FAILURE';
$before = (int) ops_val("SELECT COUNT(*) FROM activities");
try {
    $pdo->exec($engine === 'sqlite'
        ? "CREATE TRIGGER c9_block BEFORE INSERT ON activities BEGIN SELECT RAISE(ABORT, '" . $MARK . "'); END"
        : "CREATE TRIGGER c9_block BEFORE INSERT ON activities FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '" . $MARK . "'");

    //  The structures are STILL healthy — so whatever fails next is the INSERT.
    t_ok($hasCol(), 'C9.1 · the column is still present while the INSERT is blocked');
    t_ok($hasIdx(), 'C9.1 · the index is still present while the INSERT is blocked');

    $GLOBALS['__act_last_error'] = '';
    $idFail = act_log('LEAD', 850002, 'SYSTEM', 'C9 — this write cannot succeed', ['auto'=>1]);

    //  §3 — no false success.
    t_eq($idFail, 0, 'C9.1 · act_log() returns 0 — it does NOT report a row id it did not write');
    //  §9 — the failing operation is IDENTIFIED, not inferred from a row count.
    t_ok(strpos(act_last_error(), $MARK) !== false,
         'C9.1 · the recorded error names THE CORE INSERT as the operation that failed');
    t_eq(act_optional_error(), '', 'C9.1 · and the optional COLUMN migration reports no failure');
    t_eq(act_optional_index_error(), '', 'C9.1 · and the INDEX migration reports no failure either');
    //  row count is corroboration, never the proof
    t_eq((int) ops_val("SELECT COUNT(*) FROM activities"), $before, 'C9.1 · corroboration · no row was written');
    t_eq($rows('LEAD', 850002), 0, 'C9.1 · corroboration · and none for this subject');
} finally {
    try { $pdo->exec("DROP TRIGGER c9_block"); } catch (Throwable $e) {}
}
$idOk = act_log('LEAD', 850003, 'SYSTEM', 'C9 — after the block is lifted', ['auto'=>1]);
$mine[] = $idOk;
t_ok($idOk > 0, 'C9.1 · auditing resumes once the INSERT can succeed again');
t_eq(act_last_error(), '', 'C9.1 · and the recorded failure is cleared');

// ---------------------------------------------------------------------------
//  C9.2 · T1 — the OLD fixture is shown not to prove this on MariaDB
// ---------------------------------------------------------------------------
t_section('C9.2 · T1 · why the view fixture was replaced');
$pdo->exec("CREATE TABLE c9_real (id " . (($engine === 'sqlite') ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY') . ", a VARCHAR(40))");
$pdo->exec("CREATE VIEW c9_view AS SELECT * FROM c9_real");
$viewInsertable = true;
try { $pdo->prepare("INSERT INTO c9_view (a) VALUES (?)")->execute(['x']); }
catch (Throwable $e) { $viewInsertable = false; }
if ($engine === 'sqlite') {
    t_ok(!$viewInsertable, 'C9.2 · on SQLite a view is NOT insertable — which is why the old fixture worked here');
} else {
    t_ok($viewInsertable,  'C9.2 · on MariaDB a view over one table IS insertable — the old fixture proved something else');
}
$pdo->exec("DROP VIEW c9_view"); $pdo->exec("DROP TABLE c9_real");

// ---------------------------------------------------------------------------
//  C9.3 · T2 CASE A — both available
// ---------------------------------------------------------------------------
t_section('C9.3 · T2 CASE A · column YES · index YES');
$bumpEpoch();
$st = act_optional_state();
t_ok($st['column'] === true, 'C9.3 A · the column is available');
t_ok($st['index']  === true, 'C9.3 A · the index is available');
$idA = act_log('LEAD', 850010, 'SYSTEM', 'C9 case A', ['auto'=>1, 'cond_key'=>'PC|C9|A|1']);
$mine[] = $idA;
t_eq((string) ops_val("SELECT cond_key FROM activities WHERE id=?", [$idA]), 'PC|C9|A|1',
     'C9.3 A · and the metadata is stored');

// ---------------------------------------------------------------------------
//  C9.4 · T2 CASE E — THE PRIMARY ACCEPTANCE TEST
//  column YES · index NO  →  cond_key is STILL stored
//
//  Mechanism per engine:
//    SQLite   the index NAME is taken by a table (one namespace), so CREATE INDEX
//             fails with "there is already a table named …"
//    MariaDB  the table is filled to its hard 64-key limit, so CREATE INDEX fails
//             with "Too many keys specified". (Widening the column does NOT work:
//             MariaDB silently creates a prefix index instead of refusing, and a
//             TEXT column is auto-prefixed too — both were tried and rejected as
//             fixtures because they did not actually block anything.)
//  In both, the COLUMN is present and perfectly writable throughout.
// ---------------------------------------------------------------------------
t_section('C9.4 · T2 CASE E · column YES · index NO — ' . strtoupper($engine));
try { $pdo->exec($engine === 'sqlite' ? "DROP INDEX IF EXISTS idx_act_cond" : "DROP INDEX idx_act_cond ON activities"); } catch (Throwable $e) {}
$blocked = false; $c9fills = 0;
try {
    if ($engine === 'sqlite') { $pdo->exec("CREATE TABLE idx_act_cond (x INT)"); }
    else {
        //  Fill to EXACTLY the limit, counting what the table already carries —
        //  filling blindly overshoots and the fixture itself is what fails.
        $have = (int) ops_val("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics
                               WHERE table_schema=DATABASE() AND table_name='activities'");
        $need = 64 - $have;
        $fill = []; for ($i = 1; $i <= $need; $i++) $fill[] = "ADD INDEX c9fill$i (auto)";
        if ($fill) $pdo->exec("ALTER TABLE activities " . implode(', ', $fill));
        $c9fills = $need;
    }
    $bumpEpoch();
    t_ok($hasCol(),  'C9.4 E · the COLUMN is present');
    t_ok(!$hasIdx(), 'C9.4 E · the INDEX is genuinely absent');
    //  U1 — act_optional_state() only OBSERVES; it never attempts, so it cannot
    //  produce an error by itself. The attempt is made through the path the
    //  application really uses, and the observation reports what that attempt
    //  left behind. An error that only a health check can conjure is not evidence.
    t_ok(act_migrate_optional() === true, 'C9.4 E · the real migration path runs and the COLUMN still succeeds');
    $st = act_optional_state();
    $blocked = ($st['index'] === false);
    t_ok($st['column'] === true,  'C9.4 E · §6 · the column reports AVAILABLE — the index failure did not roll it back');
    t_ok($st['index']  === false, 'C9.4 E · the index reports UNAVAILABLE, on its own account');
    t_ok($st['index_error'] !== '', 'C9.4 E · with its own error, separate from the column\'s');
    t_eq($st['column_error'], '',   'C9.4 E · and the column records NO error');

    //  The point of the whole correction:
    $idE = act_log('LEAD', 850020, 'SYSTEM', 'C9 case E', ['auto'=>1, 'cond_key'=>'PC|C9|E|1']);
    $mine[] = $idE;
    t_ok($idE > 0, 'C9.4 E · the core audit row is written');
    t_eq((string) ops_val("SELECT cond_key FROM activities WHERE id=?", [$idE]), 'PC|C9|E|1',
         'C9.4 E · AND THE METADATA IS STORED — a performance structure is not a correctness precondition');
    t_ok(act_set_cond_key($idE, 'PC|C9|E|2') === true,
         'C9.4 E · the optional write reports the success it actually achieved');

    //  …and correction #7's idempotency still works with no index, just slower.
    t_ok(function_exists('appr_condition_seen') && appr_condition_seen('PC|C9|E|2') === true,
         'C9.4 E · suppression metadata remains FUNCTIONAL without its index');
} finally {
    if ($engine === 'sqlite') { try { $pdo->exec("DROP TABLE idx_act_cond"); } catch (Throwable $e) {} }
    else {
        $drop = []; for ($i = 1; $i <= ($c9fills ?? 0); $i++) $drop[] = "DROP INDEX c9fill$i";
        if ($drop) { try { $pdo->exec("ALTER TABLE activities " . implode(', ', $drop)); } catch (Throwable $e) {} }
    }
}
t_ok($blocked, 'C9.4 E · the fixture genuinely blocked the index — this case was really exercised');

// ---------------------------------------------------------------------------
//  C9.5 · §7 — the INDEX retries on its own, and the column is unaffected
// ---------------------------------------------------------------------------
t_section('C9.5 · §7 · independent, bounded retry');
$bumpEpoch();
t_ok(act_cond_index_ready() === true, 'C9.5 · once the obstacle is gone the INDEX migration succeeds on retry');
t_ok($hasIdx(),                       'C9.5 · the index is really there');
t_ok(act_cond_column_ready() === true,'C9.5 · and the column was available throughout');
t_eq(act_optional_index_error(), '',  'C9.5 · the index error is cleared');

// ---------------------------------------------------------------------------
//  C9.6 · T2 CASE C — column unavailable
// ---------------------------------------------------------------------------
t_section('C9.6 · T2 CASE C · column NO');
try { $pdo->exec($engine === 'sqlite' ? "DROP INDEX IF EXISTS idx_act_cond" : "DROP INDEX idx_act_cond ON activities"); } catch (Throwable $e) {}
$pdo->exec("ALTER TABLE activities DROP COLUMN cond_key");
//  The epoch is deliberately NOT moved: moving it would let the migration re-add
//  the column, which models a host that CAN run DDL — the opposite of this case.
t_ok(!$hasCol(), 'C9.6 C · the column is genuinely absent');
$idC = act_log('LEAD', 850030, 'SYSTEM', 'C9 case C', ['auto'=>1, 'cond_key'=>'PC|C9|C|1']);
$mine[] = $idC;
t_ok($idC > 0,          'C9.6 C · the core audit row is STILL written');
t_eq($rows('LEAD', 850030), 1, 'C9.6 C · and it is really there');
t_eq(act_last_error(), '', 'C9.6 C · with no core failure recorded');
t_ok(act_set_cond_key($idC, 'PC|C9|C|1') === false,
     'C9.6 C · and the metadata is correctly reported as NOT stored');
//  §8 · the impossible row — an index without its column is never claimed.
//  The epoch is moved here ON PURPOSE, but the table is moved out of reach at the
//  same time, so the column CANNOT be re-added. Moving the epoch alone would let
//  the migration quietly repair itself and this row would test nothing.
$pdo->exec($engine === 'sqlite' ? "ALTER TABLE activities RENAME TO activities_c9tmp" : "RENAME TABLE activities TO activities_c9tmp");
try {
    $bumpEpoch();
    $stC = act_optional_state();
    t_eq($stC['column'], false, 'C9.6 C · column is not ready');
    t_eq($stC['index'],  false, 'C9.6 C · §8 · and the index is never claimed available without it');
    //  M3 correction #10 · V1 — this assertion used to require the index to say
    //  "the column is unavailable". Nothing had ATTEMPTED anything in this epoch,
    //  so that was a claim about a failure which had not happened: it is the
    //  defect V1 names, written into a test. The truthful answer here is that
    //  neither has been attempted.
    t_eq($stC['column_status'], ACT_OPT_NOT_ATTEMPTED, 'C9.6 C · and says so honestly — NOT_ATTEMPTED, not FAILED');
    t_eq($stC['index_status'],  ACT_OPT_NOT_ATTEMPTED, 'C9.6 C · the index likewise');
    t_ok(strpos($stC['index_error'], 'column is unavailable') === false,
         'C9.6 C · the index does NOT assert a column failure that never occurred');
} finally {
    $pdo->exec($engine === 'sqlite' ? "ALTER TABLE activities_c9tmp RENAME TO activities" : "RENAME TABLE activities_c9tmp TO activities");
}
//  restore for the rest of the run
$bumpEpoch();
t_ok(act_cond_column_ready() === true, 'C9.6 C · §7 · the COLUMN migration succeeds on retry');
t_ok($hasCol(), 'C9.6 C · cond_key is back');
t_ok(act_cond_index_ready() === true, 'C9.6 C · and the index follows');

// ---------------------------------------------------------------------------
//  C9.7 · the two things the mutation battery proved were NOT being asserted
//
//  T2-M2 survived: nothing checked that a COLUMN failure reports itself as a
//  COLUMN error. Writing it into the index's error instead passed every test.
//  T2-M6 survived: nothing checked that a structure which is ALREADY THERE is
//  recognised without spending a DDL attempt — so removing the cheap existence
//  probe changed no result, even though it is what lets a repaired schema be
//  seen once the retry budget is gone.
// ---------------------------------------------------------------------------
t_section('C9.7 · a column failure is a COLUMN error, and a repaired schema is seen');
$pdo->exec($engine === 'sqlite' ? "ALTER TABLE activities RENAME TO activities_c9r" : "RENAME TABLE activities TO activities_c9r");
try {
    $bumpEpoch();
    //  T2-M2 — the failure must name the COLUMN, on the column's own channel.
    t_ok(act_cond_column_ready() === false, 'C9.7 · with the table away the COLUMN migration fails');
    t_ok(act_optional_error() !== '',        'C9.7 · and the failure is reported');
    t_ok(strpos(act_optional_error(), 'cond_key column') !== false,
         'C9.7 · on the COLUMN\'s own channel, naming the column — not filed under the index');
    //  Spend the rest of the bounded budget while it cannot possibly succeed.
    act_cond_column_ready(); act_cond_column_ready(); act_cond_column_ready();
    t_ok(act_cond_column_ready() === false, 'C9.7 · repeated attempts stay bounded and keep failing');
} finally {
    $pdo->exec($engine === 'sqlite' ? "ALTER TABLE activities_c9r RENAME TO activities" : "RENAME TABLE activities_c9r TO activities");
}
//  T2-M6 — the column was never actually missing, only unreachable. Now that the
//  table is back it IS there, and that must be recognised WITHOUT a DDL attempt —
//  the retry budget for this epoch is already spent.
t_ok($hasCol(), 'C9.7 · the column was there all along — only the table was out of reach');
t_ok(act_cond_column_ready() === true,
     'C9.7 · and it is recognised at once, though the retry budget for this epoch is spent');
t_eq(act_optional_error(), '', 'C9.7 · the recorded failure is cleared');

// ---------------------------------------------------------------------------
//  C9.8 · U1 — ASKING IS NOT ATTEMPTING
//
//  act_optional_state() is what a health check, a support screen or a diagnostic
//  calls. It used to answer by running the readiness functions, each of which may
//  spend one of the three DDL attempts reserved for REPAIRING the thing it is
//  reporting on — so polling it during an outage disarmed the repair.
// ---------------------------------------------------------------------------
t_section('C9.8 · U1 · observing the state must not spend the repair budget');
try { $pdo->exec($engine === 'sqlite' ? "DROP INDEX IF EXISTS idx_act_cond" : "DROP INDEX idx_act_cond ON activities"); } catch (Throwable $e) {}
$pdo->exec("ALTER TABLE activities DROP COLUMN cond_key");
$pdo->exec($engine === 'sqlite' ? "ALTER TABLE activities RENAME TO activities_c9u" : "RENAME TABLE activities TO activities_c9u");
try {
    $bumpEpoch();
    //  Six health-check polls — twice the entire retry budget.
    for ($i = 0; $i < 6; $i++) $stU = act_optional_state();
    t_eq($stU['column'], false, 'C9.8 · during the outage the state reports the column unavailable');
    t_eq($stU['index'],  false, 'C9.8 · and the index unavailable');
} finally {
    $pdo->exec($engine === 'sqlite' ? "ALTER TABLE activities_c9u RENAME TO activities" : "RENAME TABLE activities_c9u TO activities");
}
//  The column is genuinely missing now, so the repair needs a real DDL attempt.
//  If the six observations had consumed the budget there would be none left.
t_ok(!$hasCol(), 'C9.8 · the column is genuinely gone — the repair needs a real attempt');
t_ok(act_cond_column_ready() === true,
     'C9.8 · and the repair still succeeds — SIX observations spent none of its budget');
t_ok($hasCol(), 'C9.8 · cond_key is back');
t_ok(act_cond_index_ready() === true, 'C9.8 · and the index follows');

// ---------------------------------------------------------------------------
//  Clean up
// ---------------------------------------------------------------------------
foreach (array_filter($mine) as $a) $pdo->prepare("DELETE FROM activities WHERE id=?")->execute([(int)$a]);
$GLOBALS['__db_epoch'] = $epoch0;
t_eq(db_epoch(), $epoch0, 'C9 · the database epoch is restored — no later suite inherits a moved guard');
t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE subject LIKE 'C9 %'"), 0, 'C9 · fixtures removed');
t_ok($hasCol() && $hasIdx(), 'C9 · and the spine is left whole');
