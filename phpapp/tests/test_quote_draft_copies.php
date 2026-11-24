<?php
// ============================================================================
//  Q-05 (owner, 24 Nov 2026: "Yes as per recommendation") — draft copies of a
//  quotation, and who gets the editable Word copy.
//
//  What is proved here:
//   1. Before it has cleared its approval, the quotation's PDF carries a large
//      "DRAFT — NOT FOR CLIENT" across every page; the approved copy is unchanged.
//   2. The editable Word copy is offered only once it has cleared, and only to
//      people who send quotations (crm.quote.send — the existing right).
//   3. Every PDF and Word download is written to the quotation's History: who,
//      when, which format, draft or approved copy.
//   4. Sending still needs approval — in the app, and "send from my mailbox".
//   5. Managers can still read a draft (PDF, marked); nothing about the approval
//      rules changes.
// ============================================================================
t_section('Q-05 — draft quotation copies are marked; the Word copy waits for approval');

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
$said = fn($r) => implode(' | ', array_map(fn($f) => (string)($f['text'] ?? ''), $r['flash'] ?? [])) . (isset($r['error']) ? ' [' . $r['error'] . ']' : '');

$pdo = db();
crm_migrate();
$prevSession = $_SESSION ?? [];
$as = function ($uid) { $_SESSION['uid'] = $uid; unset($_SESSION['uid_ws']); current_user(true); ua(true); };
$tag = 'qdc' . getmypid();
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['QDC Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$mk = function ($first, $role) use ($pdo, $tag, $off) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
        ->execute([$tag . '_' . strtolower($first), $first, 'Qdc', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$KAM = $mk('Kiran', 'KEY_ACCOUNTS_MANAGER');     // sends quotations; does not approve
$MEX = $mk('Meera', 'MARKETING_EXECUTIVE');      // prepares quotations; does not send
$MM  = $mk('Manoj', 'MARKETING_MANAGER');        // approves and sends
//  An approval level that covers every value, so a draft needs approval.
$pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES (?, 'ANY', '', 0, 0, 1, 'MARKETING_MANAGER', NULL, 1, ?)")
    ->execute([$tag . ' level', date('c')]);
$ruleId = (int) $pdo->lastInsertId();
$n = 0;
$mkQ = function ($status, array $x = []) use ($pdo, $tag, &$n, $KAM, $off) {
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, client_name, contact_email, status, is_current, sbu, subtotal, gst_pct, gst_amount, total_amount, validity_days, owner_id, office_id, created_at, sent_at)
                   VALUES (?,0,?,?,?,1,'',100000,18,18000,118000,30,?,?,?,?)")
        ->execute([strtoupper($tag) . '-' . (++$n), 'QDC Client', 'buyer@example.test', $status, $KAM, $off, date('c'), (string)($x['sent_at'] ?? '')]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, description, qty, unit, rate, amount) VALUES (?,1,'Third-party inspection',10,'MANDAY',10000,100000)")->execute([$id]);
    return $id;
};
$Q = fn($id) => crm_quote_get($id);
$hist = fn($id, $like) => (int) ops_val("SELECT COUNT(*) FROM quote_revisions WHERE quote_id=? AND summary LIKE ?", [$id, $like]);
//  A small Word template of our own (inactive — it is asked for by id, so no other test sees it).
$docx = '';
if (class_exists('ZipArchive')) {
    $tmp = tempnam(sys_get_temp_dir(), 'qdc'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE);
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
    $z->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>{{quote_no}}</w:t></w:r></w:p></w:body></w:document>');
    $z->close(); $docx = (string) file_get_contents($tmp); @unlink($tmp);
}
$pdo->prepare("INSERT INTO crm_templates (kind, name, file_name, file_data, active, is_default, created_at) VALUES ('QUOTE_DOC', ?, 'qdc.docx', ?, 0, 0, ?)")
    ->execute([$tag . ' template', base64_encode($docx), date('c')]);
$tplId = (int) $pdo->lastInsertId();

try {
    $qDraft = $mkQ('DRAFT'); $qPend = $mkQ('PENDING_APPROVAL'); $qAppr = $mkQ('APPROVED'); $qSent = $mkQ('SENT', ['sent_at' => date('c')]);
    $qAcc = $mkQ('ACCEPTED', ['sent_at' => date('c')]); $qLostSent = $mkQ('LOST', ['sent_at' => date('c')]); $qLostDraft = $mkQ('LOST');

    // ---- 1. which copies are drafts ----------------------------------------------------------------
    t_ok(crm_quote_needs_approval($Q($qDraft)), 'ARMING · a draft of this value needs approval under the rules');
    t_eq([crm_quote_doc_cleared($Q($qDraft)), crm_quote_doc_cleared($Q($qPend))], [false, false], 'a draft, and one waiting for approval, have not cleared');
    t_eq([crm_quote_doc_cleared($Q($qAppr)), crm_quote_doc_cleared($Q($qSent)), crm_quote_doc_cleared($Q($qAcc))], [true, true, true], 'approved, sent and accepted ones have');
    t_eq([crm_quote_doc_cleared($Q($qLostSent)), crm_quote_doc_cleared($Q($qLostDraft))], [true, false], 'a lost one counts as cleared only if it had gone to the client');
    $noRule = $Q($qDraft); $noRule['status'] = 'DRAFT';
    t_eq(crm_quote_doc_cleared($noRule), !crm_quote_needs_approval($noRule), 'a draft the rules let go out with no approval is cleared — the same test the Send button uses');

    // ---- 2. the PDF ----------------------------------------------------------------------------------
    t_section('Q-05 — the draft PDF is marked; the approved PDF is unchanged');
    $build = fn($id) => quote_pdf_build($Q($id), crm_quote_lines($id), [], [], []);
    $pd = $build($qDraft); $pa = $build($qAppr);
    t_ok(strpos($pd, 'NOT FOR CLIENT) Tj') !== false && strpos($pd, 'DRAFT ') !== false, 'the draft PDF carries "DRAFT — NOT FOR CLIENT"');
    t_ok(strpos($pa, 'NOT FOR CLIENT') === false && strpos($pa, ' Tm (') === false, 'the approved PDF carries no mark at all');
    $pp = $build($qPend);
    t_ok(strpos($pp, 'NOT FOR CLIENT) Tj') !== false, 'so does one waiting for approval');
    preg_match('#/F2 (\d+) Tf [\d.\- ]+ Tm \(DRAFT#', $pd, $m);
    t_ok(isset($m[1]) && (int) $m[1] < 74 && (int) $m[1] >= 24, 'the long mark is sized to fit across the page (font ' . ($m[1] ?? '?') . ')');
    $short = new SimplePDF(); $short->watermark = 'DRAFT'; $short->text(40, 'x');
    t_ok(strpos($short->output(), '/F2 74 Tf 0.7071 0.7071 -0.7071 0.7071 122.64 300.94 Tm (DRAFT) Tj') !== false, 'a short mark (an inspection report\'s DRAFT) is drawn exactly as before');

    // ---- 3. the download routes and the History ------------------------------------------------------
    t_section('Q-05 — every download is written to the History');
    $r = $drive($KAM, 'quote-pdf', 'GET', ['id' => $qDraft], [], ['%PDF', 'NOT FOR CLIENT) Tj']);
    t_ok(!empty($r['has']['%PDF']) && !empty($r['has']['NOT FOR CLIENT) Tj']), 'a draft downloads as a marked PDF: ' . $said($r));
    t_eq($hist($qDraft, 'PDF downloaded by Kiran Qdc — draft copy, marked "DRAFT — NOT FOR CLIENT".'), 1, 'the History says who downloaded it, that it was the PDF, and that it was the draft copy');
    $row = ops_one("SELECT * FROM quote_revisions WHERE quote_id=? AND summary LIKE 'PDF downloaded%' ORDER BY id DESC LIMIT 1", [$qDraft]);
    t_ok($row && (string) $row['changed_by'] === 'Kiran Qdc' && substr((string) $row['changed_at'], 0, 10) === date('Y-m-d'), '…with who and when on the line');
    t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='QUOTE' AND entity_id=? AND subject LIKE ?", [$qDraft, '%PDF downloaded by Kiran Qdc%']) === 1, '…and on the quotation\'s activity record');
    $r = $drive($KAM, 'quote-pdf', 'GET', ['id' => $qAppr], [], ['%PDF', 'NOT FOR CLIENT']);
    t_ok(!empty($r['has']['%PDF']) && empty($r['has']['NOT FOR CLIENT']), 'an approved one downloads unmarked');
    t_eq($hist($qAppr, 'PDF downloaded by Kiran Qdc — approved copy.'), 1, 'and the History says it was the approved copy');
    $r = $drive($MEX, 'quote-pdf', 'GET', ['id' => $qDraft], [], ['%PDF', 'NOT FOR CLIENT) Tj']);
    t_ok(!empty($r['has']['NOT FOR CLIENT) Tj']) && $hist($qDraft, 'PDF downloaded by Meera Qdc%') === 1, 'someone who cannot send still reads the draft, marked — and that is logged too');

    // ---- 4. the Word copy --------------------------------------------------------------------------
    t_section('Q-05 — the Word copy: only once approved, only for those who send');
    $r = $drive($KAM, 'quote-doc', 'GET', ['id' => $qDraft, 'tpl' => $tplId], [], ['PK']);
    t_ok(strpos($said($r), 'ready once this') !== false && empty($r['has']['PK']), 'a sender cannot download the Word copy of a draft: ' . $said($r));
    $r = $drive($KAM, 'quote-doc', 'GET', ['id' => $qPend, 'tpl' => $tplId], [], ['PK']);
    t_ok(strpos($said($r), 'ready once this') !== false && empty($r['has']['PK']), '…nor of one waiting for approval');
    t_eq($hist($qDraft, 'Word downloaded%') + $hist($qPend, 'Word downloaded%'), 0, 'a refused Word download writes nothing as downloaded');
    $r = $drive($MEX, 'quote-doc', 'GET', ['id' => $qAppr, 'tpl' => $tplId], [], ['PK']);
    t_ok(strpos($said($r), 'for the people who send') !== false && empty($r['has']['PK']), 'someone without the send right gets no Word copy, even when approved: ' . $said($r));
    if ($docx !== '') {
        $r = $drive($KAM, 'quote-doc', 'GET', ['id' => $qAppr, 'tpl' => $tplId], [], ['PK']);
        t_ok(!empty($r['has']['PK']) && $said($r) === '', 'a sender downloads the Word copy once it is approved');
        t_eq($hist($qAppr, 'Word downloaded by Kiran Qdc — approved copy.'), 1, 'and the History says so');
        $r = $drive($MM, 'quote-doc', 'GET', ['id' => $qAcc, 'tpl' => $tplId], [], ['PK']);
        t_ok(!empty($r['has']['PK']), 'and of an accepted one (the final copy)');
    }
    $as($KAM); t_eq([crm_quote_word_allowed($Q($qDraft)), crm_quote_word_allowed($Q($qAppr))], [false, true], 'the sender: no Word on the draft, Word on the approved one');
    $as($MEX); t_eq([crm_quote_word_allowed($Q($qDraft)), crm_quote_word_allowed($Q($qAppr))], [false, false], 'the preparer without the send right: no Word at all');
    $page = (string) file_get_contents($root . '/views/ops/crm/quote_detail.php');
    $guarded = substr_count($page, '<?php if (crm_quote_word_allowed($q)): ?><a') + substr_count($page, '<?php if ($qdWord): ?><a');
    t_eq(substr_count($page, 'href="/quote-doc?id='), $guarded, 'every Word button on the quotation page is drawn only when the Word copy is allowed');
    t_ok(strpos($page, '⬇ PDF (draft)') !== false, 'the quotation page labels the PDF of a draft "PDF (draft)"');

    // ---- 5. sending still needs approval -----------------------------------------------------------
    t_section('Q-05 — sending still needs approval');
    $r = $drive($KAM, 'quote-status', 'POST', ['id' => $qPend], ['to' => 'SENT']);
    t_ok((string) $Q($qPend)['status'] === 'PENDING_APPROVAL' && $said($r) !== '', 'a quotation waiting for approval cannot be sent from the app: ' . $said($r));
    $r = $drive($KAM, 'quote-status', 'POST', ['id' => $qDraft], ['to' => 'SENT']);
    t_ok((string) $Q($qDraft)['status'] === 'DRAFT' && strpos($said($r), 'needs approval') !== false, 'nor a draft that needs approval: ' . $said($r));
    $r = $drive($KAM, 'quote-compose', 'GET', ['id' => $qDraft]);
    t_ok(strpos($said($r), 'needs approval') !== false && $hist($qDraft, 'Opened in%') === 0, '"send from my mailbox" is refused for a draft too: ' . $said($r));
    $r = $drive($KAM, 'quote-compose', 'GET', ['id' => $qAppr], [], ['Content-Type: application/pdf', 'NOT FOR CLIENT']);
    t_ok($hist($qAppr, 'Opened in%mailbox to send by hand') === 1 && empty($r['has']['NOT FOR CLIENT']), 'an approved one opens in the mailbox, with the unmarked PDF: ' . $said($r));
    t_ok(strpos((string) file_get_contents($root . '/lib/crm.php'), 'function crm_quote_transition_problem') !== false && crm_quote_transition_problem($Q($qPend), 'SENT') !== '', 'the approval rules themselves are untouched — the same refusal as before');
} finally {
    $ids = array_map('intval', array_column(ops_all("SELECT id FROM quotations WHERE quote_no LIKE ?", [strtoupper($tag) . '-%']) ?: [], 'id'));
    foreach ($ids as $id) {
        $pdo->prepare("DELETE FROM quote_revisions WHERE quote_id=?")->execute([$id]);
        try { $pdo->prepare("DELETE FROM activities WHERE entity_kind='QUOTE' AND entity_id=?")->execute([$id]); } catch (Throwable $e) {}
        $pdo->prepare("DELETE FROM quote_approvals WHERE quote_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM quote_lines WHERE quote_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]);
    }
    $pdo->prepare("DELETE FROM quote_approval_rules WHERE id=?")->execute([$ruleId]);
    $pdo->prepare("DELETE FROM crm_templates WHERE id=?")->execute([$tplId]);
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
    $_SESSION = $prevSession; current_user(true); ua(true);
}
