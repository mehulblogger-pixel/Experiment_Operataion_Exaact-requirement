<?php
// Command line only. Runs a chosen list of test files in ONE process (the way the
// full suite does), so a leak from one file into the next can be reproduced.
//   php tests/_st1_run_files.php test_a.php test_b.php ...
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require __DIR__ . '/lib.php';
require __DIR__ . '/bootstrap.php';
foreach (array_slice($argv, 1) as $f) { echo "\n### $f ###\n"; require __DIR__ . '/' . basename($f); }
$g = $GLOBALS['__t'];
echo "\n" . str_repeat('-', 52) . "\nRESULT: {$g['pass']} passed, {$g['fail']} failed\n";
if ($g['fail']) { echo "FAILURES:\n"; foreach ($g['fails'] as $x) echo "  - $x\n"; }
exit($g['fail'] ? 1 : 0);
