<?php
// Follow-up authority probes (run after flow_perm.php on the same DB).
require __DIR__ . '/flow_lib.php';
$QM = q1("SELECT * FROM quotations WHERE subject='QM own quote'");
$stp = q1("SELECT * FROM quote_approvals WHERE quote_id=? AND status='PENDING' ORDER BY level LIMIT 1", [$QM['id']]);
$r = act('mm', 'quote-approve', 'POST', ['id' => $QM['id']], ['step' => (string)$stp['id'], 'decision' => 'approve']);
step('P11b', "MARKETING_MANAGER approves the step on his OWN quote {$QM['quote_no']} (step routed to role MARKETING_MANAGER)", 'refused — creator ≠ approver',
     $r['say'] . '; quote status=' . qv("SELECT status FROM quotations WHERE id=?", [$QM['id']]));
// small OGC quote: no rule matches → one-click approve path
act('mm', 'quote-new', 'POST', [], quote_post(['subject' => 'QS small own', 'sbu' => 'OGC', 'l_sbu' => ['OGC'], 'l_qty' => ['1']])); $QS = lastq();
$r = act('mm', 'quote-status', 'POST', ['id' => $QS['id']], ['to' => 'APPROVED']);
step('P11c', "MARKETING_MANAGER one-click approves his own small quote {$QS['quote_no']} (total {$QS['total_amount']}, no rule matches)", 'refused, or at least logged as self-approval',
     $r['say'] . '; status=' . qv("SELECT status FROM quotations WHERE id=?", [$QS['id']]) . '; revisions log rows=' . qv("SELECT COUNT(*) FROM quote_revisions WHERE quote_id=?", [$QS['id']]));
// BM tries one-click APPROVED (no crm.quote.approve)
act('bdmA', 'quote-new', 'POST', [], quote_post(['subject' => 'QT bm one-click', 'sbu' => 'OGC', 'l_sbu' => ['OGC'], 'l_qty' => ['1']])); $QT = lastq();
$r = act('bmA', 'quote-status', 'POST', ['id' => $QT['id']], ['to' => 'APPROVED']);
step('P11d', 'BRANCH_MANAGER (no crm.quote.approve) one-click APPROVED', 'refused', $r['say'] . '; status=' . qv("SELECT status FROM quotations WHERE id=?", [$QT['id']]));
$r = act('bmA', 'quote-status', 'POST', ['id' => $QT['id']], ['to' => 'REJECTED']);
step('P11e', 'BRANCH_MANAGER (quotes view only) sets the same DRAFT to REJECTED via quote-status', 'refused (only an approver rejects, with a reason)', $r['say'] . '; status=' . qv("SELECT status FROM quotations WHERE id=?", [$QT['id']]));

// P9b — the 5.9L quote with the deactivated L3 approver
$QD = q1("SELECT * FROM quotations WHERE subject='QD 5.9L'");
$st = q("SELECT * FROM quote_approvals WHERE quote_id=? ORDER BY level", [$QD['id']]);
act('bmA', 'quote-approve', 'POST', ['id' => $QD['id']], ['step' => (string)$st[0]['id'], 'decision' => 'approve']);
act('mm',  'quote-approve', 'POST', ['id' => $QD['id']], ['step' => (string)$st[1]['id'], 'decision' => 'approve']);
$r = act('mm', 'quote-approve', 'POST', ['id' => $QD['id']], ['step' => (string)$st[2]['id'], 'decision' => 'approve']);
$who = shell_exec('cd /home/user/Experiment_Operataion_Exaact-requirement/phpapp && php -r ' . escapeshellarg('require "' . __DIR__ . '/lib_boot.php"; as_user(' . U('mm') . '); echo crm_pending_with(' . (int)$QD['id'] . '), " | mm awaiting=", crm_quotes_awaiting_me(); as_user(' . U('master') . '); echo " | master awaiting=", crm_quotes_awaiting_me();') . ' 2>/dev/null');
step('P9b', "After L1 and L2 approve {$QD['quote_no']}, L3 is the deactivated user; MARKETING_MANAGER tries L3", 'the system says the approver is inactive and offers a re-route',
     'mm on L3: ' . $r['say'] . '; quote status=' . qv("SELECT status FROM quotations WHERE id=?", [$QD['id']]) . "; crm_pending_with()/awaiting: $who");
file_put_contents(__DIR__ . '/flow_perm2.out', implode('', $GLOBALS['LOG']));
