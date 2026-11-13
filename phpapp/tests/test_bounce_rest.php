<?php
// ============================================================================
//  The rest of the lead-to-contract bounce list — owner, 13 Nov 2026:
//  "Go ahead with 2.1 and the rest of the bounce list".
//
//   2.1  A deal quotes with the customer name it has; the record is created at "won".
//   2.3  An open deal's top line follows its quotation.
//   2.4  A quotation made from a deal sits under that deal; a won lead links its deal.
//   2.5  One quotation button at a time.
//   1.1  An approved quotation names who sends it, to someone who may not.
//   1.2  One press for Accounts in the amber box; no second "Register contract".
//   1.3  A won quotation tells Sales it is with Accounts — by name.
//   1.4  The strip shows the lead; an empty "Actions" box is not drawn.
//   1.5  One parent in the trail.
//   1.6  An opened contract reaches the coordinator's dashboard.
//   …and the costing's four cards are the application's standard card.
// ============================================================================
t_section('2.1 — a deal with only a customer name: quote now, customer record at "won"');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
$prevSession = $_SESSION ?? []; $prevRef = $_SERVER['HTTP_REFERER'] ?? null;
try {
    t_as_admin();
    opp_migrate(); if (function_exists('crm_migrate')) crm_migrate(); contracts_migrate(); leads_migrate();
    $tag = 'br' . getmypid();
    $r = opp_create(['name' => 'Shutdown inspection ' . $tag, 'partner_name' => 'BR Prospect ' . $tag, 'value' => 900000]);
    $oid = (int)($r['id'] ?? 0);
    t_ok($oid > 0 && (int)(opp_row($oid)['partner_id'] ?? 0) === 0, 'ARMING · an open deal with a typed customer name and no customer record');
    $before = (int)ops_val("SELECT COUNT(*) FROM business_partners");
    $q = opp_create_quote($oid);
    t_ok(empty($q['err']) && !empty($q['id']), 'the deal raises its quotation without a customer record' . (!empty($q['err']) ? ': ' . $q['err'] : ''));
    $qid = (int)$q['id'];
    t_eq((int)ops_val("SELECT COUNT(*) FROM business_partners"), $before, '…and no customer record is created yet — nobody is "won" before they are');
    t_eq((int)ops_val("SELECT COUNT(*) FROM opportunity_quotes WHERE opportunity_id=? AND quotation_id=?", [$oid, $qid]), 1, 'the quotation is joined to the deal');
    //  At acceptance the existing machinery lands the customer and puts it on the deal.
    $qrow = crm_quote_get($qid);
    $res = quote_land_on_client($qrow);
    t_ok(!empty($res['client_id']), 'ARMING · accepting it puts the company on the client master (the rule a lead-quotation already followed)');
    $view = (string)file_get_contents(__DIR__ . '/../views/ops/opportunity_detail.php');
    t_ok(strpos($view, '<b>Link the customer to raise a quotation.</b>') === false && strpos($view, 'Link the customer first to generate one') === false, 'the deal no longer stops with "Link the customer to raise a quotation"');
    t_ok(strpos($view, 'is not on the client master yet — that is fine') !== false && strpos($view, 'Already a customer?') !== false,
        'it says the customer is added by itself on acceptance, and still lets an existing customer be picked');

    t_section('2.3 — an open deal follows its quotation');
    t_eq(opp_quote_now((int)opp_create(['name' => 'No quote yet ' . $tag, 'partner_name' => 'X'])['id']), null, 'a deal with no quotation has nothing to follow');
    $now = opp_quote_now($oid);
    t_ok($now && $now['id'] === $qid && strpos($now['text'], 'is being prepared') !== false, 'a draft: "' . ($now['text'] ?? '') . '"');
    foreach (['PENDING_APPROVAL' => 'is waiting for approval', 'APPROVED' => 'is approved — ready to send to them', 'SENT' => 'is sent — waiting for their answer', 'REJECTED' => 'was sent back by the approver'] as $st => $words) {
        $pdo->prepare("UPDATE quotations SET status=? WHERE id=?")->execute([$st, $qid]);
        t_ok(strpos(opp_quote_now($oid)['text'], $words) !== false, strtolower($st) . ': the deal says its quotation "' . $words . '"');
    }
    t_ok(strpos($view, '$quoteNow[\'text\']') !== false && strpos($view, 'Open <?= e(Tl(\'quote\')) ?> <?= e($quoteNow[\'label\']) ?> →') !== false, 'the deal\'s top line shows it, with a link to the quotation');

    t_section('2.4 — Back from a deal\'s quotation goes to the deal');
    $link = crm_quote_deal_link(crm_quote_get($qid));
    t_eq($link['url'] ?? '', '/opportunity?id=' . $oid, 'a quotation made from a deal sits under that deal');
    t_ok(strpos((string)($link['label'] ?? ''), 'Shutdown inspection') !== false, '…and the trail names the deal');
    t_eq(crm_quote_deal_link(['id' => $qid, 'lead_id' => 5]), null, 'a quotation made from a lead keeps its lead as the parent');
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_name, status, created_at) VALUES (?,0,1,'Loose','DRAFT',?)")->execute(['BR-LOOSE-' . getmypid(), date('c')]);
    t_eq(crm_quote_deal_link(crm_quote_get((int)$pdo->lastInsertId())), null, 'a quotation on no deal keeps its usual place');
    $lv = (string)file_get_contents(__DIR__ . '/../views/ops/lead_detail.php');
    t_ok(strpos($lv, "if (!empty(\$oppId)): ?> · <a href=\"/opportunity?id=<?= (int)\$oppId ?>\"><b>its deal</b></a>") !== false, 'a won lead keeps a link to its deal');

    t_section('1.5 — one parent in the trail');
    nav_under('quote', 'leads', ['label' => 'L-9 · Acme', 'url' => '/lead?id=9']);
    $_SERVER['HTTP_REFERER'] = 'http://x/quotes?mine=contract';
    $trail = array_column(nav_trail('quote'), 'label');
    t_ok(!in_array('Quotes', $trail, true) && in_array('L-9 · Acme', $trail, true), 'arriving from the quotation register does not add a second parent: ' . implode(' › ', $trail));
    unset($_SERVER['HTTP_REFERER']);
    t_eq(array_column(nav_trail('quote'), 'label'), $trail, '…the trail is the same wherever the visitor came from');

    t_section('1.1 and 1.3 — the next step names who takes it');
    $pdo->prepare("INSERT INTO offices (name, is_active) VALUES (?,1)")->execute(['BR Office ' . $tag]); $off = (int)$pdo->lastInsertId();
    $mkU = function ($first, $role) use ($pdo, $off, $tag) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, is_superuser, home_office_id) VALUES (?,?,?,?,1,0,?)")->execute([$tag . strtolower($first), $first, 'Br', $role, $off]);
        return (int)$pdo->lastInsertId();
    };
    $mkU('Sunita', 'BUSINESS_DEV_MANAGER'); $faiz = $mkU('Faiz', 'FINANCE'); $mkU('Meena', 'MARKETING_EXECUTIVE');
    $who = crm_people_who_can('crm.quote.send', ['office_id' => $off, 'sbu' => ''], 50);
    t_ok(strpos($who, 'Sunita Br') !== false && strpos($who, 'Meena Br') === false && strpos($who, 'Faiz Br') === false, 'who sends: the BDM is named; a Marketing Executive and Finance, who may not send, are not');
    $whoC = crm_people_who_can('crm.contract.register', ['office_id' => $off, 'sbu' => ''], 50);
    t_ok(strpos($whoC, 'Faiz Br') !== false && strpos($whoC, 'Sunita Br') === false, 'who registers the contract: Finance is named; the salesperson is not');
    t_eq(crm_people_who_can('no.such.permission', ['office_id' => $off]), '', 'a right nobody holds names nobody');
    $mkU('Aadi', 'ADMIN');                                  // sorts first by name, but is an administrator
    $who2 = crm_people_who_can('crm.quote.send', ['office_id' => $off, 'sbu' => ''], 50);
    t_ok(strpos($who2, 'Aadi Br') === false || strpos($who2, 'Sunita Br') < strpos($who2, 'Aadi Br'), 'the people whose job it is are named before an administrator: ' . $who2);
    $qv = (string)file_get_contents(__DIR__ . '/../views/ops/crm/quote_detail.php');
    t_ok(strpos($qv, "sending is not yours to do. Tell them it is ready.") !== false && strpos($qv, "if (!empty(\$canSend)) \$nbNext = 'Next: send it to the '") !== false,
        'an approved quotation says "send it" only to someone who can; otherwise it names who sends');
    t_ok(strpos($qv, "to register the contract number. Nothing more for you to do here.") !== false, 'a won quotation tells Sales it is with Accounts — not "register the contract number"');

    t_section('1.2 and 1.4 — one prompt for Accounts; nothing that leads nowhere');
    t_ok(strpos($qv, 'if ($canContract && empty($clientContracts)): ?>') !== false && strpos($qv, '✓ Register the contract — next number') !== false,
        'the amber box registers in one press when the client has no other contract to check first');
    $pb = crm_quote_playbook(['id' => 0, 'status' => 'DRAFT', 'contract_number' => '', 'quote_no' => 'X', 'rev' => 0]);
    $step = null; foreach ($pb['steps'] as $s0) if (($s0['key'] ?? '') === 'contract') $step = $s0;
    t_ok($step && ($step['cta'] ?? 'x') === '' && ($step['href'] ?? 'x') === '', 'the playbook no longer offers "Register contract" — a draft showed it long before it could be done');
    t_ok(strpos($qv, "if (strpos(\$qActs, '<button') !== false") !== false && strpos($qv, 'quote-jump a[href="#actions"]') !== false,
        'the "Actions — move this quote" box is drawn only when it holds something to press, and its shortcut hides with it');
    //  The strip: a quotation made straight from a lead starts at that lead.
    $pdo->prepare("INSERT INTO leads (ref, company_name, status, created_at, updated_at) VALUES (?, 'BR Lead Co', 'OPEN', ?, ?)")->execute(['BRL-' . getmypid(), date('c'), date('c')]);
    $lid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, lead_id, client_name, status, created_at) VALUES (?,0,1,?,'BR Lead Co','DRAFT',?)")->execute(['BR-LQ-' . getmypid(), $lid, date('c')]);
    $lq = (int)$pdo->lastInsertId();
    t_eq(chain_root('QUOTE', $lq), ['LEAD', $lid], 'the strip finds the lead a quotation was made from (it showed "Lead —")');
    $down = chain_from('LEAD', $lid);
    t_ok(!empty($down['LEAD']) && in_array($lq, array_map(fn($x) => (int)$x['id'], $down['QUOTE'] ?? []), true), '…and walking down from that lead reaches the quotation');

    t_section('No link to a page that would refuse — Accounts on a deal\'s quotation');
    //  Found walking as Finance: the quotation offered "← back to the deal", and the
    //  deal page refused them. Accounts register the contract; they do not open deals.
    $_SESSION['uid'] = $faiz; unset($_SESSION['uid_ws']); current_user(true); ua(true);
    t_ok(!can('mod.leads.view') && can('crm.contract.register'), 'ARMING · Finance registers contracts but may not open the sales pipeline');
    t_eq(crm_quote_deal_link(crm_quote_get($qid)), null, 'Accounts are not offered a link to the deal — the quotation keeps its usual place for them');
    t_eq(crm_quote_lead_link(['id' => $lq, 'lead_id' => $lid]), null, '…nor a link to the lead');
    $strip = chain_strip('QUOTE', $qid);
    t_ok(strpos($strip, 'href="/opportunity?id=' . $oid . '"') === false && strpos($strip, 'Opportunity') !== false,
        'the strip still shows the deal it came from, as plain text — not a link that is refused');
    t_ok(strpos($strip, 'href="/quote?id=' . $qid . '"') !== false, '…and everything they can open is still a link');
    t_as_admin();
    t_ok(strpos(chain_strip('QUOTE', $qid), 'href="/opportunity?id=' . $oid . '"') !== false, 'somebody who can open the deal still gets the link');

    t_section('1.6 — an opened contract reaches the coordinator');
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES (?,?,1,'ACTIVE')")->execute(['BR Client ' . $tag, 'BR Client ' . $tag]); $cli = (int)$pdo->lastInsertId();
    $mkC = function ($no, $status, $openedAt) use ($pdo, $cli, $off) {
        $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, branch_id, open_status, is_active, opened_at) VALUES (?,?,?,?,?,?,1,?)")->execute([$cli, $no, 'BR', 1000, $off, $status, $openedAt]);
        return (int)$pdo->lastInsertId();
    };
    $c1 = $mkC(strtoupper($tag) . '/1', 'OPEN', date('c')); $c2 = $mkC(strtoupper($tag) . '/2', 'PENDING', ''); $c3 = $mkC(strtoupper($tag) . '/3', 'OPEN', date('c')); $c4 = $mkC(strtoupper($tag) . '/4', 'OPEN', '');
    $pdo->prepare("INSERT INTO calls (call_code, client_id, contract_number, status, created_at) VALUES (?,?,?, 'OPEN', ?)")->execute(['BRC-' . getmypid(), $cli, strtoupper($tag) . '/3', date('c')]);
    $ids = array_map(fn($x) => (int)$x['id'], contracts_awaiting_first_call());
    t_ok(in_array($c1, $ids, true), 'a contract opened through approval, with no work order yet, is listed');
    t_ok(!in_array($c2, $ids, true), 'a contract still waiting for approval is not — it cannot take work yet');
    t_ok(!in_array($c3, $ids, true), 'a contract that already has a work order is not');
    t_ok(!in_array($c4, $ids, true), 'an old contract that was never opened through approval (no opening date) is not swept in');
    $tasks = []; foreach (ops_pending_tasks() as $t) $tasks[$t['href']] = $t;
    $hit = array_values(array_filter($tasks, fn($t) => strpos($t['label'], 'opened — raise the') !== false));
    t_ok($hit && $hit[0]['n'] >= 1, 'it shows on the dashboard as a task: "' . ($hit[0]['label'] ?? '') . '"');
    $rc = (string)file_get_contents(__DIR__ . '/../views/ops/raise_call.php');
    t_ok(strpos($rc, 'Newly opened — no <?= e(Tl(\'call\')) ?> raised yet') !== false, '"Raise a work order" lists the newly opened contracts first, one press each');

    t_section('Costing — the four cards are the application\'s standard KPI card');
    $pv = (string)file_get_contents(__DIR__ . '/../views/ops/project_costing.php');
    t_ok(strpos($pv, '<div class="kpi-row">') !== false && strpos($pv, 'class="kpis"') === false, 'the costing uses .kpi-row, the standard row');
    t_ok(strpos($pv, '.pc .kpi{') === false && strpos($pv, '.pc .kpi .l{') === false, 'and carries no card styles of its own to fight the standard ones');
    t_eq(substr_count($pv, '<span class="kic">'), 4, 'each card has its icon');
    t_ok(preg_match_all('~<div class="kpi[^"]*"><span class="kic">[^<]+</span><div class="k">[^<]+</div><div class="v">.*?</div><div class="d">[^<]+~s', $pv) === 4, 'each card has the standard parts: label band, figure and a line saying what it is');
    t_ok(strpos($pv, "\$T['profit']>=0?'tone-ok':'tone-bad'") !== false, 'profit and margin take the standard good / bad tone');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
    $_SESSION = $prevSession; current_user(true); ua(true);
    if ($prevRef !== null) $_SERVER['HTTP_REFERER'] = $prevRef; else unset($_SERVER['HTTP_REFERER']);
}
