<?php
// ============================================================================
//  PHASE 3 · M3 CORRECTION #10 — ABSENT IS NOT FAILED  (V1)
//
//  Correction #9 made observation read-only, which was right, and then answered a
//  THREE-valued question with a boolean. A structure nothing had tried to create
//  was reported exactly like one that had been attempted and could not be made:
//
//      column=false  column_error=''  index_error='cond_key index: the column is unavailable'
//
//  …with no attempt having occurred. A support screen could not tell "broken"
//  from "nobody has asked yet", which is the only question it exists to answer.
//
//  FAILED is never inferred from absence. It requires recorded evidence of a real
//  attempt — so every FAILED assertion below is preceded by an attempt that is
//  proved to have happened, and every NOT_ATTEMPTED by an attempt count of zero.
// ============================================================================

t_section('Phase 3 · M3 CORRECTION #10 — NOT_ATTEMPTED · FAILED · READY');

$pdo = db(); act_migrate();
$engine = db_driver();
$mine = [];
$epoch0 = db_epoch(); $b = 0;
$bump = function () use (&$b) { $GLOBALS['__db_epoch'] = 930000 + (++$b); };
$dropIdx = function () use ($pdo, $engine) {
    try { $pdo->exec($engine === 'sqlite' ? "DROP INDEX IF EXISTS idx_act_cond" : "DROP INDEX idx_act_cond ON activities"); } catch (Throwable $e) {}
};
$away = function () use ($pdo, $engine) {
    $pdo->exec($engine === 'sqlite' ? "ALTER TABLE activities RENAME TO activities_c10" : "RENAME TABLE activities TO activities_c10");
};
$back = function () use ($pdo, $engine) {
    $pdo->exec($engine === 'sqlite' ? "ALTER TABLE activities_c10 RENAME TO activities" : "RENAME TABLE activities_c10 TO activities");
};

// ---------------------------------------------------------------------------
//  C10.1 · CASE C / F — READY
// ---------------------------------------------------------------------------
t_section('C10.1 · CASE C + F · present means READY');
$bump();
t_eq(act_cond_column_status(), ACT_OPT_READY, 'C10.1 C · a column that is there is READY');
t_eq(act_cond_index_status(),  ACT_OPT_READY, 'C10.1 F · an index that is there is READY');
$st = act_optional_state();
t_eq($st['column_error'], '', 'C10.1 · a READY column carries no error');
t_eq($st['index_error'],  '', 'C10.1 · nor a READY index');
t_eq($st['column_tries'], 0,  'C10.1 · and no failed attempts are on the ledger');

// ---------------------------------------------------------------------------
//  C10.2 · §9 — THE V1 FALSE-GREEN, REPRODUCED AND THEN CORRECTED
//  column absent · NOTHING attempted · the column is perfectly creatable
// ---------------------------------------------------------------------------
t_section('C10.2 · §9 · CASE A + D · absent with no attempt is NOT_ATTEMPTED');
$dropIdx();
$pdo->exec("ALTER TABLE activities DROP COLUMN cond_key");
$bump();                                   // a fresh workspace: nothing attempted here
$st = act_optional_state();
t_eq($st['column_tries'], 0, 'C10.2 · the ledger records NO attempt — this is the precondition, not an assumption');
t_eq($st['index_tries'],  0, 'C10.2 · for the index either');
t_eq($st['column_status'], ACT_OPT_NOT_ATTEMPTED, 'C10.2 A · the column is NOT_ATTEMPTED — not FAILED');
t_eq($st['index_status'],  ACT_OPT_NOT_ATTEMPTED, 'C10.2 D · and the index is NOT_ATTEMPTED');
t_eq($st['column_error'], 'Not attempted', 'C10.2 · §4 · the message states the fact');
t_eq($st['index_error'],  'Not attempted', 'C10.2 · §4 · and so does the index\'s');
t_ok(strpos($st['index_error'], 'unavailable') === false,
     'C10.2 · §4 · nothing claims a failure that never happened');
//  …and the proof that NOT_ATTEMPTED was the right answer: one attempt succeeds.
t_ok(act_cond_column_ready() === true, 'C10.2 · one real attempt creates it — so FAILED would have been a lie');
t_eq(act_cond_column_status(), ACT_OPT_READY, 'C10.2 · §7 · NOT_ATTEMPTED → attempt → READY');

// ---------------------------------------------------------------------------
//  C10.3 · CASE B — a REAL failed attempt is FAILED, with a real reason
// ---------------------------------------------------------------------------
t_section('C10.3 · CASE B · attempted and genuinely failed is FAILED');
$dropIdx();
$pdo->exec("ALTER TABLE activities DROP COLUMN cond_key");
$away();
try {
    $bump();
    t_eq(act_optional_state()['column_status'], ACT_OPT_NOT_ATTEMPTED, 'C10.3 · before the attempt: NOT_ATTEMPTED');
    t_ok(act_cond_column_ready() === false, 'C10.3 · the attempt genuinely fails');
    $st = act_optional_state();
    t_ok($st['column_tries'] > 0, 'C10.3 · the ledger now records a real attempt');
    t_eq($st['column_status'], ACT_OPT_FAILED, 'C10.3 B · §7 · NOT_ATTEMPTED → failed attempt → FAILED');
    t_ok(strpos($st['column_error'], 'Failed:') === 0, 'C10.3 · §4 · the message says Failed');
    t_ok(strlen($st['column_error']) > strlen('Failed: '), 'C10.3 · …and carries the recorded reason');
} finally { $back(); }
//  §7 — FAILED → retry → READY
t_eq(act_optional_state()['column_status'], ACT_OPT_FAILED, 'C10.3 · still FAILED until something retries');
t_ok(act_cond_column_ready() === true, 'C10.3 · §7 · the retry succeeds');
t_eq(act_optional_state()['column_status'], ACT_OPT_READY, 'C10.3 · §7 · FAILED → retry → READY');

// ---------------------------------------------------------------------------
//  C10.4 · CASE E — the INDEX fails on its own account, column stays READY
// ---------------------------------------------------------------------------
t_section('C10.4 · CASE E · index FAILED while column stays READY — ' . strtoupper($engine));
$dropIdx();
$c10fills = 0; $blocked = false;
try {
    if ($engine === 'sqlite') { $pdo->exec("CREATE TABLE idx_act_cond (x INT)"); }
    else {
        $have = (int) ops_val("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics
                               WHERE table_schema=DATABASE() AND table_name='activities'");
        $c10fills = 64 - $have;
        $f = []; for ($i = 1; $i <= $c10fills; $i++) $f[] = "ADD INDEX c10fill$i (auto)";
        if ($f) $pdo->exec("ALTER TABLE activities " . implode(', ', $f));
    }
    $bump();
    t_eq(act_optional_state()['index_status'], ACT_OPT_NOT_ATTEMPTED, 'C10.4 · before the attempt: NOT_ATTEMPTED');
    t_ok(act_cond_index_ready() === false, 'C10.4 E · the index attempt genuinely fails');
    $blocked = true;
    $st = act_optional_state();
    t_eq($st['column_status'], ACT_OPT_READY,  'C10.4 E · §5 · the COLUMN is still READY — independent');
    t_eq($st['index_status'],  ACT_OPT_FAILED, 'C10.4 E · the INDEX is FAILED, on its own account');
    t_ok(strpos($st['index_error'], 'Failed:') === 0, 'C10.4 E · with its own recorded reason');
    t_eq($st['column_error'], '', 'C10.4 E · and the column reports no error at all');
    //  …and metadata still stores, which is the whole point of the T2 split
    $idE = act_log('LEAD', 840001, 'SYSTEM', 'C10 case E', ['auto'=>1, 'cond_key'=>'PC|C10|E|1']);
    $mine[] = $idE;
    t_eq((string) ops_val("SELECT cond_key FROM activities WHERE id=?", [$idE]), 'PC|C10|E|1',
         'C10.4 E · §5 · cond_key is still stored — only performance is degraded');
} finally {
    if ($engine === 'sqlite') { try { $pdo->exec("DROP TABLE idx_act_cond"); } catch (Throwable $e) {} }
    else {
        $d = []; for ($i = 1; $i <= $c10fills; $i++) $d[] = "DROP INDEX c10fill$i";
        if ($d) { try { $pdo->exec("ALTER TABLE activities " . implode(', ', $d)); } catch (Throwable $e) {} }
    }
}
t_ok($blocked, 'C10.4 E · the fixture genuinely blocked the index — this case was really exercised');
t_ok(act_cond_index_ready() === true, 'C10.4 E · §7 · and the index retries to READY once the obstacle is gone');

// ---------------------------------------------------------------------------
//  C10.5 · §6 / §8 — OBSERVATION CHANGES NOTHING
// ---------------------------------------------------------------------------
t_section('C10.5 · §6/§8 · state survives observation, and observation spends nothing');
//  READY → observe × 10 → READY
$bump();
for ($i = 0; $i < 10; $i++) $st = act_optional_state();
t_eq($st['column_status'], ACT_OPT_READY, 'C10.5 · READY survives ten observations');
t_eq($st['column_tries'], 0, 'C10.5 · and the attempt count is untouched');

//  NOT_ATTEMPTED → observe × 10 → still NOT_ATTEMPTED, budget intact
$dropIdx();
$pdo->exec("ALTER TABLE activities DROP COLUMN cond_key");
$away();
try {
    $bump();
    $before = act_optional_state();
    t_eq($before['column_status'], ACT_OPT_NOT_ATTEMPTED, 'C10.5 · NOT_ATTEMPTED to begin with');
    for ($i = 0; $i < 10; $i++) $after = act_optional_state();
    t_eq($after['column_status'], ACT_OPT_NOT_ATTEMPTED, 'C10.5 · §6 · TEN observations leave it NOT_ATTEMPTED');
    t_eq($after['column_tries'], 0, 'C10.5 · §8 · and consume no retry budget at all');
    //  now spend the budget for real, and watch it move
    act_cond_column_ready(); t_eq(act_optional_state()['column_tries'], 1, 'C10.5 · §8 · attempt 1 → 1 on the ledger');
    act_cond_column_ready(); t_eq(act_optional_state()['column_tries'], 2, 'C10.5 · §8 · attempt 2 → 2');
    act_cond_column_ready(); t_eq(act_optional_state()['column_tries'], 3, 'C10.5 · §8 · attempt 3 → 3');
    for ($i = 0; $i < 5; $i++) act_optional_state();
    t_eq(act_optional_state()['column_tries'], 3, 'C10.5 · §8 · five more observations do not move it');
    t_eq(act_optional_state()['column_status'], ACT_OPT_FAILED, 'C10.5 · §6 · FAILED survives observation too');
} finally { $back(); }
$bump();
t_ok(act_cond_column_ready() === true, 'C10.5 · and the schema repairs once the obstacle is gone');
t_ok(act_cond_index_ready()  === true, 'C10.5 · index too');

// ---------------------------------------------------------------------------
//  Clean up
// ---------------------------------------------------------------------------
foreach (array_filter($mine) as $a) $pdo->prepare("DELETE FROM activities WHERE id=?")->execute([(int)$a]);
$GLOBALS['__db_epoch'] = $epoch0;
t_eq(db_epoch(), $epoch0, 'C10 · the database epoch is restored');
t_ok(act_has_cond_column() && act_has_cond_index(), 'C10 · and the spine is left whole');
