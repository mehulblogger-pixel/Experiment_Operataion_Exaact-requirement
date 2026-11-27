<?php
// ============================================================================
//  Owner, 27 Nov 2026 — quotations (decisions 1 and 4b, QP-09, QP-10, Word format).
//
//  Q1  One person, one level: whoever signed one level of a quotation cannot sign
//      another — named, by role, as a cover or by escalation; a level left with
//      nobody is opened to someone free (level above / reporting line) or, with
//      nobody, the Master Admin. The same for project costings and the shared
//      recruitment chain. Next step names a cover "(cover: …)".
//  Q2  Send again from the company mailbox on a Sent quotation (stays Sent).
//  Q3  The system's own Word format; the company's uploaded format when chosen.
//  Q4  An unchanged Save keeps the approval when the client master was renamed or
//      a service type has since left the list; a real change still cancels it.
// ============================================================================
t_section('Owner 27 Nov — quotations');

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
$draw = function ($uid, $route, array $get) use ($root, $env) {
    $raw = (string) shell_exec($env . ' WORKER_HTML=1 php ' . escapeshellarg($root . '/tests/_stage4_worker.php') . ' ' . (int) $uid . ' '
         . escapeshellarg($route) . ' ' . escapeshellarg(json_encode($get)) . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['html' => ''];
};
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
            if ($v === null) $v = $el->getAttribute('data-cur');
            $add($n, (string) $v);
        }
    }
    return [$form !== null, $post];
};
$said = fn($r) => implode(' | ', array_map(fn($f) => (string) ($f['text'] ?? ''), $r['flash'] ?? [])) . (!empty($r['error']) ? ' [' . $r['error'] . ']' : '');
$hist = fn($qid) => implode(' ¶ ', array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=? ORDER BY id", [(int) $qid]) ?: [], 'summary'));

$pdo = db();
crm_migrate(); if (function_exists('appr_migrate')) appr_migrate(); if (function_exists('pc_migrate')) pc_migrate();
$tag = 'o27' . getmypid();
$KEYS = ['quote_approval_mode', 'quote_word_format', 'quote_self_approval'];
$wasSet = []; foreach ($KEYS as $k) $wasSet[$k] = (string) setting_get($k, '');
$rulesWere = ops_all("SELECT * FROM quote_approval_rules") ?: [];
$tplWere = array_map(fn($r) => [(int) $r['id'], (int) $r['active']], ops_all("SELECT id, active FROM crm_templates WHERE kind='QUOTE_DOC'") ?: []);
$as = function ($uid) { $_SESSION['uid'] = $uid; unset($_SESSION['uid_ws']); current_user(true); ua(true); };
$pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['O27 Office ' . $tag]);
$off = (int) $pdo->lastInsertId();
$mk = function ($first, $role) use ($pdo, $off, $tag) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,?,1,0,?)")
        ->execute([$tag . '_' . strtolower($first), $first, 'O27', '', $role, $off]);
    return (int) $pdo->lastInsertId();
};
$BDM = $mk('Bina', 'BUSINESS_DEV_MANAGER');
$BM  = $mk('Bharat', 'BRANCH_MANAGER');
$SBU = $mk('Sunil', 'SBU_HEAD');
$BD  = $mk('Dev', 'BUSINESS_DIRECTOR');
$OM  = $mk('Omkar', 'MARKETING_MANAGER');   // a person who opens quotations, above in the rules
$MX  = $mk('Meera', 'MARKETING_EXECUTIVE');
$MA  = (int) ops_val("SELECT id FROM users WHERE is_active=1 AND (role='MASTER_ADMIN' OR is_superuser=1) ORDER BY id LIMIT 1");
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES (?,?,1,'ACTIVE')")->execute(['O27 Client ' . $tag, '']);
$cli = (int) $pdo->lastInsertId();
$cname = 'O27 Client ' . $tag;
$svc = (string) array_key_first(lk_options_or('inspection_type', INSPECTION_TYPES));
$qids = []; $dels = []; $tpls = []; $pcIds = []; $rqIds = [];
$mkQ = function ($no, $status, array $x = []) use ($pdo, $BDM, $off, $cli, $cname, $svc, &$qids) {
    $cols = array_merge(['quote_no' => $no, 'rev' => 0, 'is_current' => 1, 'client_id' => $cli, 'client_name' => $cname, 'contact_email' => 'buyer@example.test',
        'subject' => 'Third-party inspection', 'status' => $status, 'sbu' => '', 'subtotal' => 100000, 'gst_pct' => 18, 'gst_amount' => 18000, 'total_amount' => 118000,
        'validity_days' => 30, 'owner_id' => $BDM, 'office_id' => $off, 'created_at' => date('c'), 'created_by' => 'Bina O27', 'terms_conditions' => '',
        'inspection_types' => $svc], $x);
    $pdo->prepare("INSERT INTO quotations (" . implode(',', array_keys($cols)) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute(array_values($cols));
    $id = (int) $pdo->lastInsertId(); $qids[] = $id;
    $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, service_type, description, qty, unit, rate, amount) VALUES (?,0,?,'Third-party inspection',10,'MANDAY',10000,100000)")->execute([$id, $svc]);
    return $id;
};
$rules = function (array $levels) use ($pdo) {
    $pdo->exec("DELETE FROM quote_approval_rules");
    $r = $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES (?, 'ANY', '', ?, 0, ?, ?, NULL, 1, ?)");
    foreach ($levels as [$lvl, $role, $min]) $r->execute(['L' . $lvl, $min, $lvl, $role, date('c')]);
};
$steps = fn($qid) => ops_all("SELECT * FROM quote_approvals WHERE quote_id=? ORDER BY level, id", [(int) $qid]) ?: [];

try {
    setting_set('quote_approval_mode', 'order');
    setting_set('quote_self_approval', 'yes');

    // ---- Q1 — one person, one level --------------------------------------------------------------
    t_section('Q1a — the QP-09 set-up: the SBU Head covers for the Director');
    $rules([[1, 'BRANCH_MANAGER', 0], [2, 'SBU_HEAD', 0], [3, 'BUSINESS_DIRECTOR', 0]]);
    $pdo->prepare("INSERT INTO approval_delegations (delegator_user_id, delegate_user_id, entity, active, created_at) VALUES (?,?,'QUOTE',1,?)")->execute([$BD, $SBU, date('c')]);
    $dels[] = (int) $pdo->lastInsertId();
    $q1 = $mkQ('O27-Q1-' . $tag, 'DRAFT');
    $x = $drive($BDM, 'quote-status', 'POST', ['id' => $q1], ['to' => 'PENDING_APPROVAL']);
    $s = $steps($q1);
    t_eq(count($s), 3, 'submitted to three levels: ' . $said($x));
    $drive($BM, 'quote-approve', 'POST', [], ['step' => $s[0]['id'], 'decision' => 'approve']);
    $w = crm_pending_with($q1);
    t_ok(strpos($w, '(cover: Sunil O27)') !== false, 'the Director\'s level names the SBU Head as cover, not as a second approver: ' . $w);
    t_eq(substr_count($w, 'Sunil O27'), 2, 'the SBU Head appears twice only as "level 2 approver" and "cover": ' . $w);
    t_ok(strpos($w, 'Dev O27 (') !== false, 'the Director is named on level 3');
    t_ok((int) ops_val("SELECT acted_by_id FROM quote_approvals WHERE id=?", [$s[0]['id']]) === $BM, 'the signer is kept by id');
    $x = $drive($SBU, 'quote-approve', 'POST', [], ['step' => $s[1]['id'], 'decision' => 'approve']);
    t_eq((string) ops_val("SELECT status FROM quote_approvals WHERE id=?", [$s[1]['id']]), 'APPROVED', 'the SBU Head signs level 2: ' . $said($x));
    $as($SBU);
    t_ok(!crm_can_act_approval(ops_one("SELECT * FROM quote_approvals WHERE id=?", [$s[2]['id']])), 'having signed level 2, the SBU Head may not sign level 3 as the Director\'s cover');
    t_ok(!in_array($q1, crm_quote_ids_awaiting_me(), true), '…and it is not on the SBU Head\'s pending list');
    $w = crm_pending_with($q1);
    t_ok(strpos($w, 'Sunil') === false && strpos($w, 'Dev O27') !== false, 'Next step now names only the Director: ' . $w);
    $x = $drive($SBU, 'quote-approve', 'POST', [], ['step' => $s[2]['id'], 'decision' => 'approve']);
    t_eq((string) ops_val("SELECT status FROM quote_approvals WHERE id=?", [$s[2]['id']]), 'PENDING', 'a typed approval of level 3 by the SBU Head is refused: ' . $said($x));
    $x = $drive($BD, 'quote-approve', 'POST', [], ['step' => $s[2]['id'], 'decision' => 'approve']);
    t_eq((string) crm_quote_get($q1)['status'], 'APPROVED', 'the Director signs level 3 — approved: ' . $said($x));
    $pdo->prepare("UPDATE approval_delegations SET active=0 WHERE id=?")->execute([end($dels)]);

    t_section('Q1b — a level whose only approver already signed is opened to someone free');
    //  Levels 1 and 2 both go to the SBU Head; a level 3 rule exists (Operation Manager) for
    //  far larger quotations only, so this quotation's own chain is two levels.
    $rules([[1, 'SBU_HEAD', 0], [2, 'SBU_HEAD', 0], [3, 'MARKETING_MANAGER', 50000000]]);
    $q2 = $mkQ('O27-Q1B-' . $tag, 'DRAFT');
    $drive($BDM, 'quote-status', 'POST', ['id' => $q2], ['to' => 'PENDING_APPROVAL']);
    $s = $steps($q2);
    t_eq(count($s), 2, 'two levels, both the SBU Head');
    $x = $drive($SBU, 'quote-approve', 'POST', [], ['step' => $s[0]['id'], 'decision' => 'approve']);
    $l2 = ops_one("SELECT * FROM quote_approvals WHERE id=?", [$s[1]['id']]);
    t_eq((int) $l2['escalate_user_id'], $OM, 'level 2 is opened at once to the level above in the rules (the Marketing Manager): ' . $said($x));
    t_ok(strpos($hist($q2), 'nobody signs two levels') !== false, 'History says why: ' . $hist($q2));
    $as($SBU); t_ok(!crm_can_act_approval($l2), 'the SBU Head may not sign level 2 after level 1');
    $as($OM);  t_ok(crm_can_act_approval($l2), 'the Marketing Manager may');
    t_ok(strpos(crm_pending_with($q2), 'Omkar O27') !== false, 'Next step names him: ' . crm_pending_with($q2));
    $x = $drive($OM, 'quote-approve', 'POST', [], ['step' => $s[1]['id'], 'decision' => 'approve']);
    t_eq((string) crm_quote_get($q2)['status'], 'APPROVED', 'he signs it — approved: ' . $said($x));

    t_section('Q1c — nobody free: the Master Admin signs');
    $rules([[1, 'SBU_HEAD', 0], [2, 'SBU_HEAD', 0]]);
    $q3 = $mkQ('O27-Q1C-' . $tag, 'DRAFT');
    $drive($BDM, 'quote-status', 'POST', ['id' => $q3], ['to' => 'PENDING_APPROVAL']);
    $s = $steps($q3);
    $drive($SBU, 'quote-approve', 'POST', [], ['step' => $s[0]['id'], 'decision' => 'approve']);
    t_ok(strpos($hist($q3), 'Master Admin signs it') !== false, 'History: only the Master Admin is left');
    t_ok(strpos(crm_pending_with($q3), 'Master Admin') !== false, 'Next step says the Master Admin: ' . crm_pending_with($q3));
    $x = $drive($SBU, 'quote-approve', 'POST', [], ['step' => $s[1]['id'], 'decision' => 'approve']);
    t_eq((string) crm_quote_get($q3)['status'], 'PENDING_APPROVAL', 'the SBU Head cannot sign the second level too: ' . $said($x));
    $x = $drive($MA, 'quote-approve', 'POST', [], ['step' => $s[1]['id'], 'decision' => 'approve']);
    t_eq((string) crm_quote_get($q3)['status'], 'APPROVED', 'the Master Admin (the last resort, not held by the rule) signs it: ' . $said($x));
    //  An older signature with no id is matched by name.
    t_eq(crm_user_signed_other_level($q3, ops_one("SELECT * FROM users WHERE id=?", [$SBU]), 2,
        [['level' => 1, 'acted_by' => 'Sunil O27', 'acted_by_id' => null]]), 1, 'an older signature (name only) still counts');

    t_section('Q1d — project costings and the recruitment chain follow the same rule');
    $pdo->prepare("INSERT INTO project_costings (code, status, office_id, created_at) VALUES (?, 'SUBMITTED', ?, ?)")->execute(['PC-O27-' . $tag, $off, date('c')]);
    $pc = (int) $pdo->lastInsertId(); $pcIds[] = $pc;
    $h = ['id' => $pc, 'office_id' => $off, 'sbu' => '', 'created_by_user_id' => $BDM, 'status' => 'SUBMITTED'];
    $ins = $pdo->prepare("INSERT INTO project_costing_approvals (costing_id, level, approver_role, approver_user_id, status, acted_by, acted_by_id) VALUES (?,?,?,?,?,?,?)");
    $ins->execute([$pc, 1, '', $SBU, 'APPROVED', 'Sunil O27', $SBU]);
    $ins->execute([$pc, 2, '', $SBU, 'PENDING', '', null]);
    $people = array_map(fn($u) => (int) $u['id'], pc_step_people(['level' => 2, 'approver_role' => '', 'approver_user_id' => $SBU, 'status' => 'PENDING'], $h));
    t_ok(!in_array($SBU, $people, true), 'a costing level named to the SBU Head, who signed level 1, goes to someone else who may approve');
    t_ok(in_array($SBU, array_map(fn($u) => (int) $u['id'], pc_step_people(['level' => 1, 'approver_role' => '', 'approver_user_id' => $SBU, 'status' => 'APPROVED'], $h)), true),
        'his own signed level still shows him');
    //  The fallback never pulls in someone who holds another waiting level (a "free person", as on quotes).
    $ins->execute([$pc, 3, '', $BD, 'PENDING', '', null]);
    $fb = array_map(fn($u) => (int) $u['id'], pc_step_people(['level' => 2, 'approver_role' => '', 'approver_user_id' => $SBU, 'status' => 'PENDING'], $h));
    t_ok(!in_array($BD, $fb, true), 'the fallback for level 2 leaves out the Business Director, who holds waiting level 3');
    t_ok($fb !== [], 'someone else may still sign level 2: ' . count($fb) . ' person(s)');
    $pdo->prepare("INSERT INTO recruit_approval_requests (entity, entity_id, status, current_seq) VALUES ('REQUISITION', 0, 'PENDING', 2)")->execute();
    $rq = (int) $pdo->lastInsertId(); $rqIds[] = $rq;
    $pdo->prepare("INSERT INTO recruit_approval_steps (request_id, seq, approver_user_id, status, acted_by, acted_by_id) VALUES (?,1,?,'APPROVED','Sunil O27',?)")->execute([$rq, $SBU, $SBU]);
    $pdo->prepare("INSERT INTO recruit_approval_steps (request_id, seq, approver_user_id, status) VALUES (?,2,?,'PENDING')")->execute([$rq, $SBU]);
    $u = ops_one("SELECT * FROM users WHERE id=?", [$SBU]);
    t_ok(!appr_can_act(['request_id' => $rq, 'seq' => 2, 'approver_user_id' => $SBU, 'status' => 'PENDING'], $u), 'a recruitment level named to him, after he signed level 1: refused');
    t_ok(appr_can_act(['request_id' => $rq, 'seq' => 1, 'approver_user_id' => $SBU, 'status' => 'PENDING'], $u), 'the level he signed is his (the rule only looks at other levels)');
    $src = (string) file_get_contents($root . '/lib/recruit_approval.php');
    t_ok(strpos($src, "acted_by=?, acted_by_id=?") !== false, 'recruitment signatures keep the signer\'s id');

    // ---- Q2 — Send again ------------------------------------------------------------------------
    t_section('Q2 — Send again from the company mailbox (QP-10)');
    $rules([]);
    $q4 = $mkQ('O27-Q2-' . $tag, 'SENT', ['sent_at' => date('c')]);
    $x = $drive($BDM, 'quote', 'GET', ['id' => $q4], [], ['✉ Send again from the company mailbox']);
    t_ok(!empty($x['has']['✉ Send again from the company mailbox']), 'a Sent quotation offers "Send again from the company mailbox" to the person who sends it');
    $x = $drive($BDM, 'quote-status', 'POST', ['id' => $q4], ['resend' => 1]);
    $ok = strpos($said($x), 'Sent again to buyer@example.test') !== false;
    t_ok($ok || strpos($said($x), 'Not sent again') !== false, 'pressed: ' . $said($x));
    t_eq((string) crm_quote_get($q4)['status'], 'SENT', 'it stays Sent');
    if ($ok) t_ok(strpos($hist($q4), 'Sent again by Bina O27 to buyer@example.test') !== false, 'History: who sent it again, to whom');
    $x = $drive($MX, 'quote-status', 'POST', ['id' => $q4], ['resend' => 1]);
    t_ok(strpos($hist($q4), 'Sent again by Meera') === false, 'someone without the send right cannot: ' . $said($x));
    $q5 = $mkQ('O27-Q2B-' . $tag, 'APPROVED');
    $x = $drive($BDM, 'quote-status', 'POST', ['id' => $q5], ['resend' => 1]);
    t_ok(strpos($said($x), 'Only the current revision of a sent') !== false && (string) crm_quote_get($q5)['status'] === 'APPROVED', 'an Approved one is not "sent again" (it is sent first): ' . $said($x));
    $x = $drive($BDM, 'quote', 'GET', ['id' => $q5], [], ['✉ Send again from the company mailbox']);
    t_ok(empty($x['has']['✉ Send again from the company mailbox']), '…and has no Send again button');

    // ---- Q3 — the system's own Word format ----------------------------------------------------------
    t_section('Q3 — the system Word format');
    $pdo->exec("UPDATE crm_templates SET active=0 WHERE kind='QUOTE_DOC'");
    setting_set('quote_word_format', 'company');
    $jpg = '';
    if (function_exists('imagecreatetruecolor')) { $im = imagecreatetruecolor(40, 20); ob_start(); imagejpeg($im); $jpg = (string) ob_get_clean(); }
    $as($BDM);
    $unzip = function ($bin) {
        $tmp = tempnam(sys_get_temp_dir(), 'o27'); file_put_contents($tmp, (string) $bin); $out = [];
        $z = new ZipArchive(); if ($z->open($tmp) === true) { for ($i = 0; $i < $z->numFiles; $i++) $out[$z->getNameIndex($i)] = (string) $z->getFromIndex($i); $z->close(); }
        @unlink($tmp); return $out;
    };
    $qa = crm_quote_get($q5);
    [$bin, $err] = quote_docx_system_build($qa, crm_quote_lines($q5), [], ['img' => $jpg, 'name' => 'Asha Signatory', 'desig' => 'Director'], quote_letterhead($off));
    $parts = $unzip($bin);
    t_ok($err === null && isset($parts['word/document.xml'], $parts['[Content_Types].xml'], $parts['word/header1.xml'], $parts['word/footer1.xml']), 'a complete .docx is built: ' . (string) $err);
    $okXml = true; foreach ($parts as $n => $c) if (substr($n, -4) === '.xml' || substr($n, -5) === '.rels') { $d = new DOMDocument(); if (!@$d->loadXML($c)) { $okXml = false; t_ok(false, 'well-formed: ' . $n); } }
    t_ok($okXml, 'every part is well-formed XML');
    $doc = (string) ($parts['word/document.xml'] ?? '');
    foreach ([quote_label($qa), $cname, 'Third-party inspection', 'Total (INR)', '1,18,000.00', 'Terms &amp; Conditions', 'Authorised Signatory', 'Asha Signatory'] as $needle)
        t_ok(strpos($doc, $needle) !== false || strpos($doc, str_replace('1,18,000.00', '118,000.00', $needle)) !== false, 'it carries "' . $needle . '"');
    t_ok($jpg === '' || preg_grep('~^word/media/image\d+\.jpeg$~', array_keys($parts)), 'the approved copy carries the signature image');
    t_ok(strpos($doc, 'Unsigned draft') === false && strpos($doc, 'NOT FOR CLIENT') === false, 'the approved copy is not marked draft');
    $qd = crm_quote_get($mkQ('O27-Q3D-' . $tag, 'PENDING_APPROVAL'));
    [$binD] = quote_docx_system_build($qd, crm_quote_lines($qd['id']), [], ['img' => $jpg, 'name' => 'Asha Signatory'], []);
    $pd = $unzip($binD);
    t_ok(strpos((string) ($pd['word/document.xml'] ?? ''), 'Unsigned draft') !== false && strpos((string) ($pd['word/document.xml'] ?? ''), 'NOT FOR CLIENT') !== false
        && !preg_grep('~^word/media/~', array_keys($pd)), 'a draft is marked and unsigned, as the PDF (the Word button refuses drafts anyway)');
    $x = $drive($BDM, 'quote-doc', 'GET', ['id' => $q5], [], ['PK']);
    t_ok(!empty($x['has']['PK']), 'with no uploaded format, the Word button downloads the system format: ' . $said($x));
    t_ok(strpos($hist($q5), 'Word (system format) downloaded') !== false, 'History names the format');
    //  The company's own format: used when chosen; the system one still on offer.
    $pdo->prepare("INSERT INTO crm_templates (kind, name, file_name, file_data, active, is_default) VALUES ('QUOTE_DOC', ?, 'own.docx', ?, 1, 1)")->execute(['O27 own ' . $tag, base64_encode($bin)]);
    $tid = (int) $pdo->lastInsertId(); $tpls[] = $tid;
    t_eq((int) (quote_word_template('')['id'] ?? 0), $tid, 'with an uploaded format, the company\'s format is used');
    t_eq(quote_word_template('system'), null, '"system" picks the system format');
    $x = $drive($BDM, 'quote-doc', 'GET', ['id' => $q5], [], ['PK']);
    t_ok(!empty($x['has']['PK']) && substr_count($hist($q5), 'Word downloaded by') === 1, 'downloaded in the company format ("Word downloaded by …" in History, as before)');
    $x = $drive($BDM, 'quote', 'GET', ['id' => $q5], [], ['Word — system format', 'Word — O27 own']);
    t_ok(!empty($x['has']['Word — system format']) && !empty($x['has']['Word — O27 own']), 'More ▾ offers every format');
    setting_set('quote_word_format', 'system');
    t_eq(quote_word_template(''), null, 'when the company chooses the system format, it is used');
    $x = $drive($MA, 'crm-templates', 'POST', [], ['word_format' => 'company']);
    t_eq((string) ops_val("SELECT svalue FROM settings WHERE skey='quote_word_format'"), 'company', 'Quote templates saves the choice: ' . $said($x));
    $x = $drive($MA, 'crm-templates', 'GET', [], [], ['Word format for the editable', 'System format']);
    t_ok(!empty($x['has']['Word format for the editable']) && !empty($x['has']['System format']), 'Quote templates shows the choice');
    $x = $drive($MX, 'quote-doc', 'GET', ['id' => $q5], [], ['PK']);
    t_ok(empty($x['has']['PK']) && strpos($said($x), 'for the people who send') !== false, 'who may download Word is unchanged (no send right, no Word): ' . $said($x));

    // ---- Q4 — an unchanged Save keeps the approval ---------------------------------------------------
    t_section('Q4 — unchanged Save after a client rename or a retired service type');
    $q6 = $mkQ('O27-Q4-' . $tag, 'APPROVED');
    $pdo->prepare("INSERT INTO quote_approvals (quote_id, level, approver_role, status, acted_by) VALUES (?,1,'BRANCH_MANAGER','APPROVED','Bharat O27')")->execute([$q6]);
    $pdo->prepare("UPDATE business_partners SET legal_name=? WHERE id=?")->execute([$cname . ' Pvt Ltd', $cli]);
    $page = $draw($BDM, 'quote-edit', ['id' => $q6]);
    [$found, $post] = $formPost((string) ($page['html'] ?? ''));
    t_ok($found, 'the edit form is drawn');
    $x = $drive($BDM, 'quote-edit', 'POST', ['id' => $q6], $post);
    $qq = crm_quote_get($q6);
    t_eq((string) $qq['status'], 'APPROVED', '(a) the client was renamed on its master record; an unchanged Save keeps the approval: ' . $said($x));
    t_eq((string) $qq['client_name'], $cname . ' Pvt Ltd', '…and the quotation now carries the master\'s name');
    //  (b) a service type that has left the list since.
    $pdo->prepare("UPDATE quote_lines SET service_type='O27_RETIRED' WHERE quote_id=?")->execute([$q6]);
    $pdo->prepare("UPDATE quotations SET inspection_types=? WHERE id=?")->execute([$svc . ',O27_RETIRED', $q6]);
    $page = $draw($BDM, 'quote-edit', ['id' => $q6]);
    [, $post] = $formPost((string) ($page['html'] ?? ''));
    $li = array_search('Third-party inspection', (array) ($post['l_desc'] ?? []), true);
    t_ok($li !== false, 'the saved line is on the form (row ' . var_export($li, true) . ')');
    $post['l_service'][$li] = '';   // what the form posts for a type it can no longer show
    t_ok(!in_array('O27_RETIRED', (array) ($post['inspection_types'] ?? []), true), 'the header cannot show the retired type, so it is not posted');
    $x = $drive($BDM, 'quote-edit', 'POST', ['id' => $q6], $post);
    t_eq((string) crm_quote_get($q6)['status'], 'APPROVED', '(b) an unchanged Save with a retired service type keeps the approval: ' . $said($x));
    t_eq((string) ops_val("SELECT service_type FROM quote_lines WHERE quote_id=? ORDER BY line_no LIMIT 1", [$q6]), 'O27_RETIRED', '…and the line keeps its stored type');
    t_ok(strpos((string) crm_quote_get($q6)['inspection_types'], 'O27_RETIRED') !== false, '…and the header keeps it');
    $page = $draw($BDM, 'quote-edit', ['id' => $q6]);   // a fresh form (the last save moved its version on)
    [, $post] = $formPost((string) ($page['html'] ?? ''));
    $li = array_search('Third-party inspection', (array) ($post['l_desc'] ?? []), true);
    $post['l_rate'][$li] = '12000';
    $x = $drive($BDM, 'quote-edit', 'POST', ['id' => $q6], $post);
    t_eq((string) crm_quote_get($q6)['status'], 'DRAFT', 'a real change (the rate) still cancels the approval: ' . $said($x));
} finally {
    foreach ($KEYS as $k) setting_set($k, $wasSet[$k]);
    $pdo->exec("DELETE FROM quote_approval_rules");
    foreach ($rulesWere as $rw) {
        $cols = array_keys($rw);
        $pdo->prepare("INSERT INTO quote_approval_rules (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute(array_values($rw));
    }
    foreach ($tpls as $id) $pdo->prepare("DELETE FROM crm_templates WHERE id=?")->execute([$id]);
    foreach ($tplWere as [$id, $act]) $pdo->prepare("UPDATE crm_templates SET active=? WHERE id=?")->execute([$act, $id]);
    foreach ($dels as $id) $pdo->prepare("DELETE FROM approval_delegations WHERE id=?")->execute([$id]);
    foreach ($qids as $id) {
        foreach (['quote_lines', 'quote_approvals', 'quote_revisions', 'quote_followups'] as $t) try { $pdo->prepare("DELETE FROM $t WHERE quote_id=?")->execute([$id]); } catch (Throwable $e) {}
        $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]);
    }
    foreach ($pcIds as $id) { $pdo->prepare("DELETE FROM project_costing_approvals WHERE costing_id=?")->execute([$id]); $pdo->prepare("DELETE FROM project_costings WHERE id=?")->execute([$id]); }
    foreach ($rqIds as $id) { $pdo->prepare("DELETE FROM recruit_approval_steps WHERE request_id=?")->execute([$id]); $pdo->prepare("DELETE FROM recruit_approval_requests WHERE id=?")->execute([$id]); }
    $pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$cli]);
    $pdo->prepare("DELETE FROM users WHERE username LIKE ?")->execute([$tag . '\_%']);
    $pdo->prepare("DELETE FROM offices WHERE id=?")->execute([$off]);
    $_SESSION = [];
}
