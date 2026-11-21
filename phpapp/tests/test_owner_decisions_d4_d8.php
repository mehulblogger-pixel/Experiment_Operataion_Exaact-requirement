<?php
// Owner decisions D4, D5, D7, D8 (21 Nov 2026, findings plan "as recommended").
//   D4 one mobile rule and the word "Mobile"; D5 a new contact on an inquiry joins the
//   customer's Contacts; D7 the lead stage menu's "Move back"; D8 the one KPI card.
t_section('Owner decisions D4, D5, D7, D8 — mobile, inquiry contact, move back, KPI card');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$made = ['u' => [], 'l' => [], 'bp' => [], 'inq' => []];
$flashHas = fn($r, $needle) => (bool) array_filter((array) ($r['flash'] ?? []), fn($x) => stripos((string) $x, $needle) !== false);
$raw = function ($route, $uid, array $get = []) {
    $root = dirname(__DIR__);
    $env = (getenv('DB_DRIVER') === 'mysql') ? 'DB_DRIVER=mysql' : 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'));
    $cmd = 'BBV_RAW=1 ' . $env . ' php ' . escapeshellarg($root . '/tests/_bbv_worker.php') . ' ' . escapeshellarg($route) . ' GET ' . (int) $uid
         . ' 0 ' . escapeshellarg('{}') . ' ' . escapeshellarg(json_encode((object) $get)) . ' 2>/dev/null';
    foreach (array_reverse(explode("\n", trim((string) shell_exec($cmd)))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['html' => ''];
};
try {
    bbv_as($f['master']); leads_migrate(); opp_migrate();
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id) VALUES (?,?,?,?,1,?)")
        ->execute([$f['tag'] . '_bdm', 'Bdm', 'T', 'BUSINESS_DEV_MANAGER', $f['mum']]);
    $made['u'][] = $bdm = (int) $pdo->lastInsertId();

    // ===== D4 ===========================================================================
    foreach (['9825012345' => '', '+91 98250 12345' => '', '098250 12345' => '', '+44 20 7946 0958' => ''] as $num => $want)
        t_eq(sales_mobile_problem($num), $want, "D4 · \"$num\" is a mobile");
    t_ok(stripos(sales_mobile_problem('12345'), 'looks short') !== false, 'D4 · "12345" — looks short');
    t_ok(sales_mobile_problem('022 2345 6789') !== '', 'D4 · a landline "022 2345 6789" is not a mobile');
    t_ok(sales_mobile_problem('', true) !== '' && sales_mobile_problem('', false) === '', 'D4 · empty: needed on the quick lead only');
    bbv_as($bdm);
    $r = sx_lead_save(['company_name' => 'D4 No Mobile ' . $f['tag']], true);
    t_ok(!empty($r['err']) && stripos($r['err'], 'mobile') !== false, 'D4 · quick lead with only a name is refused (owner QL-01)');
    $r = sx_lead_save(['company_name' => 'D4 Ok ' . $f['tag'], 'contact_phone' => '98250 77777'], true);
    if (!empty($r['id'])) $made['l'][] = (int) $r['id'];
    t_ok(!empty($r['ok']) || !empty($r['id']), 'D4 · quick lead with a 10-digit mobile saves');
    $r = bbv_route('lead-new', 'POST', $bdm, 0, ['company_name' => 'D4 Full NoContact ' . $f['tag'], 'confirm' => '1'], []);
    t_ok($flashHas($r, 'mobile or an e-mail') && !ops_val("SELECT id FROM leads WHERE company_name=?", ['D4 Full NoContact ' . $f['tag']]),
        'D4 · full lead form with no mobile and no e-mail is refused');
    $r = bbv_route('lead-new', 'POST', $bdm, 0, ['company_name' => 'D4 Full Landline ' . $f['tag'], 'contact_phone' => '022 2345 6789', 'confirm' => '1'], []);
    t_ok($flashHas($r, 'mobile number') && !ops_val("SELECT id FROM leads WHERE company_name=?", ['D4 Full Landline ' . $f['tag']]), 'D4 · full form: a landline in Mobile is refused');
    // An older record with a landline is not touched, and saving its other details still works.
    bbv_as($f['master']);
    $old = lead_create(['company_name' => 'D4 Old ' . $f['tag'], 'contact_phone' => '022 2345 6789', 'source' => 'REFERRAL']);
    $made['l'][] = $oid = (int) $old['id'];
    $pdo->prepare("UPDATE leads SET office_id=?, owner_user_id=? WHERE id=?")->execute([$f['mum'], $bdm, $oid]);
    $r = bbv_route('lead-edit', 'POST', $bdm, 0, ['id' => $oid, 'company_name' => 'D4 Old ' . $f['tag'], 'contact_phone' => '022 2345 6789', 'requirement' => 'edited'], []);
    t_eq((string) ops_val("SELECT contact_phone FROM leads WHERE id=?", [$oid]), '022 2345 6789', 'D4 · an existing landline stays; editing other details is not blocked');
    $h = (string) ($raw('lead-new', $bdm)['html'] ?? '');
    t_ok(strpos($h, '>Mobile</label>') !== false && strpos($h, '>Telephone</label>') === false, 'D4 · full lead form says "Mobile"');
    $h = (string) ($raw('opportunity-new', $bdm)['html'] ?? '');
    t_ok(strpos($h, '>Mobile</label>') !== false && strpos($h, 'Contact phone') === false, 'D4 · new deal form says "Mobile"');

    // ===== D5 ===========================================================================
    bbv_as($f['master']);
    $pdo->prepare("INSERT INTO business_partners (legal_name, display_name, is_client, code) VALUES (?,?,1,?)")->execute(['D5 Valves ' . $f['tag'], 'D5 Valves ' . $f['tag'], 'D5-' . $f['tag']]);
    $made['bp'][] = $pid = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO partner_contacts (partner_id, name, email, mobile, is_primary) VALUES (?,?,?,?,1)")->execute([$pid, 'Rakesh Mehta', 'rakesh@d5.example', '9825011111']);
    t_ok(crm_inquiry_add_contact($pid, 'Priya Nair', '', '98250 33333', 'INQ-T', 0), 'D5 · Priya (new mobile) is added');
    $rows = ops_all("SELECT name, is_primary FROM partner_contacts WHERE partner_id=? ORDER BY id", [$pid]);
    t_eq(array_map(fn($r) => $r['name'] . ($r['is_primary'] ? ' (main)' : ''), $rows), ['Rakesh Mehta (main)', 'Priya Nair'], 'D5 · Rakesh stays the main contact; Priya is added beside him');
    t_ok(!crm_inquiry_add_contact($pid, 'P. Nair', '', '+91 9825033333', 'INQ-T', 0), 'D5 · the same mobile again (written differently) is not added twice');
    t_ok(!crm_inquiry_add_contact($pid, 'R Mehta', 'RAKESH@d5.example', '', 'INQ-T', 0), 'D5 · the same e-mail is not added twice');
    t_ok((bool) ops_val("SELECT 1 FROM activities WHERE partner_id=? AND subject LIKE ?", [$pid, '%Priya Nair%']), "D5 · written in the customer's history");

    // ===== D7 ===========================================================================
    $l = lead_create(['company_name' => 'D7 Lead ' . $f['tag'], 'contact_phone' => '9825044444', 'source' => 'REFERRAL']);
    $made['l'][] = $lid = (int) $l['id'];
    $st = array_values(array_filter(pipeline_stages((int) ops_val("SELECT pipeline_id FROM leads WHERE id=?", [$lid])), fn($s) => $s['kind'] === 'OPEN'));
    $pdo->prepare("UPDATE leads SET office_id=?, owner_user_id=?, stage_id=? WHERE id=?")->execute([$f['mum'], $bdm, (int) $st[1]['id'], $lid]);
    $h = (string) ($raw('lead', $bdm, ['id' => $lid])['html'] ?? '');
    $menu = substr($h, (int) strpos($h, 'id="stage-menu"'), 9000);
    t_ok(strpos($menu, 'rec-mi cur') !== false && strpos($menu, 'Current') !== false, 'D7 · the current stage is marked and is not a button');
    t_ok(strpos($menu, '>Move back</div>') !== false && strpos($menu, 'Move back to ' . e($st[0]['name'])) !== false, 'D7 · the earlier stage is under "Move back"');
    t_ok(strpos($menu, 'Move back from') !== false, 'D7 · its question says "Move back from … to …?"');
    $pos = strpos($menu, '>Move back</div>');
    t_ok($pos !== false && strpos(substr($menu, 0, $pos), e($st[2]['name'] ?? '§')) !== false, 'D7 · stages ahead stay above, as before');

    // ===== D8 ===========================================================================
    $h = (string) ($raw('sales-home', $f['bd'])['html'] ?? '');
    t_ok(substr_count($h, 'class="kpi sxw-kpi') >= 5 && strpos($h, 'class="sxw-k ') === false, 'D8 · Sales home figures are the product\'s .kpi cards (no tinted cards)');
    t_ok((bool) preg_match('#class="kpi sxw-kpi[^"]*"[^>]*><span class="k">[^<]+</span><span class="kic"#', $h), 'D8 · label in the header band, icon beside it, then the number');
    $css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/app.css');
    t_ok(strpos($css, '.sxw-k{') === false && strpos($css, '.sxw-kp>.kpi') !== false, 'D8 · the old tinted card style is gone; only grid sizing is Sales\' own');
} finally {
    bbv_as($f['master']);
    $del = function ($t, $ids) use ($pdo) { $ids = array_filter(array_map('intval', $ids)); if ($ids) $pdo->exec("DELETE FROM $t WHERE id IN (" . implode(',', $ids) . ")"); };
    if ($made['bp']) $pdo->exec("DELETE FROM partner_contacts WHERE partner_id IN (" . implode(',', array_map('intval', $made['bp'])) . ")");
    $pdo->exec("DELETE FROM leads WHERE company_name LIKE '%" . $f['tag'] . "%'");
    $del('leads', $made['l']); $del('business_partners', $made['bp']); $del('users', $made['u']);
    bbv_cleanup($f);
}
