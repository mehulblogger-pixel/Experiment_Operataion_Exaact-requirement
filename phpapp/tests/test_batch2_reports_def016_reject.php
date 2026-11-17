<?php
// ============================================================================
//  Change 1 — DEF-REP-016 (owner, 17 Nov 2026, QA-REPORTS/V51/29).
//
//  "If one approver rejects the report the next approver will not show that
//  report approve for pending."
//
//  Before: a report needs Neha (level 1) then Kiran (level 2). Neha sends it
//  back (or rejects it); the report goes to the inspector, but Kiran's step
//  stayed PENDING. It stayed in his queue and the approve handler never looked
//  at the report's status, so Kiran could approve a report sent back for
//  correction and, at the last level, make it "Approved".
//
//  After: a reject or send-back closes every later step at once (the report
//  leaves Kiran's pending list, dashboard count, inbox and reminders), the
//  History says "Rejected at level 1 by Neha Probe: <reason>", and approve /
//  reject / send back / delegate are refused unless the report is UNDER
//  REVIEW — even from an old browser tab. Resubmitting starts the chain again
//  from level 1, as before. No new status.
// ============================================================================
require_once __DIR__ . '/_bb_reports_fixture.php';
t_section('DEF-REP-016 — a report rejected or sent back leaves the later approvers');

$fx = bbr_fixture();
$pdo = db();
// A two-level chain: Neha (Ahmedabad Coordinator) then Kiran (Ahmedabad Branch Manager).
$chain = function ($docId) use ($pdo, $fx) {
    $pdo->prepare("DELETE FROM report_approvals WHERE report_doc_id=?")->execute([$docId]);
    $ins = $pdo->prepare("INSERT INTO report_approvals (report_doc_id,level,approver_kind,approver_role,approver_user_id,resolved_user_id,status,sla_due,created_at,rule_id)
                          VALUES (?,?,?,'',?,?,'PENDING',?,?,0)");
    $ins->execute([$docId, 1, 'USER', $fx['coA'], $fx['coA'], date('c', time() - 7200), date('c')]);
    $ins->execute([$docId, 2, 'USER', $fx['bmA'], $fx['bmA'], date('c', time() - 7200), date('c')]);
};
$pending = fn($docId) => (int) ops_val("SELECT COUNT(*) FROM report_approvals WHERE report_doc_id=? AND status='PENDING'", [$docId]);
// What Kiran's own screens count, computed as Kiran in this process.
$asKiran = function (callable $fn) use ($fx) {
    $_SESSION['uid'] = $fx['bmA']; current_user(true); if (function_exists('ua')) ua(true);
    try { return $fn(); } finally { unset($_SESSION['uid']); current_user(true); if (function_exists('ua')) ua(true); }
};

// ---- The normal path still works: L1 approves, then L2 approves ---------------------------------
$ok = bbr_report(['title' => 'BBR 016 normal', 'status' => 'UNDER_REVIEW']);
$chain($ok);
$r = bbr_drive($fx['coA'], 'document-approve', 'POST', [], ['id' => $ok, 'decision' => 'approve']);
t_eq([(string) bbr_doc($ok)['status'], $pending($ok)], ['UNDER_REVIEW', 1], 'level 1 approves; the report waits on level 2: ' . bbr_said($r));
$r = bbr_drive($fx['bmA'], 'document-approve', 'POST', [], ['id' => $ok, 'decision' => 'approve']);
t_eq((string) bbr_doc($ok)['status'], 'APPROVED', 'level 2 approves it: ' . bbr_said($r));

// ---- Send back at level 1 ------------------------------------------------------------------------
$sb = bbr_report(['title' => 'BBR 016 send back', 'status' => 'UNDER_REVIEW']);
$irn = (string) bbr_doc($sb)['irn'];
$chain($sb);
$r = bbr_drive($fx['coA'], 'document-approve', 'POST', [], ['id' => $sb, 'decision' => 'sendback', 'remarks' => 'The nameplate photo is missing']);
t_eq((string) bbr_doc($sb)['status'], 'DRAFT', 'Neha sends it back to the inspector: ' . bbr_said($r));
t_eq($pending($sb), 0, 'Kiran\'s level-2 step is closed at once — no approval step is left waiting');
t_eq((int) ops_val("SELECT COUNT(*) FROM report_approvals WHERE report_doc_id=? AND status='SENTBACK'", [$sb]), 1,
     'Neha\'s send-back is kept, so the inspector still sees who sent it back and why');
$h = bbr_history($sb, 'SENDBACK');
$line = $h ? (string) end($h)['new_value'] : '';
t_ok(strpos($line, 'Sent back at level 1 by Neha Probe: The nameplate photo is missing') === 0, 'the History says who sent it back, at which level, and why: ' . $line);
t_ok(strpos($line, 'level 2 (Kiran Probe)') !== false, 'and which later approval was closed');

// Kiran: not in his queue, his count, or his page — and his approve is refused.
t_eq($asKiran(fn() => idems_awaiting_my_approval_count()), 0, 'the report is not in Kiran\'s pending count (dashboard / tasks)');
$r = bbr_drive($fx['bmA'], 'documents', 'GET', ['mine' => 'approve'], [], [$irn]);
t_ok(empty($r['has'][$irn]), 'nor in his "Awaiting my approval" list');
$r = bbr_drive($fx['bmA'], 'document', 'GET', ['id' => $sb], [], ['✓ Approve', 'Your approval is needed']);
t_ok(empty($r['has']['✓ Approve']) && empty($r['has']['Your approval is needed']), 'his report page offers no Approve button');
$r = bbr_drive($fx['bmA'], 'document-approve', 'POST', [], ['id' => $sb, 'decision' => 'approve']);
t_eq((string) bbr_doc($sb)['status'], 'DRAFT', 'Kiran approving from an old tab is refused — the report stays with the inspector');
t_ok(stripos(bbr_said($r), 'no longer waiting for approval') !== false, 'with a plain message: ' . bbr_said($r));

// Resubmitting starts again from level 1 (the existing rebuild on submit).
idems_build_approval_chain(bbr_doc($sb));
t_eq((int) ops_val("SELECT COUNT(*) FROM report_approvals WHERE report_doc_id=? AND status<>'PENDING'", [$sb]), 0,
     'on resubmit the chain is rebuilt with nothing approved or sent back');

// ---- Reject at level 1 ---------------------------------------------------------------------------
$rj = bbr_report(['title' => 'BBR 016 reject', 'status' => 'UNDER_REVIEW']);
$chain($rj);
$r = bbr_drive($fx['coA'], 'document-approve', 'POST', [], ['id' => $rj, 'decision' => 'reject', 'remarks' => 'Wrong report type for this order']);
t_eq([(string) bbr_doc($rj)['status'], $pending($rj)], ['REJECTED', 0], 'Neha rejects it; the later step is closed: ' . bbr_said($r));
$h = bbr_history($rj, 'REJECT');
t_ok($h && strpos((string) end($h)['new_value'], 'Rejected at level 1 by Neha Probe: Wrong report type for this order') === 0,
     'the History reads "Rejected at level 1 by Neha Probe: <reason>"');
foreach (['approve' => 'approve', 'reject' => 'reject', 'sendback' => 'send back', 'delegate' => 'delegate'] as $dec => $w) {
    $r = bbr_drive($fx['bmA'], 'document-approve', 'POST', [], ['id' => $rj, 'decision' => $dec, 'remarks' => 'late', 'delegate_to' => $fx['coA']]);
    t_eq([(string) bbr_doc($rj)['status'], $pending($rj)], ['REJECTED', 0], "Kiran cannot $w a rejected report: " . bbr_said($r));
}

// ---- A report left over from before this fix: a stale PENDING step on a Draft ----------------------
$old = bbr_report(['title' => 'BBR 016 stale', 'status' => 'DRAFT']);
$pdo->prepare("INSERT INTO report_approvals (report_doc_id,level,approver_kind,approver_role,approver_user_id,resolved_user_id,status,sla_due,created_at,rule_id)
               VALUES (?,2,'INSPECTOR_MAP','',?,?,'PENDING',?,?,0)")->execute([$old, $fx['bmA'], $fx['bmA'], date('c', time() - 7200), date('c')]);
$stale = (int) $pdo->lastInsertId();
$r = bbr_drive($fx['bmA'], 'document-approve', 'POST', [], ['id' => $old, 'decision' => 'approve']);
t_eq((string) bbr_doc($old)['status'], 'DRAFT', 'a stale step on a Draft cannot be approved: ' . bbr_said($r));
t_eq($asKiran(fn() => idems_awaiting_my_approval_count()), 0, 'and is not counted as waiting on Kiran');
// The approver-map re-route and the SLA reminder leave it alone (they act only on reports under review).
idems_map_reroute_pending($fx['insAmit']);
t_eq((int) ops_val("SELECT COALESCE(resolved_user_id,0) FROM report_approvals WHERE id=?", [$stale]), $fx['bmA'],
     'changing the inspector\'s approver does not re-route a step of a report that is not under review');
$pdo->prepare("UPDATE report_approvals SET escalated=0 WHERE id=?")->execute([$stale]);
idems_run_sla_escalations();
t_eq((int) ops_val("SELECT escalated FROM report_approvals WHERE id=?", [$stale]), 0, 'and no overdue reminder is sent for it');

bbr_cleanup();   // leave nothing behind for later test files
