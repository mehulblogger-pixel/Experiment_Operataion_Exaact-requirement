<?php
// Extras: quote-from-lead acceptance, blocked client, accept twice.
require __DIR__ . '/plib.php';
function mklead($name) { R('ADMIN', 'lead-new', 'POST', [], ['company_name' => $name, 'contact_name' => 'A', 'requirement' => 'x', 'office_id' => (string)$GLOBALS['OFF']]); return lastid('leads'); }
function L($id) { return json_encode(ops_one("SELECT id,status,partner_id,converted_partner_id FROM leads WHERE id=?", [$id])); }
H('1. quotation raised straight off an OPEN lead is accepted');
$l = mklead('Quote Off Lead Ltd');
[$q] = new_quote('MM', ['lead_id' => (string)$l, 'client_id' => '', 'client_name' => 'Quote Off Lead Ltd']); setq($q, ['status' => 'SENT', 'sent_at' => date('c')]);
show('accept', R('MM', 'quote-status', 'POST', ['id' => $q], ['to' => 'ACCEPTED'])); echo "    lead: " . L($l) . "  quote client_id=" . q($q)['client_id'] . "\n";
H('2. quotation off a LOST lead is accepted');
$l2 = mklead('Lost Lead Quoted Ltd');
$lost = (int)ops_val("SELECT s.id FROM pipeline_stages s JOIN leads l ON l.pipeline_id=s.pipeline_id WHERE l.id=? AND s.kind='LOST'", [$l2]);
R('ADMIN', 'lead-move', 'POST', [], ['id' => $l2, 'stage_id' => $lost, 'lost_reason' => 'PRICE']);
[$q2] = new_quote('MM', ['lead_id' => (string)$l2, 'client_id' => '', 'client_name' => 'Lost Lead Quoted Ltd']); setq($q2, ['status' => 'SENT', 'sent_at' => date('c')]);
show('accept', R('MM', 'quote-status', 'POST', ['id' => $q2], ['to' => 'ACCEPTED'])); echo "    lead: " . L($l2) . "  (won customer, lead still LOST)\n";
H('3. accept the same quotation twice');
$p0 = cnt("SELECT COUNT(*) FROM partner_contacts");
show('accept again', R('MM', 'quote-status', 'POST', ['id' => $q], ['to' => 'ACCEPTED'])); echo "    partner_contacts +" . (cnt("SELECT COUNT(*) FROM partner_contacts") - $p0) . " accepted_date=" . ops_val("SELECT accepted_date FROM quotations WHERE id=?", [$q]) . "\n";
H('4. client on BLOCK hold: create is stopped, but accept/send of an existing quote?');
[$q3] = new_quote('MM'); setq($q3, ['status' => 'APPROVED']);
if (function_exists('partner_hold_set')) { echo "    partner_hold_set exists\n"; }
$cols = t_columns('business_partners'); echo "    hold columns on partner: " . json_encode(array_values(array_filter($cols, fn($c) => stripos($c, 'hold') !== false || stripos($c, 'block') !== false))) . "\n";
partner_hold_set($GLOBALS['CLIENT'], 'BLOCKED', 'Unpaid invoices > 180 days');
[$q4, $r] = new_quote('MM'); show('MM quote-new for BLOCKED client', $r); echo "    created: " . ($q4 ? 'yes' : 'no') . "\n";
show('MM sends the already-APPROVED quote of the BLOCKED client', R('MM', 'quote-status', 'POST', ['id' => $q3], ['to' => 'SENT'])); echo "    " . qline($q3) . "\n";
show('MM marks it ACCEPTED', R('MM', 'quote-status', 'POST', ['id' => $q3], ['to' => 'ACCEPTED'])); echo "    " . qline($q3) . "\n";
show('FIN registers contract for the BLOCKED client', R('FIN', 'quote-contract', 'POST', ['id' => $q3], ['auto_contract' => '1'])); echo "    " . qline($q3) . "\n";
