<?php
// ============================================================================
//  The contract-review (pre-order) checklist — the owner's answer of 20 Oct
//  2026 (D-10, "Yes to both"): when every point is required it holds approval,
//  sending and the customer's acceptance; a point that cannot be confirmed is
//  waived with a reason by someone who may approve.
//  Before: it held only a chain-step signature — a quotation approved directly,
//  sent with no approval needed, or accepted, walked past it; a tick recorded no
//  who or when; ticks carried into a revision; there was no waiver (SA-13).
// ============================================================================
t_section('Sales — the review checklist holds approval, sending and acceptance');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? []));
$pdo = db();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('CL Office',1)")->execute();
$off = (int) $pdo->lastInsertId();
$mk = function ($un, $role) use ($pdo, $off) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$BDM = $mk('cl_bdm', 'BUSINESS_DEV_MANAGER');   // prepares; cannot approve
$MM  = $mk('cl_mm',  'MARKETING_MANAGER');      // may approve
$pdo->exec("DELETE FROM quote_approval_rules");
setting_set('quote_unmatched_approval', 'none');
setting_set('preorder_checklist_on', '1');
setting_set('preorder_checklist_require', '1');
setting_set('preorder_checklist_items', "Scope is clear\nClient is not on hold");
$items = preorder_checklist_items();
$keys  = array_map('preorder_item_key', $items);
$quote = function ($status = 'DRAFT', $owner = null) use ($pdo, $BDM, $off) {
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, client_name, subject, office_id, subtotal, gst_pct, gst_amount, total_amount, owner_id, created_by, created_at, sent_at)
                   VALUES (?,0,1,?,'CL Client','Inspection',?,10000,18,1800,11800,?,'cl_bdm',?,?)")
        ->execute(['CL-' . uniqid(), $status, $off, $owner ?? $BDM, date('c'), $status === 'SENT' ? date('c') : '']);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, description, qty, unit, rate, amount) VALUES (?,1,'Inspection',1,'MANDAY',10000,10000)")->execute([$id]);
    return $id;
};
$st   = fn($id) => (string) ops_val("SELECT status FROM quotations WHERE id=?", [$id]);
$tick = fn($uid, $id, array $ks) => $drive($uid, 'quote-preorder-save', 'POST', [], ['id' => $id, 'pc' => array_fill_keys($ks, '1')]);

// ---- sending with no approval needed is held --------------------------------------------
$q1 = $quote();
$r = $drive($BDM, 'quote-status', 'POST', ['id' => $q1], ['to' => 'SENT']);
t_eq($st($q1), 'DRAFT', 'a quotation that needs no approval is not sent while the checklist is incomplete');
t_ok(strpos($said($r), 'review checklist') !== false, '…and the person is told why: ' . $said($r));
$tick($BDM, $q1, [$keys[0]]);
$drive($BDM, 'quote-status', 'POST', ['id' => $q1], ['to' => 'SENT']);
t_eq($st($q1), 'DRAFT', 'one point of two is not enough');
$tick($BDM, $q1, $keys);
$s = preorder_checklist_state(ops_one("SELECT preorder_checklist FROM quotations WHERE id=?", [$q1]));
t_eq((string)($s[$keys[1]]['by'] ?? ''), user_name(ops_one("SELECT * FROM users WHERE id=?", [$BDM])), 'each tick records who ticked it');
t_ok(trim((string)($s[$keys[1]]['at'] ?? '')) !== '', '…and when');
$drive($BDM, 'quote-status', 'POST', ['id' => $q1], ['to' => 'SENT']);
t_eq($st($q1), 'SENT', 'with every point ticked it is sent');

// ---- direct approval is held -------------------------------------------------------------------
$q2 = $quote();
$r = $drive($MM, 'quote-status', 'POST', ['id' => $q2], ['to' => 'APPROVED']);
t_eq($st($q2), 'DRAFT', 'approving directly (no chain) is held while the checklist is incomplete — the gap: only a chain step was held');
t_ok(strpos($said($r), 'review checklist') !== false, '…held by the checklist itself: ' . $said($r));
setting_set('quote_unmatched_approval', 'any');
// ---- a chain-step signature is held, as before -------------------------------------------------
$drive($BDM, 'quote-status', 'POST', ['id' => $q2], ['to' => 'PENDING_APPROVAL']);
t_eq($st($q2), 'PENDING_APPROVAL', 'submitting for approval is allowed — the approver reviews the checklist');
$stepId = (int) ops_val("SELECT id FROM quote_approvals WHERE quote_id=? ORDER BY id LIMIT 1", [$q2]);
$drive($MM, 'quote-approve', 'POST', [], ['step' => $stepId, 'decision' => 'approve']);
t_eq($st($q2), 'PENDING_APPROVAL', 'a chain-step signature is held too');

// ---- the waiver --------------------------------------------------------------------------------
$r = $drive($BDM, 'quote-preorder-save', 'POST', [], ['id' => $q2, 'do' => 'waive', 'waiver_reason' => 'Customer will not say']);
t_eq((string) ops_val("SELECT preorder_waiver FROM quotations WHERE id=?", [$q2]), '', 'the preparer, who may not approve, cannot waive it: ' . $said($r));
$r = $drive($MM, 'quote-preorder-save', 'POST', [], ['id' => $q2, 'do' => 'waive', 'waiver_reason' => '']);
t_eq((string) ops_val("SELECT preorder_waiver FROM quotations WHERE id=?", [$q2]), '', 'a waiver needs a reason');
$drive($MM, 'quote-preorder-save', 'POST', [], ['id' => $q2, 'do' => 'waive', 'waiver_reason' => 'Bank guarantee terms come with the PO']);
$w = ops_one("SELECT preorder_waiver, preorder_waived_by FROM quotations WHERE id=?", [$q2]);
t_eq([$w['preorder_waiver'], $w['preorder_waived_by']], ['Bank guarantee terms come with the PO', user_name(ops_one("SELECT * FROM users WHERE id=?", [$MM]))],
     'someone who may approve waives it, with a reason, and is named');
t_eq((int) ops_val("SELECT COUNT(*) FROM quote_revisions WHERE quote_id=? AND summary LIKE ?", [$q2, '%checklist waived%Bank guarantee%']), 1,
     '…and the waiver is written in its history');
$pg = $drive($BDM, 'quote', 'GET', ['id' => $q2], [], ['Waived', 'Bank guarantee terms come with the PO']);
t_ok(!empty($pg['has']['Bank guarantee terms come with the PO']), 'the waiver and its reason are shown on the quotation');
$drive($MM, 'quote-approve', 'POST', [], ['step' => $stepId, 'decision' => 'approve']);
t_eq($st($q2), 'APPROVED', 'once waived, the approver can sign');

// ---- acceptance is held ------------------------------------------------------------------------
$q3 = $quote('SENT');    // sent before the checklist was required
$r = $drive($BDM, 'quote-status', 'POST', ['id' => $q3], ['to' => 'ACCEPTED']);
t_eq($st($q3), 'SENT', 'the customer\'s acceptance is not recorded while the checklist is incomplete: ' . $said($r));
$tick($BDM, $q3, $keys);
$drive($BDM, 'quote-status', 'POST', ['id' => $q3], ['to' => 'ACCEPTED']);
t_eq($st($q3), 'ACCEPTED', 'with every point ticked, it is');
$r = $tick($BDM, $q3, []);
t_eq(count(preorder_checklist_state(ops_one("SELECT preorder_checklist FROM quotations WHERE id=?", [$q3]))), 2,
     'an accepted quotation\'s checklist can no longer be changed');

// ---- a revision is reviewed afresh ---------------------------------------------------------------
$drive($BDM, 'quote-revise', 'POST', ['id' => $q1]);
$rev = (int) ops_val("SELECT id FROM quotations WHERE parent_id=? AND is_current=1", [$q1]);
t_ok($rev > 0, 'arranged: a revision of the sent quotation');
t_eq(count(preorder_checklist_state(ops_one("SELECT preorder_checklist FROM quotations WHERE id=?", [$rev]))), 0, 'a revision starts with the checklist unticked');
$drive($MM, 'quote-revise', 'POST', ['id' => $q2]);
$rev2 = (int) ops_val("SELECT id FROM quotations WHERE parent_id=? AND is_current=1", [$q2]);
t_eq((string) ops_val("SELECT preorder_waiver FROM quotations WHERE id=?", [$rev2]), '', '…and a waiver is not carried into it');

// ---- as a reminder only, nothing is held -----------------------------------------------------------
setting_set('preorder_checklist_require', '0');
setting_set('quote_unmatched_approval', 'none');
$q4 = $quote();
$drive($BDM, 'quote-status', 'POST', ['id' => $q4], ['to' => 'SENT']);
t_eq($st($q4), 'SENT', 'control: when the company answers "a reminder only", nothing is held');

setting_set('preorder_checklist_on', ''); setting_set('preorder_checklist_require', ''); setting_set('preorder_checklist_items', '');
setting_set('quote_unmatched_approval', '');
