<?php
// ============================================================================
//  The test suite is never reachable from a browser. It boots the app and runs
//  thousands of checks; opened from the web it would load the server and write
//  test files. Closed twice: the web rules refuse the folder, and the runner
//  refuses anything but the command line.
// ============================================================================
t_section('the tests folder cannot be opened from the web');
$root = dirname(__DIR__);
$ht = (string) file_get_contents($root . '/.htaccess');
t_ok((bool) preg_match('#RewriteRule \^\((?:[a-z]+\|)*tests(?:\|[a-z]+)*\)/ - \[F\]#', $ht), '.htaccess refuses /tests/ like /lib/, /views/, /tools/ and /docs/');
foreach (['run.php', 'bootstrap.php'] as $f) {
    $src = (string) file_get_contents(__DIR__ . '/' . $f);
    t_ok(strpos($src, "if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }") !== false
        && strpos($src, 'PHP_SAPI') < 200, 'tests/' . $f . ' stops at once unless run from the command line');
}
if (is_executable('/usr/bin/php-cgi') || trim((string) shell_exec('command -v php-cgi')) !== '') {
    $out = (string) shell_exec('REDIRECT_STATUS=1 SCRIPT_FILENAME=' . escapeshellarg(__DIR__ . '/run.php') . ' php-cgi 2>&1');
    t_ok(strpos($out, 'Forbidden') !== false && strpos($out, '###') === false, 'run as a web request, the runner answers "Forbidden" and runs nothing');
}
