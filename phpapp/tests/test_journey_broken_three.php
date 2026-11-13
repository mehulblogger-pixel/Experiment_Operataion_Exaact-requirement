<?php
// ============================================================================
//  The three BROKEN items on the lead-to-contract bounce list — owner, 13 Nov
//  2026: "Go ahead with the three broken items first".
//
//   2.2  One sale could get two contracts: a deal won through a quotation went on
//        offering "Send to Accounts" after the quotation had its contract.
//   3.1  Accounts could not register a contract for a deal won without a
//        quotation: "Register" opened the deal page, which Finance may not open.
//   3.2  A won deal for a new customer said "Add them as a customer first" and
//        offered no way to do it.
// ============================================================================
t_section('One sale, one contract — a deal won through its quotation (2.2)');
$pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
$prevSession = $_SESSION ?? [];
try {
    t_as_admin();
    opp_migrate(); if (function_exists('crm_migrate')) crm_migrate(); contracts_migrate();
    $tag = 'jb3' . getmypid();
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES (?,?,1,'ACTIVE')")->execute(['JB3 Client ' . $tag, 'JB3 Client ' . $tag]);
    $cli = (int)$pdo->lastInsertId();
    $mkOpp = function ($name, $partner, array $x = []) use ($pdo, $tag) {
        $pdo->prepare("INSERT INTO opportunities (ref, name, partner_id, partner_name, value, status, contact_name, contact_email, contact_phone, lead_id, sent_to_accounts_at, created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([strtoupper($tag) . '-' . mt_rand(100, 99999), $name, $partner ?: null, $x['partner_name'] ?? 'JB3 Client', 300000, $x['status'] ?? 'WON',
                       $x['contact'] ?? '', '', '', $x['lead_id'] ?? null, $x['sent'] ?? '', date('c')]);
        return (int)$pdo->lastInsertId();
    };
    $mkQuote = function ($status, $contractNo = '') use ($pdo, $cli, $tag) {
        $pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, client_name, status, contract_number, total_amount, created_at) VALUES (?,0,1,?,?,?,?,354000,?)")
            ->execute(['JB3-Q-' . mt_rand(100, 99999), $cli, 'JB3 Client', $status, $contractNo, date('c')]);
        return (int)$pdo->lastInsertId();
    };
    $link = fn($opp, $q) => $pdo->prepare("INSERT INTO opportunity_quotes (opportunity_id, quotation_id, linked_by, linked_at) VALUES (?,?,?,?)")->execute([$opp, $q, 'test', date('c')]);

    // A deal with no quotation: the direct road is open, as before.
    $plain = $mkOpp('Plain won deal', $cli);
    t_eq(opp_winning_quote($plain), null, 'a deal with no quotation has no "winning quotation"');
    t_eq(opp_own_contract_block(opp_row($plain)), '', '…so it may be sent to Accounts');
    t_ok(!empty(opp_send_to_accounts($plain)['ok']), 'ARMING · the direct road still works: sent to Accounts');
    t_ok(in_array($plain, array_map(fn($o) => (int)$o['id'], opps_awaiting_contract()), true), '…and it is on Accounts\' list');

    // A deal whose quotation is only a draft or sent: nothing is settled yet, the deal is not blocked.
    $draft = $mkOpp('Deal with a draft quotation', $cli); $link($draft, $mkQuote('DRAFT'));
    t_eq(opp_own_contract_block(opp_row($draft)), '', 'a draft quotation does not block the deal\'s own road');
    $sentQ = $mkOpp('Deal with a sent quotation', $cli); $link($sentQ, $mkQuote('SENT'));
    t_eq(opp_own_contract_block(opp_row($sentQ)), '', 'nor does a quotation that is only sent');

    // Won through an accepted quotation: it is with Accounts through the quotation.
    $won = $mkOpp('Deal won through its quotation', $cli); $qa = $mkQuote('ACCEPTED'); $link($won, $qa);
    $w = opp_winning_quote($won);
    t_ok((int)($w['id'] ?? 0) === $qa && array_key_exists('contract', $w) && $w['contract'] === null, 'the accepted quotation is the one that carries the sale; no contract yet');
    $r = opp_send_to_accounts($won);
    t_ok(!empty($r['err']) && strpos($r['err'], 'already with Accounts') !== false && strpos($r['err'], 'two contracts') !== false, '"Send to Accounts" is refused, and says why: ' . ($r['err'] ?? ''));
    t_eq((string)opp_row($won)['sent_to_accounts_at'], '', '…nothing was stamped on the deal');
    $r = opp_register_contract($won, ['auto_contract' => 1]);
    t_ok(!empty($r['err']) && (int)opp_row($won)['contract_id'] === 0, 'registering a contract from the deal is refused too — it is registered on the quotation');
    t_ok(strpos(opp_won_next(opp_row($won)), 'with Accounts through') !== false && strpos(opp_won_next(opp_row($won)), 'send it to Accounts') === false,
        'the deal\'s top line says it is with Accounts through the quotation — not "send it to Accounts": ' . opp_won_next(opp_row($won)));

    // The owner's exact case: sent to Accounts by mistake BEFORE this fix, and the quotation already has its contract.
    $pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, open_status, is_active) VALUES (?,?,?,?, 'PENDING', 0)")->execute([$cli, strtoupper($tag) . '/C/1', 'From quotation', 300000]);
    $pcId = (int)$pdo->lastInsertId();
    $dup = $mkOpp('Deal already sent by mistake', $cli, ['sent' => date('c')]); $qc = $mkQuote('ACCEPTED', strtoupper($tag) . '/C/1'); $link($dup, $qc);
    $pdo->prepare("UPDATE quotations SET contract_id=? WHERE id=?")->execute([$pcId, $qc]);
    t_ok(!in_array($dup, array_map(fn($o) => (int)$o['id'], opps_awaiting_contract()), true), 'a deal whose quotation already has a contract is NOT on Accounts\' "won deals without a quote" list');
    $w = opp_winning_quote($dup);
    t_eq([(string)$w['contract_number'], (int)($w['contract']['id'] ?? 0)], [strtoupper($tag) . '/C/1', $pcId], 'the deal knows the contract its quotation carries');
    t_ok(strpos(opp_won_next(opp_row($dup)), 'Contract ' . strtoupper($tag) . '/C/1') === 0 && strpos(opp_won_next(opp_row($dup)), 'waiting for approval') !== false,
        'and its top line shows that contract and its state: ' . opp_won_next(opp_row($dup)));
    $r = opp_register_contract($dup, ['auto_contract' => 1]);
    t_ok(!empty($r['err']) && strpos($r['err'], 'One sale, one contract') !== false, 'a second contract for the same sale is refused: ' . ($r['err'] ?? ''));
    t_eq((int)ops_val("SELECT COUNT(*) FROM partner_contracts WHERE partner_id=?", [$cli]), 1, '…the customer still has exactly one contract');

    t_section('Accounts register a won deal on their own screen (3.1)');
    $ops = (string)file_get_contents(__DIR__ . '/../lib/ops.php');
    t_ok(strpos($ops, "'contracts-to-register'=>'quotes'") !== false, 'the screen sits behind the Quotes module — where Finance already is — not Leads');
    t_ok(strpos($ops, "case \$route === 'contracts-to-register':") !== false, 'and has its own address, /contracts-to-register');
    $opp = (string)file_get_contents(__DIR__ . '/../lib/opportunities.php');
    t_ok(strpos($opp, "ops_require(can('crm.contract.register') || is_master(), 'Only Accounts / back-office register contracts.');") !== false, 'it asks the right Accounts already hold — no new permission');
    t_ok(strpos($opp, "(\$_POST['from'] ?? '') === 'list') ? '/contracts-to-register' : '/opportunity?id=' . \$id") !== false, 'registering from the list returns to the list (that one address only), not to the deal page');
    //  Render it as Accounts would see it.
    $deals = opps_awaiting_contract(); $quotes = []; $offices = []; $ruleWords = contract_rule_sentence();
    ob_start(); require __DIR__ . '/../views/ops/contracts_to_register.php'; $html = (string)ob_get_clean();
    t_ok(strpos($html, 'id="deal-' . $plain . '"') !== false && strpos($html, 'Plain won deal') !== false, 'the won deal is listed with its name');
    t_ok(preg_match('~action="/opportunity-contract"[^>]*>\s*<input type="hidden" name="id" value="' . $plain . '"><input type="hidden" name="from" value="list">\s*<input type="hidden" name="auto_contract" value="1">~', $html) === 1,
        'one press registers it with the next number, and comes back to this list');
    t_ok(strpos($html, 'Enter it differently') !== false && strpos($html, 'name="contract_number"') !== false, 'the client\'s own number, dates and branch are one fold away');
    t_ok(strpos($html, '/opportunity?id=') === false, 'nothing on the screen leads to the deal page, which Accounts may not open');
    $dash = (string)file_get_contents(__DIR__ . '/../views/dashboard.php');
    t_ok(strpos($dash, 'href="/contracts-to-register#deal-<?= (int)$nd[\'id\'] ?>">Register</a>') !== false && strpos($dash, 'href="/opportunity?id=<?= (int)$nd[\'id\'] ?>">Register') === false,
        'the dashboard\'s "Register" on a won deal opens this screen, no longer the deal');
    //  And it really registers.
    $r = opp_register_contract($plain, ['auto_contract' => 1]);
    t_ok(!empty($r['ok']) && (int)opp_row($plain)['contract_id'] > 0, 'ARMING · the contract is registered for the plain won deal: ' . ($r['contract_no'] ?? ($r['err'] ?? '')));
    t_ok(!in_array($plain, array_map(fn($o) => (int)$o['id'], opps_awaiting_contract()), true), '…and the deal leaves the list');

    t_section('Add the customer from the won deal (3.2)');
    $new = $mkOpp('Deal for a brand-new customer', 0, ['partner_name' => 'JB3 Newco ' . $tag, 'contact' => 'Arun Rao']);
    t_ok(!empty(opp_send_to_accounts($new)['err']), 'ARMING · without a customer the deal cannot go to Accounts');
    $before = (int)ops_val("SELECT COUNT(*) FROM business_partners");
    $r = opp_add_customer($new, ['state' => 'Gujarat']);
    t_ok(!empty($r['ok']), 'the customer is added from the deal' . (!empty($r['err']) ? ': ' . $r['err'] : ''));
    $bp = ops_one("SELECT * FROM business_partners WHERE id=?", [(int)($r['partner_id'] ?? 0)]);
    t_eq([(string)($bp['legal_name'] ?? ''), (int)($bp['is_client'] ?? 0), (string)($bp['status'] ?? ''), (string)($bp['state'] ?? '')], ['JB3 Newco ' . $tag, 1, 'ACTIVE', 'Gujarat'],
        'a short client record: the deal\'s own name, active, with the State typed');
    t_eq((int)opp_row($new)['partner_id'], (int)$bp['id'], 'the deal now points at it');
    t_eq((string)ops_val("SELECT name FROM partner_contacts WHERE partner_id=?", [(int)$bp['id']]), 'Arun Rao', 'the deal\'s contact comes across too');
    t_ok(!empty(opp_send_to_accounts($new)['ok']), 'and the deal can now go to Accounts');
    t_ok(!empty(opp_add_customer($new, [])['err']) && (int)ops_val("SELECT COUNT(*) FROM business_partners") === $before + 1, 'pressing it again creates no second customer');
    //  Same name as a customer already on file: stop once and ask — never a silent second record.
    $twin = $mkOpp('Deal for a same-named company', 0, ['partner_name' => 'JB3 Newco ' . $tag]);
    $n0 = (int)ops_val("SELECT COUNT(*) FROM business_partners");
    $r = opp_add_customer($twin, []);
    t_ok(!empty($r['err']) && strpos($r['err'], 'already on file') !== false && !empty($r['near']) && (int)ops_val("SELECT COUNT(*) FROM business_partners") === $n0,
        'a name already on file stops and asks — same company (pick it) or a different one? ' . ($r['err'] ?? ''));
    $r = opp_add_customer($twin, ['confirm_new' => '1']);
    t_ok(!empty($r['ok']) && (int)ops_val("SELECT COUNT(*) FROM business_partners") === $n0 + 1, 'ticking "It is a different company" adds it, on purpose');
    $open = $mkOpp('Still an open deal', 0, ['status' => 'OPEN', 'partner_name' => 'JB3 Open ' . $tag]);
    t_ok(!empty(opp_add_customer($open, [])['err']), 'it is for a WON deal — an open deal is refused (quoting from a deal is a separate decision, 2.1)');
    $fromLead = $mkOpp('Deal that came from a lead', 0, ['lead_id' => 987654, 'partner_name' => 'JB3 Lead ' . $tag]);
    t_ok(strpos((string)(opp_add_customer($fromLead, [])['err'] ?? ''), 'came from a lead') !== false, 'a deal that came from a lead still goes through the lead\'s own conversion');
    $noname = $mkOpp('No name', 0, ['partner_name' => '']);
    t_ok(!empty(opp_add_customer($noname, ['company_name' => ''])['err']), 'no name, no customer');
    t_ok(strpos($opp, "ops_require(function_exists('leads_can_edit') && leads_can_edit(), 'Only someone who works leads and deals can add the customer.');") !== false,
        'who may: exactly the people who may already make a lead a customer — nobody new');
    $view = (string)file_get_contents(__DIR__ . '/../views/ops/opportunity_detail.php');
    t_ok(strpos($view, 'action="/opportunity-add-customer"') !== false && strpos($view, '＋ Add as a customer') !== false, 'the won deal shows the "＋ Add as a customer" form where it used to say only "add them first"');
    t_ok(strpos($view, "<?php elseif (!empty(\$o['sent_to_accounts_at'])): ?>") !== false && strpos($view, "if (!empty(\$winQuote)):") !== false, 'and shows the quotation road in place of "Send to Accounts" when the deal was won through a quotation');
} finally {
    if ($own && $pdo->inTransaction()) $pdo->rollBack();
    $_SESSION = $prevSession; current_user(true); ua(true);
}
