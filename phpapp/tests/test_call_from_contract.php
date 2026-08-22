<?php
// Acceptance is given to the QUOTE; the quotation's journey ends at the contract
// number. Inspection / deputation calls are raised FROM the contract afterwards
// (often a later day, as many as the work needs) — never straight off the quote.
// Guards the quote playbook's final step.
t_section('inspection calls are raised from the contract');

$stepBy = function ($pb, $key) { foreach ($pb['steps'] as $s) if ($s['key'] === $key) return $s; return null; };

// The raise-call action only shows to someone who can actually raise calls, so
// sign in as a master for the "action is offered" checks.
$pdo = db();
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser) VALUES ('cfc_master','M','MASTER_ADMIN',1,1)")->execute();
$_SESSION['uid'] = (int)$pdo->lastInsertId();
current_user(true); ua(true);

// Accepted quote with an OPEN contract → the final step raises a call FROM it.
$pdo->prepare("INSERT INTO partner_contracts (partner_id,contract_number,open_status,is_active) VALUES (0,'CFC-OPEN','OPEN',1)")->execute();
$pb = crm_quote_playbook(['id' => 0, 'status' => 'ACCEPTED', 'contract_number' => 'CFC-OPEN']);
$order = $stepBy($pb, 'order');
t_ok($order !== null, 'the playbook has the raise-inspection-calls step');
t_ok(strpos($order['href'], '/call-new?contract=') === 0, 'the step points at the contract, not the quote');
t_ok(strpos($order['href'], '?quote=') === false, 'it never links straight to a call off the quote');
t_ok(strpos($order['href'], rawurlencode('CFC-OPEN')) !== false, 'the contract number is carried in the link');
t_ok($order['cta'] !== '', 'a "raise call" action is offered once the contract is OPEN and I can raise calls');
t_ok(strpos((string)$order['cta'], 'inspection inspection') === false && strpos((string)$order['label'], 'inspection inspection') === false,
    'no doubled "inspection inspection" wording');

// Accepted quote with a PENDING contract → NO raise action; it must be opened first.
$pdo->prepare("INSERT INTO partner_contracts (partner_id,contract_number,open_status,is_active) VALUES (0,'CFC-PEND','PENDING',1)")->execute();
$pbP = crm_quote_playbook(['id' => 0, 'status' => 'ACCEPTED', 'contract_number' => 'CFC-PEND']);
$orderP = $stepBy($pbP, 'order');
t_ok($orderP['href'] === '' && $orderP['cta'] === '',
    'a not-yet-open (PENDING) contract offers no raise-call action');
t_ok(strpos((string)$orderP['line'], 'endorsed and approved') !== false,
    'the step explains the contract must be endorsed and approved first');

// Accepted but NO contract yet → no raise-call link; the contract must be registered first.
$pb2 = crm_quote_playbook(['id' => 0, 'status' => 'ACCEPTED', 'contract_number' => '']);
$order2 = $stepBy($pb2, 'order');
t_ok($order2['href'] === '' && $order2['cta'] === '',
    'with no contract yet there is no raise-call link — register the contract first');
$contract2 = $stepBy($pb2, 'contract');
t_ok($contract2 && empty($contract2['done']), 'the "register the contract" step is still open');

// A quote not yet accepted offers neither a contract nor a call.
$pb3 = crm_quote_playbook(['id' => 0, 'status' => 'SENT', 'contract_number' => '']);
$order3 = $stepBy($pb3, 'order');
t_ok($order3['href'] === '', 'a sent-but-unaccepted quote cannot raise a call');

// A user who cannot raise calls (no session / no ops.call.create) never gets the
// raise-call action, even on an accepted quote with a contract — e.g. Finance.
unset($_SESSION['uid']); current_user(true); ua(true);
$pbF = crm_quote_playbook(['id' => 0, 'status' => 'ACCEPTED', 'contract_number' => 'CFC-OPEN']);
$orderF = $stepBy($pbF, 'order');
t_ok($orderF['href'] === '' && $orderF['cta'] === '',
    'someone who cannot raise calls sees no raise-call action (Finance/Sales just see the status)');
