<?php
// Owner decisions D1–D3 (21 Nov 2026, findings plan "as recommended").
//   D1 Finance in Sales: the Quotes register (view only) and the Client register's
//      normal view; every Sales workspace screen refused; ageing in Money; contract
//      registration and the central customer ledger unchanged.
//   D2 Working one lead or deal: the person handling it, the people above them, an
//      administrator. Everyone else reads it, with "Only <owner> or their manager…".
//      Unassigned: anybody in the branch. BM / SBU Head stay view only.
//   D3 The quotation stage and later need a quotation, or "Sent outside EXAACT" with a
//      reason kept in History. The stage is the template's quotation step (by chance of
//      winning), not a stage name.
t_section('Owner decisions D1–D3 — Finance in Sales, who works a record, a quotation before "Quotation sent"');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$made = ['u' => [], 'l' => [], 'o' => [], 'q' => [], 'oq' => []];
$flashHas = fn($r, $needle) => (bool) array_filter((array) ($r['flash'] ?? []), fn($x) => stripos((string) $x, $needle) !== false);
try {
    bbv_as($f['master']); leads_migrate(); opp_migrate();
    $user = function ($key, $role, $office, $reportsTo = null) use ($pdo, $f, &$made) {
        $pdo->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, reports_to_id) VALUES (?,?,?,?,1,?,?)")
            ->execute([$f['tag'] . '_' . $key, ucfirst($key), 'T', $role, $office, $reportsTo]);
        return $made['u'][] = (int) $pdo->lastInsertId();
    };
    $mm  = $user('mm', 'MARKETING_MANAGER', $f['mum']);
    $bdm = $user('bdm', 'BUSINESS_DEV_MANAGER', $f['mum'], $mm);      // reports to the Marketing Manager
    $kam = $user('kam', 'KEY_ACCOUNTS_MANAGER', $f['mum']);            // same branch, not in the line
    $me  = $user('mex', 'MARKETING_EXECUTIVE', $f['mum']);             // same branch, not in the line

    // ===== D1 — Finance ================================================================
    foreach (['Finance (Mumbai)' => $f['meera'], 'Finance (all branches)' => $f['rakesh']] as $who => $uid) {
        bbv_as($uid);
        t_ok(!sx_can() && !opp_can_view() && !can('crm.deal.view') && !can('mod.crm_reports.view') && !can('mod.crm_orders.view'),
            "D1 · $who · no deals, no Sales reports, no Orders / contracts rights");
        t_ok(can('mod.quotes.view') && !can('crm.quote.create') && !can('mod.quotes.edit') && !can('crm.quote.approve') && !can('crm.quote.send'),
            "D1 · $who · Quotes: view only (no create, edit, approve or send)");
        t_ok(can('crm.contract.register') && function_exists('ar_can') && ar_can(), "D1 · $who · contract registration and receivables unchanged");
        foreach (['sales-home', 'sales-today', 'sales-board', 'sales-dashboard', 'sales-orders', 'sales-renewals', 'sales-targets'] as $rt) {
            $r = bbv_route($rt, 'GET', $uid, 0, [], []);
            t_ok(($r['rendered'] ?? null) !== true && $flashHas($r, 'Sales is not open to you'), "D1 · $who · /$rt refused with \"Sales is not open to you\"");
        }
        $r = bbv_route('quotes', 'GET', $uid, 0, [], []);
        t_ok(($r['view'] ?? '') === 'ops/crm/quote_list' && ($r['rendered'] ?? false) === true, "D1 · $who · the Quotes register opens");
        $r = bbv_route('quote-new', 'GET', $uid, 0, [], []);
        t_ok(($r['rendered'] ?? null) !== true, "D1 · $who · a new quotation is refused");
        $r = bbv_route('crm-dashboard', 'GET', $uid, 0, [], []);
        t_ok(($r['rendered'] ?? null) !== true, "D1 · $who · Sales reports refused");
    }
    bbv_as($f['rakesh']); $centralAll = function_exists('scope_offices') ? scope_offices() : null;
    t_ok($centralAll === null || $centralAll === 'ALL' || (is_array($centralAll) && in_array((int) $f['ahm'], array_map('intval', $centralAll), true)) || (function_exists('scope_office_allows') && scope_office_allows($f['ahm'])),
        'D1 · central Finance still covers every branch (customer ledger, Stage 5 decision)');
    t_ok(!in_array('crm.deal.view', role_perms('FINANCE'), true) && in_array('mod.quotes.view', role_perms('FINANCE'), true),
        'D1 · the Finance role default: Quotes view, no deal view (the split no longer derives it)');
    // The one-time narrowing reaches a saved grid and a frozen per-person copy.
    bbv_as($f['master']);
    $keepGrid = setting_get('role_access', ''); $keepApplied = setting_get('access_owner_revokes_applied', '');
    setting_set('role_access', json_encode(['FINANCE' => ['mod.quotes.view', 'crm.deal.view', 'mod.crm_reports.view', 'mod.crm_orders.edit', 'mod.invoicing.view']]));
    $frozenFin = $user('finfrozen', 'FINANCE', $f['mum']);
    $pdo->prepare("UPDATE users SET permissions=? WHERE id=?")->execute(['mod.quotes.view,crm.deal.view,mod.crm_reports.view,mod.invoicing.view', $frozenFin]);
    setting_set('access_owner_revokes_applied', '');
    $done = access_apply_owner_revokes(true);
    $grid = json_decode((string) setting_get('role_access', ''), true);
    t_ok(in_array('D1-FINANCE-SALES-2026-11-21', $done, true) && !array_intersect(['crm.deal.view', 'mod.crm_reports.view', 'mod.crm_orders.edit'], $grid['FINANCE'] ?? ['x'])
         && in_array('mod.quotes.view', $grid['FINANCE'], true), 'D1 · once: taken out of a saved Finance grid, Quotes kept');
    $eff = perm_resolve_stored((string) ops_val("SELECT permissions FROM users WHERE id=?", [$frozenFin]), 'FINANCE');
    t_ok(!in_array('crm.deal.view', $eff, true) && in_array('mod.quotes.view', $eff, true), 'D1 · once: a frozen per-person Finance copy loses deals, keeps Quotes');
    t_eq(access_apply_owner_revokes(true), [], 'D1 · idempotent — the rule never runs twice');
    setting_set('role_access', (string) $keepGrid); setting_set('access_owner_revokes_applied', (string) $keepApplied);

    // ===== D2 — who works one lead or deal ===============================================
    bbv_as($f['master']);
    $mk = function ($name, $owner) use ($pdo, $f, &$made) {
        $r = lead_create(['company_name' => $name . ' ' . $f['tag'], 'contact_phone' => '98250' . random_int(10000, 99999), 'source' => 'REFERRAL']);
        $pdo->prepare("UPDATE leads SET office_id=?, owner_user_id=? WHERE id=?")->execute([$f['mum'], $owner, $r['id']]);
        return $made['l'][] = (int) $r['id'];
    };
    $lOwned = $mk('D2 owned lead', $bdm);
    $lFree  = $mk('D2 unassigned lead', null);
    $row = ops_one("SELECT pipeline_id, stage_id FROM leads WHERE id=?", [$lOwned]);
    $next = null; foreach (pipeline_stages((int) $row['pipeline_id']) as $s) if ($s['kind'] === 'OPEN' && (int) $s['id'] !== (int) $row['stage_id']) { $next = $s; break; }
    $lock = 'or their manager can change this';
    foreach (['Marketing Executive (same branch)' => [$me, false], 'KAM (same branch)' => [$kam, false], 'BDM (handles it)' => [$bdm, true],
              'Marketing Manager (BDM reports to them)' => [$mm, true], 'Admin' => [$f['admin'], true]] as $who => [$uid, $may]) {
        $pdo->prepare("UPDATE leads SET stage_id=? WHERE id=?")->execute([(int) $row['stage_id'], $lOwned]);
        $r = bbv_route('lead-move', 'POST', $uid, 0, ['id' => $lOwned, 'stage_id' => (int) $next['id']], []);
        $moved = (int) ops_val("SELECT stage_id FROM leads WHERE id=?", [$lOwned]) === (int) $next['id'];
        t_ok($may ? $moved : (!$moved && $flashHas($r, $lock)), "D2 · lead · $who · " . ($may ? 'changes the stage' : 'refused: "Only Bdm T or their manager can change this."'));
        $r = bbv_route('sales-log', 'POST', $uid, 0, ['lead' => $lOwned, 'method' => 'CALL', 'note' => 'D2 log ' . $who, 'occurred_on' => date('Y-m-d')], []);
        $logged = (bool) ops_val("SELECT 1 FROM activities WHERE entity_kind='LEAD' AND entity_id=? AND body LIKE ?", [$lOwned, '%D2 log ' . $who . '%'])
               || (bool) ops_val("SELECT 1 FROM activities WHERE entity_kind='LEAD' AND entity_id=? AND subject LIKE ?", [$lOwned, '%D2 log ' . $who . '%']);
        t_ok($may ? !$flashHas($r, $lock) : ($flashHas($r, $lock) && !$logged), "D2 · lead · $who · " . ($may ? 'logs on it' : 'may not log on it'));
    }
    $p = bbv_route('lead', 'GET', $me, $lOwned, [], ['id' => $lOwned]);
    t_ok(strpos((string) ($p['out'] ?? ''), 'View only') !== false && strpos((string) ($p['out'] ?? ''), 'Only Bdm T or their manager can change this.') !== false,
        'D2 · lead page · the Marketing Executive reads it, with "Only Bdm T or their manager can change this."');
    $p = bbv_route('lead', 'GET', $bdm, $lOwned, [], ['id' => $lOwned]);
    t_ok(strpos((string) ($p['out'] ?? ''), 'View only') === false, 'D2 · lead page · the BDM works it (no "View only")');
    $fr = ops_one("SELECT pipeline_id, stage_id FROM leads WHERE id=?", [$lFree]);
    $r = bbv_route('lead-move', 'POST', $me, 0, ['id' => $lFree, 'stage_id' => (int) $next['id']], []);
    t_ok((int) ops_val("SELECT stage_id FROM leads WHERE id=?", [$lFree]) === (int) $next['id'], 'D2 · an unassigned lead: anybody in the branch may work it (Q10 answer "a")');
    $r = bbv_route('lead-move', 'POST', $f['kiran'], 0, ['id' => $lFree, 'stage_id' => (int) $fr['stage_id']], []);
    t_ok($flashHas($r, 'You cannot change a lead'), 'D2 · Branch Manager stays view only (owner decision, unchanged)');

    // Deals.
    $pipe = pipeline_default('OPPORTUNITY');
    $o = opp_create(['name' => 'D2 deal ' . $f['tag'], 'partner_name' => 'D2 Co', 'value' => 100000, 'pipeline_id' => $pipe['id']]);
    $made['o'][] = $oid = (int) $o['id'];
    $pdo->prepare("UPDATE opportunities SET office_id=?, owner_user_id=? WHERE id=?")->execute([$f['mum'], $bdm, $oid]);
    $stages = array_values(pipeline_stages((int) $pipe['id']));
    $first = $stages[0]; $second = $stages[1];
    $pdo->prepare("UPDATE opportunities SET stage_id=? WHERE id=?")->execute([(int) $first['id'], $oid]);
    $r = bbv_route('opportunity-move', 'POST', $me, 0, ['id' => $oid, 'stage_id' => (int) $second['id']], []);
    t_ok((int) ops_val("SELECT stage_id FROM opportunities WHERE id=?", [$oid]) === (int) $first['id'] && $flashHas($r, $lock), 'D2 · deal · Marketing Executive refused, with the reason');
    $r = bbv_route('opportunity-move', 'POST', $mm, 0, ['id' => $oid, 'stage_id' => (int) $second['id']], []);
    t_ok((int) ops_val("SELECT stage_id FROM opportunities WHERE id=?", [$oid]) === (int) $second['id'], 'D2 · deal · the BDM\'s manager moves it');
    $p = bbv_route('opportunity', 'GET', $kam, $oid, [], ['id' => $oid]);
    t_ok(strpos((string) ($p['out'] ?? ''), 'Only Bdm T or their manager can change this.') !== false, 'D2 · deal page · the KAM reads it, with the reason');

    // ===== D3 — a quotation before the quotation stage ===================================
    $qs = opp_quote_stage($stages, (int) $pipe['id']);
    t_ok($qs !== null, 'D3 · this funnel has a quotation stage: "' . ($qs['stage']['name'] ?? '—') . '" (' . ($qs['stage']['probability'] ?? '?') . '% — the template\'s quotation step)');
    $before = $stages[$qs['idx'] - 1] ?? null;
    bbv_as($bdm);
    $pdo->prepare("UPDATE opportunities SET stage_id=? WHERE id=?")->execute([(int) $first['id'], $oid]);
    if ($before && (int) $before['id'] !== (int) $first['id']) { $r = opp_move($oid, (int) $before['id'], []); t_ok(!empty($r['ok']), 'D3 · a stage before the quotation stage needs no quotation'); }
    $r = opp_move($oid, (int) $qs['stage']['id'], []);
    t_ok(!empty($r['err']) && stripos($r['err'], 'Sent outside EXAACT') !== false, 'D3 · to the quotation stage with no quotation: refused, offering "Sent outside EXAACT"');
    $later = null; for ($i = $qs['idx'] + 1; $i < count($stages); $i++) if ($stages[$i]['kind'] === 'OPEN') { $later = $stages[$i]; break; }
    if ($later) { $r = opp_move($oid, (int) $later['id'], []); t_ok(!empty($r['err']), 'D3 · a later open stage ("' . $later['name'] . '") is refused the same way'); }
    $r = opp_move($oid, (int) $qs['stage']['id'], ['sent_outside' => '1', 'outside_reason' => '']);
    t_ok(!empty($r['err']) && stripos($r['err'], 'say why') !== false, 'D3 · ticked but no reason: refused');
    $r = opp_move($oid, (int) $qs['stage']['id'], ['sent_outside' => '1', 'outside_reason' => 'E-mailed from Outlook on 18 Nov, ref D3/' . $f['tag']]);
    t_ok(!empty($r['ok']), 'D3 · ticked with a reason: moved');
    t_ok((bool) ops_val("SELECT 1 FROM activities WHERE entity_kind='OPPORTUNITY' AND entity_id=? AND (subject LIKE ? OR body LIKE ?)", [$oid, '%D3/' . $f['tag'] . '%', '%D3/' . $f['tag'] . '%']),
        'D3 · the reason is in the deal\'s history');
    // With a quotation linked, no question asked.
    $o2 = opp_create(['name' => 'D3 quoted ' . $f['tag'], 'partner_name' => 'D3 Co', 'value' => 50000, 'pipeline_id' => $pipe['id']]);
    $made['o'][] = $oid2 = (int) $o2['id'];
    $pdo->prepare("UPDATE opportunities SET office_id=?, owner_user_id=?, stage_id=? WHERE id=?")->execute([$f['mum'], $bdm, (int) $first['id'], $oid2]);
    $pdo->prepare("INSERT INTO quotations (quote_no, rev, status, subtotal, total_amount, created_at) VALUES (?,0,'SENT',50000,59000,?)")->execute(['D3-' . $f['tag'], date('c')]);
    $made['q'][] = $qid = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO opportunity_quotes (opportunity_id, quotation_id) VALUES (?,?)")->execute([$oid2, $qid]);
    $made['oq'][] = (int) $pdo->lastInsertId();
    $r = opp_move($oid2, (int) $qs['stage']['id'], []);
    t_ok(!empty($r['ok']), 'D3 · a deal with its quotation moves to the quotation stage with no question');
    // Template step, not a name: each template's quotation step.
    foreach (['manufacturing' => 'Offer submitted', 'services' => 'Proposal sent', 'construction' => 'Tender submitted', 'inspection' => 'Commercial proposal submitted',
              'manpower' => 'Profiles / CVs submitted', 'recruitment' => 'Profiles submitted', 'audit' => 'Proposal submitted'] as $k => $nm)
        t_eq(INDUSTRY_TEMPLATES[$k]['funnel'][INDUSTRY_TEMPLATES[$k]['quote']][0], $nm, "D3 · template \"$k\": its quotation step is \"$nm\"");
    t_ok(INDUSTRY_TEMPLATES['staffing']['quote'] === null, 'D3 · a staffing funnel has no quotation step, so no such rule');
} finally {
    bbv_as($f['master']);
    $del = function ($t, $ids) use ($pdo) { $ids = array_filter(array_map('intval', $ids)); if ($ids) $pdo->exec("DELETE FROM $t WHERE id IN (" . implode(',', $ids) . ")"); };
    $del('opportunity_quotes', $made['oq']); $del('quotations', $made['q']); $del('opportunities', $made['o']); $del('leads', $made['l']); $del('users', $made['u']);
    bbv_cleanup($f);
}
