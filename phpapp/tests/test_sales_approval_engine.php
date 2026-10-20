<?php
// ============================================================================
//  The quotation approval engine — the owner's answers of 20 Oct 2026:
//    D-05  "Yes"          levels sign in order;
//    D-11a "Yes to both"  a delegate approves for someone who is away, and a
//                         quotation waiting too long moves up a level.
//  Before: level 2 could sign before level 1; delegation ignored quotations
//  although the setup screen said it covered "everything"; a step waiting on
//  someone away waited for ever (Sales audit SA-05, SA-25).
// ============================================================================
t_section('Sales — approvals in order, covered when someone is away, escalated when they wait');

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
if (function_exists('appr_migrate')) appr_migrate();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES ('AE Office',1)")->execute();
$off = (int) $pdo->lastInsertId();
$mk = function ($un, $role) use ($pdo, $off) {
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id)
                   VALUES (?,?,?,?,?,1,0,?)")->execute([$un, ucfirst($un), 'Probe', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$BDM = $mk('ae_bdm', 'BUSINESS_DEV_MANAGER');
$KAM = $mk('ae_kam', 'KEY_ACCOUNTS_MANAGER');
$MM  = $mk('ae_mm',  'MARKETING_MANAGER');
$BM  = $mk('ae_bm',  'BRANCH_MANAGER');
$BD  = $mk('ae_bd',  'BUSINESS_DIRECTOR');
$FIN = $mk('ae_fin', 'FINANCE');
$pdo->exec("DELETE FROM quote_approval_rules");
$pdo->exec("DELETE FROM approval_delegations");
$rule = fn($lvl, $role) => $pdo->prepare("INSERT INTO quote_approval_rules (name, level, active, match_type, sbu, min_amount, max_amount, approver_role)
                                          VALUES (?,?,1,'ANY','',0,0,?)")->execute(["L$lvl", $lvl, $role]);
$quote = function ($submittedAgo = 0) use ($pdo, $BDM, $off) {
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, client_name, subject, office_id, subtotal, gst_pct, gst_amount, total_amount, owner_id, created_by, created_at)
                   VALUES (?,0,1,'DRAFT','AE Client','Inspection',?,50000,18,9000,59000,?,'ae_bdm',?)")->execute(['AE-' . uniqid(), $off, $BDM, date('c')]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, description, qty, unit, rate, amount) VALUES (?,1,'Inspection',1,'MANDAY',50000,50000)")->execute([$id]);
    return $id;
};
$submit = function ($id) use ($drive, $BDM) { $drive($BDM, 'quote-status', 'POST', ['id' => $id], ['to' => 'PENDING_APPROVAL']); };
$step  = fn($id, $lvl) => ops_one("SELECT * FROM quote_approvals WHERE quote_id=? AND level=? ORDER BY id LIMIT 1", [$id, $lvl]);
$st    = fn($id) => (string) ops_val("SELECT status FROM quotations WHERE id=?", [$id]);
$sign  = fn($uid, $stepRow) => $drive($uid, 'quote-approve', 'POST', [], ['step' => (int) $stepRow['id'], 'decision' => 'approve']);

// ---- D-05: levels in order ---------------------------------------------------------------
$rule(1, 'BRANCH_MANAGER'); $rule(2, 'MARKETING_MANAGER');
$q = $quote(); $submit($q);
$r = $sign($MM, $step($q, 2));
t_eq((string) $step($q, 2)['status'], 'PENDING', 'level 2 cannot sign before level 1');
t_ok(strpos($said($r), 'Level 1 approves first') !== false, '…and is told why: ' . $said($r));
$sign($BM, $step($q, 1));
t_eq((string) $step($q, 1)['status'], 'APPROVED', 'level 1 signs');
$sign($MM, $step($q, 2));
t_eq($st($q), 'APPROVED', 'then level 2 signs, and the quotation is approved');

// ---- D-11a: a delegate covers for someone who is away ----------------------------------------
$pdo->exec("DELETE FROM quote_approval_rules"); $rule(1, 'MARKETING_MANAGER');
$q2 = $quote(); $submit($q2);
$sign($KAM, $step($q2, 1));
t_eq($st($q2), 'PENDING_APPROVAL', 'control: a Key Accounts Manager cannot sign a Marketing Manager\'s step');
$r = $drive($MM, 'quote-delegations', 'POST', [], ['do' => 'save', 'delegator_user_id' => $MM, 'delegate_user_id' => $KAM,
        'effective_from' => date('Y-m-d'), 'reason' => 'Annual leave']);
$del = ops_one("SELECT * FROM approval_delegations WHERE delegator_user_id=? AND delegate_user_id=?", [$MM, $KAM]);
t_ok($del && strtoupper((string) $del['entity']) === 'QUOTE', 'the Marketing Manager sets "while I am away, the KAM approves my quotations" on the Sales screen (' . $said($r) . ')');
$ids = (function () use ($KAM) { $_SESSION['uid'] = $KAM; current_user(true); ua(true); return crm_quote_ids_awaiting_me(); })();
t_ok(in_array($q2, $ids, true), 'the quotation now appears in the delegate\'s "waiting for me"');
$sign($KAM, $step($q2, 1));
t_eq($st($q2), 'APPROVED', 'and the delegate approves it');
$drive($MM, 'quote-delegations', 'POST', [], ['do' => 'revoke', 'id' => (int) $del['id']]);
$q3 = $quote(); $submit($q3);
$sign($KAM, $step($q3, 1));
t_eq($st($q3), 'PENDING_APPROVAL', 'once the delegation is revoked, the delegate can no longer sign');
$pdo->prepare("INSERT INTO approval_delegations (delegator_user_id, delegate_user_id, entity, active) VALUES (?,?,'',1)")->execute([$MM, $KAM]);
$sign($KAM, $step($q3, 1));
t_eq($st($q3), 'APPROVED', 'a delegation for "everything they approve" covers quotations too (the setup screen always said it did)');
$pdo->exec("DELETE FROM approval_delegations");
$drive($FIN, 'quote-delegations', 'POST', [], ['do' => 'save', 'delegator_user_id' => $MM, 'delegate_user_id' => $FIN]);
t_eq((int) ops_val("SELECT COUNT(*) FROM approval_delegations"), 0, 'Finance, who does not manage quotation approvals, cannot set a delegation');

// ---- D-11a: escalation after the time the company set ----------------------------------------
$pdo->exec("DELETE FROM quote_approval_rules"); $rule(1, 'BRANCH_MANAGER'); $rule(2, 'BUSINESS_DIRECTOR');
$pdo->prepare("UPDATE quote_approval_rules SET min_amount=1000000 WHERE level=2")->execute();   // this quotation needs only level 1
$q4 = $quote(); $submit($q4);
t_eq(count(ops_all("SELECT id FROM quote_approvals WHERE quote_id=?", [$q4])), 1, 'arranged: a quotation waiting at level 1 only');
setting_set('quote_approval_escalate_days', '0');
$pdo->prepare("UPDATE quotations SET submitted_at=? WHERE id=?")->execute([date('c', time() - 4 * 86400), $q4]);
t_eq(crm_escalate_quote_approvals(), 0, 'with escalation off, nothing moves');
setting_set('quote_approval_escalate_days', '3');
$q5 = $quote(); $submit($q5);   // submitted just now
$n = crm_escalate_quote_approvals();
t_eq((string) $step($q5, 1)['escalated_at'], '', 'a quotation submitted today is not escalated');
t_ok(trim((string) $step($q4, 1)['escalated_at']) !== '', 'one waiting four days, with "after 3 days" set, is opened to the level above');
t_eq((string) $step($q4, 1)['escalate_role'], 'BUSINESS_DIRECTOR', '…the next level in the rules, as its own chain has no level above');
$ids = (function () use ($BD) { $_SESSION['uid'] = $BD; current_user(true); ua(true); return crm_quote_ids_awaiting_me(); })();
t_ok(in_array($q4, $ids, true), 'it appears in the Business Director\'s "waiting for me"');
$sign($BD, $step($q4, 1));
t_eq($st($q4), 'APPROVED', 'and the Business Director can approve it');
t_ok(strpos((string) ops_val("SELECT GROUP_CONCAT(summary, ' | ') FROM quote_revisions WHERE quote_id=?", [$q4]), 'opened to the level above') !== false
     || (int) ops_val("SELECT COUNT(*) FROM quote_revisions WHERE quote_id=? AND summary LIKE '%opened to the level above%'", [$q4]) === 1,
     'the escalation is written in the quotation\'s history');
t_eq(crm_escalate_quote_approvals(), 0, 'running the night job again escalates nothing twice');
setting_set('quote_approval_escalate_days', '');
$pdo->exec("DELETE FROM quote_approval_rules");

// ---- the screens ------------------------------------------------------------------------------
$r = $drive($MM, 'sales-setup', 'GET', [], [], ['waits too long at one approval level', 'covers for an approver who is away']);
t_ok(!empty($r['has']['waits too long at one approval level']) && !empty($r['has']['covers for an approver who is away']),
     'Set up Sales asks when to escalate, and links to who covers for an approver');
$r = $drive($MM, 'quote-delegations', 'GET', [], [], ['Who covers for a quotation approver', 'Add delegation']);
t_ok(!empty($r['has']['Who covers for a quotation approver']), 'the delegation screen opens for the Marketing Manager, without the Recruitment module');
