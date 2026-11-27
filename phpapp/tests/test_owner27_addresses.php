<?php
// Owner's test results, 27 Nov 2026 — billing addresses (BL-03, BL-05, BL-07) and the
// owner's answer A3 ("both shall have same form and have GSTIN value input so we don't
// have duplicate screens"):
//   C1  the "Main" billing line uses only the head-office address — never one of the
//       billing locations' — and says so when none is on file; "Mark as head office";
//   C2  one form for a billing address: the Addresses tab offers the same form (with
//       its GSTIN), a GSTIN-less "Billing Address" is not saved by the plain form, and
//       old GSTIN-less ones are labelled, not deleted;
//   C3  the state read from the GSTIN shows as you type; a visible Change button;
//   C4  the Registration tab lists the GSTINs from the billing addresses;
//   C5  "who already has this GSTIN?" as it is pasted — same people as on Save.
//
// The owner's own scenario (made up numbers): a client created by a lead conversion,
// so it has NO address; then Mumbai plant (Maharashtra GSTIN); then Ankleshwar works
// under the same Gujarat GSTIN; then a third Gujarat office.
t_section('Owner 27 Nov — billing addresses: head office, one form, GSTIN as you type');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$made = ['bp' => []];
$pdo = db();
$root = dirname(__DIR__);
$G = function ($state, $pan, $ent = '1') { $first = $state . $pan . $ent . 'Z'; return $first . commercial_gstin_check_char($first); };
$PAN = 'AAACR' . str_pad((string) (abs(crc32($f['tag'] . 'r')) % 10000), 4, '0', STR_PAD_LEFT) . 'D';
$guj = $G('24', $PAN); $mah = $G('27', $PAN);
$render = function ($file, array $vars) {
    extract($vars);
    ob_start();
    try { include $file; } catch (Throwable $e) { echo 'RENDER-ERROR ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine(); }
    return (string) ob_get_clean();
};
try {
    bbv_as($f['master']); custid_migrate();
    // ---- The client a lead conversion makes: GSTIN and PAN, no address at all ----------
    $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
        ->execute(['RV-' . $f['tag'], 'Riverside Valves Pvt Ltd ' . $f['tag'], 'Riverside Valves ' . $f['tag'], date('c')]);
    $rv = (int) $pdo->lastInsertId(); $made['bp'][] = $rv;
    custid_save($rv, custid_from_input(['gstin' => $guj], true));
    t_eq(custid_head_office_address($rv), null, 'C1 · a client converted from a lead has no head-office address yet');

    $r = custid_add_location($rv, ['gstin' => $mah, 'label' => 'Mumbai plant', 'line1' => 'MIDC Andheri', 'city' => 'Mumbai', 'pincode' => '400093']);
    $mumId = (int) ($r['id'] ?? 0);
    t_ok($mumId > 0, 'scenario · Mumbai plant added (Maharashtra GSTIN)');
    $locs = custid_locations($rv);
    t_eq([$locs[0]['label'], $locs[0]['gstin'], $locs[0]['address']], ['Main', $guj, ''],
         'C1 · BL-03: the Main line (Gujarat GSTIN) does NOT borrow the Mumbai plant\'s address — it is empty');
    t_eq(custid_head_office_address($rv), null, 'C1 · a billing location is never the head office');

    // Ankleshwar under the same GSTIN — the duplicate message must not name "Main".
    $r = custid_add_location($rv, ['gstin' => $guj, 'label' => 'Ankleshwar works', 'line1' => 'Plot 4401 GIDC Estate', 'city' => 'Ankleshwar', 'pincode' => '393002']);
    $ankId = (int) ($r['id'] ?? 0);
    t_ok($ankId > 0 && !empty($r['same_gstin']), 'scenario · Ankleshwar works added under the same Gujarat GSTIN' . (!empty($r['err']) ? ' — ' . $r['err'] : ''));
    $r = custid_add_location($rv, ['gstin' => $guj, 'line1' => 'plot 4401  gidc estate', 'city' => 'ANKLESHWAR', 'pincode' => '393002']);
    t_ok(!empty($r['err']) && strpos($r['err'], 'Ankleshwar works') !== false && strpos($r['err'], '(Main)') === false,
         'C1 · BL-05: the same address again is refused naming Ankleshwar works, not "Main" (' . ($r['err'] ?? '') . ')');
    $r = custid_add_location($rv, ['gstin' => $guj, 'label' => 'Dahej', 'line1' => 'GIDC Dahej', 'city' => 'Dahej', 'pincode' => '392130']);
    t_ok(!empty($r['id']), 'scenario · a third Gujarat office (Dahej) is added too');
    $locs = custid_locations($rv);
    t_eq(array_map(fn($l) => $l['label'], $locs), ['Main', 'Mumbai plant', 'Ankleshwar works', 'Dahej'], 'C1 · every row is listed: Main, Mumbai plant, Ankleshwar works, Dahej');

    // The box says the head office is missing, with a link, and offers a visible Change.
    $p = ops_one("SELECT * FROM business_partners WHERE id=?", [$rv]);
    $h = $render($root . '/views/ops/_billing_locations.php', ['p' => $p, 'blBack' => 'partner']);
    t_ok(strpos($h, 'Head-office address not on file') !== false && strpos($h, 'tab=addresses') !== false,
         'C1 · the Main row reads "Head-office address not on file — add it" with a link to Addresses');
    t_ok(strpos($h, 'MIDC Andheri') !== false && substr_count($h, 'MIDC Andheri') <= 2, 'C1 · the Mumbai address shows on its own row only (row + its change form)');
    t_eq(substr_count($h, '✎ Change</a>'), 3, 'C3 · each of the three extra rows has a visible "✎ Change" button');
    t_ok(strpos($h, 'id="bl-edit-' . $ankId . '"') !== false && strpos($h, 'href="#bl-edit-' . $ankId . '"') !== false,
         'C3 · the button opens that address\'s own change form, full width under the list');
    t_ok(strpos($h, 'readonly') !== false && strpos($h, 'stays — it is what this address is') !== false, 'C3 · the GSTIN shows in the change form but cannot be changed');
    t_ok(strpos($h, 'class="ff-help gstin-state"') !== false, 'C3 · the add form has the place where "State: … (from the GSTIN)" appears');
    t_ok(strpos($h, 'data-gstin-who="' . $rv . '"') !== false && strpos($h, 'data-gstin-pan="' . $PAN . '"') !== false,
         'C5 · the add form asks who has the GSTIN (leaving this client out) and knows this client\'s PAN');

    // Pick-list and the invoice copy follow the same rule.
    $words = array_column(custid_location_map()[$rv] ?? [], 1);
    t_ok(isset($words[0]) && strpos($words[0], 'Main') === 0 && strpos($words[0], 'Mumbai') === false,
         'C1 · the quotation / invoice pick-list "Main" line no longer reads Mumbai (' . ($words[0] ?? '') . ')');
    t_eq(custid_print_address(custid_location($rv, 0)['addr'] ?? null), '', 'C1 · an invoice billed to Main copies no borrowed address');

    // ---- Mark as head office ---------------------------------------------------------
    $pdo->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city,state,pincode,is_primary) VALUES (?,'PLANT','Works','Survey 12, Waghodia Road','Vadodara','Gujarat','390019',0)")->execute([$rv]);
    $works = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city,state,pincode,is_primary) VALUES (?,'CORPORATE','Corporate','Alkapuri','Vadodara','Gujarat','390007',0)")->execute([$rv]);
    $corp = (int) $pdo->lastInsertId();
    t_eq((int) (custid_head_office_address($rv)['id'] ?? 0), $corp, 'C1 · with no address marked, a corporate office is preferred to a plant');
    t_eq(custid_mark_head_office($rv, $works), '', 'C1 · "Mark as head office" on the Works address');
    t_eq([(int) (custid_head_office_address($rv)['id'] ?? 0), (int) ops_val("SELECT COUNT(*) FROM partner_addresses WHERE partner_id=? AND is_primary=1", [$rv])], [$works, 1],
         'C1 · Works is now the head office — and only one address is marked');
    t_ok(strpos(custid_locations($rv)[0]['address'], 'Waghodia') !== false, 'C1 · the Main billing line now shows the Works address');
    t_ok(custid_mark_head_office($rv, $mumId) !== '', 'C1 · a billing location (own GSTIN) cannot be marked head office');
    t_ok(custid_mark_head_office($rv, 999999) !== '', 'C1 · an address of another client cannot be marked');
    t_ok((bool) ops_val("SELECT COUNT(*) FROM activities WHERE partner_id=? AND subject LIKE 'Head-office address set%'", [$rv]) || !function_exists('act_log'),
         'C1 · the change is written to History');
    $idx = (string) file_get_contents($root . '/index.php');
    t_ok(preg_match("~route === 'partner-address-head-office' && \\\$method === 'POST'.{0,700}mod\.clients\.edit.{0,500}partner_can_write~s", $idx) === 1,
         'C1 · the route asks the same right and office boundary as adding an address');

    // ---- C2 — one form, one save path ----------------------------------------------------
    t_ok(preg_match("~kind === 'address' && !empty\(\\\$p\['is_client'\]\) && strtoupper\(\(string\) \(\\\$b\['address_type'\] \?\? ''\)\) === 'BILLING'~", $idx) === 1,
         'C2 · the plain address form refuses a client\'s "Billing Address" (it needs its GSTIN)');
    $detail = (string) file_get_contents($root . '/views/detail.php');
    t_ok(strpos($detail, "_billing_location_form.php") !== false && strpos($detail, "\$blBack = 'addresses'") !== false,
         'C2 · the Addresses tab includes the SAME billing-address form, coming back to Addresses');
    $box = (string) file_get_contents($root . '/views/ops/_billing_locations.php');
    t_ok(strpos($box, "_billing_location_form.php") !== false && strpos($box, 'name="gstin"') === false,
         'C2 · the Billing addresses box uses that one form too (no second copy of its boxes)');
    t_eq(custid_back_url(['back' => 'addresses'], $rv), '/partner?id=' . $rv . '&tab=addresses', 'C2 · added from the Addresses tab, it comes back there');
    t_eq(custid_back_url(['back' => 'elsewhere'], $rv), '/partner?id=' . $rv . '#billing-locations', 'C2 · any other back value is still ignored');
    // An old GSTIN-less "Billing Address" stays, labelled; and is used for Main only when nothing else is on file.
    $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
        ->execute(['OB-' . $f['tag'], 'Old Billing Co ' . $f['tag'], 'Old Billing ' . $f['tag'], date('c')]);
    $ob = (int) $pdo->lastInsertId(); $made['bp'][] = $ob;
    $pdo->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city,state,pincode,is_primary) VALUES (?,'BILLING','Accounts','Ring Road','Surat','Gujarat','395002',0)")->execute([$ob]);
    $obA = (int) $pdo->lastInsertId();
    t_eq((int) (custid_head_office_address($ob)['id'] ?? 0), $obA, 'C2 · an old GSTIN-less Billing Address is still used for Main when it is the only address (nothing lost)');
    $pdo->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city,state,pincode,is_primary) VALUES (?,'REGISTERED','Regd','Station Road','Surat','Gujarat','395003',0)")->execute([$ob]);
    $obR = (int) $pdo->lastInsertId();
    t_eq((int) (custid_head_office_address($ob)['id'] ?? 0), $obR, 'C2 · … but a registered office comes first once one is on file');
    t_ok((int) ops_val("SELECT COUNT(*) FROM partner_addresses WHERE id=?", [$obA]) === 1, 'C2 · the old row is kept, not deleted or rewritten');

    // The Addresses and Registration tabs, rendered.
    $tabVars = function ($pid, $tab) {
        $pp = ops_one("SELECT * FROM business_partners WHERE id=?", [$pid]);
        return ['p' => $pp, 'tab' => $tab, 'viewLevel' => 'FULL', 'parent' => null, 'subsidiaries' => [],
                'addresses' => ops_all("SELECT * FROM partner_addresses WHERE partner_id=? ORDER BY id", [$pid]),
                'contacts' => [], 'registrations' => [], 'notes' => [], 'contracts' => [], 'cityList' => [], 'awaiting' => [],
                'relationships' => [], 'pos' => [], 'projects' => [], 'timeline' => []];
    };
    $ha = $render($root . '/views/detail.php', $tabVars($ob, 'addresses'));
    t_ok(strpos($ha, 'Billing address without its own GSTIN') !== false, 'C2 · the old row is labelled "Billing address without its own GSTIN"');
    t_ok(strpos($ha, 'Mark as head office') !== false, 'C1 · the Addresses tab offers "Mark as head office"');
    t_ok(preg_match('~name="address_type">.*?</select>~s', $ha, $sel) === 1 && strpos($sel[0], 'value="BILLING"') === false && strpos($sel[0], 'value="REGISTERED"') !== false,
         'C2 · the plain address form no longer offers "Billing Address" for a client (office, plant, site … stay)');
    $hr = $render($root . '/views/detail.php', $tabVars($rv, 'addresses'));
    t_ok(strpos($hr, '+ Add a billing address / GSTIN') !== false && strpos($hr, 'name="back" value="addresses"') !== false,
         'C2 · the Addresses tab shows "+ Add a billing address / GSTIN" — the same form, with its GSTIN box');
    t_ok(strpos($hr, 'billing address · GSTIN ' . $mah) !== false, 'C2 · a billing location on the Addresses tab is marked with its GSTIN and points to Billing addresses');
    $hg = $render($root . '/views/detail.php', $tabVars($rv, 'registration'));
    t_ok(strpos($hg, 'GSTINs on file') !== false && strpos($hg, $mah) !== false && strpos($hg, 'Ankleshwar works') !== false,
         'C4 · the Registration tab lists the GSTINs from Billing addresses (Main, Mumbai plant, Ankleshwar works …)');
    t_eq((int) ops_val("SELECT COUNT(*) FROM partner_registrations WHERE partner_id=?", [$rv]), 0, 'C4 · nothing is stored twice — it is read, not copied');
    $ho = $render($root . '/views/detail.php', $tabVars($rv, 'overview'));
    t_ok(preg_match('~<dt>Head office</dt><dd>([^<]*)~', $ho, $hm) === 1 && strpos($hm[1], 'Waghodia') !== false,
         'C1 · the Overview "Head office" is the marked head office, not a billing location (' . ($hm[1] ?? '') . ')');

    // ---- C5 — who already has this GSTIN ------------------------------------------------
    $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
        ->execute(['OT-' . $f['tag'], 'Orbit Traders ' . $f['tag'], 'Orbit ' . $f['tag'], date('c')]);
    $ot = (int) $pdo->lastInsertId(); $made['bp'][] = $ot;
    $otG = $G('24', 'AAKCO' . substr($PAN, 5, 4) . 'Q');
    custid_save($ot, custid_from_input(['gstin' => $otG], true));
    $w = custid_gstin_who($guj, 0);
    t_ok($w['kind'] === 'GSTIN' && strpos($w['text'], 'RV-' . $f['tag']) !== false && strpos($w['text'], 'Already belongs to') !== false,
         'C5 · pasting Riverside\'s GSTIN: "Already belongs to RV-… — Riverside Valves …" (' . $w['text'] . ')');
    $w = custid_gstin_who($mah, 0);
    t_eq($w['kind'], 'GSTIN', 'C5 · a billing location\'s GSTIN is found too (Mumbai plant)');
    t_eq(custid_gstin_who($guj, $rv)['kind'], 'NONE', 'C5 · on Riverside\'s own form its own GSTIN is no clash');
    $w = custid_gstin_who($G('29', $PAN), $ot);
    t_ok($w['kind'] === 'PAN' && strpos($w['text'], 'same company') !== false, 'C5 · a new state of a PAN on file: "Its PAN … belongs to RV-… — the same company"');
    t_eq(custid_gstin_who('24AAACC1206D1ZA', 0)['kind'], 'BAD', 'C5 · a GSTIN that fails its check is not looked up');
    t_eq(array_keys(array_diff_key(custid_gstin_who($guj, 0), ['kind' => 1, 'text' => 1])), ['code', 'name'], 'C5 · the answer carries only the code and the name — no more than the Save refusal');
    // Who may ask: the same people as on Save.
    $askers = [];
    foreach (['master', 'neha', 'kiran', 'rakesh', 'amit'] as $k) { bbv_as($f[$k]); $askers[$k] = custid_lookup_can(); }
    t_eq([$askers['master'], $askers['neha'], $askers['amit']], [true, true, false], 'C5 · Master Admin and a coordinator may ask; an inspector may not');
    $same = true;
    foreach (['kiran', 'rakesh', 'amit'] as $k) {
        bbv_as($f[$k]);
        $saveSees = is_master() || can('mod.clients.edit') || can('mod.vendors.edit') || (function_exists('is_coordinator_level') && is_coordinator_level())
                 || (function_exists('leads_can_edit') && leads_can_edit()) || (function_exists('opp_can_add_customer') && opp_can_add_customer())
                 || (function_exists('crm_quote_can_create') && crm_quote_can_create());
        if ($saveSees !== $askers[$k]) $same = false;
    }
    t_ok($same, 'C5 · for every fixture person, asking is allowed exactly when Save would have told them');
    bbv_nobody(); t_ok(!custid_lookup_can(), 'C5 · nobody signed in: refused');
    bbv_as($f['master']);
    t_ok(preg_match("~route === 'gstin-who'.{0,400}\\\$method !== 'POST' \|\| !function_exists\('custid_lookup_can'\) \|\| !custid_lookup_can\(\).{0,200}403~s", $idx) === 1,
         'C5 · the lookup route is POST-only (the page token is checked for every POST) and refuses anyone else with 403');
    $js = (string) file_get_contents($root . '/assets/js/app.js');
    t_ok(strpos($js, "window.exaactGstinWho") !== false && strpos($js, "fetch('/gstin-who'") !== false && strpos($js, "_csrf") !== false,
         'C5 · the browser asks /gstin-who with the page token');
    t_ok(strpos($js, "'State: ' + states[v.substr(0, 2)] + ' (from the GSTIN)'") !== false, 'C3 · the browser shows "State: … (from the GSTIN)"');
    $cid = (string) file_get_contents($root . '/views/ops/_custid_fields.php');
    t_ok(strpos($cid, 'class="cid-who"') !== false && strpos($cid, 'window.exaactGstinWho(v, exclude') !== false,
         'C5 · the client form, lead conversion, deal and quotation GSTIN box asks too');

    // ---- Phone: the new pieces are plain .inline-add forms (one column under 560px) -------
    $form = (string) file_get_contents($root . '/views/ops/_billing_location_form.php');
    t_ok(strpos($form, 'class="inline-add"') !== false && strpos($box, 'class="inline-add"') !== false, 'phone · the add and change forms are .inline-add (one column, full-width button on a phone)');
} finally {
    bbv_as($f['master']);
    if ($made['bp']) {
        $in = implode(',', array_map('intval', $made['bp']));
        $pdo->exec("DELETE FROM partner_addresses WHERE partner_id IN ($in)");
        $pdo->exec("DELETE FROM business_partners WHERE id IN ($in)");
    }
    bbv_cleanup($f);
}
