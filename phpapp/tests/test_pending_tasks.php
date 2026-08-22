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
current_user(true); ua(true); // adopt the new session user (caches are refreshed)
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

// ---- Beyond reports: quotes, contracts and vouchers awaiting me ----------
// A quotation pending approval whose current step is a generic approver step —
// a master may sign it, so it must appear (the branch-manager scenario).
$pdo->prepare("INSERT INTO quotations (quote_no,rev,is_current,status,office_id,sbu,subject,total_amount,created_at)
               VALUES ('PT-Q1',0,1,'PENDING_APPROVAL',NULL,'','Pending quote',1000,?)")->execute([date('c')]);
$qid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO quote_approvals (quote_id,level,approver_role,approver_user_id,status)
               VALUES (?,1,'',NULL,'PENDING')")->execute([$qid]);
t_ok(crm_quotes_awaiting_me() >= 1, 'a quote whose current step is mine counts for me');

// Contract openings: one awaiting endorsement, one endorsed & awaiting approval.
$pdo->prepare("INSERT INTO partner_contracts (partner_id,contract_number,open_status,mgr_endorsed_at,bm_approved_at)
               VALUES (0,'PT-C1','PENDING','','')")->execute();
$pdo->prepare("INSERT INTO partner_contracts (partner_id,contract_number,open_status,mgr_endorsed_at,bm_approved_at)
               VALUES (0,'PT-C2','PENDING',?, '')")->execute([date('c')]);

// A submitted voucher, awaiting a manager's approval.
$pdo->prepare("INSERT INTO vouchers (inspector_id,month,status,created_at)
               VALUES (0,'2026-08','SUBMITTED',?)")->execute([date('c')]);

$tasks2 = ops_pending_tasks();
$byLabel2 = []; foreach ($tasks2 as $t) $byLabel2[$t['label']] = (int)$t['n'];
t_ok(($byLabel2['quotes to approve'] ?? 0) >= 1, 'quotations awaiting my approval show as a task');
if (function_exists('can_endorse_contract_open') && can_endorse_contract_open())
    t_ok(($byLabel2['contracts to endorse'] ?? 0) >= 1, 'a contract awaiting my endorsement shows as a task');
if (function_exists('can_approve_contract_open') && can_approve_contract_open())
    t_ok(($byLabel2['contracts to approve'] ?? 0) >= 1, 'a contract awaiting my approval shows as a task');
if (is_coordinator_level())
    t_ok(($byLabel2['vouchers to approve'] ?? 0) >= 1, 'a submitted voucher shows for a manager to approve');

// The panel renders the new tasks too.
$html3 = ops_render_pending_tasks();
t_ok(strpos($html3, 'quotes to approve') !== false, 'the rendered panel lists quotes awaiting approval');

// Restore the guest state so later tests are not left signed in as master.
unset($_SESSION['uid']);
current_user(true); ua(true);
