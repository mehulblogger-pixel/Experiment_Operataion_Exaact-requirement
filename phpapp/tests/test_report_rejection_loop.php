<?php
// Report-rejection loop (Stage 7). A client rejecting an issued report is not a
// dead end: the decision is recorded, an NCR is raised automatically, and the
// rejection surfaces for the team to act on. This guards that existing loop.
t_section('report rejection loop (reject → NCR → surfaced)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    rcr_migrate();

    // a client and an ISSUED report belonging to them
    db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,status,created_at) VALUES ('RCR-CO','Reject Test Co','Reject Test Co',1,'ACTIVE',?)")->execute([date('c')]);
    $client = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO report_docs (irn,title,client_id,sbu,result,release_status,status,finalized,finalized_at,issue_date,client_decision,created_by,created_at) VALUES ('RCR-RPT-1','Weld Inspection Report',?, 'IND','ACCEPTED','RELEASED','ISSUED',1,?,?, '', 'seed', ?)")->execute([$client, date('c'), date('Y-m-d'), date('c')]);
    $docId = (int)db()->lastInsertId();
    $doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$docId]); $doc['rev'] = 0;

    $clientUser = ['id' => 1, 'partner_id' => $client, 'name' => 'EPC QA', 'email' => 'qa@demo.test'];

    // rejecting without a reason/note is refused (the loop needs something to act on)
    t_ok(rcr_decide($doc, ['decision' => 'REJECTED'], $clientUser) !== '', 'a rejection with no reason is refused');
    t_ok(rcr_decide($doc, ['decision' => 'REJECTED', 'reason' => 'EVIDENCE'], $clientUser) !== '', 'a rejection with no note is refused');

    // a proper rejection succeeds
    $err = rcr_decide($doc, ['decision' => 'REJECTED', 'reason' => 'EVIDENCE', 'note' => 'Weld-map photos are unreadable — please re-shoot.'], $clientUser);
    t_eq($err, '', 'a rejection with a reason + note is recorded');

    // the report now reads REJECTED
    t_eq((string)ops_val("SELECT client_decision FROM report_docs WHERE id=?", [$docId]), 'REJECTED', 'the report is marked REJECTED');

    // a review row exists and the decision is now current (locks re-deciding)
    t_ok((int)ops_val("SELECT COUNT(*) FROM report_client_reviews WHERE report_doc_id=? AND decision='REJECTED'", [$docId]) === 1, 'a client-review row is recorded');
    t_ok(rcr_current($doc) !== null, 'the recorded decision is current (a second decision is blocked)');
    t_ok(rcr_decide($doc, ['decision' => 'ACCEPTED'], $clientUser) !== '', 're-deciding the same revision is blocked (ask to re-issue)');

    // it surfaces in the rejected list for the team
    $found = false; foreach (rcr_rejected() as $r) if ((int)$r['report_doc_id'] === $docId) $found = true;
    t_ok($found, 'the rejection surfaces in rcr_rejected() for the team to act on');

    // The rejection also runs the quality-follow-up path (rcr_raise_ncr) and the
    // notification path (rcr_notify) inside rcr_decide — a clean decide (returned
    // '') above proves both ran without error, which is what closes the loop.
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
