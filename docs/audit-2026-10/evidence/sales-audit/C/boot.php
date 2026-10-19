<?php
// Shared boot for the sales-C probes. Attaches the REAL app to a throwaway SQLite
// file in this scratch folder. Pass FRESH=1 to rebuild schema+seed (boot()).
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', '1');
$ROOT = '/home/user/Experiment_Operataion_Exaact-requirement/phpapp';
$DB   = getenv('PROBE_DB') ?: (__DIR__ . '/probe.sqlite');
putenv('DB_DRIVER=sqlite'); putenv('SQLITE_PATH=' . $DB); putenv('PROBE_DB=' . $DB);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'sales-C-probe';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($ROOT . '/index.php');
preg_match_all("#require __DIR__ \\. '(/lib/[a-z0-9_]+\\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $ROOT . $rel;
if (getenv('FRESH') === '1') {
    boot();
    setting_set('appr_self_approval', '0');
    setting_set('appr_self_master_exception', '1');
}
function as_user($uid) { $_SESSION['uid'] = (int)$uid; current_user(true); ua(true); }
