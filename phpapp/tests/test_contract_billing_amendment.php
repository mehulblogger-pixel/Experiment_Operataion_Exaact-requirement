<?php
// Owner, 8 Nov 2026 — Option 1: billing terms on an OPEN (signed-off) contract
// change only as an amendment with the same two signatures as its value and
// dates (D-25); the current terms stay in force until then. A contract not yet
// open is changed directly (an endorsement given for other terms is withdrawn).
t_section('Billing terms on a signed contract need the two signatures');
if (function_exists('contracts_migrate')) contracts_migrate();
if (function_exists('billunit_migrate')) billunit_migrate();
$no = 'BTA-' . getmypid();
db()->prepare("INSERT INTO partner_contracts (contract_number, open_status, billing_triggers, billing_period) VALUES (?, 'OPEN', '', 'MONTHLY')")->execute([$no]);
$id = (int) ops_val("SELECT id FROM partner_contracts WHERE contract_number=?", [$no]);
$c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [$id]);

$r = billunit_contract_terms_request($c, 'PERIOD', 'MONTHLY', '');
t_eq($r['kind'], 'error', 'on an open contract a change without a reason is refused');
t_eq((string) ops_val("SELECT billing_triggers FROM partner_contracts WHERE id=?", [$id]), '', 'and nothing changes');

$r = billunit_contract_terms_request($c, 'PERIOD', 'MONTHLY', 'Client asked for monthly bills');
t_eq($r['kind'], 'amendment', 'with a reason it becomes an amendment');
t_eq((string) ops_val("SELECT billing_triggers FROM partner_contracts WHERE id=?", [$id]), '', 'the current terms stay in force until it is signed');
$am = contract_pending_amendment($id);
t_ok($am && strpos(contract_amendment_summary($am), 'billing terms') === 0, 'the amendment says what it changes: ' . ($am ? contract_amendment_summary($am) : ''));
t_eq(billunit_contract_terms_request($c, 'FIR', '', 'another')['kind'], 'error', 'a second change waits until the first is decided');

$err = contract_amendment_apply($c, $am, 'Approver', 0, 'ok');
t_eq($err, '', 'once approved it is applied');
$after = ops_one("SELECT * FROM partner_contracts WHERE id=?", [$id]);
t_eq([$after['billing_triggers'], $after['billing_period']], ['PERIOD', 'MONTHLY'], 'the contract now carries the new billing terms');
t_eq((string) ops_val("SELECT status FROM contract_amendments WHERE id=?", [(int) $am['id']]), 'APPROVED', 'and the amendment is marked approved, the old terms kept on it');
t_ok(json_decode((string) $am['old_values'], true)['billing']['triggers'] === '', 'the old terms are kept');

// A contract still being signed off: changed directly; an endorsement is withdrawn.
db()->prepare("UPDATE partner_contracts SET open_status='PENDING', mgr_endorsed_at=?, mgr_endorsed_by='M' WHERE id=?")->execute([date('c'), $id]);
$c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [$id]);
$r = billunit_contract_terms_request($c, 'RN', '', '');
t_eq($r['kind'], 'saved', 'a contract not yet open takes the change at once');
t_eq((string) ops_val("SELECT billing_triggers FROM partner_contracts WHERE id=?", [$id]), 'RN', 'with the new terms');
t_eq((string) ops_val("SELECT mgr_endorsed_at FROM partner_contracts WHERE id=?", [$id]), '', 'and the endorsement given for the earlier terms is withdrawn');

// A value/dates amendment still works as before.
db()->prepare("UPDATE partner_contracts SET open_status='OPEN', value=100 WHERE id=?")->execute([$id]);
db()->prepare("INSERT INTO contract_amendments (contract_id,status,old_values,new_values,reason) VALUES (?,'PENDING',?,?,'r')")
    ->execute([$id, json_encode(['value' => 100, 'start_date' => '', 'end_date' => '', 'lines' => []]), json_encode(['value' => 250, 'start_date' => '', 'end_date' => '', 'lines' => []])]);
$c = ops_one("SELECT * FROM partner_contracts WHERE id=?", [$id]);
t_eq(contract_amendment_apply($c, contract_pending_amendment($id), 'A', 0, ''), '', 'a value amendment is applied as before');
$c2 = ops_one("SELECT * FROM partner_contracts WHERE id=?", [$id]);
t_ok((float) $c2['value'] === 250.0 && $c2['billing_triggers'] === 'RN', 'it changes the value and leaves the billing terms alone');

// The form on an open contract asks for the reason and says it needs two signatures.
$h = billunit_terms_form_html('/contract-billing', ['contract_id' => $id], 'RN', '', true, '', 'open — needs two signatures');
t_ok(strpos($h, 'name="amend_reason"') !== false && strpos($h, 'needs two signatures') !== false, 'the form asks for a reason and says it needs two signatures');

db()->prepare("DELETE FROM contract_amendments WHERE contract_id=?")->execute([$id]);
db()->prepare("DELETE FROM partner_contracts WHERE id=?")->execute([$id]);
