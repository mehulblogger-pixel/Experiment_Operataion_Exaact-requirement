<?php
// A REAL separate process for the Gate 3 concurrency tests. Attaches to the SAME
// database as the parent; deliberately does NOT use tests/bootstrap.php, which
// drops every table on the MySQL path.
//
//   php tests/_g3_worker.php <continue|reject> <reviewId> <reason> <delay-ms> <uid>
//
//  Why a second process at all: both resolvers read the review's status in PHP
//  before they write. A single-process test can never make two readers see OPEN at
//  the same moment, so it cannot tell a real guard from a missing one — the PHP
//  pre-check alone would pass it. Only the conditional UPDATE holds here, and only
//  this worker can prove it does.
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'g3-worker';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$op = (string) ($argv[1] ?? ''); $rid = (int) ($argv[2] ?? 0);
$reason = (string) ($argv[3] ?? 'worker'); $delay = (int) ($argv[4] ?? 0); $uid = (int) ($argv[5] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }
if ($delay > 0) usleep($delay * 1000);

$out = ['op' => $op, 'ok' => false, 'msg' => ''];
try {
    if ($op === 'continue')   [$ok, $msg] = crev_continue($rid, $reason);
    elseif ($op === 'reject') [$ok, $msg] = crev_reject($rid, $reason);
    else                      [$ok, $msg] = [false, 'unknown op'];
    $out['ok'] = (bool) $ok; $out['msg'] = (string) $msg;
} catch (Throwable $e) { $out['msg'] = 'threw: ' . $e->getMessage(); }
echo json_encode($out) . "\n";
