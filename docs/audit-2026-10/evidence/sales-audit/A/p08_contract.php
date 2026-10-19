<?php
// QUOTE -> CONTRACT transition.
require __DIR__ . '/plib.php';
function mv($who, $id, $to, $x = []) { $r = R($who, 'quote-status', 'POST', ['id' => $id], array_merge(['to' => $to], $x)); show("$who #$id -> $to", $r); }
function reg($who, $id, $post = ['auto_contract' => '1']) { $r = R($who, 'quote-contract', 'POST', ['id' => $id], $post); show("$who quote-contract #$id " . json_encode($post), $r); echo "      " . qline($id) . "\n"; }
function contracts() { foreach (ops_all("SELECT id, contract_number, quotation_id, open_status FROM partner_contracts ORDER BY id") as $c) echo "      contract #{$c['id']} {$c['contract_number']} quotation={$c['quotation_id']} {$c['open_status']}\n"; }

H('1. register a contract on a quotation that is NOT accepted');
[$a] = new_quote('MM'); reg('FIN', $a);                                   // DRAFT
[$b] = new_quote('MM'); setq($b, ['status' => 'SENT', 'sent_at' => date('c')]); mv('MM', $b, 'LOST', ['lost_reason' => 'PRICE']); reg('FIN', $b);   // LOST
[$c] = new_quote('MM'); mv('MM', $c, 'PENDING_APPROVAL'); reg('FIN', $c); // PENDING_APPROVAL
show('MM (no contract right) tries', R('MM', 'quote-contract', 'POST', ['id' => $c], ['auto_contract' => '1']));
contracts();

H('2. the same accepted quotation registered twice');
[$d] = new_quote('MM'); setq($d, ['status' => 'SENT', 'sent_at' => date('c')]); mv('MM', $d, 'ACCEPTED');
reg('FIN', $d, ['contract_number' => 'CN-PROBE-1']);
reg('FIN', $d, ['contract_number' => 'CN-PROBE-2']);      // different number while first is PENDING
reg('FIN', $d, ['contract_number' => 'CN-PROBE-1']);      // same number again
$cid = (int)q($d)['contract_id'];
show('BM rejects the opening', R('BM', 'contract-open', 'POST', [], ['id' => $cid, 'do' => 'reject', 'note' => 'wrong value']));
reg('FIN', $d, ['contract_number' => 'CN-PROBE-3']);      // after rejection: fresh registration
contracts();
echo "      contracts pointing at quote #$d: " . cnt("SELECT COUNT(*) FROM partner_contracts WHERE quotation_id=?", [$d]) . "\n";

H('3. two concurrent registrations of the same accepted quotation (auto numbers)');
[$e] = new_quote('MM'); setq($e, ['status' => 'SENT', 'sent_at' => date('c')]); mv('MM', $e, 'ACCEPTED');
$rs = RACE('FIN', 'quote-contract', 'POST', ['id' => $e], ['auto_contract' => '1'], 2); foreach ($rs as $j => $r) show("race worker$j", $r);
echo "      " . qline($e) . " ; contracts with quotation_id=$e: " . cnt("SELECT COUNT(*) FROM partner_contracts WHERE quotation_id=?", [$e]) . " ; all PENDING contracts for client: " . cnt("SELECT COUNT(*) FROM partner_contracts WHERE notes LIKE ?", ['From quotation ' . q($e)['quote_no'] . '%']) . "\n";

H('4. accepted quotation with a live contract is then marked LOST');
[$f] = new_quote('MM'); setq($f, ['status' => 'SENT', 'sent_at' => date('c')]); mv('MM', $f, 'ACCEPTED'); reg('FIN', $f);
$fc = (int)q($f)['contract_id'];
mv('MM', $f, 'LOST', ['lost_reason' => 'PRICE']);
echo "      " . qline($f) . " ; contract #$fc status " . ops_val("SELECT open_status FROM partner_contracts WHERE id=?", [$fc]) . "\n";
H('5. two different quotations of the same client registering the same number (rate-contract draw-down)');
[$g] = new_quote('MM'); mv('MM', $g, 'ACCEPTED'); reg('FIN', $g, ['contract_number' => 'RC-SHARED']);
[$h] = new_quote('MM'); mv('MM', $h, 'ACCEPTED'); reg('FIN', $h, ['contract_number' => 'RC-SHARED']);
echo "      partner_contracts RC-SHARED rows=" . cnt("SELECT COUNT(*) FROM partner_contracts WHERE contract_number='RC-SHARED'") . " quotation_id=" . ops_val("SELECT quotation_id FROM partner_contracts WHERE contract_number='RC-SHARED'") . "\n";
