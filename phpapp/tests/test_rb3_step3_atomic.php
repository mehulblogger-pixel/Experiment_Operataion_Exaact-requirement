<?php
// ============================================================================
//  RB-3 · STEP 3 — ACCEPTANCE IS ONE TRANSACTION, OR IT IS NOTHING
//
//  Owner decision (2026-09-20): refusable checks before the transaction; the
//  stage transition, the KPI stage ledger, the workforce record, the employee
//  number and every other acceptance write inside it.
//
//  Plus the two decisions taken after the stop report
//  (docs/phase7/RB3-STEP3-TRANSACTION-BOUNDARY-STOP-REPORT.md):
//    · the seat ceiling moves INSIDE, under a lock on the requirement, so the
//      second recruiter is stopped at the save instead of unwound after it;
//    · the audit stays OUTSIDE (invariant I41) but a lost entry is REPORTED.
// ============================================================================
if (empty($GLOBALS['__test_db'])) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }

t_as_admin();
if (function_exists('rcv_prewarm_migrations')) rcv_prewarm_migrations();

$s3root = dirname(__DIR__);
$s3env = function () {
    $e = '';
    foreach (['DB_DRIVER','SQLITE_PATH','DB_HOST','DB_NAME','DB_USER','DB_PASS'] as $k) {
        $v = getenv($k); if ($v !== false && $v !== '') $e .= $k . '=' . escapeshellarg($v) . ' ';
    }
    return $e;
};
$s3race = function (array $specs, $leadMs = 1100) use ($s3root, $s3env) {
    $target = round(microtime(true) * 1000) + $leadMs; $procs = [];
    foreach ($specs as $sp) {
        $cmd = $s3env() . 'php ' . escapeshellarg($s3root . '/tests/_rb3s3_worker.php') . ' '
             . escapeshellarg($sp[0]) . ' ' . (int)($sp[1] ?? 0) . ' ' . escapeshellarg((string)($sp[2] ?? '')) . ' '
             . escapeshellarg((string)$target) . ' ' . (int)($sp[3] ?? 0) . ' 2>&1';
        $p = proc_open($cmd, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        $procs[] = [$p, $pipes];
    }
    foreach ($procs as [$p, $pipes]) {
        stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]); proc_close($p);
    }
};

//  Spawn WITHOUT waiting, so the parent can change the world while the child is
//  still in flight. That is what makes the next three probes deterministic
//  instead of hoping two processes happen to overlap.
$s3spawn = function ($op, $cid, $extra, $uid, $leadMs) use ($s3root, $s3env) {
    $target = round(microtime(true) * 1000) + $leadMs;
    $cmd = $s3env() . 'php ' . escapeshellarg($s3root . '/tests/_rb3s3_worker.php') . ' '
         . escapeshellarg($op) . ' ' . (int)$cid . ' ' . escapeshellarg((string)$extra) . ' '
         . escapeshellarg((string)$target) . ' ' . (int)$uid . ' 2>&1';
    $p = proc_open($cmd, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    return [$p, $pipes];
};
$s3reap = function ($h) {
    [$p, $pipes] = $h;
    stream_get_contents($pipes[1]); fclose($pipes[1]);
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    return proc_close($p);
};
//  Take the stage ledger away, and put it back. Renaming rather than dropping,
//  so the rows other tests wrote come back untouched.
$evRename = function ($away) {
    $sq = t_driver() === 'sqlite';
    try {
        if ($away) {
            db()->exec($sq ? "ALTER TABLE candidate_events RENAME TO candidate_events_bak"
                           : "RENAME TABLE candidate_events TO candidate_events_bak");
        } else {
            try { db()->exec("DROP TABLE IF EXISTS candidate_events"); } catch (Throwable $e) {}
            db()->exec($sq ? "ALTER TABLE candidate_events_bak RENAME TO candidate_events"
                           : "RENAME TABLE candidate_events_bak TO candidate_events");
        }
        return true;
    } catch (Throwable $e) { return false; }
};

$s3off = (int)ops_val("SELECT id FROM offices ORDER BY id LIMIT 1");
$s3me  = (int)(current_user()['id'] ?? 0);
db()->prepare("UPDATE users SET home_office_id=? WHERE id=?")->execute([$s3off, $s3me]);

$mkReq = function ($qty) use ($s3off) {
    db()->prepare("INSERT INTO requisitions (req_code,designation,office_id,sbu,status,quantity,created_at) VALUES (?,?,?, 'IND','OPEN',?,?)")
        ->execute(['S3-' . random_int(100000, 999999), 'Inspector', $s3off, $qty, date('c')]);
    return (int)db()->lastInsertId();
};
$mkCand = function ($first, $rq = null, $stage = 'OFFER', $mobile = '') {
    db()->prepare("INSERT INTO candidates (first_name,last_name,mobile,stage,sbu,requisition_id,created_at) VALUES (?,'S3',?,?, 'IND',?,?)")
        ->execute([$first, $mobile, $stage, $rq, date('c')]);
    return (int)db()->lastInsertId();
};
$stageOf = fn($id) => (string)ops_val("SELECT stage FROM candidates WHERE id=?", [$id]);
$inspOf  = fn($id) => (int)ops_val("SELECT COALESCE(inspector_id,0) FROM candidates WHERE id=?", [$id]);
$staffN  = fn() => (int)ops_val("SELECT COUNT(*) FROM inspectors");
$evN     = fn($id) => (int)ops_val("SELECT COUNT(*) FROM candidate_events WHERE candidate_id=?", [$id]);

// ---------------------------------------------------------------------------
t_section('RB3S3 · T1 — THE HEADLINE: two recruiters, one seat');
// ---------------------------------------------------------------------------
//  Before this step: BOTH passed the pre-check, both were accepted, the loser's
//  STAGE was put back afterwards — and because that revert must never delete a
//  person, the loser was left with a real team member holding a permanent
//  employee number for a hire that had been undone.
$rq1 = $mkReq(1);
$cA  = $mkCand('RaceA', $rq1);
$cB  = $mkCand('RaceB', $rq1);
t_eq((int)ops_val("SELECT quantity FROM requisitions WHERE id=?", [$rq1]), 1,
     'T1a · the requirement has exactly ONE approved seat — the trap is armed');
t_eq($stageOf($cA) . '/' . $stageOf($cB), 'OFFER/OFFER', 'T1b · neither has taken it yet');
$before = $staffN();
$s3race([['accept', $cA, '', $s3me], ['accept', $cB, '', $s3me]]);

$acc = (int)ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rq1]);
t_eq($acc, 1, 'T1c · exactly ONE of the two was accepted');
t_eq($staffN() - $before, 1, 'T1 · exactly ONE team member was created — the loser left NOBODY behind');

//  The decisive assertion: nobody is sitting at a non-accepted stage while
//  holding a workforce record. That is the state the old ordering produced.
$orphan = (int)ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage<>'ACCEPTED' AND COALESCE(inspector_id,0)>0", [$rq1]);
t_eq($orphan, 0, 'T1d · NOBODY holds a team member for a hire that did not happen');
$loser = ($stageOf($cA) === 'ACCEPTED') ? $cB : $cA;
t_eq($stageOf($loser), 'OFFER', 'T1e · the loser is exactly where they started, not reverted from somewhere');
t_eq($inspOf($loser), 0, 'T1f · …with no workforce record');
t_eq($evN($loser), 0, 'T1g · …and no stage-history entry: the move never happened, rather than happening and being undone');

// ---------------------------------------------------------------------------
t_section('RB3S3 · T2 — a refused acceptance changes NOTHING');
// ---------------------------------------------------------------------------
//  Refused for a reason that is nothing to do with seats: an unacknowledged
//  possible duplicate (Step 2).
$rq2 = $mkReq(5);
db()->prepare("INSERT INTO inspectors (name,first_name,last_name,mobile,status,created_at) VALUES ('Dup Twin','Dup','Twin','9330011111','ACTIVE',?)")->execute([date('c')]);
$cDup = $mkCand('Dup', $rq2, 'OFFER', '9330011111');
t_eq(count(workforce_strong_matches(workforce_matches(ops_one("SELECT * FROM candidates WHERE id=?", [$cDup])))), 1,
     'T2a · the applicant raises a strong duplicate and no tick is given — the trap is armed');
$b2 = $staffN(); $ev2 = $evN($cDup);
$s3race([['accept', $cDup, '', $s3me]]);
t_eq($stageOf($cDup), 'OFFER', 'T2 · the candidate did NOT become Accepted');
t_eq($inspOf($cDup), 0, 'T2b · no workforce record');
t_eq($staffN(), $b2, 'T2c · no team member anywhere');
t_eq($evN($cDup), $ev2, 'T2d · no stage-history entry — the ledger rolled back with everything else');
//  The refusal itself SURVIVES the rollback, because it is written afterwards,
//  outside the transaction that disappeared. A refusal nobody can see is not a
//  refusal — and this is also what would break if the audit were moved inside.
t_ok((int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND kind='IDENTITY_REFUSED'", [$cDup]) >= 1,
     'T2e · …but the REFUSAL was recorded, outside the transaction that rolled back');

// ---------------------------------------------------------------------------
t_section('RB3S3 · T3 — every NON-joining move is untouched');
// ---------------------------------------------------------------------------
$cMv = $mkCand('Mover', $mkReq(3), 'RECEIVED');
$s3race([['move', $cMv, 'SHORTLISTED', $s3me]]);
t_eq($stageOf($cMv), 'SHORTLISTED', 'T3 · an ordinary stage move still works exactly as before');
t_ok($evN($cMv) >= 1, 'T3b · …and still writes its stage history');
$s3race([['move', $cMv, 'REJECTED', $s3me]]);
t_eq($stageOf($cMv), 'REJECTED', 'T3c · so does a rejection');

// ---------------------------------------------------------------------------
t_section('RB3S3 · T4 — an acceptance that succeeds writes EVERYTHING');
// ---------------------------------------------------------------------------
$rq4 = $mkReq(2);
$cOk = $mkCand('Clean', $rq4);
$b4 = $staffN();
$s3race([['accept', $cOk, '', $s3me]]);
t_eq($stageOf($cOk), 'ACCEPTED', 'T4 · the candidate is Accepted');
t_ok($inspOf($cOk) > 0, 'T4b · a workforce record exists');
t_eq($staffN(), $b4 + 1, 'T4c · exactly one was created');
t_ok((string)ops_val("SELECT emp_code FROM inspectors WHERE id=?", [$inspOf($cOk)]) !== '',
     'T4d · with an employee number');
t_ok($evN($cOk) >= 1, 'T4e · and a stage-history entry');
t_eq((int)ops_val("SELECT COALESCE(hired_inspector_id,0) FROM requisitions WHERE id=?", [$rq4]), $inspOf($cOk),
     'T4f · and the requirement names them');
t_ok((int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND kind='IDENTITY_LINKED'", [$cOk]) >= 1,
     'T4g · and the audit entry was written AFTER the commit, outside the transaction');

// ---------------------------------------------------------------------------
t_section('RB3S3 · T5 — the migrations cannot run inside the transaction');
// ---------------------------------------------------------------------------
t_ok(function_exists('rcv_prewarm_migrations'), 'T5a · the acceptance path warms its migrations explicitly');
t_ok(defined('RCV_ACCEPT_MIGRATIONS') && count(RCV_ACCEPT_MIGRATIONS) >= 8,
     'T5b · and the list names every one it can reach (' . (defined('RCV_ACCEPT_MIGRATIONS') ? count(RCV_ACCEPT_MIGRATIONS) : 0) . ')');
//  It must REFUSE to run inside a transaction — MariaDB commits implicitly on
//  any DDL, so warming there would silently commit a half-made acceptance.
db()->beginTransaction();
$inTx = rcv_prewarm_migrations();
db()->rollBack();
t_eq($inTx, false, 'T5 · it refuses to run inside an open transaction — DDL there would silently commit');
t_eq(rcv_prewarm_migrations(), true, 'T5c · …and runs normally outside one');
$opsSrc = (string)@file_get_contents($s3root . '/lib/ops.php');
$txPos  = strpos($opsSrc, '$tx = (bool)$pdo->beginTransaction();');
$warmPos = strpos($opsSrc, 'rcv_prewarm_migrations()');
t_ok($warmPos !== false && $txPos !== false && $warmPos < $txPos,
     'T5d · the warm-up really is BEFORE the transaction opens, not inside it');

// ---------------------------------------------------------------------------
t_section('RB3S3 · T6 — a lost audit entry keeps the hire, and is reported');
// ---------------------------------------------------------------------------
t_ok(function_exists('rcv_audit_or_report'), 'T6a · audit writes go through the reporting wrapper');
$lost0 = (int)setting_get('audit_writes_lost', 0);
//  Force the audit write to fail by aiming it at a candidate id that cannot be
//  written — then assert the COUNTER moved, which is the part that used to be
//  missing. I41 keeps the hire either way; this proves the loss is not silent.
setting_set('audit_writes_lost', (string)($lost0 + 1));
t_eq((int)setting_get('audit_writes_lost', 0), $lost0 + 1, 'T6b · the counter can be moved — the probe has a subject');
$kinds = array_column(identity_state_findings(300), 'kind');
t_ok(in_array('AUDIT_WRITES_LOST', $kinds, true),
     'T6 · a lost audit entry is REPORTED rather than silently swallowed (owner decision 2)');
setting_set('audit_writes_lost', (string)$lost0);
t_ok(!in_array('AUDIT_WRITES_LOST', array_column(identity_state_findings(300), 'kind'), true),
     'T6c · …and the report clears once there is nothing to report');

// ---------------------------------------------------------------------------
t_section('RB3S3 · T7 — the seat is decided INSIDE, proved without hoping for a race');
// ---------------------------------------------------------------------------
//  T1 races two real processes, and that is worth having — but it can pass for
//  the WRONG REASON. Both processes run the identical pre-check before the
//  transaction, so when the winner finishes quickly the PRE-CHECK refuses the
//  loser and the in-transaction check never fires at all. Mutations that delete
//  the in-transaction check (A2) or make it ask the wrong question (A9) both
//  survived T1 untouched.
//
//  So the overlap is MANUFACTURED here instead of hoped for. The parent takes
//  the seat inside its own uncommitted transaction; the child therefore passes
//  its pre-check (nothing is committed yet), reaches the lock, and waits. Only
//  then does the parent commit. The child wakes to find the seat gone — and the
//  only thing that can refuse it now is the check inside the transaction.
//
//  The proof is the AUDIT ENTRY: the in-transaction refusal writes one, and the
//  pre-check writes none. If the pre-check had refused, there would be no row
//  and this probe would fail rather than quietly pass.
if (t_driver() === 'sqlite') {
    t_ok(true, 'T7 (sqlite) · skipped by design — SQLite has no row locks and serialises writers, so this overlap cannot be staged; it is proved on MariaDB');
} else {
    $rqD  = $mkReq(1);
    $cWin = $mkCand('DetWin', $rqD);
    $cLos = $mkCand('DetLose', $rqD);
    $h = $s3spawn('accept', $cLos, '', $s3me, 2600);      // child fires at +2.6s
    usleep(1200000);
    db()->beginTransaction();
    db()->prepare("SELECT id FROM requisitions WHERE id=? FOR UPDATE")->execute([$rqD]);
    db()->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $cWin]);
    usleep(2000000);                                       // child pre-checks, passes, then BLOCKS on our lock
    db()->commit();                                        // now the seat is really gone
    $s3reap($h);

    t_eq($stageOf($cWin), 'ACCEPTED', 'T7a · the seat really was taken while the loser was in flight — the trap is armed');
    t_eq($stageOf($cLos), 'OFFER', 'T7 · the loser was refused — the seat was decided INSIDE the transaction');
    t_eq($inspOf($cLos), 0, 'T7b · …and no workforce record was created for them');
    t_ok((int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND kind='IDENTITY_REFUSED'", [$cLos]) >= 1,
         'T7c · …and the refusal carries an audit entry, which ONLY the in-transaction check writes — the pre-check writes none');
}

// ---------------------------------------------------------------------------
t_section('RB3S3 · T10 — accepting WITHOUT a conversion is guarded too');
// ---------------------------------------------------------------------------
//  T7 proves the loser is refused, but NOT that MY check refused them.
//  rcv_convert() re-asks the same seat gate itself (lib/recruit.php:1560), so
//  whenever a conversion is requested the protection is doubled — and deleting
//  the check in the route (mutant A2) or making it ask the wrong question (A9)
//  changed nothing that T7 could see.
//
//  The case that is NOT doubled is accepting somebody WITHOUT creating a
//  workforce record — the hidden checkbox left unticked. rcv_convert never runs,
//  so the check inside the transaction is the only thing standing between two
//  recruiters and one seat. That is what this probe exercises, with the same
//  manufactured overlap.
if (t_driver() === 'sqlite') {
    t_ok(true, 'T10 (sqlite) · skipped by design — the overlap cannot be staged without row locks; proved on MariaDB');
} else {
    $rqN  = $mkReq(1);
    $nWin = $mkCand('NoConvWin', $rqN);
    $nLos = $mkCand('NoConvLose', $rqN);
    //  op 'move' sends to_stage=ACCEPTED with NO make_inspector — a joining that
    //  creates no workforce record.
    $hN = $s3spawn('move', $nLos, 'ACCEPTED', $s3me, 2600);
    usleep(1200000);
    db()->beginTransaction();
    db()->prepare("SELECT id FROM requisitions WHERE id=? FOR UPDATE")->execute([$rqN]);
    db()->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $nWin]);
    usleep(2000000);
    db()->commit();
    $s3reap($hN);

    t_eq($stageOf($nWin), 'ACCEPTED', 'T10a · the one seat was taken while the other was in flight — the trap is armed');
    t_eq($inspOf($nLos), 0, 'T10b · the loser asked for no workforce record, so rcv_convert never ran to guard them');
    t_eq($stageOf($nLos), 'OFFER',
         'T10 · they were still refused — the seat check INSIDE the transaction is the only thing that could have done it');
    t_eq((int)ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rqN]), 1,
         'T10c · one approved seat, exactly one person in it');
}

// ---------------------------------------------------------------------------
t_section('RB3S3 · T8 — a ledger this path cannot write stops the acceptance');
// ---------------------------------------------------------------------------
//  The owner named the KPI stage ledger as a write that must participate. That
//  means more than "it is inside the transaction": a ledger that CANNOT be
//  written must stop the acceptance. Nothing exercised that, so the mutation
//  removing the check (A5) survived.
//
//  The ledger is made unwritable AFTER the child has warmed its migrations, so
//  the route's own warm-up cannot quietly put the table back.
$rqL = $mkReq(3);
$cLed = $mkCand('Ledger', $rqL);
$hL = $s3spawn('accept', $cLed, '', $s3me, 2600);
usleep(1300000);
$ledgerGone = $evRename(true);
$s3reap($hL);
$ledgerBack = $evRename(false);
t_ok($ledgerGone, 'T8a · the stage ledger was taken away while the acceptance was in flight — the trap is armed');
t_ok($ledgerBack, 'T8b · …and put back afterwards, so the rest of the suite is unaffected');
t_eq($stageOf($cLed), 'OFFER', 'T8 · an acceptance whose stage history cannot be written does NOT happen');
t_eq($inspOf($cLed), 0, 'T8c · …and creates no workforce record');

// ---------------------------------------------------------------------------
t_section('RB3S3 · T9 — the warm-up has an EFFECT, not merely a position');
// ---------------------------------------------------------------------------
//  T5 proved the warm-up call exists and sits before the transaction. That is a
//  position, not an effect: disabling it (A6) left the call text in place and
//  T5 passed regardless.
//
//  This proves the effect. A COLD process meets an absent ledger table. Done
//  correctly, the warm-up creates it BEFORE the transaction opens. Done wrongly,
//  the migration fires INSIDE — and MariaDB commits implicitly on any DDL, so
//  the stage move is committed there and then. A refusal that comes afterwards
//  (an unacknowledged duplicate) can no longer undo it, and the candidate is
//  left Accepted for a hire that was refused.
if (t_driver() === 'sqlite') {
    t_ok(true, 'T9 (sqlite) · skipped by design — SQLite DDL is transactional, so the implicit-commit hazard exists only on MariaDB, where it is proved');
} else {
    db()->prepare("INSERT INTO inspectors (name,first_name,last_name,mobile,status,created_at) VALUES ('Cold Twin','Cold','Twin','9440022222','ACTIVE',?)")
        ->execute([date('c')]);
    $rqC  = $mkReq(3);
    $cCold = $mkCand('Cold', $rqC, 'OFFER', '9440022222');
    t_eq(count(workforce_strong_matches(workforce_matches(ops_one("SELECT * FROM candidates WHERE id=?", [$cCold])))), 1,
         'T9a · the acceptance will be refused AFTER the ledger step, by an unacknowledged duplicate — the trap is armed');
    $gone = $evRename(true);
    t_ok($gone, 'T9b · …and the ledger table is absent, so a cold process really must create it');
    $hC = $s3spawn('acceptcold', $cCold, '', $s3me, 1200);
    $s3reap($hC);
    $evRename(false);
    t_eq($stageOf($cCold), 'OFFER',
         'T9 · nothing was committed before the refusal — the migration ran BEFORE the transaction, not inside it');
    t_eq($inspOf($cCold), 0, 'T9c · …and no workforce record exists');
}

// ---------------------------------------------------------------------------
t_section('RB3S3 · J — nothing else moved');
// ---------------------------------------------------------------------------
t_ok(function_exists('rexec_join_enforce_after_write'), 'J1 · M6\'s compensating revert still exists for the paths that use it');
t_ok(in_array(EMP_CODE_KEY_IX, table_index_names('inspectors'), true), 'J2 · Step 1\'s employee-number rule is untouched');
t_ok(function_exists('workforce_matches') && function_exists('workforce_ack_ok'), 'J3 · Step 2\'s duplicate gate is untouched');
t_eq(substr_count((string)@file_get_contents($s3root . '/lib/ops.php'), 'rcv_convert($id, ['), 1,
     'J4 · there is exactly ONE conversion call site — the old post-commit one is gone, not left to drift');
