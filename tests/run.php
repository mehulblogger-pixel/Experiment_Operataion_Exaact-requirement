<?php
// Test runner. Boots the app once against a throwaway database, then runs every
// tests/test_*.php file and prints a pass/fail summary. Exit code is non-zero on
// any failure, so CI (or a quick manual run) can trust it.
//
//   php tests/run.php
require __DIR__ . '/lib.php';
require __DIR__ . '/bootstrap.php';

foreach (glob(__DIR__ . '/test_*.php') as $f) {
    echo "\n### " . basename($f) . " ###\n";
    require $f;
}

$g = $GLOBALS['__t'];
echo "\n" . str_repeat('-', 52) . "\n";
echo "RESULT: {$g['pass']} passed, {$g['fail']} failed\n";
if ($g['fail']) { echo "FAILURES:\n"; foreach ($g['fails'] as $x) echo "  - $x\n"; }
exit($g['fail'] ? 1 : 0);
