<?php
// MOD-MON (owner, 31 Oct): "Suppose I have issued the report with comments or
// hold point or deviation and it has been approved … and it is locked. I want to
// close those points — what has to be done?" The Release Note card listed the
// reasons ("… still open — close them first") but not WHERE to close them.
// Each reason now carries the way to it: the job's hold/witness points, the
// nonconformity register, or a new revision of the report.
t_section('a blocked Release Note says where each point is closed');

$pdo = db();
if (function_exists('idems_migrate')) { try { idems_migrate(); } catch (Throwable $e) {} }
if (function_exists('hwp_migrate')) { try { hwp_migrate(); } catch (Throwable $e) {} }
if (function_exists('ncr_migrate')) { try { ncr_migrate(); } catch (Throwable $e) {} }

$pdo->prepare("INSERT INTO jobs (stage, created_at) VALUES ('REPORT_PENDING', ?)")->execute([date('c')]);
$jobId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO report_docs (irn, type_code, status, finalized, job_id, rn_to_issue, data, created_at) VALUES ('RNB-1','IR','ISSUED',1,?,0,'[]',?)")
    ->execute([$jobId, date('c')]);
$doc = ops_one("SELECT * FROM report_docs WHERE irn='RNB-1'");

// Nothing open but the Release Note tick → only the revision is offered.
$links = idems_rn_blocker_links($doc);
t_ok(count($links) === 1 && ($links[0]['post'] ?? '') === '/document-revise', 'unticked Release Note: the way out is a new revision');

// An open hold point and an open deviation on the job.
$pdo->prepare("INSERT INTO hw_points (job_id, report_doc_id, point_type, description, status, dedupe_key, created_at) VALUES (?,?,'HOLD','Hydro test','OPEN','rnb-h1',?)")
    ->execute([$jobId, (int)$doc['id'], date('c')]);
$pdo->prepare("INSERT INTO nonconformities (ref, title, status, job_id, report_doc_id, created_at) VALUES ('NC-RNB','Weld undercut','OPEN',?,?,?)")
    ->execute([$jobId, (int)$doc['id'], date('c')]);
$hrefs = array_column(idems_rn_blocker_links($doc), 'href');
t_ok(in_array('/job?id=' . $jobId . '#holdpoints', $hrefs, true), 'an open hold point links to the job\'s hold / witness points');
t_ok(in_array('/ncr?job=' . $jobId, $hrefs, true), 'an open deviation links to the nonconformity register for the job');

// The card shows those links, as a person with report rights sees it.
$_SESSION['uid'] = 1; current_user(true); ua(true);
ob_start(); idems_render_report_playbook($doc, [], true); $html = ob_get_clean();
t_ok(strpos($html, 'Release Note — not yet') !== false, 'the issued report shows the blocked Release Note');
t_ok(strpos($html, 'href="/job?id=' . $jobId . '#holdpoints"') !== false, 'the card links to the hold / witness points');
t_ok(strpos($html, 'href="/ncr?job=' . $jobId . '"') !== false, 'the card links to the nonconformities');
t_ok(strpos($html, 'action="/document-revise"') !== false, 'the card offers the new revision as a button');

// Clear them → the links go, and only the revision (Release Note tick) is left.
$pdo->prepare("UPDATE hw_points SET status='CLEARED' WHERE job_id=?")->execute([$jobId]);
$pdo->prepare("UPDATE nonconformities SET status='CLOSED' WHERE job_id=?")->execute([$jobId]);
$links = idems_rn_blocker_links($doc);
t_ok(count($links) === 1, 'once the points are closed only the Release Note tick remains');

// Ticked and clear → no blockers, nothing to link.
$pdo->prepare("UPDATE report_docs SET rn_to_issue=1 WHERE id=?")->execute([(int)$doc['id']]);
$doc = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$doc['id']]);
t_ok(idems_rn_blockers($doc) === [] && idems_rn_blocker_links($doc) === [], 'clear and ticked: no blockers and no links');
unset($_SESSION['uid']); current_user(true); ua(true);
