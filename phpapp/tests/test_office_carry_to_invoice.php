<?php
// The office is set ONCE at contract registration and carries down to the invoice.
// invoice_office_for() proves the resolution: a same-office call (no executing
// branch) still bills from the contract's branch, so "No branch is set" cannot
// recur.
t_section('the contract branch carries down to the invoice');

$pdo = db();
// A registered contract carrying its branch (office 7).
$pdo->prepare("INSERT INTO partner_contracts (partner_id, contract_number, branch_id, open_status, is_active) VALUES (0,'OC/2026/1', 7, 'OPEN', 1)")->execute();

// Same-office call: no executing branch, no explicit contracting office on the row —
// only the contract number. The invoice must still resolve to the contract's branch.
$call = ['contract_number' => 'OC/2026/1', 'ibo_office_id' => null, 'contracting_office_id' => null, 'executing_office_id' => null];
$job  = ['contract_number' => 'OC/2026/1', 'contracting_office_id' => null, 'executing_office_id' => null];
t_ok(invoice_office_for($job, $call) === 7, 'a same-office call bills from the contract branch (no "No branch is set")');

// The call's own managing office wins when present (it was inherited from the contract).
$call2 = ['contract_number' => 'OC/2026/1', 'ibo_office_id' => 5];
t_ok(invoice_office_for([], $call2) === 5, 'the call managing/contracting office is used when set');

// The job's contracting office is the most specific and wins.
t_ok(invoice_office_for(['contracting_office_id' => 9], ['ibo_office_id' => 5]) === 9, 'the job contracting office wins when set');

// With nothing on the records and no branch on the contract, it falls to the
// executing office rather than nothing.
$callX = ['contract_number' => '', 'executing_office_id' => 3];
t_ok(invoice_office_for([], $callX) === 3, 'the executing office is the fallback when no contracting office exists');

// Registration captures the branch from the posted value, else the quote office.
$crm = file_get_contents(__DIR__ . '/../lib/crm.php');
t_ok(strpos($crm, "\$_POST['branch_id']") !== false, 'contract registration reads the chosen billing branch');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "\$ct['branch_id']") !== false, 'a call raised from a contract inherits the contract branch');
