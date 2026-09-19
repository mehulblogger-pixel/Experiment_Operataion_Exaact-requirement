<?php
// A REAL separate process for the Phase 6 Batch 1 security and concurrency
// probes. Attaches to the SAME database as the parent; deliberately does NOT
// use tests/bootstrap.php, which drops every table on the MySQL path.
//
//   php tests/_p6_worker.php <op> <a> <b> <c> <target-epoch-ms> <uid>
//
// Ops:
//   link         a=professional b=inspector      — the inspector-axis writer
//   candlink     a=candidate    b=professional   — the candidate-axis writer
//   unlink       a=link_id      b=expect-candidate (0 = none)
//   rawlink      a=professional b=inspector      — RAW SQL, bypassing every PHP
//                                                  guard: the direct uniqueness attack
//   rawcand      a=candidate    b=professional   — ditto, candidate axis
//   route_unlink a=candidate    b=posted link_id — the real route, forged POST
//   route_link   a=candidate    b=posted pro_id  — the real route
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'p6-worker';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$op = (string)($argv[1] ?? ''); $a = (int)($argv[2] ?? 0); $b = (int)($argv[3] ?? 0);
$c  = (string)($argv[4] ?? ''); $target = (float)($argv[5] ?? 0); $uid = (int)($argv[6] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }

// Pay every one-time per-process cost BEFORE the barrier, exactly as the Phase 4
// worker does: an idempotent migration firing inside the first write costs
// milliseconds, while the window these processes must collide in is microseconds.
// Without this they queue politely and the race never happens.
try {
    db();
    if (function_exists('connect_identity_migrate')) connect_identity_migrate();
    if (function_exists('act_migrate')) act_migrate();
    if (function_exists('connect_identity_of_professional')) connect_identity_of_professional($a);
    if (function_exists('connect_identity_of_candidate')) connect_identity_of_candidate($a);
    if (function_exists('connect_identity_admin_can')) connect_identity_admin_can();
    ops_val("SELECT COUNT(*) FROM cx_identity_link");
} catch (Throwable $e) {}

// Synchronise on a wall-clock instant, never on a sleep (Phase 4's rule).
if ($target > 1000000000) { while (microtime(true) * 1000 < $target) { } }

$out = ['op' => $op, 'ok' => false, 'msg' => '', 'id' => 0];
try {
    if ($op === 'link') {
        $r = connect_identity_link_create($a, $b, 'manual', 'worker');
        $out['ok'] = (bool)$r[0]; $out['msg'] = (string)$r[1]; $out['id'] = (int)($r[2] ?? 0);
    } elseif ($op === 'candlink') {
        $r = connect_identity_candidate_link_create($a, $b, 'manual', 'worker');
        $out['ok'] = (bool)$r[0]; $out['msg'] = (string)$r[1]; $out['id'] = (int)($r[2] ?? 0);
    } elseif ($op === 'unlink') {
        $r = $b > 0 && (new ReflectionFunction('connect_identity_unlink'))->getNumberOfParameters() >= 3
           ? connect_identity_unlink($a, 'worker', ['candidate_id' => $b])
           : connect_identity_unlink($a, 'worker');
        $out['ok'] = (bool)$r[0]; $out['msg'] = (string)$r[1];
    } elseif ($op === 'rawlink' || $op === 'rawcand') {
        // The direct-SQL attack: no PHP guard runs at all. Only a database
        // constraint can stop this, which is the whole point of U1/U2/U3.
        $cand = $op === 'rawcand' ? $a : 0;
        $pro  = $op === 'rawcand' ? $b : $a;
        $insp = $op === 'rawcand' ? 0  : $b;
        $cols = ['professional_id', 'inspector_id', 'candidate_id', 'method', 'status', 'linked_by', 'linked_at'];
        $vals = [$pro, $insp, $cand, 'raw', 'LINKED', 'attacker', date('c')];
        foreach (['uq_pro_insp' => ($cand ? null : ($pro ?: null)),
                  'uq_insp'     => ($cand ? null : ($insp ?: null)),
                  'uq_cand'     => ($cand ?: null)] as $k => $v) {
            if (in_array($k, array_column(ops_all(t_driver_sql()), 'name'), true)) { $cols[] = $k; $vals[] = $v; }
        }
        db()->prepare("INSERT INTO cx_identity_link (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute($vals);
        $out['ok'] = true; $out['id'] = (int)db()->lastInsertId();
    } elseif ($op === 'route_unlink' || $op === 'route_link') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf'] = 'p6test'; $_POST['_csrf'] = 'p6test';
        $_GET['id'] = $a; $_POST['id'] = $a;
        if ($op === 'route_unlink') $_POST['link_id'] = $b; else $_POST['pro_id'] = $b;
        // The route redirect()s and exits, so the verdict is printed first and
        // the parent reads the DATABASE afterwards. Nothing here is believed.
        $out['ok'] = true; $out['msg'] = 'DISPATCHED';
        echo json_encode($out) . "\n";
        ops_dispatch($op === 'route_unlink' ? 'candidate-unlink-pro' : 'candidate-link-pro', 'POST');
        exit;
    }
} catch (Throwable $e) { $out['msg'] = 'EX:' . $e->getMessage(); }
echo json_encode($out) . "\n";

function t_driver_sql() {
    return (string)db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? "PRAGMA table_info(cx_identity_link)"
        : "SELECT column_name AS name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='cx_identity_link'";
}
