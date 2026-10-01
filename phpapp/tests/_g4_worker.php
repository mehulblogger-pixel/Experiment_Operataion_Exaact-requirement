<?php
// A REAL separate process for the Gate 4 concurrency tests. Attaches to the SAME
// database as the parent; deliberately does NOT use tests/bootstrap.php, which
// drops every table on the MySQL path.
//
//   php tests/_g4_worker.php <hiringRequestId> <actingUserId> <delay-ms>
//
//  Why a second process: the segregation question and the status transition are two
//  statements, and only a second process can put another decision between them. A
//  self-approval that slips through on timing would be invisible to a single-process
//  test, because there is nothing to interleave with.
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'g4-worker';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$hid = (int) ($argv[1] ?? 0); $uid = (int) ($argv[2] ?? 0); $delay = (int) ($argv[3] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }
if ($delay > 0) usleep($delay * 1000);

//  WHAT THIS PROCESS BELIEVES, reported back with its result. A race whose loser
//  cannot say which policy and which identity it saw is a race nobody can debug.
$out = ['uid' => $uid, 'ok' => false, 'msg' => '',
        'me' => (int) ((current_user()['id'] ?? 0)),
        'master' => function_exists('is_master') ? (is_master() ? 1 : 0) : -1,
        'self' => appr_self_allowed() ? 1 : 0,
        'mx' => appr_self_master_exception() ? 1 : 0];
try {
    $row = hreq_get($hid);
    $out['raised_by'] = (int) ($row['requested_by_id'] ?? 0);
    $out['seg'] = hreq_segregation_blocks($row) ? 1 : 0;
    //  THE GUARDED entry point, which is what a person's click reaches.
    [$ok, $msg] = hreq_decide($hid, true, 'raced');
    $out['ok'] = (bool) $ok; $out['msg'] = (string) $msg;
} catch (Throwable $e) { $out['msg'] = 'threw: ' . $e->getMessage(); }
echo json_encode($out) . "\n";
