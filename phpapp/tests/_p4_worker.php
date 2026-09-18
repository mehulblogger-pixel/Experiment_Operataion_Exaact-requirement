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
if ($delay > 0) usleep($delay * 1000);

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
} catch (Throwable $e) { $out['code'] = 'EX'; $out['msg'] = $e->getMessage(); }
echo json_encode($out) . "\n";
