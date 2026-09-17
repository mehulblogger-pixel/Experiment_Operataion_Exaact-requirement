<?php
// A REAL separate process for the M4 concurrency tests. It attaches to the SAME
// database the parent is using — it deliberately does not use tests/bootstrap.php,
// which drops every table on the MySQL path.
//
//   php tests/_m4_worker.php <op> <id> <arg> <delay-ms> <uid>
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['HTTP_USER_AGENT']='m4-worker';
$_SERVER['REQUEST_URI']='/'; $_SERVER['HTTP_HOST']='localhost';
if (!isset($_SESSION)) $_SESSION = [];
$idx = file_get_contents($root . '/index.php');
preg_match_all("#require __DIR__ \. '(/lib/[a-z0-9_]+\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) require_once $root . $rel;

$op = (string)($argv[1] ?? ''); $id = (int)($argv[2] ?? 0);
$arg = (string)($argv[3] ?? ''); $delay = (int)($argv[4] ?? 0); $uid = (int)($argv[5] ?? 0);
if ($uid > 0) { $_SESSION['uid'] = $uid; current_user(true); ua(true); }
if ($delay > 0) usleep($delay * 1000);
$out = ['op'=>$op, 'ok'=>false, 'msg'=>''];
try {
    if ($op === 'seat')        { [$ok,$msg,] = hreq_to_requisition($id, (int)$arg); $out['ok']=(bool)$ok; $out['msg']=$msg; }
    elseif ($op === 'qty')     { $prev=(int)ops_val("SELECT quantity FROM requisitions WHERE id=?",[$id]);
                                 db()->prepare("UPDATE requisitions SET quantity=? WHERE id=?")->execute([(int)$arg,$id]);
                                 $why = hreq_qty_enforce_after_write($id, $prev);
                                 $out['ok'] = ($why === ''); $out['msg'] = $why; }
    elseif ($op === 'material'){ $r = hreq_get($id);
                                 [$ok,$msg,] = hreq_save($id, ['requested_by_id'=>$r['requested_by_id'],
                                    'requesting_department_id'=>$r['requesting_department_id'],
                                    'hiring_department_id'=>$r['hiring_department_id'],
                                    'job_title'=>$r['job_title'], 'designation'=>$r['designation'],
                                    'quantity'=>(int)$r['quantity'], 'office_id'=>$r['office_id'],
                                    'employment_type'=>$r['employment_type'], 'request_type'=>$r['request_type'],
                                    'priority'=>$r['priority'], 'grade'=>$arg]);
                                 $out['ok']=(bool)$ok; $out['msg']=$msg; }
    elseif ($op === 'decide')  { [$ok,$msg] = hreq_apply_decision($id, $arg, 'worker', 'concurrent'); $out['ok']=(bool)$ok; $out['msg']=$msg; }
    elseif ($op === 'exec')    { $out['msg'] = hreq_req_block_reason($id); $out['ok'] = ($out['msg'] === ''); }
} catch (Throwable $e) { $out['msg'] = 'EX: ' . $e->getMessage(); }
echo json_encode($out) . "\n";
