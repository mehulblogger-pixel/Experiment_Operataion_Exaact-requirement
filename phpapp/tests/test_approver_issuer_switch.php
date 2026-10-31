<?php
// MOD-MON (owner, 31 Oct): "Finalise & issue — the approver and the issuer cannot
// be the same … suppose I want to switch this off, where is that setting?"
// Owner's decision: a company switch, ON by default, easy to reach. Only an
// administrator (settings.manage) may change it — never the approver it binds.
t_section('the approver ≠ issuer rule is a company switch, on by default');

$pdo = db();
if (function_exists('idems_migrate')) { try { idems_migrate(); } catch (Throwable $e) {} }
setting_set('idems_sod_issue', '');   // never set → default

$pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active) VALUES ('ais_appr','Asha','Approver','OPERATION_MANAGER',1)")->execute();
$apprId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active) VALUES ('ais_admin','Anil','Admin','ADMIN',1)")->execute();
$adminId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO report_docs (irn, type_code, status, data, created_at) VALUES ('AIS-1','IR','APPROVED','[]',?)")->execute([date('c')]);
$doc = ops_one("SELECT * FROM report_docs WHERE irn='AIS-1'");
$pdo->prepare("INSERT INTO report_approvals (report_doc_id, level, status, resolved_user_id, acted_by, acted_at) VALUES (?,?,?,?,?,?)")
    ->execute([(int)$doc['id'], 1, 'APPROVED', $apprId, 'Asha Approver', date('c')]);

$as = function ($uid) { $_SESSION['uid'] = $uid; current_user(true); ua(true); };

$as($apprId);
t_ok(idems_sod_issue_on() === true, 'the rule is on when nobody has set it');
t_ok(idems_issue_blocked_for_approver($doc) === true, 'the approver is stopped from issuing');
$pb = idems_report_playbook($doc, idems_report_approvals((int)$doc['id']), true);
$issue = null; foreach ($pb['steps'] as $st) if (($st['key'] ?? '') === 'issue') $issue = $st;
t_ok(empty($issue['post']), 'the approver is not offered Finalise & issue');
t_ok(strpos((string)$issue['line'], 'An administrator can change this rule') !== false, 'a non-administrator is told who can change it');
t_ok(idems_sod_issue_can_change() === false, 'an Operations Manager (an approver) cannot change the rule');

$as($adminId);
t_ok(idems_sod_issue_can_change() === true, 'an administrator can change the rule');
t_ok(strpos(idems_sod_issue_link_html(), '/vetting-checklist#issue-rule') !== false, 'an administrator gets a one-click link to the switch');
$areas = array_column(apsetup_open(), 'url');
t_ok(in_array('/vetting-checklist', $areas, true), 'the rule is listed on the All approvals setup screen');

// Switched off: the approver may issue.
setting_set('idems_sod_issue', '0');
$as($apprId);
t_ok(idems_sod_issue_on() === false, 'the switch turns the rule off');
t_ok(idems_issue_blocked_for_approver($doc) === false, 'with the rule off the approver is not stopped');

// Back on (the default) restores the block.
setting_set('idems_sod_issue', '1');
t_ok(idems_issue_blocked_for_approver($doc) === true, 'switching it back on restores the block');

// The same-person issue is recorded when the rule is off (source check: the
// finalize handler writes a SAME_PERSON_ISSUE audit entry).
$src = file_get_contents(__DIR__ . '/../lib/idems.php');
t_ok(strpos($src, "'SAME_PERSON_ISSUE'") !== false, 'an approver issuing with the rule off is written to the audit history');
setting_set('idems_sod_issue', '');
unset($_SESSION['uid']); current_user(true); ua(true);
