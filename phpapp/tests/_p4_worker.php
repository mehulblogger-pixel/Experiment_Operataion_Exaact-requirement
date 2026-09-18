<?php
// A REAL separate process for the Phase 4 concurrency tests. Attaches to the
// SAME database as the parent; deliberately does NOT use tests/bootstrap.php,
// which drops every table on the MySQL path.
//
//   php tests/_p4_worker.php <op> <id> <arg> <arg2> <delay-ms> <uid>
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'p4-worker';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$op    = (string) ($argv[1] ?? ''); $id = (int) ($argv[2] ?? 0);
$arg   = (string) ($argv[3] ?? ''); $arg2 = (string) ($argv[4] ?? '');
$delay = (int) ($argv[5] ?? 0);     $uid = (int) ($argv[6] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }
//  WARM THE ONE-TIME PER-PROCESS COSTS BEFORE THE BARRIER.
//
//  Measured: the lazy, idempotent schema check fires inside the first production
//  read and costs 7.5-9.8 ms, varying by 2.4 ms between workers — while the
//  window this race is supposed to happen inside (the seat check to the write) is
//  about 250 microseconds. The processes were therefore arriving nine
//  milliseconds apart and queueing politely; they were never racing, and two
//  compare-and-swaps and a compensator could be deleted without a single probe
//  noticing.
//
//  This is test-side only. No production logic, timing or SQL is altered: these
//  are ordinary reads plus an idempotent migration that every worker pays for in
//  the same place, for exactly the reason the database connection is opened here
//  rather than after the barrier. With it, eight workers now enter the critical
//  section within a few hundred microseconds of one another.
if (function_exists('rful_migrate')) {
    try {
        db(); rful_migrate();
        rful_may_touch(0);
        if (function_exists('lk_options_or')) lk_options_or('req_sourcing_model', []);
        if ($id > 0) { rful_get($id); rful_get($arg === '' ? 0 : (int) $arg); }
        //  The ALLOCATE and RESIZE paths reach their ceiling check through M6's
        //  gate, which asks M4 — several queries of variable cost that were still
        //  being paid AFTER the barrier, spreading the workers out before they got
        //  anywhere near the window they were meant to contend in. Same defect as
        //  the schema check, same test-side remedy: pay it up front. Reads only.
        if ($op === 'allocate' && $id > 0) {
            if (function_exists('rexec_block_reason')) rexec_block_reason($id);
            rful_summary($id);
        } elseif (($op === 'reallocate' || $op === 'close') && $id > 0) {
            $wa = rful_get($id);
            if ($wa) {
                if (function_exists('rexec_block_reason')) rexec_block_reason((int) $wa['requisition_id']);
                rful_summary((int) $wa['requisition_id']);
            }
        }
    } catch (Throwable $e) {}
}
//  SYNCHRONISE ON A WALL-CLOCK INSTANT, not on a sleep.
//
//  Sleeping a fixed interval after start does not make a race: PHP's own boot
//  takes a few hundred milliseconds and varies, so the processes ended up queued
//  rather than collided, and a compare-and-swap could be removed without a single
//  probe noticing. Every worker now spins until the SAME microsecond the parent
//  named, so they enter the critical section together whatever their start-up
//  cost. $delay is that absolute epoch-microsecond target when it is large, and
//  a plain millisecond sleep when it is small (the older call sites).
if ($delay > 1000000000) { while (microtime(true) * 1000 < $delay) { } }
elseif ($delay > 0) { usleep($delay * 1000); }

$out = ['op' => $op, 'arg' => $arg, 'ok' => false, 'code' => '', 'msg' => ''];
try {
    if ($op === 'allocate') {
        //  Exactly what /requisition-allocations does for "add a source".
        $r = rful_allocate($id, $arg, (int) $arg2);
        $out['ok'] = (bool) $r['ok']; $out['code'] = (string) $r['code'];
        $out['id'] = (int) $r['id']; $out['msg'] = (string) $r['reason'];
    } elseif ($op === 'reallocate') {
        $r = rful_reallocate($id, (int) $arg, $arg2 === '' ? [] : ['expect' => (int) $arg2]);
        $out['ok'] = (bool) $r['ok']; $out['code'] = (string) $r['code']; $out['msg'] = (string) $r['reason'];
    } elseif ($op === 'close') {
        $r = rful_close($id, $arg ?: 'RELEASED', ['reason' => 'worker']);
        $out['ok'] = (bool) $r['ok']; $out['code'] = (string) $r['code'];
    } elseif ($op === 'attach') {
        //  The candidate-save path: credit this person to that source.
        $r = rful_attach($id, (int) $arg);
        $out['ok'] = (bool) $r['ok']; $out['code'] = (string) $r['code']; $out['msg'] = (string) $r['reason'];
    } elseif ($op === 'join_attach') {
        //  The whole arrival, as the stage route performs it: M6 decides the seat,
        //  Phase 4 decides the credit, and the Phase 4 compensator runs after.
        $c = ops_one("SELECT * FROM candidates WHERE id=?", [$id]);
        $why = rexec_block_reason((int) $c['requisition_id'], 'JOIN', $id);
        if ($why !== '') { $out['code'] = 'GATED'; $out['msg'] = $why; }
        else {
            rful_attach($id, (int) $arg);
            db()->prepare("UPDATE candidates SET stage='ACCEPTED', decided_at=? WHERE id=?")->execute([date('c'), $id]);
            if (function_exists('reqf_sync')) reqf_sync((int) $c['requisition_id']);
            $rev = rexec_join_enforce_after_write($id, (string) $c['stage'], (string) ($c['decided_at'] ?? ''));
            $p4  = rful_enforce_candidate($id, 0);
            $out['ok'] = ($rev === '');
            $out['code'] = $rev !== '' ? 'SEAT_REVERTED' : ($p4 !== '' ? 'CREDIT_REVOKED' : 'JOINED');
            $out['msg'] = $rev !== '' ? $rev : $p4;
        }
    }
    elseif ($op === 'route_cand_edit' || $op === 'route_cand_new' || $op === 'route_cand_stage') {
        //  THE REAL ROUTE, in its own process — because redirect() exits, and
        //  because a route is exactly where a control gets forgotten. The parent
        //  reads the DATABASE afterwards, so nothing here has to be believed.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'p4test'; $_POST['_csrf'] = 'p4test';
        $parts = json_decode($arg2 !== '' ? $arg2 : '{}', true);
        if (is_array($parts)) foreach ($parts as $k => $v) $_POST[$k] = $v;
        if ($op === 'route_cand_stage') {
            $_GET['id'] = $id; $_POST['to_stage'] = $arg !== '' ? $arg : 'ACCEPTED';
            $out['code'] = 'DISPATCHED';
            echo json_encode($out) . "\n";
            ops_dispatch('candidate-stage', 'POST');
            exit;
        }
        $cand = $id > 0 ? ops_one("SELECT * FROM candidates WHERE id=?", [$id]) : null;
        if ($op === 'route_cand_edit') { $_GET['id'] = $id; $_POST['id'] = $id; }
        //  The verdict is printed BEFORE dispatching, because the route will
        //  redirect() and exit. What matters is the database the parent then reads.
        $out['code'] = 'DISPATCHED'; $out['ok'] = true;
        echo json_encode($out) . "\n";
        ops_dispatch($op === 'route_cand_edit' ? 'candidate-edit' : 'candidate-new', 'POST');
        exit;
    }
} catch (Throwable $e) { $out['code'] = 'EX'; $out['msg'] = $e->getMessage(); }
echo json_encode($out) . "\n";
