<?php
// A REAL separate process that drives the REAL joining route for Gate 5.
// Attaches to the SAME database as the parent; deliberately does NOT use
// tests/bootstrap.php, which drops every table on the MySQL path.
//
//   php tests/_g5_worker.php <candidateId> <actingUserId> <YYYY-MM-DD|UNDO> <delay-ms>
//
//  Why a second process at all: the route is reached by a browser, and redirect()
//  exits, so the route cannot be called in-process without ending the test run.
//  It is also the only way to put two simultaneous joinings against each other —
//  the date write and the activation are two statements, and a double activation
//  would be invisible to a single-process test.
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'g5-worker';
$_SERVER['REQUEST_URI'] = '/candidate-joined'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$cid = (int) ($argv[1] ?? 0); $uid = (int) ($argv[2] ?? 0);
$when = (string) ($argv[3] ?? ''); $delay = (int) ($argv[4] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }
if ($delay > 0) usleep($delay * 1000);

//  redirect() exits, so the result is reported from a shutdown handler. What the
//  user was TOLD matters as much as what was written: a route that activates
//  nobody but says "available for scheduling" is still a defect.
register_shutdown_function(function () use ($cid) {
    $flash = [];
    foreach (($_SESSION['flash'] ?? []) as $f) $flash[] = ($f['tag'] ?? '') . ': ' . ($f['text'] ?? '');
    $ins = 0; $st = ''; $joined = '';
    try {
        $joined = (string) ops_val("SELECT COALESCE(joined_at,'') FROM candidates WHERE id=?", [$cid]);
        $ins = (int) ops_val("SELECT COALESCE(inspector_id,0) FROM candidates WHERE id=?", [$cid]);
        if ($ins > 0) $st = (string) ops_val("SELECT COALESCE(status,'') FROM inspectors WHERE id=?", [$ins]);
    } catch (Throwable $e) {}
    echo json_encode(['cid' => $cid, 'joined' => $joined, 'inspector' => $ins,
                      'status' => $st, 'flash' => $flash]) . "\n";
});

$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['id'] = $cid;
$_POST = ($when === 'UNDO') ? ['undo' => '1'] : ['joined_on' => $when];
try { ops_dispatch('candidate-joined', 'POST'); } catch (Throwable $e) { echo json_encode(['threw' => $e->getMessage()]) . "\n"; }
