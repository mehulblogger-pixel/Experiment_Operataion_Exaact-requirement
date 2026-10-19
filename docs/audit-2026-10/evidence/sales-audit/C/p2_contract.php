<?php
// P2 — accepted quotation -> contract registration: permission, required quote
// status, field mapping, duplicates, mid-way failure, amendment / reopen.
putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
$I = json_decode(file_get_contents(__DIR__ . '/ids.json'), true);
$L = [['Third-party inspection', 'TPI', 10, 'MANDAY', 8000], ['Final inspection visit', 'TPI', 4, 'VISIT', 5000]];
$cstate = fn($id) => row("SELECT id, partner_id, contract_number, title, value, start_date, end_date, branch_id, open_status, is_active, quotation_id, qty_total, qty_unit FROM partner_contracts WHERE id=?", [$id]);

echo "[2a] BDM (no crm.contract.register) tries to register on an ACCEPTED quote\n";
[$qa] = mkquote($I['business_dev_manager'], ['client_id' => $I['p_active'], 'office_id' => $I['office'], 'subject' => 'Annual TPI rate contract'], $L);
req($I['business_dev_manager'], 'quote-status', 'POST', ['id' => $qa], ['to' => 'SENT']);
req($I['finance'], 'quote-status', 'POST', ['id' => $qa], ['to' => 'ACCEPTED']);
db()->prepare("UPDATE quotations SET po_number='PO-4500123', po_date='2026-10-01' WHERE id=?")->execute([$qa]);
show('BDM quote-contract', req($I['business_dev_manager'], 'quote-contract', 'POST', ['id' => $qa], ['auto_contract' => 1]));
echo "  contracts on quote: " . (int)val("SELECT COUNT(*) FROM partner_contracts WHERE quotation_id=?", [$qa]) . "\n";

echo "\n[2b] FINANCE registers (one-click: auto number, nothing else posted)\n";
show('FIN quote-contract #1', req($I['finance'], 'quote-contract', 'POST', ['id' => $qa], ['auto_contract' => 1]));
$q = row("SELECT id, status, contract_number, contract_id, client_id, subtotal, gst_pct, gst_amount, total_amount, validity_days, po_number, payment_terms, office_id FROM quotations WHERE id=?", [$qa]);
echo "  quote: " . json_encode($q) . "\n";
$c = $cstate((int)$q['contract_id']); echo "  contract: " . json_encode($c) . "\n";
echo "  contract_line_items copied from quote: " . (int)val("SELECT COUNT(*) FROM contract_line_items WHERE contract_id=?", [(int)$q['contract_id']]) . " (quote has " . count(crm_quote_lines($qa)) . " lines)\n";
echo "  contract_state(): " . json_encode(array_intersect_key(contract_state($qa), array_flip(['state','qty_total','qty_used','qty_left','end_date']))) . "  <- quote_qty_total sums MANDAY 10 + VISIT 4\n";

echo "\n[2c] FINANCE registers the SAME accepted quote again (auto number)\n";
show('FIN quote-contract #2 auto', req($I['finance'], 'quote-contract', 'POST', ['id' => $qa], ['auto_contract' => 1]));
echo "\n[2c'] ... and again typing the SAME number\n";
show('FIN quote-contract #3 same no', req($I['finance'], 'quote-contract', 'POST', ['id' => $qa], ['contract_number' => $q['contract_number']]));
echo "  contracts for this client+quote: " . json_encode(rows("SELECT id, contract_number, open_status FROM partner_contracts WHERE quotation_id=?", [$qa])) . "\n";

echo "\n[2d] FINANCE registers a contract on a DRAFT quote (never approved, never sent, never accepted)\n";
[$qd] = mkquote($I['business_dev_manager'], ['client_id' => $I['p_active'], 'office_id' => $I['office'], 'subject' => 'Draft only'], $L);
show('FIN quote-contract on DRAFT', req($I['finance'], 'quote-contract', 'POST', ['id' => $qd], ['auto_contract' => 1]));
echo "  draft quote now: " . json_encode(row("SELECT status, contract_number, contract_id FROM quotations WHERE id=?", [$qd])) . "\n";
echo "\n[2d'] ... and on a LOST quote\n";
[$ql] = mkquote($I['business_dev_manager'], ['client_id' => $I['p_active'], 'office_id' => $I['office'], 'subject' => 'Lost one'], $L);
req($I['business_dev_manager'], 'quote-status', 'POST', ['id' => $ql], ['to' => 'SENT']);
req($I['business_dev_manager'], 'quote-status', 'POST', ['id' => $ql], ['to' => 'LOST', 'lost_reason' => 'PRICE']);
show('FIN quote-contract on LOST', req($I['finance'], 'quote-contract', 'POST', ['id' => $ql], ['auto_contract' => 1]));
echo "  lost quote now: " . json_encode(row("SELECT status, contract_number, contract_id FROM quotations WHERE id=?", [$ql])) . "\n";

echo "\n[2e] Mid-way failure: an injected fault on the quote UPDATE after the contract INSERT\n";
[$qf] = mkquote($I['business_dev_manager'], ['client_id' => $I['p_active'], 'office_id' => $I['office'], 'subject' => 'Fault injection'], $L);
req($I['business_dev_manager'], 'quote-status', 'POST', ['id' => $qf], ['to' => 'SENT']);
req($I['finance'], 'quote-status', 'POST', ['id' => $qf], ['to' => 'ACCEPTED']);
$nBefore = (int)val("SELECT COUNT(*) FROM partner_contracts");
db()->exec("CREATE TRIGGER probe_fail BEFORE UPDATE OF contract_id ON quotations WHEN NEW.id=$qf BEGIN SELECT RAISE(ABORT,'probe injected fault'); END");
show('FIN quote-contract with fault', req($I['finance'], 'quote-contract', 'POST', ['id' => $qf], ['contract_number' => 'PROBE/C/FAULT/1']));
db()->exec("DROP TRIGGER probe_fail");
echo "  partner_contracts rows: before $nBefore, after " . (int)val("SELECT COUNT(*) FROM partner_contracts") . "; orphan: " . json_encode(row("SELECT id, contract_number, open_status, quotation_id FROM partner_contracts WHERE contract_number='PROBE/C/FAULT/1'")) . "\n";
echo "  quote after fault: " . json_encode(row("SELECT status, contract_number, contract_id FROM quotations WHERE id=?", [$qf])) . "\n";
show('FIN retries the same number', req($I['finance'], 'quote-contract', 'POST', ['id' => $qf], ['contract_number' => 'PROBE/C/FAULT/1']));
echo "  quote after retry: " . json_encode(row("SELECT contract_number, contract_id FROM quotations WHERE id=?", [$qf])) . "\n";

echo "\n[2f] Open the contract (endorse by OM, approve by BM), then amend it\n";
$cid = (int)$q['contract_id'];
show('OM endorse', req($I['operation_manager'], 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'endorse']));
show('BM approve', req($I['branch_manager'], 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'approve']));
echo "  contract: " . json_encode($cstate($cid)) . "\n";
show('FIN edits OPEN contract value 118000 -> 500000 and end date', req($I['finance'], 'contract-edit', 'POST', [], ['id' => $cid, 'title' => 'Annual TPI rate contract', 'value' => 500000, 'start_date' => '2026-10-19', 'end_date' => '2027-03-31', 'notes' => 'amended']));
echo "  contract: " . json_encode($cstate($cid)) . "\n";
echo "  audit trail (activities): " . json_encode(rows("SELECT subject, body FROM activities WHERE subject LIKE ? ORDER BY id DESC LIMIT 2", ['%' . $c['contract_number'] . '%edited%'])) . "\n";
echo "  quote value vs contract value: quote total " . val("SELECT total_amount FROM quotations WHERE id=?", [$qa]) . " / contract " . val("SELECT value FROM partner_contracts WHERE id=?", [$cid]) . "\n";

echo "\n[2g] 'Reopen' an OPEN contract (no status guard?)\n";
show('FIN contract-open do=reopen on OPEN', req($I['finance'], 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'reopen']));
echo "  contract now: " . json_encode(row("SELECT open_status, mgr_endorsed_by, bm_approved_by FROM partner_contracts WHERE id=?", [$cid])) . "\n";
show('OM endorse', req($I['operation_manager'], 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'endorse']));
show('BM approve', req($I['branch_manager'], 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'approve']));
file_put_contents(__DIR__ . '/p2_ids.json', json_encode(['qa' => $qa, 'cid' => $cid, 'cno' => $c['contract_number'], 'qd' => $qd, 'ql' => $ql]));
