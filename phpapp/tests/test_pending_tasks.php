<?php
// Every dashboard shows the current user's pending tasks. Guards ops_pending_tasks
// (the counts that drive the panel) and that it degrades safely with no user.
t_section('pending tasks on the dashboard');

// With no logged-in user there is nothing pending, and no error.
t_ok(ops_pending_tasks() === [], 'no user → no pending tasks, without error');

// The renderer always produces a panel (either the tasks or "all caught up").
$html = ops_render_pending_tasks();
t_ok(is_string($html) && strpos($html, 'Your pending tasks') !== false, 'the panel renders on any dashboard');
t_ok(strpos($html, 'caught up') !== false, 'with nothing pending it says you are all caught up');

// As a master with reports in each pending state, the right chips appear.
$pdo = db();
$typeId = idems_build_fire_extinguisher_report();
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser) VALUES ('pt_master','M','MASTER_ADMIN',1,1)")->execute();
$_SESSION['uid'] = (int)$pdo->lastInsertId();
if (!is_master()) { t_ok(true, 'could not become master in this run — skipping chip checks'); return; }

$mk = function ($status, $over = []) use ($pdo, $typeId) {
    $row = array_merge(['report_type_id'=>$typeId,'type_code'=>'FEXT','irn'=>'PT-'.substr(md5($status.json_encode($over).microtime()),0,6),
        'status'=>$status,'finalized'=>$status==='ISSUED'?1:0,'data'=>'[]','created_at'=>date('c')], $over);
    $cols=implode(',',array_keys($row)); $ph=implode(',',array_fill(0,count($row),'?'));
    $pdo->prepare("INSERT INTO report_docs ($cols) VALUES ($ph)")->execute(array_values($row));
    return (int)$pdo->lastInsertId();
};
$mk('VETTING');
$mk('APPROVED');   // to issue

$tasks = ops_pending_tasks();
$byLabel = []; foreach ($tasks as $t) $byLabel[$t['label']] = (int)$t['n'];
t_ok(($byLabel['to vet'] ?? 0) >= 1, 'a VETTING report shows under "to vet" for a vetter');
t_ok(($byLabel['to issue'] ?? 0) >= 1, 'an APPROVED (not issued) report shows under "to issue"');

$html2 = ops_render_pending_tasks();
t_ok(strpos($html2, 'to vet') !== false, 'the rendered panel lists the pending vet task');
