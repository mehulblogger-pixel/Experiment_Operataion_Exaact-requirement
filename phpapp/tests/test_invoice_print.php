<?php
// The printable / PDF tax invoice reflects the same contract grouping as the
// on-screen bill: a combined invoice spanning several contracts breaks its lines
// under a contract sub-header; a single-project invoice shows none.
t_section('printable invoice groups lines by contract');

$render = function ($inv, $lines) {
    $office = ['name' => 'Ahmedabad', 'city' => 'Ahmedabad', 'address' => '1 Test Road'];
    $buyerAddr = ['line1' => '5 Client St', 'city' => 'Surat', 'state' => 'Gujarat', 'pincode' => '395001'];
    $settled = 0;
    ob_start();
    require __DIR__ . '/../views/ops/invoice_print.php';
    return (string) ob_get_clean();
};

$baseInv = [
    'id' => 1, 'invoice_no' => 'INV-1', 'invoice_date' => '2026-08-31', 'due_date' => '',
    'po_number' => '', 'contract_number' => '', 'status' => 'ISSUED',
    'client_name' => 'Client Co', 'client_gstin' => '', 'office_id' => 1,
    'subtotal' => 13000, 'cgst' => 1170, 'sgst' => 1170, 'igst' => 0, 'round_off' => 0, 'total' => 15340,
];
$mkLine = fn($cn, $desc, $amt) => ['contract_number' => $cn, 'description' => $desc, 'job_code' => '',
    'hsn_sac' => '998311', 'qty' => 1, 'rate' => $amt, 'amount' => $amt, 'gst_pct' => 18, 'line_total' => $amt * 1.18];

// Combined invoice across two contracts → both sub-headers appear.
$htmlMulti = $render($baseInv, [$mkLine('PRJ-A', 'Inspection A', 5000), $mkLine('PRJ-B', 'Deputation B', 8000)]);
t_ok(strpos($htmlMulti, 'TAX INVOICE') !== false, 'the print view renders a tax invoice');
t_ok(strpos($htmlMulti, 'Contract PRJ-A') !== false && strpos($htmlMulti, 'Contract PRJ-B') !== false,
    'a combined invoice shows a sub-header for each contract');
t_ok(strpos($htmlMulti, 'Subtotal — PRJ-A') !== false, 'each contract gets its own subtotal on the bill');
t_ok(strpos($htmlMulti, 'several — see below') !== false, 'the header notes the invoice spans several contracts');

// Single-contract invoice → no contract sub-headers (stays clean).
$single = array_merge($baseInv, ['contract_number' => 'PRJ-A']);
$htmlOne = $render($single, [$mkLine('PRJ-A', 'Inspection A', 5000)]);
t_ok(strpos($htmlOne, 'TAX INVOICE') !== false, 'a single-project invoice still renders');
t_ok(strpos($htmlOne, 'Subtotal — PRJ-A') === false, 'a single-project invoice shows no per-contract sub-header');
