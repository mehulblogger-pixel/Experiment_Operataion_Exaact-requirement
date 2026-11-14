<?php
// ============================================================================
//  Owner's quotation notes, 14 Nov 2026 — driven as real logins.
//
//  1. "Suppose I cannot submit quote for approval then why I am being shown submit
//      quote for approval."
//  2. "The business director in any case shall approve the quote at last only after
//      approval of all the other reporting directly or indirectly to him … top
//      management or top approver shall approve the quote at last only whatever the
//      level setup is."
//  3. "Send to customer button not found why? and when send from my mail box it
//      downloaded .eml file why? what is use of it."
//  4. "There are two document tabs. When clicked on costing it takes one step down
//      below the costing box."
// ============================================================================
t_section('Quote 14 Nov — 1. "Submit for approval" only for someone who may submit');

$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$drive = function ($uid, $route, $method = 'POST', array $get = [], array $post = [], array $needles = []) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode(array_values($needles)));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => []];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? [])) . (!empty($r['error']) ? ' [' . $r['error'] . ']' : '');

$pdo = db(); crm_migrate(); pc_migrate();
$tag = 'q14n' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['Q14N Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$mkU = function ($first, $role, $reportsTo = null) use ($pdo, $off, $tag) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id, reports_to_id) VALUES (?,?,?,?,?,1,0,?,?)")
        ->execute([$tag . '_' . strtolower($first), $first, 'Q14', strtolower($first) . '.' . $tag . '@example.test', $role, $off, $reportsTo]);
    return (int) $pdo->lastInsertId();
};
$BD  = $mkU('Bharat', 'BUSINESS_DIRECTOR');
$SBU = $mkU('Sunita', 'SBU_HEAD', $BD);
$BM  = $mkU('Bimal', 'BRANCH_MANAGER', $SBU);
$MM  = $mkU('Meera', 'MARKETING_MANAGER', $BM);
$BDM = $mkU('Bala', 'BUSINESS_DEV_MANAGER', $MM);
$MEX = $mkU('Mohan', 'MARKETING_EXECUTIVE', $MM);

$rulesWere = ops_all("SELECT id, active FROM quote_approval_rules") ?: [];
$modeWas = setting_get('quote_approval_mode', null);
$pdo->exec("UPDATE quote_approval_rules SET active=0");
$ri = $pdo->prepare("INSERT INTO quote_approval_rules (name, level, min_amount, max_amount, approver_role, active, match_type, sbu, created_at) VALUES (?,?,0,0,?,1,'ANY','',?)");
$newRules = [];
//  The owner's own setup on the test copy: BM, MM, BD, then the SBU Head at level 4.
foreach ([[1, 'BRANCH_MANAGER'], [2, 'MARKETING_MANAGER'], [3, 'BUSINESS_DIRECTOR'], [4, 'SBU_HEAD']] as [$l, $r]) { $ri->execute(['Q14N L' . $l, $l, $r, date('c')]); $newRules[] = (int) $pdo->lastInsertId(); }
$madeQ = [];
$mkQ = function ($owner) use ($pdo, $off, $tag, &$madeQ) {
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_name, contact_email, subject, status, office_id, sbu, subtotal, gst_pct, gst_amount, total_amount, owner_id, created_by, created_at)
                   VALUES (?,0,1,'Q14N Client','buyer@example.test','Inspection','DRAFT',?,'',100000,18,18000,118000,?,'q14n',?)")
        ->execute([strtoupper($tag) . '-' . count($madeQ), $off, $owner, date('c')]);
    $id = (int) $pdo->lastInsertId(); $madeQ[] = $id;
    $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, description, qty, unit, rate, amount) VALUES (?,1,'Inspection',1,'MANDAY',100000,100000)")->execute([$id]);
    return $id;
};
$st = fn($id) => (string) ops_val("SELECT status FROM quotations WHERE id=?", [$id]);
$step = fn($id, $lvl) => ops_one("SELECT * FROM quote_approvals WHERE quote_id=? AND level=?", [$id, $lvl]);

try {
    $q = $mkQ($BDM);
    $g = $drive($BM, 'quote', 'GET', ['id' => $q], [], ['>Submit for approval<', 'that is not yours to do']);
    t_ok(empty($g['has']['>Submit for approval<']), 'the Branch Manager (who may not submit) is not shown "Submit for approval"' . $said($g));
    t_ok(!empty($g['has']['that is not yours to do']), '…the page says who submits it instead');
    $r = $drive($BM, 'quote-status', 'POST', ['id' => $q], ['to' => 'PENDING_APPROVAL']);
    t_eq($st($q), 'DRAFT', '…and a forced submit is still refused at the door: ' . $said($r));
    $g = $drive($BDM, 'quote', 'GET', ['id' => $q], [], ['>Submit for approval<']);
    t_ok(!empty($g['has']['>Submit for approval<']), 'the BDM who prepared it is shown "Submit for approval"');
    //  Something that would refuse the submission: the button gives way to the reason.
    $pdo->prepare("DELETE FROM quote_lines WHERE quote_id=?")->execute([$q]);
    $why = crm_quote_transition_problem(crm_quote_get($q), 'PENDING_APPROVAL');
    $g = $drive($BDM, 'quote', 'GET', ['id' => $q], [], ['>Submit for approval<', 'Not ready to submit']);
    t_ok($why === '' || (empty($g['has']['>Submit for approval<']) && !empty($g['has']['Not ready to submit'])),
        'a quotation that would be refused shows why, not a button that fails' . ($why !== '' ? ': ' . $why : ' (nothing refuses an empty quotation here)'));
    $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, description, qty, unit, rate, amount) VALUES (?,1,'Inspection',1,'MANDAY',100000,100000)")->execute([$q]);

    t_section('Quote 14 Nov — 2. top management signs last, whatever the level setup');
    setting_set('quote_approval_mode', 'together');
    $r = $drive($BDM, 'quote-status', 'POST', ['id' => $q], ['to' => 'PENDING_APPROVAL']);
    t_eq($st($q), 'PENDING_APPROVAL', 'submitted: ' . $said($r));
    t_ok(stripos($said($r), 'then, last, level 3') !== false, '…the message names the Business Director (level 3) as signing last');
    $mails = fn($uid) => (int) ops_val("SELECT COUNT(*) FROM email_log WHERE to_addr=(SELECT email FROM users WHERE id=?) AND subject LIKE ?", [$uid, 'Approval needed:%' . strtoupper($tag) . '%']);
    t_eq($mails($BD), 0, 'the Business Director is not e-mailed yet');
    //  The reporting line, at the same time: Bimal (BM) reports to Sunita (SBU Head), so Sunita waits for him;
    //  Meera (MM) reports to Bimal, so Bimal waits for Meera.
    t_eq(crm_quote_open_levels($q), [2], 'at the same time, nobody signs before the people who report to them: Meera (MM, level 2) first');
    t_ok($mails($MM) >= 1 && $mails($BM) === 0, '…so only Meera is asked now');
    $g = $drive($BD, 'quote', 'GET', ['id' => $q], [], ['value="approve"', 'Approve opens when the levels before yours have signed']);
    t_ok(empty($g['has']['value="approve"']) && !empty($g['has']['Approve opens when the levels before yours have signed']), 'the Business Director has no Approve button yet — the page says when it opens');
    $r = $drive($BD, 'quote-approve', 'POST', [], ['step' => (int) $step($q, 3)['id'], 'decision' => 'approve']);
    t_ok($step($q, 3)['status'] === 'PENDING' && stripos($said($r), 'signs last') !== false, 'a forced early approval by the Business Director is refused: ' . $said($r));
    $drive($MM, 'quote-approve', 'POST', [], ['step' => (int) $step($q, 2)['id'], 'decision' => 'approve']);
    t_eq(crm_quote_open_levels($q), [1], 'Meera signed → Bimal (BM) is next');
    t_ok($mails($BM) >= 1, '…and is e-mailed now that it is his turn');
    $drive($BM, 'quote-approve', 'POST', [], ['step' => (int) $step($q, 1)['id'], 'decision' => 'approve']);
    t_eq(crm_quote_open_levels($q), [4], 'Bimal signed → Sunita (SBU Head, level 4) — though numbered above the Business Director');
    t_eq($mails($BD), 0, 'the Business Director is still not asked');
    $drive($SBU, 'quote-approve', 'POST', [], ['step' => (int) $step($q, 4)['id'], 'decision' => 'approve']);
    t_eq(crm_quote_open_levels($q), [3], 'every other level signed → the Business Director, last');
    t_ok($mails($BD) >= 1, '…who is e-mailed now');
    $r = $drive($BD, 'quote-approve', 'POST', [], ['step' => (int) $step($q, 3)['id'], 'decision' => 'approve']);
    t_eq($st($q), 'APPROVED', 'the Business Director signs last and it is approved: ' . $said($r));

    //  One after another: the same — the Business Director moves to the end.
    setting_set('quote_approval_mode', 'order');
    $q2 = $mkQ($BDM);
    $drive($BDM, 'quote-status', 'POST', ['id' => $q2], ['to' => 'PENDING_APPROVAL']);
    $seq = [];
    for ($i = 0; $i < 4 && ($open = crm_quote_open_levels($q2)); $i++) {
        $seq[] = $open[0];
        $who = [1 => $BM, 2 => $MM, 3 => $BD, 4 => $SBU][$open[0]];
        $drive($who, 'quote-approve', 'POST', [], ['step' => (int) $step($q2, $open[0])['id'], 'decision' => 'approve']);
    }
    t_eq($seq, [1, 2, 4, 3], 'one after another: levels 1, 2, 4, then the Business Director (3) last');
    t_eq($st($q2), 'APPROVED', '…and it is approved');
    //  Taking back an approval below the top: the top's approval, given on the strength of it, is reset too.
    $pdo->prepare("UPDATE quotations SET status='APPROVED' WHERE id=?")->execute([$q2]);
    $r = $drive($SBU, 'quote-unapprove', 'POST', ['id' => $q2], ['reason' => 'wrong client']);
    t_ok($step($q2, 4)['status'] === 'PENDING' && $step($q2, 3)['status'] === 'PENDING', 'the SBU Head takes back: the Business Director\'s later approval is reset with it — ' . $said($r));

    //  Without top management in the chain, the highest level signs last.
    $steps = [['level' => 1, 'status' => 'PENDING', 'approver_role' => 'BRANCH_MANAGER'], ['level' => 2, 'status' => 'PENDING', 'approver_role' => 'SBU_HEAD']];
    t_eq(appr_open_levels($steps, fn($s) => [], 'together'), [1], 'no Business Director in the chain: the highest level (2) still signs last');
    t_eq(appr_open_levels([['level' => 1, 'status' => 'PENDING', 'approver_role' => 'BRANCH_MANAGER']], fn($s) => [], 'together'), [1], 'a single level signs at once');

    t_section('Quote 14 Nov — 3. who sends, and the three ways to send');
    $q3 = $mkQ($MEX); $pdo->prepare("UPDATE quotations SET status='APPROVED' WHERE id=?")->execute([$q3]);
    $g = $drive($MEX, 'quote', 'GET', ['id' => $q3], [], ['sending is not yours to do', 'Open in Gmail', 'Outlook (.eml)', 'Three ways to send this', '✉ Send to']);
    t_ok(!empty($g['has']['sending is not yours to do']) && empty($g['has']['Open in Gmail']) && empty($g['has']['Outlook (.eml)']) && empty($g['has']['Three ways to send this']) && empty($g['has']['✉ Send to']),
        'a Marketing Executive (who may not send) sees who sends it — and no sending buttons or "ways to send" box');
    $r = $drive($MEX, 'quote-compose', 'GET', ['id' => $q3], [], ['You cannot send']);
    t_ok(!empty($r['has']['You cannot send']) || stripos($said($r), 'cannot send') !== false, '…and the mailbox route refuses them too');
    $sender = in_array('crm.quote.send', role_perms('BUSINESS_DEV_MANAGER') ?: [], true) ? $BDM : $MM;
    t_ok(in_array('crm.quote.send', role_perms(ops_val("SELECT role FROM users WHERE id=?", [$sender])) ?: [], true), 'ARMING · the sender holds the send right');
    t_ok(!in_array('crm.quote.send', role_perms('MARKETING_EXECUTIVE') ?: [], true), 'ARMING · a Marketing Executive does not (docs/02: "Cannot: send or approve quotes")');
    $g = $drive($sender, 'quote', 'GET', ['id' => $q3], [], ['Three ways to send this', '✉ Send to', 'Open in Gmail', 'Outlook (.eml)', 'I have sent it — mark sent', '.eml']);
    foreach (['Three ways to send this', '✉ Send to', 'Open in Gmail', 'Outlook (.eml)', 'I have sent it — mark sent', '.eml'] as $n) t_ok(!empty($g['has'][$n]), 'someone who may send sees: ' . $n);
    t_ok(str_starts_with(compose_gmail('a@b.test', 'Quotation Q-1', 'Hello'), 'https://mail.google.com/mail/?view=cm&fs=1&to=a%40b.test&su=Quotation%20Q-1&body=Hello'), '"Open in Gmail" opens a written Gmail message: ' . compose_gmail('a@b.test', 'Quotation Q-1', 'Hello'));
    $before = (int) ops_val("SELECT COUNT(*) FROM email_log WHERE to_addr='buyer@example.test'");
    $r = $drive($sender, 'quote-status', 'POST', ['id' => $q3], ['to' => 'SENT', 'by_hand' => '1']);
    t_eq($st($q3), 'SENT', '"I have sent it — mark sent": the quotation is marked sent — ' . $said($r));
    t_eq((int) ops_val("SELECT COUNT(*) FROM email_log WHERE to_addr='buyer@example.test'"), $before, '…and the client is NOT e-mailed a second time from here');

    t_section('Quote 14 Nov — 4. one Documents button; a jump lands on its section');
    $g = $drive($BDM, 'quote', 'GET', ['id' => $q], [], ['scroll-margin-top:96px']);
    $g2 = $drive($BDM, 'quote', 'GET', ['id' => $q], [], ['title="Attach the enquiry e-mail, PO, QAP, scope of inspection or any document received">📎 Documents']);
    t_ok(empty($g2['has']['title="Attach the enquiry e-mail, PO, QAP, scope of inspection or any document received">📎 Documents']), 'the second "📎 Documents" button (in the title row) is gone — the one in "On this quote" stays');
    t_ok(!empty($g['has']['scroll-margin-top:96px']), 'a jump from "On this quote" (Costing, Documents …) lands with the section in view, below the top bar');
} finally {
    setting_set('quote_approval_mode', $modeWas ?? 'order');
    foreach ($madeQ as $id) {
        foreach (['quote_approvals', 'quote_lines', 'quote_revisions', 'quote_followups'] as $t) try { $pdo->prepare("DELETE FROM $t WHERE quote_id=?")->execute([$id]); } catch (Throwable $e) {}
        $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]);
    }
    foreach ($newRules as $id) $pdo->prepare("DELETE FROM quote_approval_rules WHERE id=?")->execute([$id]);
    foreach ($rulesWere as $r) $pdo->prepare("UPDATE quote_approval_rules SET active=? WHERE id=?")->execute([(int) $r['active'], (int) $r['id']]);
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
}
