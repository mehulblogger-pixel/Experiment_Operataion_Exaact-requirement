<?php
// Segregation of duties on a report: the person who APPROVED it must not also be
// the one who FINALISES & ISSUES it. Master/admin is the standing exception (a
// one-person office where nobody else could issue).
t_section('the report approver cannot also issue the report');

$pdo = db();
if (function_exists('idems_migrate')) { try { idems_migrate(); } catch (Throwable $e) {} }

// An approver (non-master) and a separate would-be issuer.
$pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active) VALUES ('sod_appr','Sunil','Verma','OPERATION_MANAGER',1)")->execute();
$approverId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active) VALUES ('sod_other','Other','Issuer','ADMIN',1)")->execute();
$otherId = (int)$pdo->lastInsertId();

// An approved report whose designated approver is Sunil.
$pdo->prepare("INSERT INTO report_docs (irn, type_code, status, approver_user_id, approved_by, data, created_at) VALUES ('SOD-1','IR','APPROVED',?,?,?,?)")
    ->execute([$approverId, 'Sunil Verma', '[]', date('c')]);
$doc = ops_one("SELECT * FROM report_docs WHERE irn='SOD-1'");

// The approver is recognised as having approved it; a different user is not.
t_ok(idems_user_approved_doc($doc, $approverId) === true, 'the approver is recognised as having approved the report');
t_ok(idems_user_approved_doc($doc, $otherId) === false, 'a different user is not treated as the approver');

// A report_approvals chain step approved by a user is also caught.
$pdo->prepare("INSERT INTO report_docs (irn, type_code, status, data, created_at) VALUES ('SOD-2','IR','APPROVED','[]',?)")->execute([date('c')]);
$doc2 = ops_one("SELECT * FROM report_docs WHERE irn='SOD-2'");
$pdo->prepare("INSERT INTO report_approvals (report_doc_id, level, status, resolved_user_id, acted_by, acted_at) VALUES (?,?,?,?,?,?)")
    ->execute([(int)$doc2['id'], 1, 'APPROVED', $approverId, 'Sunil Verma', date('c')]);
t_ok(idems_user_approved_doc($doc2, $approverId) === true, 'a chain step approved by the user counts');
t_ok(idems_user_approved_doc($doc2, $otherId) === false, 'someone who did not act on the chain does not count');

// The playbook keeps the "Finalise & issue" button OFF the approver's screen and
// explains why, instead of offering it to them.
$_SESSION['uid'] = $approverId; current_user(true); ua(true);
$pb = idems_report_playbook($doc2, idems_report_approvals((int)$doc2['id']), true);
$issue = null; foreach ($pb['steps'] as $st) if (($st['key'] ?? '') === 'issue') { $issue = $st; break; }
t_ok($issue !== null, 'the playbook has an issue step');
t_ok(empty($issue['post']), 'the approver is not offered the Finalise & issue button');
t_ok(strpos((string)($issue['line'] ?? ''), 'segregation of duties') !== false, 'the approver is told a different person must issue it');

unset($_SESSION['uid']); current_user(true); ua(true);
