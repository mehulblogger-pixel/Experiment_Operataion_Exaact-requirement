<?php
// Probe bootstrap: boots the REAL app libs against a PERSISTENT throwaway SQLite
// file in the scratch folder (so separate processes can share state). Mirrors
// tests/bootstrap.php but never unlinks, and stubs view() (defined in index.php).
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = '/home/user/Experiment_Operataion_Exaact-requirement/phpapp';
$dbf  = __DIR__ . '/probe.sqlite';
if (!empty($GLOBALS['__fresh'])) @unlink($dbf);
putenv('DB_DRIVER=sqlite'); putenv('SQLITE_PATH=' . $dbf);
$_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['HTTP_USER_AGENT']='probe'; $_SERVER['REQUEST_URI']='/'; $_SERVER['HTTP_HOST']='localhost';
if (!isset($_SESSION)) $_SESSION = [];
if (!function_exists('view')) {
    function view($name, $vars = []) {
        $GLOBALS['__view'] = $name;
        $GLOBALS['__view_vars'] = $vars;
    }
}
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \\. '(/lib/[a-z0-9_]+\\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;
if (!empty($GLOBALS['__fresh'])) boot(); else migrate_all();
function as_user($uid) { $_SESSION['uid'] = (int)$uid; current_user(true); ua(true); }
