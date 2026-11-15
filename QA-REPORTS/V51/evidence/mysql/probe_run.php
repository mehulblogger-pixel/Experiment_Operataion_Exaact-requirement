<?php
// A12-M probe runner: boots the V51.1 baseline test harness, then runs probe files given on argv (outside the checkout).
$B = '/tmp/claude-0/-home-user-Experiment-Operataion-Exaact-requirement/a8e2f8b5-ad99-5b2e-a592-b23795770424/scratchpad/v511base/phpapp';
chdir($B);
require $B . '/tests/lib.php';
require $B . '/tests/bootstrap.php';
foreach (array_slice($argv, 1) as $f) { echo "\n### $f ###\n"; require $f; }
$g = $GLOBALS['__t']; echo "\nRESULT: {$g['pass']} passed, {$g['fail']} failed\n";
