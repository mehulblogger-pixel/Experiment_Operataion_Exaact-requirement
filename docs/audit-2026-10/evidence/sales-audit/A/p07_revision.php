<?php
// QUOTATION revisions.
require __DIR__ . '/plib.php';
function rev($who, $id, $sum = 'Revised') { $b = lastid('quotations'); $r = R($who, 'quote-revise', 'POST', ['id' => $id], ['summary' => $sum]); show("$who quote-revise #$id", $r); $n = lastid('quotations'); return $n > $b ? $n : 0; }
function mv($who, $id, $to, $x = []) { $r = R($who, 'quote-status', 'POST', ['id' => $id], array_merge(['to' => $to], $x)); show("$who #$id -> $to", $r); }
function chain($no) { foreach (ops_all("SELECT id FROM quotations WHERE quote_no=? ORDER BY rev, id", [$no]) as $r) echo "      " . qline($r['id']) . "\n"; }

H('1. revise a SENT quotation; then accept the SUPERSEDED rev 0');
[$a] = new_quote('MM'); setq($a, ['status' => 'SENT', 'sent_at' => date('c')]); $no = q($a)['quote_no'];
$a1 = rev('MM', $a, 'Client asked for a discount');
chain($no);
echo "      rev1 lines=" . cnt("SELECT COUNT(*) FROM quote_lines WHERE quote_id=?", [$a1]) . " ; snapshot rows=" . cnt("SELECT COUNT(*) FROM quote_revisions WHERE quote_id=?", [$a]) . "\n";
mv('MM', $a, 'ACCEPTED');
chain($no);
echo "      register 'awaiting contract' list shows: " . json_encode(array_column(ops_all("SELECT id FROM quotations WHERE is_current=1 AND status='ACCEPTED' AND quote_no=?", [$no]), 'id')) . " (is_current=1 filter hides the accepted rev 0)\n";
show('FIN registers contract on the superseded-but-accepted rev 0', R('FIN', 'quote-contract', 'POST', ['id' => $a], ['auto_contract' => '1']));
chain($no);

H('2. revise an ACCEPTED quotation that already has a contract');
[$b] = new_quote('MM'); setq($b, ['status' => 'SENT', 'sent_at' => date('c')]); $no = q($b)['quote_no'];
mv('MM', $b, 'ACCEPTED'); show('FIN contract', R('FIN', 'quote-contract', 'POST', ['id' => $b], ['auto_contract' => '1']));
$b1 = rev('MM', $b, 'Scope change after award');
chain($no);
echo "      the new DRAFT revision already carries contract_id=" . q($b1)['contract_id'] . " / number=" . q($b1)['contract_number'] . "\n";
R('MM', 'quote-edit', 'POST', ['id' => $b1], quote_post(['l_rate' => ['9000']]));
mv('MM', $b1, 'SENT'); mv('MM', $b1, 'LOST', ['lost_reason' => 'PRICE']);
chain($no);
echo "      contract still points at quotation_id=" . ops_val("SELECT quotation_id FROM partner_contracts WHERE id=?", [q($b)['contract_id']]) . " ; current revision is LOST while its contract is live\n";

H('3. revise a DRAFT (UI hides the button; server allows?) and a LOST one');
[$c] = new_quote('MM'); $c1 = rev('MM', $c); chain(q($c)['quote_no']);
[$d] = new_quote('MM'); setq($d, ['status' => 'SENT', 'sent_at' => date('c')]); mv('MM', $d, 'LOST', ['lost_reason' => 'PRICE']); $d1 = rev('MM', $d); chain(q($d)['quote_no']);

H('4. revise rev 0 AGAIN after rev 1 exists (stale page)');
[$e] = new_quote('MM'); setq($e, ['status' => 'SENT', 'sent_at' => date('c')]); $e1 = rev('MM', $e); $e1b = rev('MM', $e);
chain(q($e)['quote_no']);

H('5. revise rev 1, then is rev 0 still acceptable / sendable?');
[$f] = new_quote('MM'); mv('MM', $f, 'APPROVED'); $f1 = rev('MM', $f);
mv('MM', $f, 'SENT'); chain(q($f)['quote_no']);
echo "      two live offers out of one quote number? is_current: " . json_encode(ops_all("SELECT rev,is_current,status FROM quotations WHERE quote_no=?", [q($f)['quote_no']])) . "\n";

H('6. who may revise');
[$g] = new_quote('MM'); setq($g, ['status' => 'SENT', 'sent_at' => date('c')]);
foreach (['FIN','BM','MEX'] as $w) { $x = rev($w, $g); echo "      $w -> " . ($x ? 'created ' . qline($x) : 'refused') . "\n"; }

H('7. costing link survives a revision?');
[$h] = new_quote('MM');
db()->prepare("INSERT INTO project_costings (code,title,status,quote_id,created_at) VALUES ('PC-T1','t','APPROVED',?,?)")->execute([$h, date('c')]);
$h1 = rev('MM', $h);
echo "      pc_for_quote(rev0)=" . (pc_for_quote($h)['code'] ?? 'none') . " pc_for_quote(rev1)=" . (pc_for_quote($h1)['code'] ?? 'none') . "\n";
