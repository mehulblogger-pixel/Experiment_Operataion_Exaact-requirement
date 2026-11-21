<?php
// A REAL separate process that drives ONE route as ONE user and prints the page
// it drew — for test_whocan_screen.php ("Who can do what", batch B). Attaches to
// the SAME database as the parent (env), like _bbv_worker.php, and renders the
// screen's own template (not the layout) so the form can be read back.
//
//   php tests/_whocan_worker.php <route> <GET|POST> <uid> '<get json>' '<post json>'
//
// Prints one JSON line: code, flash, view, view_ok, view_err, done, html.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$route = (string) ($argv[1] ?? ''); $method = strtoupper((string) ($argv[2] ?? 'GET'));
$uid = (int) ($argv[3] ?? 0);
$_GET  = json_decode((string) ($argv[4] ?? '{}'), true) ?: [];
$_POST = json_decode((string) ($argv[5] ?? '{}'), true) ?: [];
$_REQUEST = $_GET + $_POST;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'whocan-worker';
$_SERVER['REQUEST_URI'] = '/' . $route . ($_GET ? '?' . http_build_query($_GET) : ''); $_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = $method;
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;
if (!function_exists('view')) {
    function view($__name, $__vars = []) {
        $GLOBALS['__wc_view'] = (string) $__name;
        extract($__vars); $cur = $cur ?? (string) ($GLOBALS['__wc_route'] ?? '');
        try { include dirname(__DIR__) . '/views/' . $__name . '.php'; $GLOBALS['__wc_view_ok'] = true; }
        catch (Throwable $e) { $GLOBALS['__wc_view_err'] = $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine(); }
    }
}
$GLOBALS['__wc_route'] = $route;
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }
$GLOBALS['__wc_done'] = false;
ob_start();
register_shutdown_function(function () {
    $out = (string) ob_get_contents(); while (ob_get_level()) ob_end_clean();
    $flash = [];
    foreach (($_SESSION['flash'] ?? []) as $f) $flash[] = ($f['tag'] ?? '') . ': ' . ($f['text'] ?? '');
    $err = error_get_last();
    echo json_encode(['code' => (int) http_response_code(), 'flash' => $flash, 'done' => $GLOBALS['__wc_done'],
                      'view' => $GLOBALS['__wc_view'] ?? '', 'view_ok' => !empty($GLOBALS['__wc_view_ok']),
                      'view_err' => $GLOBALS['__wc_view_err'] ?? (($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) ? $err['message'] : ''),
                      'html' => $out]) . "\n";
});
try { $ok = ops_dispatch($route, $method); $GLOBALS['__wc_done'] = (bool) $ok; }
catch (Throwable $e) { $GLOBALS['__wc_done'] = 'threw: ' . $e->getMessage(); }
