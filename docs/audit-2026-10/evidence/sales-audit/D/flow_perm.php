<?php
// Server-side authority probes through the real handlers (one process per call).
// Run after a fresh seed.php. Every line below was EXECUTED; outputs land in flow_perm.out.
require __DIR__ . '/flow_lib.php';
$A = $GLOBALS['IDS']['A']; $C = $GLOBALS['IDS']['C'];

// ---- approval rules (master) ----
act('master', 'quote-approval-rule-new', 'POST', [], ['name'=>'L1 branch manager (IND)','level'=>'1','match_type'=>'Business Unit','sbu'=>'IND','min_amount'=>'0','max_amount'=>'0','approver_role'=>'BRANCH_MANAGER','active'=>'1']);
act('master', 'quote-approval-rule-new', 'POST', [], ['name'=>'L2 marketing manager >=1L','level'=>'2','match_type'=>'ANY','min_amount'=>'100000','max_amount'=>'0','approver_role'=>'MARKETING_MANAGER','active'=>'1']);
$r = act('master', 'quote-approval-rule-new', 'POST', [], ['name'=>'L3 named person >=5L','level'=>'3','match_type'=>'ANY','min_amount'=>'500000','max_amount'=>'0','approver_role'=>'','approver_user_id'=>(string)U('gone'),'active'=>'1']);
step('P0', 'Master saves an approval rule naming a DEACTIVATED user (id ' . U('gone') . ') as approver', 'refused — approver must be an active user',
     $r['say'] . '; rules stored=' . qv("SELECT COUNT(*) FROM quote_approval_rules") . ', L3 approver_user_id=' . qv("SELECT approver_user_id FROM quote_approval_rules WHERE level=3"));

// ---- blank quote ----
$before = (int)qv("SELECT COUNT(*) FROM quotations");
$r = act('bdmA', 'quote-new', 'POST', [], []);
$after = (int)qv("SELECT COUNT(*) FROM quotations"); $b = lastq();
step('P1', 'BDM submits /quote-new completely empty', 'refused with a message naming the missing client / line / price',
     $r['say'] . "; quotations $before→$after" . ($b ? "; new row {$b['quote_no']} status={$b['status']} client_id=" . var_export($b['client_id'], true) . " client_name='{$b['client_name']}' total={$b['total_amount']}" : ''));

// ---- QA: 1,18,000 IND, by BDM ----
act('bdmA', 'quote-new', 'POST', [], quote_post(['subject' => 'QA 1.18L']));  $QA = lastq();
act('bdmA', 'quote-new', 'POST', [], quote_post(['subject' => 'QB 59k', 'l_qty' => ['5']])); $QB = lastq();
// P2 — send a DRAFT that the rules say must be approved
$need = 'rules match (L1 IND any amount)';
$r = act('bdmA', 'quote-status', 'POST', ['id' => $QB['id']], ['to' => 'SENT']);
$st = q1("SELECT status, sent_at FROM quotations WHERE id=?", [$QB['id']]);
step('P2', "BDM (crm.quote.send) posts quote-status to=SENT on DRAFT {$QB['quote_no']} total {$QB['total_amount']} — $need, never submitted for approval",
     'refused: a quote matching an approval rule cannot go to the client before the chain is complete',
     $r['say'] . "; status now {$st['status']}, sent_at=" . ($st['sent_at'] ? 'set' : 'empty') . ', approval steps=' . qv("SELECT COUNT(*) FROM quote_approvals WHERE quote_id=?", [$QB['id']]));

// P3 — Finance (quotes VIEW only) marks a sent quote ACCEPTED
$r = act('fin', 'quote-status', 'POST', ['id' => $QB['id']], ['to' => 'ACCEPTED']);
$st = q1("SELECT status, accepted_date, client_id FROM quotations WHERE id=?", [$QB['id']]);
step('P3', "FINANCE (mod.quotes.view only, no crm.quote.*) posts quote-status to=ACCEPTED on SENT {$QB['quote_no']}", 'refused — recording the customer\'s acceptance is a sales act (matrix: FIN has no quotation right)',
     $r['say'] . "; status now {$st['status']} accepted_date={$st['accepted_date']}");

// P4 — Branch manager (view) marks a quote LOST with no reason; also Finance moves a quote back to DRAFT
act('bdmA', 'quote-new', 'POST', [], quote_post(['subject' => 'QL lost-no-reason'])); $QL = lastq();
$r = act('bmA', 'quote-status', 'POST', ['id' => $QL['id']], ['to' => 'LOST']);
$st = q1("SELECT status, lost_reason FROM quotations WHERE id=?", [$QL['id']]);
step('P4', "BRANCH_MANAGER (quotes view) posts quote-status to=LOST on {$QL['quote_no']} with NO lost_reason", 'refused: no permission to change a quote, and a loss needs a reason (UI marks it required)',
     $r['say'] . "; status now {$st['status']} lost_reason='" . $st['lost_reason'] . "'");
$r = act('fin', 'quote-status', 'POST', ['id' => $QL['id']], ['to' => 'DRAFT']);
step('P4b', "FINANCE posts quote-status to=DRAFT on the LOST quote {$QL['quote_no']}", 'refused', $r['say'] . '; status now ' . qv("SELECT status FROM quotations WHERE id=?", [$QL['id']]));

// P5 — submit QA (118000): chain L1 BM (IND) + L2 MM
act('bdmA', 'quote-status', 'POST', ['id' => $QA['id']], ['to' => 'PENDING_APPROVAL']);
$steps = q("SELECT id, level, approver_role, approver_user_id, status FROM quote_approvals WHERE quote_id=? ORDER BY level", [$QA['id']]);
$s1 = $steps[0]['id']; $s2 = $steps[1]['id'] ?? 0;
step('P5', "BDM submits {$QA['quote_no']} (118000, IND) for approval", 'two steps: L1 BRANCH_MANAGER, L2 MARKETING_MANAGER', json_encode($steps));

// P6 — out-of-order: L2 approver acts before L1
$r = act('mm', 'quote-approve', 'POST', ['id' => $QA['id']], ['step' => (string)$s2, 'decision' => 'approve']);
step('P6', 'MARKETING_MANAGER approves the LEVEL-2 step while level 1 is still pending', 'refused — level 2 is not yet the current step',
     $r['say'] . '; steps now ' . json_encode(q("SELECT level,status,acted_by FROM quote_approvals WHERE quote_id=? ORDER BY level", [$QA['id']])));

// P7 — branch manager of OFFICE B approves office A's level-1 step
$inboxB = act('bmB', 'quotes', 'GET', ['mine' => 'approve']);
$open = act('bmB', 'quote', 'GET', ['id' => $QA['id']]);
step('P7a', "BRANCH_MANAGER of office B opens 'Awaiting my approval' and then the quote itself ({$QA['quote_no']}, office A)",
     'consistent: either the quote is not in B\'s inbox, or B may open it',
     'inbox ' . $inboxB['say'] . ' (rows listed via SQL with no office scope — crm.php:1433-1447); opening the quote: ' . $open['say']);
$r = act('bmB', 'quote-approve', 'POST', ['id' => $QA['id']], ['step' => (string)$s1, 'decision' => 'approve']);
$st1 = q1("SELECT status, acted_by FROM quote_approvals WHERE id=?", [$s1]);
step('P7b', 'BRANCH_MANAGER office B posts quote-approve WITH ?id=<quote> (what the screen posts)', 'refused — outside branch scope',
     $r['say'] . "; step1 status={$st1['status']}");
$r = act('bmB', 'quote-approve', 'POST', [], ['step' => (string)$s1, 'decision' => 'approve']);
$st1 = q1("SELECT status, acted_by FROM quote_approvals WHERE id=?", [$s1]);
step('P7c', 'BRANCH_MANAGER office B posts quote-approve with ONLY step=<id> in the body (no ?id)', 'refused — outside branch scope (same as P7b)',
     $r['say'] . "; step1 status={$st1['status']} acted_by={$st1['acted_by']}");
$r = act('mm', 'quote-approve', 'POST', ['id' => $QA['id']], ['step' => (string)$s2, 'decision' => 'approve']);
$QA = q1("SELECT * FROM quotations WHERE id=?", [$QA['id']]);
step('P7d', 'MARKETING_MANAGER then approves level 2', 'quote APPROVED (chain complete)', $r['say'] . "; quote status={$QA['status']} total={$QA['total_amount']}");

// P8 — edit an APPROVED quote: triple the price, then send
$r = act('bdmA', 'quote-edit', 'POST', ['id' => $QA['id']], quote_post(['subject' => 'QA edited after approval', 'l_qty' => ['30']]));
$QA2 = q1("SELECT status, total_amount FROM quotations WHERE id=?", [$QA['id']]);
$r2 = act('bdmA', 'quote-status', 'POST', ['id' => $QA['id']], ['to' => 'SENT']);
$QA3 = q1("SELECT status, total_amount FROM quotations WHERE id=?", [$QA['id']]);
step('P8', "BDM edits the APPROVED {$QA['quote_no']} (qty 10→30, total {$QA['total_amount']}→?) then sends it", 'edit refused, or approval reset to DRAFT / re-approval required (total now crosses the 5L rule)',
     'edit: ' . $r['say'] . "; after edit status={$QA2['status']} total={$QA2['total_amount']}; send: " . $r2['say'] . "; final status={$QA3['status']} total={$QA3['total_amount']}");

// P9 — inactive named approver at level 3 (590000)
act('bdmA', 'quote-new', 'POST', [], quote_post(['subject' => 'QD 5.9L', 'l_qty' => ['50']])); $QD = lastq();
act('bdmA', 'quote-status', 'POST', ['id' => $QD['id']], ['to' => 'PENDING_APPROVAL']);
$st = q("SELECT a.id, a.level, a.approver_role, a.approver_user_id, u.username, u.is_active FROM quote_approvals a LEFT JOIN users u ON u.id=a.approver_user_id WHERE quote_id=? ORDER BY level", [$QD['id']]);
step('P9', "Quote {$QD['quote_no']} (590000) submitted; L3 is the deactivated user", 'chain refuses an inactive approver or reroutes to an active one',
     json_encode($st));

// P10 — Finance registers a contract on a DRAFT quote
act('bdmA', 'quote-new', 'POST', [], quote_post(['subject' => 'QE draft only'])); $QE = lastq();
$r = act('fin', 'quote-contract', 'POST', ['id' => $QE['id']], ['contract_number' => 'CN-PROBE-DRAFT']);
$st = q1("SELECT status, contract_number, contract_id FROM quotations WHERE id=?", [$QE['id']]);
step('P10', "FINANCE posts quote-contract on DRAFT {$QE['quote_no']} (never approved, sent or accepted)", 'refused — only an ACCEPTED quote becomes a contract',
     $r['say'] . "; quote status={$st['status']} contract_number={$st['contract_number']} contract row=" . json_encode(q1("SELECT open_status, value FROM partner_contracts WHERE id=?", [(int)$st['contract_id']])));

// P11 — Marketing manager self-approval (OGC quote matches no IND rule and is < 1L → generic step)
act('mm', 'quote-new', 'POST', [], quote_post(['subject' => 'QM own quote', 'sbu' => 'OGC', 'l_sbu' => ['OGC']])); $QM = lastq();
$r = act('mm', 'quote-status', 'POST', ['id' => $QM['id']], ['to' => 'APPROVED']);
step('P11', "MARKETING_MANAGER creates {$QM['quote_no']} and sets it APPROVED himself (one click)", 'refused — the creator cannot approve his own quote (stage gates already enforce requestor≠approver)',
     $r['say'] . '; status now ' . qv("SELECT status FROM quotations WHERE id=?", [$QM['id']]) . '; owner_id=' . qv("SELECT owner_id FROM quotations WHERE id=?", [$QM['id']]) . ' (mm=' . U('mm') . ')');

// P12 — Finance registers a contract for a deal WON without a quotation
$r0 = act('master', 'opportunity-new', 'POST', [], ['name' => 'Direct win deal', 'partner_id' => (string)$C, 'value' => '250000', 'office_id' => (string)$A]);
$O = q1("SELECT * FROM opportunities ORDER BY id DESC LIMIT 1");
$won = qv("SELECT id FROM pipeline_stages WHERE pipeline_id=? AND kind='WON'", [$O['pipeline_id']]);
act('master', 'opportunity-move', 'POST', [], ['id' => (string)$O['id'], 'stage_id' => (string)$won]);
act('master', 'opportunity-send-to-accounts', 'POST', [], ['id' => (string)$O['id']]);
$r = act('fin', 'opportunity-contract', 'POST', [], ['id' => (string)$O['id'], 'auto_contract' => '1']);
$o2 = q1("SELECT status, sent_to_accounts_at, contract_id FROM opportunities WHERE id=?", [$O['id']]);
$rv = act('fin', 'opportunity', 'GET', ['id' => $O['id']]);
step('P12', "FINANCE (holds crm.contract.register) registers the contract for {$O['ref']} — WON with no quotation and handed to Accounts", 'allowed — Accounts is the only role that registers contracts',
     'POST: ' . $r['say'] . '; opening the deal: ' . $rv['say'] . "; deal status={$o2['status']} sent_to_accounts=" . ($o2['sent_to_accounts_at'] ? 'yes' : 'no') . ' contract_id=' . var_export($o2['contract_id'], true));

// P13 — project costing: coordinator creates; branch manager approves a DRAFT; coordinator reopens the approval
$r = act('coA', 'project-costing-new', 'POST', [], ['title' => 'Probe costing', 'office_id' => (string)$A, 'basis' => 'LONG']);
$pc = q1("SELECT * FROM project_costings ORDER BY id DESC LIMIT 1");
step('P13a', 'COORDINATOR creates a project costing (cost build-up, margins)', 'matrix has no costing row — expected per doc 01-SALES: sales/management only',
     $r['say'] . '; created=' . ($pc ? $pc['code'] . ' by ' . $pc['created_by'] : 'none'));
$r = act('bmA', 'project-costing-approve', 'POST', [], ['id' => (string)$pc['id']]);
step('P13b', "BRANCH_MANAGER approves costing {$pc['code']} while it is still DRAFT (never submitted)", 'refused — only a SUBMITTED costing can be approved',
     $r['say'] . '; status now ' . qv("SELECT status FROM project_costings WHERE id=?", [$pc['id']]) . ' approved_by=' . qv("SELECT approved_by FROM project_costings WHERE id=?", [$pc['id']]));
$r = act('coA', 'project-costing-reopen', 'POST', [], ['id' => (string)$pc['id']]);
step('P13c', 'COORDINATOR (no approve right) reopens the APPROVED costing to DRAFT', 'refused — undoing an approval needs the approver',
     $r['say'] . '; status now ' . qv("SELECT status FROM project_costings WHERE id=?", [$pc['id']]));
act('bmA', 'project-costing-new', 'POST', [], ['title' => 'BM own costing', 'office_id' => (string)$A]); $pc2 = q1("SELECT * FROM project_costings ORDER BY id DESC LIMIT 1");
act('bmA', 'project-costing-submit', 'POST', [], ['id' => (string)$pc2['id']]);
$r = act('bmA', 'project-costing-approve', 'POST', [], ['id' => (string)$pc2['id']]);
step('P13d', 'BRANCH_MANAGER creates, submits and approves his OWN costing', 'refused — creator ≠ approver',
     $r['say'] . '; status=' . qv("SELECT status FROM project_costings WHERE id=?", [$pc2['id']]) . ' created_by=' . $pc2['created_by'] . ' approved_by=' . qv("SELECT approved_by FROM project_costings WHERE id=?", [$pc2['id']]));
$rv = act('fin', 'project-costing', 'GET', ['id' => $pc['id']]);
step('P13e', 'FINANCE opens a costing (cost heads, margin)', 'matrix silent', $rv['say']);

// P14 — branch scope on quotes, inquiries
act('bdmA', 'inquiry-new', 'POST', [], ['client_id' => (string)$C, 'contact_name' => 'Ravi', 'contact_mobile' => '99', 'sbu' => 'IND', 'subject' => 'Inquiry raised in office A']);
$inq = q1("SELECT * FROM crm_inquiries ORDER BY id DESC LIMIT 1");
$rl = act('bmB', 'inquiries', 'GET');
$rq = act('bmB', 'quotes', 'GET', ['fy' => 'all']);
$re = act('bmB', 'inquiry-edit', 'POST', ['id' => $inq['id']], ['client_id' => (string)$C, 'contact_name' => 'Changed by office B', 'contact_mobile' => '99', 'sbu' => 'IND', 'subject' => 'edited by B']);
step('P14', 'BRANCH_MANAGER of office B: inquiry register, quote register, and editing office A\'s inquiry', 'office B sees/edits only office B sales records',
     'inquiries view=' . $rl['say'] . '; quote register: ' . $rq['say'] . '; inquiry-edit POST: ' . $re['say'] . '; contact now "' . qv("SELECT contact_name FROM crm_inquiries WHERE id=?", [$inq['id']]) . '" (crm_inquiries has no office column)');

// P15 — blank inquiry
$r = act('bdmA', 'inquiry-new', 'POST', [], []);
step('P15', 'BDM submits /inquiry-new empty', 'refused with a message naming the missing fields; typed values kept', ($r['form_error'] ?? $r['say']));

// P16 — blank opportunity / lead
$r = act('master', 'opportunity-new', 'POST', [], []);
step('P16a', 'Master submits /opportunity-new empty', 'refused with message, form re-shown', $r['say']);
$r = act('master', 'lead-new', 'POST', [], []);
step('P16b', 'Master submits /lead-new empty', 'refused with message, typed values stashed', $r['say']);
$r = act('master', 'project-costing-new', 'POST', [], []);
step('P16c', 'Master submits project-costing-new empty (title is HTML-required)', 'refused', $r['say'] . '; title stored="' . qv("SELECT title FROM project_costings ORDER BY id DESC LIMIT 1") . '"');
$r = act('bdmA', 'quote-external', 'POST', [], []);
step('P16d', 'BDM submits /quote-external empty', 'refused (origin and total are HTML-required)', $r['say'] . '; last quote: ' . json_encode(q1("SELECT quote_no,status,origin,total_amount,client_name FROM quotations ORDER BY id DESC LIMIT 1")));
$r = act('bdmA', 'quote-external', 'POST', [], ['origin' => 'CLIENT_PORTAL', 'status' => 'ACCEPTED', 'client_id' => (string)$C, 'total_amount' => '900000', 'subject' => 'portal win']);
$x = q1("SELECT * FROM quotations ORDER BY id DESC LIMIT 1");
step('P16e', 'BDM registers an external quote directly as ACCEPTED, 9,00,000 (above every approval band)', 'approval chain still applies, or acceptance runs the same client/deal hand-off as /quote-status',
     $r['say'] . "; stored status={$x['status']} total={$x['total_amount']} subtotal={$x['subtotal']} gst_pct={$x['gst_pct']} approvals=" . qv("SELECT COUNT(*) FROM quote_approvals WHERE quote_id=?", [$x['id']]));
$r = act('bdmA', 'quote-external', 'POST', [], ['origin' => 'CLIENT_PORTAL', 'status' => 'WHATEVER', 'client_id' => (string)$C, 'total_amount' => '1000']);
step('P16f', 'BDM posts quote-external with status=WHATEVER (not a quote status)', 'refused', $r['say'] . '; stored status=' . qv("SELECT status FROM quotations ORDER BY id DESC LIMIT 1"));

// P17 — opportunity owner set to a deactivated user
$r = act('master', 'opportunity-edit', 'POST', [], ['id' => (string)$O['id'], 'owner_user_id' => (string)U('gone')]);
step('P17', 'Allocate an opportunity to a deactivated user', 'refused', $r['say']);

// P18 — Finance and Branch Manager, can they revise a quote? (create/edit gate)
$r = act('fin', 'quote-revise', 'POST', ['id' => $QB['id']], []);
step('P18', 'FINANCE posts quote-revise on an ACCEPTED quote', 'refused', $r['say']);

file_put_contents(__DIR__ . '/flow_perm.out', implode('', $GLOBALS['LOG']));
