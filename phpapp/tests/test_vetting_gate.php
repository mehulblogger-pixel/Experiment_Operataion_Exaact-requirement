<?php
// The vetting gate: when on, a submitted inspection report goes to the vetting
// authority first; release notes skip vetting. Guards the pure gate rule and that
// the approval chain the auto-forward builds resolves to the report's approver.
t_section('vetting gate (vet before approver)');

// Off by default.
setting_set('vetting_gate_required', '');
t_ok(idems_vetting_gate_on() === false, 'the vetting gate is off by default');
t_ok(idems_vetting_required(['type_code'=>'FEXT']) === false, 'with the gate off, no report needs vetting');

// Switch it on.
setting_set('vetting_gate_required', '1');
t_ok(idems_vetting_gate_on() === true, 'the vetting gate can be switched on');
t_ok(idems_vetting_required(['type_code'=>'FEXT']) === true, 'an inspection report needs vetting when the gate is on');
t_ok(idems_vetting_required(['type_code'=>'IR']) === true, 'a standard inspection report needs vetting');
t_ok(idems_vetting_required(['type_code'=>'RN']) === false, 'a Release Note skips vetting (straight to approver)');
t_ok(idems_vetting_required(['type_code'=>'IRN']) === false, 'an Inspection Release Note skips vetting too');

// VETTING is a real lifecycle state that locks editing and is an "open" state.
t_ok(isset(IDEMS_STATUS['VETTING']), 'VETTING is a known status');
t_ok(idems_status_allows_edit('VETTING') === false, 'a report at vetting is locked from editing');
t_ok(in_array('VETTING', IDEMS_OPEN_STATES, true), 'VETTING counts as an open (not-yet-issued) state');

// The chain the auto-forward builds resolves to the approver picked on the report.
$pdo = db();
$typeId = idems_build_fire_extinguisher_report();
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active) VALUES ('vg_appr','Appr','MANAGER',1)")->execute();
$approverId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,status,approver_user_id,data,created_at)
               VALUES (?,?,?,?,?,?,?)")->execute([$typeId,'FEXT','VG-1','VETTING',$approverId,'[]',date('c')]);
$doc = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$pdo->lastInsertId()]);
$n = idems_build_approval_chain($doc);
t_ok($n >= 1, 'the approval chain builds at least one actionable step');
$step = idems_current_step($doc['id']);
t_eq((int)$step['resolved_user_id'], $approverId, 'the pending step resolves to the report’s chosen approver');

// tidy up
setting_set('vetting_gate_required', '');
