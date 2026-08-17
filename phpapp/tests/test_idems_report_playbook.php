<?php
// The report screen now carries a guided playbook, and the automatic signature
// is a visible step in it. These guard the two pieces of new logic that do not
// depend on who is logged in: whether the inspector's signature is detected as
// "on file" (so it auto-stamps at issue), and the shape of the playbook steps.
t_section('IDEMS report playbook + signature visibility');

idems_migrate();

// An inspector with NO signature, and a report assigned to them.
db()->prepare("INSERT INTO inspectors (name, status, created_at) VALUES ('Arun Verma','ACTIVE',?)")->execute([date('c')]);
$insId = (int) db()->lastInsertId();
db()->prepare("INSERT INTO report_docs (irn, type_code, title, status, inspector_id, created_at, updated_at)
    VALUES ('IR-TEST-1','IR','Inspection Report','DRAFT',?,?,?)")
    ->execute([$insId, date('c'), date('c')]);
$docId = (int) db()->lastInsertId();
$doc = ops_one("SELECT * FROM report_docs WHERE id=?", [$docId]);

// --- Signature state: none on file ---
[$onFile, $name, $mine] = idems_report_signature_state($doc);
t_ok($onFile === false, 'with no signature saved, it is not detected on file');
t_eq($name, 'Arun Verma', 'the inspector name is resolved');

// The playbook carries a signature step, and while unsigned it is not "done".
$pb = idems_report_playbook($doc, [], true);
$sign = null; foreach ($pb['steps'] as $s) if ($s['key'] === 'sign') $sign = $s;
t_ok(is_array($sign), 'the playbook has a dedicated signature step');
t_ok($sign['state'] !== 'done', 'the signature step is not done while none is on file');
t_ok(stripos($sign['line'], 'signature') !== false, 'the signature step explains the automatic stamp');

// At most one step is the highlighted "do this now".
$nowCount = count(array_filter($pb['steps'], fn($s) => $s['state'] === 'now'));
t_ok($nowCount <= 1, 'at most one step is flagged "do this now" (' . $nowCount . ')');

// --- Now the inspector saves a signature (as My Signature does on the user, or
//     directly on the inspector record). It must be detected as on file. ---
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCA';
db()->prepare("UPDATE inspectors SET signature=? WHERE id=?")->execute([$png, $insId]);
[$onFile2] = idems_report_signature_state($doc);
t_ok($onFile2 === true, 'once the inspector has a signature, it is detected on file');

$pb2 = idems_report_playbook($doc, [], true);
$sign2 = null; foreach ($pb2['steps'] as $s) if ($s['key'] === 'sign') $sign2 = $s;
t_eq($sign2['state'], 'done', 'the signature step is done once one is on file');
t_ok($pb2['sig_on_file'] === true, 'the playbook reports the signature is ready');

// A signature captured on the report body (a signature field file) also counts,
// even if the inspector record has none.
db()->prepare("UPDATE inspectors SET signature='' WHERE id=?")->execute([$insId]);
db()->prepare("INSERT INTO report_files (report_doc_id, field_key, kind, file_name, mime, data, created_by, created_at)
    VALUES (?,?,?,?,?,?,?,?)")->execute([$docId, 'sig', 'signature', 'sig.png', 'image/png', $png, 'test', date('c')]);
[$onFile3] = idems_report_signature_state($doc);
t_ok($onFile3 === true, 'a signature captured on the report body also counts as on file');

// --- Approval hand-off: a submitted report shows an approval step ---
// Put the report under review with a pending approver who is NOT the current
// (guest) viewer, so the step is informational — it must never offer the approve
// buttons to someone who cannot act.
db()->prepare("UPDATE report_docs SET status='UNDER_REVIEW' WHERE id=?")->execute([$docId]);
db()->prepare("INSERT INTO report_approvals (report_doc_id, level, approver_kind, approver_role, resolved_user_id, status, created_at)
    VALUES (?,?,?,?,?,'PENDING',?)")->execute([$docId, 1, 'REPORT_PICK', '', 99999, date('c')]);
$docUR = ops_one("SELECT * FROM report_docs WHERE id=?", [$docId]);
$appr = idems_report_approvals($docId);
$pbUR = idems_report_playbook($docUR, $appr, true);
$ap = null; foreach ($pbUR['steps'] as $s) if ($s['key'] === 'approve') $ap = $s;
t_ok(is_array($ap) && $ap['state'] === 'info', 'a submitted report shows an awaiting-approval step to a non-approver');
t_ok(empty($ap['approve']), 'the approve buttons are NOT offered to someone who cannot act');
t_ok($pbUR['can_act'] === false, 'the playbook reports the viewer cannot act on this step');

// An issued report shows the closing state, not an actionable step.
db()->prepare("UPDATE report_docs SET status='ISSUED', finalized=1 WHERE id=?")->execute([$docId]);
$docIssued = ops_one("SELECT * FROM report_docs WHERE id=?", [$docId]);
$pb3 = idems_report_playbook($docIssued, [], true);
t_ok($pb3['issued'] === true, 'an issued report is marked issued in the playbook');
