<?php
// ============================================================================
//  A REAL separate process for the Phase 6 Batch 2 conversion probes.
//
//  Attaches to the SAME database as the parent; deliberately does NOT use
//  tests/bootstrap.php, which drops every table on the MySQL path.
//
//    php tests/_p6b2_worker.php <op> <a> <b> <target-epoch-ms> <uid>
//
//  Ops
//    route_convert  a=candidate            — the REAL /candidate-stage route,
//                                            ACCEPTED + make_inspector, forged POST
//    convert        a=candidate            — the conversion function directly
//    rawconvert     a=candidate b=name     — a raw INSERT + UPDATE, no guard at
//                                            all: the direct-writer attack
//    grouplink      a=candidate b=candidate— person_link_rows(), two processes
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'p6b2-worker';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$op = (string)($argv[1] ?? ''); $a = (int)($argv[2] ?? 0); $b = (string)($argv[3] ?? '');
$target = (float)($argv[4] ?? 0); $uid = (int)($argv[5] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }

//  Pay every one-time per-process cost BEFORE the barrier — an idempotent
//  migration firing inside the first write costs milliseconds, while the window
//  these processes must collide in is microseconds. Without this they queue
//  politely and the race never happens (the Phase 4 lesson, carried forward).
try {
    db();
    if (function_exists('connect_identity_migrate')) connect_identity_migrate();
    if (function_exists('act_migrate')) act_migrate();
    if (function_exists('person_migrate')) person_migrate();
    $c = ops_one("SELECT * FROM candidates WHERE id=?", [$a]);
    if ($c && function_exists('rexec_block_reason') && !empty($c['requisition_id'])) rexec_block_reason((int)$c['requisition_id']);
    if (function_exists('connect_identity_admin_can')) connect_identity_admin_can();
    if (function_exists('rcv_branch_for')) rcv_branch_for($a);
    ops_val("SELECT COUNT(*) FROM inspectors");
} catch (Throwable $e) {}

if ($target > 1000000000) { while (microtime(true) * 1000 < $target) { } }

$out = ['op' => $op, 'ok' => false, 'code' => '', 'msg' => '', 'inspector_id' => 0];
try {
    if ($op === 'route_convert') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'p6b2'; $_POST['_csrf'] = 'p6b2';
        $_GET['id'] = $a; $_POST['id'] = $a;
        $_POST['to_stage'] = 'ACCEPTED'; $_POST['make_inspector'] = '1';
        //  The route redirect()s and exits, so the verdict is printed FIRST and
        //  the parent reads the DATABASE afterwards. Nothing here is believed.
        $out['code'] = 'DISPATCHED'; $out['ok'] = true;
        echo json_encode($out) . "\n";
        ops_dispatch('candidate-stage', 'POST');
        exit;
    } elseif ($op === 'convert') {
        if (!function_exists('rcv_convert')) { $out['code'] = 'ABSENT'; }
        else {
            $r = rcv_convert($a, []);
            $out['ok'] = (bool)($r['ok'] ?? false); $out['code'] = (string)($r['code'] ?? '');
            $out['msg'] = (string)($r['message'] ?? ''); $out['inspector_id'] = (int)($r['inspector_id'] ?? 0);
            $out['linked'] = (string)($r['identity'] ?? '');
        }
    } elseif ($op === 'rawconvert') {
        //  No guard runs at all. Only a database rule can stop this.
        db()->prepare("INSERT INTO inspectors (name,status,created_at) VALUES (?, 'ACTIVE', ?)")->execute([$b ?: 'Raw', date('c')]);
        $ins = (int)db()->lastInsertId();
        db()->prepare("UPDATE candidates SET inspector_id=? WHERE id=?")->execute([$ins, $a]);
        $out['ok'] = true; $out['inspector_id'] = $ins;
    } elseif ($op === 'grouplink') {
        $why = function_exists('person_link_rows') ? person_link_rows([$a, (int)$b]) : 'absent';
        $out['ok'] = ($why === ''); $out['msg'] = (string)$why;
    }
} catch (Throwable $e) { $out['code'] = 'EX'; $out['msg'] = $e->getMessage(); }
echo json_encode($out) . "\n";
