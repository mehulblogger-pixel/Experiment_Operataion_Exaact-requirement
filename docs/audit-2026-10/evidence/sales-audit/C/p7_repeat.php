<?php
// P7 — repeat orders / rate contracts: a second order under an existing contract,
// and the PO path that DOES carry a rate onto the work order.
putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
$I = json_decode(file_get_contents(__DIR__ . '/ids.json'), true); $P = json_decode(file_get_contents(__DIR__ . '/p2_ids.json'), true);
$qa = (int)$P['qa']; $cid = (int)$P['cid']; $cno = $P['cno']; $fin = $I['finance']; $co = $I['coordinator']; $bdm = $I['business_dev_manager'];
echo "contract #$cid before: " . json_encode(row("SELECT contract_number, quotation_id, value, open_status FROM partner_contracts WHERE id=?", [$cid])) . "\n";
echo "\n[7a] A NEW quote for a repeat order, registered under the EXISTING open contract number\n";
[$qb] = mkquote($bdm, ['client_id' => $I['p_active'], 'office_id' => $I['office'], 'subject' => 'Repeat order Q2'], [['TPI repeat', 'TPI', 5, 'MANDAY', 7500]]);
req($bdm, 'quote-status', 'POST', ['id' => $qb], ['to' => 'SENT']); req($fin, 'quote-status', 'POST', ['id' => $qb], ['to' => 'ACCEPTED']);
show('FIN quote-contract with existing number', req($fin, 'quote-contract', 'POST', ['id' => $qb], ['contract_number' => $cno]));
echo "  contract #$cid after: " . json_encode(row("SELECT contract_number, quotation_id, value, open_status FROM partner_contracts WHERE id=?", [$cid])) . "\n";
echo "  original quote #$qa still says contract_id=" . val("SELECT contract_id FROM quotations WHERE id=?", [$qa]) . "; contract now points at quote #" . val("SELECT quotation_id FROM partner_contracts WHERE id=?", [$cid]) . "\n";
echo "  contract_state_row(contract) now reads quote #" . (contract_state_row(row("SELECT * FROM partner_contracts WHERE id=?", [$cid]))['quotation_id'] ?? '?') . ": " . json_encode(array_intersect_key(contract_state_row(row("SELECT * FROM partner_contracts WHERE id=?", [$cid])), array_flip(['state','qty_total','qty_used','qty_left']))) . "\n";
echo "  contract_state(original quote #$qa): " . json_encode(array_intersect_key(contract_state($qa), array_flip(['state','qty_total','qty_used','qty_left']))) . "\n";
echo "  call-new?contract_id=$cid now prefills quotation_id=" . json_encode((req($co, 'call-new', 'GET', ['contract_id' => $cid])['vars']['call']['quotation_id']) ?? null) . "\n";

echo "\n[7b] PO with lines pulled from the quote, then a work order off the contract: is the rate carried?\n";
db()->prepare("INSERT INTO partner_purchase_orders (partner_id, contract_id, po_number, title, value, start_date, end_date, is_active) VALUES (?,?,?,?,?,?,?,1)")
   ->execute([$I['p_active'], $cid, 'PO-REP-1', 'Repeat PO', 40000, '2026-10-19', '2027-03-31']);
$po = (int)db()->lastInsertId();
echo "  po_pull_quote_lines: " . json_encode(po_pull_quote_lines($po, $qb)) . "\n";
$o = req($co, 'call-new', 'GET', ['contract_id' => $cid]); echo "  prefill: " . json_encode($o['vars']['call'] ?? null) . "\n";
echo "  call_commercial_prefill: " . json_encode(call_commercial_prefill($cid)) . "\n";
