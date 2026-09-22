<?php
// R20 concurrency worker — one creation attempt per process, all firing at the
// same wall-clock microsecond.
//   php tests/_r20_worker.php <mode> <name> <email> <empcode> <start-at-ms>
// modes: raw   — INSERT a fixed employee number directly (no application code)
//        team  — team_member_create(), the direct Add-a-person door
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['HTTP_USER_AGENT']='r20';
$_SERVER['REQUEST_URI']='/'; $_SERVER['HTTP_HOST']='localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$mode=(string)($argv[1]??''); $name=(string)($argv[2]??''); $mail=(string)($argv[3]??'');
$code=(string)($argv[4]??''); $at=(float)($argv[5]??0);

$out=['mode'=>$mode,'ok'=>false,'id'=>0,'why'=>''];
try {
    db(); boot();
    $u=ops_one("SELECT * FROM users WHERE is_superuser=1 ORDER BY id LIMIT 1");
    if ($u) { $_SESSION['uid']=(int)$u['id']; current_user(true); ua(true); }
    if (function_exists('emp_code_migrate')) emp_code_migrate();
    if ($at>0) { $w=$at-microtime(true)*1000; if ($w>0) usleep((int)($w*1000)); }
    if ($mode==='raw') {
        try {
            db()->prepare("INSERT INTO inspectors (name,emp_code,email,status,created_at) VALUES (?,?,?,'ACTIVE',?)")
                ->execute([$name,$code,$mail,date('c')]);
            $out['id']=(int)db()->lastInsertId(); $out['ok']=true;
        } catch (Throwable $e) { $out['why']=substr(strtolower($e->getMessage()),0,80); }
    } else {
        $out['id']=(int)team_member_create($name,'FIELD',null,$mail);
        $out['ok']=$out['id']>0;
        if (!$out['ok'] && function_exists('team_member_last_refusal')) $out['why']=team_member_last_refusal();
    }
} catch (Throwable $e) { $out['why']=substr($e->getMessage(),0,120); }
echo json_encode($out)."\n";
