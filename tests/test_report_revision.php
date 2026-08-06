<?php
// Report re-issue / revision workflow (Roadmap Step 14).
t_section('report re-issue / revision (Step 14)');

$now = date('c');
db()->prepare("INSERT INTO report_docs (irn, report_type_id, type_code, title, status, rev, finalized, data, remarks, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)")
    ->execute(['TIR/26/0777', 1, 'TIR', 'Test report', 'ISSUED', 0, 1, '{"k":"v"}', 'Original remarks', $now, $now]);
$srcId = (int)db()->lastInsertId();
$src = ops_one("SELECT * FROM report_docs WHERE id=?", [$srcId]);

[$newId, $err] = idems_revise_doc($src);
t_ok($newId > 0 && $err === '', 'a revision is created from an issued report');
$new = ops_one("SELECT * FROM report_docs WHERE id=?", [$newId]);
t_eq((int)$new['rev'], 1, 'the revision is Rev 1');
t_eq($new['status'], 'DRAFT', 'the revision starts as an editable DRAFT');
t_eq((int)$new['finalized'], 0, 'the revision is not finalized');
t_ok(strpos($new['irn'], 'TIR/26/0777/R1') === 0, "it keeps the report number, marked /R1 ({$new['irn']})");
t_eq((int)$new['revises_id'], $srcId, 'the revision links back to the original');
t_eq($new['data'], '{"k":"v"}', 'the content (field data) is carried into the revision');

$src2 = ops_one("SELECT * FROM report_docs WHERE id=?", [$srcId]);
t_eq((int)$src2['revised_by_id'], $newId, 'the original points forward to its revision');
t_eq($src2['status'], 'ISSUED', 'the original stays ISSUED and unchanged (immutable history)');

// Revising an already-revised original returns the existing revision, no duplicate.
[$again, $err2] = idems_revise_doc($src2);
t_ok($err2 === 'EXISTS' && $again === $newId, 'revising again returns the existing revision, not a duplicate');

// A draft that was never issued cannot be revised.
db()->prepare("INSERT INTO report_docs (irn, status, rev, finalized, created_at, updated_at) VALUES (?,?,?,?,?,?)")
    ->execute(['TIR/26/0778', 'DRAFT', 0, 0, $now, $now]);
$draft = ops_one("SELECT * FROM report_docs WHERE irn='TIR/26/0778'");
[$bad, $err3] = idems_revise_doc($draft);
t_ok($bad === 0 && $err3 !== '' && $err3 !== 'EXISTS', 'a never-issued draft cannot be revised');
