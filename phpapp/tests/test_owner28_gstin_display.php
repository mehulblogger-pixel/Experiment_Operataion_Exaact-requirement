<?php
//  Owner, 28 Nov 2026 — GSTIN display fix: Overview shows each GSTIN once, Registration has one row
//  per GSTIN with its offices, every Addresses card shows State and GSTIN. All read custid_locations().
t_section('GSTINs shown the same way on Overview, Registration and Addresses');
require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture(); $pdo = db(); $made = [];
try {
    bbv_as($f['master']); custid_migrate();
    $G = function ($st, $pan) { $x = $st . $pan . '1Z'; return $x . commercial_gstin_check_char($x); };
    $pan = 'AAKCQ' . substr(str_pad((string) (crc32($f['tag']) % 10000), 4, '0', STR_PAD_LEFT), 0, 4) . 'M';
    $guj = $G('24', $pan); $mah = $G('27', $pan);
    $pdo->prepare("INSERT INTO business_partners (code,legal_name,display_name,is_client,is_vendor,status,created_at) VALUES (?,?,?,1,0,'ACTIVE',?)")
        ->execute(['GD-' . $f['tag'], 'Gstin Display Co ' . $f['tag'], 'Gstin Display ' . $f['tag'], date('c')]);
    $id = (int) $pdo->lastInsertId(); $made[] = $id;
    custid_save($id, custid_from_input(['gstin' => $guj], true));
    $pdo->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city,state,pincode,is_primary) VALUES (?,'REGISTERED','HO','Plot 12','Vadodara','Gujarat','390010',1)")->execute([$id]);
    foreach ([['gstin' => $mah, 'label' => 'Mumbai plant', 'line1' => 'MIDC Andheri', 'city' => 'Mumbai', 'pincode' => '400093'],
              ['gstin' => $guj, 'label' => 'Ankleshwar works', 'line1' => 'Plot 4401', 'city' => 'Ankleshwar', 'pincode' => '393002'],
              ['gstin' => $guj, 'label' => 'Dahej site', 'line1' => 'Plot 7', 'city' => 'Dahej', 'pincode' => '392130']] as $b) custid_add_location($id, $b);
    $gr = custid_gstin_groups($id);
    t_eq(array_keys($gr), [$guj, $mah], 'two GSTINs, each once, main first');
    t_eq(count($gr[$guj]['offices']), 3, 'the main GSTIN lists its 3 offices (Main, Ankleshwar works, Dahej site)');
    $render = function ($tab) use ($id) {
        $vars = ['p' => ops_one("SELECT * FROM business_partners WHERE id=?", [$id]), 'tab' => $tab, 'viewLevel' => 'FULL', 'parent' => null, 'subsidiaries' => [],
                 'addresses' => ops_all("SELECT * FROM partner_addresses WHERE partner_id=? ORDER BY id", [$id]),
                 'contacts' => [], 'registrations' => [], 'notes' => [], 'contracts' => [], 'cityList' => [], 'awaiting' => [],
                 'relationships' => [], 'pos' => [], 'projects' => [], 'timeline' => []];
        extract($vars); ob_start(); try { include dirname(__DIR__) . '/views/detail.php'; } catch (Throwable $e) { echo 'RENDER-ERROR ' . $e->getMessage(); }
        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(preg_replace('~<script\b.*?</script>~s', '', (string) ob_get_clean()))));
    };
    $ov = $render('overview'); $rg = $render('registration'); $ad = $render('addresses');
    t_ok(strpos($ov, 'GSTINs') !== false && strpos($ov, $guj . ' (Gujarat, main)') !== false && strpos($ov, $mah . ' (Maharashtra)') !== false, 'Overview: "GSTINs" lists each GSTIN once with its state');
    t_eq(substr_count($rg, $guj), 1, 'Registration: the main GSTIN appears once (not once per office)');
    t_ok(strpos($rg, '3 — Main — Vadodara, Ankleshwar works — Ankleshwar, Dahej site — Dahej') !== false, 'Registration: its offices are listed on that one row');
    t_ok(strpos($ad, 'State: Gujarat · GSTIN: ' . $guj . ' (main)') !== false, 'Addresses: the head office shows the main GSTIN and state');
    t_ok(strpos($ad, 'State: Maharashtra · GSTIN: ' . $mah) !== false, 'Addresses: the Mumbai plant shows its own GSTIN and state');
    $pdo->prepare("INSERT INTO partner_addresses (partner_id,address_type,label,line1,city,state,pincode,is_primary) VALUES (?,'BILLING','Old billing','Ring Road','Surat','Gujarat','395002',0)")->execute([$id]);
    t_ok(strpos($render('addresses'), 'No GSTIN — add it') !== false, 'Addresses: an old billing address with no GSTIN says "No GSTIN — add it"');
} finally {
    if ($made) { $in = implode(',', array_map('intval', $made)); $pdo->exec("DELETE FROM partner_addresses WHERE partner_id IN ($in)"); $pdo->exec("DELETE FROM business_partners WHERE id IN ($in)"); }
    bbv_cleanup($f);
}
