<?php
// ============================================================================
//  Q-02 (owner, 24 Nov 2026) — the quotation page in its clean layout.
//
//  The owner approved: (1) Approve / Reject move into the "Next step" card at the top
//  (the Approval chain table still shows who approved and when); (2) the six-step
//  playbook goes to the end of Overview, folded; and kept the Sales KPI cards (before
//  GST · GST with its CGST + SGST / IGST split · with GST · validity) directly under the
//  header and the step bar. Same handler, same permission checks, same forms.
//
//  Each quotation page is drawn twice through the production dispatcher, as the same
//  person on the same data: with the page as it was (tests/fixtures/pre_q02/) and the
//  new one. Proved here:
//   1. nothing is lost — every link, form (same address, every field), amount and figure
//      of the old page is on the new one — and nothing hidden appears: the new page has
//      no link or form the old one did not have for that person (the only new link is the
//      "Decide in Next step" jump, and only for the person the approval waits on);
//   2. the step bar reads the status (done / now / stopped) for every state;
//   3. the Next step card carries the one right button for the person and the state;
//   4. the KPI cards are there, in order, with the page's own figures, under the step bar;
//   5. the playbook is at the end of Overview and starts folded;
//   6. Approve / Reject are in the Next step card only for the person the step waits on
//      (by the server's own rule, crm_can_act_approval), and Approve waits for the
//      levels before;
//   7. Q-05 still holds: a draft PDF is marked, the Word copy only once approved and only
//      for those who send;
//   8. the wording a Finance user saw on a won quotation ("Inspection inspection calls")
//      reads once.
// ============================================================================
require_once __DIR__ . '/_bbv_fixture.php';

function qp2_env() {
    $engine = $GLOBALS['__test_engine'] ?? 'sqlite';
    return $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
}
function qp2_draw($uid, $id, $old = false) {
    $root = dirname(__DIR__);
    $raw = (string) shell_exec(qp2_env() . ' WORKER_HTML=1' . ($old ? ' QPAGE_OLD=1' : '') . ' php ' . escapeshellarg($root . '/tests/_stage4_worker.php') . ' ' . (int) $uid . ' quote '
        . escapeshellarg(json_encode(['id' => (int) $id])) . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['done' => 'no-json: ' . substr($raw, 0, 300), 'figs' => [], 'hrefs' => [], 'forms' => [], 'money' => [], 'refused' => false, 'text' => '', 'html' => '', 'buttons' => []];
}
//  Whether the OLD page showed this person the quotation at all (someone it is not open to is sent
//  elsewhere, before and after alike — that is checked; the layout checks then skip them).
function qp2_shown($old) { return !$old['refused'] && strpos((string) ($old['html'] ?? ''), 'sxqd-hd') !== false; }
function qp2_as($uid, $fn) { $keep = $_SESSION['uid'] ?? null; bbv_as($uid); $r = $fn(); if ($keep) bbv_as($keep); return $r; }
function qp2_split($sig) { return preg_match('/^(.*?) \[(.*)\]$/', $sig, $m) ? [$m[1], $m[2] === '' ? [] : explode(',', $m[2])] : [$sig, []]; }
//  The page as a DOM, and the pieces the checks look at.
function qp2_dom($html) {
    $d = new DOMDocument(); libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="utf-8"?>' . $html); return new DOMXPath($d);
}
function qp2_cls($c) { return "contains(concat(' ',normalize-space(@class),' '),' $c ')"; }
function qp2_forms_in($x, $ctx) {
    $o = [];
    foreach ($x->query('.//form', $ctx) as $f) {
        $names = []; foreach ($x->query('.//*[@name]', $f) as $in) if ($in->getAttribute('name') !== '_csrf') $names[] = $in->getAttribute('name');
        sort($names); $o[] = strtolower($f->getAttribute('method') ?: 'get') . ' ' . $f->getAttribute('action') . ' [' . implode(',', $names) . ']';
    }
    return $o;
}

t_section('Q-02 — the quotation page, clean layout: nothing lost, nothing hidden shown, the right button at the top');
$f = bbv_fixture();
$pdo = db();
$made = ['quotes' => [], 'partners' => [], 'users' => []];
try {
    bbv_as($f['master']);
    if (function_exists('crm_migrate')) crm_migrate(); if (function_exists('contracts_migrate')) contracts_migrate();
    $mk = function ($key, $role, $office, $scope = '') use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices, email) VALUES (?,?,?,?,1,?,?,?)")
            ->execute([$f['tag'] . '_q2' . $key, ucfirst($key), 'Q2', $role, $office, $scope, $f['tag'] . 'q2' . $key . '@example.com']);
        return $made['users'][] = (int) $pdo->lastInsertId();
    };
    $bdm = $mk('bdm', 'BUSINESS_DEV_MANAGER', $f['mum']);
    $kam = $mk('kam', 'KEY_ACCOUNTS_MANAGER', $f['mum']);
    $mex = $mk('mex', 'MARKETING_EXECUTIVE', $f['mum']);
    $pdo->prepare("INSERT INTO business_partners (code, legal_name, display_name, is_client, status, home_branch_id) VALUES (?,?,?,1,'ACTIVE',?)")
        ->execute(['Q2-' . $f['tag'], 'Q2 Pumps ' . $f['tag'], 'Q2 Pumps ' . $f['tag'], $f['mum']]);
    $made['partners'][] = $cid = (int) $pdo->lastInsertId();
    $sk = (string) array_key_first(lk_options_or('sbu', OPS_SBUS));
    $n = 0;
    $quote = function ($status, $owner, array $over = []) use ($pdo, $f, $cid, $sk, &$made, &$n) {
        $n++;
        $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, status, client_id, client_name, contact_name, subject, sbu, office_id, subtotal, gst_pct, gst_amount, total_amount,
                       owner_id, created_by, created_at, validity_days, sent_at, contract_number, submitted_at, rejected_by)
                       VALUES (?,0,1,?,?,?,?,?,?,?,?,18,?,?,?,?,?,30,?,'',?,?)")
            ->execute(['Q-Q2' . substr($f['tag'], -4) . '-' . $n, $status, $cid, 'Q2 Pumps ' . $f['tag'], 'Asha Rao', 'Q2 ' . $status . ' ' . $f['tag'], $sk, $f['mum'],
                       200000, 36000, 236000, $owner, 'test', date('c', strtotime('-3 days')),
                       in_array($status, ['SENT', 'ACCEPTED', 'LOST', 'EXPIRED'], true) ? date('c', strtotime($status === 'EXPIRED' ? '-60 days' : '-2 days')) : '',
                       $status === 'PENDING_APPROVAL' ? date('c') : '', $status === 'REJECTED' ? 'Kiran' : '']);
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO quote_lines (quote_id, line_no, description, qty, unit, rate, amount) VALUES (?,1,'Third-party inspection',20,'MANDAY',10000,200000)")->execute([$id]);
        return $made['quotes'][] = $id;
    };
    //  An approval level that covers every value, so a draft needs approval (its PDF is a marked draft).
    $pdo->prepare("INSERT INTO quote_approval_rules (name,match_type,sbu,min_amount,max_amount,level,approver_role,approver_user_id,active,created_at) VALUES (?, 'ANY', '', 0, 0, 1, 'BRANCH_MANAGER', NULL, 1, ?)")
        ->execute([$f['tag'] . ' q2 level', date('c')]);
    $ruleId = (int) $pdo->lastInsertId();
    $Q = [];
    foreach (['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'SENT', 'ACCEPTED', 'LOST', 'REJECTED', 'EXPIRED'] as $s) $Q[$s] = $quote($s, $s === 'SENT' || $s === 'ACCEPTED' ? $kam : $bdm);
    //  Two approval levels on the submitted one: level 1 with the branch manager, level 2 with the Business Director.
    $pdo->prepare("INSERT INTO quote_approvals (quote_id, level, approver_role, approver_user_id, status) VALUES (?,1,'',?,'PENDING')")->execute([$Q['PENDING_APPROVAL'], $f['kiran']]);
    $step1 = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quote_approvals (quote_id, level, approver_role, approver_user_id, status) VALUES (?,2,'',?,'PENDING')")->execute([$Q['PENDING_APPROVAL'], $f['bd']]);
    $step2 = (int) $pdo->lastInsertId();

    $people = ['BDM (preparer)' => $bdm, 'KAM' => $kam, 'Marketing Executive' => $mex, 'Branch Manager (level 1)' => $f['kiran'], 'Business Director (level 2)' => $f['bd'],
               'Finance, Mumbai' => $f['meera'], 'Coordinator' => $f['neha'], 'Master admin' => $f['master']];
    $draws = [];
    foreach ($people as $label => $uid) foreach ($Q as $st => $id) {
        $o = qp2_draw($uid, $id, true); $nw = qp2_draw($uid, $id, false);
        $draws[$label][$st] = [$o, $nw];
        $pg = "$label · $st";
        t_eq([$nw['done'], $nw['refused']], [$o['done'], $o['refused']], "$pg — opens (or is refused) exactly as before");
        if (!qp2_shown($o)) { t_ok(!qp2_shown($nw) || $nw['refused'], "$pg — not shown the quotation before, not shown it now"); continue; }
        //  1. nothing lost …
        $fd = []; foreach ($o['figs'] as $k => $v) if ($k !== '' && ($nw['figs'][$k] ?? null) !== $v) $fd[] = "$k: old \"$v\" new \"" . ($nw['figs'][$k] ?? '—') . '"';
        t_eq($fd, [], "$pg — all " . count($o['figs']) . ' figures read the same');
        t_eq(array_values(array_diff($o['money'], $nw['money'])), [], "$pg — every amount of the old page is on the new one");
        t_eq(array_values(array_diff(array_unique($o['hrefs']), $nw['hrefs'])), [], "$pg — every link of the old page is on the new one");
        $lost = [];
        foreach (array_unique($o['forms']) as $sig) {
            [$at, $fl] = qp2_split($sig); $ok = false;
            foreach ($nw['forms'] as $ns) { [$nat, $nfl] = qp2_split($ns); if ($nat === $at && !array_diff($fl, $nfl)) { $ok = true; break; } }
            if (!$ok) $lost[] = $sig;
        }
        t_eq($lost, [], "$pg — every form (same address, every field) is on the new one");
        //  … and nothing hidden appears.
        $newL = array_values(array_diff(array_unique($nw['hrefs']), $o['hrefs'], ['#next-step']));
        t_eq($newL, [], "$pg — no link the old page did not give this person");
        $newF = array_values(array_diff(array_unique(array_map(fn($s) => qp2_split($s)[0], $nw['forms'])), array_map(fn($s) => qp2_split($s)[0], $o['forms'])));
        t_eq($newF, [], "$pg — no form the old page did not give this person");
        t_eq(count($nw['forms']), count($o['forms']), "$pg — the same number of forms (" . count($o['forms']) . ')');
        //  The jump to "Next step" from the Approval chain is there exactly when Approve / Reject are.
        $jump = in_array('#next-step', $nw['hrefs'], true);
        $hasDec = (bool) array_filter($nw['forms'], fn($s) => strpos($s, 'post /quote-approve?id=') === 0);
        t_eq($jump, $hasDec, "$pg — the \"Decide in Next step\" jump shows exactly when this person has a decision to make");
    }

    t_section('Q-02 — the step bar reads the status');
    $want = ['DRAFT' => [['now', 'Draft'], ['', 'Submitted'], ['', 'Approved'], ['', 'Sent'], ['', 'Won']],
             'REJECTED' => [['now', 'Draft'], ['stop', 'Sent back'], ['', 'Approved'], ['', 'Sent'], ['', 'Won']],
             'PENDING_APPROVAL' => [['done', 'Draft'], ['now', 'Submitted'], ['', 'Approved'], ['', 'Sent'], ['', 'Won']],
             'APPROVED' => [['done', 'Draft'], ['done', 'Submitted'], ['now', 'Approved'], ['', 'Sent'], ['', 'Won']],
             'SENT' => [['done', 'Draft'], ['done', 'Submitted'], ['done', 'Approved'], ['now', 'Sent'], ['', 'Won']],
             'EXPIRED' => [['done', 'Draft'], ['done', 'Submitted'], ['done', 'Approved'], ['now', 'Expired'], ['', 'Won']],
             'LOST' => [['done', 'Draft'], ['done', 'Submitted'], ['done', 'Approved'], ['done', 'Sent'], ['stop', 'Lost']],
             'ACCEPTED' => [['done', 'Draft'], ['done', 'Submitted'], ['done', 'Approved'], ['done', 'Sent'], ['done', 'Won']]];
    foreach ($want as $st => $steps) {
        $x = qp2_dom($draws['BDM (preparer)'][$st][1]['html']);
        $got = []; foreach ($x->query('//ol[' . qp2_cls('q2-steps') . ']/li') as $li) $got[] = [trim($li->getAttribute('class')), trim($li->textContent)];
        t_eq($got, $steps, "$st — the step bar shows " . implode(' › ', array_map(fn($s) => $s[1] . ($s[0] ? " ($s[0])" : ''), $steps)));
        $cur = $x->query('//ol[' . qp2_cls('q2-steps') . ']/li[@aria-current="step"]')->length;
        t_eq($cur, in_array($st, ['LOST', 'ACCEPTED'], true) ? 0 : 1, "$st — " . (in_array($st, ['LOST', 'ACCEPTED'], true) ? 'no step is "current" (the journey has ended)' : 'one step is marked current for a screen reader'));
    }

    t_section('Q-02 — the KPI cards sit under the header and step bar, with the page\'s own figures');
    foreach (['DRAFT', 'APPROVED', 'ACCEPTED'] as $st) {
        $html = $draws['Finance, Mumbai'][$st][1]['html']; $x = qp2_dom($html);
        $ks = []; foreach ($x->query('//div[' . qp2_cls('q2-kp') . ']/*[@data-k]') as $k) $ks[] = $k->getAttribute('data-k');
        t_eq($ks, ['Before GST', 'GST', 'With GST', 'Validity'], "$st — four cards: before GST · GST amount · with GST · validity");
        t_ok($x->query('//div[' . qp2_cls('q2-kp') . ']/*[' . qp2_cls('kpi') . ']')->length === 4, "$st — …drawn as the app-wide KPI card (.kpi)");
        $figs = $draws['Finance, Mumbai'][$st][1]['figs'];
        t_eq([$figs['Before GST'] ?? '', $figs['GST'] ?? '', $figs['With GST'] ?? '', $figs['Validity'] ?? ''], ['₹2,00,000', '₹36,000', '₹2,36,000', '30 days'], "$st — ₹2,00,000 before GST, ₹36,000 GST, ₹2,36,000 with GST, 30 days");
        $pSteps = strpos($html, 'class="q2-steps"'); $pKp = strpos($html, 'class="sxw-kp sxqd-kp q2-kp"'); $pNext = strpos($html, 'class="rec-card q2-next" id="next-step"'); $pTabs = strpos($html, 'data-tab="');
        t_ok($pSteps !== false && $pSteps < $pKp && $pKp < $pNext && $pNext < $pTabs, "$st — order on the page: step bar → KPI cards → Next step → the tabs");
        t_ok(strpos($html, 'class="q2-money"') !== false && strpos($html, 'Total with GST') !== false, "$st — the header still shows the total as text");
    }
    $gx = qp2_dom($draws['Finance, Mumbai']['APPROVED'][1]['html']);
    $gd = trim((string) ($gx->query('//*[@data-k="GST"]//*[' . qp2_cls('d') . ']')->item(0)->textContent ?? ''));
    $split = qp2_as($f['meera'], fn() => sx_quote_gst_split(crm_quote_get($Q['APPROVED'])));
    $wantD = !$split ? '' : ($split['mode'] === 'IGST' ? 'all IGST' : ($split['mode'] === 'UNKNOWN' ? 'CGST + SGST or IGST' : 'CGST'));
    t_ok($wantD === '' ? $gd === '' : strpos($gd, $wantD) === 0, 'the GST card says how it splits, by the invoice\'s own rule (' . ($split['mode'] ?? 'none') . ': "' . $gd . '")');

    t_section('Q-02 — the playbook sits at the end of Overview, folded');
    foreach (['DRAFT', 'SENT'] as $st) {
        $html = $draws['BDM (preparer)'][$st][1]['html']; $x = qp2_dom($html);
        $pb = $x->query('//details[' . qp2_cls('q2-fold') . ']');
        t_eq($pb->length, 1, "$st — one playbook box");
        if ($pb->length) {
            $d = $pb->item(0);
            t_ok(!$d->hasAttribute('open'), "$st — it starts folded");
            t_eq($d->getAttribute('data-tab'), 'Overview', "$st — on the Overview tab");
            t_ok(stripos($x->query('./summary', $d)->item(0)->textContent ?? '', 'playbook') !== false, "$st — its heading says what it is");
            $tabs = $x->query('//*[@data-tab]'); $last = null; foreach ($tabs as $t) if ($t->getAttribute('data-tab') === 'Overview') $last = $t;
            t_ok($last === $d, "$st — it is the last thing on Overview");
        }
    }

    t_section('Q-02 — Approve / Reject are in the Next step card, only for the person the step waits on');
    foreach ($people as $label => $uid) {
        [$o, $nw] = $draws[$label]['PENDING_APPROVAL'];
        if (!qp2_shown($o)) continue;
        $x = qp2_dom($nw['html']); $card = $x->query('//section[@id="next-step"]')->item(0);
        t_ok($card !== null, "$label — the Next step card is there");
        if (!$card) continue;
        $inCard = qp2_forms_in($x, $card);
        foreach ([$step1 => 1, $step2 => 2] as $sid => $lv) {
            $can = qp2_as($uid, fn() => crm_can_act_approval(ops_one("SELECT * FROM quote_approvals WHERE id=?", [$sid])));
            $mine = (bool) array_filter($inCard, fn($s) => strpos($s, 'post /quote-approve?id=' . $Q['PENDING_APPROVAL'] . ' [') === 0 && strpos($s, 'remarks') !== false && strpos($s, 'step') !== false)
                && strpos($nw['html'], 'name="step" value="' . $sid . '"') !== false;
            t_eq($mine, $can, "$label — level $lv: " . ($can ? 'the decision is in the Next step card' : 'no decision anywhere (the step does not wait on them)'));
        }
        $everywhere = array_values(array_filter($nw['forms'], fn($s) => strpos($s, 'post /quote-approve?id=') === 0));
        $inC = array_values(array_filter($inCard, fn($s) => strpos($s, 'post /quote-approve?id=') === 0));
        t_eq(count($everywhere), count($inC), "$label — every Approve / Reject form is in the Next step card, none left down the page");
        //  The approval table keeps who / when: its rows are there, with a jump up for the decider.
        t_ok($x->query('//*[@id="approval"]')->length === 1, "$label — the Approval chain is still on the page (who approved and when)");
    }
    //  Approve waits for the levels before: the Business Director (level 2) may reject now, approve later.
    $bdNew = $draws['Business Director (level 2)']['PENDING_APPROVAL'][1];
    if (qp2_as($f['bd'], fn() => crm_can_act_approval(ops_one("SELECT * FROM quote_approvals WHERE id=?", [$step2]))) && !in_array(2, crm_quote_open_levels($Q['PENDING_APPROVAL']), true)) {
        $x = qp2_dom($bdNew['html']);
        $f2 = null; foreach ($x->query('//section[@id="next-step"]//form') as $fm) if ($x->query('.//input[@name="step"][@value="' . $step2 . '"]', $fm)->length) $f2 = $fm;
        t_ok($f2 !== null && $x->query('.//button[@value="reject"]', $f2)->length === 1 && $x->query('.//button[@value="approve"]', $f2)->length === 0,
             'level 2 before level 1 has signed: Reject is offered, Approve is not yet');
        t_ok($f2 !== null && strpos($f2->textContent, 'Approve opens when the levels before yours have signed') !== false, '…and it says why');
    }
    $kx = qp2_dom($draws['Branch Manager (level 1)']['PENDING_APPROVAL'][1]['html']);
    $kf = null; foreach ($kx->query('//section[@id="next-step"]//form') as $fm) if ($kx->query('.//input[@name="step"][@value="' . $step1 . '"]', $fm)->length) $kf = $fm;
    t_ok($kf !== null && $kx->query('.//button[@value="approve"]', $kf)->length === 1 && $kx->query('.//button[@value="reject"]', $kf)->length === 1 && $kx->query('.//input[@name="remarks"]', $kf)->length === 1,
         'the branch manager (level 1) finds Approve, Reject and the comment box in the Next step card');

    t_section('Q-02 — the Next step card carries the right button for the person and the state');
    //  Every action of the old "Actions" box is in the Next step card on the new page, for every person and state.
    foreach ($people as $label => $uid) foreach ($Q as $st => $id) {
        [$o, $nw] = $draws[$label][$st];
        if (!qp2_shown($o)) continue;
        $ox = qp2_dom($o['html']); $oa = $ox->query('//*[@id="actions"]')->item(0);
        $nx = qp2_dom($nw['html']); $card = $nx->query('//section[@id="next-step"]')->item(0);
        $want = $oa ? array_map(fn($s) => qp2_split($s)[0], qp2_forms_in($ox, $oa)) : [];
        $got = $card ? array_map(fn($s) => qp2_split($s)[0], qp2_forms_in($nx, $card)) : [];
        t_eq(array_values(array_diff($want, $got)), [], "$label · $st — every action button of the old Actions box is in the Next step card (" . (implode(', ', array_unique($want)) ?: 'none') . ')');
        t_ok($nx->query('//section[@id="next-step"]//*[' . qp2_cls('nowband') . ']')->length === 1, "$label · $st — the card says where it is and what comes next");
    }
    $has = fn($label, $st, $at) => (bool) array_filter(qp2_forms_in($x = qp2_dom($draws[$label][$st][1]['html']), $x->query('//section[@id="next-step"]')->item(0)), fn($s) => strpos($s, $at) === 0);
    $canSubmit = qp2_as($bdm, fn() => can('crm.quote.create') || is_master());
    t_ok(!$canSubmit || $has('BDM (preparer)', 'DRAFT', 'post /quote-status?id=' . $Q['DRAFT']), 'draft · its preparer: Submit for approval is in the card');
    t_ok(!$has('Finance, Mumbai', 'DRAFT', 'post /quote-status?id='), 'draft · Finance: no button …');
    t_ok(strpos($draws['Finance, Mumbai']['DRAFT'][1]['text'], 'not yours to do') !== false, '…and it says that it is not theirs to do, and who does it');
    t_ok($has('BDM (preparer)', 'PENDING_APPROVAL', 'post /quote-withdraw?id=' . $Q['PENDING_APPROVAL']), 'submitted · its preparer: Withdraw to draft is in the card');
    $canSend = qp2_as($kam, fn() => can('crm.quote.send') || is_master());
    t_ok(!$canSend || $has('KAM', 'SENT', 'post /quote-status?id=' . $Q['SENT']), 'sent · its owner: Mark accepted / lost is in the card');
    $canContract = qp2_as($f['meera'], fn() => can('crm.contract.register') || is_master());
    if ($canContract) t_ok($has('Finance, Mumbai', 'ACCEPTED', 'post /quote-contract?id=' . $Q['ACCEPTED']), 'won · Finance: Register the contract is in the card');

    t_section('Q-02 — the wording on a won quotation reads once');
    $wt = $draws['Finance, Mumbai']['ACCEPTED'][1]['text'];
    t_ok(stripos($wt, 'inspection inspection') === false, 'no "Inspection inspection calls" on the page');
    if ($canContract) t_ok(strpos($wt, 'Next: register the contract number. ' . ucfirst(Tlp('call')) . ' are then raised from the contract.') !== false, '"Next: register the contract number. ' . ucfirst(Tlp('call')) . ' are then raised from the contract."');
    $src = (string) file_get_contents(dirname(__DIR__) . '/views/ops/crm/quote_detail.php');
    t_ok(stripos($src, "'raise inspection ' . Tlp(") === false && stripos($src, "Inspection ' . Tlp(") === false, 'the page source no longer puts "inspection" before the company\'s own word for a call');

    t_section('Q-02 — Q-05 still holds: draft PDF marked, Word only once approved and for those who send');
    foreach ($people as $label => $uid) foreach (['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'SENT'] as $st) {
        $nw = $draws[$label][$st][1]; if (!qp2_shown($draws[$label][$st][0])) continue;
        $q = crm_quote_get($Q[$st]); $x = qp2_dom($nw['html']);
        $chip = null; foreach ($x->query('//nav[' . qp2_cls('q2-chips') . ']//a') as $a) if (strpos($a->getAttribute('href'), '/quote-pdf?id=') === 0) $chip = $a;
        $cleared = crm_quote_doc_cleared($q);
        t_ok($chip !== null && strpos($chip->textContent, $cleared ? 'PDF (for client)' : 'PDF (draft)') !== false, "$label · $st — the PDF chip reads \"" . ($cleared ? 'PDF (for client)' : 'PDF (draft)') . '"');
        if (!$cleared) t_ok($chip !== null && strpos($chip->getAttribute('title'), QUOTE_DRAFT_MARK) !== false, "$label · $st — …and says every page is marked \"" . QUOTE_DRAFT_MARK . '"');
        if ($st === 'DRAFT') t_ok(!$cleared, "$label · DRAFT — ARMING: an approval level covers it, so its PDF is a draft");
        $word = qp2_as($uid, fn() => crm_quote_word_allowed(crm_quote_get($Q[$st])));
        t_eq(in_array('/quote-doc?id=' . $Q[$st], $nw['hrefs'], true), $word, "$label · $st — the Word copy " . ($word ? 'is offered (approved, and they send quotations)' : 'is not offered'));
    }
} finally {
    if (!empty($ruleId)) $pdo->prepare("DELETE FROM quote_approval_rules WHERE id=?")->execute([$ruleId]);
    foreach ($made['quotes'] as $id) {
        foreach (['quote_approvals', 'quote_files', 'quote_revisions', 'quote_followups', 'quote_lines', 'quote_locations'] as $t) { try { $pdo->prepare("DELETE FROM $t WHERE quote_id=?")->execute([$id]); } catch (Throwable $e) {} }
        $pdo->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]);
    }
    foreach ($made['partners'] as $id) $pdo->prepare("DELETE FROM business_partners WHERE id=?")->execute([$id]);
    foreach ($made['users'] as $id) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    bbv_cleanup($f);
}
t_ok(true, 'the test removed its own rows');
