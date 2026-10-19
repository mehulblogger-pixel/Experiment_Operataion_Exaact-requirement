<?php
// QUOTATION status transitions through the real /quote-status route.
require __DIR__ . '/plib.php';
function mv($who, $id, $to, $extra = []) { $r = R($who, 'quote-status', 'POST', ['id' => $id], array_merge(['to' => $to], $extra)); show("$who $to", $r); echo "      -> " . qline($id) . "\n"; return $r; }
H('1. DRAFT -> SENT directly (no approval) by a sender');
[$a] = new_quote('MM'); mv('MM', $a, 'SENT');
echo "      approvals rows on it: " . cnt("SELECT COUNT(*) FROM quote_approvals WHERE quote_id=?", [$a]) . "\n";
[$a2] = new_quote('BDM'); mv('BDM', $a2, 'SENT');
H('2. DRAFT -> ACCEPTED directly');
[$b] = new_quote('MM'); mv('MM', $b, 'ACCEPTED');
[$b2] = new_quote('MEX'); mv('MEX', $b2, 'ACCEPTED');
H('3. DRAFT -> APPROVED by the creator (no rule configured): self-approval');
[$c] = new_quote('MM'); mv('MM', $c, 'APPROVED');
echo "      log: " . json_encode(array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=?", [$c]), 'summary')) . "\n";
H('4. view-only roles moving a quotation (route gate = mod.quotes.view)');
foreach (['FIN','BM','BD'] as $w) {
    [$d] = new_quote('MM'); setq($d, ['status' => 'SENT', 'sent_at' => date('c')]);
    mv($w, $d, 'ACCEPTED');
    [$e] = new_quote('MM'); setq($e, ['status' => 'SENT', 'sent_at' => date('c')]);
    mv($w, $e, 'LOST', ['lost_reason' => 'PRICE']);
    [$f] = new_quote('MM'); mv($w, $f, 'EXPIRED'); mv($w, $f, 'REJECTED');
}
H('5. terminal states: ACCEPTED <-> LOST, back to DRAFT');
[$g] = new_quote('MM'); setq($g, ['status' => 'SENT', 'sent_at' => date('c')]);
mv('MM', $g, 'ACCEPTED'); mv('MM', $g, 'LOST', ['lost_reason' => 'PRICE']); mv('MM', $g, 'ACCEPTED'); mv('MM', $g, 'DRAFT'); mv('ADMIN', $g, 'DRAFT');
echo "      log rows for #$g: " . json_encode(array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=?", [$g]), 'summary')) . "\n";
[$h] = new_quote('MM'); mv('MM', $h, 'ACCEPTED');      // never sent -> sent_at empty -> not locked for a master
mv('ADMIN', $h, 'DRAFT'); mv('MM', $h, 'LOST', ['lost_reason' => 'PRICE']); mv('MM', $h, 'DRAFT');
H('6. LOST without a reason');
[$k] = new_quote('MM'); setq($k, ['status' => 'SENT', 'sent_at' => date('c')]); mv('MM', $k, 'LOST');
echo "      lost_reason='" . ops_val("SELECT lost_reason FROM quotations WHERE id=?", [$k]) . "'\n";
H('7. expiry: SENT 40 days ago with 30-day validity, then the daily sweep');
[$m] = new_quote('MM'); setq($m, ['status' => 'SENT', 'sent_at' => date('c', strtotime('-40 days'))]);
[$m2] = new_quote('MM'); setq($m2, ['status' => 'APPROVED']);   // approved, never sent, old
[$m3] = new_quote('MM'); setq($m3, ['status' => 'SENT', 'sent_at' => date('c', strtotime('-40 days')), 'validity_days' => 0]);
echo "      crm_expire_quotes() stamped " . crm_expire_quotes() . "\n";
echo "      " . qline($m) . " | " . qline($m2) . " | validity 0: " . qline($m3) . "\n";
mv('MM', $m, 'ACCEPTED');
echo "      log: " . json_encode(array_column(ops_all("SELECT summary FROM quote_revisions WHERE quote_id=?", [$m]), 'summary')) . "\n";
[$m4] = new_quote('MM'); setq($m4, ['status' => 'SENT', 'sent_at' => date('c', strtotime('-40 days'))]); crm_expire_quotes();
mv('MM', $m4, 'SENT'); mv('MM', $m4, 'LOST', ['lost_reason' => 'PRICE']);
H('8. which status changes leave a trace in quote_revisions (the quote history) or activities');
foreach ([$a => 'DRAFT->SENT', $b => 'DRAFT->ACCEPTED', $k => 'SENT->LOST'] as $id => $what)
    printf("      %-16s quote_revisions=%d activities=%d\n", $what, cnt("SELECT COUNT(*) FROM quote_revisions WHERE quote_id=?", [$id]), cnt("SELECT COUNT(*) FROM activities WHERE entity_kind='QUOTE' AND entity_id=?", [$id]));
