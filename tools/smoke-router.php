<?php
// Dev-server router so tools/smoke.js can drive the app locally.
//
//   cp data.sqlite /tmp/smoke.sqlite   # a throwaway copy — never the real DB
//   SQLITE_PATH=/tmp/smoke.sqlite DB_DRIVER=sqlite \
//     php -S 127.0.0.1:8801 tools/smoke-router.php &
//   node tools/smoke.js http://127.0.0.1:8801 admin <password>
//
// Serves real static files; routes everything else through the front controller.
$root = dirname(__DIR__);
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$f = $root . $p;
if ($p !== '/' && $p !== '' && is_file($f) && strpos(realpath($f), $root) === 0) return false;
require $root . '/index.php';
