<?php
// ============================================================================
//  R2 — DEF-REP-001 (P1). An Inspector could open another inspector's draft,
//  press "✎ Edit details" and save. docs/02 gives the Inspector "Edit (own)":
//  they edit only reports where they are the inspector, or which they created.
//  (Plan 0.2: the inspector who conducted the inspection opens, edits and
//  submits their own report — unchanged.) Coordinators keep what docs/02 gives.
// ============================================================================
require_once __DIR__ . '/_bb_reports_fixture.php';
t_section('R2 — an Inspector edits only their own report (DEF-REP-001)');

$fx = bbr_fixture();
$amits = bbr_report(['title' => 'BBR Amit draft', 'inspector_id' => $fx['insAmit'], 'call_id' => null]);
$rahuls = bbr_report(['title' => 'BBR Rahul draft', 'inspector_id' => $fx['insRahul'], 'call_id' => null]);

// Rahul's draft: Amit cannot change it.
$r = bbr_drive($fx['amit'], 'document', 'GET', ['id' => $rahuls], [], ['Edit details', 'Fill report']);
t_ok(empty($r['has']['Edit details']) && empty($r['has']['Fill report']), 'no "Edit details" / "Fill report" on another inspector\'s report');
$r = bbr_drive($fx['amit'], 'document-edit', 'POST', ['id' => $rahuls], bbr_edit_post($rahuls, ['title' => 'BBR changed by another inspector']));
t_eq((string) bbr_doc($rahuls)['title'], 'BBR Rahul draft', 'saving the edit form of another inspector\'s draft is refused: ' . bbr_said($r));
$r = bbr_drive($fx['amit'], 'document-fill', 'GET', ['id' => $rahuls], [], ['Save report body']);
t_ok(empty($r['has']['Save report body']), 'the fill screen of another inspector\'s draft does not open: ' . bbr_said($r));
$r = bbr_drive($fx['amit'], 'document-submit', 'POST', ['id' => $rahuls], ['id' => $rahuls]);
t_eq((string) bbr_doc($rahuls)['status'], 'DRAFT', 'nor can he submit it');

// His own draft: unchanged.
$r = bbr_drive($fx['amit'], 'document', 'GET', ['id' => $amits], [], ['Edit details']);
t_ok(!empty($r['has']['Edit details']), 'his own draft shows "Edit details"');
$r = bbr_drive($fx['amit'], 'document-edit', 'POST', ['id' => $amits], bbr_edit_post($amits, ['title' => 'BBR Amit edited own']));
t_eq((string) bbr_doc($amits)['title'], 'BBR Amit edited own', 'he edits his own report: ' . bbr_said($r));

// A report he created (another inspector on it) is his to edit too.
$r = bbr_drive($fx['amit'], 'document-new', 'POST', [], ['report_type_id' => $fx['typeId'], 'office_id' => $fx['A'],
     'inspector_id' => $fx['insRahul'], 'title' => 'BBR created by Amit', 'inspection_date' => '2026-11-01']);
$mine = (int) ops_val("SELECT id FROM report_docs WHERE title='BBR created by Amit' ORDER BY id DESC LIMIT 1");
t_ok($mine > 0, 'arranged: Amit creates a report naming Rahul as inspector: ' . bbr_said($r));
$r = bbr_drive($fx['amit'], 'document-edit', 'POST', ['id' => $mine], bbr_edit_post($mine, ['title' => 'BBR created by Amit, edited']));
t_eq((string) bbr_doc($mine)['title'], 'BBR created by Amit, edited', 'the creator may edit the report he created');

// A Coordinator of the branch keeps editing any report of the branch.
$r = bbr_drive($fx['coA'], 'document-edit', 'POST', ['id' => $rahuls], bbr_edit_post($rahuls, ['title' => 'BBR Rahul draft, coordinator']));
t_eq((string) bbr_doc($rahuls)['title'], 'BBR Rahul draft, coordinator', 'the branch Coordinator still edits an inspector\'s draft');

bbr_cleanup();   // leave nothing behind for later test files
