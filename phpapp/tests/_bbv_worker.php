<?php
// A REAL separate process that drives a REAL voucher route (branch batch R5).
// Attaches to the SAME database as the parent (it does not use bootstrap.php,
// which drops every table on the MySQL path) — the pattern of _g5_worker.php.
//
//   php tests/_bbv_worker.php <route> <GET|POST> <actingUserId> <voucherId> [post-json]
//
// Why a second process: the route is reached through the module gate and ends
// in redirect()/exit, so it cannot be called in-process without ending the run.
// Prints one JSON line: the HTTP code, the flash messages, whether the screen
// rendered, and the voucher's status afterwards.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$route = (string) ($argv[1] ?? ''); $method = strtoupper((string) ($argv[2] ?? 'GET'));
$uid = (int) ($argv[3] ?? 0); $vid = (int) ($argv[4] ?? 0);
$post = json_decode((string) ($argv[5] ?? '{}'), true) ?: [];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'bbv-worker';
$_SERVER['REQUEST_URI'] = '/' . $route; $_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = $method;
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;
// index.php's view() wraps the page in the layout; here the screen's own
// template is rendered (so a template error still shows) and its name recorded.
if (!function_exists('view')) {
    function view($__name, $__vars = []) {
        $GLOBALS['__bbv_view'] = (string) $__name;
        extract($__vars); $cur = $cur ?? (string) ($GLOBALS['__bbv_route'] ?? '');
        try { include dirname(__DIR__) . '/views/' . $__name . '.php'; $GLOBALS['__bbv_view_ok'] = true; }
        catch (Throwable $e) { $GLOBALS['__bbv_view_err'] = $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine(); }
    }
}
$GLOBALS['__bbv_route'] = $route;
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }
$GLOBALS['__bbv_done'] = false;
ob_start();
register_shutdown_function(function () use ($vid) {
    $out = (string) ob_get_contents(); while (ob_get_level()) ob_end_clean();
    $flash = [];
    foreach (($_SESSION['flash'] ?? []) as $f) $flash[] = ($f['tag'] ?? '') . ': ' . ($f['text'] ?? '');
    $st = '';
    try { $st = (string) ops_val("SELECT status FROM vouchers WHERE id=?", [$vid]); } catch (Throwable $e) {}
    echo json_encode(['code' => (int) http_response_code(), 'flash' => $flash, 'status' => $st,
                      'restricted' => stripos($out, 'restricted') !== false || stripos($out, 'have permission') !== false,
                      'rendered' => $GLOBALS['__bbv_done'], 'view' => $GLOBALS['__bbv_view'] ?? '',
                      'view_ok' => !empty($GLOBALS['__bbv_view_ok']), 'view_err' => $GLOBALS['__bbv_view_err'] ?? '',
                      'out' => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(
                          preg_replace('#<(style|script)\b.*?</\1>#is', ' ', $out)), ENT_QUOTES, 'UTF-8'))),
                      'len' => strlen($out)]
                     // BBV_RAW=1: also the page's own HTML, for checks on links and attributes.
                     + (getenv('BBV_RAW') ? ['html' => $out] : [])) . "\n";
});
// Optional query-string values (argv[6], JSON) — e.g. ins/month for find-or-create.
$_GET = (json_decode((string) ($argv[6] ?? '{}'), true) ?: []) + ['id' => $vid];
$_POST = $post;
try { $ok = ops_dispatch($route, $method); $GLOBALS['__bbv_done'] = (bool) $ok; }
catch (Throwable $e) { $GLOBALS['__bbv_done'] = 'threw: ' . $e->getMessage(); }
