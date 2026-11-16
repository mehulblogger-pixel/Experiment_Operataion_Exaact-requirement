<?php
// ============================================================================
//  B5 — DEF-REP-007. "New inspection report → Start from an inspection call"
//  listed every branch's work orders, and Load filled that branch's client,
//  vendor and PO. It now offers only work orders whose executing or contracting
//  office is within the person's branch scope — the work-order register's rule —
//  and ?call= for any other work order loads nothing.
// ============================================================================
require_once __DIR__ . '/_bb_reports_fixture.php';
t_section('B5 — "Start from an inspection call" offers only the person\'s own branch\'s work orders (DEF-REP-007)');

$fx = bbr_fixture();
// A Pune-only work order, for a Pune client nobody else can pick.
db()->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, is_vendor, home_branch_id) VALUES ('BBRPUNE','BBR Pune Steel','BBR Pune Steel',1,0,?)")
    ->execute([$fx['P']]);
$puneClient = (int) db()->lastInsertId();
db()->prepare("INSERT INTO calls (call_code, client_id, ibo_office_id, executing_office_id, contracting_office_id, sbu, status, created_at)
               VALUES ('BBR-WO-PUN',?,?,?,?,'','OPEN',?)")->execute([$puneClient, $fx['P'], $fx['P'], $fx['P'], date('c')]);
$puneCall = (int) db()->lastInsertId();

$r = bbr_drive($fx['coA'], 'document-new', 'GET', [], [], ['BBR-WO-AHM', 'BBR-WO-MUM', 'BBR-WO-PUN']);
t_ok(!empty($r['has']['BBR-WO-AHM']), 'Ahmedabad Coordinator: a work order Ahmedabad carries out is offered');
t_ok(empty($r['has']['BBR-WO-MUM']), 'Ahmedabad Coordinator: a Mumbai-only work order is not offered');
t_ok(empty($r['has']['BBR-WO-PUN']), 'Ahmedabad Coordinator: a Pune work order is not offered');

$r = bbr_drive($fx['coM'], 'document-new', 'GET', [], [], ['BBR-WO-AHM', 'BBR-WO-MUM', 'BBR-WO-PUN']);
t_ok(!empty($r['has']['BBR-WO-AHM']) && !empty($r['has']['BBR-WO-MUM']),
     'Mumbai Coordinator: its own work order, and the one it sold to Ahmedabad (contracting office), are offered');
t_ok(empty($r['has']['BBR-WO-PUN']), 'Mumbai Coordinator: the Pune work order is not offered');

// Load by typing ?call= for another branch's work order fills nothing.
$r = bbr_drive($fx['coA'], 'document-new', 'GET', ['call' => $puneCall], [], ['Prefilled from', 'BBR Pune Steel']);
t_ok(empty($r['has']['Prefilled from']) && empty($r['has']['BBR Pune Steel']),
     'Ahmedabad Coordinator: typing ?call= for a Pune work order loads none of its client, vendor or PO: ' . bbr_said($r));
$r = bbr_drive($fx['coA'], 'document-new', 'GET', ['call' => $fx['callAM']], [], ['Prefilled from']);
t_ok(!empty($r['has']['Prefilled from']), 'Ahmedabad Coordinator: its own work order still loads');

// Creating a report against another branch's work order is refused.
$before = (int) ops_val("SELECT COUNT(*) FROM report_docs WHERE call_id=?", [$puneCall]);
$r = bbr_drive($fx['coA'], 'document-new', 'POST', [], ['report_type_id' => $fx['typeId'], 'call_id' => $puneCall,
     'client_id' => $puneClient, 'office_id' => $fx['A'], 'title' => 'BBR from Pune call', 'inspection_date' => date('Y-m-d')]);
t_eq((int) ops_val("SELECT COUNT(*) FROM report_docs WHERE call_id=?", [$puneCall]), $before,
     'Ahmedabad Coordinator: no report is created against the Pune work order: ' . bbr_said($r));

// Plan 0.2 — the inspector allocated to a job still starts its report from the job,
// even when the job is carried out by another office (a lent inspector).
db()->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, sbu) VALUES ('BBR-JOB-MUM',?,?,?,'')")
    ->execute([$fx['callMM'], $fx['M'], $fx['insAmit']]);
$mumJob = (int) db()->lastInsertId();
$r = bbr_drive($fx['amit'], 'document-new', 'GET', ['job' => $mumJob, 'call' => $fx['callMM']], [], ['Prefilled from']);
t_ok(!empty($r['has']['Prefilled from']), 'the inspector allocated to a Mumbai job still loads it to write its report: ' . bbr_said($r));
$r = bbr_drive($fx['rahul'], 'document-new', 'GET', ['job' => $mumJob, 'call' => $fx['callMM']], [], ['Prefilled from']);
t_ok(empty($r['has']['Prefilled from']), 'another Ahmedabad inspector, not on that job, does not: ' . bbr_said($r));

bbr_cleanup();   // leave nothing behind for later test files
