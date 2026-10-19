<?php
// P8 — with an approval rule ACTIVE (every amount, 1 level, Marketing Manager),
// can a quote still reach SENT / ACCEPTED without the chain? And quote-external.
putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
$I = json_decode(file_get_contents(__DIR__ . '/ids.json'), true);
$bdm = $I['business_dev_manager']; $fin = $I['finance'];
db()->prepare("INSERT INTO quote_approval_rules (name, match_type, min_amount, max_amount, level, approver_role, active, created_at) VALUES ('All quotes', 'ANY', 0, 0, 1, 'MARKETING_MANAGER', 1, ?)")->execute([date('c')]);
$L = [['TPI', 'TPI', 10, 'MANDAY', 9000]];
[$q] = mkquote($bdm, ['client_id' => $I['p_active'], 'office_id' => $I['office']], $L);
echo "rule active; crm_quote_needs_chain=" . var_export(crm_quote_needs_chain(crm_quote_get($q)), true) . "\n";
show('BDM APPROVED directly (expect: routed to chain)', req($bdm, 'quote-status', 'POST', ['id' => $q], ['to' => 'APPROVED']));
echo "  status: " . qstatus($q) . "\n";
[$q2] = mkquote($bdm, ['client_id' => $I['p_active'], 'office_id' => $I['office']], $L);
show('BDM DRAFT -> SENT with chain required', req($bdm, 'quote-status', 'POST', ['id' => $q2], ['to' => 'SENT']));
echo "  status: " . qstatus($q2) . ", approvals rows " . (int)val("SELECT COUNT(*) FROM quote_approvals WHERE quote_id=?", [$q2]) . "\n";
[$q3] = mkquote($bdm, ['client_id' => $I['p_active'], 'office_id' => $I['office']], $L);
show('FIN DRAFT -> ACCEPTED with chain required', req($fin, 'quote-status', 'POST', ['id' => $q3], ['to' => 'ACCEPTED']));
echo "  status: " . qstatus($q3) . "\n";
echo "\n[8b] quote-external registered straight as ACCEPTED\n";
$b = (int)val("SELECT MAX(id) FROM quotations");
show('BDM quote-external status=ACCEPTED', req($bdm, 'quote-external', 'POST', [], ['client_id' => $I['p_active'], 'status' => 'ACCEPTED', 'origin' => 'TENDER_PORTAL', 'total_amount' => 2500000, 'subject' => 'Tender won', 'gst_pct' => 18]));
$qe = (int)val("SELECT MAX(id) FROM quotations");
echo "  " . json_encode(row("SELECT id, status, origin, total_amount, accepted_date, sent_at FROM quotations WHERE id=?", [$qe])) . " approvals rows " . (int)val("SELECT COUNT(*) FROM quote_approvals WHERE quote_id=?", [$qe]) . "\n";
echo "  appears in quotes_awaiting_contract: " . (in_array($qe, array_map('intval', array_column(quotes_awaiting_contract(1000), 'id')), true) ? 'yes' : 'no') . "\n";
db()->exec("UPDATE quote_approval_rules SET active=0");
