<?php
// ============================================================================
//  Point 4 (owner, 17 Nov 2026, QA-REPORTS/V51/29) — "Revision of an issued
//  report: also the issuing office's Coordinator, when it differs from the
//  executing office."
//
//  Before (branch batch, 0.3): only a Coordinator-or-above of the EXECUTING
//  office, or their Approval delegate, could start a new revision. A report
//  filed under one office (issuing) for work another office carried out
//  (executing) could not be revised by the office whose name is on it.
//
//  After: the issuing office's Coordinator-or-above may also start it, with
//  their delegate (the same delegation rule as sending back). Nobody else.
// ============================================================================
require_once __DIR__ . '/_bb_reports_fixture.php';
t_section('Point 4 — the issuing office may also start a revision of an issued report');

$fx = bbr_fixture();
$pdo = db();
// Filed under Pune (issuing) for the work order Ahmedabad carries out (executing), sold by Mumbai.
$mk = fn($t) => bbr_report(['title' => $t, 'status' => 'ISSUED', 'finalized' => 1, 'office_id' => $fx['P'], 'irn' => 'BBR/IR/P4/' . getmypid() . '/' . md5($t)]);
$revs = fn($id) => (int) ops_val("SELECT COUNT(*) FROM report_docs WHERE revises_id=? AND deleted=0", [$id]);

$a = $mk('BBR P4 issuing');
$r = bbr_drive($fx['coM'], 'document-revise', 'POST', [], ['id' => $a]);
t_eq($revs($a), 0, 'the contracting office (Mumbai) still cannot start a revision: ' . bbr_said($r));
$r = bbr_drive($fx['amit'], 'document-revise', 'POST', [], ['id' => $a]);
t_eq($revs($a), 0, 'nor can the inspector');
$pg = bbr_drive($fx['coP'], 'document', 'GET', ['id' => $a], [], ['Reissue as new revision']);
t_ok(!empty($pg['has']['Reissue as new revision']), 'the issuing office\'s (Pune) Coordinator is offered "Reissue as new revision"');
$r = bbr_drive($fx['coP'], 'document-revise', 'POST', [], ['id' => $a]);
t_eq($revs($a), 1, 'the issuing office\'s Coordinator starts the revision: ' . bbr_said($r));
$h = bbr_history($a, 'REVISION_STARTED');
t_ok($h && strpos((string) $h[0]['new_value'], 'Pune Probe (BBR Pune — issuing office)') !== false,
     'the History names him and the issuing office: ' . ($h ? $h[0]['new_value'] : ''));
t_eq((string) bbr_doc($a)['status'], 'ISSUED', 'the issued original is unchanged');

// The executing office keeps the right it had.
$b = $mk('BBR P4 executing');
$r = bbr_drive($fx['coA'], 'document-revise', 'POST', [], ['id' => $b]);
t_eq($revs($b), 1, 'the executing office\'s (Ahmedabad) Coordinator still starts one: ' . bbr_said($r));
$h = bbr_history($b, 'REVISION_STARTED');
t_ok($h && strpos((string) $h[0]['new_value'], 'executing office') !== false, 'recorded as the executing office');

// The issuing office's delegate — a Senior Inspector of Ahmedabad acting for the Pune Coordinator.
if (function_exists('appr_migrate')) appr_migrate();
$pdo->prepare("DELETE FROM users WHERE username='bbr_sr_p4'")->execute();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES ('bbr_sr_p4','Sunil','Probe','','SR_INSPECTOR',1,0,?)")
    ->execute([$fx['A']]);
$sr = (int) $pdo->lastInsertId();
$c = $mk('BBR P4 delegate');
$r = bbr_drive($sr, 'document-revise', 'POST', [], ['id' => $c]);
t_eq($revs($c), 0, 'a Senior Inspector on his own cannot start a revision: ' . bbr_said($r));
$pdo->prepare("INSERT INTO approval_delegations (delegator_user_id, delegate_user_id, entity, office_id, effective_from, effective_to, reason, active, created_by, created_at)
               VALUES (?,?,'',?,'','','leave cover',1,'test',?)")->execute([$fx['coP'], $sr, $fx['P'], date('c')]);
$r = bbr_drive($sr, 'document-revise', 'POST', [], ['id' => $c]);
t_eq($revs($c), 1, 'acting for the issuing office\'s Coordinator, he starts it: ' . bbr_said($r));
$h = bbr_history($c, 'REVISION_STARTED');
t_ok($h && strpos((string) $h[0]['new_value'], 'acting for Pune Probe') !== false && strpos((string) $h[0]['new_value'], 'issuing office') !== false,
     'the History says he acted for the Pune Coordinator (issuing office)');

// When the issuing office IS the executing office nothing changes: Pune has no part.
$d = bbr_report(['title' => 'BBR P4 same office', 'status' => 'ISSUED', 'finalized' => 1]);
$r = bbr_drive($fx['coP'], 'document-revise', 'POST', [], ['id' => $d]);
t_eq($revs($d), 0, 'an office with no part in the report still cannot start one: ' . bbr_said($r));

bbr_cleanup();   // leave nothing behind for later test files
