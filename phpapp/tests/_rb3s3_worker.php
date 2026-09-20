<?php
// ============================================================================
//  RB-3 Step 3 — a REAL separate process driving the REAL acceptance route.
//
//    php tests/_rb3s3_worker.php <op> <candId> <extra> <target-epoch-ms> <uid>
//
//  Ops
//    accept   — POST /candidate-stage, to_stage=ACCEPTED, make_inspector=1
//    move     — POST /candidate-stage, to_stage=<extra>  (a NON-joining move)
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'rb3s3-worker';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$op = (string)($argv[1] ?? ''); $cid = (int)($argv[2] ?? 0); $extra = (string)($argv[3] ?? '');
$target = (float)($argv[4] ?? 0); $uid = (int)($argv[5] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }

//  Everything one-time paid BEFORE the barrier. The migrations are warmed here
//  DELIBERATELY — the route warms them too, and this is the ordinary case. The
//  COLD case, where the route must warm them itself before opening the
//  transaction, is what op 'acceptcold' below leaves untouched.
try {
    db();
    if ($op !== 'acceptcold' && function_exists('rcv_prewarm_migrations')) rcv_prewarm_migrations();
    $c = $cid ? ops_one("SELECT * FROM candidates WHERE id=?", [$cid]) : null;
    if ($c && function_exists('workforce_matches')) workforce_matches($c);
    if ($c && function_exists('rcv_branch_for')) rcv_branch_for($cid, $uid);
    if ($c && !empty($c['requisition_id']) && function_exists('rexec_block_reason'))
        rexec_block_reason((int)$c['requisition_id'], 'JOIN', $cid);
    ops_val("SELECT COUNT(*) FROM inspectors");
} catch (Throwable $e) {}

if ($target > 1000000000) { while (microtime(true) * 1000 < $target) { } }

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['csrf'] = 'rb3s3'; $_POST['_csrf'] = 'rb3s3';
$_GET['id'] = $cid; $_POST['id'] = $cid;
$_POST['to_stage'] = ($op === 'move') ? $extra : 'ACCEPTED';
if ($op === 'accept' || $op === 'acceptcold') $_POST['make_inspector'] = '1';
if ($extra !== '' && $op !== 'move') $_POST['dup_ack'] = $extra;

//  The route redirect()s and exits, so the verdict is printed FIRST and the
//  parent reads the DATABASE afterwards. Nothing here is believed.
echo json_encode(['op' => $op, 'cand' => $cid, 'code' => 'DISPATCHED']) . "\n";
try { ops_dispatch('candidate-stage', 'POST'); } catch (Throwable $e) {}
