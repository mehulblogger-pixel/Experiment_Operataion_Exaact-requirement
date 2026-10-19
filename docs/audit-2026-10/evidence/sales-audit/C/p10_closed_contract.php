<?php
// P10 — a job allocated on an existing work order AFTER its contract was CLOSED.
putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
$I = json_decode(file_get_contents(__DIR__ . '/ids.json'), true); $P = json_decode(file_get_contents(__DIR__ . '/p2_ids.json'), true); $E = json_decode(file_get_contents(__DIR__ . '/p3e_ids.json'), true);
$cid = (int)$P['cid']; $call = (int)$E['call'];
db()->prepare("UPDATE partner_contracts SET open_status='CLOSED', is_active=0, closed_at=?, close_reason='probe: closed' WHERE id=?")->execute([date('c'), $cid]);
echo "contract #$cid: " . json_encode(row("SELECT contract_number, open_status, is_active FROM partner_contracts WHERE id=?", [$cid])) . "\n";
$o = req($I['coordinator'], 'job-new', 'POST', ['call' => $call], ['inspector_id' => $E['insp'], 'executing_office_id' => $I['office'], 'scheduled_date' => '2026-12-01',
     'impartiality_ok' => 1, 'mandays' => 1, 'cert_override_note' => 'probe override', 'sitedoc_override_note' => 'probe override', 'dblbook_override_note' => 'probe override']);
show('job-new on a work order whose contract is CLOSED', $o);
$o = req($I['coordinator'], 'call-new', 'POST', [], ['client_id' => $I['p_active'], 'contract_number' => $P['cno'], 'sbu' => 'TPI', 'ibo_office_id' => $I['office'], 'engagement_type' => 'SINGLE', 'inspection_required_date' => '2026-12-02', 'billable_rate' => 8000, 'billable_qty' => 1, 'billable_qty_auto' => '0']);
show('call-new typing the CLOSED contract number', $o);
db()->prepare("UPDATE partner_contracts SET open_status='OPEN', is_active=1, closed_at='', close_reason='' WHERE id=?")->execute([$cid]);
