<?php
// ============================================================================
//  Reporting A3 — DEF-REP-002 (P1). "♻️ Reissue as new revision" led to "Not
//  found": the handler existed but /document-revise was never wired into the
//  router. An issued report is now corrected by a new revision (R1, R2 …)
//  that goes through approval and issue again; the issued original never
//  changes. Per the owner's 0.3 it is started by a Coordinator-or-above of the
//  executing office (or their delegate).
// ============================================================================
require_once __DIR__ . '/_bb_reports_fixture.php';
t_section('Reporting A3 — "Reissue as new revision" works through the real route (DEF-REP-002)');

$fx = bbr_fixture();
$orig = bbr_report(['title' => 'BBR issued', 'status' => 'ISSUED', 'finalized' => 1, 'irn' => 'BBR/IR/A3/' . getmypid()]);
$irn = (string) bbr_doc($orig)['irn'];
$before = (string) json_encode(array_intersect_key(bbr_doc($orig), array_flip(['irn', 'title', 'status', 'finalized', 'data', 'client_id', 'vendor_id'])));

// Not someone outside the executing office, nor an inspector.
$r = bbr_drive($fx['coP'], 'document-revise', 'POST', [], ['id' => $orig]);
t_eq((int) ops_val("SELECT COUNT(*) FROM report_docs WHERE revises_id=?", [$orig]), 0, 'a Pune Coordinator cannot start a revision: ' . bbr_said($r));
$r = bbr_drive($fx['amit'], 'document-revise', 'POST', [], ['id' => $orig]);
t_eq((int) ops_val("SELECT COUNT(*) FROM report_docs WHERE revises_id=?", [$orig]), 0, 'nor can the inspector start one himself: ' . bbr_said($r));

// The executing office's Coordinator: the route reaches the handler (no more "Not found").
$r = bbr_drive($fx['coA'], 'document-revise', 'POST', [], ['id' => $orig]);
$rev = ops_one("SELECT * FROM report_docs WHERE revises_id=? AND deleted=0", [$orig]);
t_ok($rev && (string) $rev['irn'] === $irn . '/R1', 'the Ahmedabad Coordinator reissues it as ' . ($rev['irn'] ?? '(nothing)') . ': ' . bbr_said($r));
t_ok(stripos(bbr_said($r), 'Revision drafted as') !== false, 'the route reaches its handler — no more "Not found"');
t_eq((string) ($rev['status'] ?? ''), 'DRAFT', 'the revision starts as a Draft, to go through approval and issue again');
t_eq((int) ($rev['inspector_id'] ?? 0), $fx['insAmit'], 'the inspector stays on the revision, so he can correct it');
$after = (string) json_encode(array_intersect_key(bbr_doc($orig), array_flip(['irn', 'title', 'status', 'finalized', 'data', 'client_id', 'vendor_id'])));
t_eq($after, $before, 'the issued original is unchanged');
t_ok(count(bbr_history($orig, 'REVISION_STARTED')) === 1 && strpos((string) bbr_history($orig, 'REVISION_STARTED')[0]['new_value'], 'executing office') !== false,
     'the History of the original says who started the revision, from the executing office');

// The inspector edits the revision (his own report).
$r = bbr_drive($fx['amit'], 'document-edit', 'POST', ['id' => (int) $rev['id']], bbr_edit_post((int) $rev['id'], ['title' => 'BBR issued — corrected']));
t_eq((string) bbr_doc((int) $rev['id'])['title'], 'BBR issued — corrected', 'the inspector corrects the revision: ' . bbr_said($r));

// A revision of the revision is R2 of the same number — never ".../R1/R2".
db()->prepare("UPDATE report_docs SET status='ISSUED', finalized=1 WHERE id=?")->execute([(int) $rev['id']]);
$r = bbr_drive($fx['coA'], 'document-revise', 'POST', [], ['id' => (int) $rev['id']]);
$rev2 = ops_one("SELECT irn FROM report_docs WHERE revises_id=? AND deleted=0", [(int) $rev['id']]);
t_eq((string) ($rev2['irn'] ?? ''), $irn . '/R2', 'the next revision is numbered R2 of the original report: ' . bbr_said($r));

// Pressing it again on the original returns the existing revision.
$r = bbr_drive($fx['coA'], 'document-revise', 'POST', [], ['id' => $orig]);
t_eq((int) ops_val("SELECT COUNT(*) FROM report_docs WHERE revises_id=?", [$orig]), 1, 'a second press makes no duplicate: ' . bbr_said($r));

bbr_cleanup();   // leave nothing behind for later test files
