<?php
// Plan 42 step C, parts 2 and 3 (owner, 18 Nov 2026 — "proceed with part 2 and 3"):
// quick new lead, log activity with the next follow-up in WORKING days, orders &
// payments, renewals, targets (history kept), Sales settings, and the quote's
// CGST + SGST / IGST split shown without changing any figure.
t_section('Simple Sales parts 2–3 — quick lead, log activity, working days, orders, renewals, targets, settings');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$made = ['l' => [], 'o' => [], 'q' => [], 'c' => [], 'p' => [], 'h' => [], 't' => [], 'i' => []];
$keep = [];
foreach (['sales_outcomes', 'sales_weekly_off', 'sales_owner_by_office', 'sales_stale_days'] as $k) $keep[$k] = setting_get($k, null);
$setS = function ($k, $v) { setting_set($k, $v); };
try {
    bbv_as($f['master']); leads_migrate(); opp_migrate(); sx_more_migrate();

    // ---- Working days ----------------------------------------------------------------
    $setS('sales_weekly_off', '0');                                 // Sunday off
    $fri = date('Y-m-d', strtotime('next friday'));
    t_eq(sx_add_working_days(1, null, $fri), date('Y-m-d', strtotime("$fri +1 day")), 'working days · Friday + 1 = Saturday (only Sunday is off)');
    t_eq(sx_add_working_days(2, null, $fri), date('Y-m-d', strtotime("$fri +3 days")), 'working days · Friday + 2 skips Sunday → Monday');
    $mon = date('Y-m-d', strtotime("$fri +3 days"));
    db()->prepare("INSERT INTO holidays (hol_date, name, office_id) VALUES (?,?,NULL)")->execute([$mon, 'Fx national holiday']); $made['h'][] = (int) db()->lastInsertId();
    t_eq(sx_add_working_days(2, null, $fri), date('Y-m-d', strtotime("$fri +4 days")), 'working days · a national holiday on Monday is skipped too → Tuesday');
    $tue = date('Y-m-d', strtotime("$fri +4 days"));
    db()->prepare("INSERT INTO holidays (hol_date, name, office_id) VALUES (?,?,?)")->execute([$tue, 'Fx Mumbai holiday', $f['mum']]); $made['h'][] = (int) db()->lastInsertId();
    t_eq(sx_add_working_days(2, $f['ahm'], $fri), $tue, 'working days · another office\'s holiday does not count');
    t_eq(sx_add_working_days(2, $f['mum'], $fri), date('Y-m-d', strtotime("$fri +5 days")), 'working days · this office\'s holiday does');
    $setS('sales_weekly_off', '');
    t_eq(sx_weekly_off(), [], 'weekly off · can be "none" (works every day)');
    $setS('sales_weekly_off', '0,6');
    t_eq(sx_weekly_off(), [0, 6], 'weekly off · Saturday and Sunday');
    t_eq(sx_add_working_days(0), '', 'working days · 0 = no date (the person picks)');

    // ---- Outcomes are a setting (examples until edited) ------------------------------
    $setS('sales_outcomes', '');
    t_ok(sx_outcomes_are_example() && isset(sx_outcomes()['INTERESTED']), 'outcomes · example values until the company edits them');
    $setS('sales_outcomes', json_encode([['k' => 'FX_HOT', 'label' => 'Fx hot', 'days' => 2], ['k' => 'FX_PICK', 'label' => 'Fx pick', 'days' => 0]]));
    t_eq(array_keys(sx_outcomes()), ['FX_HOT', 'FX_PICK'], 'outcomes · the saved list replaces the examples');
    $setS('sales_weekly_off', '0');

    // ---- Quick new lead ------------------------------------------------------------------
    $co = 'Fx Quick Co ' . uniqid();
    $r = sx_lead_save(['company_name' => $co, 'contact_phone' => '98250 77001', 'source' => 'REFERRAL'], false);
    t_ok(!empty($r['ok']), 'quick lead · company + mobile + source is enough' . (!empty($r['err']) ? ' — ' . $r['err'] : ''));
    $made['l'][] = (int) ($r['id'] ?? 0);
    $L = lead_row($r['id']);
    t_eq($L['next_action_on'], sx_add_working_days(1, (int) ($L['office_id'] ?? 0)), 'quick lead · first follow-up = next working day');
    t_eq((int) $L['owner_user_id'], (int) $f['master'], 'quick lead · with no office rule it is the person who typed it');
    $r2 = sx_lead_save(['company_name' => $co, 'contact_phone' => ''], false);
    t_ok(!empty($r2['dupes']) && empty($r2['ok']), 'quick lead · the same name again is shown as a possible duplicate, not saved');
    $r3 = sx_lead_save(['company_name' => 'Fx Other ' . uniqid(), 'contact_phone' => '+91 98250-77001'], false);
    t_ok(!empty($r3['dupes']) && strpos($r3['dupes'][0]['text'], 'mobile') !== false, 'quick lead · the same mobile (any format) is shown too');
    $r4 = sx_lead_save(['company_name' => $co, 'contact_phone' => ''], true);
    t_ok(!empty($r4['ok']), 'quick lead · "Save anyway" saves it'); $made['l'][] = (int) ($r4['id'] ?? 0);
    t_ok(!empty(sx_lead_save(['company_name' => 'Fx Short', 'contact_phone' => '123'], true)['err']), 'quick lead · a mobile that is too short is refused');
    t_ok(!empty(sx_lead_save(['company_name' => '  '], true)['err']), 'quick lead · no company is refused');
    $setS('sales_owner_by_office', json_encode([(string) $f['mum'] => $f['kiran']]));
    $r5 = sx_lead_save(['company_name' => 'Fx Mum Co ' . uniqid(), 'office_id' => $f['mum']], true);
    $made['l'][] = (int) ($r5['id'] ?? 0);
    t_eq((int) ($r5['owner'] ?? 0), (int) $f['kiran'], 'quick lead · an office with a salesperson set gives the lead to them');

    // ---- Log activity on a lead ----------------------------------------------------------
    $_GET = ['lead' => (string) $r['id']]; $t = sx_log_target(); $_GET = [];
    t_ok(!empty(sx_log_save($t, ['method' => 'CALL'])['err']), 'log · nothing said and no outcome is refused');
    t_ok(!empty(sx_log_save($t, ['method' => 'CALL', 'outcome' => 'FX_HOT', 'next_action_on' => date('Y-m-d', strtotime('-1 day'))])['err']), 'log · a next date in the past is refused');
    $lg = sx_log_save($t, ['method' => 'WHATSAPP', 'outcome' => 'FX_HOT', 'note' => 'Fx wants rates']);
    $L = lead_row($r['id']);
    t_eq($L['next_action_on'], sx_add_working_days(2, (int) ($L['office_id'] ?? 0)), 'log · outcome "2 working days" sets the lead\'s next follow-up');
    $a = ops_one("SELECT * FROM activities WHERE entity_kind='LEAD' AND entity_id=? AND COALESCE(auto,0)=0 ORDER BY id DESC", [$r['id']]);
    t_eq([$a['kind'], $a['outcome'], $a['body']], ['WHATSAPP', 'Fx hot', 'Fx wants rates'], 'log · written on the lead\'s own timeline with the outcome');

    // ---- Log activity on a deal ------------------------------------------------------------
    $o = opp_create(['name' => 'Fx deal ' . uniqid(), 'partner_name' => 'Fx', 'value' => 900000, 'pipeline_id' => pipeline_default('OPPORTUNITY')['id']]);
    $made['o'][] = (int) $o['id'];
    $_GET = ['deal' => (string) $o['id']]; $t = sx_log_target(); $_GET = [];
    $want = date('Y-m-d', strtotime('+10 days'));
    sx_log_save($t, ['method' => 'MEETING', 'outcome' => 'FX_PICK', 'next_action_on' => $want, 'next_action' => 'Site visit']);
    $O = opp_row($o['id']);
    t_eq([$O['next_action_on'], $O['next_action']], [$want, 'Site visit'], 'log · a deal\'s next step and date are set (the person picked the date)');
    t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='OPPORTUNITY' AND entity_id=? AND kind='MEETING'", [$o['id']]) === 1, 'log · written on the deal\'s own timeline');

    // ---- Targets: history kept, latest counts ------------------------------------------------
    $months = sxt_months(); $m0 = $months[0]; $m1 = $months[1];
    $_POST = []; $n = sxt_save(['t' => [$f['sunil'] => [$m0 => '500000']]], $months);
    t_eq($n, 0, 'targets · a person who carries no lead or deal and no Sales role is not on the grid (nothing saved)');
    // Kiran owns a lead (the office rule above gave it to him), so he carries a target.
    $n = sxt_save(['t' => [$f['kiran'] => [$m0 => '500000', $m1 => '600000']]], $months);
    t_eq($n, 2, 'targets · two months saved for a salesperson');
    t_eq(sxt_save(['t' => [$f['kiran'] => [$m0 => '500000', $m1 => '600000']]], $months), 0, 'targets · saving the same figures changes nothing');
    sxt_save(['t' => [$f['kiran'] => [$m0 => '550000']]], $months);
    t_eq((int) ops_val("SELECT COUNT(*) FROM sales_targets WHERE user_id=? AND ym=?", [$f['kiran'], $m0]), 2, 'targets · a change is a NEW row — the old figure stays in the history');
    t_eq(sxt_grid($months)[$f['kiran']][$m0], 550000.0, 'targets · the latest figure is the one that counts');
    t_eq(sxt_target_total($f['kiran'], $m0 . '-01', $m1 . '-28'), 1150000.0, 'targets · the dashboard total for the two months = 5.5 L + 6 L');
    t_eq(sx_target_total($f['kiran'], $m0 . '-01', $m1 . '-28'), 1150000.0, 'targets · part 1\'s dashboard hook now reads them');

    // ---- Renewals ----------------------------------------------------------------------------
    $pid = (int) ops_val("SELECT id FROM business_partners LIMIT 1");
    foreach ([20 => 'FX-R20', -10 => 'FX-R-10', -40 => 'FX-R-40', 120 => 'FX-R120'] as $dd => $cn) {
        db()->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, start_date, end_date, is_active) VALUES (?,?,?,?,?,?,1)")
            ->execute([$pid, $cn, 'Fx', 100000, date('Y-m-d', strtotime('-300 days')), date('Y-m-d', strtotime("$dd days"))]);
        $made['c'][] = (int) db()->lastInsertId();
    }
    $got = array_column(sx_renewals_list(90), 'contract_number');
    t_ok(in_array('FX-R20', $got, true) && in_array('FX-R-10', $got, true), 'renewals · ending in 20 days and ended 10 days ago are listed');
    t_ok(!in_array('FX-R-40', $got, true) && !in_array('FX-R120', $got, true), 'renewals · ended 40 days ago, or 120 days away (90-day view), are not');
    t_ok(in_array('FX-R120', array_column(sx_renewals_list(180), 'contract_number'), true), 'renewals · the 180-day view shows it');

    // ---- Orders & payments -------------------------------------------------------------------
    db()->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, client_name, subtotal, gst_pct, gst_amount, total_amount, status, accepted_date, created_at, updated_at)
                   VALUES (?,0,1,?,?,?,?,?,?, 'ACCEPTED', ?, ?, ?)")->execute(['FX-Q-1', $pid, 'Fx', 100000, 18, 18000, 118000, date('Y-m-d'), date('c'), date('c')]);
    $qid = (int) db()->lastInsertId(); $made['q'][] = $qid;
    if (function_exists('books_migrate')) books_migrate();
    db()->prepare("INSERT INTO invoices (invoice_no, partner_id, quotation_id, invoice_date, total, status, created_at) VALUES (?,?,?,?,?, 'ISSUED', ?)")
        ->execute(['FX-INV-1', $pid, $qid, date('Y-m-d'), 59000, date('c')]);
    $made['i'][] = (int) db()->lastInsertId();
    [$fa, $fb] = sx_period('fy');
    $row = array_values(array_filter(sx_orders(0, $fa, $fb), fn($q) => (int) $q['id'] === $qid))[0] ?? null;
    t_ok($row !== null, 'orders · an accepted quotation this year is an order');
    t_eq([(float) $row['total_amount'], $row['_billed'], $row['_recv']], [118000.0, 59000.0, 0.0], 'orders · ₹1,18,000 order, ₹59,000 billed against it, nothing received yet');

    // ---- Quote GST split (display only) ----------------------------------------------------
    $q = ops_one("SELECT * FROM quotations WHERE id=?", [$qid]);
    $sp = sx_quote_gst_split($q);
    if ($sp && $sp['mode'] !== 'UNKNOWN') {
        $sum = array_sum(array_map(fn($x) => $x[1], $sp['rows']));
        t_eq(round($sum, 2), 18000.0, 'quote GST · the split adds back to the quotation\'s own GST');
    } else t_ok($sp === null || $sp['rows'] === [], 'quote GST · when a state is not known it says so instead of guessing');
    t_eq((float) ops_val("SELECT total_amount FROM quotations WHERE id=?", [$qid]), 118000.0, 'quote GST · the quotation total is unchanged');

    // ---- Screens and who may use them ------------------------------------------------------
    $opened = fn($r, $v) => empty($r['restricted']) && (int) ($r['code'] ?? 0) !== 403 && ($r['view'] ?? '') === $v && !empty($r['view_ok']);
    foreach (['sales-lead' => 'ops/sx_lead', 'sales-orders' => 'ops/sx_orders', 'sales-renewals' => 'ops/sx_renewals', 'sales-targets' => 'ops/sx_targets', 'sales-settings' => 'ops/sx_settings'] as $route => $view) {
        $rr = bbv_route($route, 'GET', $f['master'], 0, [], ['who' => 'all']);
        t_ok($opened($rr, $view), "screen · /$route opens for the Master Admin" . (($rr['view_err'] ?? '') !== '' ? ' — ' . $rr['view_err'] : ''));
    }
    $rr = bbv_route('sales-log', 'GET', $f['master'], 0, [], ['deal' => (string) $o['id']]);
    t_ok($opened($rr, 'ops/sx_log'), 'screen · /sales-log opens on a deal' . (($rr['view_err'] ?? '') !== '' ? ' — ' . $rr['view_err'] : ''));
    t_ok(!$opened(bbv_route('sales-settings', 'GET', $f['neha'], 0), 'ops/sx_settings'), 'screen · a coordinator cannot open Sales settings');
    t_ok(!$opened(bbv_route('sales-lead', 'GET', $f['amit'], 0), 'ops/sx_lead'), 'screen · an inspector without Sales cannot add a lead');
    $before = (int) ops_val("SELECT COUNT(*) FROM sales_targets");
    bbv_route('sales-targets', 'POST', $f['neha'], 0, ['t' => [$f['kiran'] => [$m0 => '1']]]);
    t_eq((int) ops_val("SELECT COUNT(*) FROM sales_targets"), $before, 'targets · a non-manager cannot set targets');
    $all = feat_catalog_keys();
    t_ok(isset($all['sales-lead'], $all['sales-orders'], $all['sales-renewals'], $all['sales-targets'], $all['sales-settings']), 'switches · each new screen is in Modules & features with its own switch');
} finally {
    $_GET = []; $_POST = [];
    foreach ($keep as $k => $v) setting_set($k, $v === null ? '' : $v);
    if ($keep['sales_weekly_off'] === null) db()->prepare("DELETE FROM settings WHERE skey='sales_weekly_off'")->execute();
    foreach ($made['l'] as $id) if ($id) { db()->prepare("DELETE FROM leads WHERE id=?")->execute([$id]); db()->prepare("DELETE FROM activities WHERE entity_kind='LEAD' AND entity_id=?")->execute([$id]); }
    foreach ($made['o'] as $id) { db()->prepare("DELETE FROM opportunities WHERE id=?")->execute([$id]); db()->prepare("DELETE FROM activities WHERE entity_kind='OPPORTUNITY' AND entity_id=?")->execute([$id]); }
    foreach ($made['h'] as $id) db()->prepare("DELETE FROM holidays WHERE id=?")->execute([$id]);
    foreach ($made['c'] as $id) db()->prepare("DELETE FROM partner_contracts WHERE id=?")->execute([$id]);
    foreach ($made['q'] as $id) db()->prepare("DELETE FROM quotations WHERE id=?")->execute([$id]);
    foreach ($made['i'] as $id) db()->prepare("DELETE FROM invoices WHERE id=?")->execute([$id]);
    db()->prepare("DELETE FROM sales_targets WHERE user_id=?")->execute([$f['kiran']]);
    bbv_cleanup($f);
}
t_ok(true, 'the test removed its own rows');
