<?php
// Owner, 21 Nov 2026 — D1–D4 "as recommended": a customer's GSTIN and PAN are
// required when it is won and matched against the central customer list; ONE
// customer is ONE PAN, billed at one or more places (billing locations); every
// invoice keeps a copy of who it was billed to, so old bills never change.
//
// Example used throughout (made up): TB Refinery Ltd, PAN AAACT1234F — Gujarat
// (main), Maharashtra and Tamil Nadu.
t_section('Customer GSTIN / PAN and billing locations (D1–D4)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$made = ['bp' => [], 'inv' => [], 'q' => [], 'l' => [], 'o' => []];
$G = function ($state, $pan, $ent = '1') {          // a GSTIN with a correct check digit
    $first = $state . $pan . $ent . 'Z';
    return $first . commercial_gstin_check_char($first);
};
$PAN = 'AAACT1234F';
$guj = $G('24', $PAN); $mah = $G('27', $PAN); $tn = $G('33', $PAN);
$pdo = db();
try {
    bbv_as($f['master']); custid_migrate(); books_migrate(); leads_migrate(); opp_migrate();
    // Our own branch is registered in Gujarat, so a Gujarat customer is CGST + SGST.
    $pdo->prepare("UPDATE offices SET gstin=? WHERE id=?")->execute([$G('24', 'AABCM9999K'), $f['mum']]);

    // ---- Typing: check digit, PAN inside the GSTIN, the four kinds ---------------
    $ok = custid_from_input(['gstin' => strtolower($guj)], true);
    t_eq([$ok['err'], $ok['pan'], $ok['state_code'], $ok['state']], ['', $PAN, '24', 'Gujarat'], 'typing · GSTIN fills PAN AAACT1234F and state Gujarat');
    $bad = substr($guj, 0, 14) . ($guj[14] === 'A' ? 'B' : 'A');
    t_ok(strpos(custid_from_input(['gstin' => $bad], true)['err'], 'typing mistake') !== false, 'typing · a wrong last character (check digit) is caught');
    t_ok(strpos(custid_from_input(['gstin' => $guj, 'pan' => 'AAACT9999F'], true)['err'], 'is not the PAN inside') !== false, 'typing · a typed PAN that is not the GSTIN\'s is refused');
    t_ok(custid_from_input([], true)['err'] !== '', 'typing · a client with nothing is refused');
    t_ok(custid_from_input([], false)['err'] === '', 'typing · a vendor-only record may leave them empty');
    $un = custid_from_input(['tax_status' => 'UNREGISTERED', 'pan' => 'AAAPZ1234C', 'tax_reason' => 'below the GST threshold'], true);
    t_eq([$un['err'], $un['tax_status'], $un['pan']], ['', 'UNREGISTERED', 'AAAPZ1234C'], 'D2 · not GST-registered: PAN + reason accepted');
    t_ok(custid_from_input(['tax_status' => 'UNREGISTERED', 'pan' => 'AAAPZ1234C'], true)['err'] !== '', 'D2 · not GST-registered without a reason is refused');
    t_ok(custid_from_input(['tax_status' => 'UNREGISTERED', 'tax_reason' => 'x'], true)['err'] !== '', 'D2 · not GST-registered without a PAN is refused');
    $ov = custid_from_input(['tax_status' => 'OVERSEAS', 'country' => 'United Arab Emirates', 'foreign_tax_id' => 'TRN100200300400003'], true);
    t_eq([$ov['err'], $ov['gstin'], $ov['pan']], ['', '', ''], 'D2 · overseas: country + their tax number, no PAN/GSTIN');
    t_ok(custid_from_input(['tax_status' => 'OVERSEAS', 'country' => 'India', 'foreign_tax_id' => 'X'], true)['err'] !== '', 'D2 · "overseas" in India is refused');
    $gv = custid_from_input(['tax_status' => 'GOVT_NO_PAN', 'tax_reason' => 'State PWD — no PAN issued'], true);
    t_eq([$gv['err'], $gv['tax_status']], ['', 'GOVT_NO_PAN'], 'D2 · government department without PAN: reason accepted');
    t_ok(custid_from_input(['tax_status' => 'GOVT_NO_PAN'], true)['err'] !== '', 'D2 · government department without a reason is refused');

    // ---- The customer on file, and the decision against the central list ---------
    $mk = function ($name, array $id, $extra = []) use (&$made, $pdo) {
        $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
            ->execute(['GB-' . uniqid(), $name, $extra['display_name'] ?? $name, date('c')]);
        $id0 = (int) $pdo->lastInsertId(); $made['bp'][] = $id0;
        custid_save($id0, custid_from_input($id, true));
        foreach ($extra as $k => $v) if ($k !== 'display_name') $pdo->prepare("UPDATE business_partners SET $k=? WHERE id=?")->execute([$v, $id0]);
        return $id0;
    };
    $tb = $mk('TB Refinery Ltd ' . $f['tag'], ['gstin' => $guj]);
    $pdo->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city,state,pincode,is_primary) VALUES (?,'REGISTERED','HO','Plot 7, GIDC','Vadodara','Gujarat','390010',1)")->execute([$tb]);
    t_ok(custid_complete(ops_one("SELECT * FROM business_partners WHERE id=?", [$tb])), 'record · TB Refinery is complete (GSTIN + PAN)');
    $m = custid_match('Anything', $guj, $PAN, 0);
    t_eq([$m['kind'], (int) $m['row']['id']], ['GSTIN', $tb], 'match · the same GSTIN IS that customer (blocked)');
    $m = custid_match('TB Refinery Mumbai', $mah, $PAN, 0);
    t_eq([$m['kind'], (int) $m['row']['id']], ['PAN', $tb], 'match · the same PAN with a new GSTIN is a billing location of it, not a new customer');
    t_ok(strpos(custid_match_message($m, $mah), 'Maharashtra') !== false, 'match · the offer names the state (Maharashtra)');
    t_eq(custid_match('TB REFINERY LIMITED ' . $f['tag'], '', '', 0)['kind'], 'NAME', 'match · a similar name (Limited / Ltd) is a warning');
    $fd = find_duplicate_partner('X', $mah, $PAN, '', 0);
    t_eq([$fd['by'], $fd['confidence']], ['PAN', 'LOCATION'], 'duplicate finder · same PAN, new GSTIN = LOCATION (not EXACT)');
    t_eq(find_duplicate_partner('X', $guj, $PAN, '', 0)['confidence'], 'EXACT', 'duplicate finder · same GSTIN stays EXACT');
    t_ok(strpos(partner_find_or_problem('X', $mah, $PAN)['message'], 'billing location') !== false, 'staff guard · offers a billing location');

    // ---- Q-02: names compared without Pvt Ltd / Ltd / LLP / Co / Inc ------------
    t_eq(normalize_name('Sunrise Valves Pvt. Ltd.'), normalize_name('Sunrise Valves'), 'Q-02 · "Sunrise Valves Pvt. Ltd." = "Sunrise Valves"');
    t_eq(normalize_name('Sunrise Valves & Co'), normalize_name('SUNRISE VALVES INC'), 'Q-02 · "& Co" and "Inc" ignored too');
    $r = lead_create(['company_name' => 'Sunrise Valves Pvt Ltd ' . $f['tag'], 'contact_phone' => '9825011111', 'source' => 'REFERRAL']);
    $made['l'][] = (int) $r['id'];
    $hit = lead_possible_duplicate('Sunrise Valves ' . $f['tag']);
    t_ok($hit && $hit['kind'] === 'lead' && (int) $hit['row']['id'] === (int) $r['id'], 'Q-02 · a new lead "Sunrise Valves" flags the open lead "Sunrise Valves Pvt Ltd"');

    // ---- Billing locations ----------------------------------------------------------
    $loc = custid_add_location($tb, ['gstin' => $mah, 'label' => 'Mumbai plant', 'line1' => 'Trans-Thane Creek', 'city' => 'Navi Mumbai', 'pincode' => '400705']);
    t_ok(!empty($loc['id']), 'location · Maharashtra added to TB Refinery' . (!empty($loc['err']) ? ' — ' . $loc['err'] : ''));
    $mahId = (int) ($loc['id'] ?? 0);
    t_ok(!empty(custid_add_location($tb, ['gstin' => $mah])['err']), 'location · the same GSTIN again without its own address is refused (another office in the same state needs its address — owner 25 Nov, Q3)');
    t_ok(strpos((string) (custid_add_location($tb, ['gstin' => $G('33', 'AAACZ5555Q')])['err'] ?? ''), 'different PAN') !== false, 'location · a GSTIN with another PAN is refused (a different company)');
    t_eq(custid_match('Y', $mah, '', 0)['kind'], 'GSTIN', 'location · its GSTIN is now known to the central list');
    $locs = custid_locations($tb);
    t_eq(array_map(fn($l) => [$l['state_code'], $l['gstin']], $locs), [['24', $guj], ['27', $mah]], 'location · TB Refinery: Gujarat (main) + Maharashtra');
    t_eq([books_is_igst($tb, $f['mum'], 0), books_is_igst($tb, $f['mum'], $mahId)], [0, 1], 'tax · Gujarat = CGST+SGST; Maharashtra location = IGST');
    $tnAdd = custid_add_location($tb, ['gstin' => $tn, 'label' => 'Chennai']);
    $tnId = (int) ($tnAdd['id'] ?? 0);
    t_eq(custid_stop_location($tb, $tnId), '', 'location · a location can be stopped for new bills');
    t_eq(count(custid_locations($tb)), 2, 'location · a stopped location is no longer offered');

    // ---- Quotation → invoice: location, tax, the copy ----------------------------
    $pdo->prepare("INSERT INTO quotations (quote_no,rev,is_current,status,client_id,client_name,office_id,subject,total_amount,created_at) VALUES (?,0,1,'ACCEPTED',?,?,?,?,450000,?)")
        ->execute(['GBQ-' . $f['tag'], $tb, 'TB Refinery Ltd', $f['mum'], 'Tank inspection', date('c')]);
    $qid = (int) $pdo->lastInsertId(); $made['q'][] = $qid;
    custid_quote_set_location($qid, ['billing_address_id' => $mahId]);
    t_eq((int) ops_val("SELECT billing_address_id FROM quotations WHERE id=?", [$qid]), $mahId, 'quotation · billed to the Maharashtra location');
    custid_quote_set_location($qid, ['billing_address_id' => 999999]);
    t_eq((int) ops_val("SELECT billing_address_id FROM quotations WHERE id=?", [$qid]), 0, 'quotation · a location not of this customer falls back to the main one');
    custid_quote_set_location($qid, ['billing_address_id' => $mahId]);
    $split = sx_quote_gst_split(['gst_amount' => 81000, 'gst_pct' => 18, 'client_id' => $tb, 'office_id' => $f['mum'], 'billing_address_id' => $mahId]);
    t_eq($split['mode'] ?? '', 'IGST', 'quotation · its tax preview follows the location (IGST)');

    $inv = books_invoice_create(['partner_id' => $tb, 'office_id' => $f['mum'], 'quotation_id' => $qid]);
    $iid = (int) ($inv['id'] ?? 0); $made['inv'][] = $iid;
    $row = ops_one("SELECT * FROM invoices WHERE id=?", [$iid]);
    t_eq([(int) $row['billing_address_id'], $row['place_of_supply'], $row['gstin'], (int) $row['is_igst']], [$mahId, '27', $mah, 1], 'invoice · from the quotation: Maharashtra, its GSTIN, IGST');
    t_eq([(int) $row['bill_snap'], $row['bill_gstin'], $row['bill_state']], [1, $mah, '27'], 'invoice · keeps its own copy of who it is billed to');
    t_eq(custid_invoice_set_location($iid, 0), '', 'invoice · a draft can be moved to the main location');
    $row = ops_one("SELECT * FROM invoices WHERE id=?", [$iid]);
    t_eq([$row['place_of_supply'], $row['gstin'], (int) $row['is_igst'], $row['bill_gstin']], ['24', $guj, 0, $guj], 'invoice · main location: Gujarat, CGST+SGST, copy refreshed');
    t_eq(books_set_office($iid, $f['mum']), '', 'invoice · changing the branch keeps place of supply and GSTIN together');
    $row = ops_one("SELECT * FROM invoices WHERE id=?", [$iid]);
    t_eq([$row['place_of_supply'], $row['gstin']], ['24', $guj], 'invoice · still Gujarat after the branch is set again');

    // Print: the copy, not the live record.
    $print = function ($id) {
        $_GET['id'] = $id; ob_start(); try { ops_books('invoice-print', 'GET'); } catch (Throwable $e) { echo 'ERR ' . $e->getMessage(); } $h = (string) ob_get_clean();
        if (preg_match('~<div class="lbl">Bill to</div>(.*?)</td>~s', $h, $m)) return trim(preg_replace('/\s+/', ' ', strip_tags($m[1])));
        return 'NO-BILL-TO ' . substr(strip_tags($h), 0, 200);
    };
    $before = $print($iid);
    t_ok(strpos($before, $guj) !== false && strpos($before, 'Vadodara') !== false, 'print · shows the Gujarat GSTIN and billing address (' . $before . ')');
    $pdo->prepare("UPDATE business_partners SET display_name=?, legal_name=? WHERE id=?")->execute(['TB Refinery (renamed)', 'TB Refinery Renamed Ltd', $tb]);
    $pdo->prepare("UPDATE partner_addresses SET city='Surat' WHERE partner_id=? AND is_primary=1")->execute([$tb]);
    t_eq($print($iid), $before, 'print · editing the customer afterwards does not change the bill');

    // An invoice raised BEFORE this change (no copy) prints the live record, exactly as today.
    $pdo->prepare("UPDATE invoices SET bill_snap=0, bill_name='', bill_gstin='', bill_state='', bill_address='' WHERE id=?")->execute([$iid]);
    $legacy = $print($iid);
    t_ok(strpos($legacy, 'Surat') !== false && strpos($legacy, 'TB Refinery (renamed)') !== false, 'print · an older invoice (no copy) reads the record as it always did');

    // ---- D3: identity required at the next invoice ----------------------------------
    $bare = $mk('Old Customer ' . $f['tag'], ['tax_status' => 'GOVT_NO_PAN', 'tax_reason' => 'tmp']);
    $pdo->prepare("UPDATE business_partners SET tax_status='', tax_reason='', gstin='', pan='', state='Gujarat' WHERE id=?")->execute([$bare]);
    $bi = books_invoice_create(['partner_id' => $bare, 'office_id' => $f['mum']]); $made['inv'][] = (int) $bi['id'];
    books_line_add((int) $bi['id'], ['description' => 'Inspection', 'qty' => 1, 'rate' => 1000, 'gst_pct' => 18]);
    $why = books_issue_missing(books_invoice((int) $bi['id']));
    t_ok((bool) array_filter($why, fn($w) => strpos($w, 'no GSTIN and PAN') !== false), 'D3 · a customer with no GSTIN/PAN cannot have its next invoice issued');
    t_ok(!array_filter(books_issue_missing(books_invoice($iid)), fn($w) => strpos($w, 'no GSTIN and PAN') !== false), 'D3 · a complete customer is not stopped');
    $h = custid_health();
    t_ok($h['missing'] >= 1, 'D3 · Data health counts customers missing GSTIN/PAN (' . $h['missing'] . ')');
    $gov = $mk('State PWD ' . $f['tag'], ['tax_status' => 'GOVT_NO_PAN', 'tax_reason' => 'no PAN issued']);
    t_ok(custid_health()['govt_no_pan'] >= 1 && custid_complete(ops_one("SELECT * FROM business_partners WHERE id=?", [$gov])), 'D2/D3 · a government department without PAN is complete, and counted for Finance');
    $rows = sx_health(0);
    t_ok((bool) array_filter($rows, fn($r) => strpos($r[0], 'without GSTIN and PAN') !== false), 'Data health · the new line is on Sales home');

    // ---- Won paths: lead → customer, won deal → customer, accepted quotation ------
    $lr = lead_create(['company_name' => 'Kite Engineering ' . $f['tag'], 'contact_phone' => '9825022222', 'source' => 'REFERRAL']);
    $made['l'][] = (int) $lr['id'];
    $c1 = lead_convert((int) $lr['id'], []);
    t_ok(!empty($c1['err']) && strpos($c1['err'], 'GSTIN') !== false, 'lead → customer · refused without GSTIN/PAN');
    $kite = $G('24', 'AAFCK4321M');
    $c2 = lead_convert((int) $lr['id'], ['gstin' => $kite]);
    $kp = (int) (ops_val("SELECT converted_partner_id FROM leads WHERE id=?", [(int) $lr['id']]) ?: 0); if ($kp) $made['bp'][] = $kp;
    t_ok(empty($c2['err']) && $kp && ops_val("SELECT pan FROM business_partners WHERE id=?", [$kp]) === 'AAFCK4321M', 'lead → customer · with a GSTIN: customer created with its PAN' . (!empty($c2['err']) ? ' — ' . $c2['err'] : ''));
    $lr2 = lead_create(['company_name' => 'TB Refinery Chennai ' . $f['tag'], 'contact_phone' => '9825033333', 'source' => 'REFERRAL']);
    $made['l'][] = (int) $lr2['id'];
    $c3 = lead_convert((int) $lr2['id'], ['gstin' => $tn]);
    t_ok(!empty($c3['err']) && (int) ($c3['loc_offer'] ?? 0) === $tb, 'lead → customer · same PAN, new GSTIN: offered as TB Refinery\'s billing location');
    $c4 = lead_convert((int) $lr2['id'], ['gstin' => $tn, 'add_location_to' => $tb]);
    t_ok(empty($c4['err']) && (int) ops_val("SELECT converted_partner_id FROM leads WHERE id=?", [(int) $lr2['id']]) === $tb, 'lead → customer · ticked: converted to TB Refinery, no second customer' . (!empty($c4['err']) ? ' — ' . $c4['err'] : ''));
    t_ok((bool) ops_val("SELECT id FROM partner_addresses WHERE partner_id=? AND gstin=? AND is_billing=1", [$tb, $tn]), 'lead → customer · Tamil Nadu is now a billing location of TB Refinery');

    $pipe = pipeline_default('OPPORTUNITY');
    $o = opp_create(['name' => 'Won deal ' . $f['tag'], 'partner_name' => 'Nova Castings ' . $f['tag'], 'value' => 100000, 'pipeline_id' => $pipe['id']]);
    $made['o'][] = (int) $o['id'];
    $pdo->prepare("UPDATE opportunities SET status='WON', lead_id=NULL WHERE id=?")->execute([(int) $o['id']]);
    t_ok(!empty(opp_add_customer((int) $o['id'], ['company_name' => 'Nova Castings ' . $f['tag']])['err']), 'won deal → customer · refused without GSTIN/PAN');
    $oc = opp_add_customer((int) $o['id'], ['company_name' => 'Nova Castings ' . $f['tag'], 'tax_status' => 'UNREGISTERED', 'pan' => 'AAJFN7777P', 'tax_reason' => 'below the GST threshold']);
    if (!empty($oc['partner_id'])) $made['bp'][] = (int) $oc['partner_id'];
    t_ok(!empty($oc['partner_id']) && ops_val("SELECT tax_status FROM business_partners WHERE id=?", [(int) $oc['partner_id']]) === 'UNREGISTERED', 'won deal → customer · not GST-registered with PAN + reason is accepted' . (!empty($oc['err']) ? ' — ' . $oc['err'] : ''));

    $qn = ['client_name' => 'Orbit Pumps ' . $f['tag'], 'office_id' => $f['mum'], '_cid' => custid_from_input(['gstin' => $G('29', 'AAGCO2468H')], true)];
    $opid = crm_register_client_for_quote($qn); if ($opid) $made['bp'][] = (int) $opid;
    t_eq((string) ops_val("SELECT gstin FROM business_partners WHERE id=?", [(int) $opid]), $G('29', 'AAGCO2468H'), 'accepted quotation · the new customer gets the GSTIN given as it was won');
    $res = custid_resolve_new_customer('Someone', ['gstin' => $mah]);
    t_ok(!empty($res['err']) && strpos($res['err'], 'already belongs') !== false, 'accepted quotation · a GSTIN already on file is refused');

    // ---- Import: never overwrite the main GSTIN with a branch row ---------------------
    if (function_exists('partner_import_parse')) {
        $hdr = ['Legal name', 'Role', 'GSTIN', 'City'];
        $p = partner_import_parse([$hdr, ['TB Refinery Ltd ' . $f['tag'], 'Client', $G('09', $PAN), 'Noida']], 'Client');
        $row0 = $p['rows'][0] ?? [];
        t_eq([$row0['action'] ?? '', (int) ($row0['match_id'] ?? 0)], ['location', $tb], 'import · a row with the same PAN and a new GSTIN becomes a billing location');
        $res = partner_import_apply($p['rows']);
        t_eq([(int) ($res['locations'] ?? 0), (string) ops_val("SELECT gstin FROM business_partners WHERE id=?", [$tb])], [1, $guj], 'import · added as a location; TB Refinery keeps its Gujarat GSTIN');
        $p2 = partner_import_parse([$hdr, ['Brand New Client ' . $f['tag'], 'Client', '', 'Pune']], 'Client');
        t_ok(!empty($p2['rows'][0]['skip']) && strpos(implode(' ', $p2['rows'][0]['notes']), 'GSTIN') !== false, 'import · a new client with no GSTIN/PAN is not added (with the reason)');
        $p3 = partner_import_parse([$hdr, ['Brand New Vendor ' . $f['tag'], 'Vendor', '', 'Pune']], 'Vendor');
        t_ok(empty($p3['rows'][0]['skip']), 'import · a vendor-only row needs no GSTIN');
    }

    // ---- D1: move an existing branch company under the main customer ----------------
    $brG = $G('07', $PAN);
    $br = $mk('TB Refinery Delhi ' . $f['tag'], ['gstin' => $brG], ['parent_id' => $tb]);
    $pdo->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city,state,pincode,is_primary) VALUES (?,'REGISTERED','Delhi','Okhla Phase 2','New Delhi','Delhi','110020',1)")->execute([$br]);
    $old = books_invoice_create(['partner_id' => $br, 'office_id' => $f['mum']]); $oid = (int) $old['id']; $made['inv'][] = $oid;
    $pdo->prepare("UPDATE invoices SET bill_snap=0, bill_name='', bill_gstin='', bill_state='', bill_address='', billing_address_id=0 WHERE id=?")->execute([$oid]);   // as raised before this change
    $oldPrint = $print($oid);
    $pv = custid_move_preview($br);
    t_ok(empty($pv['err']) && $pv['creates_location'] && $pv['invoices'] === 1, 'move · preview: Delhi becomes a location, 1 invoice keeps its copy' . (!empty($pv['err']) ? ' — ' . $pv['err'] : ''));
    t_ok(!empty(custid_move_apply($br, '')['err']), 'move · refused without a reason');
    t_eq((string) ops_val("SELECT status FROM business_partners WHERE id=?", [$br]), 'ACTIVE', 'move · nothing changed by the preview or the refused attempt');
    $mv = custid_move_apply($br, 'same PAN — Delhi branch');
    t_ok(!empty($mv['ok']), 'move · done when a person presses it with a reason' . (!empty($mv['err']) ? ' — ' . $mv['err'] : ''));
    t_eq([(string) ops_val("SELECT status FROM business_partners WHERE id=?", [$br]), (int) ops_val("SELECT partner_id FROM invoices WHERE id=?", [$oid])], ['MERGED', $tb], 'move · the branch record is retired (kept) and its invoice now belongs to TB Refinery');
    t_eq($print($oid), $oldPrint, 'move · the branch\'s old invoice prints exactly as before (' . $oldPrint . ')');
    t_ok((bool) ops_val("SELECT id FROM partner_addresses WHERE partner_id=? AND gstin=? AND is_billing=1", [$tb, $brG]), 'move · Delhi is now a billing location of TB Refinery');
    t_ok((bool) ops_val("SELECT id FROM idems_audit WHERE entity='partner' AND entity_id=? AND action='BRANCH_MOVED_UNDER'", [$tb]), 'move · written to the audit trail (who, why)');
    $other = $mk('Different Co ' . $f['tag'], ['gstin' => $G('24', 'AAKCD1111E')], ['parent_id' => $tb]);
    t_ok(strpos((string) (custid_move_preview($other)['err'] ?? ''), 'PANs differ') !== false, 'move · a branch with a different PAN is not joined');

    // ---- Who may (D4: no new permission) ---------------------------------------------
    bbv_as($f['neha']);   // a coordinator edits client records today
    $tbRow = ops_one("SELECT * FROM business_partners WHERE id=?", [$tb]);
    t_eq(custid_can_edit($tbRow), (is_master() || can('mod.clients.edit') || can('mod.vendors.edit') || is_coordinator_level()) && (!function_exists('partner_can_write') || partner_can_write($tbRow)),
         'D4 · adding a location follows exactly the client-edit rule (no new permission)');
    bbv_as($f['amit']);   // an inspector
    t_ok(!custid_can_edit(ops_one("SELECT * FROM business_partners WHERE id=?", [$tb])), 'D4 · an inspector cannot add a billing location');
    bbv_as($f['master']);
} finally {
    bbv_as($f['master']);
    $del = function ($t, $ids) use ($pdo) { $ids = array_filter(array_map('intval', $ids)); if ($ids) $pdo->exec("DELETE FROM $t WHERE id IN (" . implode(',', $ids) . ")"); };
    $del('invoices', $made['inv']); $del('quotations', $made['q']); $del('opportunities', $made['o']); $del('leads', $made['l']);
    if ($made['bp']) { $in = implode(',', array_map('intval', $made['bp'])); $pdo->exec("DELETE FROM partner_addresses WHERE partner_id IN ($in)"); $pdo->exec("DELETE FROM partner_contacts WHERE partner_id IN ($in)"); }
    $pdo->exec("DELETE FROM business_partners WHERE legal_name LIKE '%" . $f['tag'] . "%'");
    $del('business_partners', $made['bp']);
    bbv_cleanup($f);
}
