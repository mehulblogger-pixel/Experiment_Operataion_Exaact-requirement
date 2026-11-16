<?php
// ============================================================================
//  C2 + 0.3 (owner, 16 Nov 2026) — reopening a report.
//
//  The inspector edits only in Draft / Sent back. After submission, approval
//  or issue they can edit again only when a Coordinator (or above) OF THE
//  EXECUTING OFFICE — or the person acting for them through the existing
//  Approval delegation — sends it back, with a mandatory reason:
//    · Submitted / at vetting / under review → Draft   (this actor added)
//    · Approved → Draft                                (the one new move; the
//      approval chain runs again from the start on resubmit)
//    · Issued → only a new revision (test_branch_batch_reports_a3_revise.php)
//  Each send-back writes who, when and why to the History.
// ============================================================================
require_once __DIR__ . '/_bb_reports_fixture.php';
t_section('C2 — the executing office sends a report back to its inspector, with a reason');

$fx = bbr_fixture();
$pdo = db();
// A report under review, with a chain the Branch Manager approves.
$ur = bbr_report(['title' => 'BBR under review', 'status' => 'UNDER_REVIEW', 'approver_user_id' => $fx['bmA']]);
idems_build_approval_chain(bbr_doc($ur));
t_ok((int) ops_val("SELECT COUNT(*) FROM report_approvals WHERE report_doc_id=? AND status='PENDING'", [$ur]) > 0, 'arranged: the report waits on its approver');

// The inspector cannot change it while it is under review.
$r = bbr_drive($fx['amit'], 'document-edit', 'POST', ['id' => $ur], bbr_edit_post($ur, ['title' => 'BBR inspector under review']));
t_eq((string) bbr_doc($ur)['title'], 'BBR under review', 'the inspector cannot edit a report under review');

// Not the contracting office (Mumbai), not another branch, not the inspector.
foreach (['coM' => 'the contracting office\'s Coordinator', 'coP' => 'a Pune Coordinator', 'amit' => 'the inspector himself'] as $k => $who) {
    $r = bbr_drive($fx[$k], 'document-reopen', 'POST', [], ['id' => $ur, 'reason' => 'Please fix the photos']);
    t_eq((string) bbr_doc($ur)['status'], 'UNDER_REVIEW', "$who cannot send it back: " . bbr_said($r));
}
// The reason is mandatory.
$r = bbr_drive($fx['coA'], 'document-reopen', 'POST', [], ['id' => $ur, 'reason' => '  ']);
t_eq((string) bbr_doc($ur)['status'], 'UNDER_REVIEW', 'a send-back needs a reason: ' . bbr_said($r));
// The Ahmedabad (executing office) Coordinator sends it back.
$pg = bbr_drive($fx['coA'], 'document', 'GET', ['id' => $ur], [], ['Send back to the inspector']);
t_ok(!empty($pg['has']['Send back to the inspector']), 'the executing office\'s Coordinator is offered "Send back to the inspector"');
$r = bbr_drive($fx['coA'], 'document-reopen', 'POST', [], ['id' => $ur, 'reason' => 'Photos of the nameplate are missing']);
t_eq((string) bbr_doc($ur)['status'], 'DRAFT', 'under review → Draft by the executing office\'s Coordinator: ' . bbr_said($r));
$h = bbr_history($ur, 'REOPEN');
t_ok($h && strpos((string) $h[0]['new_value'], 'Neha Probe (BBR Ahmedabad — executing office)') !== false
     && strpos((string) $h[0]['new_value'], 'Photos of the nameplate are missing') !== false && (string) $h[0]['old_value'] === 'UNDER_REVIEW',
     'the History says who sent it back, from which office, from which stage, and why');
t_eq((int) ops_val("SELECT COUNT(*) FROM report_approvals WHERE report_doc_id=? AND status='PENDING'", [$ur]), 0, 'no approval step is left waiting');
$r = bbr_drive($fx['amit'], 'document-edit', 'POST', ['id' => $ur], bbr_edit_post($ur, ['title' => 'BBR corrected by inspector']));
t_eq((string) bbr_doc($ur)['title'], 'BBR corrected by inspector', 'the inspector can edit it again');
$mine = bbr_drive($fx['amit'], 'documents', 'GET', ['mine' => 'returned'], [], [(string) bbr_doc($ur)['irn']]);
t_ok(!empty($mine['has'][(string) bbr_doc($ur)['irn']]), 'it shows in the inspector\'s "Returned for correction" list');

// ---- APPROVED → DRAFT: the new move -------------------------------------------------------------
$ap = bbr_report(['title' => 'BBR approved', 'status' => 'UNDER_REVIEW', 'approver_user_id' => $fx['bmA']]);
idems_build_approval_chain(bbr_doc($ap));
$pdo->prepare("UPDATE report_approvals SET status='APPROVED', acted_by='Kiran Probe', acted_at=? WHERE report_doc_id=?")->execute([date('c'), $ap]);
$pdo->prepare("UPDATE report_docs SET status='APPROVED', approved_by='Kiran Probe', approved_at=? WHERE id=?")->execute([date('c'), $ap]);
$r = bbr_drive($fx['coM'], 'document-reopen', 'POST', [], ['id' => $ap, 'reason' => 'x']);
t_eq((string) bbr_doc($ap)['status'], 'APPROVED', 'the contracting office cannot send an approved report back');
$r = bbr_drive($fx['coA'], 'document-reopen', 'POST', [], ['id' => $ap, 'reason' => 'Client asked for the drawing revision to be shown']);
$d = bbr_doc($ap);
t_eq((string) $d['status'], 'DRAFT', 'approved → Draft by the executing office\'s Coordinator: ' . bbr_said($r));
t_eq([(string) $d['approved_by'], (string) $d['approved_at']], ['', ''], 'the approval on the report is cleared');
t_eq((int) ops_val("SELECT COUNT(*) FROM report_approvals WHERE report_doc_id=? AND status='APPROVED'", [$ap]), 0,
     'the approvals given so far are cleared, so the chain runs again on resubmit');
$h = bbr_history($ap, 'REOPEN');
t_ok($h && (string) $h[0]['old_value'] === 'APPROVED' && strpos((string) $h[0]['new_value'], 'Kiran Probe') !== false,
     'the History records the approval that was cleared');
// It cannot be issued until it has been approved again.
$r = bbr_drive($fx['bmA'], 'document-finalize', 'POST', ['id' => $ap], ['id' => $ap]);
t_eq((string) bbr_doc($ap)['status'], 'DRAFT', 'it cannot be issued while it is back with the inspector');
// Resubmitting runs the chain from the start (rebuilt PENDING).
idems_build_approval_chain(bbr_doc($ap));
t_eq((int) ops_val("SELECT COUNT(*) FROM report_approvals WHERE report_doc_id=? AND status='PENDING'", [$ap]) > 0
     && (int) ops_val("SELECT COUNT(*) FROM report_approvals WHERE report_doc_id=? AND status='APPROVED'", [$ap]) === 0, true,
     'on resubmit the approval chain starts again with nothing approved');

// ---- Issued: never reopened (a revision instead) ---------------------------------------------
$is = bbr_report(['title' => 'BBR issued', 'status' => 'ISSUED', 'finalized' => 1]);
$r = bbr_drive($fx['coA'], 'document-reopen', 'POST', [], ['id' => $is, 'reason' => 'Wrong date']);
t_eq([(string) bbr_doc($is)['status'], (int) bbr_doc($is)['finalized']], ['ISSUED', 1], 'an issued report is not sent back — a new revision is the route: ' . bbr_said($r));

// ---- Delegation: the person acting for the executing office's Coordinator -----------------------
if (function_exists('appr_migrate')) appr_migrate();
$pdo->prepare("DELETE FROM users WHERE username='bbr_sr_a'")->execute();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES ('bbr_sr_a','Sunil','Probe','','SR_INSPECTOR',1,0,?)")
    ->execute([$fx['A']]);
$sr = (int) $pdo->lastInsertId();
$dg = bbr_report(['title' => 'BBR delegate', 'status' => 'UNDER_REVIEW', 'approver_user_id' => $fx['bmA']]);
idems_build_approval_chain(bbr_doc($dg));
$r = bbr_drive($sr, 'document-reopen', 'POST', [], ['id' => $dg, 'reason' => 'Away cover']);
t_eq((string) bbr_doc($dg)['status'], 'UNDER_REVIEW', 'a Senior Inspector on his own cannot send it back: ' . bbr_said($r));
// A delegation for quotations only does not reach reports.
$pdo->prepare("INSERT INTO approval_delegations (delegator_user_id, delegate_user_id, entity, office_id, effective_from, effective_to, reason, active, created_by, created_at)
               VALUES (?,?,'QUOTE',NULL,'','','quotes only',1,'test',?)")->execute([$fx['coA'], $sr, date('c')]);
$r = bbr_drive($sr, 'document-reopen', 'POST', [], ['id' => $dg, 'reason' => 'Away cover']);
t_eq((string) bbr_doc($dg)['status'], 'UNDER_REVIEW', 'a quotation-only delegation does not reach reports');
// A delegation for everything, for Ahmedabad: he acts for Neha.
$pdo->prepare("INSERT INTO approval_delegations (delegator_user_id, delegate_user_id, entity, office_id, effective_from, effective_to, reason, active, created_by, created_at)
               VALUES (?,?,'',?,'','','leave cover',1,'test',?)")->execute([$fx['coA'], $sr, $fx['A'], date('c')]);
$r = bbr_drive($sr, 'document-reopen', 'POST', [], ['id' => $dg, 'reason' => 'Neha is on leave — the readings table is incomplete']);
t_eq((string) bbr_doc($dg)['status'], 'DRAFT', 'acting for the executing office\'s Coordinator, he sends it back: ' . bbr_said($r));
$h = bbr_history($dg, 'REOPEN');
t_ok($h && strpos((string) $h[0]['new_value'], 'acting for Neha Probe') !== false, 'the History says he acted for Neha through the delegation');

bbr_cleanup();   // leave nothing behind for later test files
