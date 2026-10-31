<?php
// MOD-MON (owner, 31 Oct): "You prepared this report … tick the acknowledgement"
// — but there was no tick box. The "How to get this report issued" card carries
// its own Approve button, and that form had no self-review tick, so the preparer
// could never approve from it. The card now shows the same tick as the
// Approvals tab, and the refusal says exactly where the box is.
t_section('the preparer sees the self-review tick on the report card');

$pdo = db();
if (function_exists('idems_migrate')) { try { idems_migrate(); } catch (Throwable $e) {} }

$pdo->prepare("INSERT INTO inspectors (name) VALUES ('Prep Inspector')")->execute();
$insId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, inspector_id) VALUES ('srt_prep','Prep','Inspector','OPERATION_MANAGER',1,?)")->execute([$insId]);
$prepId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active) VALUES ('srt_other','Other','Approver','OPERATION_MANAGER',1)")->execute();
$otherId = (int)$pdo->lastInsertId();

// A submitted report prepared by this inspector, waiting at level 1 on a step
// either user can act on (the role step).
$pdo->prepare("INSERT INTO report_docs (irn, type_code, status, inspector_id, data, created_at, submitted_at) VALUES ('SRT-1','IR','UNDER_REVIEW',?,?,?,?)")
    ->execute([$insId, '[]', date('c'), date('c')]);
$doc = ops_one("SELECT * FROM report_docs WHERE irn='SRT-1'");
$pdo->prepare("INSERT INTO report_approvals (report_doc_id, level, status, approver_role) VALUES (?,?,?,?)")
    ->execute([(int)$doc['id'], 1, 'PENDING', 'OPERATION_MANAGER']);

$render = function () use ($doc) {
    ob_start(); idems_render_report_playbook($doc, idems_report_approvals((int)$doc['id']), true); return ob_get_clean();
};

$_SESSION['uid'] = $prepId; current_user(true); ua(true);
t_ok(idems_is_self_review($doc) === true, 'the preparer is recognised as reviewing their own report');
$html = $render();
t_ok(strpos($html, 'Approve &amp; move forward') !== false, 'the card offers the preparer the Approve button');
t_ok(strpos($html, 'name="self_ack"') !== false, 'the card shows the self-review tick box next to it');
t_ok(strpos($html, 'approving my own work deliberately') !== false, 'the tick box says what it confirms');

$_SESSION['uid'] = $otherId; current_user(true); ua(true);
$html = $render();
t_ok(strpos($html, 'Approve &amp; move forward') !== false, 'another approver also gets the Approve button');
t_ok(strpos($html, 'name="self_ack"') === false, 'someone who did not prepare it is not asked to tick it');

// The refusal names the box and where it is.
$src = file_get_contents(__DIR__ . '/../lib/idems.php');
t_ok(strpos($src, 'just above the Approve button') !== false, 'the refusal says where the tick box is');
t_ok(strpos($src, 'tick the acknowledgement to confirm') === false, 'the old wording that pointed at nothing is gone');

unset($_SESSION['uid']); current_user(true); ua(true);
