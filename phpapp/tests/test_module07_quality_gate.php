<?php
// Module 07 — Vetting / Technical Review / Approval. Additive UX over the report
// quality gate: a provenance strip (Prepared/Vetted/Approved/Issued), a prominent
// return-reason banner, disambiguated status labels, a soft self-review
// acknowledgement, and an email to the inspector on return. All existing controls
// (issuer != approver, mandatory return reasons, gates) are preserved.
t_section('Module 07 — provenance, return reason, self-review ack, notify-on-return');

$idm = file_get_contents(__DIR__ . '/../lib/idems.php');
$view = file_get_contents(__DIR__ . '/../views/ops/idems/doc_detail.php');

// Helpers exist.
foreach (['idems_provenance','idems_latest_return','idems_status_label','idems_is_self_review','idems_notify_inspector_returned'] as $fn)
    t_ok(function_exists($fn), "$fn() exists");

// ---- Provenance strip: ordered Prepared / Vetted / Approved / Issued ----
$draft = ['id'=>0, 'status'=>'DRAFT', 'inspector_name'=>'Ravi', 'inspector_id'=>0, 'vet_by'=>'', 'vet_status'=>'', 'approved_by'=>'', 'finalized'=>0];
$p = idems_provenance($draft);
t_ok(count($p) === 4 && $p[0]['role']==='Prepared' && $p[1]['role']==='Vetted' && $p[2]['role']==='Approved' && $p[3]['role']==='Issued',
    'provenance lists the four roles in workflow order');
t_ok($p[0]['state']==='done' && $p[0]['name']==='Ravi', 'the preparer is shown as done');
t_ok(in_array($p[1]['state'], ['pending','na'], true) && $p[2]['state']==='pending' && $p[3]['state']==='pending',
    'not-yet-reached stages read as pending (or not-required for vetting)');

$issued = ['id'=>0, 'status'=>'ISSUED', 'inspector_name'=>'Ravi', 'inspector_id'=>0, 'vet_by'=>'Meera', 'vet_status'=>'VETTED',
           'vet_at'=>'2026-08-01', 'approved_by'=>'Sunil', 'approved_at'=>'2026-08-02', 'finalized'=>1, 'finalized_by'=>'Anita', 'finalized_at'=>'2026-08-03'];
$pi = idems_provenance($issued);
t_ok($pi[1]['name']==='Meera' && $pi[1]['state']==='done', 'a vetted report shows the vetter');
t_ok($pi[2]['name']==='Sunil' && $pi[3]['name']==='Anita' && $pi[3]['state']==='done', 'approver and issuer are shown distinctly');

// ---- Return reason: latest of vetting vs approval wins; status label follows ----
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('idems_migrate')) idems_migrate();
    db()->prepare("INSERT INTO report_docs (type_code, status, inspector_id, deleted, created_at) VALUES ('IC','DRAFT',990002,0,?)")->execute([date('c')]);
    $rid = (int)db()->lastInsertId();
    // A vetting return early, then an approver send-back later — the send-back is newer.
    db()->prepare("INSERT INTO report_vetting (report_doc_id, stage, action, note, acted_by, acted_at) VALUES (?,'VET','RETURNED','fix the scope','Meera','2026-08-01T09:00:00+00:00')")->execute([$rid]);
    db()->prepare("INSERT INTO report_approvals (report_doc_id, level, status, remarks, acted_by, acted_at, created_at) VALUES (?,1,'SENTBACK','add the QAP rev','Sunil','2026-08-05T09:00:00+00:00',?)")->execute([$rid, date('c')]);
    $doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$rid]);
    $ret = idems_latest_return($doc);
    t_ok($ret && $ret['kind']==='sendback' && $ret['reason']==='add the QAP rev', 'the most recent return (approver send-back) is the one surfaced');
    t_ok(idems_status_label($doc) === 'Returned for correction', 'a returned draft is labelled "Returned for correction", not a plain draft');

    // A rejected report is labelled distinctly from a returned draft.
    db()->prepare("UPDATE report_docs SET status='REJECTED' WHERE id=?")->execute([$rid]);
    t_ok(idems_status_label(ops_one("SELECT * FROM report_docs WHERE id=?", [$rid])) === 'Rejected — revise & resubmit',
        'a rejected report is labelled "Rejected — revise & resubmit"');

    // A fresh never-returned draft has no banner (null) and the plain label.
    db()->prepare("INSERT INTO report_docs (type_code, status, inspector_id, deleted, created_at) VALUES ('IC','DRAFT',990003,0,?)")->execute([date('c')]);
    $fresh = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)db()->lastInsertId()]);
    t_ok(idems_latest_return($fresh) === null && idems_status_label($fresh) === (IDEMS_STATUS['DRAFT'] ?? 'Draft'),
        'a fresh draft has no return banner and reads as an ordinary draft');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- Soft self-review acknowledgement (never blocks; asks) ----
t_ok(strpos($idm, "\$decision === 'approve' && idems_is_self_review(\$doc) && empty(\$_POST['self_ack'])") !== false,
    'approving your own report requires the self-review acknowledgement');
t_ok(strpos($idm, "\$action === 'VETTED' && idems_is_self_review(\$doc) && empty(\$_POST['self_ack'])") !== false,
    'vetting your own report requires the self-review acknowledgement');
t_ok(strpos($view, 'name="self_ack"') !== false, 'the vet/approve forms show the self-review acknowledgement to the preparer');

// ---- Email to the inspector on return (was missing entirely) ----
t_ok(strpos($idm, "idems_notify_inspector_returned(\$doc, 'reject', \$remarks)") !== false
  && strpos($idm, "idems_notify_inspector_returned(\$doc, 'sendback', \$remarks)") !== false
  && strpos($idm, "idems_notify_inspector_returned(\$doc, 'vetting', \$note)") !== false,
    'reject, send-back and vetting-return all notify the inspector');

// ---- Preserved controls (must NOT be weakened) ----
t_ok(strpos($idm, 'idems_user_approved_doc($doc, (int)(current_user()') !== false,
    'the issuer != approver finalize guard is still in place');
t_ok(strpos($idm, "A remark is mandatory when rejecting.") !== false
  && strpos($idm, "A remark is mandatory when sending back for correction.") !== false
  && strpos($idm, "A note is mandatory when returning a report for correction.") !== false,
    'return reasons remain mandatory on reject / send-back / vetting-return');

// ---- View additive pieces ----
t_ok(strpos($view, 'Provenance — who did what') !== false, 'the report screen shows the provenance strip');
t_ok(strpos($view, 'Returned for correction') !== false, 'the report screen shows the return-reason banner');
t_ok(strpos($view, 'a <b>different person</b> must finalize') !== false, 'the approver is told why they cannot issue their own approval');

// ---- No new permission introduced ----
t_ok(!preg_match('/can\(\x27idems\.(vet|selfreview|provenance)/', $idm), 'Module 07 introduces no new permission constant');
