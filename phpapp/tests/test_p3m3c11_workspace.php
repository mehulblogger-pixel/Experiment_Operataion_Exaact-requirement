<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #11
//
//  W1  Every error channel in the spine was a process global while this
//      application switches workspace IN PROCESS. A failure recorded against
//      company A was returned after the switch to company B — a false diagnosis
//      in B, and A's database error text handed to B.
//
//  W2  act_set_cond_key() returned one boolean for "nothing has tried to create
//      the column" and "the write was attempted and failed" — V1's sentence,
//      ABSENT IS NOT FAILED, on the writer side.
//
//  The core INSERT failure is forced with a BEFORE INSERT trigger (correction #9's
//  mechanism), and the metadata write failure with a BEFORE UPDATE trigger, each
//  carrying a marker unique to its workspace so a leak is unmistakable.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #11 — an error belongs to its workspace');

$pdo = db(); act_migrate();
$engine = db_driver();
$mine = [];
$epoch0 = db_epoch();
//  Genuine in-process workspace switching (§3). db() assigns __db_epoch from its
//  own counter on db(true); these values sit far outside that counter's range so
//  nothing this suite does can collide with a switch made elsewhere in the run.
$WS = ['A' => 920001, 'B' => 920002, 'C' => 920003];
$enter = function ($w) use ($WS) { $GLOBALS['__db_epoch'] = $WS[$w]; };
$trigOn = function ($event, $mark) use ($pdo, $engine) {
    $pdo->exec($engine === 'sqlite'
        ? "CREATE TRIGGER c11_block BEFORE $event ON activities BEGIN SELECT RAISE(ABORT, '$mark'); END"
        : "CREATE TRIGGER c11_block BEFORE $event ON activities FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '$mark'");
};
$trigOff = function () use ($pdo) { try { $pdo->exec("DROP TRIGGER c11_block"); } catch (Throwable $e) {} };

// ---------------------------------------------------------------------------
//  C11.1 · W1 · CASE A — workspace A fails, and A sees ITS OWN error
// ---------------------------------------------------------------------------
t_section('C11.1 · W1 CASE A · A fails and A sees A\'s error');
$MARK_A = 'C11 WORKSPACE A ONLY SECRET';
$enter('A');
$trigOn('INSERT', $MARK_A);
try {
    $r = act_log('LEAD', 820001, 'SYSTEM', 'C11 — A cannot write');
    t_eq($r, 0, 'C11.1 A · the core write genuinely failed in A');
    t_ok(strpos(act_last_error(), $MARK_A) !== false, 'C11.1 A · and A sees A\'s own error');
} finally { $trigOff(); }

// ---------------------------------------------------------------------------
//  C11.2 · W1 · CASE B — THE DEFECT. B has failed at nothing.
//  §10 — B is asked BEFORE it is ever made to fail, which is the whole point.
// ---------------------------------------------------------------------------
t_section('C11.2 · W1 CASE B · B has failed at nothing and must see nothing');
$enter('B');
t_eq(act_last_error(), '', 'C11.2 B · B, which has failed at nothing, sees NO error');
//  §5 — nothing of A's may reach B.
foreach ([$MARK_A, 'SQLSTATE', 'activities', 'RAISE', '45000', '23000'] as $leak)
    t_ok(strpos(act_last_error(), $leak) === false, 'C11.2 B · §5 · no trace of A reaches B — "' . $leak . '"');
t_eq(act_optional_error(), '',       'C11.2 B · nor on the column channel');
t_eq(act_optional_index_error(), '', 'C11.2 B · nor the index channel');

// ---------------------------------------------------------------------------
//  C11.3 · W1 · CASE C — B's own failure is B's, and is not A's
// ---------------------------------------------------------------------------
t_section('C11.3 · W1 CASE C · B fails, and sees only B\'s error');
$MARK_B = 'C11 WORKSPACE B ONLY SECRET';
$trigOn('INSERT', $MARK_B);
try {
    t_eq(act_log('LEAD', 820002, 'SYSTEM', 'C11 — B cannot write'), 0, 'C11.3 C · B genuinely fails too');
    t_ok(strpos(act_last_error(), $MARK_B) !== false, 'C11.3 C · B sees B\'s error');
    t_ok(strpos(act_last_error(), $MARK_A) === false, 'C11.3 C · and A\'s error is NOT returned');
} finally { $trigOff(); }

// ---------------------------------------------------------------------------
//  C11.4 · W1 · CASE D + A→B→C→A — keyed, not cleared
// ---------------------------------------------------------------------------
t_section('C11.4 · W1 CASE D · A → B → C → A');
$enter('C');
t_eq(act_last_error(), '', 'C11.4 · C, a third workspace, sees nothing from either');
//  §2 CASE D + §4 — THE DOCUMENTED CONTRACT.
//  The channel holds the MOST RECENT error and reveals it only to the workspace
//  that produced it. It is deliberately single-slot: keeping one entry per
//  workspace would be the "global historical error store" §4 forbids. So a
//  workspace whose entry has since been superseded reads EMPTY — never stale, and
//  never somebody else's. The safety property is what matters and is asserted
//  here; the retention property is a design choice, and this is it.
$enter('A');
$backInA = act_last_error();
t_ok(strpos($backInA, $MARK_B) === false, 'C11.4 D · back in A, B\'s error is NEVER returned');
t_ok($backInA === '' || strpos($backInA, $MARK_A) !== false,
     'C11.4 D · A sees either its own error or nothing — never another workspace\'s');
$enter('B');
t_ok(strpos(act_last_error(), $MARK_B) !== false, 'C11.4 · B, the most recent writer, still has B\'s');
t_ok(strpos(act_last_error(), $MARK_A) === false, 'C11.4 · and A\'s never became B\'s');

// ---------------------------------------------------------------------------
//  C11.5 · W2 · the writer's four outcomes, each by the path actually taken
// ---------------------------------------------------------------------------
t_section('C11.5 · W2 · STORED · FAILED · UNAVAILABLE · NOT_ATTEMPTED');
$enter('C');
act_cond_column_ready(); act_cond_index_ready();
$id = act_log('LEAD', 820010, 'SYSTEM', 'C11 writer probe', ['auto'=>1]);
$mine[] = $id;
t_ok($id > 0, 'C11.5 · a core row exists to attach metadata to');

//  CASE B — the column exists and the UPDATE succeeds
t_eq(act_set_cond_key($id, 'PC|C11|1'), ACT_COND_STORED, 'C11.5 B · a real write reports STORED');
t_eq((string) ops_val("SELECT cond_key FROM activities WHERE id=?", [$id]), 'PC|C11|1',
     'C11.5 B · …and the value is genuinely there — STORED is not merely claimed');

//  CASE C — the column exists, the UPDATE itself genuinely fails
$trigOn('UPDATE', 'C11 WRITE BLOCKED');
try {
    $res = act_set_cond_key($id, 'PC|C11|2');
    t_eq($res, ACT_COND_FAILED, 'C11.5 C · an attempted write that fails reports FAILED');
    t_ok(strpos(act_optional_error(), 'C11 WRITE BLOCKED') !== false,
         'C11.5 C · with the real reason recorded — the WRITE\'s own channel');
    t_eq(act_cond_column_status(), ACT_OPT_READY,
         'C11.5 C · §8 · and the COLUMN is still READY — a write failure is not a migration failure');
} finally { $trigOff(); }
t_eq((string) ops_val("SELECT cond_key FROM activities WHERE id=?", [$id]), 'PC|C11|1',
     'C11.5 C · the earlier value is untouched by the failed write');
//  §9 — and the CORE row survives the optional failure
t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE id=?", [$id]), 1,
     'C11.5 · §9 · the core audit row is still there after the metadata write failed');

//  CASE A — nothing has attempted the column, and the column CAN be made
try { $pdo->exec($engine === 'sqlite' ? "DROP INDEX IF EXISTS idx_act_cond" : "DROP INDEX idx_act_cond ON activities"); } catch (Throwable $e) {}
$pdo->exec("ALTER TABLE activities DROP COLUMN cond_key");
$GLOBALS['__db_epoch'] = 920004;                       // a workspace that has attempted nothing
t_eq(act_optional_state()['column_status'], ACT_OPT_NOT_ATTEMPTED, 'C11.5 A · precondition · nothing attempted');
$resA = act_set_cond_key($id, 'PC|C11|3');
t_ok($resA !== ACT_COND_FAILED, 'C11.5 A · an unattempted column is NOT reported as a failed write');
t_eq($resA, ACT_COND_STORED, 'C11.5 A · the writer creates it and stores — so FAILED would have been a lie');

//  CASE D — the column is absent AND the migration genuinely cannot run
try { $pdo->exec($engine === 'sqlite' ? "DROP INDEX IF EXISTS idx_act_cond" : "DROP INDEX idx_act_cond ON activities"); } catch (Throwable $e) {}
$pdo->exec("ALTER TABLE activities DROP COLUMN cond_key");
$pdo->exec($engine === 'sqlite' ? "ALTER TABLE activities RENAME TO activities_c11" : "RENAME TABLE activities TO activities_c11");
try {
    $GLOBALS['__db_epoch'] = 920005;
    $resD = act_set_cond_key(1, 'PC|C11|4');
    t_eq($resD, ACT_COND_UNAVAILABLE, 'C11.5 D · a column that cannot be made reports UNAVAILABLE');
    t_ok($resD !== ACT_COND_STORED,   'C11.5 D · and never resembles a successful write');
    t_ok($resD !== ACT_COND_FAILED,   'C11.5 D · §8 · nor a failure of the WRITE, which never ran');
} finally {
    $pdo->exec($engine === 'sqlite' ? "ALTER TABLE activities_c11 RENAME TO activities" : "RENAME TABLE activities_c11 TO activities");
}
//  the four outcomes are genuinely four
t_eq(count(array_unique([ACT_COND_STORED, ACT_COND_FAILED, ACT_COND_UNAVAILABLE, ACT_COND_NOT_ATTEMPTED])), 4,
     'C11.5 · §6 · the contract has four distinct outcomes, not one boolean');

// ---------------------------------------------------------------------------
//  Clean up
// ---------------------------------------------------------------------------
$GLOBALS['__db_epoch'] = 920006;
act_cond_column_ready(); act_cond_index_ready();
foreach (array_filter($mine) as $a) $pdo->prepare("DELETE FROM activities WHERE id=?")->execute([(int)$a]);
$pdo->exec("DELETE FROM activities WHERE subject LIKE 'C11 %'");
$GLOBALS['__db_epoch'] = $epoch0;
t_eq(db_epoch(), $epoch0, 'C11 · the database epoch is restored');
t_ok(act_has_cond_column() && act_has_cond_index(), 'C11 · and the spine is left whole');
