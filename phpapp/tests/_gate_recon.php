<?php
// A real separate process that runs ONE reconciliation pass.
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['HTTP_USER_AGENT']='gate'; $_SERVER['REQUEST_URI']='/'; $_SERVER['HTTP_HOST']='localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;
usleep(((int)($argv[1] ?? 0)) * 1000);
$r = appr_cond_reconcile();
echo "RC " . json_encode($r) . "\n";
