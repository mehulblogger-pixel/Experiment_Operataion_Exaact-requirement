<?php
// ============================================================================
//  Setup in plain questions — the owner's rule (20 Oct 2026): "every module
//  shall have simple questions and answers for the user to set up all these
//  things very easily." Sales is the first set (lib/setupqa.php, /sales-setup).
//
//  Each answer writes a setting the module's code already reads, so the test
//  checks both halves: the screen stores only an offered answer, and the answer
//  then changes what the server allows.
// ============================================================================
t_section('Sales setup in plain questions');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'GET', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$pdo = db();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('SQ Office',1)")->execute();
$off = (int) $pdo->lastInsertId();
$mk = function ($un, $role) use ($pdo, $off) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$MM  = $mk('sq_mm',  'MARKETING_MANAGER');
$FIN = $mk('sq_fin', 'FINANCE');
$pdo->exec("DELETE FROM quote_approval_rules");
$pdo->prepare("INSERT INTO quote_approval_rules (name, level, active, match_type, sbu, min_amount, max_amount, approver_role)
               VALUES ('Up to a lakh', 1, 1, 'ANY', '', 0, 100000, 'MARKETING_MANAGER')")->execute();
setting_set('quote_unmatched_approval', ''); setting_set('quote_self_approval', '');

// ---- the screen ----------------------------------------------------------------------
$r = $drive($MM, 'sales-setup', 'GET', [], [], ['set up in a few questions', 'not covered by any of your approval levels',
    'approve it themselves', 'review checklist', 'before tax', 'Nothing above', 'Save these answers']);
t_eq($r['done'], 'return', 'the Marketing Manager — who manages the approval rules — opens Sales setup (' . ($r['error'] ?? 'ok') . ')');
foreach (['not covered by any of your approval levels', 'approve it themselves', 'review checklist', 'Save these answers'] as $n)
    t_ok(!empty($r['has'][$n]), "the screen asks, in plain words: \"$n\"");
t_ok(!empty($r['has']['before tax']) && !empty($r['has']['Nothing above']),
     'it states the levels in sentences, before tax, and says what no level covers');
$r = $drive($FIN, 'sales-setup', 'GET', [], [], ['Save these answers']);
t_ok(empty($r['has']['Save these answers']), 'Finance, who may not change the approval rules, does not get the questions');
$hub = $drive($MM, 'sales', 'GET', [], [], ['Set up Sales']);
t_ok(!empty($hub['has']['Set up Sales']), 'the Sales hub offers "Set up Sales" to the person who may answer');

// ---- saving stores only an offered answer ------------------------------------------------
// Read the stored answer from the database: the worker is another process, so
// this process's settings cache cannot have seen what it wrote.
$stored = fn($k) => (string) ops_val("SELECT svalue FROM settings WHERE skey=?", [$k]);
$drive($MM, 'sales-setup', 'POST', [], ['quote_unmatched_approval' => 'none', 'quote_self_approval' => 'no', 'preorder_checklist_on' => '1']);
t_eq($stored('quote_unmatched_approval'), 'none', 'the answer "no approval needed" is stored');
t_eq($stored('quote_self_approval'), 'no', 'the answer "someone else must approve" is stored');
t_eq($stored('preorder_checklist_on'), '1', 'switching the review checklist on is stored in the same setting the checklist screen uses');
$drive($MM, 'sales-setup', 'POST', [], ['quote_unmatched_approval' => 'everyone-approves-everything']);
t_eq($stored('quote_unmatched_approval'), 'none', 'an answer that is not one of the choices is ignored, not stored');
$drive($FIN, 'sales-setup', 'POST', [], ['quote_unmatched_approval' => 'any']);
t_eq($stored('quote_unmatched_approval'), 'none', 'Finance cannot change the answers by posting them');
t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE subject LIKE 'Sales answered:%'") >= 1,
     'a change of answers is written to the activity log, in the words of the questions');

// ---- the self-approval answer changes what the server allows ------------------------------
$pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, client_name, office_id, subtotal, gst_pct, gst_amount, total_amount, owner_id, created_by, created_at)
               VALUES ('SQ-1',0,1,'DRAFT','x',?,50000,18,9000,59000,?,'sq_mm',?)")->execute([$off, $MM, date('c')]);
$own = (int) $pdo->lastInsertId();
setting_set('quote_unmatched_approval', 'any');
$drive($MM, 'quote-status', 'POST', ['id' => $own], ['to' => 'PENDING_APPROVAL']);
$step = ops_one("SELECT * FROM quote_approvals WHERE quote_id=?", [$own]);
t_ok((bool) $step, 'arranged: the Marketing Manager\'s own quotation waits at the level addressed to their role');
$drive($MM, 'quote-approve', 'POST', [], ['step' => (int) $step['id'], 'decision' => 'approve']);
t_eq((string) ops_val("SELECT status FROM quotations WHERE id=?", [$own]), 'PENDING_APPROVAL',
     'with "someone else must approve", the preparer cannot sign their own quotation even at their own level');
setting_set('quote_self_approval', 'yes');
$drive($MM, 'quote-approve', 'POST', [], ['step' => (int) $step['id'], 'decision' => 'approve']);
t_eq((string) ops_val("SELECT status FROM quotations WHERE id=?", [$own]), 'APPROVED',
     'with "yes, within their own authority" (the owner\'s default), the same person may approve it');
t_ok(strpos((string) ops_val("SELECT summary FROM quote_revisions WHERE quote_id=? AND summary LIKE 'Approved by%' ORDER BY id DESC", [$own]), 'self-approval') !== false,
     '…and the history records it as a self-approval');

setting_set('quote_unmatched_approval', ''); setting_set('quote_self_approval', ''); setting_set('preorder_checklist_on', '');
$pdo->exec("DELETE FROM quote_approval_rules");
