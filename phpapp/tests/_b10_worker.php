<?php
// A separate process that renders a B10 route through the PRODUCTION view(),
// as the /my-work regression does — three other test files install a stub
// view(), and a source assertion is not enough for B10 (§41).
//   php tests/_b10_worker.php <mode> <role> [arg]
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['HTTP_USER_AGENT']='b10-worker';
$_SERVER['REQUEST_URI']='/'; $_SERVER['HTTP_HOST']='localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;
$i = strpos($idx, 'function view($name, $vars = []) {');
$fn = substr($idx, $i, strpos($idx, "\n}\n", $i) - $i + 3);
foreach ((array) glob($root . '/.viewprobe_*.php') as $st) @unlink($st);
$tmp = $root . '/.viewprobe_' . getmypid() . '.php';
file_put_contents($tmp, "<?php\n" . $fn);
register_shutdown_function(function () use ($tmp) { @unlink($tmp); });
require $tmp; @unlink($tmp);

$mode = (string)($argv[1] ?? ''); $role = (string)($argv[2] ?? ''); $arg = (string)($argv[3] ?? '');
$out = ['mode'=>$mode, 'role'=>$role, 'ok'=>false];
try {
    $un = 'b10w_' . strtolower($role);
    db()->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    db()->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, is_superuser)
                   VALUES (?,?,?,?,1,?)")->execute([$un, 'Probe', ucfirst(strtolower($role)), $role, $role === 'ADMIN' ? 1 : 0]);
    $_SESSION['uid'] = (int) db()->lastInsertId();
    current_user(true); if (function_exists('ua')) ua(true);
    $out['mayAllocate'] = function_exists('is_coordinator_level') ? (bool) is_coordinator_level() : null;
    // ARM the Operations card grid. A throwaway database has no work in it, so
    // the pending-scheduling list is empty for EVERY role and an assertion about
    // who gets an Allocate button would pass vacuously. One open call, in the
    // viewer's own office scope, with no scheduled job — exactly what
    // tosrm_pending_scheduling() looks for.
    if ($mode === 'operations') {
        $off = (int) ops_val("SELECT id FROM offices ORDER BY id LIMIT 1");
        if ($off && !ops_val("SELECT id FROM calls WHERE call_code='B10-ARM'")) {
            db()->prepare("INSERT INTO calls (call_code, status, executing_office_id, ibo_office_id,
                                              inspection_required_date, created_at)
                           VALUES ('B10-ARM','OPEN',?,?,?,?)")
                ->execute([$off, $off, date('Y-m-d', strtotime('+3 days')), date('c')]);
        }
        $out['armed'] = (int) count(function_exists('tosrm_pending_scheduling')
            ? tosrm_pending_scheduling(function_exists('tosrm_office_scope') ? tosrm_office_scope() : 'ALL') : []);
    }
    ob_start();
    if ($mode === 'my-jobs')          { $_SERVER['REQUEST_URI']='/my-jobs'; ops_my_jobs(); }
    elseif ($mode === 'operations')   { $_SERVER['REQUEST_URI']='/operations'; ops_operations_home('GET'); }
    elseif ($mode === 'job-new')      { $_SERVER['REQUEST_URI']='/job-new'; $_GET['call']=(int)$arg; ops_jobs('job-new','GET'); }
    else throw new RuntimeException('unknown mode');
    $html = (string) ob_get_clean();
    db()->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $out['ok'] = true;
    $out['len'] = strlen($html);
    $out['allocateLinks'] = preg_match_all('#href="/job-new\?call=#', $html);
    $out['openLinks']     = preg_match_all('#>Open</a>#', $html);
    $out['isNotice']      = strpos($html, 'access_notice') !== false
                         || (strpos($html, 'isn’t available to your role') !== false)
                         || (strpos($html, 'not linked to a') !== false);
    $out['hasWayOut']     = strpos($html, 'href="/my-work"') !== false && strpos($html, 'href="/"') !== false;
    $out['bounced']       = strpos($html, 'could not be loaded') !== false;
    $out['html']          = $html;
} catch (Throwable $e) { $out['error'] = get_class($e) . ': ' . $e->getMessage(); }
echo json_encode($out), "\n";
