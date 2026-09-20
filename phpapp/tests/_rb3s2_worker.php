<?php
// ============================================================================
//  RB-3 Step 2 — a REAL separate process for the acknowledgement probes.
//
//  Attaches to the SAME database as the parent; deliberately does NOT use
//  tests/bootstrap.php, which drops every table on the MySQL path.
//
//    php tests/_rb3s2_worker.php <op> <candId> <token> <target-epoch-ms> <uid>
//
//  Ops
//    convert  — rcv_convert() carrying <token> as the acknowledgement
//    issue    — print an acknowledgement token for <candId> as user <uid>
//    match    — print what workforce_matches() makes of <candId>
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'rb3s2-worker';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$op = (string)($argv[1] ?? ''); $cid = (int)($argv[2] ?? 0); $tok = (string)($argv[3] ?? '');
$target = (float)($argv[4] ?? 0); $uid = (int)($argv[5] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }

//  Every one-time cost paid BEFORE the barrier: an idempotent migration firing
//  inside the first write costs milliseconds, and the window these processes
//  must collide in is microseconds.
try {
    db();
    if (function_exists('connect_identity_migrate')) connect_identity_migrate();
    if (function_exists('act_migrate')) act_migrate();
    if (function_exists('emp_code_migrate')) emp_code_migrate();
    if (function_exists('workforce_ack_secret')) workforce_ack_secret();   // create the key before the race
    $c = $cid ? ops_one("SELECT * FROM candidates WHERE id=?", [$cid]) : null;
    if ($c && function_exists('workforce_matches')) workforce_matches($c);
    if (function_exists('rcv_branch_for') && $cid) rcv_branch_for($cid, $uid);
    if (function_exists('connect_identity_admin_can')) connect_identity_admin_can();
} catch (Throwable $e) {}

if ($target > 1000000000) { while (microtime(true) * 1000 < $target) { } }

$out = ['op' => $op, 'ok' => false, 'code' => '', 'inspector_id' => 0, 'token' => '', 'strong' => 0, 'weak' => 0];
try {
    $cand = $cid ? ops_one("SELECT * FROM candidates WHERE id=?", [$cid]) : null;
    if ($op === 'convert') {
        $r = rcv_convert($cid, ['dup_ack' => $tok, 'actor_id' => $uid]);
        $out['ok'] = (bool)($r['ok'] ?? false); $out['code'] = (string)($r['code'] ?? '');
        $out['inspector_id'] = (int)($r['inspector_id'] ?? 0);
    } elseif ($op === 'issue') {
        $mm = $cand ? workforce_matches($cand) : [];
        $out['token'] = workforce_ack_issue($cid, $mm, $cand ?: [], $uid);
        $out['strong'] = count(workforce_strong_matches($mm));
        $out['ok'] = $out['token'] !== '';
    } elseif ($op === 'match') {
        $mm = $cand ? workforce_matches($cand) : [];
        $out['strong'] = count(workforce_strong_matches($mm));
        $out['weak']   = count($mm) - $out['strong'];
        $out['ok'] = true;
    }
} catch (Throwable $e) { $out['code'] = 'EX'; $out['msg'] = $e->getMessage(); }
echo json_encode($out) . "\n";
