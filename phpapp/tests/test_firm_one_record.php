<?php
// Owner, 18 Nov 2026 — "we already have customer and vendor registration form in
// the operations and directory … make it unified and centralized so nothing is
// duplicate or multiple entries"; "the firm shall be universal. Can be opened from
// sales as well as from directory … added as both client and vendors".
//
//   1. ONE numbering rule for every door (form, lead → customer, deal → customer,
//      quote for a new name, branch company, import, Connect registration), and
//      the running number never repeats (it used to follow the alphabetically
//      last code, so TB Group Parent and TB Group Child both got …-00003).
//   2. The old cut-down quick-add box no longer creates a client or vendor.
//   3. Contacts carry a buying role (an editable master list).
//   4. Sales opens the SAME client register with Sales columns — no second list.
t_section('One firm record — one numbering rule, one form, buying role, Sales view of the one client list');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$made = []; $leads = [];
$fmt = '/^[A-Z0-9]{1,4}-\d\d-[A-Z0-9]{1,5}-\d{5}$/';
try {
    bbv_as($f['master']);
    $yy = substr(date('Y'), 2, 2);
    $bc = branch_abbr(null);
    $ins = function ($code, $name, $client = 1, $vendor = 0) use (&$made) {
        db()->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,?,?,'ACTIVE',?)")
            ->execute([$code, $name, $name, $client, $vendor, date('c')]);
        return $made[] = (int) db()->lastInsertId();
    };
    // ---- 1. The running number is the highest one, not the alphabetically last --------
    $max = 0;
    foreach (ops_all("SELECT code FROM business_partners WHERE code LIKE ?", ["$bc-$yy-%"]) ?: [] as $r)
        if (preg_match('/-(\d{5})$/', $r['code'], $m)) $max = max($max, (int) $m[1]);
    $ins(sprintf('%s-%s-AAAAA-%05d', $bc, $yy, $max + 40), 'Fx Alpha ' . uniqid());   // highest number, first alphabetically
    $ins(sprintf('%s-%s-ZZZZZ-%05d', $bc, $yy, $max + 2), 'Fx Zulu ' . uniqid());     // lower number, last alphabetically
    $c = gen_partner_code(null, 'Fx Tata Power');
    t_eq((int) substr($c, -5), $max + 41, 'number · the next firm gets the highest number + 1 (' . $c . '), not the alphabetically last + 1');
    t_ok(preg_match($fmt, $c) === 1, 'number · office-year-name-00000 shape');

    // ---- 1b. Every automatic door uses it --------------------------------------------
    leads_migrate();
    $L = lead_create(['company_name' => 'Fx Lead Firm ' . uniqid(), 'contact_name' => 'Fx Person', 'contact_phone' => '9825099991', 'source' => 'REFERRAL']);
    $leads[] = (int) $L['id'];
    //  21 Nov 2026 (D1–D4): a new customer carries its tax identity — here a recorded exception.
    $r = lead_convert((int) $L['id'], ['tax_status' => 'UNREGISTERED', 'pan' => 'AABPF' . sprintf('%04d', random_int(0, 9999)) . 'F', 'tax_reason' => 'test — below the GST threshold']);
    t_ok(!empty($r['ok']) && !empty($r['partner_id']), 'lead → customer · converts' . (!empty($r['err']) ? ' — ' . $r['err'] : ''));
    if (!empty($r['partner_id'])) { $made[] = (int) $r['partner_id'];
        t_ok(preg_match($fmt, (string) ops_val("SELECT code FROM business_partners WHERE id=?", [$r['partner_id']])) === 1, 'lead → customer · numbered by the one rule (was C-NAME-0042)'); }
    $pid = crm_register_client_for_quote(['client_name' => 'Fx Quote Firm ' . uniqid()]);
    if ($pid) $made[] = (int) $pid;
    t_ok($pid && preg_match($fmt, (string) ops_val("SELECT code FROM business_partners WHERE id=?", [$pid])) === 1, 'quote for a new name · numbered by the one rule (was GEN-NAME-0001)');
    t_ok(preg_match($fmt, partner_next_code('Fx Import Firm')) === 1, 'Excel import · numbered by the one rule (was GEN-NAME-0001)');
    $src = file_get_contents(dirname(__DIR__) . '/lib/customer360.php') . file_get_contents(dirname(__DIR__) . '/lib/connect_org.php') . file_get_contents(dirname(__DIR__) . '/lib/opportunities.php');
    t_ok(substr_count($src, 'gen_partner_code(') >= 3, 'branch company, Connect registration and deal → customer call the one rule');
    t_ok(strpos($src, "str_pad((string)((int)ops_val(\"SELECT COUNT(*) FROM business_partners\")") === false, 'no door numbers a firm by counting rows any more');

    // ---- 1c. A firm that is already on file is found, not re-entered ------------------
    $dupName = 'Fx Castings ' . uniqid();
    $dId = $ins(gen_partner_code(null, $dupName), $dupName, 0, 1);
    $hit = find_duplicate_partner($dupName, '', '', '', 0);
    t_eq((int) ($hit['row']['id'] ?? 0), $dId, 'duplicate · the same name is found before a second record is made');

    // ---- 2. The old quick-add box no longer creates a firm --------------------------
    bbv_as($f['neha']);   // a coordinator could use it
    $before = (int) ops_val("SELECT COUNT(*) FROM business_partners");
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_GET['kind'] = 'client';
    $_POST = ['name' => 'Fx QuickAdd ' . uniqid(), 'gstin' => '', 'pan' => 'ABCDE1234F', 'line1' => 'x', 'pname' => 'p', 'pmob' => '9825000000', 'pmail' => 'p@x.example'];
    ob_start(); ops_quick_add(); $j = json_decode(ob_get_clean(), true);
    $_POST = []; unset($_GET['kind']); $_SERVER['REQUEST_METHOD'] = 'GET';
    t_ok(empty($j['ok']) && strpos((string) ($j['url'] ?? ''), '/partner-new') === 0, 'quick-add · refused, and points to the one registration form');
    t_eq((int) ops_val("SELECT COUNT(*) FROM business_partners"), $before, 'quick-add · no firm was created');
    bbv_as($f['master']);

    // ---- 3. Buying role on contacts --------------------------------------------------
    t_ok(column_exists('partner_contacts', 'buying_role'), 'contacts · buying_role column exists');
    t_ok(isset(CONTACT_BUYING_ROLES['DECISION_MAKER']), 'contacts · starting list includes Decision maker');
    $idx = file_get_contents(dirname(__DIR__) . '/index.php');
    t_ok(preg_match("/'contact' => \['partner_contacts', \[[^\]]*'buying_role'/", $idx) === 1, 'contacts · the one contact form saves the buying role');
    $view = file_get_contents(dirname(__DIR__) . '/views/detail.php');
    t_ok(strpos($view, 'name="buying_role"') !== false && strpos($view, "lk_options_or('contact_buying_role'") !== false, 'contacts · the form offers the editable Buying role list');
    t_ok(strpos($view, 'birthday') === false && strpos($view, 'anniversary') === false, 'contacts · no birthday / anniversary (owner: not professional)');

    // ---- 4. Sales view of the ONE client register --------------------------------------
    opp_migrate();
    $cId = $ins(gen_partner_code(null, 'Fx Sales Client'), 'Fx Sales Client ' . uniqid());
    db()->prepare("INSERT INTO partner_contacts (partner_id,name,mobile,is_primary) VALUES (?,?,?,1)")->execute([$cId, 'Fx Buyer', '9825012345']);
    $o = opp_create(['name' => 'Fx deal', 'partner_name' => 'Fx', 'value' => 500000, 'pipeline_id' => pipeline_default('OPPORTUNITY')['id']]);
    db()->prepare("UPDATE opportunities SET partner_id=?, office_id=NULL WHERE id=?")->execute([$cId, $o['id']]);
    $cols = sx_client_sales_cols([$cId]);
    t_eq([$cols[$cId]['deals'], $cols[$cId]['pipe'], $cols[$cId]['contact']], [1, 500000.0, 'Fx Buyer'], 'Sales view · open deals, pipeline and primary contact read from the existing records');
    t_ok($cols[$cId]['owed'] === null || $cols[$cId]['owed'] === 0.0, 'Sales view · amount owed is the ledger\'s outstanding figure (0 here), never a stray 1');
    db()->prepare("DELETE FROM opportunities WHERE id=?")->execute([$o['id']]);
    $lst = file_get_contents(dirname(__DIR__) . '/views/list.php');
    t_ok(strpos($lst, 'Sales view') !== false && strpos($lst, '/partner?id=') !== false, 'Sales view · is a switch on the same list, every name opens the one record');
    t_ok(!file_exists(dirname(__DIR__) . '/views/ops/sx_customers.php'), 'Sales view · no second customer list was built');
} finally {
    foreach ($leads as $id) db()->prepare("DELETE FROM leads WHERE id=?")->execute([$id]);
    if ($made) { $in = implode(',', array_map('intval', $made));
        db()->exec("DELETE FROM partner_contacts WHERE partner_id IN ($in)");
        db()->exec("DELETE FROM partner_addresses WHERE partner_id IN ($in)");
        try { db()->exec("DELETE FROM crm_inquiries WHERE client_id IN ($in)"); } catch (Throwable $e) {}
        db()->exec("DELETE FROM business_partners WHERE id IN ($in)"); }
    bbv_cleanup($f);
}
t_ok(true, 'the test removed its own rows');
