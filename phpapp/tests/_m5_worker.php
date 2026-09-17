<?php
// A REAL separate process for the M5 concurrency tests. It attaches to the SAME
// database the parent is using, and deliberately does NOT use tests/bootstrap.php,
// which drops every table on the MySQL path.
//
//   php tests/_m5_worker.php <op> <entityId> <toUserId> <expect> <delay-ms> <uid>
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'm5-worker';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$op     = (string) ($argv[1] ?? '');
$id     = (int) ($argv[2] ?? 0);
$to     = ($argv[3] ?? '') === '' ? null : (int) $argv[3];
$expect = ($argv[4] ?? '') === 'NONE' ? null : (int) ($argv[4] ?? 0);
$delay  = (int) ($argv[5] ?? 0);
$uid    = (int) ($argv[6] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }
if ($delay > 0) usleep($delay * 1000);

$out = ['op' => $op, 'ok' => false, 'code' => '', 'to' => $to];
try {
    if ($op === 'assign' || $op === 'assign_cand') {
        $subject = $op === 'assign' ? 'REQ_RECRUITER' : 'CAND_RECRUITER';
        $r = rasg_assign($subject, $id, $to, ['expect' => $expect, 'source' => 'm5-worker']);
        $out['ok'] = (bool) $r['ok']; $out['code'] = (string) $r['code'];
    } elseif ($op === 'assign_nobase') {
        //  No baseline at all — the compare-and-swap still has to hold the line.
        $r = rasg_assign('REQ_RECRUITER', $id, $to, ['source' => 'm5-worker']);
        $out['ok'] = (bool) $r['ok']; $out['code'] = (string) $r['code'];
    } elseif ($op === 'form') {
        //  Exactly what a browser POST does, baseline and all.
        $why = rasg_apply_posted('REQ_RECRUITER', $id,
            ['recruiter_id' => $to, 'own_base_recruiter_id' => $expect], 'recruiter_id', 'm5-worker-form');
        $out['ok'] = ($why === ''); $out['code'] = $why === '' ? 'OK' : 'REFUSED';
    }
} catch (Throwable $e) { $out['code'] = 'EX: ' . $e->getMessage(); }
echo json_encode($out) . "\n";
