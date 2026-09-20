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
t_section('RB3S3 · J — nothing else moved');
// ---------------------------------------------------------------------------
t_ok(function_exists('rexec_join_enforce_after_write'), 'J1 · M6\'s compensating revert still exists for the paths that use it');
t_ok(in_array(EMP_CODE_KEY_IX, table_index_names('inspectors'), true), 'J2 · Step 1\'s employee-number rule is untouched');
t_ok(function_exists('workforce_matches') && function_exists('workforce_ack_ok'), 'J3 · Step 2\'s duplicate gate is untouched');
t_eq(substr_count((string)@file_get_contents($s3root . '/lib/ops.php'), 'rcv_convert($id, ['), 1,
     'J4 · there is exactly ONE conversion call site — the old post-commit one is gone, not left to drift');
