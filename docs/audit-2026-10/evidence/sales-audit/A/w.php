<?php
// Worker: dispatch ONE real route in its own process (redirect() exits), against
// the parent's throwaway SQLite DB. Prints one JSON line at shutdown.
//   php w.php <sqlite-path> <uid> <route> <method> <get-json> <post-json>
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = '/home/user/Experiment_Operataion_Exaact-requirement/phpapp';
putenv('DB_DRIVER=sqlite'); putenv('SQLITE_PATH=' . $argv[1]);
$_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['HTTP_USER_AGENT']='sales-a-worker';
$_SERVER['REQUEST_URI']='/' . $argv[3]; $_SERVER['HTTP_HOST']='localhost';
$_SERVER['REQUEST_METHOD'] = $argv[4];
$_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \\. '(/lib/[a-z0-9_]+\\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;
// index.php's view() is not loaded by the lib list; record which view a handler chose and any error it carried.
if (!function_exists('view')) { function view($name, $vars = []) { $GLOBALS['__view'] = $name . (isset($vars['error']) && $vars['error'] !== '' ? ' ERROR=' . $vars['error'] : (isset($vars['form_err']) && $vars['form_err'] !== '' ? ' ERROR=' . $vars['form_err'] : '')); } }
$GLOBALS['__view'] = '';
$uid = (int)$argv[2];
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); if (function_exists('ua')) ua(true); }
$_GET  = json_decode($argv[5] ?: '{}', true) ?: [];
$_POST = json_decode($argv[6] ?: '{}', true) ?: [];
$_REQUEST = array_merge($_GET, $_POST);
$GLOBALS['__exc'] = '';
register_shutdown_function(function () {
    while (ob_get_level() > 0) ob_end_clean();
    $err = error_get_last();
    echo "\n@@RESULT@@" . json_encode([
        'flash' => array_map(fn($f) => $f['tag'] . ': ' . mb_substr($f['text'], 0, 300), $_SESSION['flash'] ?? []),
        'exc' => $GLOBALS['__exc'], 'view' => $GLOBALS['__view'],
        'fatal' => ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) ? $err['message'] : '',
        'code' => http_response_code(),
    ]) . "\n";
});
// optional wall-clock barrier (epoch ms) so two workers enter together
if (($b = (float)getenv('BARRIER')) > 0) { db(); while (microtime(true) * 1000 < $b) {} }
ob_start();
try { ops_dispatch($argv[3], $argv[4]); }
catch (Throwable $e) { $GLOBALS['__exc'] = get_class($e) . ': ' . $e->getMessage(); }
