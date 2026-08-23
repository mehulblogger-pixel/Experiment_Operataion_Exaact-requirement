<?php
// The invoice screen leads with a "This invoice bills" panel that ties the bill to
// its customer, branch, contract, PO, business unit and the jobs it charges — the
// interlinks in one place instead of scattered down the page.
t_section('the invoice screen shows its order references up front');

$src = file_get_contents(__DIR__ . '/../views/ops/invoice_detail.php');

t_ok(strpos($src, 'This invoice bills') !== false, 'a "This invoice bills" summary panel is present');
t_ok(strpos($src, '/ledger?id=') !== false, 'the customer links to their ledger');
t_ok(strpos($src, '/calls?contract=') !== false, 'the contract number links to its calls');
t_ok(strpos($src, "Jobs on this invoice") !== false && strpos($src, '/job?id=') !== false, 'the jobs it charges are shown as links');
t_ok(strpos($src, "OPS_SBUS[\$inv['sbu']]") !== false, 'the business unit is shown when set');

// PO / contract are no longer duplicated inside the Tax-treatment panel.
$taxBlock = substr($src, strpos($src, 'Tax treatment'));
$taxBlock = substr($taxBlock, 0, strpos($taxBlock, 'What is being charged') ?: strlen($taxBlock));
t_ok(strpos($taxBlock, "<span class=\"k\">PO</span>") === false, 'PO is not duplicated in the tax panel');
t_ok(strpos($taxBlock, "<span class=\"k\">Contract</span>") === false, 'Contract is not duplicated in the tax panel');
