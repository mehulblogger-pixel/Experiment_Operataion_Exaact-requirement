<?php
// ============================================================================
//  Router for PHP's built-in web server — used by the laptop launchers
//  (start.command on Mac/Linux, start.bat on Windows).
//
//  It serves a real file (a stylesheet, an image, a .php endpoint) as itself,
//  and sends everything else to the app's front controller, so the clean URLs
//  (/calls, /job?id=…) work exactly as they do on a normal web server.
//
//  Not used on hosting — Apache/Nginx/LiteSpeed route through .htaccess there.
// ============================================================================
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '/' && $path !== '' && is_file(__DIR__ . $path)) {
    return false;   // let the built-in server serve/execute the real file
}
require __DIR__ . '/index.php';
