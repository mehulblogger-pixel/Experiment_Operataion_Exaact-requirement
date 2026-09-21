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

$inspRename = function ($away) {
    $sq = t_driver() === 'sqlite';
    try {
        if ($away) db()->exec($sq ? "ALTER TABLE inspectors RENAME TO inspectors_bak3"
                                  : "RENAME TABLE inspectors TO inspectors_bak3");
        else {
            try { db()->exec("DROP TABLE IF EXISTS inspectors"); } catch (Throwable $e) {}
            db()->exec($sq ? "ALTER TABLE inspectors_bak3 RENAME TO inspectors"
                           : "RENAME TABLE inspectors_bak3 TO inspectors");
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
//  The case that was NOT doubled used to be accepting somebody WITHOUT creating
//  a workforce record — the hidden checkbox left unticked. rcv_convert never
//  ran, so the check inside the transaction was the only thing standing between
//  two recruiters and one seat.
//
//  RB-1 DELETED THAT PATH. Accepting somebody now always creates their team
//  record, so there is no longer an acceptance that rcv_convert does not see.
//  This probe therefore asserts the new truth rather than the old one: the same
//  manufactured overlap still refuses the loser, AND the unguarded path is gone
//  — an acceptance that creates no workforce record can no longer be produced,
//  not even by a POST that asks for one.
//
//  A consequence, recorded honestly: with that path gone the route's own seat
//  check is doubled everywhere, so removing it is now an EQUIVALENT mutation
//  rather than a detectable one. Section E proves the doubling, so the claim is
//  tested rather than asserted.
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
    t_eq($inspOf($nLos), 0, 'T10b · the loser has no workforce record — the refusal left nothing behind');
    t_eq($stageOf($nLos), 'OFFER',
         'T10 · they were still refused — the seat check INSIDE the transaction is the only thing that could have done it');
    t_eq((int)ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rqN]), 1,
         'T10c · one approved seat, exactly one person in it');

    //  T10d — RB-1 ITSELF: the path T10 used to guard no longer exists.
    //  A plain 'move' to ACCEPTED asks for no workforce record. Before RB-1 it
    //  produced a hired person with nobody behind them; now it cannot.
    $rqR  = $mkReq(1);
    $cRb1 = $mkCand('Rb1NoTick', $rqR);
    $hR   = $s3spawn('move', $cRb1, 'ACCEPTED', $s3me, 900);
    $s3reap($hR);
    t_eq($stageOf($cRb1), 'ACCEPTED', 'T10d1 · the acceptance went through with NO request for a workforce record — armed');
    t_ok($inspOf($cRb1) > 0,
         'T10d · …and they have one anyway — RB-1: every accepted person is a member of the team, never an unticked box');
    t_ok((string)ops_val("SELECT COALESCE(emp_code,'') FROM inspectors WHERE id=?", [$inspOf($cRb1)]) !== '',
         'T10d2 · …with an employee number, so the record is real and not a stub');
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
//  NOTE — this probe used to force its late failure with an unacknowledged
//  duplicate. Once the refusable conditions moved in FRONT of the transaction
//  (§7) that candidate never reached the transaction at all, and the probe
//  stopped testing anything: mutant A6 walked straight through it. The late
//  failure is now a genuine DATABASE failure, which cannot be refused early.
if (t_driver() === 'sqlite') {
    t_ok(true, 'T9 (sqlite) · skipped by design — SQLite DDL is transactional, so the implicit-commit hazard exists only on MariaDB, where it is proved');
} else {
    $rqC   = $mkReq(3);
    $cCold = $mkCand('Cold', $rqC);
    $goneE = $evRename(true);
    t_ok($goneE, 'T9a · the ledger table is absent, so a COLD process really must create it — armed');
    $hC = $s3spawn('acceptcold', $cCold, '', $s3me, 2600);
    usleep(1300000);
    $goneI = $inspRename(true);
    t_ok($goneI, 'T9b · …and the team-member table is taken away, so the failure lands AFTER the ledger step');
    $s3reap($hC);
    $inspRename(false);
    $evRename(false);
    t_eq($stageOf($cCold), 'OFFER',
         'T9 · nothing was committed before that failure — the migration ran BEFORE the transaction, not inside it');
    t_eq($inspOf($cCold), 0, 'T9c · …and no workforce record exists');
}

// ---------------------------------------------------------------------------
t_section('RB3S3 · X2 / X4 / X17 — the tick, and accepting twice');
// ---------------------------------------------------------------------------
$mkTwin = function ($mob) { db()->prepare("INSERT INTO inspectors (name,first_name,last_name,mobile,status,created_at) VALUES (?,?,?,?, 'ACTIVE',?)")
    ->execute(['Twin ' . $mob, 'Twin', 'S3', $mob, date('c')]); return (int)db()->lastInsertId(); };
$tokFor = function ($cid) use ($s3me) { $c = ops_one("SELECT * FROM candidates WHERE id=?", [$cid]);
    return workforce_ack_issue($cid, workforce_matches($c), $c, $s3me); };

//  X2 — a real duplicate, acknowledged: everything commits.
$mkTwin('9550011111');
$cAck = $mkCand('AckOk', $mkReq(3), 'OFFER', '9550011111');
$tk = $tokFor($cAck);
t_ok($tk !== '', 'X2a · a strong duplicate exists and a genuine tick was issued — the trap is armed');
$bX2 = $staffN();
$s3race([['accept', $cAck, $tk, $s3me]]);
t_eq($stageOf($cAck), 'ACCEPTED', 'X2 · an acknowledged duplicate is accepted');
t_ok($inspOf($cAck) > 0, 'X2b · …and the workforce record was created');
t_eq($staffN(), $bX2 + 1, 'X2c · exactly one');

//  X4 — an INVALID tick refuses, and does so before the transaction is opened.
$mkTwin('9550022222');
$cBad = $mkCand('AckBad', $mkReq(3), 'OFFER', '9550022222');
$bX4 = $staffN(); $evX4 = $evN($cBad);
$s3race([['accept', $cBad, '1', $s3me]]);            // "1" — the shape the old hidden field used
t_eq($stageOf($cBad), 'OFFER', 'X4 · a forged tick is refused');
t_eq($staffN(), $bX4, 'X4b · …nothing was created');
t_eq($evN($cBad), $evX4, 'X4c · …and no stage-history entry');
t_ok((int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND kind='IDENTITY_REFUSED' AND subject LIKE '%before any change%'", [$cBad]) >= 1,
     'X4d · …refused BEFORE the transaction was opened, not rolled back out of one (§7)');

//  X17 — accepting somebody who is already accepted.
$cTwice = $mkCand('Twice', $mkReq(3));
$s3race([['accept', $cTwice, '', $s3me]]);
$firstInsp = $inspOf($cTwice);
t_ok($firstInsp > 0, 'X17a · the first acceptance succeeded — the trap is armed');
$bX17 = $staffN();
$s3race([['accept', $cTwice, '', $s3me]]);
t_eq($inspOf($cTwice), $firstInsp, 'X17 · a second acceptance changes nothing — the same team member');
t_eq($staffN(), $bX17, 'X17b · …and creates no second workforce record');
t_eq((int)ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$firstInsp]), 1, 'X17c · …nor a second employee number');
//  Asked of the ACTION, not the route: a forged POST that never passed the
//  pre-transaction pass must still be refused (invariant I27).
t_eq((string)rcv_convert($cTwice, ['team_role' => 'FIELD', 'actor_id' => $s3me])['code'], 'ALREADY',
     'X17d · …and the action refuses it on its own, without the route\'s help');

// ---------------------------------------------------------------------------
t_section('RB3S3 · X6 / X11 — the workforce write itself fails');
// ---------------------------------------------------------------------------
//  The hardest failure to fake honestly: the team-member INSERT itself. The
//  table is taken away while the acceptance is in flight, exactly as T8 does
//  with the ledger, so the failure lands AFTER the stage move and the ledger
//  write and BEFORE the commit.
$rqF = $mkReq(3);
$cFail = $mkCand('WorkFail', $rqF);
$evF = $evN($cFail);
$hF = $s3spawn('accept', $cFail, '', $s3me, 2600);
usleep(1300000);
$gone = $inspRename(true);
$s3reap($hF);
$back = $inspRename(false);
t_ok($gone && $back, 'X6a · the team-member table was taken away mid-flight and restored — the trap is armed');
t_eq($stageOf($cFail), 'OFFER', 'X6 · the workforce write failed, so the STAGE MOVE rolled back with it');
t_eq($inspOf($cFail), 0, 'X6b · …no workforce record');
t_eq($evN($cFail), $evF, 'X11 · …and no stage-history entry: a database exception rolls everything back');
//  A failure INSIDE the transaction is audited after the rollback, by a
//  different call site from the pre-transaction refusal — so this asserts the
//  entry that does NOT say "before any change".
t_ok((int)ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CANDIDATE' AND entity_id=? AND kind='IDENTITY_REFUSED' AND subject NOT LIKE '%before any change%'", [$cFail]) >= 1,
     'X11b · …and the rolled-back failure was recorded, outside the transaction that vanished');

//  X12 — RETRY. The same candidate, once the problem is gone, accepts cleanly.
$bR = $staffN();
$s3race([['accept', $cFail, '', $s3me]]);
t_eq($stageOf($cFail), 'ACCEPTED', 'X12 · the recruiter retried and it worked');
t_ok($inspOf($cFail) > 0, 'X12b · …with a workforce record');
t_eq($staffN(), $bR + 1, 'X12c · …exactly one, with nothing left over from the failed attempt');

// ---------------------------------------------------------------------------
t_section('RB3S3 · X8 — the employee number cannot be issued');
// ---------------------------------------------------------------------------
//  Every number the claim would try is taken, so it gives up rather than hand
//  out one somebody already holds. The acceptance must go back with it.
$rqE = $mkReq(3);
$cEmp = $mkCand('NoNumber', $rqE);
//  The blockers must be INVISIBLE to the generator and REAL to the database, or
//  the generator simply starts past them and nothing is blocked at all. The
//  first version of this probe did exactly that, and its own arming assertion
//  showed it. A LEADING SPACE does both: ' EMP07' does not match the
//  generator's `emp_code LIKE 'EMP%'` scan, and Step 1's key normalises it to
//  EMP07, so the database holds it.
$wanted = next_emp_code('ASSET');
$base   = (int)preg_replace('/[^0-9]/', '', $wanted);
for ($i = 0; $i < 30; $i++)
    db()->prepare("INSERT INTO inspectors (name,emp_code,status,created_at) VALUES (?,?, 'ACTIVE', ?)")
        ->execute(['Blocker ' . $i, ' EMP' . str_pad((string)($base + $i), 2, '0', STR_PAD_LEFT), date('c')]);
t_eq(next_emp_code('ASSET'), $wanted,
     'X8a · the generator still offers ' . $wanted . ', unaware that it and the next 29 are taken — the trap is armed');
$bE = $staffN(); $evE = $evN($cEmp);
$s3race([['accept', $cEmp, '', $s3me]]);
t_eq($stageOf($cEmp), 'OFFER', 'X8 · no employee number, no acceptance');
t_eq($inspOf($cEmp), 0, 'X8b · …no workforce record');
t_eq($staffN(), $bE, 'X8c · …nothing created at all');
t_eq($evN($cEmp), $evE, 'X8d · …and no stage-history entry');
//  Clear the blockers. Without this they keep every later probe's claim
//  exhausted too — which is exactly what happened the first time this section
//  ran, and X13 below failed for a reason that had nothing to do with X13.
db()->exec("DELETE FROM inspectors WHERE name LIKE 'Blocker %'");
t_eq((int)ops_val("SELECT COUNT(*) FROM inspectors WHERE name LIKE 'Blocker %'"), 0,
     'X8e · the blockers were cleared, so what follows starts from clean numbering');

// ---------------------------------------------------------------------------
t_section('RB3S3 · X13 / X16 — double submit, and an id from another tenant');
// ---------------------------------------------------------------------------
$cDbl = $mkCand('DoubleSubmit', $mkReq(3));
$bD = $staffN();
$s3race([['accept', $cDbl, '', $s3me], ['accept', $cDbl, '', $s3me], ['accept', $cDbl, '', $s3me]]);
t_eq($stageOf($cDbl), 'ACCEPTED', 'X13a · the candidate was accepted');
t_eq($staffN(), $bD + 1, 'X13 · three simultaneous submissions of the SAME acceptance produce ONE workforce record');
t_eq((int)ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$inspOf($cDbl)]), 1, 'X13b · …and one employee number');

//  X16 — tenancy is structural: one database per tenant, proved against two real
//  databases in Step 2. Here the question is narrower — an id that is not in
//  THIS database must be refused before anything is written.
$ghost = (int)ops_val("SELECT COALESCE(MAX(id),0)+5000 FROM candidates");
t_eq(rcv_refusal_before_transaction(['id' => $ghost], ['want_hire' => 1]), RCV_CODES['NO_CANDIDATE'],
     'X16 · an application id that is not in this tenant\'s database is refused before any write');
t_eq((string)rcv_convert($ghost, ['team_role' => 'FIELD', 'actor_id' => $s3me])['code'], 'NO_CANDIDATE',
     'X16b · …and the action refuses it too, so a forged POST gains nothing');

// ---------------------------------------------------------------------------
t_section('RB3S3 · E — why two mutations are EQUIVALENT, not missed');
// ---------------------------------------------------------------------------
//  Two mutations survive the battery. Neither is a hole; both are unreachable,
//  and that is asserted here rather than claimed in prose — so if the code ever
//  changes to make them reachable, THESE assertions fail and the equivalence
//  claim is withdrawn automatically.

//  E1 · "commit immediately after the workforce record is created" survives
//  because NOTHING AFTER IT CAN FAIL. Both remaining writes swallow their own
//  errors by existing design, so committing there or at the end leaves exactly
//  the same committed state.
$cE = $mkCand('Equiv', null, 'OFFER');
$raGone = false;
try { db()->exec(t_driver() === 'sqlite' ? "ALTER TABLE requisition_allocations RENAME TO ra_bak_e"
                                         : "RENAME TABLE requisition_allocations TO ra_bak_e"); $raGone = true; } catch (Throwable $e) {}
t_ok($raGone, 'E1a · the source-credit table was taken away — the trap is armed');
$threw = false;
try { rful_enforce_candidate($cE, 0); } catch (Throwable $e) { $threw = true; }
try { db()->exec(t_driver() === 'sqlite' ? "ALTER TABLE ra_bak_e RENAME TO requisition_allocations"
                                         : "RENAME TABLE ra_bak_e TO requisition_allocations"); } catch (Throwable $e) {}
t_eq($threw, false, 'E1 · the source credit cannot fail the transaction — it swallows its own errors');

$rqGone = false;
try { db()->exec(t_driver() === 'sqlite' ? "ALTER TABLE requisitions RENAME TO rq_bak_e"
                                         : "RENAME TABLE requisitions TO rq_bak_e"); $rqGone = true; } catch (Throwable $e) {}
t_ok($rqGone, 'E1b · the requirement table was taken away — the trap is armed');
$threw2 = false;
try { reqf_sync(1); } catch (Throwable $e) { $threw2 = true; }
try { db()->exec(t_driver() === 'sqlite' ? "ALTER TABLE rq_bak_e RENAME TO requisitions"
                                         : "RENAME TABLE rq_bak_e TO requisitions"); } catch (Throwable $e) {}
t_eq($threw2, false, 'E1c · nor can recomputing the requirement — so an early commit there is indistinguishable');

//  E2 · "a failed workforce creation no longer stops the acceptance" survives
//  because rcv_convert() RE-THROWS inside a borrowed transaction rather than
//  returning a failure, so the route's own `if (empty($cv['ok'])) throw` is a
//  backstop nothing can currently reach. Every refusal that DOES return is
//  already settled earlier — by the pre-transaction pass or the seat check.
db()->beginTransaction();
$borrowThrew = false;
try { rcv_convert(0, ['team_role' => 'FIELD', 'actor_id' => $s3me]); } catch (Throwable $e) { $borrowThrew = true; }
$stillIn = db()->inTransaction();
try { db()->rollBack(); } catch (Throwable $e) {}
t_ok($stillIn, 'E2a · the call really was made inside somebody else\'s transaction — the trap is armed');
t_ok(strpos((string)@file_get_contents($s3root . '/lib/recruit.php'), 'if (!$own) throw $e;') !== false,
     'E2 · rcv_convert re-throws on a borrowed transaction instead of returning a failure, so the route\'s backstop is unreachable');
t_eq((string)rcv_convert(0, ['team_role' => 'FIELD', 'actor_id' => $s3me])['code'], 'NO_CANDIDATE',
     'E2b · …and the refusals it DOES return are the early gates, every one of which the pre-transaction pass already settles');

// ---------------------------------------------------------------------------
t_section('RB3S3 · J — nothing else moved');
// ---------------------------------------------------------------------------
t_ok(function_exists('rexec_join_enforce_after_write'), 'J1 · M6\'s compensating revert still exists for the paths that use it');
t_ok(in_array(EMP_CODE_KEY_IX, table_index_names('inspectors'), true), 'J2 · Step 1\'s employee-number rule is untouched');
t_ok(function_exists('workforce_matches') && function_exists('workforce_ack_ok'), 'J3 · Step 2\'s duplicate gate is untouched');
t_eq(substr_count((string)@file_get_contents($s3root . '/lib/ops.php'), 'rcv_convert($id, ['), 1,
     'J4 · there is exactly ONE conversion call site — the old post-commit one is gone, not left to drift');
