<?php
// Owner, 25 Nov 2026 — Q3 "Yes":
//   1. the Billing addresses box is on the Sales customer page too (same box,
//      same edit rule; view-only people see the list without the buttons), and
//      Add / Change / Stop land back on the page they came from;
//   2. the two buttons are renamed so they cannot be confused;
//   3. a second office in the SAME state under the SAME GSTIN (GST "additional
//      place of business") can be added with its own address;
//   4. the form fits a phone (one column, full-width button).
//
// Example used throughout (made up): Sunrise Valves Pvt Ltd, PAN AAACC1206D —
// Gujarat main 24AAACC1206D1ZM (Vadodara), a second Gujarat office in Ankleshwar
// under the same GSTIN, Maharashtra 27AAACC1206D1ZG.
t_section('Billing addresses — same GSTIN, Sales page, renamed buttons (owner 25 Nov, Q3)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$made = ['bp' => [], 'inv' => []];
$pdo = db();
$G = function ($state, $pan, $ent = '1') { $first = $state . $pan . $ent . 'Z'; return $first . commercial_gstin_check_char($first); };
// A PAN unique to this run, so the GSTINs never meet another test's records.
$PAN = 'AAACS' . str_pad((string) (abs(crc32($f['tag'])) % 10000), 4, '0', STR_PAD_LEFT) . 'D';
$guj = $G('24', $PAN); $mah = $G('27', $PAN);
try {
    bbv_as($f['master']); custid_migrate(); books_migrate();
    $pdo->prepare("UPDATE offices SET gstin=? WHERE id=?")->execute([$G('24', 'AABCM9999K'), $f['mum']]);   // our branch is in Gujarat

    // The made-up GSTINs given to the owner for opstest really carry a correct check character.
    t_eq([custid_gstin_problem('24AAACC1206D1ZM'), custid_gstin_problem('27AAACC1206D1ZG'), custid_gstin_problem('29AAACC1206D1ZC')], ['', '', ''],
         'test data · 24AAACC1206D1ZM, 27AAACC1206D1ZG and 29AAACC1206D1ZC pass the check');
    t_ok(custid_gstin_problem('27AAACC1206D1ZA') !== '', 'test data · 27AAACC1206D1ZA (the owner\'s typed number) is refused by the check');

    $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
        ->execute(['SV-' . $f['tag'], 'Sunrise Valves Pvt Ltd ' . $f['tag'], 'Sunrise Valves ' . $f['tag'], date('c')]);
    $sv = (int) $pdo->lastInsertId(); $made['bp'][] = $sv;
    custid_save($sv, custid_from_input(['gstin' => $guj], true));
    $pdo->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city,state,pincode,is_primary) VALUES (?,'REGISTERED','HO','Plot 12, GIDC Makarpura','Vadodara','Gujarat','390010',1)")->execute([$sv]);

    // ---- 3. The same GSTIN, another office in the same state -------------------------
    $r = custid_add_location($sv, ['gstin' => $guj]);
    t_ok(!empty($r['err']) && strpos($r['err'], 'address and city') !== false, 'same GSTIN · without an address it is refused, asking for the address and city');
    $r = custid_add_location($sv, ['gstin' => $guj, 'line1' => 'Plot 12, GIDC Makarpura']);
    t_ok(!empty($r['err']) && strpos($r['err'], 'address and city') !== false, 'same GSTIN · without a city it is refused');
    $r = custid_add_location($sv, ['gstin' => strtolower($guj), 'line1' => 'plot 12 gidc  makarpura', 'city' => 'VADODARA', 'pincode' => '390 010']);
    t_ok(!empty($r['err']) && strpos($r['err'], 'already a billing address') !== false, 'same GSTIN · the main office\'s own address again (case and spaces differ) is refused as a duplicate');
    $r = custid_add_location($sv, ['gstin' => $guj, 'line1' => 'Plot 4401, GIDC Estate', 'city' => 'Ankleshwar', 'pincode' => '393002']);
    t_ok(!empty($r['id']) && !empty($r['same_gstin']), 'same GSTIN · a second Gujarat office (Ankleshwar) is added under the same GSTIN' . (!empty($r['err']) ? ' — ' . $r['err'] : ''));
    $ank = (int) ($r['id'] ?? 0);
    t_eq((string) ops_val("SELECT label FROM partner_addresses WHERE id=?", [$ank]), 'Ankleshwar', 'same GSTIN · with no name given it is named after its city');
    $r = custid_add_location($sv, ['gstin' => $guj, 'label' => 'Again', 'line1' => 'Plot 4401, GIDC Estate', 'city' => 'Ankleshwar', 'pincode' => '393002']);
    t_ok(!empty($r['err']) && strpos($r['err'], 'Ankleshwar') !== false, 'same GSTIN · the Ankleshwar address a second time is refused, naming the one on file');
    $r = custid_add_location($sv, ['gstin' => $guj, 'label' => 'Dahej', 'line1' => 'GIDC Dahej', 'city' => 'Dahej']);
    $dahej = (int) ($r['id'] ?? 0);
    t_ok($dahej > 0, 'same GSTIN · a third Gujarat office (Dahej) is added too');
    t_ok(custid_update_location($sv, $dahej, ['label' => 'Dahej', 'line1' => 'Plot 4401 GIDC Estate', 'city' => 'ankleshwar', 'pincode' => '393002']) !== '',
         'same GSTIN · changing Dahej\'s address into Ankleshwar\'s is refused');
    t_eq(custid_update_location($sv, $dahej, ['label' => 'Dahej SEZ', 'line1' => 'GIDC Dahej SEZ', 'city' => 'Dahej']), '', 'same GSTIN · an ordinary change of its own address is saved');
    t_eq(custid_stop_location($sv, $dahej), '', 'same GSTIN · one of the same-GSTIN offices can be stopped on its own');
    $locs = custid_locations($sv);
    t_eq(array_map(fn($l) => [$l['label'], $l['gstin']], $locs), [['Main', $guj], ['Ankleshwar', $guj]], 'same GSTIN · Sunrise Valves: Main + Ankleshwar, both under ' . $guj);

    // Another state as before; another customer's GSTIN still refused; another PAN still refused.
    $r = custid_add_location($sv, ['gstin' => $mah, 'label' => 'Mumbai plant', 'line1' => 'MIDC Andheri', 'city' => 'Mumbai', 'pincode' => '400093']);
    $mahId = (int) ($r['id'] ?? 0);
    t_ok($mahId > 0 && empty($r['same_gstin']), 'another state · Maharashtra added with its own GSTIN, as before');
    $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
        ->execute(['OT-' . $f['tag'], 'Other Traders ' . $f['tag'], 'Other Traders ' . $f['tag'], date('c')]);
    $ot = (int) $pdo->lastInsertId(); $made['bp'][] = $ot;
    $otG = $G('24', 'AAKCO' . substr($PAN, 5, 4) . 'Q');
    custid_save($ot, custid_from_input(['gstin' => $otG], true));
    $r = custid_add_location($sv, ['gstin' => $otG, 'line1' => 'Somewhere', 'city' => 'Surat']);
    t_ok(!empty($r['err']) && (strpos($r['err'], 'already belongs') !== false || strpos($r['err'], 'PAN') !== false), 'refused · another customer\'s GSTIN cannot become a Sunrise Valves address');
    $r = custid_add_location($ot, ['gstin' => $guj, 'line1' => 'New office', 'city' => 'Surat']);
    t_ok(!empty($r['err']), 'refused · Sunrise Valves\' GSTIN cannot be added to another customer, even with a new address');
    t_ok(strpos((string) custid_match_message(custid_match('X', $guj, '', 0), $guj), 'Add a billing address / GSTIN') !== false,
         'new customer form · typing an existing GSTIN points to "+ Add a billing address / GSTIN" on that customer');

    // Pick-lists tell the two Gujarat offices apart by their city.
    $map = custid_location_map()[$sv] ?? [];
    $words = array_column($map, 1);
    t_ok(count($words) === 3 && strpos($words[0], 'Vadodara') !== false && strpos($words[1], 'Ankleshwar') !== false && strpos($words[2], 'Vadodara') === false,
         'pick-list · the two offices under one GSTIN show their city; Maharashtra does not need it (' . implode(' | ', $words) . ')');

    // ---- Tax and the invoice copy for the second office -------------------------------
    t_eq([books_is_igst($sv, $f['mum'], 0), books_is_igst($sv, $f['mum'], $ank), books_is_igst($sv, $f['mum'], $mahId)], [0, 0, 1],
         'tax · Vadodara and Ankleshwar = CGST+SGST (same state); Maharashtra = IGST');
    $inv = books_invoice_create(['partner_id' => $sv, 'office_id' => $f['mum'], 'billing_address_id' => $ank]);
    $iid = (int) ($inv['id'] ?? 0); $made['inv'][] = $iid;
    $row = ops_one("SELECT * FROM invoices WHERE id=?", [$iid]);
    t_eq([(int) $row['billing_address_id'], $row['place_of_supply'], $row['gstin'], (int) $row['is_igst'], $row['bill_gstin']], [$ank, '24', $guj, 0, $guj],
         'invoice · billed to Ankleshwar: Gujarat, the same GSTIN, CGST+SGST');
    t_ok(strpos((string) $row['bill_address'], 'Ankleshwar') !== false && strpos((string) $row['bill_address'], 'Vadodara') === false,
         'invoice · its copy carries the Ankleshwar address, not the head office (' . $row['bill_address'] . ')');
    books_line_add($iid, ['description' => 'Valve inspection', 'qty' => 1, 'rate' => 10000, 'gst_pct' => 18]);
    $row = ops_one("SELECT * FROM invoices WHERE id=?", [$iid]);
    t_eq([round((float) $row['cgst'], 2), round((float) $row['sgst'], 2), round((float) $row['igst'], 2)], [900.0, 900.0, 0.0], 'invoice · ₹10,000 at 18%: CGST ₹900 + SGST ₹900, no IGST');
    $print = function ($id) {
        $_GET['id'] = $id; ob_start(); try { ops_books('invoice-print', 'GET'); } catch (Throwable $e) { echo 'ERR ' . $e->getMessage(); } $h = (string) ob_get_clean();
        if (preg_match('~<div class="lbl">Bill to</div>(.*?)</td>~s', $h, $m)) return trim(preg_replace('/\s+/', ' ', strip_tags($m[1])));
        return 'NO-BILL-TO ' . substr(strip_tags($h), 0, 200);
    };
    $p1 = $print($iid);
    t_ok(strpos($p1, 'Ankleshwar') !== false && strpos($p1, $guj) !== false, 'print · Bill to shows the Ankleshwar address and GSTIN ' . $guj . ' (' . $p1 . ')');

    // An issued invoice never changes afterwards.
    $pdo->prepare("UPDATE invoices SET status='ISSUED' WHERE id=?")->execute([$iid]);
    t_eq(custid_update_location($sv, $ank, ['label' => 'Ankleshwar', 'line1' => 'Plot 9, New Estate', 'city' => 'Bharuch', 'pincode' => '392001']), '', 'issued · the Ankleshwar office later moves to Bharuch');
    custid_invoice_snapshot($iid);
    t_eq($print($iid), $p1, 'issued · the invoice already issued still prints the Ankleshwar address');
    t_ok(strpos((string) ops_val("SELECT bill_address FROM invoices WHERE id=?", [$iid]), 'Ankleshwar') !== false, 'issued · its stored copy is untouched');

    // ---- 1. The box on the Sales customer page, and who sees the buttons ------------
    $render = function ($back) use ($sv) {
        $p = ops_one("SELECT * FROM business_partners WHERE id=?", [$sv]);
        $blBack = $back;
        ob_start(); include dirname(__DIR__) . '/views/ops/_billing_locations.php'; return (string) ob_get_clean();
    };
    $h = $render('customer');
    t_ok(strpos($h, '+ Add a billing address / GSTIN') !== false && strpos($h, 'Ankleshwar') !== false, 'box · an editor sees the list and "+ Add a billing address / GSTIN"');
    t_ok(strpos($h, 'Add a billing location (another state)') === false, 'box · the old "(another state)" label is gone');
    t_eq(substr_count($h, 'name="back" value="customer"'), substr_count($h, 'name="do" value="'), 'box · on the Sales page every Add / Change / Stop form says to come back to it');
    t_ok(strpos($render('partner'), 'name="back" value="partner"') !== false, 'box · on the master record the forms come back to the master record');
    t_ok(strpos($h, 'Another office in the same state') !== false, 'box · the help text explains another state vs another office in the same state');
    t_eq([custid_back_url(['back' => 'customer'], $sv), custid_back_url(['back' => 'partner'], $sv), custid_back_url(['back' => 'https://evil.example/'], $sv), custid_back_url([], $sv)],
         ['/customer?id=' . $sv . '#billing-locations', '/partner?id=' . $sv . '#billing-locations', '/partner?id=' . $sv . '#billing-locations', '/partner?id=' . $sv . '#billing-locations'],
         'back · lands on the Sales page or the master record — any other value is ignored');
    $idx = (string) file_get_contents(dirname(__DIR__) . '/index.php');
    t_ok(strpos($idx, 'redirect(custid_back_url($_POST, (int) $p[\'id\']));') !== false, 'back · the billing-address route uses that rule');

    // View-only: someone who may open the customer but not edit it sees the list, no buttons.
    $viewer = 0;
    foreach (['meera', 'rakesh', 'sbu', 'am'] as $k) {
        bbv_as($f[$k]);
        if (!custid_can_edit(ops_one("SELECT * FROM business_partners WHERE id=?", [$sv]))) { $viewer = $f[$k]; break; }
    }
    t_ok($viewer > 0, 'box · a fixture user who cannot edit clients was found');
    if ($viewer) {
        $hv = $render('customer');
        t_ok(strpos($hv, 'Ankleshwar') !== false && strpos($hv, '+ Add a billing address') === false && strpos($hv, 'Stop using for new bills') === false,
             'box · a view-only person sees the addresses but no Add / Change / Stop');
    }
    bbv_as($f['master']);

    // The real Sales customer page carries the box (rendered by its own route).
    putenv('BBV_RAW=1');
    $res = bbv_route('customer', 'GET', $f['master'], $sv);
    putenv('BBV_RAW');
    $html = (string) ($res['html'] ?? '');
    t_ok(!empty($res['view_ok']) && strpos($html, 'id="billing-locations"') !== false && strpos($html, '+ Add a billing address / GSTIN') !== false,
         'Sales page · /customer shows the Billing addresses box with the add button' . (!empty($res['view_err']) ? ' — ' . $res['view_err'] : ''));
    t_ok(strpos($html, 'Create a separate branch company (handled by another of our offices)') !== false && strpos($html, 'Add a branch / GSTIN of this client for another office') === false,
         'Sales page · the branch-company button is renamed');

    // ---- 4. Phone: an inline form is one column with a full-width button ----------------
    $css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/app.css');
    t_ok((bool) preg_match('~@media \(max-width:560px\)\{\s*\.inline-add\{grid-template-columns:minmax\(0,1fr\)\}~', $css), 'phone · .inline-add is one column under 560px');
    t_ok(strpos($css, '.inline-add button,.inline-add .btn{width:100%}') !== false, 'phone · its buttons are full width');
} finally {
    bbv_as($f['master']);
    putenv('BBV_RAW');
    $del = function ($t, $ids) use ($pdo) { $ids = array_filter(array_map('intval', $ids)); if ($ids) $pdo->exec("DELETE FROM $t WHERE id IN (" . implode(',', $ids) . ")"); };
    if ($made['inv']) { $in = implode(',', array_map('intval', $made['inv'])); try { $pdo->exec("DELETE FROM invoice_lines WHERE invoice_id IN ($in)"); } catch (Throwable $e) {} }
    $del('invoices', $made['inv']);
    if ($made['bp']) { $in = implode(',', array_map('intval', $made['bp'])); $pdo->exec("DELETE FROM partner_addresses WHERE partner_id IN ($in)"); }
    $del('business_partners', $made['bp']);
    bbv_cleanup($f);
}
