<?php
// Hand-off probes: DRAFT straight to ACCEPTED, and the value carried to the work order.
require __DIR__ . '/flow_lib.php';
$C = $GLOBALS['IDS']['C']; $A = $GLOBALS['IDS']['A'];
act('bdmA', 'quote-new', 'POST', [], quote_post(['subject' => 'QX draft to accepted'])); $QX = lastq();
$r = act('bdmA', 'quote-status', 'POST', ['id' => $QX['id']], ['to' => 'ACCEPTED']);
step('H1', "BDM posts quote-status to=ACCEPTED on DRAFT {$QX['quote_no']} (never approved or sent; no approval rules configured in this run)", 'refused — only a SENT (or EXPIRED) quote is accepted (03-object-lifecycles.md)',
     $r['say'] . '; status=' . qv("SELECT status FROM quotations WHERE id=?", [$QX['id']]) . ' approvals=' . qv("SELECT COUNT(*) FROM quote_approvals WHERE quote_id=?", [$QX['id']]));
// deal estimate 250000; the attached accepted quote is 100000 ex-GST / 118000 incl.
act('master', 'opportunity-new', 'POST', [], ['name' => 'Order value deal', 'partner_id' => (string)$C, 'value' => '250000', 'office_id' => (string)$A]);
$O = q1("SELECT * FROM opportunities ORDER BY id DESC LIMIT 1");
act('master', 'opportunity-quote', 'POST', [], ['id' => (string)$O['id'], 'quotation_id' => (string)$QX['id']]);
$won = qv("SELECT id FROM pipeline_stages WHERE pipeline_id=? AND kind='WON'", [$O['pipeline_id']]);
act('master', 'opportunity-move', 'POST', [], ['id' => (string)$O['id'], 'stage_id' => (string)$won]);
$r = act('master', 'opportunity-raise-order', 'POST', [], ['id' => (string)$O['id']]);
$call = q1("SELECT call_code, quotation_id, billable_value FROM calls ORDER BY id DESC LIMIT 1");
step('H2', "Raise the work order from won deal {$O['ref']} (estimate 250000) carrying accepted {$QX['quote_no']} (subtotal {$QX['subtotal']}, total {$QX['total_amount']})",
     'billable_value = the quote subtotal 100000 (the comment at opportunities.php:359-365 says so)',
     $r['say'] . '; call=' . json_encode($call));
file_put_contents(__DIR__ . '/flow_order.out', implode('', $GLOBALS['LOG']));
