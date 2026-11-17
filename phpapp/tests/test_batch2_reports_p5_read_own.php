<?php
// ============================================================================
//  Point 5 (owner, 17 Nov 2026, QA-REPORTS/V51/29) — "Inspectors reading
//  other inspectors' reports: own reports only, for reading too."
//
//  Before (branch batch R2): an Inspector EDITED only their own reports, but
//  could still open every report of their branch — the page, the PDF, the
//  Word copy, its photos and files — and the register listed them all.
//
//  After: an Inspector (or Senior Inspector) opens only their own reports —
//  the inspector on it, or the person who created it (idems_is_own_report()).
//  Two people keep what their work needs: the approver named on the report's
//  approval chain, and a holder of the vetting / issue right (Senior
//  Inspector, `idems.finalize`). Coordinators and above are unchanged.
// ============================================================================
require_once __DIR__ . '/_bb_reports_fixture.php';
t_section('Point 5 — an Inspector reads only their own reports');

$fx = bbr_fixture();
$pdo = db();
$rahuls = bbr_report(['title' => 'BBR P5 Rahul', 'inspector_id' => $fx['insRahul'], 'call_id' => null, 'status' => 'ISSUED', 'finalized' => 1]);
$amits  = bbr_report(['title' => 'BBR P5 Amit', 'inspector_id' => $fx['insAmit'], 'call_id' => null]);
$rIrn = (string) bbr_doc($rahuls)['irn']; $aIrn = (string) bbr_doc($amits)['irn'];
$file = function ($docId, $bytes) use ($pdo) {
    $pdo->prepare("INSERT INTO report_files (report_doc_id, field_key, kind, file_name, mime, data, created_by, created_at) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$docId, 'photos', 'photo', 'bbr.txt', 'text/plain', 'data:text/plain;base64,' . base64_encode($bytes), 'Neha Probe', date('c')]);
    return (int) $pdo->lastInsertId();
};
$rFile = $file($rahuls, 'BBR-P5-RAHUL-BYTES'); $aFile = $file($amits, 'BBR-P5-AMIT-BYTES');
$refused = fn($r) => stripos(bbr_said($r), 'another inspector') !== false;

// ---- Rahul's report: Amit cannot read it anywhere -------------------------------------------------
$r = bbr_drive($fx['amit'], 'document', 'GET', ['id' => $rahuls], [], [$rIrn, 'BBR P5 Rahul']);
t_ok(empty($r['has']['BBR P5 Rahul']) && $refused($r), 'Amit: Rahul\'s report page does not open: ' . bbr_said($r));
$r = bbr_drive($fx['amit'], 'document-pdf', 'GET', ['id' => $rahuls]);
t_ok($refused($r), 'Amit: its PDF is refused: ' . bbr_said($r));
$r = bbr_drive($fx['amit'], 'document-docx', 'GET', ['id' => $rahuls]);
t_ok($refused($r), 'Amit: its Word copy is refused: ' . bbr_said($r));
$r = bbr_drive($fx['amit'], 'report-file', 'GET', ['id' => $rFile], [], ['BBR-P5-RAHUL-BYTES']);
t_ok(empty($r['has']['BBR-P5-RAHUL-BYTES']), 'Amit: a photo / file on it does not open');
$r = bbr_drive($fx['amit'], 'documents', 'GET', [], [], [$rIrn, $aIrn]);
t_ok(empty($r['has'][$rIrn]), 'Amit: the register does not list Rahul\'s report');
t_ok(!empty($r['has'][$aIrn]), 'Amit: the register still lists his own report');

// ---- His own reports: unchanged ---------------------------------------------------------------------
$r = bbr_drive($fx['amit'], 'document', 'GET', ['id' => $amits], [], ['BBR P5 Amit']);
t_ok(!empty($r['has']['BBR P5 Amit']), 'Amit opens his own report: ' . bbr_said($r));
$r = bbr_drive($fx['amit'], 'document-pdf', 'GET', ['id' => $amits]);
t_ok(!$refused($r) && !bbr_scope_refused($r), 'and its PDF: ' . bbr_said($r));
$r = bbr_drive($fx['amit'], 'report-file', 'GET', ['id' => $aFile], [], ['BBR-P5-AMIT-BYTES']);
t_ok(!empty($r['has']['BBR-P5-AMIT-BYTES']), 'and its files');
// A report he created, naming Rahul as inspector, is his too.
$made = bbr_report(['title' => 'BBR P5 made by Amit', 'inspector_id' => $fx['insRahul'], 'call_id' => null, 'created_by_user_id' => $fx['amit']]);
$r = bbr_drive($fx['amit'], 'document', 'GET', ['id' => $made], [], ['BBR P5 made by Amit']);
t_ok(!empty($r['has']['BBR P5 made by Amit']), 'a report he created (another inspector on it) opens for him');

// ---- Who keeps reading -----------------------------------------------------------------------------
$r = bbr_drive($fx['coA'], 'document', 'GET', ['id' => $rahuls], [], ['BBR P5 Rahul']);
t_ok(!empty($r['has']['BBR P5 Rahul']), 'the branch Coordinator still reads every report of the branch');
$r = bbr_drive($fx['coA'], 'documents', 'GET', [], [], [$rIrn, $aIrn]);
t_ok(!empty($r['has'][$rIrn]) && !empty($r['has'][$aIrn]), 'and the register lists both for him');
// An inspector named as the approver on a report's chain reads it (he must, to approve it).
$ur = bbr_report(['title' => 'BBR P5 to approve', 'inspector_id' => $fx['insRahul'], 'call_id' => null, 'status' => 'UNDER_REVIEW']);
$pdo->prepare("INSERT INTO report_approvals (report_doc_id,level,approver_kind,approver_role,approver_user_id,resolved_user_id,status,sla_due,created_at,rule_id)
               VALUES (?,1,'USER','',?,?,'PENDING','',?,0)")->execute([$ur, $fx['amit'], $fx['amit'], date('c')]);
$r = bbr_drive($fx['amit'], 'document', 'GET', ['id' => $ur], [], ['BBR P5 to approve']);
t_ok(!empty($r['has']['BBR P5 to approve']), 'an inspector named as its approver reads the report he must approve');
$r = bbr_drive($fx['amit'], 'documents', 'GET', ['mine' => 'approve'], [], [(string) bbr_doc($ur)['irn']]);
t_ok(!empty($r['has'][(string) bbr_doc($ur)['irn']]), 'and finds it in "Awaiting my approval"');
// A Senior Inspector (vetting / issue right) keeps reading the branch's reports.
$pdo->prepare("DELETE FROM users WHERE username='bbr_sr_p5'")->execute();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES ('bbr_sr_p5','Sunil','Probe','','SR_INSPECTOR',1,0,?)")
    ->execute([$fx['A']]);
$sr = (int) $pdo->lastInsertId();
$r = bbr_drive($sr, 'document', 'GET', ['id' => $rahuls], [], ['BBR P5 Rahul']);
t_ok(!empty($r['has']['BBR P5 Rahul']), 'a Senior Inspector, who vets and issues, still reads it');

bbr_cleanup();   // leave nothing behind for later test files
