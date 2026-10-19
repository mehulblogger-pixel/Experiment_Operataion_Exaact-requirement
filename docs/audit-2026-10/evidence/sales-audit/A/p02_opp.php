<?php
// OPPORTUNITY lifecycle and its links to quotes/contracts.
require __DIR__ . '/plib.php';
function mkopp($name, $extra = []) {
    $b = lastid('opportunities');
    $r = R('ADMIN', 'opportunity-new', 'POST', [], array_merge(['name' => $name, 'partner_id' => (string)$GLOBALS['CLIENT'], 'value' => '100000', 'office_id' => (string)$GLOBALS['OFF'], 'sbu' => 'INSP'], $extra));
    $id = lastid('opportunities'); return [$id > $b ? $id : 0, $r];
}
function O($id) { $o = ops_one("SELECT id,ref,status,stage_id,value,partner_id,contract_id,call_id FROM opportunities WHERE id=?", [$id]); return json_encode($o); }
function stage($oid, $kind, $nameLike = null) {
    return (int)ops_val("SELECT s.id FROM pipeline_stages s JOIN opportunities o ON o.pipeline_id=s.pipeline_id WHERE o.id=? AND s.kind=?" . ($nameLike ? " AND s.name LIKE '$nameLike'" : '') . " ORDER BY s.seq LIMIT 1", [$oid, $kind]);
}
function sentq($who = 'MM') { [$id] = new_quote($who); setq($id, ['status' => 'SENT', 'sent_at' => date('c')]); return $id; }

H('1. who can open / create an opportunity');
foreach (['MM','BDM','FIN','BM','BD'] as $who) { $r = R($who, 'opportunities'); printf("    %-4s /opportunities http=%s flash=%s\n", $who, $r['code'], implode('|', $r['flash'])); }

H('2. WON: does it raise an enquiry or quotation by itself?');
[$o1] = mkopp('Deal won directly');
$i0 = cnt("SELECT COUNT(*) FROM crm_inquiries"); $q0 = cnt("SELECT COUNT(*) FROM quotations");
show('opportunity-move -> WON', R('ADMIN', 'opportunity-move', 'POST', [], ['id' => $o1, 'stage_id' => stage($o1, 'WON')]));
printf("    %s ; inquiries +%d, quotations +%d\n", O($o1), cnt("SELECT COUNT(*) FROM crm_inquiries") - $i0, cnt("SELECT COUNT(*) FROM quotations") - $q0);
show('opportunity-move WON -> OPEN stage (reopen?)', R('ADMIN', 'opportunity-move', 'POST', [], ['id' => $o1, 'stage_id' => stage($o1, 'OPEN')]));
show('opportunity-move WON -> LOST', R('ADMIN', 'opportunity-move', 'POST', [], ['id' => $o1, 'stage_id' => stage($o1, 'LOST'), 'lost_reason' => 'PRICE']));
show('opportunity-edit on WON deal (value 1, renamed)', R('ADMIN', 'opportunity-edit', 'POST', [], ['id' => $o1, 'name' => 'Renamed after win', 'value' => '1']));
echo "    after edit: " . O($o1) . "\n";
echo "    history rows for the edit (activities): " . cnt("SELECT COUNT(*) FROM activities WHERE entity_kind='OPPORTUNITY' AND entity_id=? AND subject LIKE '%value%'", [$o1]) . " ; all activity subjects: " . json_encode(array_column(ops_all("SELECT subject FROM activities WHERE entity_kind='OPPORTUNITY' AND entity_id=?", [$o1]), 'subject')) . "\n";

H('3. two alternative quotations on one deal: one is LOST, the other is later ACCEPTED');
[$o2] = mkopp('Deal with two alternative offers');
$qa = sentq(); $qb = sentq();
show('link quote A', R('ADMIN', 'opportunity-quote', 'POST', [], ['id' => $o2, 'quotation_id' => $qa]));
show('link quote B', R('ADMIN', 'opportunity-quote', 'POST', [], ['id' => $o2, 'quotation_id' => $qb]));
show('MM marks A LOST (client chose option B)', R('MM', 'quote-status', 'POST', ['id' => $qa], ['to' => 'LOST', 'lost_reason' => 'PRICE']));
echo "    deal after A lost: " . O($o2) . "\n";
show('MM marks B ACCEPTED', R('MM', 'quote-status', 'POST', ['id' => $qb], ['to' => 'ACCEPTED']));
echo "    deal after B accepted: " . O($o2) . "   quote B: " . qline($qb) . "\n";

H('4. deal already LOST by hand, then its quotation is ACCEPTED');
[$o3] = mkopp('Deal lost then quote accepted');
$qc = sentq();
R('ADMIN', 'opportunity-quote', 'POST', [], ['id' => $o3, 'quotation_id' => $qc]);
show('deal -> LOST', R('ADMIN', 'opportunity-move', 'POST', [], ['id' => $o3, 'stage_id' => stage($o3, 'LOST'), 'lost_reason' => 'NO_RESPONSE']));
show('quote ACCEPTED', R('MM', 'quote-status', 'POST', ['id' => $qc], ['to' => 'ACCEPTED']));
echo "    deal: " . O($o3) . "   quote: " . qline($qc) . "\n";

H('5. one won deal, two contracts: quote-contract AND opportunity-contract');
[$o4] = mkopp('Deal for double contract');
$qd = sentq();
R('ADMIN', 'opportunity-quote', 'POST', [], ['id' => $o4, 'quotation_id' => $qd]);
show('quote ACCEPTED (wins the deal)', R('MM', 'quote-status', 'POST', ['id' => $qd], ['to' => 'ACCEPTED']));
echo "    deal: " . O($o4) . "\n";
show('FIN quote-contract (auto number)', R('FIN', 'quote-contract', 'POST', ['id' => $qd], ['auto_contract' => '1']));
echo "    quote: " . qline($qd) . "\n";
show('FIN opportunity-contract on the same won deal (auto number)', R('FIN', 'opportunity-contract', 'POST', [], ['id' => $o4, 'auto_contract' => '1']));
echo "    deal: " . O($o4) . "\n";
foreach (ops_all("SELECT id, contract_number, partner_id, quotation_id, open_status, notes FROM partner_contracts ORDER BY id") as $c) echo "    contract #{$c['id']} {$c['contract_number']} q={$c['quotation_id']} {$c['open_status']} — {$c['notes']}\n";

H('6. attach a quotation of a DIFFERENT client to a deal, accept it');
db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('Other Client Ltd','Other Client',1,'ACTIVE')")->execute();
$other = lastid('business_partners');
[$o5] = mkopp('Deal for Acme');
[$qe] = new_quote('MM', ['client_id' => (string)$other]); setq($qe, ['status' => 'SENT', 'sent_at' => date('c')]);
show('link Other-Client quote to Acme deal', R('ADMIN', 'opportunity-quote', 'POST', [], ['id' => $o5, 'quotation_id' => $qe]));
show('accept it', R('MM', 'quote-status', 'POST', ['id' => $qe], ['to' => 'ACCEPTED']));
echo "    deal (partner should be Acme={$GLOBALS['CLIENT']}): " . O($o5) . "  quote client_id=" . q($qe)['client_id'] . "\n";

H('7. direct RFQ: quotation with no lead, no deal, no inquiry');
[$qf] = new_quote('MM'); $x = q($qf);
printf("    %s lead_id=%s inquiry_id=%s linked deals=%d\n", qline($qf), var_export($x['lead_id'], true), var_export($x['inquiry_id'], true), cnt("SELECT COUNT(*) FROM opportunity_quotes WHERE quotation_id=?", [$qf]));

H('5b. same as 5, the direct-win contract registered by ADMIN (Finance is refused the route: module gate leads)');
show('ADMIN opportunity-contract on the won deal that already has a quote contract', R('ADMIN', 'opportunity-contract', 'POST', [], ['id' => $o4, 'auto_contract' => '1']));
echo "    deal: " . O($o4) . "\n";
foreach (ops_all("SELECT id, contract_number, partner_id, quotation_id, open_status, notes FROM partner_contracts ORDER BY id") as $c) echo "    contract #{$c['id']} {$c['contract_number']} q={$c['quotation_id']} {$c['open_status']} — {$c['notes']}\n";
show('FIN /opportunity?id (can Finance even see the deal queue?)', R('FIN', 'opportunity', 'GET', ['id' => $o4]));
