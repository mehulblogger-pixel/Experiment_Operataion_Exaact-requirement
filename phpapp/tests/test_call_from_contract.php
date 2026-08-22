<?php
// Acceptance is given to the QUOTE; the quotation's journey ends at the contract
// number. Inspection / deputation calls are raised FROM the contract afterwards
// (often a later day, as many as the work needs) — never straight off the quote.
// Guards the quote playbook's final step.
t_section('inspection calls are raised from the contract');

$stepBy = function ($pb, $key) { foreach ($pb['steps'] as $s) if ($s['key'] === $key) return $s; return null; };

// Accepted quote WITH a contract number → the final step raises a call FROM the contract.
$pb = crm_quote_playbook(['id' => 0, 'status' => 'ACCEPTED', 'contract_number' => 'CON/2026/0009']);
$order = $stepBy($pb, 'order');
t_ok($order !== null, 'the playbook has the raise-inspection-calls step');
t_ok(strpos($order['href'], '/call-new?contract=') === 0, 'the step points at the contract, not the quote');
t_ok(strpos($order['href'], '?quote=') === false, 'it never links straight to a call off the quote');
t_ok(strpos($order['href'], rawurlencode('CON/2026/0009')) !== false, 'the contract number is carried in the link');
t_ok($order['cta'] !== '', 'a "raise inspection call" action is offered once the contract exists');

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
