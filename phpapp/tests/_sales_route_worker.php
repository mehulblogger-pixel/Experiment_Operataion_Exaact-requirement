<?php
// ============================================================================
//  A REAL separate process that drives ONE Sales route as ONE user, through the
//  production dispatcher (module gate, verb gate, branch scope and the handler's
//  own checks) and the production view().
//
//  Why a process: every write route ends in redirect(), which exits. A separate
//  process lets the test see what the handler did and then carry on — the same
//  reason tests/_b10_worker.php exists. It attaches to the SAME database as the
//  parent (env) and never uses tests/bootstrap.php.
//
//    php tests/_sales_route_worker.php <uid> <route> <GET|POST> '<get json>' '<post json>' ['<needles json>'] ['<session json>']
//
//  Prints one JSON line: {"flash":[{text,tag}], "done":"redirect|return|threw", "error":...,
//  "has":{needle:true|false}} — "has" says whether each needle appears in the page drawn.
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'sales-route-worker';
$_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;
$i = strpos($idx, 'function view($name, $vars = []) {');
$fn = substr($idx, $i, strpos($idx, "\n}\n", $i) - $i + 3);
$tmp = $root . '/.viewprobe_' . getmypid() . '.php';
file_put_contents($tmp, "<?php\n" . $fn);
require $tmp; @unlink($tmp);

[$uid, $route, $method] = [(int)($argv[1] ?? 0), (string)($argv[2] ?? ''), strtoupper((string)($argv[3] ?? 'GET'))];
$_GET  = json_decode((string)($argv[4] ?? '{}'), true) ?: [];
$_POST = json_decode((string)($argv[5] ?? '{}'), true) ?: [];
$_REQUEST = $_GET + $_POST;
$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['REQUEST_URI'] = '/' . $route . ($_GET ? '?' . http_build_query($_GET) : '');
// Optional 7th argument: session values to start from — what an earlier request
// in the same browser session left behind (e.g. a used one-time form key).
foreach ((json_decode((string)($argv[7] ?? '{}'), true) ?: []) as $sk => $sv) $_SESSION[$sk] = $sv;
$_SESSION['uid'] = $uid;
current_user(true); if (function_exists('ua')) ua(true);

$GLOBALS['__sales_worker_done'] = 'redirect';
$needles = json_decode((string)($argv[6] ?? '[]'), true) ?: [];
register_shutdown_function(function () use ($needles) {
    $html = '';
    while (ob_get_level()) $html = (string) ob_get_clean() . $html;
    $has = [];
    foreach ($needles as $n) $has[$n] = strpos($html, (string) $n) !== false;
    $err = error_get_last();
    echo json_encode(['flash' => array_values($_SESSION['flash'] ?? []), 'done' => $GLOBALS['__sales_worker_done'],
                      'error' => $GLOBALS['__sales_worker_error'] ?? (($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) ? $err['message'] : null),
                      'has' => $has, 'len' => strlen($html)]) . "\n";
});
ob_start();
try { ops_dispatch($route, $method); $GLOBALS['__sales_worker_done'] = 'return'; }
catch (Throwable $e) { $GLOBALS['__sales_worker_done'] = 'threw'; $GLOBALS['__sales_worker_error'] = $e->getMessage(); }
