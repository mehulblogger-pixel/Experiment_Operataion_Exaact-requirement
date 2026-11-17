<?php
// ============================================================================
//  Stage inspection reports — "Start next stage report" (owner, 17 Nov 2026,
//  QA-REPORTS/V51/29 §B): "the next inspector must have a direct editable copy
//  of the report from the previous inspection … or the coordinator can select
//  the report for the inspector, editable, with the next report number."
//
//  On an APPROVED or ISSUED report:
//    · the inspector allocated to a job on the same work order, or
//    · a Coordinator-or-above of the issuing or executing office, who picks the
//      inspector (those allocated to the work order, else the executing
//      office's inspectors),
//  starts a NEW report: the next number in the same IRN series, a DRAFT that is
//  the chosen inspector's own, filled in from the previous one (client, vendor,
//  P.O., project, drawing, specs, location, type, the form's findings) with this
//  visit's own things empty (result / conclusion, inspection date, photos and
//  files, signatures, approvals, release status). "Continues from <IRN>" on the
//  new one, "Next stage: <IRN>" on the old one, which never changes.
// ============================================================================
require_once __DIR__ . '/_bb_reports_fixture.php';
t_section('Stage reports — "Start next stage report" makes the next report, filled in from this one');

$fx = bbr_fixture();
$pdo = db();
$typeId = idems_build_mgh_report();
$typeCode = (string) ops_val("SELECT code FROM report_types WHERE id=?", [$typeId]);
$job = function ($code, $call, $ins) use ($pdo, $fx) {
    $pdo->prepare("INSERT INTO jobs (job_code, call_id, executing_office_id, inspector_id, closed_flag, created_at) VALUES (?,?,?,?,0,?)")
        ->execute([$code, $call, $fx['A'], $ins, date('c')]);
    return (int) $pdo->lastInsertId();
};
$jobAmit = $job('BBR-J-AMIT', $fx['callAM'], $fx['insAmit']);
$jobRahul = $job('BBR-J-RAHUL', $fx['callAM'], $fx['insRahul']);

// Stage 1 — Amit's issued report, numbered by the IRN engine.
$hdr = ['report_type_id' => $typeId, 'type_code' => $typeCode, 'title' => 'BBR Stage report', 'client_id' => $fx['client'], 'vendor_id' => $fx['vendor'],
        'call_id' => $fx['callAM'], 'job_id' => $jobAmit, 'office_id' => $fx['A'], 'sbu' => '', 'client_code' => 'BBRREF', 'project_code' => 'PRJ9',
        'project_name' => 'BBR Coker revamp', 'po_ref' => 'PO-BBR-77', 'drawing_no' => 'DRG-501', 'drawing_rev' => 'C', 'qap_rev' => '2',
        'standards' => 'ASME VIII', 'location' => 'Bay 4', 'product_category' => 'Pressure vessel', 'material_grade' => 'SA516-70',
        'inspector_id' => $fx['insAmit'], 'approver_user_id' => $fx['bmA'], 'inspection_date' => '2026-11-02', 'result' => 'ACCEPTED',
        'release_status' => 'RELEASED', 'remarks' => 'Stage 1 remarks'];
[$irn1, $serial1] = idems_generate_irn($hdr);
$body = [
    'client' => 'BBR Refinery Ltd', 'po_number' => 'PO-BBR-77', 'inspection_stage' => 'Stage inspection', 'inspection_date' => '2026-11-02',
    'inspector' => 'BBR Inspector Amit', 'location' => 'Bay 4',
    'reference_documents' => [['document_name' => 'QAP', 'document_number' => 'Q-1']],
    'observations' => 'Shell long seam welded; RT of seam 3 pending', 'current_holdpoints' => 'RT of seam 3',
    'conclusion' => 'Accepted for stage 1', 'general_remarks' => 'NA',
    'photos__photo_denied' => '1', 'photos__photo_denied_note' => 'Not allowed in bay',
    'signoff' => ['Prepared by' => ['name' => 'Amit', 'desig' => 'Inspector', 'date' => '2026-11-02']],
];
$src = bbr_report(array_merge($hdr, ['irn' => $irn1, 'serial' => $serial1, 'status' => 'ISSUED', 'finalized' => 1, 'data' => json_encode($body), 'title' => 'BBR Stage report']));
$pdo->prepare("INSERT INTO report_files (report_doc_id, field_key, kind, file_name, mime, data, created_by, created_at) VALUES (?,?,?,?,?,?,?,?)")
    ->execute([$src, 'photos', 'photo', 'bbr.jpg', 'image/jpeg', 'data:image/jpeg;base64,AAAA', 'Amit Probe', date('c')]);
$pdo->prepare("INSERT INTO report_approvals (report_doc_id,level,approver_kind,approver_role,approver_user_id,resolved_user_id,status,acted_by,acted_at,sla_due,created_at,rule_id)
               VALUES (?,1,'USER','',?,?,'APPROVED','Kiran Probe',?,'',?,0)")->execute([$src, $fx['bmA'], $fx['bmA'], date('c'), date('c')]);
$snap = fn($id) => json_encode(ops_one("SELECT * FROM report_docs WHERE id=?", [$id]));
$before = $snap($src);
$nextOf = fn($id, $ins) => ops_one("SELECT * FROM report_docs WHERE prev_stage_doc_id=? AND inspector_id=? AND deleted=0", [$id, $ins]);

// ---- (a) Rahul, allocated stage 2 on the same work order ------------------------------------------
$r = bbr_drive($fx['rahul'], 'document', 'GET', ['id' => $src], [], ['BBR Stage report']);
t_ok(empty($r['has']['BBR Stage report']), 'arranged: Rahul may not open Amit\'s report itself (point 5)');
$r = bbr_drive($fx['rahul'], 'job', 'GET', ['id' => $jobRahul], [], ['Start next stage report', $irn1]);
t_ok(!empty($r['has']['Start next stage report']) && !empty($r['has'][$irn1]), 'his job page offers "Start next stage report" from ' . $irn1 . ': ' . bbr_said($r));
$r = bbr_drive($fx['rahul'], 'document-next-stage', 'POST', [], ['id' => $src, 'job_id' => $jobRahul]);
$n = $nextOf($src, $fx['insRahul']);
t_ok((bool) $n, 'Rahul starts the next stage report: ' . bbr_said($r));
$n = $n ?: [];
$prefix = substr($irn1, 0, strrpos($irn1, '/'));
t_eq((string) ($n['irn'] ?? ''), $prefix . '/' . str_pad((string) ($serial1 + 1), idems_serial_width(), '0', STR_PAD_LEFT), 'it takes the next number in the same series');
t_eq([(string) ($n['status'] ?? ''), (int) ($n['finalized'] ?? 1), (int) ($n['rev'] ?? 9)], ['DRAFT', 0, 0], 'it is a new Draft (no new status)');
t_eq([(int) ($n['inspector_id'] ?? 0), (int) ($n['job_id'] ?? 0), (int) ($n['created_by_user_id'] ?? 0), (int) ($n['call_id'] ?? 0)],
     [$fx['insRahul'], $jobRahul, $fx['rahul'], $fx['callAM']], 'it is Rahul\'s, on his job of the same work order');
$keep = ['report_type_id', 'type_code', 'title', 'client_id', 'vendor_id', 'office_id', 'client_code', 'project_code', 'project_name', 'po_ref',
         'drawing_no', 'drawing_rev', 'qap_rev', 'standards', 'location', 'product_category', 'material_grade'];
$s = bbr_doc($src);
t_eq(array_map('strval', array_intersect_key($n, array_flip($keep))), array_map('strval', array_intersect_key($s, array_flip($keep))),
     'client, vendor, P.O., project, drawing, specifications, location and report type are copied');
t_eq([(string) $n['inspection_date'], (string) $n['result'], (string) $n['release_status'], (int) ($n['approver_user_id'] ?? 0)], ['', '', '', 0],
     'inspection date, result, release status and the approver are left empty');
$d = json_decode((string) $n['data'], true) ?: [];
t_ok(($d['observations'] ?? '') === $body['observations'] && ($d['current_holdpoints'] ?? '') === 'RT of seam 3'
     && ($d['reference_documents'] ?? null) === $body['reference_documents'] && ($d['po_number'] ?? '') === 'PO-BBR-77',
     'the form\'s findings, tables and order details carry over');
$gone = array_values(array_intersect(['conclusion', 'inspection_date', 'inspector', 'signoff', 'photos__photo_denied', 'photos__photo_denied_note', 'general_remarks'], array_keys($d)));
t_eq($gone, [], 'conclusion, inspection date, inspector name, sign-off, photo notes and automatic "NA" blanks are not carried');
t_eq((int) ops_val("SELECT COUNT(*) FROM report_files WHERE report_doc_id=?", [(int) $n['id']]), 0, 'no photo or file is copied');
t_eq((int) ops_val("SELECT COUNT(*) FROM report_approvals WHERE report_doc_id=?", [(int) $n['id']]), 0, 'no approval is copied');
t_eq($snap($src), $before, 'the previous report is unchanged');
$h = bbr_history((int) $n['id'], 'CONTINUES_FROM');
t_ok($h && strpos((string) $h[0]['new_value'], 'Continues from ' . $irn1) === 0, 'the new report\'s History: "Continues from ' . $irn1 . '"');
$h = bbr_history($src, 'NEXT_STAGE');
t_ok($h && strpos((string) $h[0]['new_value'], 'Next stage: ' . $n['irn']) === 0, 'the old report\'s History: "Next stage: ' . $n['irn'] . '"');
// It is Rahul's to edit, and the pages link the two.
$r = bbr_drive($fx['rahul'], 'document-edit', 'POST', ['id' => (int) $n['id']], bbr_edit_post((int) $n['id'], ['title' => 'BBR Stage 2 report', 'inspection_date' => '2026-11-12']));
t_eq((string) bbr_doc((int) $n['id'])['title'], 'BBR Stage 2 report', 'Rahul edits his stage 2 report: ' . bbr_said($r));
$r = bbr_drive($fx['rahul'], 'document', 'GET', ['id' => (int) $n['id']], [], ['Continues from', $irn1]);
t_ok(!empty($r['has']['Continues from']) && !empty($r['has'][$irn1]), 'his report shows "Continues from ' . $irn1 . '"');
$r = bbr_drive($fx['coA'], 'document', 'GET', ['id' => $src], [], ['Next stage:', (string) $n['irn']]);
t_ok(!empty($r['has']['Next stage:']) && !empty($r['has'][(string) $n['irn']]), 'the old report shows "Next stage: ' . $n['irn'] . '"');
// A second press makes nothing twice.
$r = bbr_drive($fx['rahul'], 'document-next-stage', 'POST', [], ['id' => $src, 'job_id' => $jobRahul]);
t_eq((int) ops_val("SELECT COUNT(*) FROM report_docs WHERE prev_stage_doc_id=? AND deleted=0", [$src]), 1, 'a second press makes no duplicate: ' . bbr_said($r));

// ---- Who may NOT -------------------------------------------------------------------------------------
$pdo->prepare("INSERT INTO inspectors (name, status, home_office_id, created_at) VALUES ('BBR Inspector Vijay', 'ACTIVE', ?, ?)")->execute([$fx['A'], date('c')]);
$insV = (int) $pdo->lastInsertId();
$pdo->prepare("DELETE FROM users WHERE username='bbr_ins_vijay'")->execute();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id, inspector_id) VALUES ('bbr_ins_vijay','Vijay','Probe','','INSPECTOR',1,0,?,?)")
    ->execute([$fx['A'], $insV]);
$vijay = (int) $pdo->lastInsertId();
$r = bbr_drive($vijay, 'document-next-stage', 'POST', [], ['id' => $src]);
t_eq((int) ops_val("SELECT COUNT(*) FROM report_docs WHERE prev_stage_doc_id=? AND inspector_id=?", [$src, $insV]), 0,
     'an inspector with no job on this work order cannot: ' . bbr_said($r));
foreach (['coM' => 'the contracting office\'s Coordinator', 'coP' => 'a Coordinator of another branch'] as $k => $who) {
    $r = bbr_drive($fx[$k], 'document-next-stage', 'POST', [], ['id' => $src, 'inspector_id' => $fx['insAmit']]);
    t_eq((int) ops_val("SELECT COUNT(*) FROM report_docs WHERE prev_stage_doc_id=? AND inspector_id=?", [$src, $fx['insAmit']]), 0, "$who cannot: " . bbr_said($r));
}
// Not from a report that is not yet approved.
$draft = bbr_report(array_merge($hdr, ['irn' => 'BBR/IR/NS/' . getmypid(), 'status' => 'UNDER_REVIEW']));
$r = bbr_drive($fx['coA'], 'document', 'GET', ['id' => $draft], [], ['Start next stage report']);
t_ok(empty($r['has']['Start next stage report']), 'a report under review offers no "Start next stage report"');
$r = bbr_drive($fx['coA'], 'document-next-stage', 'POST', [], ['id' => $draft, 'inspector_id' => $fx['insRahul']]);
t_eq((int) ops_val("SELECT COUNT(*) FROM report_docs WHERE prev_stage_doc_id=?", [$draft]), 0, 'and a typed request is refused: ' . bbr_said($r));

// ---- (b) the executing office's Coordinator picks the inspector ----------------------------------------
$src2 = bbr_report(array_merge($hdr, ['irn' => $prefix . '/' . getmypid() . 'B', 'status' => 'APPROVED', 'data' => json_encode($body), 'title' => 'BBR Stage report B']));
$r = bbr_drive($fx['coA'], 'document', 'GET', ['id' => $src2], [], ['Start next stage report', 'BBR Inspector Amit', 'BBR Inspector Rahul', 'BBR Inspector Vijay']);
t_ok(!empty($r['has']['Start next stage report']) && !empty($r['has']['BBR Inspector Amit']) && !empty($r['has']['BBR Inspector Rahul']),
     'the Ahmedabad Coordinator is offered it, with the inspectors allocated to this work order');
t_ok(empty($r['has']['BBR Inspector Vijay']), 'an inspector with no job on the work order is not offered');
$r = bbr_drive($fx['coA'], 'document-next-stage', 'POST', [], ['id' => $src2, 'inspector_id' => $insV]);
t_eq((int) ops_val("SELECT COUNT(*) FROM report_docs WHERE prev_stage_doc_id=?", [$src2]), 0, 'a typed inspector who is not on the list is refused: ' . bbr_said($r));
$r = bbr_drive($fx['coA'], 'document-next-stage', 'POST', [], ['id' => $src2, 'inspector_id' => $fx['insAmit']]);
$n2 = $nextOf($src2, $fx['insAmit']);
t_ok($n2 && (int) $n2['job_id'] === $jobAmit && (int) $n2['created_by_user_id'] === $fx['coA'] && (string) $n2['status'] === 'DRAFT',
     'he starts it for Amit, on Amit\'s job, as a Draft he created: ' . bbr_said($r));
$h = $n2 ? bbr_history((int) $n2['id'], 'CONTINUES_FROM') : [];
t_ok($h && strpos((string) $h[0]['new_value'], 'Neha Probe (BBR Ahmedabad — executing office)') !== false, 'the History names him and his office');
$r = $n2 ? bbr_drive($fx['amit'], 'document-edit', 'POST', ['id' => (int) $n2['id']], bbr_edit_post((int) $n2['id'], ['title' => 'BBR B by Amit'])) : [];
t_eq($n2 ? (string) bbr_doc((int) $n2['id'])['title'] : '', 'BBR B by Amit', 'Amit can edit the report made for him');

// ---- the issuing office, and the fallback to the executing office's inspectors -------------------------
$src3 = bbr_report(array_merge($hdr, ['irn' => $prefix . '/' . getmypid() . 'C', 'status' => 'ISSUED', 'finalized' => 1, 'office_id' => $fx['P'], 'title' => 'BBR Stage report C']));
$r = bbr_drive($fx['coP'], 'document-next-stage', 'POST', [], ['id' => $src3, 'inspector_id' => $fx['insRahul']]);
t_ok((bool) $nextOf($src3, $fx['insRahul']), 'the issuing office\'s (Pune) Coordinator may start it too: ' . bbr_said($r));
$pdo->prepare("INSERT INTO inspectors (name, status, home_office_id, created_at) VALUES ('BBR Inspector Mumbai', 'ACTIVE', ?, ?)")->execute([$fx['M'], date('c')]);
$insMum = (int) $pdo->lastInsertId();
$src4 = bbr_report(array_merge($hdr, ['irn' => $prefix . '/' . getmypid() . 'D', 'status' => 'APPROVED', 'call_id' => $fx['callMM'], 'job_id' => null, 'office_id' => $fx['M'], 'title' => 'BBR Stage report D']));
$r = bbr_drive($fx['coM'], 'document', 'GET', ['id' => $src4], [], ['Start next stage report', 'BBR Inspector Mumbai']);
t_ok(!empty($r['has']['Start next stage report']) && !empty($r['has']['BBR Inspector Mumbai']),
     'with nobody allocated to the work order, the executing office\'s inspectors are offered');
$r = bbr_drive($fx['coM'], 'document-next-stage', 'POST', [], ['id' => $src4, 'inspector_id' => $insMum]);
$n4 = $nextOf($src4, $insMum);
t_ok($n4 && (int) ($n4['job_id'] ?? 0) === 0, 'and the report is made for that inspector: ' . bbr_said($r));

bbr_cleanup();   // leave nothing behind for later test files
