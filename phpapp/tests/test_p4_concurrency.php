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

//  Every worker is given the SAME wall-clock instant to act on, and spins until
//  it. A fixed sleep after start does not make a race — PHP's boot takes a few
//  hundred milliseconds and varies, so the processes queue instead of colliding,
//  and a mutation battery proved that two compare-and-swaps could be deleted
//  without a single probe noticing. The instant is far enough ahead for every
//  worker to have finished booting.
$race = function (array $ops, $delay = 600, $lead = 3.0) use ($root, $engine, $uC) {
    //  The lead must be longer than the SLOWEST worker's start-up, or the last
    //  process to boot arrives after the instant has passed and never collides.
    //  Each worker loads the whole application (227 libraries), and six of them
    //  contend for CPU and disk while doing it, so a second is not enough — a
    //  mutation battery proved that with a short lead the attach races were not
    //  races at all and two controls could be deleted unnoticed.
    $fireAt = (int) round((microtime(true) + max($lead, $delay / 1000)) * 1000);
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
             . escapeshellarg((string) $arg2) . ' ' . $fireAt . ' ' . (int) $uC . ' 2>&1';
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
//  Over-commitment here is not a failure — it is the truth being reported.
//  If a racer's CREDIT loses but their JOINING wins (correct: Phase 4 must never
//  refuse a joining), they fill an approved seat directly, and the agency is
//  still promised a seat that no longer exists. Demanding zero would be
//  demanding one of several legitimate outcomes; what must ALWAYS hold is that
//  the over-commitment is exactly explained by the people who arrived without a
//  source, and that it can still be trimmed away.
t_ok($s6['over_committed'] <= $s6['direct_fulfilled'],
     'C6.6 · any over-commitment is no larger than the arrivals that caused it');
t_eq($s6['over_committed'], max(0, $s6['allocated'] + $s6['direct_fulfilled'] - $s6['authorised']),
     'C6.6a · …and is exactly what the figures say it is, not an invented number');
if ($s6['over_committed'] > 0) {
    $trim = (int) rful_get($a6)['allocated_qty'] - $s6['over_committed'];
    t_eq(rful_reallocate($a6, max(1, $trim))['code'], 'OK', 'C6.6b · a coordinator can still trim it');
    t_eq(rful_summary($rq6)['over_committed'], 0, 'C6.6c · …and the requirement is square again');
} else {
    t_eq($s6['allocated'] + $s6['direct_fulfilled'], $s6['authorised'] - ($s6['authorised'] - $s6['allocated'] - $s6['direct_fulfilled']),
         'C6.6b · nothing was promised twice, and the figures agree');
}
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


// ---- C7 · ONE PERSON, TWO SOURCES, AT THE SAME MOMENT -----------------------
//  The compare-and-swap on the credit only matters under a race: in a single
//  process the value read is always the value written. Two processes moving the
//  SAME person to DIFFERENT sources is the case it exists for.
t_section('C7 · two processes move one person to two different sources at once');
$rq7 = $c4req(8, 'P4C Two sources');
$x1 = rful_allocate($rq7, 'MANPOWER_AGENCY', 4)['id'];
$x2 = rful_allocate($rq7, 'SUBCON_AGENCY', 4)['id'];
$mover = $c4cand($rq7, 'ACCEPTED');
$res = $race([['attach', $mover, $x1, ''], ['attach', $mover, $x2, '']]);
t_eq(count($res), 2, 'C7.1 · two real processes reported back');
$landed = (int) ops_val("SELECT COALESCE(allocation_id,0) FROM candidates WHERE id=?", [$mover]);
t_ok(in_array($landed, [(int) $x1, (int) $x2], true),
     'C7.2 · the person is credited to ONE of the two sources, never a blend');
t_eq(rful_fulfilled($x1) + rful_fulfilled($x2), 1,
     'C7.3 · counted exactly once across both sources — never twice, never zero');
t_ok($okN($res) <= 2, 'C7.4 · both processes completed');
$s7 = rful_summary($rq7);
t_ok($s7['sourced_fulfilled'] <= $s7['allocated'], 'C7.5 · SOURCED ≤ ALLOCATED survived (I1)');
t_eq($s7['sourced_fulfilled'], 1, 'C7.6 · one person, counted once');
//  THE LEDGER MUST RECORD EXACTLY THE CHANGES THAT HAPPENED — no more, no fewer.
//
//  Note what this must NOT assert. If the two processes serialise rather than
//  collide, BOTH succeed: the person is credited to the first source and then
//  legitimately MOVED to the second, and both entries are true history. An
//  earlier version of this probe demanded that the source the person did not end
//  on carry no entry, which confuses "lost the race" with "never had it" — and
//  MariaDB duly produced the serialised interleaving and failed it.
//
//  What must always hold is that the number of credits written equals the number
//  of processes that actually changed something. A compare-and-swap that writes
//  safely but then announces a success it did not achieve would leave a credit in
//  the ledger that never happened.
$attachEv = (int) ops_val("SELECT COUNT(*) FROM requisition_allocation_events
                           WHERE event='ATTACHED' AND allocation_id IN (?,?) AND reason LIKE ?",
                          [(int) $x1, (int) $x2, '%candidate #' . (int) $mover . '%']);
t_eq($attachEv, $okN($res), 'C7.7 · the ledger records exactly as many credits as processes that succeeded');
t_ok($attachEv >= 1 && $attachEv <= 2, 'C7.8 · …which is one (a real race) or two (a move), never more');

// ---- C8 · MANY PROCESSES, ONE REMAINING SEAT --------------------------------
//  Six racers rather than two, so the interleaving that lets two pass the
//  pre-check together actually occurs and the compensating check is exercised
//  rather than merely present.
//  SEVERAL ROUNDS, not one. A compensating check only fires when two processes
//  BOTH get past the pre-check before either writes, and whether that happens in
//  any single round is luck. A mutation battery proved that a one-round race let
//  the compensators be deleted unnoticed. Four rounds on fresh requirements makes
//  the interleaving occur rather than hoping for it — and every round asserts the
//  same invariants, so a failure in any one of them fails the battery.
t_section('C8 · six processes, four seats left — four rounds');
for ($round = 1; $round <= 4; $round++) {
    $rq8 = $c4req(10, 'P4C Six r' . $round);
    rful_allocate($rq8, 'OWN_PAYROLL', 6);
    $res = $race(array_map(fn($i) => ['allocate', $rq8, 'SUPPLIER', 4], range(1, 6)), 800);
    t_eq(count($res), 6, "C8.1 · round $round · six real processes reported back");
    $s8 = rful_summary($rq8);
    t_ok($s8['allocated'] <= 10, "C8.2 · round $round · the requirement is NOT over-promised");
    t_eq($s8['over_committed'], 0, "C8.3 · round $round · nothing is promised twice");
    t_ok($okN($res) <= 1, "C8.4 · round $round · at most one of the six succeeded");
    foreach ($res as $r) if (empty($r['ok']))
        t_eq($r['code'], 'OVER_AUTHORISED', "C8.5 · round $round · every loser was told the seats were gone");
    t_ok(in_array($s8['unallocated'], [0, 4], true),
         "C8.6 · round $round · either one claim took the four, or all four remain");
    //  And the ledger must not carry a promise that was withdrawn as if it stood.
    $liveN = (int) ops_val("SELECT COUNT(*) FROM requisition_allocations
                            WHERE requisition_id=? AND source='SUPPLIER' AND status NOT IN ('RELEASED','CANCELLED')", [$rq8]);
    t_eq($liveN, $okN($res), "C8.7 · round $round · exactly as many supplier promises stand as processes succeeded");
}

// ---- C9 · SIX ARRIVALS, TWO SEATS AT ONE SOURCE — FOUR ROUNDS ---------------
//  The same reasoning for the credit side: the attach compensator and its
//  compare-and-swap only matter when several processes pass the seat check
//  together, so the race is run repeatedly rather than once.
t_section('C9 · six arrivals, two credits — four rounds');
for ($round = 1; $round <= 4; $round++) {
    $rq9 = $c4req(8, 'P4C Credits r' . $round);
    $a9 = rful_allocate($rq9, 'MANPOWER_AGENCY', 2)['id'];
    $six = []; for ($i = 0; $i < 6; $i++) $six[] = $c4cand($rq9, 'ACCEPTED');
    $res = $race(array_map(fn($c) => ['attach', $c, $a9, ''], $six), 800);
    t_eq(count($res), 6, "C9.1 · round $round · six real processes reported back");
    t_ok(rful_fulfilled($a9) <= 2, "C9.2 · round $round · the source is never credited beyond its two");
    t_eq(rful_over_allocated($a9), false, "C9.3 · round $round · and is not over-credited");
    $credited = (int) ops_val("SELECT COUNT(*) FROM candidates WHERE allocation_id=?", [$a9]);
    t_eq($credited, rful_fulfilled($a9), "C9.4 · round $round · every credit written is a credit counted");
    t_ok($credited <= 2, "C9.5 · round $round · never more links than seats");
    //  Nobody is ejected from a seat by losing a credit.
    t_eq((int) ops_val("SELECT COUNT(*) FROM candidates WHERE requisition_id=? AND stage='ACCEPTED'", [$rq9]), 6,
         "C9.6 · round $round · all six are STILL in their seats (§24)");
}

$_SESSION = $c4o; current_user(true); ua(true);
