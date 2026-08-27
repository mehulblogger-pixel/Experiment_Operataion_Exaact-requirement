<?php
// Test runner. Boots the app once against a throwaway database, then runs every
// tests/test_*.php file and prints a pass/fail summary. Exit code is non-zero on
// any failure, so CI (or a quick manual run) can trust it.
//
//   php tests/run.php                 — every test
//   php tests/run.php finance_truth   — only test files whose name contains "finance_truth"
require __DIR__ . '/lib.php';
require __DIR__ . '/bootstrap.php';

// Optional substring filter (argv[1]) so a single control can be verified on its own. No argument =
// the full suite, unchanged. A filter that matches nothing is reported rather than passing silently.
$filter = $argv[1] ?? '';
$files = glob(__DIR__ . '/test_*.php');
if ($filter !== '') $files = array_values(array_filter($files, fn($f) => strpos(basename($f), $filter) !== false));
if ($filter !== '' && !$files) { echo "No test file matches \"$filter\".\n"; exit(2); }

foreach ($files as $f) {
    echo "\n### " . basename($f) . " ###\n";
    require $f;
}

$g = $GLOBALS['__t'];
echo "\n" . str_repeat('-', 52) . "\n";
echo "RESULT: {$g['pass']} passed, {$g['fail']} failed\n";
if ($g['fail']) { echo "FAILURES:\n"; foreach ($g['fails'] as $x) echo "  - $x\n"; }
exit($g['fail'] ? 1 : 0);
