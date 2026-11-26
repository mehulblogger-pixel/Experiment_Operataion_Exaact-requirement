<?php
// ============================================================================
//  Owner, 26 Nov 2026 — "go ahead and fix everything as recommended" (Batch A:
//  quotations and contracts).
//
//  A1  An unchanged Save on an APPROVED quotation keeps its approval (DEF-02).
//      A real change of terms still cancels it (D-07 unchanged).
//  A2  A draft PDF is printed UNSIGNED ("Unsigned draft"); an earlier revision
//      prints "SUPERSEDED — see Rev NN", its Word copy and "Send from my
//      mailbox" are refused.
//  A3  The contract number is a link to the contract page — on the Quotes
//      register, the quote's Contract panel and Sales → Orders & payments —
//      for people who may open contracts; plain text for others.
//  A4  Contract signatures: any number (7 proved end to end), no cap of five.
//  A5  The government-GSTIN example passes its own check character.
// ============================================================================
t_section('Owner 26 Nov — quotations and contracts');

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
//  The page itself (for the edit form), drawn by the production dispatcher.
$draw = function ($uid, $route, array $get) use ($root, $env) {
    $raw = (string) shell_exec($env . ' WORKER_HTML=1 php ' . escapeshellarg($root . '/tests/_stage4_worker.php') . ' ' . (int) $uid . ' '
         . escapeshellarg($route) . ' ' . escapeshellarg(json_encode($get)) . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['html' => ''];
};
//  Every field of the quotation edit form, as a browser would post it untouched.
$formPost = function ($html) {
    $dom = new DOMDocument(); libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?>' . $html); $xp = new DOMXPath($dom);
    $form = null;
    foreach ($xp->query('//form') as $f) if (strpos($f->getAttribute('action'), 'quote-edit') !== false) { $form = $f; break; }
    $post = [];
    $add = function ($n, $v) use (&$post) { if (substr($n, -2) === '[]') $post[substr($n, 0, -2)][] = $v; else $post[$n] = $v; };
    if ($form) foreach ($xp->query('.//input|.//select|.//textarea', $form) as $el) {
        $n = $el->getAttribute('name'); if ($n === '' || $el->hasAttribute('disabled')) continue;
        $tag = strtolower($el->nodeName);
        if ($tag === 'input') {
            $t = strtolower($el->getAttribute('type') ?: 'text');
            if (in_array($t, ['checkbox', 'radio'], true)) { if ($el->hasAttribute('checked')) $add($n, $el->getAttribute('value') ?: 'on'); continue; }
            if (in_array($t, ['submit', 'button', 'file'], true)) continue;
            $add($n, $el->getAttribute('value'));
        } elseif ($tag === 'textarea') $add($n, $el->textContent);
        else {
            $v = null;
            foreach ($xp->query('.//option', $el) as $o) { if ($v === null) $v = $o->getAttribute('value'); if ($o->hasAttribute('selected')) $v = $o->getAttribute('value'); }
            //  A list the page fills by script carries its value in data-cur.
            if ($v === null) $v = $el->getAttribute('data-cur');
            $add($n, (string) $v);
        }
    }
    return [$form !== null, $post];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string) ($f['text'] ?? ''), $r['flash'] ?? [])) . (!empty($r['error']) ? ' [' . $r['error'] . ']' : '');

$pdo = db();
crm_migrate(); contracts_migrate();
$tag = 'o26' . getmypid();
$KEYS = ['contract_open_signatures', 'contract_same_person', 'quote_terms'];
foreach (range(1, 12) as $i) { $KEYS[] = 'contract_sig' . $i . '_roles'; $KEYS[] = 'contract_sig' . $i . '_user'; }
$wasSet = []; foreach ($KEYS as $k) $wasSet[$k] = (string) setting_get($k, '');
$rulesWere = ops_all("SELECT * FROM quote_approval_rules") ?: [];
$as = function ($uid) { $_SESSION['uid'] = $uid; unset($_SESSION['uid_ws']); current_user(true); ua(true); };
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['O26 Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$mk = function ($first, $role) use ($pdo, $off, $tag) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
        ->execute([$tag . '_' . strtolower($first), $first, 'O26', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$BDM = $mk('Bina', 'BUSINESS_DEV_MANAGER');
$BM  = $mk('Bharat', 'BRANCH_MANAGER');
$BD  = $mk('Dev', 'BUSINESS_DIRECTOR');
$OM  = $mk('Omkar', 'OPERATION_MANAGER');
$FIN = $mk('Farah', 'FINANCE');
$KAM = $mk('Kiran', 'KEY_ACCOUNTS_MANAGER');
$SBU = $mk('Sunil', 'SBU_HEAD');
$MM  = $mk('Mona', 'MARKETING_MANAGER');
$AD1 = $mk('Arun', 'ADMIN');
$AD2 = $mk('Asha', 'ADMIN');
$INS = $mk('Indu', 'INSPECTOR');
$MA  = (int) ops_val("SELECT id FROM users WHERE is_active=1 AND (role='MASTER_ADMIN' OR is_superuser=1) ORDER BY id LIMIT 1");
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES (?,?,1,'ACTIVE')")->execute(['O26 Client ' . $tag, 'O26 Client ' . $tag]);
$cli = (int) $pdo->lastInsertId();
$cname = 'O26 Client ' . $tag;
//  A service the line form offers (the form posts only what it lists).
$svc = (string) array_key_first(lk_options_or('inspection_type', INSPECTION_TYPES));
$qids = [];
$mkQ = function ($no, $status, array $x = []) use ($pdo, $BDM, $off, $cli, $cname, $svc, &$qids) {
    $cols = array_merge(['quote_no' => $no, 'rev' => 0, 'is_current' => 1, 'client_id' => $cli, 'client_name' => $cname, 'contact_email' => 'buyer@example.test',
        'subject' => 'Third-party inspection', 'status' => $status, 'sbu' => '', 'subtotal' => 100000, 'gst_pct' => 18, 'gst_amount' => 18000, 'total_amount' => 118000,
        'validity_days' => 30, 'owner_id' => $BDM, 'office_id' => $off, 'created_at' => date('c'), 'created_by' => 'Bina O26', 'terms_conditions' => ''], $x);
    $pdo->prepare("INSERT INTO quotations (" . implode(',', array_keys($cols)) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute(array_values($cols));
    $id = (int) $pdo->lastInsertId(); $qids[] = $id;
    $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, service_type, description, qty, unit, rate, amount) VALUES (?,1,?,'Third-party inspection',10,'MANDAY',10000,100000)")->execute([$id, $svc]);
    return $id;
};

try {
    $pdo->exec("DELETE FROM quote_approval_rules");
    $r = $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES (?, 'ANY', '', 0, 0, ?, ?, NULL, 1, ?)");
    $r->execute(['L1', 1, 'BRANCH_MANAGER', date('c')]); $r->execute(['L2', 2, 'BUSINESS_DIRECTOR', date('c')]);

    // ---- A1 — an unchanged Save keeps the approval ------------------------------------------------
    t_section('A1 — Save with no change on an approved quotation (DEF-02)');
    t_eq(crm_terms_norm("1. Pay in 30 days.  \r\n2. GST extra.\r\n\r\n"), "1. Pay in 30 days.\n2. GST extra.", 'terms compare as text: Windows line breaks and trailing spaces do not count');
    $q1 = $mkQ('O26-A1-' . $tag, 'DRAFT');            // saved with NO terms — like one made from a costing or a deal
    $drive($BDM, 'quote-status', 'POST', ['id' => $q1], ['to' => 'PENDING_APPROVAL']);
    $steps = ops_all("SELECT * FROM quote_approvals WHERE quote_id=? ORDER BY level", [$q1]) ?: [];
    t_eq(count($steps), 2, 'submitted to two levels');
    if (count($steps) === 2) {
        $drive($BM, 'quote-approve', 'POST', [], ['step' => $steps[0]['id'], 'decision' => 'approve']);
        $drive($BD, 'quote-approve', 'POST', [], ['step' => $steps[1]['id'], 'decision' => 'approve']);
    }
    t_eq((string) crm_quote_get($q1)['status'], 'APPROVED', 'approved by both levels');
    $page = $draw($BDM, 'quote-edit', ['id' => $q1]);
    [$found, $post] = $formPost((string) ($page['html'] ?? ''));
    t_ok($found, 'the edit form is drawn for the preparer');
    t_eq(trim((string) ($post['terms_conditions'] ?? '')), '', 'the edit form shows the terms as saved (none) — the standard terms are not filled into an existing quotation');
    $x = $drive($BDM, 'quote-edit', 'POST', ['id' => $q1], $post);
    t_eq((string) crm_quote_get($q1)['status'], 'APPROVED', 'Save with no change keeps the approval: ' . $said($x));
    t_eq((int) ops_val("SELECT COUNT(*) FROM quote_approvals WHERE quote_id=?", [$q1]), 2, '…and its approval steps');
    //  The same text with Windows line breaks is the same terms.
    $pdo->prepare("UPDATE quotations SET terms_conditions=? WHERE id=?")->execute(["1. Pay in 30 days.\n2. GST extra.", $q1]);
    $post['terms_conditions'] = "1. Pay in 30 days.  \r\n2. GST extra.\r\n";
    $x = $drive($BDM, 'quote-edit', 'POST', ['id' => $q1], $post);
    t_eq((string) crm_quote_get($q1)['status'], 'APPROVED', 'the same terms re-saved with Windows line breaks keep the approval: ' . $said($x));
    //  A real change of terms still cancels it (D-07).
    $post['terms_conditions'] = "1. Pay in 45 days.\n2. GST extra.";
    $x = $drive($BDM, 'quote-edit', 'POST', ['id' => $q1], $post);
    t_eq((string) crm_quote_get($q1)['status'], 'DRAFT', 'a real change of terms still cancels the approval: ' . $said($x));
    //  A NEW quotation still gets the company's standard terms in the box.
    $page = $draw($BDM, 'quote-new', []);
    $std = trim(explode("\n", crm_default_terms())[0]);
    t_ok(strpos((string) ($page['html'] ?? ''), e($std)) !== false, 'a new quotation still starts with the standard terms ("' . $std . '")');
    //  Made from a deal or a project costing: starts with the standard terms like the form.
    $q1b = $mkQ('O26-A1B-' . $tag, 'DRAFT');
    crm_quote_seed_terms($q1b);
    t_eq(crm_terms_norm(crm_quote_get($q1b)['terms_conditions']), crm_terms_norm(crm_default_terms()), 'a quotation made outside the form is given the standard terms');
    $pdo->prepare("UPDATE quotations SET terms_conditions='Our own words' WHERE id=?")->execute([$q1b]);
    crm_quote_seed_terms($q1b);
    t_eq((string) crm_quote_get($q1b)['terms_conditions'], 'Our own words', '…never over terms it already has');
    $src = (string) file_get_contents($root . '/lib/projcosting.php') . (string) file_get_contents($root . '/lib/opportunities.php');
    t_eq(substr_count($src, 'crm_quote_seed_terms($qid)'), 3, 'the project-costing path and both deal paths seed the terms');

    // ---- A2 — unsigned drafts and superseded revisions ---------------------------------------------
    t_section('A2 — an unsigned draft; an earlier revision says SUPERSEDED');
    $jpg = '';
    if (function_exists('imagecreatetruecolor')) { $im = imagecreatetruecolor(40, 20); ob_start(); imagejpeg($im); $jpg = (string) ob_get_clean(); }
    $sig = ['img' => $jpg, 'name' => 'Asha Signatory', 'desig' => 'Director'];
    $q2 = $mkQ('O26-A2-' . $tag, 'DRAFT');
    $as($BDM);
    $draftPdf = quote_pdf_build(crm_quote_get($q2), crm_quote_lines($q2), [], $sig, []);
    t_ok(strpos($draftPdf, 'Unsigned draft') !== false, 'the draft PDF says "Unsigned draft" where the signature goes');
    t_ok($jpg === '' || strpos($draftPdf, '/Subtype /Image') === false, 'the draft PDF carries no signature image');
    t_ok(strpos($draftPdf, 'NOT FOR CLIENT') !== false, '…and is still marked DRAFT — NOT FOR CLIENT');
    $pdo->prepare("UPDATE quotations SET status='APPROVED' WHERE id=?")->execute([$q2]);
    $okPdf = quote_pdf_build(crm_quote_get($q2), crm_quote_lines($q2), [], $sig, []);
    t_ok(strpos($okPdf, 'Unsigned draft') === false && strpos($okPdf, 'NOT FOR CLIENT') === false, 'the approved PDF is unmarked and not called unsigned');
    t_ok($jpg === '' || strpos($okPdf, '/Subtype /Image') !== false, 'the approved PDF carries the signature image');
    $x = $drive($BM, 'quote-pdf', 'GET', ['id' => $q2], [], ['%PDF', 'Unsigned draft']);
    t_ok(!empty($x['has']['%PDF']) && empty($x['has']['Unsigned draft']), 'downloaded as the Branch Manager: the approved copy');

    //  Rev 00 approved, then Rev 01 made: Rev 00 is an earlier revision.
    $no = 'O26-A2R-' . $tag;
    $r0 = $mkQ($no, 'APPROVED', ['is_current' => 0]);
    $r1 = $mkQ($no, 'DRAFT', ['rev' => 1, 'parent_id' => $r0]);
    $q0 = crm_quote_get($r0);
    t_ok(crm_quote_superseded($q0) && !crm_quote_superseded(crm_quote_get($r1)), 'Rev 00 is superseded, Rev 01 is not');
    t_eq(crm_quote_superseded_mark($q0), 'SUPERSEDED — see Rev 01', 'the mark names the revision that replaced it');
    $old = ['is_current' => null] + $q0;
    t_ok(!crm_quote_superseded($old), 'an older row with no revision flag counts as current, as everywhere else');
    $oldPdf = quote_pdf_build($q0, crm_quote_lines($r0), [], $sig, []);
    t_ok(strpos($oldPdf, 'SUPERSEDED') !== false && strpos($oldPdf, 'NOT FOR CLIENT') === false, 'the earlier revision prints SUPERSEDED on its pages');
    $x = $drive($BDM, 'quote-pdf', 'GET', ['id' => $r0], [], ['%PDF', 'SUPERSEDED']);
    t_ok(!empty($x['has']['%PDF']) && !empty($x['has']['SUPERSEDED']), 'downloaded: the earlier revision is marked');
    $logged = implode(' ', array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=?", [$r0]) ?: [], 'summary'));
    t_ok(strpos($logged, 'earlier revision, marked "SUPERSEDED') !== false, 'the download is in its History: ' . $logged);
    $x = $drive($MA, 'quote-doc', 'GET', ['id' => $r0], [], ['PK']);
    t_ok(empty($x['has']['PK']) && strpos($said($x), 'earlier revision') !== false && strpos($said($x), 'Rev 01') !== false, 'its Word copy is refused, even for the Master Admin: ' . $said($x));
    $as($MA);
    t_ok(!crm_quote_word_allowed($q0) && crm_quote_word_allowed(crm_quote_get($q2)), 'the Word button is not offered on an earlier revision; it is on the approved current one');
    $x = $drive($MA, 'quote-compose', 'GET', ['id' => $r0]);
    t_ok(strpos($said($x), 'earlier revision') !== false, '"Send from my mailbox" on an earlier revision is refused: ' . $said($x));
    $x = $drive($MA, 'quote-status', 'POST', ['id' => $r0], ['to' => 'SENT', 'by_hand' => 1]);
    t_eq((string) crm_quote_get($r0)['status'], 'APPROVED', 'and it cannot be marked sent: ' . $said($x));
    $x = $drive($BDM, 'quote', 'GET', ['id' => $r0], [], ['PDF (earlier revision)', 'mode=gmail', 'Three ways to send this', 'Word (editable)']);
    t_ok(!empty($x['has']['PDF (earlier revision)']) && empty($x['has']['mode=gmail']) && empty($x['has']['Three ways to send this']) && empty($x['has']['Word (editable)']), 'its page offers the marked PDF only: ' . json_encode($x['has']));

    // ---- A3 — the contract number is a link ------------------------------------------------------
    t_section('A3 — contract number opens the contract (O-06)');
    $cno = 'O26C-' . strtoupper($tag);
    $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, start_date, end_date, branch_id, open_status, is_active) VALUES (?,?,?,?,?,?,?, 'OPEN', 1)")
        ->execute([$cli, $cno, 'O26 contract', 100000, '2026-04-01', '2027-03-31', $off]);
    $cid = (int) $pdo->lastInsertId();
    $q3 = $mkQ('O26-A3-' . $tag, 'ACCEPTED', ['contract_number' => $cno, 'contract_id' => $cid, 'accepted_date' => date('Y-m-d')]);
    $as($FIN);
    t_eq(contract_link_html($cno, $cid), '<a href="/contract?id=' . $cid . '" title="Open the contract">' . e($cno) . '</a>', 'for someone who may open contracts: a link');
    t_eq(contract_link_html($cno), '<a href="/contract?id=' . $cid . '" title="Open the contract">' . e($cno) . '</a>', '…found by its number when the id is not at hand');
    t_eq(contract_link_html('NO-SUCH-' . $tag), e('NO-SUCH-' . $tag), 'a number with no contract row stays plain text');
    $as($INS);
    t_ok(!contract_can_view() && contract_link_html($cno, $cid) === e($cno), 'for someone who may not open contracts: plain text');
    $link = '/contract?id=' . $cid;
    $x = $drive($BDM, 'quote', 'GET', ['id' => $q3], [], [$link]);
    t_ok(!empty($x['has'][$link]), 'the quote\'s Contract panel links to it');
    $x = $drive($BDM, 'quotes', 'GET', ['q' => 'O26-A3-' . $tag], [], [$link]);
    t_ok(!empty($x['has'][$link]), 'the Quotes register links to it');
    $x = $drive($BDM, 'sales-orders', 'GET', ['p' => 'fy', 'who' => 'all'], [], [$link, '<th>Contract</th>']);
    t_ok(!empty($x['has']['<th>Contract</th>']), 'Orders & payments has a Contract column');
    t_ok(!empty($x['has'][$link]), '…linking to the contract: ' . json_encode($x['has']) . ' ' . $said($x));

    // ---- A4 — seven signatures, one after another ---------------------------------------------------
    t_section('A4 — any number of contract signatures (7 proved)');
    foreach ($KEYS as $k) if (strpos($k, 'contract_') === 0) setting_set($k, '');
    $as($MA);
    $sec = array_values(array_filter(setupqa_sales_set()['sections'] ?? [], fn($s) => ($s['tab'] ?? '') === 'contract'))[0] ?? ['questions' => []];
    $qs = array_column($sec['questions'], null, 'key');
    t_eq(($qs['contract_open_signatures']['type'] ?? ''), 'number', 'the number of signatures is typed, not picked from 0–5');
    $set = ['title' => 'Sales', 'sections' => [$sec]];
    setupqa_save($set, ['contract_open_signatures' => 'abc']);
    setupqa_save($set, ['contract_open_signatures' => '-3']);
    t_eq(contract_open_rule()['signatures'], 2, 'a number that is not a whole number is ignored (standard two stays)');
    $changed = setupqa_save($set, [
        'contract_open_signatures' => '7',
        'contract_sig3_sent' => '1', 'contract_sig3_roles' => ['FINANCE'],
        'contract_sig4_sent' => '1', 'contract_sig4_roles' => ['ADMIN'],
        'contract_sig5_sent' => '1', 'contract_sig5_roles' => ['SBU_HEAD'],
        'contract_sig6_sent' => '1', 'contract_sig6_roles' => ['BUSINESS_DIRECTOR'],
        'contract_sig7_sent' => '1', 'contract_sig7_user' => (string) $AD2,
    ]);
    t_eq(contract_open_rule()['signatures'], 7, 'seven saved: ' . implode(' · ', $changed));
    t_ok(strpos(implode(' ', $changed), '7 — one after another') !== false && strpos(implode(' ', $changed), 'Signature 6') !== false, 'the history says what changed, in words');
    t_eq(contract_sig_order(7), [1, 3, 4, 5, 6, 7, 2], 'the order: first, five in between, then the final approval');
    t_eq(contract_open_rule()['sig'][7]['user'], $AD2, 'signature 6 is given by the person named');
    t_ok(strpos(contract_rule_sentence(), '7 signatures, one after another') === 0, 'the rule reads: ' . contract_rule_sentence());
    setupqa_save($set, ['contract_open_signatures' => (string) (CONTRACT_SIG_HARD_LIMIT + 1)]);
    t_eq(contract_open_rule()['signatures'], 7, 'a number above the technical safety limit (' . CONTRACT_SIG_HARD_LIMIT . ') is not saved');
    //  The settings screen: a box for every signature, and the follow-up question for 2 or more.
    ob_start(); $sections = [$sec]; include $root . '/views/ops/_setup_sections.php'; $scr = (string) ob_get_clean();
    t_ok(strpos($scr, 'type="number"') !== false && strpos($scr, 'name="contract_open_signatures" value="7"') !== false, 'the screen shows the number box with 7');
    t_ok(strpos($scr, 'Who gives signature 6?') !== false && strpos($scr, 'name="contract_sig7_sent"') !== false, 'a box for signature 6 (slot 7)');
    t_ok(strpos($scr, 'name="contract_sig12_sent"') !== false && strpos($scr, 'data-only-value="12+"') !== false, 'spare boxes appear as soon as a larger number is typed');
    t_ok(strpos($scr, 'data-only-value="2+"') !== false, '"may one person give more than one" is asked for two or more');

    $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, start_date, end_date, branch_id, open_status, is_active, requested_by, requested_at)
                   VALUES (?,?,?,?,?,?,?, 'PENDING', 0, 'Farah O26', ?)")->execute([$cli, $cno . '-7', 'Seven-signature probe', 100000, '2026-04-01', '2027-03-31', $off, date('c')]);
    $c7 = (int) $pdo->lastInsertId();
    $C = fn($id) => ops_one("SELECT * FROM partner_contracts WHERE id=?", [$id]);
    $mid = fn($id) => (int) ops_val("SELECT COUNT(*) FROM contract_signatures WHERE contract_id=? AND amendment_id=0 AND COALESCE(voided_at,'')=''", [$id]);
    $x = $drive($OM, 'contract-open', 'POST', [], ['id' => $c7, 'do' => 'endorse']);
    t_eq((int) $C($c7)['mgr_endorsed_by_id'], $OM, 'the first signature: ' . $said($x));
    $x = $drive($AD1, 'contract-open', 'POST', [], ['id' => $c7, 'do' => 'sign', 'slot' => 4]);
    t_ok($mid($c7) === 0 && strpos($said($x), 'not the one waiting now') !== false, 'signature 3 before signature 2 is refused: ' . $said($x));
    $x = $drive($BM, 'contract-open', 'POST', [], ['id' => $c7, 'do' => 'approve']);
    t_eq((string) $C($c7)['open_status'], 'PENDING', 'the final approval before the ones in between is refused');
    foreach ([[3, $FIN], [4, $AD1], [5, $SBU], [6, $BD], [7, $AD2]] as $k => [$slot, $who]) {
        $x = $drive($who, 'contract-open', 'POST', [], ['id' => $c7, 'do' => 'sign', 'slot' => $slot]);
        t_eq($mid($c7), $k + 1, 'signature ' . ($slot - 1) . ' of 7 given: ' . $said($x));
        t_eq((string) $C($c7)['open_status'], 'PENDING', '…still PENDING');
    }
    $as($BM); $sg = contract_open_stage($C($c7));
    t_eq([$sg['need'], $sg['primary']], ['final', 'approve'], 'six of seven given: the Branch Manager is offered the final approval');
    $x = $drive($BM, 'contract-open', 'POST', [], ['id' => $c7, 'do' => 'approve']);
    t_eq((string) $C($c7)['open_status'], 'OPEN', 'the seventh signature opens it: ' . $said($x));

    // ---- A5 — the example GSTIN passes its own check --------------------------------------------------
    t_section('A5 — example GSTINs');
    t_eq(commercial_gstin_check_char('07DELM12345D1D'), 'L', 'the government example 07DELM12345D1DL is a valid GSTIN');
    $msg = custid_govt_gstin_problem('07DELM');
    t_ok(strpos($msg, '07DELM12345D1DL') !== false, 'the help text quotes the valid example: ' . $msg);
    t_eq(custid_govt_gstin_problem('07DELM12345D1DL'), '', 'and that example is accepted');
} finally {
    foreach ($KEYS as $k) setting_set($k, $wasSet[$k]);
    $pdo->exec("DELETE FROM quote_approval_rules");
    foreach ($rulesWere as $rw) {
        $cols = array_keys($rw);
        $pdo->prepare("INSERT INTO quote_approval_rules (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute(array_values($rw));
    }
    foreach ($qids as $id) {
        foreach (['quote_lines', 'quote_approvals'] as $t) $pdo->prepare("DELETE FROM $t WHERE quote_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]);
    }
    foreach (array_map('intval', array_column(ops_all("SELECT id FROM partner_contracts WHERE partner_id=?", [$cli]) ?: [], 'id')) as $id)
        $pdo->prepare("DELETE FROM contract_signatures WHERE contract_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM partner_contracts WHERE partner_id=?")->execute([$cli]);
    $pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$cli]);
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
    $_SESSION = [];
}
