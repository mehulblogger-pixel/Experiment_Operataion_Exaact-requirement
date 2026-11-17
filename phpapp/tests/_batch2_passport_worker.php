<?php
// ============================================================================
//  A REAL separate process that opens ONE public passport page, /p/<token>,
//  exactly as index.php does before sign-in (connect_passport_route() renders a
//  standalone page and exits). Used by tests/test_batch2_marketplace_*.php.
//
//  It attaches to the SAME database as the parent (env) and never uses
//  tests/bootstrap.php. It creates nothing.
//
//    php tests/_batch2_passport_worker.php <token>
//
//  Prints one JSON line: {"status": <http code>, "html": "<page>"}.
// ============================================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'batch2-passport-worker';
$_SERVER['HTTP_HOST'] = 'localhost'; $_SERVER['REQUEST_METHOD'] = 'GET';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $mm);
foreach ($mm[1] as $rel) require_once $root . $rel;
$token = (string)($argv[1] ?? '');
$_SERVER['REQUEST_URI'] = '/p/' . $token;
register_shutdown_function(function () {
    $html = '';
    while (ob_get_level()) $html = (string)ob_get_clean() . $html;
    echo "\n" . json_encode(['status' => http_response_code(), 'html' => $html]) . "\n";
});
ob_start();
connect_passport_route($token);
