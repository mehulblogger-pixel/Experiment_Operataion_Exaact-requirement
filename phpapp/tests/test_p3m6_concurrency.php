<?php
// ============================================================================
//  PHASE 3 · M6 — REAL-PROCESS CONCURRENCY ACROSS THE MILESTONE SEAMS
//
//  Genuinely separate php processes on independent connections. Nothing here is
//  a sequential simulation, and nothing is defended in the browser.
// ============================================================================

t_section('Phase 3 · M6 — concurrency across the lifecycle');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate(); rasg_migrate(); reqf_migrate();
recruit_offer_migrate(); recruit_iv_migrate();
$engine = db_driver(); $root = dirname(__DIR__); $m6c = $_SESSION;
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (9641,'M6C Branch',1)")->execute(); } catch (Throwable $e) {}
$mkU = function ($un) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
                   VALUES (?,'M6C','MANAGER',1,1,9641,'')")->execute([$un]);
    return (int) $pdo->lastInsertId(); };
$uBoss = $mkU('m6c_boss'); $uX = $mkU('m6c_x'); $uY = $mkU('m6c_y');
$_SESSION['uid'] = $uBoss; current_user(true); ua(true);
$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$base = fn(array $x = []) => array_merge([
    'requested_by_id' => $uBoss, 'requested_by_name' => 'M6C', 'requesting_department_id' => $qua['id'],
    'hiring_department_id' => $eng['id'], 'job_title' => 'M6C Engineer', 'designation' => 'ENGINEER',
    'job_description' => 'm6c', 'quantity' => 2, 'office_id' => 9641, 'required_by' => '2026-12-01',
    'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL', 'reason' => 'x'], $x);
$approve = function (array $x = []) use ($base) {
    [$ok,, $id] = hreq_save(0, $base($x)); if (!$ok) return 0;
    hreq_submit($id); hreq_apply_decision($id, 'APPROVED', 'M6C Approver', 'ok'); return (int) $id; };
$mkCand = function ($req, $stage = 'OFFERED') use ($pdo) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,created_at)
                   VALUES (?,'M6C','C',?,?,?)")->execute(['M6CC-' . bin2hex(random_bytes(3)), $stage, $req, date('c')]);
    return (int) $pdo->lastInsertId(); };

$race = function (array $ops) use ($root, $engine, $uBoss) {
    $env = $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
          . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
          . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    $procs = [];
    foreach ($ops as [$op, $id, $arg]) {
        $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_m6_worker.php') . ' '
             . escapeshellarg($op) . ' ' . (int) $id . ' ' . escapeshellarg((string) $arg) . ' 500 ' . (int) $uBoss . ' 2>&1';
        $pipes = []; $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($p)) $procs[] = [$p, $pipes];
    }
    $res = [];
    foreach ($procs as [$p, $pipes]) {
        $raw = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
        foreach (explode("\n", trim($raw)) as $line) { $d = json_decode(trim($line), true); if (is_array($d)) $res[] = $d; }
    }
    return $res;
};
$filled = fn($rq) => (int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rq]);

// ---- C1 · TWO JOININGS FOR ONE REMAINING SEAT ------------------------------
t_section('C1 · two people join the last seat at the same moment');
$h1 = $approve(['job_title' => 'M6C Last Seat', 'quantity' => 2]);
[$ok1,, $rq1] = hreq_to_requisition($h1, 2);
$a = $mkCand($rq1); $b = $mkCand($rq1); $c = $mkCand($rq1);
$pdo->prepare("UPDATE candidates SET stage='ACCEPTED' WHERE id=?")->execute([$a]);  // one seat gone
reqf_sync($rq1);
t_eq(rexec_seats($rq1)['remaining'], 1, 'C1.1 · exactly one seat remains');
$r1 = $race([['join', $b, ''], ['join', $c, '']]);
t_eq(count($r1), 2, 'C1.2 · both processes reported');
//  THIS ASSERTION WAS AN OVER-CLAIM OF MINE, and MariaDB proved it: it demanded
//  "exactly one joining succeeded", and under a genuine dead heat BOTH claims are
//  refused — the seat is left for the next attempt rather than handed to one of
//  them. Two attempts to make the compensator pick a winner each let an arriving
//  candidate displace an established one (L12, L13), which is worse, so the safe
//  rule stands and what is guaranteed is asserted instead of what I hoped for.
//  The two hard invariants are asserted as strongly as before.
$won = array_values(array_filter($r1, fn($r) => $r['ok'] && $r['code'] === 'JOINED'));
t_ok(count($won) <= 1, 'C1.3 · *** never more than one joining succeeds ***');
t_ok($filled($rq1) <= 2, 'C1.4 · *** never more joined than the two approved seats ***');
t_eq((string) ops_val("SELECT stage FROM candidates WHERE id=?", [$a]), 'ACCEPTED',
     'C1.5 · *** and the person already in a seat is never displaced ***');
$lost = array_values(array_filter($r1, fn($r) => !$r['ok']));
t_ok(count($lost) >= 1, 'C1.6 · at least one claimant was refused');
foreach ($lost as $l)
    t_ok(in_array($l['code'], ['GATED', 'REVERTED'], true), 'C1.7 · …and told why: ' . $l['code']);
//  The cost is asserted, not hidden: a refused dead heat must leave the seat
//  usable, never consume it.
if (count($won) === 0) {
    t_eq($filled($rq1), 1, 'C1.8 · a dead heat refused both…');
    t_eq(rexec_seats($rq1)['remaining'], 1, 'C1.9 · …and left the seat for whoever tries next');
} else {
    t_eq($filled($rq1), 2, 'C1.8 · one claim survived and both seats are filled');
    t_eq(rexec_seats($rq1)['remaining'], 0, 'C1.9 · …and the requirement is full');
}

t_section('C2 · four processes, one seat');
$h2 = $approve(['job_title' => 'M6C Four', 'quantity' => 1]);
[$ok2,, $rq2] = hreq_to_requisition($h2, 1);
$four = [$mkCand($rq2), $mkCand($rq2), $mkCand($rq2), $mkCand($rq2)];
$r2 = $race(array_map(fn($x) => ['join', $x, ''], $four));
$won2 = array_values(array_filter($r2, fn($r) => $r['ok'] && $r['code'] === 'JOINED'));
t_ok(count($won2) <= 1, 'C2.1 · *** never more than one winner under four-way contention ***');
t_ok($filled($rq2) <= 1, 'C2.2 · *** the single approved seat never holds more than one person ***');
t_eq($filled($rq2), count($won2), 'C2.3 · and the record agrees with what the processes were told');

t_section('C3 · a joining racing a material change that blocks the request');
$h3 = $approve(['job_title' => 'M6C Race Block', 'quantity' => 2]);
[$ok3,, $rq3] = hreq_to_requisition($h3, 2);
$c3 = $mkCand($rq3);
$r3 = $race([['join', $c3, ''], ['material', $h3, 'G9']]);
$joinRes = null; foreach ($r3 as $r) if ($r['op'] === 'join') $joinRes = $r;
$blocked = hreq_req_block_reason($rq3) !== '';
t_ok($blocked, 'C3.1 · the material change landed and the request is blocked');
//  Either the joining got in before the block, or it was refused. What must
//  never happen is a joining recorded against a request that was already blocked.
$joinedCount = $filled($rq3);
t_ok(($joinRes['ok'] && $joinedCount === 1) || (!$joinRes['ok'] && $joinedCount === 0),
     'C3.2 · *** the outcome is consistent: ' . $joinRes['code'] . ' with ' . $joinedCount . ' joined ***');
t_ok(!hreq_is_executable(hreq_get($h3)), 'C3.3 · and execution is blocked from here on');
t_eq(rexec_block_reason($rq3, 'JOIN', 0) !== '', true, 'C3.4 · no further joining is possible');

t_section('C4 · two recruiter assignments during the lifecycle');
$h4 = $approve(['job_title' => 'M6C Assign']); [$ok4,, $rq4] = hreq_to_requisition($h4, 1);
$r4 = $race([['assign', $rq4, $uX], ['assign', $rq4, $uY]]);
$won4 = array_values(array_filter($r4, fn($r) => $r['ok'] && $r['code'] === 'OK'));
t_eq(count($won4), 1, 'C4.1 · *** exactly one assignment wins ***');
//  The winner reports the person IT asked for, and the column must hold exactly
//  that. The first version of this assertion fell back to re-reading the same
//  column when the worker did not report one — which made it compare a value
//  with itself and pass no matter what happened.
t_eq((int) ops_val("SELECT COALESCE(recruiter_id,0) FROM requisitions WHERE id=?", [$rq4]), (int) $won4[0]['to'],
     'C4.2 · *** the column holds the winning process\'s person, not a mixture ***');
t_ok(in_array((int) $won4[0]['to'], [$uX, $uY], true), 'C4.2b · …and that person is one of the two asked for');
t_eq(count(rasg_history('REQ_RECRUITER', $rq4)), 1, 'C4.3 · one move, one ledger row');

t_section('C5 · two decisions on one approval');
$h5 = $approve(['job_title' => 'M6C Decide']);
hreq_save($h5, $base(['job_title' => 'M6C Decide', 'designation' => 'SUPERVISOR']));   // needs re-approval
$r5 = $race([['decide', $h5, 'APPROVED'], ['decide', $h5, 'REJECTED']]);
$ok5 = array_values(array_filter($r5, fn($r) => $r['ok']));
t_eq(count($ok5), 1, 'C5.1 · *** only one decision is recorded ***');
$state = hreq_reapproval_state(hreq_get($h5));
t_ok(in_array($state, ['REAPPROVED', 'REJECTED'], true), 'C5.2 · the request is in one decided state: ' . $state);
$exec = hreq_is_executable(hreq_get($h5));
t_eq($exec, $state === 'REAPPROVED', 'C5.3 · *** and execution agrees with that decision ***');

t_section('C6 · recruiter deactivation racing an assignment');
$h6 = $approve(['job_title' => 'M6C Deact']); [$ok6,, $rq6] = hreq_to_requisition($h6, 1);
$uZ = $mkU('m6c_z');
$r6 = $race([['assign', $rq6, $uZ], ['deactivate', $uZ, '']]);
$owner6 = (int) ops_val("SELECT COALESCE(recruiter_id,0) FROM requisitions WHERE id=?", [$rq6]);
$activeZ = (int) ops_val("SELECT COALESCE(is_active,0) FROM users WHERE id=?", [$uZ]);
//  Either the assignment got in first (and the person was live at that moment),
//  or it was refused. What must never happen is an assignment made AFTER the
//  deactivation was committed.
t_ok(($owner6 === $uZ) || ($owner6 === 0),
     'C6.1 · the outcome is one of the two consistent possibilities (owner=' . $owner6 . ', active=' . $activeZ . ')');
t_eq(rasg_assign('REQ_RECRUITER', $rq6, $uZ, ['expect' => $owner6])['code'],
     $owner6 === $uZ ? 'NO_CHANGE' : 'RECRUITER_INACTIVE',
     'C6.2 · *** and from here on a deactivated person cannot be given the work ***');

$_SESSION = $m6c; current_user(true); ua(true);
