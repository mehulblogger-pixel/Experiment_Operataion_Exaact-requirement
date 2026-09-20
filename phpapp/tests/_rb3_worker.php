<?php
// ============================================================================
//  A REAL separate process for the RB-3 employee-number probes.
//
//  Attaches to the SAME database as the parent; deliberately does NOT use
//  tests/bootstrap.php, which drops every table on the MySQL path.
//
//    php tests/_rb3_worker.php <op> <a> <b> <target-epoch-ms> <uid>
//
//  Ops
//    convert   a=candidate           — the real conversion, which claims a number
//    claim     b=name                — team_member_create(), the other claiming path
//    rawemp    b=emp_code            — a raw INSERT carrying that code, NO guard at
//                                      all. Only a database rule can stop it.
//    guard     —                     — install the employee-number rule. Several of
//                                      these at once is the concurrent-boot case.
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'rb3-worker';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$op = (string)($argv[1] ?? ''); $a = (int)($argv[2] ?? 0); $b = (string)($argv[3] ?? '');
$target = (float)($argv[4] ?? 0); $uid = (int)($argv[5] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }

//  Pay every one-time per-process cost BEFORE the barrier. An idempotent
//  migration firing inside the first write costs milliseconds, while the window
//  these processes must collide in is microseconds — without this they queue
//  politely and the race never happens.
//
//  'guard' is DELIBERATELY EXCLUDED from the warm-up: warming it here would set
//  its epoch marker and every racing process would then do nothing at all, and
//  the test would measure a queue of no-ops while reporting a passed race. That
//  exact trap produced eleven defective instruments in the Batch 3 corrective.
try {
    db();
    if (function_exists('connect_identity_migrate')) connect_identity_migrate();
    if (function_exists('act_migrate')) act_migrate();
    if ($op !== 'guard' && function_exists('emp_code_migrate')) emp_code_migrate();
    if ($op !== 'guard' && function_exists('next_emp_code')) next_emp_code('ASSET');
    $c = $a ? ops_one("SELECT * FROM candidates WHERE id=?", [$a]) : null;
    if ($c && function_exists('rexec_block_reason') && !empty($c['requisition_id'])) rexec_block_reason((int)$c['requisition_id']);
    if (function_exists('connect_identity_admin_can')) connect_identity_admin_can();
    if (function_exists('rcv_branch_for') && $a) rcv_branch_for($a);
    ops_val("SELECT COUNT(*) FROM inspectors");
} catch (Throwable $e) {}

if ($target > 1000000000) { while (microtime(true) * 1000 < $target) { } }

$out = ['op' => $op, 'ok' => false, 'code' => '', 'msg' => '', 'inspector_id' => 0, 'emp' => ''];
try {
    if ($op === 'convert') {
        $r = rcv_convert($a, []);
        $out['ok'] = (bool)($r['ok'] ?? false); $out['code'] = (string)($r['code'] ?? '');
        $out['msg'] = (string)($r['message'] ?? ''); $out['inspector_id'] = (int)($r['inspector_id'] ?? 0);
        if ($out['inspector_id'] > 0)
            $out['emp'] = (string)ops_val("SELECT emp_code FROM inspectors WHERE id=?", [$out['inspector_id']]);
    } elseif ($op === 'claim') {
        $id = (int)team_member_create($b ?: 'RB3 Claimer', 'FIELD', null, '');
        $out['ok'] = $id > 0; $out['inspector_id'] = $id;
        if ($id > 0) $out['emp'] = (string)ops_val("SELECT emp_code FROM inspectors WHERE id=?", [$id]);
    } elseif ($op === 'rawemp') {
        //  No application guard runs. Only the database can refuse this.
        db()->prepare("INSERT INTO inspectors (name,emp_code,status,created_at) VALUES (?,?, 'ACTIVE', ?)")
            ->execute(['RB3 Raw', $b, date('c')]);
        $out['ok'] = true; $out['inspector_id'] = (int)db()->lastInsertId(); $out['emp'] = $b;
    } elseif ($op === 'guard') {
        //  Positive observation: say whether this process really did the work,
        //  so "all three succeeded" cannot be three no-ops wearing a pass.
        $before = in_array('ux_inspectors_emp_code', table_index_names('inspectors'), true);
        $r = ensure_unique_generated_index('inspectors', EMP_CODE_KEY_COL, EMP_CODE_KEY_EXPR, EMP_CODE_KEY_IX);
        $out['ok'] = ($r === 'OK'); $out['code'] = (string)$r;
        $out['msg'] = $before ? 'index-was-already-there' : 'index-was-absent-before-me';
    }
} catch (Throwable $e) { $out['code'] = 'EX'; $out['msg'] = $e->getMessage(); }
echo json_encode($out) . "\n";
