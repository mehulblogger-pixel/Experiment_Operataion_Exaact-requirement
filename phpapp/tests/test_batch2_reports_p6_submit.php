<?php
// ============================================================================
//  Point 6 (owner, 17 Nov 2026, QA-REPORTS/V51/29) — "Contracting office
//  submitting a report: No. Only the issuing or executing office submits; the
//  contracting office may edit before submission."
//
//  Before (branch batch B4/Q6): the contracting office could open, edit and
//  also SUBMIT a draft. After: it still opens and edits it (each edit in the
//  History), but has no Submit button and a typed submit is refused. The
//  issuing / executing office — and the report's own inspector — submit.
// ============================================================================
require_once __DIR__ . '/_bb_reports_fixture.php';
t_section('Point 6 — the contracting office edits a draft but does not submit it');

$fx = bbr_fixture();
$pdo = db();
$id = bbr_report(['title' => 'BBR P6 draft', 'inspection_date' => '2026-11-10', 'po_ref' => 'PO-BBR-6']);
$contractMsg = fn($r) => stripos(bbr_said($r), 'Only the issuing or executing office') !== false;

// ---- Mumbai (contracting office) ----------------------------------------------------------------
$r = bbr_drive($fx['coM'], 'document', 'GET', ['id' => $id], [], ['Edit details', 'Submit for review', 'SUBMIT REPORT']);
t_ok(!empty($r['has']['Edit details']), 'Mumbai Coordinator (contracting office): still offered "Edit details"');
t_ok(empty($r['has']['Submit for review']) && empty($r['has']['SUBMIT REPORT']), 'but no Submit button');
$r = bbr_drive($fx['coM'], 'document-edit', 'POST', ['id' => $id], bbr_edit_post($id, ['title' => 'BBR P6 edited by Mumbai']));
t_eq((string) bbr_doc($id)['title'], 'BBR P6 edited by Mumbai', 'Mumbai may still edit it before submission: ' . bbr_said($r));
$r = bbr_drive($fx['coM'], 'document-submit', 'POST', ['id' => $id], ['id' => $id]);
t_eq((string) bbr_doc($id)['status'], 'DRAFT', 'a submit typed by Mumbai is refused — the report stays a Draft');
t_ok($contractMsg($r), 'with a plain message: ' . bbr_said($r));

// ---- Ahmedabad (issuing + executing office): the office rule lets it through --------------------
$r = bbr_drive($fx['coA'], 'document-submit', 'POST', ['id' => $id], ['id' => $id]);
t_ok(!$contractMsg($r) && !bbr_scope_refused($r), 'the Ahmedabad Coordinator is not refused for his office (the usual checks apply): ' . bbr_said($r));
$r = bbr_drive($fx['amit'], 'document', 'GET', ['id' => $id], [], ['Edit details']);
t_ok(!empty($r['has']['Edit details']), 'the inspector on the report keeps his own report');

// ---- The report's own inspector, posted in the contracting office, still submits it -------------
$pdo->prepare("INSERT INTO inspectors (name, status, created_at) VALUES ('BBR Inspector Mumbai', 'ACTIVE', ?)")->execute([date('c')]);
$insM = (int) $pdo->lastInsertId();
$pdo->prepare("DELETE FROM users WHERE username='bbr_ins_mum'")->execute();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id, inspector_id) VALUES ('bbr_ins_mum','Ravi','Probe','','INSPECTOR',1,0,?,?)")
    ->execute([$fx['M'], $insM]);
$ravi = (int) $pdo->lastInsertId();
$own = bbr_report(['title' => 'BBR P6 Ravi own', 'inspector_id' => $insM]);
$r = bbr_drive($ravi, 'document-submit', 'POST', ['id' => $own], ['id' => $own]);
t_ok(!$contractMsg($r) && !bbr_scope_refused($r), 'the report\'s own inspector, though posted in Mumbai, is not refused: ' . bbr_said($r));

bbr_cleanup();   // leave nothing behind for later test files
