<?php
// Owner, 28 Nov 2026 — "1. If in quick lead when the name is typed and it matched to any of
// our existing customer it shall show while typing itself that this customer exist, select it.
// Else is ok. 2. When the customer is matched or selected from the list the lead, deal,
// enquiry directly attached to that customer. 3. Ok" (a ✓ with the customer's code).
//
//   K1  partner_name_matches() / partner_resolve_typed(): customers on file by name — exact
//       (Pvt Ltd / Ltd / punctuation left out), close (the typed name begins it); the person's
//       own office scope; active clients only. The read-only /customer-match lookup.
//   K2  "as you type" on the quick lead, the full lead form, the deal and the enquiry form.
//   K3  attach on Save: picked → that customer; one exact match → attached, with a note and a
//       History line; two or more → asked once, on the form; "It is a new company" keeps the
//       typed name. The quick lead's duplicate list leaves the chosen customer out.
//   K4  the ✓ CODE · name chip, with "change".
t_section('Owner 28 Nov 2026 — customer match as you type, attach on save, the ✓ chip (K1–K4)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$root = dirname(__DIR__);
$made = ['l' => [], 'o' => [], 'i' => [], 'p' => [], 'u' => [], 'q' => [], 'c' => []];
putenv('BBV_RAW=1');
$html = fn($r) => (string) ($r['html'] ?? $r['out'] ?? '');
try {
    bbv_as($f['master']);
    leads_migrate(); opp_migrate(); custid_migrate();
    if (function_exists('crm_migrate')) crm_migrate();
    if (function_exists('act_migrate')) act_migrate();
    $tag = $f['tag'];
    $mkClient = function ($name, $branch, $status = 'ACTIVE', $client = 1) use ($pdo, $tag, &$made) {
        $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at,home_branch_id) VALUES (?,?,?,?,0,?,?,?)")
            ->execute(['K' . substr(md5($name . microtime(true)), 0, 6), $name, $name, $client, $status, date('c'), $branch]);
        $id = (int) $pdo->lastInsertId(); $made['p'][] = $id; return $id;
    };
    $tp   = $mkClient("Testline Pumps Pvt Ltd $tag", $f['mum']);
    $tw1  = $mkClient("Twinco Industries $tag", $f['mum']);
    $tw2  = $mkClient("Twinco Industries Pvt Ltd $tag", $f['mum']);
    $old  = $mkClient("Oldline Valves $tag", $f['mum'], 'INACTIVE');
    $ven  = $mkClient("Vendorline Supplies $tag", $f['mum'], 'ACTIVE', 0);
    $ahmC = $mkClient("Scopecheck Forgings $tag", $f['ahm']);
    $pdo->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city,state,pincode,is_primary) VALUES (?,'REGISTERED','HO','Plot 12','Vadodara','Gujarat','390010',1)")->execute([$tp]);
    $tpCode = (string) ops_val("SELECT code FROM business_partners WHERE id=?", [$tp]);
    $mkUser = function ($key, $role, $office) use ($pdo, $tag, &$made) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices) VALUES (?,?,?,?,1,?,'')")
            ->execute([$tag . '_' . $key, ucfirst($key), 'T', $role, $office]);
        $id = (int) $pdo->lastInsertId(); $made['u'][] = $id; return $id;
    };
    $bdm = $mkUser('bdmk', 'BUSINESS_DEV_MANAGER', $f['mum']);

    // ---------------------------------------------------------------- K1 · the helper
    t_section('K1 — customers on file by name');
    bbv_as($bdm);
    $m = partner_name_matches("testline pumps $tag");
    t_eq(count($m['exact']), 1, 'K1 · "testline pumps" (no Pvt Ltd, small letters) is exactly one customer');
    t_eq((int) ($m['exact'][0]['id'] ?? 0), $tp, 'K1 · … Testline Pumps Pvt Ltd');
    t_eq((string) ($m['exact'][0]['code'] ?? ''), $tpCode, 'K1 · the match carries the customer code');
    t_eq((string) ($m['exact'][0]['city'] ?? ''), 'Vadodara', 'K1 · … and its town (the head office)');
    $c = partner_name_matches("Testline");
    t_ok(in_array($tp, array_column($c['close'], 'id'), true), 'K1 · typing "Testline" already offers it (close match while typing)');
    t_eq(count(partner_name_matches('Te')['exact']) + count(partner_name_matches('Te')['close']), 0, 'K1 · fewer than 3 letters: nothing is looked up');
    t_eq(count(partner_name_matches("Twinco Industries $tag")['exact']), 2, 'K1 · two customers with the same name are both found');
    $all = array_merge(partner_name_matches("Oldline Valves $tag")['exact'], partner_name_matches("Vendorline Supplies $tag")['exact']);
    t_eq(count($all), 0, 'K1 · an inactive client and a vendor-only record are not offered');
    t_eq(count(partner_name_matches("Scopecheck Forgings $tag")['exact']), 0, 'K1 · a Mumbai person is not shown an Ahmedabad customer (own office scope)');
    bbv_as($f['master']);
    t_eq(count(partner_name_matches("Scopecheck Forgings $tag")['exact']), 1, 'K1 · … the Master Admin is');
    bbv_as($bdm);
    $r = partner_resolve_typed("Testline Pumps $tag");
    t_eq((int) ($r['linked']['id'] ?? 0), $tp, 'K1 · one exact match → attach to it');
    $r = partner_resolve_typed("Twinco Industries $tag");
    t_eq(count($r['many'] ?? []), 2, 'K1 · two exact matches → ask which');
    t_eq(partner_resolve_typed("Twinco Industries $tag", 0, true), [], 'K1 · "It is a new company" → keep the typed name');
    $r = partner_resolve_typed("Twinco Industries $tag", $tw2);
    t_ok((int) ($r['linked']['id'] ?? 0) === $tw2 && !empty($r['picked']), 'K1 · "Use this customer" → that one, as picked');
    t_eq(partner_resolve_typed("Brand New Name $tag"), [], 'K1 · no match → nothing changes ("Else is ok")');
    t_ok(partner_match_by_id($ahmC) === null, 'K1 · a customer outside the person\'s scope cannot be picked by id');
    t_ok(partner_match_can(), 'K1 · a Business Development Manager may ask (they add leads)');
    bbv_as($f['rakesh']); t_ok(!partner_match_can(), 'K1 · Finance (no lead, deal or enquiry to add) may not');
    bbv_nobody(); t_ok(!partner_match_can(), 'K1 · nobody signed in: refused');
    $idx = (string) file_get_contents($root . '/index.php');
    t_ok(preg_match("~route === 'customer-match'.{0,500}\\\$method !== 'POST' \|\| !function_exists\('partner_match_can'\) \|\| !partner_match_can\(\).{0,200}403~s", $idx) === 1,
         'K1 · /customer-match is POST-only (the page token is checked for every POST) and refuses anyone else with 403');
    t_ok(preg_match("~route === 'customer-match'.{0,1200}array_slice\(\\\$rows, 0, 5\)~s", $idx) === 1, 'K1 · it returns at most 5 customers');

    // ---------------------------------------------------------------- K2 / K4 · the screens
    t_section('K2 / K4 — as you type, and the ✓');
    $js = (string) file_get_contents($root . '/assets/js/app.js');
    t_ok(strpos($js, "fetch('/customer-match'") !== false && strpos($js, '_csrf') !== false, 'K2 · the browser asks /customer-match with the page token');
    t_ok(strpos($js, 'is already a customer — ') !== false && strpos($js, "'Use this customer'") !== false, 'K2 · it says "… is already a customer — Use this customer"');
    t_ok(strpos($js, "'✓ ' + (m.code ? m.code + ' · ' : '') + m.name") !== false && strpos($js, "'change'") !== false, 'K4 · the chosen one shows as "✓ CODE · name" with "change"');
    t_ok(strpos($js, 'v.length < 3') !== false, 'K2 · it waits for 3 letters');
    foreach (['views/ops/sx_lead.php' => 'quick lead', 'views/ops/lead_form.php' => 'New lead form', 'views/ops/opportunity_form.php' => 'New deal form', 'views/ops/crm/inquiry_form.php' => 'enquiry form'] as $file => $w) {
        $src = (string) file_get_contents($root . '/' . $file);
        t_ok(strpos($src, 'data-cust-match=') !== false, "K2 · the $w offers the customer as you type");
        t_ok(strpos($src, '_customer_picks.php') !== false, "K3 · the $w can ask once which customer is meant");
    }
    foreach (['views/ops/lead_form.php', 'views/ops/opportunity_form.php', 'views/ops/crm/inquiry_form.php'] as $file)
        t_ok(strpos((string) file_get_contents($root . '/' . $file), 'data-cust-chip') !== false, 'K4 · ' . basename($file) . ' shows the ✓ of the customer picked in its list');
    $css = (string) file_get_contents($root . '/assets/css/app.css');
    t_ok(strpos($css, '.cm-hint') !== false && strpos($css, '@media (max-width:560px){ .cm-hint .cm-row .btn{width:100%} }') !== false, 'K2 · on a phone the "Use this customer" button fills the width');

    // ---------------------------------------------------------------- K3 · attach on save
    t_section('K3 — the quick lead');
    $ph = function () { return '98' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT); };
    $leadBy = fn($pid) => ops_all("SELECT * FROM leads WHERE partner_id=? ORDER BY id", [$pid]) ?: [];
    $r = bbv_route('sales-lead', 'POST', $bdm, 0, ['company_name' => "testline pumps $tag", 'contact_phone' => $ph(), 'source' => 'REFERRAL']);
    $ls = $leadBy($tp); foreach ($ls as $l) $made['l'][] = (int) $l['id'];
    t_eq(count($ls), 1, 'K3 · a quick lead typed "testline pumps" is attached to Testline Pumps Pvt Ltd');
    t_eq((string) ($ls[0]['company_name'] ?? ''), "Testline Pumps Pvt Ltd $tag", 'K3 · … under the customer\'s own name');
    t_ok((bool) array_filter($r['flash'] ?? [], fn($x) => strpos($x, 'Linked to ' . $tpCode) !== false), 'K3 · the message says "Linked to ' . $tpCode . ' … (same name)"');
    t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='LEAD' AND entity_id=? AND subject LIKE ?", [(int) ($ls[0]['id'] ?? 0), 'Linked to %']) === 1, 'K3 · and its History records the link');
    $n0 = (int) ops_val("SELECT COUNT(*) FROM leads WHERE company_name LIKE ?", ["%Twinco%$tag%"]);
    $r = bbv_route('sales-lead', 'POST', $bdm, 0, ['company_name' => "Twinco Industries $tag", 'contact_phone' => $ph(), 'source' => 'REFERRAL']);
    t_eq((int) ops_val("SELECT COUNT(*) FROM leads WHERE company_name LIKE ?", ["%Twinco%$tag%"]), $n0, 'K3 · two customers called Twinco: nothing saved yet');
    t_ok(strpos($html($r), 'Use this customer') !== false && strpos($html($r), 'It is a new company') !== false, 'K3 · the form asks which one, or "It is a new company"');
    $r = bbv_route('sales-lead', 'POST', $bdm, 0, ['company_name' => "Twinco Industries $tag", 'use_partner' => (string) $tw2, 'contact_phone' => $ph(), 'source' => 'REFERRAL']);
    $ls = $leadBy($tw2); foreach ($ls as $l) $made['l'][] = (int) $l['id'];
    t_eq(count($ls), 1, 'K3 · "Use this customer" saves the lead on the one chosen');
    $r = bbv_route('sales-lead', 'POST', $bdm, 0, ['company_name' => "Twinco Industries $tag", 'new_company' => '1', 'confirm' => '1', 'contact_phone' => $ph(), 'source' => 'REFERRAL']);
    $nl = ops_all("SELECT * FROM leads WHERE company_name=? AND partner_id IS NULL", ["Twinco Industries $tag"]) ?: [];
    foreach ($nl as $l) $made['l'][] = (int) $l['id'];
    t_eq(count($nl), 1, 'K3 · "It is a new company" keeps the typed name, with no customer');
    //  (d) The chosen customer's own contact mobile is no "Save anyway" duplicate.
    $mob = $ph();
    $pdo->prepare("INSERT INTO partner_contacts (partner_id,name,mobile,is_primary) VALUES (?,?,?,1)")->execute([$tp, 'Rakesh Mehta', $mob]);
    $pdo->prepare("UPDATE partner_contacts SET partner_id=? WHERE mobile=?")->execute([$tw1, $mob]);   // a customer with no open lead yet
    $r = bbv_route('sales-lead', 'POST', $bdm, 0, ['company_name' => "Twinco Industries $tag", 'partner_id' => (string) $tw1, 'contact_phone' => $mob, 'source' => 'REFERRAL']);
    $ls = $leadBy($tw1); foreach ($ls as $l) $made['l'][] = (int) $l['id'];
    t_eq(count($ls), 1, 'K3 · a quick lead for the picked customer, with its own contact\'s mobile and a same-named other customer on file, saves without "Save anyway"');

    t_section('K3 — the full lead form');
    //  (Testline Pumps already has an open lead, so the form's own "an open lead for this client"
    //  notice asks for "Save anyway" — that notice is kept; confirm it here.)
    $r = bbv_route('lead-new', 'POST', $bdm, 0, ['company_name' => "Testline Pumps $tag", 'contact_phone' => $ph(), 'office_id' => (string) $f['mum'], 'confirm' => '1']);
    $ls = $leadBy($tp); foreach ($ls as $l) $made['l'][] = (int) $l['id'];
    t_eq(count($ls), 2, 'K3 · a lead typed "Testline Pumps" on the full form is attached to the customer');
    t_ok((bool) array_filter($r['flash'] ?? [], fn($x) => strpos($x, 'Linked to ' . $tpCode) !== false), 'K3 · … and says so');
    $n0 = (int) ops_val("SELECT COUNT(*) FROM leads WHERE company_name LIKE ?", ["%Twinco%$tag%"]);
    $r = bbv_route('lead-new', 'POST', $bdm, 0, ['company_name' => "Twinco Industries $tag", 'contact_phone' => $ph(), 'office_id' => (string) $f['mum']]);
    t_eq((int) ops_val("SELECT COUNT(*) FROM leads WHERE company_name LIKE ?", ["%Twinco%$tag%"]), $n0, 'K3 · two customers with the name: the full form asks first, nothing saved');
    t_ok((bool) array_filter($r['flash'] ?? [], fn($x) => strpos($x, 'More than one customer on file') !== false), 'K3 · … and says why');

    t_section('K3 — the deal');
    $r = bbv_route('opportunity-new', 'POST', $bdm, 0, ['name' => "K deal $tag", 'partner_name' => "testline pumps $tag", 'office_id' => (string) $f['mum']]);
    $o = ops_one("SELECT * FROM opportunities WHERE name=?", ["K deal $tag"]);
    if ($o) $made['o'][] = (int) $o['id'];
    t_eq((int) ($o['partner_id'] ?? 0), $tp, 'K3 · a deal typed "testline pumps" is attached to Testline Pumps Pvt Ltd');
    t_ok((bool) array_filter($r['flash'] ?? [], fn($x) => strpos($x, 'Linked to ' . $tpCode) !== false), 'K3 · … and says so');
    t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='OPPORTUNITY' AND entity_id=? AND subject LIKE ?", [(int) ($o['id'] ?? 0), 'Linked to %']) === 1, 'K3 · its History records the link');
    if ($o) {
        bbv_as($bdm);
        $q = opp_create_quote((int) $o['id']);
        if (!empty($q['id'])) $made['q'][] = (int) $q['id'];
        t_eq((int) ops_val("SELECT client_id FROM quotations WHERE id=?", [(int) ($q['id'] ?? 0)]), $tp, 'K3 · its quotation carries the customer — acceptance never asks "is X the same company?"');
    }
    $r = bbv_route('opportunity-new', 'POST', $bdm, 0, ['name' => "K deal two $tag", 'partner_name' => "Twinco Industries $tag", 'office_id' => (string) $f['mum']]);
    t_eq((int) ops_val("SELECT COUNT(*) FROM opportunities WHERE name=?", ["K deal two $tag"]), 0, 'K3 · two customers with the name: the deal is not opened yet');
    t_ok(strpos($html($r), 'Use this customer') !== false, 'K3 · the deal form asks which one');
    $r = bbv_route('opportunity-new', 'POST', $bdm, 0, ['name' => "K deal two $tag", 'partner_name' => "Twinco Industries $tag", 'use_partner' => (string) $tw1, 'office_id' => (string) $f['mum']]);
    $o2 = ops_one("SELECT * FROM opportunities WHERE name=?", ["K deal two $tag"]);
    if ($o2) $made['o'][] = (int) $o2['id'];
    t_eq((int) ($o2['partner_id'] ?? 0), $tw1, 'K3 · "Use this customer" opens it on the one chosen');
    $r = bbv_route('opportunity-new', 'POST', $bdm, 0, ['name' => "K deal pick $tag", 'partner_id' => (string) $tw2, 'office_id' => (string) $f['mum']]);
    $o3 = ops_one("SELECT * FROM opportunities WHERE name=?", ["K deal pick $tag"]);
    if ($o3) $made['o'][] = (int) $o3['id'];
    t_eq((int) ($o3['partner_id'] ?? 0), $tw2, 'K3 · a customer picked from the list is the deal\'s customer, as before');

    t_section('K3 — the enquiry');
    $sbu = (string) array_key_first(lk_options_or('sbu', OPS_SBUS));
    $subj = "K enquiry $tag";
    $post = ['client_name' => "Testline Pumps $tag", 'contact_name' => 'Ravi', 'contact_mobile' => $ph(), 'sbu' => $sbu, 'subject' => $subj, 'source' => 'EMAIL'];
    $r = bbv_route('inquiry-new', 'POST', $bdm, 0, $post);
    $iq = ops_one("SELECT * FROM crm_inquiries WHERE subject=?", [$subj]);
    if ($iq) $made['i'][] = (int) $iq['id'];
    t_eq((int) ($iq['client_id'] ?? 0), $tp, 'K3 · an enquiry typed "Testline Pumps" is attached to the customer');
    $subj2 = "K enquiry two $tag";
    $r = bbv_route('inquiry-new', 'POST', $bdm, 0, ['client_name' => "Twinco Industries $tag", 'subject' => $subj2] + $post);
    t_eq((int) ops_val("SELECT COUNT(*) FROM crm_inquiries WHERE subject=?", [$subj2]), 0, 'K3 · two customers with the name: the enquiry is not saved yet');
    t_ok(strpos($html($r), 'Use this customer') !== false, 'K3 · the enquiry form asks which one');
    $r = bbv_route('inquiry-new', 'POST', $bdm, 0, ['client_name' => "Twinco Industries $tag", 'subject' => $subj2, 'new_company' => '1'] + $post);
    $iq2 = ops_one("SELECT * FROM crm_inquiries WHERE subject=?", [$subj2]);
    if ($iq2) $made['i'][] = (int) $iq2['id'];
    t_ok($iq2 && empty($iq2['client_id']), 'K3 · "It is a new company" saves it with the typed name only');
} finally {
    putenv('BBV_RAW');
    $pdo = db();
    $in = fn($a) => implode(',', array_map('intval', $a ?: [0]));
    $pdo->exec("DELETE FROM quotations WHERE id IN (" . $in($made['q']) . ")");
    $pdo->exec("DELETE FROM crm_inquiries WHERE id IN (" . $in($made['i']) . ")");
    $pdo->exec("DELETE FROM opportunities WHERE id IN (" . $in($made['o']) . ")");
    $pdo->exec("DELETE FROM leads WHERE id IN (" . $in($made['l']) . ")");
    $pdo->exec("DELETE FROM partner_contacts WHERE partner_id IN (" . $in($made['p']) . ")");
    $pdo->exec("DELETE FROM partner_addresses WHERE partner_id IN (" . $in($made['p']) . ")");
    $pdo->exec("DELETE FROM business_partners WHERE id IN (" . $in($made['p']) . ")");
    $pdo->exec("DELETE FROM users WHERE id IN (" . $in($made['u']) . ")");
    bbv_cleanup($f);
}
