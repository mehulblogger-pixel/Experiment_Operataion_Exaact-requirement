<?php
// ============================================================================
//  Permission foundation A — the new switches WORK (owner, 21 Nov 2026).
//
//  test_perm_a_equivalence.php proves that nobody gained or lost anything on day
//  one. This one proves the point of the change: each new switch can now be
//  taken away from one role, or given to one person, and only that changes.
//  Plus Q11 (a person's own access follows later changes to their role), DEF-25
//  (Customer 360 money follows the viewer's branches) and Orders & payments'
//  Overdue following the salesperson picked.
// ============================================================================
require_once __DIR__ . '/_bbv_fixture.php';

$NEW = ['crm.sales.see_all', 'crm.targets.set', 'crm.deal.view', 'crm.deal.edit', 'crm.pipeline.manage',
        'crm.lead.export', 'crm.deal.export', 'crm.quote.export', 'crm.settings.manage', 'data.customer_money',
        'costing.view', 'costing.prepare', 'costing.heads.manage'];

t_section('Foundation A — every new switch is a named, explained, grantable right');
foreach ($NEW as $k) {
    t_ok(isset(PERMISSIONS[$k]) && perm_help($k) !== $k && strlen(perm_help($k)) > 40, "$k has a plain-English name and an explanation");
    $onRole = false; foreach (permission_groups() as $ks) if (in_array($k, $ks, true)) $onRole = true;
    $onPerson = false; foreach (permission_nav_groups() as $ks) if (in_array($k, $ks, true)) $onPerson = true;
    t_ok($onRole && $onPerson, "$k appears on both the role screen and the person screen (so a save never drops it)");
}
t_ok(in_array(PERM_SPLIT_TAG, perm_close_implications(['mod.leads.view']), true), 'every save stamps the new vocabulary, so a gap is a decision and is never worked out again');
$stamped = perm_upgrade_split(['data.credit', PERM_SPLIT_TAG], 'COORDINATOR');
t_ok(!in_array('data.customer_money', $stamped, true), 'a stamped set that leaves "customer money" off keeps it off, though it holds the old credit tick');
t_ok(in_array('data.customer_money', perm_upgrade_split(['data.credit'], 'COORDINATOR'), true), 'an unstamped (older) set gets it from the old credit tick, as before');

$f = bbv_fixture();
$pdo = db();
$prevGrid = (string) setting_get('role_access', '');
$mk = function ($key, $role, $office, $scope = '', $perms = '') use ($pdo, $f) {
    $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices, permissions) VALUES (?,?,?,?,1,?,?,?)")
        ->execute([$f['tag'] . '_' . $key, ucfirst($key), 'T', $role, $office, $scope, $perms]);
    return (int) $pdo->lastInsertId();
};
$made = ['inv' => [], 'bp' => [], 'q' => []];
try {
    $bdm  = $mk('bdmA', 'BUSINESS_DEV_MANAGER', $f['mum']);
    $bdm2 = $mk('bdmB', 'BUSINESS_DEV_MANAGER', $f['mum']);
    $me   = $mk('mexA', 'MARKETING_EXECUTIVE', $f['mum']);
    $kiran = $f['kiran']; $bd = $f['bd']; $fin = $f['meera'];

    t_section('Foundation A — switch one off for a role: only that role loses it');
    setting_set('role_access', '');
    bbv_as($kiran);
    t_ok(sx_can_see_all() && ar_can() && pc_can() && ch_can_manage(), 'Branch Manager on day one: sees others\' lists, customer money, costings, cost heads (as before)');
    $grid = ['BRANCH_MANAGER' => array_values(array_diff(role_perm_set('BRANCH_MANAGER'), ['crm.sales.see_all', 'data.customer_money', 'costing.heads.manage']))];
    setting_set('role_access', json_encode($grid));
    bbv_as($kiran);
    t_ok(!sx_can_see_all(), 'Branch Manager with "See other salespeople\'s lists" switched off: own list only');
    t_ok(!ar_can(), '…and with "customer billed / paid" off: no customer money, though "inter-office credit figures" is still ticked');
    t_ok(can('data.credit'), '…the inter-office credit tick itself is untouched (it keeps its own meaning)');
    t_ok(pc_can() && !ch_can_manage(), '…costings still open; the cost-head library does not');
    bbv_as($bd);
    t_ok(sx_can_see_all() && ar_can() && ch_can_manage(), 'the Business Director, another role, keeps all three');

    setting_set('role_access', json_encode(['BUSINESS_DEV_MANAGER' => array_values(array_diff(role_perm_set('BUSINESS_DEV_MANAGER'), ['crm.lead.export', 'crm.pipeline.manage', 'crm.deal.edit']))]));
    bbv_as($bdm);
    t_ok(leads_can_view() && !leads_can_export(), 'BDM with the lead download off: still works leads, cannot download them');
    t_ok(leads_can_edit() && !pipe_can(), '…still edits leads, but can no longer change the company\'s pipelines (Q4)');
    t_ok(opp_can_view() && !opp_can_edit(), '…still sees deals, cannot add or edit them (Q3)');
    $r = bbv_route('leads', 'GET', $bdm, 0, [], ['export' => 'csv']);
    t_ok(stripos(implode(' ', $r['flash'] ?? []), 'Download leads') !== false, 'the lead download is refused through the real route, with the reason');
    $r = bbv_route('leads', 'GET', $bdm, 0, [], ['v' => 'board']);
    t_ok(strpos((string) ($r['out'] ?? ''), '⬇ CSV') === false, '…and the board shows no CSV button to them');
    $r = bbv_route('pipeline-new', 'GET', $bdm, 0, [], []);
    t_ok(stripos(implode(' ', $r['flash'] ?? []), 'cannot change the pipelines') !== false, 'opening "new pipeline" is refused');
    setting_set('role_access', '');

    t_section('Foundation A — switch one on for one person: only that person gains it');
    $all = array_keys(all_permissions());
    $store = user_perms_to_store(array_merge(role_perm_set('MARKETING_EXECUTIVE'), ['crm.settings.manage', 'crm.sales.see_all']), 'MARKETING_EXECUTIVE', $all, '');
    t_ok(strpos($store, PERM_DIFF_TAG) === 0 && strpos($store, '+crm.settings.manage') !== false && strpos($store, '+crm.sales.see_all') !== false,
        'stored as differences from the role: ' . $store);
    $pdo->prepare("UPDATE users SET permissions=? WHERE id=?")->execute([$store, $me]);
    bbv_as($me);
    t_ok(sx_settings_can() && sx_can_see_all(), 'that Marketing Executive may change Sales settings and see others\' lists');
    t_ok(!can('settings.manage'), '…without being handed every other company setting (Q9)');
    $other = $mk('mexB', 'MARKETING_EXECUTIVE', $f['mum']);
    bbv_as($other);
    t_ok(!sx_settings_can() && !sx_can_see_all(), 'another Marketing Executive does not get them');
    $r = bbv_route('sales-settings', 'GET', $me, 0, [], []);
    t_eq((string) ($r['view'] ?? ''), 'ops/sx_settings', 'the person given it opens Sales settings through the real route');
    $r = bbv_route('sales-settings', 'GET', $other, 0, [], []);
    t_ok(($r['view'] ?? '') === '' && stripos(implode(' ', $r['flash'] ?? []), 'Change Sales settings') !== false, 'the other is refused, told which permission it needs');

    t_section('Q11 — a person\'s own access follows later changes to the role');
    $store = user_perms_to_store(array_values(array_diff(role_perm_set('BUSINESS_DEV_MANAGER'), ['crm.quote.export'])), 'BUSINESS_DEV_MANAGER', $all, '');
    t_eq($store, PERM_DIFF_TAG . ',-crm.quote.export', 'one exception stored as one difference');
    //  The same exception the old way: a frozen copy of the role, as it was that day.
    $frozen = implode(',', array_values(array_diff(role_perm_set('BUSINESS_DEV_MANAGER'), ['crm.quote.export'])));
    $pdo->prepare("UPDATE users SET permissions=? WHERE id=?")->execute([$store, $bdm2]);
    bbv_as($bdm2);
    t_ok(!crm_quote_can_export() && !can('crm.quote.approve'), 'before: no quotation download (their exception), no approving (the role\'s default)');
    setting_set('role_access', json_encode(['BUSINESS_DEV_MANAGER' => array_merge(role_perm_set('BUSINESS_DEV_MANAGER'), ['crm.quote.approve'])]));
    bbv_as($bdm2);
    t_ok(can('crm.quote.approve'), 'the role is given "Approve quotations" — and it reaches this person, who has an exception');
    t_ok(!crm_quote_can_export(), '…while their own exception stays');
    t_ok(!in_array('crm.quote.approve', perm_resolve_stored($frozen, 'BUSINESS_DEV_MANAGER'), true), 'an old frozen copy of the same exception would NOT have followed — that is what Q11 fixes');
    t_ok(in_array('crm.quote.approve', perm_resolve_stored($store, 'BUSINESS_DEV_MANAGER'), true), 'resolved the same way everywhere (the editor, the audit, can())');
    $back = user_perms_to_store(role_perm_set('BUSINESS_DEV_MANAGER'), 'BUSINESS_DEV_MANAGER', $all, $store);
    t_eq($back, '', 'ticking back to the role\'s set: the person simply follows the role again');
    $n = $pdo->prepare("UPDATE users SET permissions=? WHERE id=?"); $n->execute([$store, $bdm2]);
    t_ok(user_has_own_access(ops_one("SELECT * FROM users WHERE id=?", [$bdm2])), 'the user register still flags a person with their own access');
    setting_set('role_access', '');

    t_section('Q11 — the one-time move keeps what each person can do, and writes the old value to the audit');
    $legacyCsv = implode(',', array_values(array_diff(role_perm_set('KEY_ACCOUNTS_MANAGER'), ['mod.leads.add', PERM_SPLIT_TAG])));
    $kam = $mk('kamL', 'KEY_ACCOUNTS_MANAGER', $f['mum'], '', $legacyCsv);
    $beforeEff = user_effective_perms(ops_one("SELECT * FROM users WHERE id=?", [$kam])); sort($beforeEff);
    setting_set('access_user_diff_q11_applied', '');
    $moved = access_migrate_user_diffs(true);
    $row = ops_one("SELECT * FROM users WHERE id=?", [$kam]);
    $afterEff = user_effective_perms($row); sort($afterEff);
    t_ok($moved >= 1 && strpos((string) $row['permissions'], PERM_DIFF_TAG) === 0, 'the frozen copy was moved to differences: ' . $row['permissions']);
    t_ok(array_values(array_filter($beforeEff, fn($p) => $p[0] !== '@')) === array_values(array_filter($afterEff, fn($p) => $p[0] !== '@')), 'exactly the same rights before and after');
    $log = ops_one("SELECT * FROM idems_audit WHERE entity='user' AND entity_id=? AND action='ACCESS_STORED_AS_DIFFERENCES' ORDER BY id DESC LIMIT 1", [$kam]);
    t_ok($log && (string) $log['old_value'] === $legacyCsv && (string) $log['new_value'] === (string) $row['permissions'], 'the sealed audit chain keeps the old value and the new one');
    t_eq(access_migrate_user_diffs(true), 0, 'running it again moves nobody');

    t_section('Reports that list who holds a right read the new storage correctly');
    $pdo->prepare("UPDATE users SET permissions=? WHERE id=?")->execute([PERM_DIFF_TAG . ',+person.iddoc.manage', $kam]);
    $holders = array_map('intval', array_column(iddoc_holders('person.iddoc.manage'), 'id'));
    t_ok(in_array($kam, $holders, true), 'identity-document custodians: a person given it as a difference is listed');
    $stale = null; foreach (integrity_checks() ?? [] as $c) if (($c['key'] ?? '') === 'perm_stale') $stale = $c;
    t_ok($stale === null || (int) $stale['found'] === 0, 'data control: "+right" / "-right" are not reported as unknown permissions');

    t_section('DEF-25 — Customer 360 money follows the viewer\'s branches');
    books_migrate();
    $pdo->prepare("INSERT INTO business_partners (display_name, legal_name, is_client) VALUES (?,?,1)")->execute(['DEF25 Co ' . $f['tag'], 'DEF25 Co ' . $f['tag']]);
    $pid = (int) $pdo->lastInsertId(); $made['bp'][] = $pid;
    foreach ([[$f['mum'], 59000], [$f['ahm'], 82600]] as [$o, $t]) {
        $pdo->prepare("INSERT INTO invoices (invoice_no, partner_id, partner_name, office_id, invoice_date, due_date, total, status) VALUES (?,?,?,?,?,?,?, 'ISSUED')")
            ->execute(['DEF25-' . $o . '-' . $f['tag'], $pid, 'DEF25 Co', $o, date('Y-m-d', strtotime('-40 days')), date('Y-m-d', strtotime('-10 days')), $t]);
        $made['inv'][] = (int) $pdo->lastInsertId();
    }
    bbv_as($fin);   // office Finance, Mumbai only
    $m = c360_money($pid);
    t_eq((float) $m['outstanding'], 59000.0, 'Mumbai Finance: owed ₹59,000 — Mumbai\'s invoice only (was ₹1,41,600, every branch)');
    t_eq((float) $m['overdue'], 59000.0, '…the overdue figure and ageing follow the same rule');
    t_ok(count($m['invoices']) === 1, '…and only the Mumbai invoice is listed');
    bbv_as($f['rakesh']);   // central Finance, every office
    $m = c360_money($pid);
    t_eq((float) $m['outstanding'], 141600.0, 'central Finance (every office): ₹1,41,600, exactly as before');

    t_section('Orders & payments — "Overdue" follows the salesperson picked');
    bbv_as($f['rakesh']);
    $everyone = sx_overdue_for(null);
    $ids = array_map(fn($r) => (int) $r['id'], array_filter($everyone ?? [], fn($r) => !empty($r['from_books'])));
    t_ok(in_array($made['inv'][0], $ids, true) && in_array($made['inv'][1], $ids, true), 'everyone: both overdue invoices are listed');
    $one = sx_overdue_for([$made['inv'][0]]);
    t_eq(array_map(fn($r) => (int) $r['id'], $one), [$made['inv'][0]], 'one person picked: only the invoice of their orders');
    t_eq(sx_overdue_for([]), [], 'a person with no invoiced orders: nothing overdue for them');
} finally {
    setting_set('role_access', $prevGrid);
    $del = function ($t, $ids) use ($pdo) { if ($ids) $pdo->exec("DELETE FROM $t WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")"); };
    $del('invoices', $made['inv']); $del('business_partners', $made['bp']);
    bbv_cleanup($f);
}
