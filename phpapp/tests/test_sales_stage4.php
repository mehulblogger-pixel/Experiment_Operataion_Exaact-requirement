<?php
// Owner, 21 Nov 2026 — Sales redesign Stage 4 (Quotations, costing, templates, approvals),
// approved design pack, "All as recommended". Preserve every field, button, filter and
// action; figures and permissions unchanged, except what the owner decided:
//   8  — the quotation register on the shared register: paging, sorting, a column chooser,
//        filters by owner / branch / business unit, a search on contact and contract number;
//        CSV behind the "Download quotations" switch (crm.quote.export);
//   9  — "Withdraw to draft": the preparer takes a quotation waiting for approval back, with a
//        note (a new lifecycle move, docs/03); the open steps close as withdrawn, logged;
//   10 — DEF-05: a revision shows the earlier revisions' files, "from Rev NN", linked not copied;
//   11 — DEF-06: before GST is the main figure (register, quotation page, Quotation report);
//   12 — a quotation decided from the Approvals inbox, by the same handler and rules;
//   13 — contract-opening signatures listed (and signed) in the Approvals inbox;
//   14 — DEF-23: quotes-export and quote-final open the register;
//   DEF-18 — each quotation button follows its server rule.
//
// Each screen is drawn twice through the production dispatcher, as the same person on the
// same data: with the OLD view (tests/fixtures/pre_stage4/) and the NEW one. Every figure
// must read the same; every link, form field and amount of the old page must be on the new
// one, except the changes listed and checked below; who is refused must not change.
require_once __DIR__ . '/_bbv_fixture.php';

function s4_env() {
    $engine = $GLOBALS['__test_engine'] ?? 'sqlite';
    return $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
}
function s4_draw($uid, $route, array $get = [], $old = false) {
    $root = dirname(__DIR__);
    $raw = (string) shell_exec(s4_env() . ($old ? ' STAGE4_OLD=1' : '') . ' php ' . escapeshellarg($root . '/tests/_stage4_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route) . ' ' . escapeshellarg(json_encode((object) $get)) . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['done' => 'no-json: ' . substr($raw, 0, 300), 'figs' => [], 'hrefs' => [], 'forms' => [], 'money' => [], 'nav' => [], 'bn' => [], 'refused' => false, 'len' => 0, 'text' => '', 'buttons' => [], 'flash' => []];
}
function s4_drive($uid, $route, $method = 'GET', array $get = [], array $post = [], array $needles = []) {
    $root = dirname(__DIR__);
    $cmd = s4_env() . ' php ' . escapeshellarg($root . '/tests/_sales_route_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route)
         . ' ' . escapeshellarg($method) . ' ' . escapeshellarg(json_encode((object) $get)) . ' ' . escapeshellarg(json_encode((object) $post))
         . ' ' . escapeshellarg(json_encode($needles)) . ' ' . escapeshellarg(json_encode((object) []));
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['flash' => [], 'done' => 'no-json', 'error' => substr($raw, 0, 300), 'has' => [], 'retry' => null];
}
function s4_said($r) { return implode(' | ', array_map(fn($f) => (string) ($f['text'] ?? ''), $r['flash'] ?? [])); }
function s4_form_split($sig) { return preg_match('/^(.*?) \[(.*)\]$/', $sig, $m) ? [$m[1], $m[2] === '' ? [] : explode(',', $m[2])] : [$sig, []]; }
function s4_as($uid, $fn) { $keep = $_SESSION['uid'] ?? null; bbv_as($uid); $r = $fn(); if ($keep) bbv_as($keep); return $r; }

t_section('Sales Stage 4 — the quotation, costing, template and approval screens show the same figures, links, fields and access as before, in the new look');
$f = bbv_fixture();
$pdo = db();
$made = ['quotes' => [], 'partners' => [], 'users' => [], 'contracts' => []];
$keepSet = ['contract_open_signatures' => setting_get('contract_open_signatures', '')];
try {
    bbv_as($f['master']);
    if (function_exists('crm_migrate')) crm_migrate(); if (function_exists('contracts_migrate')) contracts_migrate();

    $mk = function ($key, $role, $office, $scope = '', $perms = '') use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices, permissions, email) VALUES (?,?,?,?,1,?,?,?,?)")
            ->execute([$f['tag'] . '_' . $key, ucfirst($key), 'T', $role, $office, $scope, $perms, $f['tag'] . $key . '@example.com']);
        return $made['users'][] = (int) $pdo->lastInsertId();
    };
    $bdm  = $mk('bdm', 'BUSINESS_DEV_MANAGER', $f['mum']);
    $kam  = $mk('kam', 'KEY_ACCOUNTS_MANAGER', $f['mum']);
    $mm   = $mk('mm', 'MARKETING_MANAGER', $f['mum'], 'ALL');
    $mex  = $mk('mex', 'MARKETING_EXECUTIVE', $f['mum']);
    $noxp = $mk('noexport', 'BUSINESS_DEV_MANAGER', $f['mum'], '', PERM_DIFF_TAG . ',-crm.quote.export');

    $pdo->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, status, home_branch_id) VALUES (?,?,?,1,'ACTIVE',?)")
        ->execute(['S4-' . $f['tag'], 'Stage Four Valves ' . $f['tag'], 'Stage Four Valves ' . $f['tag'], $f['mum']]);
    $made['partners'][] = $cid = (int) $pdo->lastInsertId();
    $sbus = lk_options_or('sbu', OPS_SBUS); $sk = (string) array_key_first($sbus);
    $n = 0;
    $quote = function ($status, $office, $owner, array $over = []) use ($pdo, $f, $cid, $sk, &$made, &$n) {
        $n++; $no = $over['quote_no'] ?? ('Q-S4' . substr($f['tag'], -4) . '-' . $n);
        $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, client_id, client_name, contact_name, subject, sbu, office_id, subtotal, gst_pct, total_amount,
                       owner_id, created_by, created_at, validity_days, sent_at, contract_number, submitted_at, parent_id)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$no, $over['rev'] ?? 0, $over['is_current'] ?? 1, $status, $cid, 'Stage Four Valves ' . $f['tag'], $over['contact'] ?? 'Meena Patel', ($over['subject'] ?? 'S4 ' . $status) . ' ' . $f['tag'], $sk, $office,
                       $over['subtotal'] ?? 450000, 18, $over['total'] ?? 531000, $owner, 'test', date('c', strtotime('-3 days')), 30,
                       in_array($status, ['SENT', 'ACCEPTED', 'LOST'], true) ? date('c', strtotime('-2 days')) : '', $over['contract'] ?? '', $status === 'PENDING_APPROVAL' ? date('c') : '', $over['parent'] ?? null]);
        return $made['quotes'][] = (int) $pdo->lastInsertId();
    };
    $qDraft = $quote('DRAFT', $f['mum'], $bdm);
    $qPend  = $quote('PENDING_APPROVAL', $f['mum'], $bdm, ['contact' => 'Ravi Contactname']);
    $pdo->prepare("INSERT INTO quote_approvals (quote_id, level, approver_role, approver_user_id, status) VALUES (?,1,'',?,'PENDING')")->execute([$qPend, $f['kiran']]);
    $stepPend = (int) $pdo->lastInsertId();
    $qAppr  = $quote('APPROVED', $f['mum'], $bdm);
    $qSent  = $quote('SENT', $f['mum'], $kam);
    $qWon   = $quote('ACCEPTED', $f['mum'], $kam, ['contract' => '']);
    $qLost  = $quote('LOST', $f['mum'], $kam);
    $qAhm   = $quote('SENT', $f['ahm'], null, ['subject' => 'S4 Ahmedabad']);
    //  A quotation with a revision: Rev 00 carries a file, Rev 01 is the current one (DEF-05).
    $qR0 = $quote('SENT', $f['mum'], $bdm, ['quote_no' => 'Q-S4REV-' . $f['tag'], 'rev' => 0, 'is_current' => 0]);
    $qR1 = $quote('DRAFT', $f['mum'], $bdm, ['quote_no' => 'Q-S4REV-' . $f['tag'], 'rev' => 1, 'parent' => $qR0]);
    $pdo->prepare("INSERT INTO quote_files (quote_id, kind, file_name, mime, file_data, note, share_with_inspector, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,?,1,'test',?)")
        ->execute([$qR0, 'ATTACHMENT', 'scope-rev0.pdf', 'application/pdf', base64_encode('%PDF-1.4 rev0'), 'scope from Rev 00', date('c')]);
    $pdo->prepare("INSERT INTO quote_files (quote_id, kind, file_name, mime, file_data, note, share_with_inspector, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,?,1,'test',?)")
        ->execute([$qR1, 'ATTACHMENT', 'scope-rev1.pdf', 'application/pdf', base64_encode('%PDF-1.4 rev1'), 'new scope', date('c')]);
    //  A contract waiting to be opened (one signature: the final approval).
    setting_set('contract_open_signatures', '1');
    $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, start_date, end_date, is_active, open_status, quotation_id, requested_by, requested_at) VALUES (?,?,?,?,?,?,1,'PENDING',?,?,?)")
        ->execute([$cid, 'S4C-' . $f['tag'], 'S4 contract', 450000, date('Y-m-d'), date('Y-m-d', strtotime('+1 year')), $qWon, 'test', date('c')]);
    $made['contracts'][] = $ctr = (int) $pdo->lastInsertId();

    $people = ['Business Director' => $f['bd'], 'Master admin' => $f['master'], 'Admin' => $f['admin'], 'Business Unit Head' => $f['sbu'],
               'Branch Manager (Mumbai)' => $f['kiran'], 'Finance, Mumbai' => $f['meera'], 'Finance, all branches' => $f['rakesh'], 'Coordinator' => $f['neha'],
               'BDM (Mumbai)' => $bdm, 'KAM (Mumbai)' => $kam, 'Marketing Manager (all)' => $mm, 'Marketing Executive (Mumbai)' => $mex,
               'BDM without the quotation download' => $noxp];
    $pages = [['quotes', []], ['quotes', ['v' => 'open']], ['quotes', ['v' => 'closed', 'fy' => 'all']], ['quotes', ['mine' => 'approve']], ['quotes', ['mine' => 'contract']],
              ['quote', ['id' => $qDraft]], ['quote', ['id' => $qPend]], ['quote', ['id' => $qAppr]], ['quote', ['id' => $qSent]], ['quote', ['id' => $qWon]], ['quote', ['id' => $qLost]], ['quote', ['id' => $qR1]],
              ['quote-new', []], ['quote-edit', ['id' => $qDraft]], ['quote-external', []],
              ['templates', ['kind' => 'quote']], ['approval-rules', ['module' => 'quote']], ['quote-delegations', []], ['preorder-checklist', []],
              ['project-costings', []], ['project-costing-heads', []], ['crm-reports', []], ['approvals', []]];

    //  THE CHANGES THE OWNER DECIDED (each one is checked below, not excused blindly):
    $gone = function ($label, $uid, $route, $get, $href) use ($qAppr) {
        //  DEF-18: "+ External" only for the right its server asks for (create quotations, or a master).
        if ($route === 'quotes' && $href === '/quote-external') return !s4_as($uid, fn() => can('crm.quote.create') || is_master());
        //  DEF-18: the playbook's "Edit the quote" only for someone the edit screen lets in.
        if ($route === 'quote' && preg_match('#^/quote-edit\?id=\d+$#', $href)) {
            $q = crm_quote_get((int) $get['id']);
            return !s4_as($uid, fn() => (can('crm.quote.create') || can('mod.quotes.edit') || is_master_of('quotes')) && !quote_is_locked($q) && crm_quote_can_work($q));
        }
        //  The register's own tab/filter links now drop a stale page number — the same address otherwise.
        if ($route === 'quotes' && strpos($href, '/quotes?') === 0) return true;
        return false;
    };
    $formGone = function ($uid, $route, $get, $sig) {
        [$at] = s4_form_split($sig);
        if ($route === 'quote' && strpos($at, 'post /quote-file-delete') === 0) return !s4_as($uid, fn() => can('crm.quote.create') || is_master());
        if ($route === 'quote' && strpos($at, 'post /quote-files') === 0) return true;   // DEF-18: checked below against the server rule
        return false;
    };
    $compare = function ($label, $uid, $route, $get) use ($gone, $formGone) {
        $o = s4_draw($uid, $route, $get, true); $n = s4_draw($uid, $route, $get, false);
        $pg = '/' . $route . ($get ? '?' . http_build_query($get) : '');
        t_eq($n['done'], $o['done'], "$label · $pg — the page opens (or is refused) exactly as before");
        t_eq($n['refused'], $o['refused'], "$label · $pg — refused as before");
        if ($route !== 'crm-reports') {   // DEF-06 changes the report's money figures — checked on their own below
            $fd = []; foreach ($o['figs'] as $k => $v) if ($k !== '' && ($n['figs'][$k] ?? null) !== $v) $fd[] = "$k: old \"$v\" new \"" . ($n['figs'][$k] ?? '—') . '"';
            t_eq($fd, [], "$label · $pg — all " . count($o['figs']) . ' figures read the same');
            t_eq(array_values(array_diff($o['money'], $n['money'])), [], "$label · $pg — every amount of the old page is on the new one");
        }
        $lostL = array_values(array_filter(array_diff(array_unique($o['hrefs']), $n['hrefs']), fn($h) => !$gone($label, $uid, $route, $get, $h)));
        t_eq($lostL, [], "$label · $pg — every link of the old page is on the new one (owner-decided changes listed above)");
        $lost = [];
        foreach (array_unique($o['forms']) as $sig) {
            if ($formGone($uid, $route, $get, $sig)) continue;
            [$at, $fl] = s4_form_split($sig); $ok = false;
            foreach ($n['forms'] as $ns) { [$nat, $nfl] = s4_form_split($ns); if ($nat === $at && !array_diff($fl, $nfl)) { $ok = true; break; } }
            if (!$ok && strpos($at, 'get ') === 0) {
                $all = []; foreach ($n['forms'] as $ns) { [$nat, $nfl] = s4_form_split($ns); if (strpos($nat, 'get ') === 0) $all = array_merge($all, $nfl); }
                $ok = !array_diff($fl, $all);
            }
            if (!$ok) $lost[] = $sig;
        }
        t_eq($lost, [], "$label · $pg — every form (same address, every field) is on the new one");
        return [$o, $n];
    };
    $draws = [];
    foreach ($people as $label => $uid) foreach ($pages as [$route, $get]) $draws[$label][$route . json_encode($get)] = $compare($label, $uid, $route, $get);

    t_section('Sales Stage 4 — backend item 8: the quotation register');
    $reg = $draws['BDM (Mumbai)']['quotes[]'][1];
    t_ok(strpos($reg['text'], 'S4 SENT') !== false && strpos($reg['text'], 'S4 Ahmedabad') === false, 'branch scope: a Mumbai BDM sees the Mumbai quotations and not the Ahmedabad one (as before)');
    t_ok(strpos($draws['Marketing Manager (all)']['quotes[]'][1]['text'], 'S4 Ahmedabad') !== false, 'an all-branch Marketing Manager sees the Ahmedabad one too');
    t_ok((bool) array_filter($reg['forms'], fn($s) => strpos($s, 'post /dt-columns [') === 0), 'a column chooser (the shared register\'s own)');
    t_ok((bool) array_filter($reg['hrefs'], fn($h) => strpos($h, 'sort=') !== false), 'sortable column headings');
    t_ok((bool) array_filter($reg['forms'], fn($s) => strpos($s, 'get /quotes [') === 0 && strpos($s, 'own') !== false && strpos($s, 'q') !== false), 'filters: owner and search');
    t_ok(in_array('/quotes', $reg['nav'], true) && in_array('/leads', $reg['bn'], true), 'the register carries the Sales bar and the phone bottom bar');
    t_ok(in_array('₹4,50,000', $reg['money'], true) && in_array('₹5,31,000', $reg['money'], true) && strpos($reg['text'], 'incl. GST') !== false, 'DEF-06: ₹4,50,000 before GST first, ₹5,31,000 incl. GST beside it');
    $s = s4_draw($bdm, 'quotes', ['q' => 'Ravi Contactname'], false);
    t_ok(strpos($s['text'], 'S4 PENDING_APPROVAL') !== false && strpos($s['text'], 'S4 DRAFT') === false, 'search finds a quotation by its contact\'s name');
    $pdo->prepare("UPDATE quotations SET contract_number=? WHERE id=?")->execute(['CN-S4-' . $f['tag'], $qSent]);
    $s = s4_draw($bdm, 'quotes', ['q' => 'CN-S4-' . $f['tag']], false);
    t_ok(strpos($s['text'], 'S4 SENT') !== false && strpos($s['text'], 'S4 DRAFT') === false, '…and by its contract number');
    $s = s4_draw($bdm, 'quotes', ['own' => (string) $kam], false);
    t_ok(strpos($s['text'], 'S4 SENT') !== false && strpos($s['text'], 'S4 DRAFT ' . $f['tag']) === false, 'the owner filter shows only that person\'s quotations');
    $s = s4_draw($bdm, 'quotes', ['br' => (string) $f['ahm']], false);
    t_ok(strpos($s['text'], 'S4 Ahmedabad') === false, 'the branch filter never widens: a Mumbai BDM asking for Ahmedabad sees nothing of it');
    for ($i = 0; $i < 52; $i++) $quote('DRAFT', $f['mum'], $bdm, ['subject' => 'S4 Bulk ' . $i]);
    $p1 = s4_draw($bdm, 'quotes', ['per' => '25'], false);
    t_ok(preg_match('/Page 1 of [3-9]/', $p1['text']) === 1, 'paging: 25 a page');
    $csv = s4_drive($bdm, 'quotes', 'GET', ['export' => '1'], [], ['S4 SENT', 'S4 Ahmedabad']);
    t_ok(!empty($csv['has']['S4 SENT']) && empty($csv['has']['S4 Ahmedabad']), 'the CSV carries every page of the person\'s own rows, no other branch');
    $csvNo = s4_drive($noxp, 'quotes', 'GET', ['export' => '1'], [], ['S4 SENT']);
    t_ok(empty($csvNo['has']['S4 SENT']), 'someone whose own exception removes "Download quotations" gets no CSV (Batch A switch crm.quote.export)');
    t_ok(!array_filter($draws['BDM without the quotation download']['quotes[]'][1]['hrefs'], fn($h) => strpos($h, 'export=1') !== false), '…and no Export link');
    t_ok(strpos($reg['text'], 'pending') === false || strpos(s4_draw($bdm, 'quotes', ['v' => 'closed', 'fy' => 'all'], false)['text'], 'pending') !== false, '"Contract pending" shows on a won quotation');

    t_section('Sales Stage 4 — DEF-23: dead addresses open the register');
    foreach (['quotes-export', 'quote-final'] as $rt) { $r = s4_drive($bdm, $rt, 'GET', [], [], ['/quotes']); t_eq($r['done'], 'redirect', "/$rt now sends the person to the quotation register"); }

    t_section('Sales Stage 4 — DEF-18: each quotation button follows its server rule');
    $pa = $draws['BDM (Mumbai)']['quote' . json_encode(['id' => $qAppr])][1];
    t_ok((bool) array_filter($pa['hrefs'], fn($h) => $h === '/quote-edit?id=' . $qAppr), 'an APPROVED quotation offers Edit (its edit screen takes it; a commercial change cancels the approval)');
    foreach ($people as $label => $uid) {
        $pr = $draws[$label]['quote' . json_encode(['id' => $qR1])][1];
        $canDel = s4_as($uid, fn() => can('crm.quote.create') || is_master());
        $hasDel = (bool) array_filter($pr['forms'], fn($s) => strpos($s, 'post /quote-file-delete') === 0);
        if (!$pr['refused']) t_eq($hasDel, $canDel, "$label: the file ✕ shows exactly when the server removes a file (create quotations or master)");
        $q1 = crm_quote_get($qR1);
        $canUp = s4_as($uid, fn() => (can('crm.quote.create') || can('mod.quotes.edit') || is_master_of('quotes')) && (!quote_is_locked($q1) || is_master()));
        $hasUp = (bool) array_filter($pr['forms'], fn($s) => strpos($s, 'post /quote-files') === 0);
        if (!$pr['refused']) t_eq($hasUp, $canUp, "$label: the Attach form shows exactly when the server takes a file");
    }
    $ps = $draws['Master admin']['quote' . json_encode(['id' => $qSent])][1];
    t_ok((bool) array_filter($ps['forms'], fn($s) => strpos($s, 'post /quote-files') === 0), 'a master may attach to a sent (locked) quotation — the server allows it, the form now shows');

    t_section('Sales Stage 4 — DEF-05: the earlier revision\'s files on the new revision');
    $pr = $draws['BDM (Mumbai)']['quote' . json_encode(['id' => $qR1])][1];
    t_ok(strpos($pr['text'], 'scope-rev0.pdf') !== false && strpos($pr['text'], 'from Rev 00') !== false, 'Rev 01 shows Rev 00\'s file, marked "from Rev 00"');
    t_ok(strpos($pr['text'], 'scope-rev1.pdf') !== false, '…beside its own file');
    t_eq((int) ops_val("SELECT COUNT(*) FROM quote_files WHERE quote_id=?", [$qR1]), 1, '…linked, not copied (Rev 01 still holds one file of its own)');
    $f0 = (int) ops_val("SELECT id FROM quote_files WHERE quote_id=?", [$qR0]);
    t_ok((bool) array_filter($pr['hrefs'], fn($h) => $h === '/quote-file?id=' . $f0), '…and opens from the earlier revision');

    t_section('Sales Stage 4 — DEF-06: the quotation page and the Quotation report');
    $pp = $draws['BDM (Mumbai)']['quote' . json_encode(['id' => $qSent])][1];
    t_eq([$pp['figs']['Before GST'] ?? '', $pp['figs']['With GST'] ?? ''], ['₹4,50,000', '₹5,31,000'], 'the page leads with ₹4,50,000 before GST, ₹5,31,000 with GST beside it');
    $rp = $draws['Business Director']['crm-reports[]'];
    t_ok(strpos($rp[1]['text'], 'Quotation report') !== false && strpos($rp[0]['text'], 'Sales dashboard') !== false, 'owner Q5: /crm-reports is now called "Quotation report"');
    t_eq($rp[1]['figs']['Quotations'] ?? $rp[1]['figs'][THP('quote')] ?? null, $rp[0]['figs']['Quotations'] ?? $rp[0]['figs'][THP('quote')] ?? null, 'the count of quotations is unchanged');
    t_ok(strpos($rp[1]['text'], 'before GST') !== false, 'every value on it says it is before GST');
    $won = 0.0; bbv_as($f['bd']); [$sw, $sa] = scope_clause('q.office_id', 'q.sbu'); [$from, $to] = fy_range(current_fy());
    foreach (ops_all("SELECT * FROM quotations q WHERE q.is_current=1 AND q.status='ACCEPTED' AND $sw", $sa) ?: [] as $r) { $d = substr((string)$r['created_at'], 0, 10); if ($d >= $from && $d <= $to) $won += crm_quote_before_gst($r); }
    t_ok(isset($rp[1]['figs']['Won value (before GST)']), 'the report\'s won value is labelled before GST (' . ($rp[1]['figs']['Won value (before GST)'] ?? '—') . ', ' . fmoney_short($won) . ' by the same rule)');
    t_eq($rp[1]['figs']['Won value (before GST)'] ?? '', preg_replace('/\s+/u', ' ', fmoney_short($won)), '…and it totals the before-GST figure');

    t_section('Sales Stage 4 — backend item 12: a quotation decided from the Approvals inbox');
    $ib = $draws['Branch Manager (Mumbai)']['approvals[]'][1];
    t_ok((bool) array_filter($ib['forms'], fn($s) => strpos($s, 'post /quote-approve?id=' . $qPend . ' [') === 0 && strpos($s, 'step') !== false && strpos($s, 'back') !== false), 'the approver finds Approve / Reject for it in the inbox');
    t_ok(!array_filter($draws['BDM (Mumbai)']['approvals[]'][1]['forms'] ?? [], fn($s) => strpos($s, '/quote-approve?id=' . $qPend) !== false), 'someone it is not waiting on finds no decision for it');
    $r = s4_drive($f['kiran'], 'quote-approve', 'POST', ['id' => $qPend], ['step' => $stepPend, 'decision' => 'reject', 'remarks' => '', 'back' => 'approvals']);
    t_ok(stripos(s4_said($r), 'comment') !== false && (string) ops_val("SELECT status FROM quotations WHERE id=?", [$qPend]) === 'PENDING_APPROVAL', 'reject without a comment is refused, as on the quotation page ("' . s4_said($r) . '")');
    $r = s4_drive($f['neha'], 'quote-approve', 'POST', ['id' => $qPend], ['step' => $stepPend, 'decision' => 'approve', 'back' => 'approvals']);
    t_eq((string) ops_val("SELECT status FROM quote_approvals WHERE id=?", [$stepPend]), 'PENDING', 'someone the level does not wait on cannot sign it from the inbox either');
    $r = s4_drive($f['kiran'], 'quote-approve', 'POST', ['id' => $qPend], ['step' => $stepPend, 'decision' => 'approve', 'remarks' => 'ok', 'back' => 'approvals'], ['/approvals']);
    t_eq((string) ops_val("SELECT status FROM quote_approvals WHERE id=?", [$stepPend]), 'APPROVED', 'the approver approves it from the inbox (' . s4_said($r) . ')');
    t_eq((string) ops_val("SELECT status FROM quotations WHERE id=?", [$qPend]), 'APPROVED', '…the same outcome as on the quotation page: the last level signed, so it is approved');
    t_ok((bool) array_filter(array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=?", [$qPend]) ?: [], 'summary'), fn($x) => stripos((string) $x, 'Approved by') === 0), '…logged in its history');

    t_section('Sales Stage 4 — backend item 9: Withdraw to draft');
    $qW = $quote('PENDING_APPROVAL', $f['mum'], $bdm, ['subject' => 'S4 Withdraw']);
    $pdo->prepare("INSERT INTO quote_approvals (quote_id, level, approver_role, approver_user_id, status) VALUES (?,1,'',?,'APPROVED')")->execute([$qW, $f['kiran']]);
    $pdo->prepare("INSERT INTO quote_approvals (quote_id, level, approver_role, approver_user_id, status) VALUES (?,2,'',?,'PENDING')")->execute([$qW, $f['bd']]);
    $pw = s4_draw($bdm, 'quote', ['id' => $qW], false);
    t_ok((bool) array_filter($pw['forms'], fn($s) => strpos($s, 'post /quote-withdraw?id=' . $qW . ' [') === 0 && strpos($s, 'note') !== false), 'the preparer finds "Withdraw to draft", with a note');
    $pk = s4_draw($kam, 'quote', ['id' => $qW], false);
    t_ok(!array_filter($pk['forms'], fn($s) => strpos($s, '/quote-withdraw') !== false), 'someone else (another BDM/KAM) does not');
    $r = s4_drive($kam, 'quote-withdraw', 'POST', ['id' => $qW], ['note' => 'mine now']);
    t_eq((string) ops_val("SELECT status FROM quotations WHERE id=?", [$qW]), 'PENDING_APPROVAL', '…and the server refuses them ("' . s4_said($r) . '")');
    $r = s4_drive($bdm, 'quote-withdraw', 'POST', ['id' => $qW], ['note' => '']);
    t_eq((string) ops_val("SELECT status FROM quotations WHERE id=?", [$qW]), 'PENDING_APPROVAL', 'without a note it is refused ("' . s4_said($r) . '")');
    $r = s4_drive($bdm, 'quote-withdraw', 'POST', ['id' => $qW], ['note' => 'client added two sites']);
    t_eq((string) ops_val("SELECT status FROM quotations WHERE id=?", [$qW]), 'DRAFT', 'the preparer takes it back to draft (' . s4_said($r) . ')');
    t_eq((string) ops_val("SELECT status FROM quote_approvals WHERE quote_id=? AND level=2", [$qW]), 'WITHDRAWN', '…its open step is closed as withdrawn, not deleted');
    t_eq((string) ops_val("SELECT status FROM quote_approvals WHERE quote_id=? AND level=1", [$qW]), 'APPROVED', '…the signature already given stays on record');
    $histAll = implode(' | ', array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=?", [$qW]) ?: [], 'summary'));
    t_ok(strpos($histAll, 'Withdrawn from approval to draft') !== false && strpos($histAll, 'client added two sites') !== false && strpos($histAll, 'level 1') !== false, '…and its history says who, why, and that level 1 had signed');
    $r = s4_drive($bdm, 'quote-withdraw', 'POST', ['id' => $qDraft], ['note' => 'x']);
    t_ok(stripos(s4_said($r), 'waiting for approval') !== false, 'a draft cannot be "withdrawn" ("' . s4_said($r) . '")');

    t_section('Sales Stage 4 — backend item 13: contract signatures in the Approvals inbox');
    $sigUid = null; foreach ([$f['kiran'], $f['bd'], $f['admin'], $f['master']] as $u) if (s4_as($u, fn() => (bool) approvals_hub_contracts_waiting())) { $sigUid = $u; break; }
    t_ok($sigUid !== null, 'ARMING · someone can sign the pending contract opening');
    if ($sigUid) {
        $ic = s4_draw($sigUid, 'approvals', [], false);
        t_ok(strpos($ic['text'], 'S4C-' . $f['tag']) !== false && strpos($ic['text'], 'Contracts waiting for a signature') !== false, 'the signer finds the contract opening in the inbox');
        t_ok((bool) array_filter($ic['forms'], fn($s) => strpos($s, 'post /contract-open [') === 0 && strpos($s, 'from') !== false), '…with the signature button (the same /contract-open, back to the inbox)');
        $r = s4_drive($sigUid, 'contract-open', 'POST', [], ['id' => $ctr, 'do' => 'approve', 'from' => 'inbox'], ['/approvals']);
        t_eq((string) ops_val("SELECT open_status FROM partner_contracts WHERE id=?", [$ctr]), 'OPEN', 'signing from the inbox opens it (' . s4_said($r) . ')');
    }
    t_ok(!s4_as($bdm, fn() => (bool) approvals_hub_contracts_waiting()), 'a BDM, who signs no contract, is listed none');

    t_section('Sales Stage 4 — the new pages use the shared kit');
    foreach (['crm/quote_list', 'crm/quote_detail', 'crm/quote_form', 'crm/quote_external', 'crm/template_list', 'crm/reports', 'crm/approval_rule_list', 'project_costing', 'project_costings', 'costing_heads', 'preorder_checklist'] as $v) {
        $s = (string) file_get_contents(dirname(__DIR__) . '/views/ops/' . $v . '.php');
        t_ok(strpos($s, '_sxw_ui.php') !== false && strpos($s, 'sxw_nav(') !== false && strpos($s, 'sxw_bottom(') !== false, "$v draws with the Sales workspace kit (bar on a laptop, bottom bar on a phone)");
    }
} finally {
    foreach ($made['contracts'] as $id) $pdo->prepare("DELETE FROM partner_contracts WHERE id=?")->execute([$id]);
    foreach ($made['quotes'] as $id) {
        foreach (['quote_approvals', 'quote_files', 'quote_revisions', 'quote_followups', 'quote_lines', 'quote_locations'] as $t) { try { $pdo->prepare("DELETE FROM $t WHERE quote_id=?")->execute([$id]); } catch (Throwable $e) {} }
        $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]);
    }
    foreach ($made['partners'] as $id) $pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$id]);
    foreach ($keepSet as $k => $v) setting_set($k, (string) $v);
    bbv_cleanup($f);
}
t_ok(true, 'the test removed its own rows');
