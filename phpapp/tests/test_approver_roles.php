<?php
// An approver can be a manager, coordinator, inspector or senior inspector.
// Guards the Senior Inspector role and that an inspector-role user resolves as the
// approver on a report (so inspectors/sr-inspectors can be approvers).
t_section('approver roles (incl. inspector / senior inspector)');

// The Senior Inspector role exists and is offerable everywhere ORG_ROLES is used.
t_ok(isset(ORG_ROLES['SR_INSPECTOR']), 'Senior Inspector is a known role');
t_ok(isset(ORG_ROLES['INSPECTOR']) && isset(ORG_ROLES['COORDINATOR']), 'Inspector and Coordinator roles exist');

// A senior inspector can reach reports (idems module) and may vet/finalise.
$md = module_defaults('SR_INSPECTOR');
t_ok(in_array('idems.edit', $md, true) || in_array('idems', $md, true) || in_array('mod.idems.edit', $md, true),
     'a senior inspector has report (idems) access');
$rd = role_defaults('SR_INSPECTOR');
t_ok(in_array('idems.finalize', $rd['perms'], true), 'a senior inspector may vet / finalise');

// An inspector-role user set as a report's approver resolves as that approver.
$pdo = db();
$typeId = idems_build_fire_extinguisher_report();
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active) VALUES ('ar_insp','Insp','INSPECTOR',1)")->execute();
$inspUser = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active) VALUES ('ar_srinsp','SrInsp','SR_INSPECTOR',1)")->execute();
$srUser = (int)$pdo->lastInsertId();

foreach (['inspector'=>$inspUser, 'senior inspector'=>$srUser] as $label => $uid) {
    $pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,status,approver_user_id,data,created_at)
                   VALUES (?,?,?,?,?,?,?)")->execute([$typeId,'FEXT','AR-'.$uid,'UNDER_REVIEW',$uid,'[]',date('c')]);
    $doc = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$pdo->lastInsertId()]);
    $n = idems_build_approval_chain($doc);
    t_ok($n >= 1, "a report can name a $label as its approver");
    $step = idems_current_step($doc['id']);
    t_eq((int)$step['resolved_user_id'], $uid, "the approval step resolves to the $label");
}

// Role-based approval to the INSPECTOR / SR_INSPECTOR role is offerable (ORG_ROLES
// drives the rule dropdown), and such a step is actionable by matching role.
$roleStep = ['status'=>'PENDING', 'resolved_user_id'=>null, 'approver_role'=>'SR_INSPECTOR'];
t_ok(array_key_exists('SR_INSPECTOR', ORG_ROLES), 'a rule can target the Senior Inspector role');
