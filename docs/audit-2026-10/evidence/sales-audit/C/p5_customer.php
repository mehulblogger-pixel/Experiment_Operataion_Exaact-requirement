<?php
// P5 — customer data: free-text clients at acceptance, duplicates, and whether a
// BLACKLISTED / INACTIVE (status) or BLOCKED / HOLD (hold_status) client stops
// an inquiry, a quote, its approval, acceptance, contract registration, a work order.
putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
$I = json_decode(file_get_contents(__DIR__ . '/ids.json'), true);
$L = [['TPI visit', 'TPI', 2, 'MANDAY', 7000]];
$bdm = $I['business_dev_manager']; $fin = $I['finance']; $co = $I['coordinator']; $mm = $I['marketing_manager'];
$pc = fn() => (int)val("SELECT COUNT(*) FROM business_partners WHERE is_client=1");

echo "[5a] Free-text client name that is the SAME company written differently\n";
echo "  on file: " . json_encode(row("SELECT id, code, legal_name, gstin FROM business_partners WHERE id=?", [$I['p_active']])) . "\n";
// a loose inquiry typed by someone else, same company, no client picked
req($bdm, 'inquiry-new', 'POST', [], ['client_name' => 'Acme Valves Ltd.', 'contact_name' => 'R. Shah', 'contact_email' => 'rshah@acme.invalid', 'sbu' => 'TPI', 'subject' => 'Valve TPI enquiry']);
$inq = (int)val("SELECT MAX(id) FROM crm_inquiries");
echo "  loose inquiry #$inq client_id=" . var_export(val("SELECT client_id FROM crm_inquiries WHERE id=?", [$inq]), true) . " (typed 'Acme Valves Ltd.', no warning shown)\n";
$n0 = $pc();
[$qf] = mkquote($bdm, ['client_name' => 'ACME VALVES PRIVATE LIMITED', 'contact_name' => 'R. Shah', 'contact_email' => 'rshah@acme.invalid', 'office_id' => $I['office']], $L);
req($bdm, 'quote-status', 'POST', ['id' => $qf], ['to' => 'SENT']);
$o = req($fin, 'quote-status', 'POST', ['id' => $qf], ['to' => 'ACCEPTED']); show('accept free-text quote', $o);
$newId = (int)val("SELECT client_id FROM quotations WHERE id=?", [$qf]);
echo "  clients before $n0 after " . $pc() . "; quote now points at #$newId " . json_encode(row("SELECT code, legal_name, gstin, status FROM business_partners WHERE id=?", [$newId])) . "\n";
echo "  audit note on the new record: " . json_encode(array_column(rows("SELECT subject FROM activities WHERE entity_kind='PARTNER' AND entity_id=? ORDER BY id", [$newId]), 'subject')) . "\n";
echo "  the loose inquiry #$inq is now linked to client_id=" . var_export(val("SELECT client_id FROM crm_inquiries WHERE id=?", [$inq]), true) . "\n";
echo "  find_duplicate_partner now reports: " . json_encode(($h = find_duplicate_partner('Acme Valves Pvt Ltd', '', '', '', (int)$I['p_active'])) ? [$h['by'], $h['confidence'], $h['row']['id']] : null) . "\n";

echo "\n[5b] Free-text name with a trading-style difference (no normalised match at all)\n";
$n0 = $pc();
[$qg] = mkquote($bdm, ['client_name' => 'Acme Valves (Vadodara Works)', 'office_id' => $I['office']], $L);
req($bdm, 'quote-status', 'POST', ['id' => $qg], ['to' => 'SENT']);
req($fin, 'quote-status', 'POST', ['id' => $qg], ['to' => 'ACCEPTED']);
echo "  clients before $n0 after " . $pc() . "; quote client " . json_encode(row("SELECT bp.id, bp.legal_name, bp.gstin FROM quotations q JOIN business_partners bp ON bp.id=q.client_id WHERE q.id=?", [$qg])) . "\n";
echo "  quotations has a gstin column for the free-text path? " . (in_array('gstin', t_cols('quotations')) ? 'yes' : 'no') . "\n";

echo "\n[5c] Same free-text name accepted twice (two different quotes)\n";
$n0 = $pc();
foreach ([1, 2] as $k) { [$qq] = mkquote($bdm, ['client_name' => 'Zenith Gears LLP', 'office_id' => $I['office']], $L);
  req($bdm, 'quote-status', 'POST', ['id' => $qq], ['to' => 'SENT']); req($fin, 'quote-status', 'POST', ['id' => $qq], ['to' => 'ACCEPTED']);
  echo "  quote #$qq -> client " . val("SELECT client_id FROM quotations WHERE id=?", [$qq]) . "\n"; }
echo "  clients before $n0 after " . $pc() . "\n";

echo "\n[5d] Status / hold gates — BLACKLISTED, INACTIVE (status column) and BLOCKED, HOLD (hold_status)\n";
foreach (['p_black' => 'BLACKLISTED', 'p_inact' => 'INACTIVE', 'p_blocked' => 'BLOCKED(hold)', 'p_hold' => 'HOLD(hold)'] as $k => $lbl) {
  $pid = $I[$k]; echo "  --- $lbl client #$pid\n";
  $b = (int)val("SELECT COALESCE(MAX(id),0) FROM crm_inquiries");
  $o = req($bdm, 'inquiry-new', 'POST', [], ['client_id' => $pid, 'contact_name' => 'X', 'contact_email' => 'x@x.invalid', 'sbu' => 'TPI', 'subject' => 'enq']);
  echo "    inquiry (BDM): " . ((int)val("SELECT COALESCE(MAX(id),0) FROM crm_inquiries") > $b ? 'CREATED' : 'refused') . "  " . implode(' | ', $o['flash'] ?? []) . "\n";
  [$q, $o] = mkquote($bdm, ['client_id' => $pid, 'office_id' => $I['office']], $L);
  echo "    quote (BDM): " . ($q ? 'CREATED' : 'refused') . "  " . mb_substr(implode(' | ', $o['flash'] ?? []), 0, 160) . "\n";
  if (!$q) { [$q, $o] = mkquote($I['master'], ['client_id' => $pid, 'office_id' => $I['office']], $L); echo "    quote (master): " . ($q ? 'CREATED' : 'refused') . "\n"; }
  $o = req($mm, 'quote-status', 'POST', ['id' => $q], ['to' => 'APPROVED']); echo "    approve (MARKETING_MANAGER): " . qstatus($q) . "\n";
  req($bdm, 'quote-status', 'POST', ['id' => $q], ['to' => 'SENT']);
  $o = req($fin, 'quote-status', 'POST', ['id' => $q], ['to' => 'ACCEPTED']); echo "    accept (FINANCE): " . qstatus($q) . "\n";
  $o = req($fin, 'quote-contract', 'POST', ['id' => $q], ['auto_contract' => 1]); $cn = (string)val("SELECT contract_number FROM quotations WHERE id=?", [$q]);
  echo "    contract register (FINANCE): " . ($cn !== '' ? "REGISTERED $cn" : 'refused') . "\n";
  $b = (int)val("SELECT COALESCE(MAX(id),0) FROM calls");
  $o = req($co, 'call-new', 'POST', [], ['client_id' => $pid, 'quotation_id' => $q, 'sbu' => 'TPI', 'ibo_office_id' => $I['office'], 'engagement_type' => 'SINGLE', 'inspection_required_date' => '2026-11-20', 'billable_rate' => 7000, 'billable_qty' => 1, 'billable_qty_auto' => '0']);
  echo "    work order (COORDINATOR): " . ((int)val("SELECT COALESCE(MAX(id),0) FROM calls") > $b ? 'CREATED' : 'refused') . "  " . mb_substr(implode(' | ', array_merge($o['flash'] ?? [], [$o['view_error'] ?? ''])), 0, 200) . "\n";
  as_user($fin); $inv = books_invoice_create(['partner_id' => $pid, 'office_id' => $I['office']]);
  echo "    draft invoice (FINANCE, books_invoice_create): " . (!empty($inv['id']) ? 'CREATED' : 'refused ' . ($inv['err'] ?? '')) . "\n";
}
as_user($co); echo "  is_coordinator_level() for COORDINATOR = " . var_export(is_coordinator_level(), true) . " -> partner_hold_blocks(BLOCKED) = " . var_export(partner_hold_blocks($I['p_blocked']), true) . "\n";
function t_cols($t) { return array_column(ops_all("PRAGMA table_info($t)"), 'name'); }
