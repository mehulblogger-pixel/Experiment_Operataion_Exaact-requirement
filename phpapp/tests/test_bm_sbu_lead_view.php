<?php
// Owner, 21 Nov 2026 — "branch manager and SBU head can sure open the lead and check
// all other details as they are also responsible for business individually for their
// branch and business units respectively" … "Yes but yes they shall be able to
// download". And: "finance does not need to see the details of deal".
//
//   * The Branch Manager opens, reads and downloads the leads and deals of THEIR BRANCH.
//   * The SBU Head the same for THEIR BUSINESS UNIT (they cover every branch).
//   * Neither may add, edit, move, mark won/lost or delete there.
//   * Finance still does not open the deal pages (Sales home keeps working for them).
//   * A saved Roles & access grid and a person's own frozen copy receive the rule once.
t_section('Branch Manager and SBU Head — open and download leads and deals of their branch / unit (owner, 21 Nov 2026)');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$made = ['l' => [], 'o' => []];
$prev = ['role_access' => (string) setting_get('role_access', ''), 'applied' => (string) setting_get('access_owner_grants_applied', '')];
$extra = [];
try {
    bbv_as($f['master']); leads_migrate(); opp_migrate();
    // SBU Head of the Industrial unit (every branch).
    $pdo->prepare("UPDATE users SET scope_sbus='IND' WHERE id=?")->execute([$f['sbu']]);
    $lead = function ($office, $sbu, $name) use (&$made, $pdo) {
        $r = lead_create(['company_name' => $name . ' ' . uniqid(), 'contact_phone' => '9825011111', 'source' => 'REFERRAL', 'value' => 250000]);
        $pdo->prepare("UPDATE leads SET office_id=?, sbu=? WHERE id=?")->execute([$office, $sbu, $r['id']]);
        $made['l'][] = (int) $r['id']; return (int) $r['id'];
    };
    $pipe = pipeline_default('OPPORTUNITY');
    $deal = function ($office, $sbu, $name) use (&$made, $pdo, $pipe) {
        $r = opp_create(['name' => $name . ' ' . uniqid(), 'partner_name' => 'BMSBU Co', 'value' => 480000, 'pipeline_id' => $pipe['id']]);
        $pdo->prepare("UPDATE opportunities SET office_id=?, sbu=? WHERE id=?")->execute([$office, $sbu, $r['id']]);
        $made['o'][] = (int) $r['id']; return (int) $r['id'];
    };
    $lMumInd = $lead($f['mum'], 'IND', 'BMSBU Mumbai IND');
    $lMumOgc = $lead($f['mum'], 'OGC', 'BMSBU Mumbai OGC');
    $lAhmInd = $lead($f['ahm'], 'IND', 'BMSBU Ahmedabad IND');
    $oMumInd = $deal($f['mum'], 'IND', 'BMSBU deal Mumbai IND');
    $oMumOgc = $deal($f['mum'], 'OGC', 'BMSBU deal Mumbai OGC');
    $oAhmInd = $deal($f['ahm'], 'IND', 'BMSBU deal Ahmedabad IND');

    $refs = fn($ids, $table) => array_column(ops_all("SELECT ref FROM $table WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")") ?: [], 'ref');
    $L = array_combine(['mi', 'mo', 'ai'], $refs([$lMumInd, $lMumOgc, $lAhmInd], 'leads'));
    $O = array_combine(['mi', 'mo', 'ai'], $refs([$oMumInd, $oMumOgc, $oAhmInd], 'opportunities'));
    $refused = fn($r) => !empty($r['restricted']) || (int) ($r['code'] ?? 0) === 403
                        || (bool) array_filter($r['flash'] ?? [], fn($x) => stripos($x, 'error') === 0);

    // ---- Rights -----------------------------------------------------------------------
    foreach (['kiran' => 'Branch Manager', 'sbu' => 'SBU Head'] as $k => $who) {
        bbv_as($f[$k]);
        t_ok(can('mod.leads.view') && opp_can_view(), "$who · may open leads and deals");
        t_ok(leads_can_export() && opp_can_export(), "$who · may download leads and deals");
        t_ok(!can('mod.leads.add') && !leads_works_leads(), "$who · may not add or change leads and deals (view only)");
        t_ok(!can(perm_verb_key('leads', 'delete')), "$who · may not delete leads");
    }
    bbv_as($f['meera']);
    t_ok(!can('mod.leads.view'), 'Finance · still has no Leads — the deal pages stay closed (owner: finance does not need the deal details)');

    // ---- Branch Manager (Mumbai) --------------------------------------------------------
    $r = bbv_route('leads', 'GET', $f['kiran'], 0, [], ['v' => 'list']);
    t_ok(!$refused($r) && strpos($r['out'], $L['mi']) !== false && strpos($r['out'], $L['mo']) !== false, 'Branch Manager · the lead register opens and lists Mumbai\'s leads (every unit)');
    t_ok(strpos($r['out'], $L['ai']) === false, 'Branch Manager · Ahmedabad\'s lead is not listed');
    $r = bbv_route('leads', 'GET', $f['kiran'], 0, [], ['v' => 'list', 'export' => 'csv']);
    t_ok(!$refused($r) && strpos($r['out'], $L['mi']) !== false && strpos($r['out'], $L['ai']) === false, 'Branch Manager · downloads the lead list — Mumbai only');
    $r = bbv_route('opportunities', 'GET', $f['kiran'], 0, [], ['v' => 'list', 'export' => 'csv']);
    t_ok(!$refused($r) && strpos($r['out'], $O['mi']) !== false && strpos($r['out'], $O['ai']) === false, 'Branch Manager · downloads the deal list — Mumbai only');
    $r = bbv_route('lead', 'GET', $f['kiran'], $lMumOgc);
    t_ok(!$refused($r) && ($r['view'] ?? '') !== '', 'Branch Manager · opens a Mumbai lead page');
    $r = bbv_route('lead', 'GET', $f['kiran'], $lAhmInd);
    t_ok($refused($r), 'Branch Manager · an Ahmedabad lead is refused');
    $r = bbv_route('opportunity', 'GET', $f['kiran'], $oMumInd);
    t_ok(!$refused($r) && ($r['view'] ?? '') !== '', 'Branch Manager · opens a Mumbai deal page');
    $r = bbv_route('opportunity', 'GET', $f['kiran'], $oAhmInd);
    t_ok($refused($r), 'Branch Manager · an Ahmedabad deal is refused');

    // ---- Read only --------------------------------------------------------------------
    $before = ops_one("SELECT name, value, status FROM opportunities WHERE id=?", [$oMumInd]);
    $r = bbv_route('opportunity-edit', 'POST', $f['kiran'], $oMumInd, ['id' => $oMumInd, 'name' => 'Changed by BM', 'value' => 1]);
    t_ok($refused($r), 'Branch Manager · editing a deal is refused');
    $r = bbv_route('opportunity-move', 'POST', $f['kiran'], $oMumInd, ['id' => $oMumInd, 'to' => 'WON']);
    t_ok($refused($r), 'Branch Manager · moving / marking a deal won is refused');
    t_eq(ops_one("SELECT name, value, status FROM opportunities WHERE id=?", [$oMumInd]), $before, 'Branch Manager · the deal is unchanged');
    $lb = ops_one("SELECT company_name, value, status FROM leads WHERE id=?", [$lMumInd]);
    $r = bbv_route('lead-edit', 'POST', $f['kiran'], $lMumInd, ['id' => $lMumInd, 'company_name' => 'Changed by BM']);
    t_ok($refused($r), 'Branch Manager · editing a lead is refused');
    $r = bbv_route('lead-new', 'POST', $f['kiran'], 0, ['company_name' => 'New by BM ' . uniqid(), 'contact_phone' => '9825022222']);
    t_ok($refused($r), 'Branch Manager · adding a lead is refused');
    t_eq(ops_one("SELECT company_name, value, status FROM leads WHERE id=?", [$lMumInd]), $lb, 'Branch Manager · the lead is unchanged');
    t_eq((int) ops_val("SELECT COUNT(*) FROM leads WHERE company_name LIKE 'New by BM %'"), 0, 'Branch Manager · no lead was created');

    // ---- SBU Head (Industrial, every branch) -----------------------------------------
    $r = bbv_route('leads', 'GET', $f['sbu'], 0, [], ['v' => 'list', 'export' => 'csv']);
    t_ok(!$refused($r) && strpos($r['out'], $L['mi']) !== false && strpos($r['out'], $L['ai']) !== false, 'SBU Head · downloads the Industrial leads of every branch');
    t_ok(strpos($r['out'], $L['mo']) === false, 'SBU Head · another unit\'s lead (Oil & Gas) is not in it');
    $r = bbv_route('opportunities', 'GET', $f['sbu'], 0, [], ['v' => 'list', 'export' => 'csv']);
    t_ok(!$refused($r) && strpos($r['out'], $O['ai']) !== false && strpos($r['out'], $O['mo']) === false, 'SBU Head · downloads the Industrial deals of every branch, not Oil & Gas');
    $r = bbv_route('opportunity', 'GET', $f['sbu'], $oAhmInd);
    t_ok(!$refused($r), 'SBU Head · opens an Industrial deal in Ahmedabad');
    $r = bbv_route('opportunity', 'GET', $f['sbu'], $oMumOgc);
    t_ok($refused($r), 'SBU Head · an Oil & Gas deal is refused');
    $r = bbv_route('lead', 'GET', $f['sbu'], $lMumOgc);
    t_ok($refused($r), 'SBU Head · an Oil & Gas lead is refused');
    $r = bbv_route('opportunity-move', 'POST', $f['sbu'], $oAhmInd, ['id' => $oAhmInd, 'to' => 'LOST']);
    t_ok($refused($r), 'SBU Head · marking a deal lost is refused');

    // ---- Nobody else changed --------------------------------------------------------
    $r = bbv_route('opportunities', 'GET', $f['meera'], 0, [], ['v' => 'list']);
    t_ok($refused($r), 'Finance · the deal register is still refused');
    $r = bbv_route('opportunity', 'GET', $f['meera'], $oMumInd);
    t_ok($refused($r), 'Finance · a deal page is still refused');
    $r = bbv_route('sales-home', 'GET', $f['meera'], 0, [], []);
    //  D1 (owner, 21 Nov 2026, findings SH-08): Finance's Sales is the Quotes register only.
    t_ok($refused($r), 'Finance · Sales home is refused (D1 — Finance keeps the Quotes register only)');
    $r = bbv_route('leads', 'GET', $f['neha'], 0, [], ['v' => 'list']);
    t_ok($refused($r), 'Coordinator · leads still refused');
    $r = bbv_route('leads', 'GET', $f['bd'], 0, [], ['v' => 'list']);
    t_ok(!$refused($r) && strpos($r['out'], $L['mo']) !== false && strpos($r['out'], $L['ai']) !== false, 'Business Director · still sees every branch and unit');

    // ---- The rule reaches a saved grid and a person's own frozen copy, once ----------
    setting_set('role_access', json_encode(['BRANCH_MANAGER' => ['mod.inquiries.view', 'mod.quotes.view', PERM_SPLIT_TAG]]));
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, permissions) VALUES (?,?,?,?,1,?,?)")
        ->execute([$f['tag'] . '_bmfrozen', 'Frozen', 'BM', 'BRANCH_MANAGER', $f['mum'], 'mod.inquiries.view,mod.quotes.view,' . PERM_SPLIT_TAG]);
    $extra[] = (int) $pdo->lastInsertId();
    $applied = array_values(array_filter(explode(',', $prev['applied']), fn($x) => trim($x) !== '' && $x !== 'BM-SBU-LEADS-2026-11-21'));
    setting_set('access_owner_grants_applied', implode(',', $applied));
    $got = access_apply_owner_grants(true);
    t_ok(in_array('BM-SBU-LEADS-2026-11-21', $got, true), 'the owner\'s rule is applied once to stored settings');
    $grid = json_decode((string) setting_get('role_access', ''), true);
    t_ok(in_array('mod.leads.view', $grid['BRANCH_MANAGER'] ?? [], true) && in_array('crm.lead.export', $grid['BRANCH_MANAGER'] ?? [], true)
         && !in_array('mod.leads.add', $grid['BRANCH_MANAGER'] ?? [], true), 'a saved grid: the Branch Manager gains Leads view + downloads, not Add');
    $fz = (string) ops_val("SELECT permissions FROM users WHERE id=?", [$extra[0]]);
    t_ok(strpos($fz, 'mod.leads.view') !== false && strpos($fz, 'crm.deal.export') !== false, 'a person\'s own frozen copy gains it too');
    t_eq(access_apply_owner_grants(true), [], 'run again: nothing more (once per database)');
} finally {
    $pdo = db();
    if ($made['o']) $pdo->exec("DELETE FROM opportunities WHERE id IN (" . implode(',', $made['o']) . ")");
    if ($made['l']) $pdo->exec("DELETE FROM leads WHERE id IN (" . implode(',', $made['l']) . ")");
    foreach ($extra as $id) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    setting_set('role_access', $prev['role_access']);
    setting_set('access_owner_grants_applied', $prev['applied'] !== '' ? $prev['applied'] : (string) setting_get('access_owner_grants_applied', ''));
    bbv_cleanup($f);
}
