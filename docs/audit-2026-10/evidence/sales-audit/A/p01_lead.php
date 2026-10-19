<?php
// LEAD lifecycle: create, convert (twice, concurrently, after LOST), terminal
// edits, delete with a quote hanging off it, who may convert, inquiry numbering.
require __DIR__ . '/plib.php';
function mklead($who, $name) {
    $b = lastid('leads');
    $r = R($who, 'lead-new', 'POST', [], ['company_name' => $name, 'contact_name' => 'Asha', 'contact_email' => 'a@probe.invalid',
        'requirement' => 'Third-party inspection of pressure vessels', 'value' => '250000', 'sbu' => 'INSP', 'office_id' => (string)$GLOBALS['OFF']]);
    $id = lastid('leads'); return [$id > $b ? $id : 0, $r];
}
function L($id) { $l = ops_one("SELECT id,ref,status,stage_id,partner_id,converted_partner_id,converted_inquiry_id,company_name,value FROM leads WHERE id=?", [$id]); return $l ? json_encode($l) : "(gone)"; }

H('1. who can create / convert a lead (module gate = mod.leads.*)');
foreach (['ADMIN','MM','BDM','MEX','FIN','BM','BD','COORD'] as $who) {
    [$id, $r] = mklead($who, "Who-$who Pvt Ltd"); show("$who lead-new", $r); echo "      created: " . ($id ? "yes #$id" : 'NO') . "\n";
}
[$lid] = mklead('ADMIN', 'Convert Twice Industries');
foreach (['MM','BDM','FIN','BM','BD'] as $who) {
    $r = R($who, 'lead-convert', 'POST', ['id' => $lid], ['id' => $lid]); show("$who lead-convert", $r);
    if (ops_val("SELECT status FROM leads WHERE id=?", [$lid]) === 'CONVERTED') { echo "      -> converted by $who\n"; break; }
}

H('2. convert the same lead twice (sequential)');
[$lid] = mklead('ADMIN', 'Sequential Twice Ltd');
$p0 = cnt("SELECT COUNT(*) FROM business_partners"); $i0 = cnt("SELECT COUNT(*) FROM crm_inquiries");
$r1 = R('ADMIN', 'lead-convert', 'POST', ['id' => $lid], ['id' => $lid]); show('convert #1', $r1);
$r2 = R('ADMIN', 'lead-convert', 'POST', ['id' => $lid], ['id' => $lid]); show('convert #2', $r2);
printf("    partners +%d, inquiries +%d ; lead=%s\n", cnt("SELECT COUNT(*) FROM business_partners") - $p0, cnt("SELECT COUNT(*) FROM crm_inquiries") - $i0, L($lid));

H('3. convert the same lead twice CONCURRENTLY (two workers, one barrier) x3 attempts');
for ($k = 1; $k <= 3; $k++) {
    [$lid] = mklead('ADMIN', "Race$k Engineering Ltd");
    $p0 = cnt("SELECT COUNT(*) FROM business_partners"); $i0 = cnt("SELECT COUNT(*) FROM crm_inquiries");
    $rs = RACE('ADMIN', 'lead-convert', 'POST', ['id' => $lid], ['id' => $lid, 'company_name' => "Race$k Engineering Ltd"], 2);
    foreach ($rs as $j => $r) show("race$k worker$j", $r);
    printf("    attempt %d: partners +%d, inquiries +%d (named 'Race%d': %d partners)\n", $k,
        cnt("SELECT COUNT(*) FROM business_partners") - $p0, cnt("SELECT COUNT(*) FROM crm_inquiries") - $i0, $k,
        cnt("SELECT COUNT(*) FROM business_partners WHERE legal_name=?", ["Race$k Engineering Ltd"]));
}

H('4. a LOST lead: move it, edit it, convert it');
[$lid] = mklead('ADMIN', 'Lost Then Won Ltd');
$lost = (int)ops_val("SELECT s.id FROM pipeline_stages s JOIN leads l ON l.pipeline_id=s.pipeline_id WHERE l.id=? AND s.kind='LOST'", [$lid]);
$open2 = (int)ops_val("SELECT s.id FROM pipeline_stages s JOIN leads l ON l.pipeline_id=s.pipeline_id WHERE l.id=? AND s.kind='OPEN' ORDER BY s.seq DESC LIMIT 1", [$lid]);
show('lead-move -> LOST (no reason)', R('ADMIN', 'lead-move', 'POST', [], ['id' => $lid, 'stage_id' => $lost]));
show('lead-move -> LOST (reason)', R('ADMIN', 'lead-move', 'POST', [], ['id' => $lid, 'stage_id' => $lost, 'lost_reason' => 'PRICE']));
echo "    after lost: " . L($lid) . "\n";
show('lead-move LOST -> open stage (reopen?)', R('ADMIN', 'lead-move', 'POST', [], ['id' => $lid, 'stage_id' => $open2]));
show('lead-edit on LOST lead (value 999)', R('ADMIN', 'lead-edit', 'POST', [], ['id' => $lid, 'company_name' => 'Lost Then Won Ltd', 'value' => '999']));
echo "    after edit: " . L($lid) . "\n";
show('lead-convert on LOST lead', R('ADMIN', 'lead-convert', 'POST', ['id' => $lid], ['id' => $lid]));
echo "    after convert: " . L($lid) . "\n";

H('5. a CONVERTED lead: edit, delete, opportunity from it');
$cl = (int)ops_val("SELECT id FROM leads WHERE company_name='Sequential Twice Ltd'");
show('lead-edit on CONVERTED (company renamed, value 1)', R('ADMIN', 'lead-edit', 'POST', [], ['id' => $cl, 'company_name' => 'Renamed After Conversion', 'value' => '1']));
echo "    after edit: " . L($cl) . "\n";
show('lead-delete on CONVERTED', R('ADMIN', 'lead-delete', 'POST', [], ['id' => $cl]));
$o0 = lastid('opportunities');
show('opportunity-from-lead on CONVERTED lead', R('ADMIN', 'opportunity-from-lead', 'POST', [], ['lead_id' => $cl]));
echo "    opportunity created: " . (lastid('opportunities') > $o0 ? 'yes' : 'no') . "\n";
$ll = (int)ops_val("SELECT id FROM leads WHERE company_name='Lost Then Won Ltd'");

H('6. delete an OPEN lead that has a quotation raised off it');
[$lid] = mklead('ADMIN', 'Quoted Then Deleted Ltd');
[$qid] = new_quote('MM', ['lead_id' => (string)$lid, 'client_id' => '', 'client_name' => 'Quoted Then Deleted Ltd']);
echo "    quote " . qline($qid) . " lead_id=" . q($qid)['lead_id'] . "\n";
show('lead-delete (lead has a quote)', R('ADMIN', 'lead-delete', 'POST', [], ['id' => $lid]));
printf("    lead row exists: %s ; quote.lead_id still=%s (dangling)\n", ops_val("SELECT COUNT(*) FROM leads WHERE id=?", [$lid]) ? 'yes' : 'NO', q($qid)['lead_id']);

H('7. inquiry numbering: lead conversion vs the inquiry form');
foreach (ops_all("SELECT id, inquiry_no, lead_id, created_by FROM crm_inquiries ORDER BY id") as $r) echo "    inq #{$r['id']} {$r['inquiry_no']} lead={$r['lead_id']}\n";
$r = R('MM', 'inquiry-new', 'POST', [], ['client_id' => (string)$GLOBALS['CLIENT'], 'contact_name' => 'X', 'contact_email' => 'x@probe.invalid', 'sbu' => 'INSP', 'subject' => 'Form inquiry']);
show('inquiry-new', $r); $iq = ops_one("SELECT id, inquiry_no FROM crm_inquiries ORDER BY id DESC LIMIT 1"); echo "    form inquiry -> {$iq['inquiry_no']}\n";
// delete one converted inquiry, then convert another lead: COUNT(*)+1 numbering re-issues a live number
$victim = (int)ops_val("SELECT id FROM crm_inquiries WHERE inquiry_no LIKE 'INQ-____-%' ORDER BY id LIMIT 1");
show('inquiry-delete (an early converted one, no quote)', R('ADMIN', 'inquiry-delete', 'POST', [], ['id' => $victim]));
[$lid] = mklead('ADMIN', 'Numbering Collision Ltd');
show('lead-convert', R('ADMIN', 'lead-convert', 'POST', ['id' => $lid], ['id' => $lid]));
foreach (ops_all("SELECT inquiry_no, COUNT(*) n FROM crm_inquiries GROUP BY inquiry_no HAVING COUNT(*)>1") as $d) echo "    DUPLICATE inquiry_no {$d['inquiry_no']} x{$d['n']}\n";
echo "    unique index on crm_inquiries.inquiry_no: " . (count(array_filter(t_indexes('%'), fn($n) => stripos($n, 'inquir') !== false)) ? implode(',', array_filter(t_indexes('%'), fn($n) => stripos($n, 'inquir') !== false)) : 'none') . "\n";

H('8. history written for lead events');
foreach (ops_all("SELECT entity_kind, entity_id, kind, substr(subject,1,80) s FROM activities WHERE entity_kind='LEAD' ORDER BY id DESC LIMIT 12") ?: [] as $a) echo "    act {$a['entity_id']} {$a['kind']} {$a['s']}\n";
