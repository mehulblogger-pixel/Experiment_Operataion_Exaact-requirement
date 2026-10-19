<?php
// One handler call in its own process: php act.php <uid> <route> <GET|POST> '<json GET>' '<json POST>'
// Prints the outcome: the view rendered (if any), flash messages, and whether it redirected.
require __DIR__ . '/lib_boot.php';
[$_, $uid, $route, $method, $g, $p] = array_pad($argv, 6, '{}');
$_GET = json_decode($g ?: '{}', true) ?: []; $_POST = json_decode($p ?: '{}', true) ?: [];
$_SERVER['REQUEST_METHOD'] = $method;
as_user($uid);
register_shutdown_function(function () {
    $out = ['view' => $GLOBALS['__view'] ?? null, 'flash' => $_SESSION['_flash'] ?? $_SESSION['flash'] ?? null,
            'headers' => function_exists('xdebug_get_headers') ? xdebug_get_headers() : null, 'code' => http_response_code()];
    if (!empty($GLOBALS['__view_vars']['error'])) $out['form_error'] = $GLOBALS['__view_vars']['error'];
    if (!empty($GLOBALS['__view_vars']['form_err'])) $out['form_error'] = $GLOBALS['__view_vars']['form_err'];
    if (isset($GLOBALS['__dump'])) $out['dump'] = ($GLOBALS['__dump'])();
    echo "RESULT " . json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "\n";
});
ob_start();
$r = ops_dispatch($route, $method);
ob_end_clean();
echo "RETURNED " . var_export($r, true) . "\n";
