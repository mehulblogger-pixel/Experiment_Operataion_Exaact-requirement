<?php
// A quotation's approval step is routed to a person on purpose (by name or by
// role) via the approval matrix. Being named on the step IS the authority to act
// on it — the acting user must NOT also need the blanket crm.quote.approve
// permission, or a Branch Manager routed a quote can never approve it. This
// guards crm_can_act_approval() against that regression.
t_section('quote approval: assignment is the authority');

$pdo = db();
// A user whose role does not carry the blanket approve permission.
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active) VALUES ('qa_bm','Meena','BRANCH_MANAGER',1)")->execute();
$uid = (int)$pdo->lastInsertId();
$_SESSION['uid'] = $uid;
current_user(true); ua(true);

$pending = fn($over) => array_merge(['id'=>1,'quote_id'=>1,'level'=>1,'approver_role'=>'','approver_user_id'=>null,'status'=>'PENDING'], $over);

// Named to me personally — I can act even without the blanket permission.
t_ok(crm_can_act_approval($pending(['approver_user_id'=>$uid])) === true,
    'a step named to me is actionable by me (no blanket permission needed)');
// Addressed to my role — likewise (the branch-manager case).
t_ok(crm_can_act_approval($pending(['approver_role'=>'BRANCH_MANAGER'])) === true,
    'a step addressed to my role is actionable by me');
// Named to someone else — not mine to act on.
t_ok(crm_can_act_approval($pending(['approver_user_id'=>$uid + 999])) === false,
    'a step named to another user is not actionable by me');
// Addressed to a different role — not mine.
t_ok(crm_can_act_approval($pending(['approver_role'=>'FINANCE'])) === false,
    'a step addressed to another role is not actionable by me');
// A generic "any approver" step still needs the blanket permission.
if (!can('crm.quote.approve'))
    t_ok(crm_can_act_approval($pending([])) === false,
        'a generic step needs the approve permission (I lack it, so no)');
// A step already decided is never actionable.
t_ok(crm_can_act_approval($pending(['approver_user_id'=>$uid, 'status'=>'APPROVED'])) === false,
    'an already-decided step is not actionable');

// Restore guest state for later tests.
unset($_SESSION['uid']);
current_user(true); ua(true);
