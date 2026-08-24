<?php
// A deal can be WON without a quotation (an order received direct). It must still
// reach Finance: the sales owner hands it to Accounts, who register a contract
// straight from the deal — running the SAME PENDING → endorse → approve → OPEN
// lifecycle a quoted win uses — and the calls are raised from the OPEN contract.
// The quick "raise the work order" path (direct to a call, no contract) is kept.
t_section('direct win (no quote) can be sent to Accounts and made into a contract');

opp_migrate();
$pdo = db();

// A client, and a WON deal with no quotation, no call and no contract.
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('Vikram Steel','Vikram Steel',1,'ACTIVE')")->execute();
$pid = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO opportunities (ref, name, partner_id, partner_name, value, status, created_at) VALUES ('OPP-DW1','Air cooled heat exchanger',?, 'Vikram Steel', 250000, 'WON', ?)")->execute([$pid, date('c')]);
$oid = (int)$pdo->lastInsertId();

// --- Send to Accounts --------------------------------------------------------
$r = opp_send_to_accounts($oid);
t_ok(empty($r['err']) && !empty($r['ok']), 'a won deal can be handed to Accounts');
$o = opp_row($oid);
t_ok(trim((string)($o['sent_to_accounts_at'] ?? '')) !== '', 'the handoff timestamp is recorded');

// It now shows in Finance's "deals awaiting a contract" queue.
$queue = opps_awaiting_contract();
t_ok(in_array($oid, array_map(fn($x) => (int)$x['id'], $queue), true), 'the deal appears in the contracts-to-register queue');

// Guards: a non-won deal, or one already ordered, is refused.
$pdo->prepare("INSERT INTO opportunities (ref, name, partner_id, value, status, created_at) VALUES ('OPP-DW2','Open deal',?, 1, 'OPEN', ?)")->execute([$pid, date('c')]);
$openOid = (int)$pdo->lastInsertId();
t_ok(!empty(opp_send_to_accounts($openOid)['err']), 'an open (not won) deal cannot be handed to Accounts');

// --- Accounts register the contract -----------------------------------------
$r2 = opp_register_contract($oid, ['contract_number' => 'VSF-2026-01', 'branch_id' => 0]);
t_ok(empty($r2['err']) && $r2['open_status'] === 'PENDING', 'registering a NEW number creates a PENDING contract (held for approval)');
$o = opp_row($oid);
t_ok((int)$o['contract_id'] === (int)$r2['contract_id'], 'the contract is linked back to the deal');
$pc = ops_one("SELECT partner_id, contract_number, open_status, value FROM partner_contracts WHERE id=?", [(int)$r2['contract_id']]);
t_ok($pc && (int)$pc['partner_id'] === $pid && $pc['contract_number'] === 'VSF-2026-01' && $pc['open_status'] === 'PENDING',
    'the partner_contracts row is created PENDING for the deal customer');
t_ok((float)$pc['value'] == 250000.0, 'the deal estimate carries onto the contract value');

// Once registered, the deal leaves the awaiting-a-contract queue.
$queue2 = opps_awaiting_contract();
t_ok(!in_array($oid, array_map(fn($x) => (int)$x['id'], $queue2), true), 'a registered deal drops out of the queue');

// Registering twice is refused.
t_ok(!empty(opp_register_contract($oid, ['contract_number' => 'X'])['err']), 'a deal cannot be registered to a second contract');

// A clashing number (same number, different client) is refused.
$pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, status) VALUES ('Other Co','Other Co',1,'ACTIVE')")->execute();
$pid2 = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO opportunities (ref, name, partner_id, value, status, sent_to_accounts_at, created_at) VALUES ('OPP-DW3','Other deal',?, 5000, 'WON', ?, ?)")->execute([$pid2, date('c'), date('c')]);
$oid3 = (int)$pdo->lastInsertId();
t_ok(!empty(opp_register_contract($oid3, ['contract_number' => 'VSF-2026-01'])['err']),
    'the same contract number for a different client is refused (a number identifies one contract)');

// --- wiring ------------------------------------------------------------------
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "'opportunity-send-to-accounts'=>'leads','opportunity-contract'=>'leads'") !== false,
    'the two new routes are mapped through the module gate');
$view = file_get_contents(__DIR__ . '/../views/ops/opportunity_detail.php');
t_ok(strpos($view, '/opportunity-send-to-accounts') !== false && strpos($view, 'Send to Accounts to register the contract') !== false,
    'the won deal offers "Send to Accounts to register the contract"');
t_ok(strpos($view, '/opportunity-raise-order') !== false && strpos($view, 'Or raise a work order directly') !== false,
    'the direct work-order path is kept as a secondary, folded option');
