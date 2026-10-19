<?php
// P3 — contract -> work order (call) -> job -> billable event -> invoice.
// What is carried, what is re-typed, and where figures can diverge.
putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
$I = json_decode(file_get_contents(__DIR__ . '/ids.json'), true); $P = json_decode(file_get_contents(__DIR__ . '/p2_ids.json'), true);
$qa = (int)$P['qa']; $cid = (int)$P['cid']; $cno = $P['cno']; $co = $I['coordinator'];
$lines = crm_quote_lines($qa); $mdLine = null; foreach ($lines as $l) if ($l['unit'] === 'MANDAY') $mdLine = $l;
echo "quote #$qa lines: " . json_encode(array_map(fn($l) => [$l['id'], $l['unit'], (float)$l['qty'], (float)$l['rate'], (float)$l['amount']], $lines)) . "\n";
echo "contract #$cid: " . json_encode(row("SELECT contract_number, value, end_date, open_status FROM partner_contracts WHERE id=?", [$cid])) . "\n";

echo "\n[3a] Coordinator opens call-new?contract_id=$cid — what does the form open with?\n";
$o = req($co, 'call-new', 'GET', ['contract_id' => $cid]); echo "  prefilled: " . json_encode($o['vars']['call'] ?? null) . "\n";
echo "  quote-context endpoint offers line rates to the browser JS: ";
$o2 = req($co, 'quote-context', 'GET', ['id' => $qa]); echo substr($o2['body'] ?? json_encode($o2), 0, 0) . "(see raw)\n";

echo "\n[3b] Save the work order with a TYPED rate 9500 (quote line says 8000) and qty 3\n";
$post = ['client_id' => $I['p_active'], 'quotation_id' => $qa, 'quote_line_id' => $mdLine['id'], 'contract_number' => $cno, 'sbu' => 'TPI',
         'ibo_office_id' => $I['office'], 'inspection_type' => 'TPI', 'engagement_type' => 'SINGLE', 'inspection_required_date' => '2026-11-02',
         'billable_rate' => 9500, 'billable_basis' => 'MANDAY', 'billable_qty' => 3, 'billable_qty_auto' => '0', 'notes' => 'probe WO'];
$before = (int)val("SELECT COALESCE(MAX(id),0) FROM calls");
$o = req($co, 'call-new', 'POST', ['contract_id' => $cid], $post); show('call-new POST to /call-new?contract_id= (NOT what the form does)', $o);
$o = req($co, 'call-new', 'POST', [], $post); show('call-new POST to /call-new (the form action, views/ops/call_form.php:67)', $o);
$call = (int)val("SELECT COALESCE(MAX(id),0) FROM calls"); $call = $call > $before ? $call : 0;
echo "  call: " . json_encode(row("SELECT id, call_code, status, client_id, quotation_id, quote_line_id, contract_number, billable_rate, billable_qty, billable_value, billable_basis, po_id FROM calls WHERE id=?", [$call])) . "\n";

echo "\n[3c] A work order typed against a contract number that does not exist (no quote, no contract)\n";
$before = (int)val("SELECT COALESCE(MAX(id),0) FROM calls");
$o = req($co, 'call-new', 'POST', [], ['client_id' => $I['p_active'], 'contract_number' => 'TYPO-NOT-A-CONTRACT', 'sbu' => 'TPI', 'ibo_office_id' => $I['office'],
     'engagement_type' => 'SINGLE', 'inspection_required_date' => '2026-11-03', 'billable_rate' => 12000, 'billable_qty' => 1, 'billable_qty_auto' => '0']); show('call-new direct/typo', $o);
$c3 = (int)val("SELECT COALESCE(MAX(id),0) FROM calls"); echo "  created: " . ($c3 > $before ? json_encode(row("SELECT call_code, contract_number, quotation_id, billable_value FROM calls WHERE id=?", [$c3])) : 'no') . "\n";

echo "\n[3d] A work order against the DRAFT quote #{$P['qd']} (never approved/sent/accepted), contract box left blank\n";
$before = (int)val("SELECT COALESCE(MAX(id),0) FROM calls");
$o = req($co, 'call-new', 'POST', [], ['client_id' => $I['p_active'], 'quotation_id' => $P['qd'], 'contract_number' => '', 'sbu' => 'TPI', 'ibo_office_id' => $I['office'],
     'engagement_type' => 'SINGLE', 'inspection_required_date' => '2026-11-04', 'billable_rate' => 8000, 'billable_qty' => 1, 'billable_qty_auto' => '0']); show('call-new on DRAFT quote', $o);
$c4 = (int)val("SELECT COALESCE(MAX(id),0) FROM calls"); echo "  created: " . ($c4 > $before ? json_encode(row("SELECT call_code, contract_number, quotation_id, status FROM calls WHERE id=?", [$c4])) : 'no') . "\n";
echo "  the DRAFT quote's own contract number (registered in P2, PENDING): " . val("SELECT contract_number FROM quotations WHERE id=?", [$P['qd']]) . "\n";

echo "\n[3e] Allocate a job on the 3b work order (coordinator)\n";
$insp = (int)val("SELECT id FROM inspectors WHERE COALESCE(status,'ACTIVE')='ACTIVE' ORDER BY id LIMIT 1");
$o = req($co, 'job-new', 'POST', ['call' => $call], ['inspector_id' => $insp, 'executing_office_id' => $I['office'], 'scheduled_date' => '2026-11-02',
     'engagement_type' => 'SINGLE', 'quotation_id' => $qa, 'impartiality_ok' => 1, 'mandays' => 3, 'cert_override_note' => 'probe', 'sitedoc_override_note' => 'probe', 'dblbook_override_note' => 'probe']);
show('job-new POST', $o);
$job = (int)val("SELECT COALESCE(MAX(id),0) FROM jobs WHERE call_id=?", [$call]);
echo "  job: " . json_encode(row("SELECT id, job_code, call_id, quotation_id, contract_number, invoice_value, mandays, closed_flag FROM jobs WHERE id=?", [$job])) . "\n";
file_put_contents(__DIR__ . '/p3_ids.json', json_encode(['call' => $call, 'job' => $job, 'c3' => $c3, 'c4' => $c4, 'insp' => $insp]));
