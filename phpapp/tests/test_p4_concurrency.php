<?php
// ============================================================================
//  PHASE 4 — REAL-PROCESS CONCURRENCY (§26, §27)
//
//  Genuinely separate php processes on independent connections. Nothing here is
//  a sequential simulation and nothing is defended in the browser.
//
//  The Phase 3 rule is preserved exactly as ratified:
//      NEVER OVERFILL.  NEVER DISPLACE AN ESTABLISHED HOLDER.
//  Where simultaneous claims cannot be resolved without displacement, the
//  contested claims may be refused and the capacity left for a later valid
//  transaction. "Somebody must win" is NOT an invariant of this system.
// ============================================================================

t_section('Phase 4 — real-process concurrency');

$pdo = db(); hreq_migrate(); appr_migrate(); act_migrate(); rasg_migrate(); reqf_migrate(); rful_migrate();
$engine = db_driver(); $root = dirname(__DIR__); $c4o = $_SESSION;
try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (9671,'P4C Branch',1)")->execute(); } catch (Throwable $e) {}
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser,home_office_id,scope_offices)
               VALUES ('p4c_boss','P4C','MANAGER',1,1,9671,'')")->execute();
$uC = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $uC; current_user(true); ua(true);
$eng = dept_of('Engineering'); $qua = dept_of('Quality');
$c4req = function ($qty, $title) use ($uC, $eng, $qua) {
    [$ok,, $h] = hreq_save(0, ['requested_by_id' => $uC, 'requested_by_name' => 'P4C',
        'requesting_department_id' => $qua['id'], 'hiring_department_id' => $eng['id'],
        'job_title' => $title, 'designation' => 'ENGINEER', 'job_description' => 'p4c',
        'quantity' => $qty, 'office_id' => 9671, 'required_by' => '2026-12-01',
        'employment_type' => 'CONTRACT', 'request_type' => 'PROJECT', 'priority' => 'NORMAL', 'reason' => 'x']);
    if (!$ok) return 0;
    hreq_submit($h); hreq_apply_decision($h, 'APPROVED', 'P4C', 'ok');
    [$okR,, $rq] = hreq_to_requisition($h, $qty); return $okR ? (int) $rq : 0; };
$c4cand = function ($req, $stage = 'OFFERED') use ($pdo) {
    $pdo->prepare("INSERT INTO candidates (cand_code,first_name,last_name,stage,requisition_id,created_at)
                   VALUES (?,'P4C','C',?,?,?)")->execute(['P4CC-' . bin2hex(random_bytes(4)), $stage, $req, date('c')]);
    return (int) $pdo->lastInsertId(); };

//  Every worker sleeps the SAME amount before acting, so they collide rather
//  than queue. Launching is cheap; the sleep is what makes it a race.
$race = function (array $ops, $delay = 600) use ($root, $engine, $uC) {
    $env = $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
          . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
          . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    $procs = [];
    foreach ($ops as [$op, $id, $arg, $arg2]) {
        $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_p4_worker.php') . ' '
             . escapeshellarg($op) . ' ' . (int) $id . ' ' . escapeshellarg((string) $arg) . ' '
             . escapeshellarg((string) $arg2) . ' ' . (int) $delay . ' ' . (int) $uC . ' 2>&1';
        $pipes = []; $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($p)) $procs[] = [$p, $pipes];
    }
    $res = [];
    foreach ($procs as [$p, $pipes]) {
        $raw = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
        foreach (explode("\n", trim($raw)) as $line) { $d = json_decode(trim($line), true); if (is_array($d)) $res[] = $d; }
    }
    return $res; };
$okN = fn($res) => count(array_filter($res, fn($r) => !empty($r['ok'])));

// ---- C1 · TWO COORDINATORS PROMISE THE LAST SEATS AT THE SAME MOMENT --------
t_section('C1 · the approved headcount cannot be promised twice');
$rq1 = $c4req(10, 'P4C Race');
rful_allocate($rq1, 'OWN_PAYROLL', 6);                     // six spoken for; four left
$res = $race([['allocate', $rq1, 'MANPOWER_AGENCY', 4], ['allocate', $rq1, 'SUBCON_AGENCY', 4]]);
t_eq(count($res), 2, 'C1.1 · two real processes reported back');
$s = rful_summary($rq1);
t_ok($s['allocated'] <= 10, 'C1.2 · the requirement is NOT over-promised — whoever won');
t_eq($s['over_committed'], 0, 'C1.3 · nothing is promised twice');
t_ok($okN($res) <= 1, 'C1.4 · at most one of the two claims succeeded');
foreach ($res as $r) if (empty($r['ok']))
    t_eq($r['code'], 'OVER_AUTHORISED', 'C1.5 · the losing process was told the seats were gone');
//  Refusing BOTH is permitted by the ratified policy; what is forbidden is
//  writing both. Either way the capacity is still there for the next attempt.
$after = rful_summary($rq1);
t_ok($after['unallocated'] === 0 || $after['unallocated'] === 4,
     'C1.6 · either one claim took the four, or the four are still available');
t_eq(rful_allocate($rq1, 'SUPPLIER', $after['unallocated'] ?: 1)['code'],
     $after['unallocated'] > 0 ? 'OK' : 'OVER_AUTHORISED',
     'C1.7 · a later valid transaction can still take whatever is left');

// ---- C2 · TWO ARRIVALS, ONE SEAT LEFT AT A SOURCE ---------------------------
t_section('C2 · a source is never credited beyond its promise');
$rq2 = $c4req(6, 'P4C Credit');
$a2 = rful_allocate($rq2, 'MANPOWER_AGENCY', 1)['id'];
$p1 = $c4cand($rq2, 'ACCEPTED'); $p2 = $c4cand($rq2, 'ACCEPTED');
$res = $race([['attach', $p1, $a2, ''], ['attach', $p2, $a2, '']]);
t_eq(count($res), 2, 'C2.1 · two real processes reported back');
t_eq(rful_fulfilled($a2), min(1, $okN($res)), 'C2.2 · the agency is credited with at most its one');
t_ok(rful_fulfilled($a2) <= 1, 'C2.3 · never with two (I5)');
t_eq(rful_over_allocated($a2), false, 'C2.4 · the source is not over-credited');
//  Neither person was removed from their seat — Phase 4 decides credit, never
//  whether somebody holds a position.
t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE id IN (?,?) AND stage='ACCEPTED'", [$p1, $p2]), 2,
     'C2.5 · both people are STILL in their seats (§24)');

// ---- C3 · AN ESTABLISHED CREDIT IS NEVER DISPLACED --------------------------
t_section('C3 · an arriving claim never pushes out an established one');
$rq3 = $c4req(6, 'P4C Established');
$a3 = rful_allocate($rq3, 'SUBCON_AGENCY', 1)['id'];
$held = $c4cand($rq3, 'ACCEPTED');
t_eq(rful_attach($held, $a3)['code'], 'OK', 'C3.1 · somebody holds the single credit');
$newcomers = [];
for ($i = 0; $i < 3; $i++) $newcomers[] = $c4cand($rq3, 'ACCEPTED');
$res = $race(array_map(fn($c) => ['attach', $c, $a3, ''], $newcomers));
t_eq(count($res), 3, 'C3.2 · three real processes tried at once');
t_eq((int) ops_val("SELECT COALESCE(allocation_id,0) FROM candidates WHERE id=?", [$held]), (int) $a3,
     'C3.3 · the established credit is UNTOUCHED');
t_eq(rful_fulfilled($a3), 1, 'C3.4 · the source is still credited with exactly one');
t_eq($okN($res), 0, 'C3.5 · all three arrivals were refused rather than displacing anybody');

// ---- C4 · TWO RESIZES OF THE SAME ALLOCATION --------------------------------
t_section('C4 · two people resize one allocation at the same moment');
$rq4 = $c4req(10, 'P4C Resize');
$a4 = rful_allocate($rq4, 'OWN_PAYROLL', 4)['id'];
$res = $race([['reallocate', $a4, 9, 4], ['reallocate', $a4, 7, 4]]);
t_eq(count($res), 2, 'C4.1 · two real processes reported back');
$q4 = (int) rful_get($a4)['allocated_qty'];
t_ok(in_array($q4, [4, 7, 9], true), 'C4.2 · the allocation holds ONE of the values asked for, not a blend');
t_eq($okN($res) <= 1, true, 'C4.3 · at most one resize was accepted');
foreach ($res as $r) if (empty($r['ok']))
    t_ok(in_array($r['code'], ['STALE', 'LOST_RACE'], true),
         'C4.4 · the loser was told somebody changed it first (' . $r['code'] . ')');
t_ok(rful_summary($rq4)['allocated'] <= 10, 'C4.5 · and the requirement is still within its approval');

// ---- C5 · A RESIZE AND A RELEASE COLLIDE ------------------------------------
t_section('C5 · resizing and releasing the same source at once');
$rq5 = $c4req(10, 'P4C Collide');
$a5 = rful_allocate($rq5, 'FREELANCER', 5)['id'];
$f5 = $c4cand($rq5, 'ACCEPTED'); rful_attach($f5, $a5);
$res = $race([['reallocate', $a5, 3, 5], ['close', $a5, 'RELEASED', '']]);
t_eq(count($res), 2, 'C5.1 · two real processes reported back');
$row5 = rful_get($a5);
t_ok(rful_fulfilled($a5) <= (int) $row5['allocated_qty'],
     'C5.2 · whatever happened, the source is not credited beyond its promise (I5)');
t_eq(rful_fulfilled($a5), 1, 'C5.3 · the person it delivered is still credited to it (I6)');
t_ok(rful_summary($rq5)['sourced_fulfilled'] <= rful_summary($rq5)['allocated'],
     'C5.4 · SOURCED ≤ ALLOCATED survived the collision (I1)');

// ---- C6 · THE WHOLE ARRIVAL, RACED ------------------------------------------
//  The real production path end to end: M6 decides the seat, Phase 4 decides the
//  credit. Three people arrive at once against a TWO-seat requirement whose only
//  source was promised two.
t_section('C6 · three arrivals, two seats, one source — the whole path at once');
$rq6 = $c4req(2, 'P4C Whole');
$a6 = rful_allocate($rq6, 'MANPOWER_AGENCY', 2)['id'];
$trio = []; for ($i = 0; $i < 3; $i++) $trio[] = $c4cand($rq6, 'OFFERED');
$res = $race(array_map(fn($c) => ['join_attach', $c, $a6, ''], $trio));
t_eq(count($res), 3, 'C6.1 · three real processes ran the whole arrival path');
$joined = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rq6]);
t_ok($joined <= 2, "C6.2 · never more than the two approved seats were filled (got $joined)");
t_ok(rful_fulfilled($a6) <= 2, 'C6.3 · the agency was never credited beyond its two');
$s6 = rful_summary($rq6);
t_ok($s6['sourced_fulfilled'] <= $s6['allocated'] && $s6['allocated'] <= $s6['authorised'],
     'C6.4 · SOURCED ≤ ALLOCATED ≤ AUTHORISED (I1)');
t_ok($s6['fulfilled'] <= $s6['authorised'], 'C6.5 · nobody joined beyond the approved headcount');
t_eq($s6['over_committed'], 0, 'C6.6 · nothing was promised twice');
//  Every person who was refused must have been refused CLEANLY. Note what that
//  does NOT mean: a candidate can legitimately carry a source link before they
//  join — the agency sent them, and that is true whether or not they are hired.
//  rful_fulfilled() counts only people in a FILLED stage, so such a link consumes
//  nothing. What must hold is that no reverted arrival is counted anywhere, and
//  that the source's credit never exceeds its promise.
$creditedFilled = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE allocation_id=? AND stage='ACCEPTED'", [$a6]);
t_eq($creditedFilled, rful_fulfilled($a6), 'C6.7 · the source’s credit is exactly the people in filled stages');
t_ok($creditedFilled <= (int) rful_get($a6)['allocated_qty'],
     'C6.8 · …and never more than it was promised, whoever lost the race (I5)');
$revertedCounted = (int) ops_val(
    "SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage<>'ACCEPTED' AND id IN (" .
    implode(',', array_map('intval', $trio)) . ")", [$rq6]);
t_eq($revertedCounted + $joined, 3, 'C6.9 · every one of the three is either in a seat or plainly not');
t_eq(rful_over_allocated($a6), false, 'C6.10 · the source is not over-credited');
//  …and the whole-workspace sweep agrees: not one link anywhere is unholdable.
$badNow = [];
foreach (rful_bad_links() as $cid) {
    $c = ops_one("SELECT id, requisition_id, allocation_id FROM candidates WHERE id=?", [$cid]);
    $al = rful_get((int) $c['allocation_id']);
    if (!$al || (int) $al['requisition_id'] !== (int) $c['requisition_id'] || rful_over_allocated((int) $al['id']))
        $badNow[] = $cid;
}
t_eq(count($badNow), 0, 'C6.11 · a sweep of every link in the workspace finds nothing broken');

$_SESSION = $c4o; current_user(true); ua(true);
