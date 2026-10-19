<?php
// Phase 2: revise the ACCEPTED RQ-2 through the real handlers, re-price and accept the revision.
require __DIR__ . '/flow_lib.php';
$r = act('master', 'quote-revise', 'POST', ['id' => 2], ['summary' => 'Client asked for 20% more scope']);
$rev = q1("SELECT * FROM quotations WHERE quote_no='RQ-2' AND rev=1");
step('R0', 'Revise RQ-2, which is already ACCEPTED', 'refused, or the accepted original is superseded (no longer counted as won)', $r['say'] . '; rev1 id=' . $rev['id'] . ' status=' . $rev['status'] . ' contract carried=' . var_export($rev['contract_number'], true) . '; rev0 now ' . json_encode(q1("SELECT status,is_current FROM quotations WHERE quote_no='RQ-2' AND rev=0")));
$r = act('master', 'quote-edit', 'POST', ['id' => $rev['id']], ['client_id' => (string)$GLOBALS['IDS']['C'], 'sbu' => 'IND', 'office_id' => (string)$GLOBALS['IDS']['A'], 'subject' => 'Recon RQ-2 rev1', 'gst_pct' => '18', 'validity_days' => '30',
     'l_desc' => ['Man-days'], 'l_service' => ['INSPECTION'], 'l_qty' => ['1'], 'l_rate' => ['60000'], 'l_unit' => ['MANDAY']]);
foreach (['APPROVED', 'SENT', 'ACCEPTED'] as $to) { $r = act('master', 'quote-status', 'POST', ['id' => $rev['id']], ['to' => $to]); echo "  -> $to: {$r['say']}\n"; }
$GLOBALS['PDO']->prepare("UPDATE quotations SET created_at='2026-07-15T10:00:00+05:30', accepted_date='2026-07-20' WHERE id=?")->execute([$rev['id']]);
echo json_encode(q("SELECT id,quote_no,rev,is_current,status,subtotal,total_amount,created_at,accepted_date FROM quotations WHERE quote_no='RQ-2'")), "\n";
file_put_contents(__DIR__ . '/recon_revise.out', implode('', $GLOBALS['LOG']));
