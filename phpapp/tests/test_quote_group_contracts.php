<?php
// One rate quotation, several group companies. A rate quote is agreed once with a
// group; each group company (a subsidiary billing on its own number) may then hold
// ITS OWN contract at the same rates. Beyond the quote's primary contract, additional
// contracts can be registered under the same quotation for RELATED companies — each a
// normal PENDING contract, the quote's primary contract_id untouched.
t_section('one quote → contracts for several group companies');

$pdo = db();
// A parent client and a subsidiary (group), plus an unrelated client.
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('Vikram Group','Vikram Group',1,'ACTIVE')")->execute();
$parent = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status, parent_id) VALUES ('Vikram Steel Unit-2','Vikram Steel Unit-2',1,'ACTIVE',?)")->execute([$parent]);
$sub = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('Unrelated Co','Unrelated Co',1,'ACTIVE')")->execute();
$other = (int)$pdo->lastInsertId();

// A WON quote for the parent, with its PRIMARY contract registered.
$pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, client_name, subject, status, total_amount, created_at) VALUES ('Q-GRP1',0,1,?,'Vikram Group','Rate contract', 'ACCEPTED', 500000, ?)")->execute([$parent, date('c')]);
$qid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, quotation_id, open_status, is_active) VALUES (?, 'C-MAIN', ?, 'OPEN', 1)")->execute([$parent, $qid]);
$mainContract = (int)$pdo->lastInsertId();
$pdo->prepare("UPDATE quotations SET contract_id=?, contract_number='C-MAIN' WHERE id=?")->execute([$mainContract, $qid]);

// The subsidiary is an eligible candidate; the unrelated client is not.
$cand = array_map(fn($c) => (int)$c['id'], quote_group_contract_candidates($qid));
t_ok(in_array($sub, $cand, true), 'a subsidiary is offered as a group-contract candidate');
t_ok(!in_array($other, $cand, true), 'an unrelated client is NOT offered');
t_ok(!in_array($parent, $cand, true), 'the parent (already contracted under this quote) is not offered again');

// Register an additional contract for the subsidiary under the same quote.
$r = crm_add_group_contract($qid, $sub, ['contract_number' => 'S2-PO-77']);
t_ok(empty($r['err']) && !empty($r['contract_id']), 'a group contract is registered for the subsidiary');
$pc = ops_one("SELECT partner_id, quotation_id, open_status FROM partner_contracts WHERE id=?", [(int)$r['contract_id']]);
t_ok((int)$pc['partner_id'] === $sub && (int)$pc['quotation_id'] === $qid && $pc['open_status'] === 'PENDING',
    'it is a PENDING contract for the subsidiary, linked to the same quotation');

// The quote's PRIMARY contract is untouched (still one quote → one *primary* contract).
t_ok((int)ops_val("SELECT contract_id FROM quotations WHERE id=?", [$qid]) === $mainContract,
    'the quote primary contract_id is unchanged');
// Both contracts are found under the quote.
t_ok(count(contracts_for_quote($qid)) === 2, 'both contracts are listed under the quotation');
// The subsidiary is no longer offered (it now has one).
t_ok(!in_array($sub, array_map(fn($c) => (int)$c['id'], quote_group_contract_candidates($qid)), true),
    'a company that now holds a contract drops off the candidate list');

// Guards.
t_ok(!empty(crm_add_group_contract($qid, $other, ['contract_number' => 'X'])['err']),
    'a company outside the group is refused');
$pdo->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, status, total_amount, created_at) VALUES ('Q-GRP2',0,1,?, 'ACCEPTED', 1, ?)")->execute([$parent, date('c')]);
$noPrimary = (int)$pdo->lastInsertId();
t_ok(!empty(crm_add_group_contract($noPrimary, $sub, ['contract_number' => 'Y'])['err']),
    'no additional contract before the main contract is registered');

// Wiring.
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "'quote-add-contract'=>'quotes'") !== false, 'the group-contract route is mapped through the module gate');
$view = file_get_contents(__DIR__ . '/../views/ops/crm/quote_detail.php');
t_ok(strpos($view, '/quote-add-contract') !== false && strpos($view, 'Group-company contracts') !== false,
    'the quote shows the group-company contracts section');
