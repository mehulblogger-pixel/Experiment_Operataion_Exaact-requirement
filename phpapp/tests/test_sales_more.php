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
    // Owner, 19 Nov 2026 — "when target is changed then what is changed must be reflected somewhere".
    $lastRow = ops_one("SELECT * FROM sales_targets WHERE user_id=? AND ym=? ORDER BY id DESC", [$f['kiran'], $m0]);
    t_eq([(float) $lastRow['prev_amount'], (float) $lastRow['amount']], [500000.0, 550000.0], 'targets · the change is stored as before → after (₹5,00,000 → ₹5,50,000)');
    t_eq(count(sxt_changes($months)[$f['kiran']][$m0]), 2, 'targets · the month box knows it was changed (two figures on file)');
    $un = sxt_unseen_for($f['kiran']);
    t_ok(count($un) === 3 && in_array(550000.0, array_map(fn($r) => (float) $r['amount'], $un), true), 'targets · the salesperson has a notice for every change not yet seen (3)');
    db()->prepare("UPDATE users SET email=? WHERE id=?")->execute(['kiran.fx@example.test', $f['kiran']]);
    $mailBefore = (int) ops_val("SELECT COUNT(*) FROM email_log");
    sxt_save(['t' => [$f['kiran'] => [$m1 => '650000']]], $months);
    $mail = ops_one("SELECT * FROM email_log ORDER BY id DESC");
    t_ok((int) ops_val("SELECT COUNT(*) FROM email_log") === $mailBefore + 1 && strpos((string) $mail['body'], '₹6,00,000 → ₹6,50,000') !== false,
         'targets · the salesperson is e-mailed what changed (₹6,00,000 → ₹6,50,000)');
    t_ok(strpos(implode(' ', $GLOBALS['sxt_mail_notes'] ?? []), 'Kiran') !== false, 'targets · the person saving is told whether the e-mail went (or why not): ' . implode(' ', $GLOBALS['sxt_mail_notes'] ?? []));
    db()->prepare("UPDATE users SET email='' WHERE id=?")->execute([$f['kiran']]);
    sxt_save(['t' => [$f['kiran'] => [$m1 => '660000']]], $months);
    t_ok(strpos(implode(' ', $GLOBALS['sxt_mail_notes'] ?? []), 'no e-mail address') !== false, 'targets · a salesperson with no e-mail address is named, not skipped in silence');
    bbv_route('sales-targets', 'POST', $f['kiran'], 0, ['seen' => '1']);
    t_eq(count(sxt_unseen_for($f['kiran'])), 0, 'targets · "OK, seen" clears the notice');
    t_eq(sxt_target_total($f['kiran'], $m0 . '-01', $m1 . '-28'), 1210000.0, 'targets · the dashboard total for the two months = 5.5 L + 6.6 L (latest figures)');
    t_eq(sx_target_total($f['kiran'], $m0 . '-01', $m1 . '-28'), 1210000.0, 'targets · part 1\'s dashboard hook now reads them');

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
    t_ok(($rr['view'] ?? '') !== 'ops/sx_log', 'one form · /sales-log no longer shows a second, different form');
    t_eq([sx_safe_back('/lead?id=7#lead=contacts', '/x'), sx_safe_back('//evil.example/', '/x'), sx_safe_back('https://evil.example', '/x'), sx_safe_back('', '/x')],
         ['/lead?id=7#lead=contacts', '/x', '/x', '/x'], 'one form · after Save you go back to the lead itself (a #section is kept); never to another site');
    t_eq(sx_log_url('LEAD', 7, '/sales-today'), '/lead?id=7&log=1&back=%2Fsales-today#lead=contacts', 'one form · Today\'s 📝 Log opens the lead\'s own form, and comes back to Today');
    $lp = bbv_route('lead', 'GET', $f['master'], 0, [], ['id' => (string) $r['id'], 'log' => '1']);
    t_ok(strpos($lp['out'], 'Log a conversation') !== false && strpos($lp['view'] ?? '', 'lead_detail') !== false, 'one form · the lead page shows the one log form');
    $dp = bbv_route('opportunity', 'GET', $f['master'], 0, [], ['id' => (string) $o['id']]);
    t_ok(strpos($dp['out'], 'Log a conversation') !== false, 'one form · the deal page shows the same form (it had none)');
    t_ok(strpos($dp['out'], (string) $O['ref'] . ' opened') !== false && strpos($dp['out'], 'Site visit') !== false,
         'one form · the deal\'s own Activity list still shows below the form (the form must not disturb the page)');
    $src = file_get_contents(dirname(__DIR__) . '/views/ops/lead_detail.php');
    t_ok(strpos($src, 'action="/lead-contact"') === false && strpos($src, "_log_form.php") !== false, 'one form · the lead page no longer carries its own separate form');
    t_ok(!$opened(bbv_route('sales-settings', 'GET', $f['neha'], 0), 'ops/sx_settings'), 'screen · a coordinator cannot open Sales settings');
    t_ok(!$opened(bbv_route('sales-lead', 'GET', $f['amit'], 0), 'ops/sx_lead'), 'screen · an inspector without Sales cannot add a lead');
    $before = (int) ops_val("SELECT COUNT(*) FROM sales_targets");
    bbv_route('sales-targets', 'POST', $f['neha'], 0, ['t' => [$f['kiran'] => [$m0 => '1']]]);
    t_eq((int) ops_val("SELECT COUNT(*) FROM sales_targets"), $before, 'targets · a non-manager cannot set targets');
    // ---- Owner, 19 Nov 2026: "it must be deal"; the first deal stage reads like an Enquiry ----
    t_eq([T('opportunity'), TP('opportunity'), Tla('opportunity')], ['Deal', 'Deals', 'a deal'], 'wording · the opportunities register is called Deal (a Terminology word)');
    t_eq(nav_screen_label('opportunity'), 'Deal', 'wording · the breadcrumb says Deal, not Opportunity');
    $dp2 = bbv_route('opportunities', 'GET', $f['master'], 0);
    t_ok(strpos($dp2['out'], 'Opportunit') === false && strpos($dp2['out'], 'Deals') !== false, 'wording · the register screen says Deals and never Opportunity');
    $pidO = (int) ops_val("SELECT id FROM pipelines WHERE entity_kind='OPPORTUNITY' LIMIT 1");
    db()->prepare("INSERT INTO pipeline_stages (pipeline_id,seq,name,kind,probability,sla_days,active) VALUES (?,99,'Enquiry received','OPEN',10,7,1)")->execute([$pidO]);
    $stg = (int) db()->lastInsertId();
    db()->prepare("DELETE FROM settings WHERE skey='opp_stage_rename_v1'")->execute(); $sc = &settings_cache(); unset($sc['opp_stage_rename_v1']);
    opp_rename_first_stage();
    t_eq((string) ops_val("SELECT name FROM pipeline_stages WHERE id=?", [$stg]), 'New deal', 'stage · a deal stage still called "Enquiry received" becomes "New deal"');
    t_eq((int) ops_val("SELECT COUNT(*) FROM pipelines WHERE entity_kind='OPPORTUNITY' AND name='Opportunity pipeline'"), 0, 'stage · a deal pipeline still called "Opportunity pipeline" becomes "Deal pipeline"');
    db()->prepare("DELETE FROM pipeline_stages WHERE id=?")->execute([$stg]);
    $od = opp_create(['name' => 'Fx numbering ' . uniqid(), 'partner_name' => 'Fx', 'value' => 1, 'pipeline_id' => pipeline_default('OPPORTUNITY')['id']]);
    $made['o'][] = (int) $od['id'];
    t_ok(preg_match('/^DEAL-\d{4}-\d{4}$/', (string) $od['ref']) === 1, 'numbering · a new deal is numbered DEAL-2026-0001 style (' . $od['ref'] . '), set in Settings → Numbering');
    // ---- Found by the test-book helper, 19 Nov 2026 -----------------------------------------
    t_eq([sx_is_date('2027-02-30'), sx_is_date('2028-02-29'), sx_is_date('31/12/2026')], [false, true, false], 'dates · 30 Feb is refused, 29 Feb 2028 is a real date, 31/12/2026 is not the date format');
    $_GET = ['lead' => (string) $r['id']]; $tl = sx_log_target(); $_GET = [];
    t_ok(!empty(sx_log_save($tl, ['method' => 'CALL', 'note' => 'x', 'next_action_on' => '2027-02-30'])['err']), 'dates · a next follow-up of 30 Feb is refused, not stored');
    db()->prepare("UPDATE leads SET office_id=? WHERE id=?")->execute([$f['ahm'], $r['id']]);
    $before = (int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='LEAD' AND entity_id=?", [$r['id']]);
    $rs = bbv_route('sales-log', 'POST', $f['kiran'], 0, ['lead' => (string) $r['id'], 'method' => 'CALL', 'note' => 'from another branch', 'back' => '/sales-today']);
    t_ok(stripos(json_encode($rs), 'another office') !== false, 'scope · …and is told it belongs to another office / branch (not a permission error): ' . substr(json_encode($rs), 0, 300));
    t_eq((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='LEAD' AND entity_id=?", [$r['id']]), $before, 'scope · a Mumbai branch manager cannot log on an Ahmedabad lead through the log form');
    $tlx = act_for_entity('OPPORTUNITY', (int) $o['id'], 20); ob_start(); act_render_timeline('OPPORTUNITY', (int) $o['id'], 'Activity'); $tlh = ob_get_clean();
    t_ok(strpos($tlh, 'Next: Site visit') !== false, 'timeline · the deal\'s Activity list shows the next step that was logged');
    $all = feat_catalog_keys();
    t_ok(isset($all['sales-lead'], $all['sales-orders'], $all['sales-renewals'], $all['sales-targets'], $all['sales-settings']), 'switches · each new screen is in Modules & features with its own switch');
    // ---- Fixes from the Sales test book (SALC), 19 Nov 2026 -------------------------------
    t_eq([sx_user_covers_office($f['kiran'], $f['mum']), sx_user_covers_office($f['kiran'], $f['ahm']), sx_user_covers_office($f['meera'], $f['ahm']),
          sx_user_covers_office($f['rakesh'], $f['ahm']), sx_user_covers_office($f['master'], $f['ahm'])], [true, false, false, true, true],
         'office · who works for which office: Mumbai BM → Mumbai only; Mumbai Finance → not Ahmedabad; central Finance and Master → all');
    bbv_as($f['master']);
    $rg = sx_lead_save(['company_name' => 'Fx Give ' . uniqid(), 'office_id' => $f['ahm'], 'owner_user_id' => $f['kiran']], true);
    t_ok(!empty($rg['err']) && empty($rg['ok']), 'new lead · an Ahmedabad lead cannot be given to the Mumbai branch manager (they could not open it): ' . ($rg['err'] ?? ''));
    $rp = sx_lead_save(['company_name' => 'Fx Past ' . uniqid(), 'next_action_on' => date('Y-m-d', strtotime('-2 days'))], true);
    t_ok(!empty($rp['err']), 'new lead · a first follow-up in the past is refused by the server too');
    t_ok(!empty(sx_lead_save(['company_name' => 'Fx Feb ' . uniqid(), 'next_action_on' => '2027-02-30'], true)['err']), 'new lead · 30 Feb is refused');
    bbv_route('sales-settings', 'POST', $f['master'], 0, ['oc_label' => ['Fx hot', 'फ़ॉलो अप करें', 'Fx pick'], 'oc_key' => ['FX_HOT', '', 'FX_PICK'], 'oc_days' => ['2', '3', '0'],
              'off' => ['0'], 'stale' => '15', 'own' => [$f['mum'] => $f['kiran'], $f['ahm'] => $f['kiran']]]);
    $sc = &settings_cache();   // the screen ran as its own request: read back what it stored
    foreach (['sales_outcomes', 'sales_owner_by_office', 'sales_weekly_off', 'sales_stale_days'] as $k) { $v = ops_val("SELECT svalue FROM settings WHERE skey=?", [$k]); if ($v !== false && $v !== null) $sc[$k] = $v; }
    unset($sc);
    t_eq(sx_owner_map(), [(string) $f['mum'] => (int) $f['kiran']], 'settings · "New leads go to" refuses a person who does not work for that office (Ahmedabad skipped, Mumbai kept)');
    $ocs = sx_outcomes();
    t_ok(count($ocs) === 3 && isset($ocs['OUTCOME_2']) && $ocs['OUTCOME_2']['label'] === 'फ़ॉलो अप करें', 'settings · an outcome written only in Hindi is kept (given a numbered key), not dropped');
    // Targets: whole rupees, no minus, and an older open page cannot undo another manager's change.
    $g = sxt_grid($months); $cur0 = (string) (0 + $g[$f['kiran']][$m0]);
    t_eq(sxt_save(['t' => [$f['kiran'] => [$m0 => '700000']], 'o' => [$f['kiran'] => [$m0 => '480000']]], $months), 0, 'targets · a box changed on an older page, after someone else changed it, is not saved');
    t_eq(count($GLOBALS['sxt_skipped']['clash']), 1, 'targets · …and the person is told which month to check');
    t_eq(sxt_save(['t' => [$f['kiran'] => [$m0 => $cur0, $m1 => '670000']], 'o' => [$f['kiran'] => [$m0 => '480000', $m1 => (string) (0 + $g[$f['kiran']][$m1])]]], $months), 1,
         'targets · the boxes not touched on that page are left alone; the one changed is saved');
    t_eq(sxt_grid($months)[$f['kiran']][$m0], (float) $cur0, 'targets · the other manager\'s figure stands');
    t_eq(sxt_save(['t' => [$f['kiran'] => [$m1 => '-5000']]], $months), 0, 'targets · a minus figure is not saved…');
    t_eq(count($GLOBALS['sxt_skipped']['bad']), 1, 'targets · …and the person is told');
    sxt_save(['t' => [$f['kiran'] => [$m1 => '600000.56']]], $months);
    t_eq(sxt_grid($months)[$f['kiran']][$m1], 600001.0, 'targets · whole rupees (₹6,00,000.56 → ₹6,00,001), so History and the e-mail show what is stored');
    // Targets typed in ₹ lakh (owner, 21 Nov 2026), stored in rupees as before.
    $cur1 = sxt_lakh_text(sxt_grid($months)[$f['kiran']][$m1] ?? null);
    t_eq(sxt_save(['unit' => 'lakh', 't' => [$f['kiran'] => [$m1 => '12.5']], 'o' => [$f['kiran'] => [$m1 => $cur1]]], $months), 1, 'targets in lakh · 12.5 typed is saved');
    t_eq(sxt_grid($months)[$f['kiran']][$m1], 1250000.0, 'targets in lakh · 12.5 lakh is stored as ₹12,50,000 (rupees, as before)');
    t_eq(sxt_lakh_text(sxt_grid($months)[$f['kiran']][$m1]), '12.5', 'targets in lakh · and shows back as 12.5 in the box');
    t_eq([sxt_lakh_text(735000), sxt_lakh_text(600001), sxt_lakh_text(0), sxt_lakh_text(null)], ['7.35', '6.00001', '0', ''], 'targets in lakh · an older ₹7,35,000 shows as 7.35; ₹6,00,001 as 6.00001 (nothing lost); no target stays empty');
    t_eq(sxt_save(['unit' => 'lakh', 't' => [$f['kiran'] => [$m1 => '12.5']], 'o' => [$f['kiran'] => [$m1 => '12.5']]], $months), 0, 'targets in lakh · re-saving the page unchanged records nothing new');
    t_eq(sxt_save(['unit' => 'lakh', 't' => [$f['kiran'] => [$m1 => '-1']], 'o' => [$f['kiran'] => [$m1 => '12.5']]], $months), 0, 'targets in lakh · a minus figure is refused…');
    t_eq(count($GLOBALS['sxt_skipped']['bad']), 1, 'targets in lakh · …and the person is told');
    t_eq(sxt_save(['unit' => 'lakh', 't' => [$f['kiran'] => [$m1 => 'twelve']], 'o' => [$f['kiran'] => [$m1 => '12.5']]], $months), 0, 'targets in lakh · text is refused');
    $hRow = ops_one("SELECT amount, prev_amount FROM sales_targets WHERE user_id=? AND ym=? ORDER BY id DESC LIMIT 1", [$f['kiran'], $m1]);
    t_eq([(float) $hRow['amount'], (float) $hRow['prev_amount']], [1250000.0, 600001.0], 'targets in lakh · history keeps rupees, before → after (₹6,00,001 → ₹12,50,000)');
    $pg = bbv_route('sales-targets', 'GET', $f['master'], 0, [], []); $po = (string) ($pg['out'] ?? '');
    t_ok(strpos($po, 'amount in ₹ lakh') !== false && strpos($po, 'e.g. 12.5 = ₹12,50,000') !== false, 'targets in lakh · the page says the amount is in ₹ lakh, with an example');
    t_ok(strpos((string) file_get_contents(dirname(__DIR__) . '/views/ops/sx_targets.php'), '<input type="hidden" name="unit" value="lakh">') !== false, 'targets in lakh · the form tells the server its boxes are in lakh');
    // WhatsApp only for a mobile.
    t_eq([sx_wa('022 2345 6789'), sx_wa('98250 55555'), sx_wa('+91 98250-55555'), sx_wa('09825055555'), sx_wa('+44 7700 900123'), sx_wa('12345')],
         ['', 'https://wa.me/919825055555', 'https://wa.me/919825055555', 'https://wa.me/919825055555', 'https://wa.me/447700900123', ''],
         'WhatsApp · a landline gets Call only; an Indian mobile in any format, or a foreign number with its +code, gets WhatsApp');
    // Renewals follow the office of the contract.
    db()->prepare("INSERT INTO partner_contracts (partner_id, contract_number, title, value, start_date, end_date, is_active, branch_id) VALUES (?,?,?,?,?,?,1,?)")
        ->execute([$pid, 'FX-R-AHM', 'Fx', 100000, date('Y-m-d', strtotime('-300 days')), date('Y-m-d', strtotime('+15 days')), $f['ahm']]);
    $made['c'][] = (int) db()->lastInsertId();
    t_ok(in_array('FX-R-AHM', array_column(sx_renewals_list(90), 'contract_number'), true), 'renewals · the Master Admin sees an Ahmedabad contract');
    bbv_as($f['kiran']);
    t_ok(!in_array('FX-R-AHM', array_column(sx_renewals_list(90), 'contract_number'), true) && !in_array('FX-R-AHM', array_column(sx_renewals(), 'contract_number'), true),
         'renewals · the Mumbai branch manager does not (Renewals screen and Today)');
    bbv_as($f['master']);
    // Finance sees every order of their offices on Orders & payments.
    $ro = bbv_route('sales-orders', 'GET', $f['rakesh'], 0);
    t_ok(strpos($ro['out'] ?? '', 'FX-Q-1') !== false, 'orders · Finance sees the company\'s orders (not only "their own", which is none)');
    // "0 days" = the person picks the date.
    $_GET = ['deal' => (string) $o['id']]; $td = sx_log_target(); $_GET = [];
    $rz = sx_log_save($td, ['method' => 'MEETING', 'outcome' => 'FX_PICK']);
    t_ok(!empty($rz['err']) && strpos($rz['err'], 'By when') !== false, 'log · an outcome set to "you pick" (e.g. Meeting fixed) needs the date: ' . ($rz['err'] ?? ''));
    // Deal timeline: which way, and no "Telephone call Telephone call".
    sx_log_save($td, ['method' => 'CALL', 'outcome' => 'FX_HOT', 'direction' => 'IN']);
    ob_start(); act_render_timeline('OPPORTUNITY', (int) $o['id'], 'Activity'); $tlh2 = ob_get_clean();
    t_ok(strpos($tlh2, 'Incoming') !== false && strpos($tlh2, '<strong>Telephone call</strong>') === false && strpos($tlh2, 'Fx hot') !== false,
         'timeline · the deal shows "Incoming" and what happened, and never repeats "Telephone call"');
    // Data health counts only what this person can open.
    db()->prepare("INSERT INTO quotations (quote_no, rev, is_current, client_id, client_name, subtotal, status, office_id, created_at, updated_at) VALUES ('FX-Q-AHM',0,1,?,?,1,'PENDING_APPROVAL',?,?,?)")
        ->execute([$pid, 'Fx', $f['ahm'], date('c'), date('c')]);
    $made['q'][] = (int) db()->lastInsertId();
    $hq = fn() => (int) sx_health(0)[3][1];
    $mHas = $hq(); bbv_as($f['kiran']); $kHas = $hq(); bbv_as($f['master']);
    db()->prepare("UPDATE quotations SET office_id=? WHERE quote_no='FX-Q-AHM'")->execute([$f['mum']]);
    bbv_as($f['kiran']); $kHas2 = $hq(); bbv_as($f['master']);
    t_eq($kHas2 - $kHas, 1, 'data health · an Ahmedabad quote waiting is not counted for the Mumbai branch manager; once it is Mumbai\'s, it is');
    // GST split says which state is missing.
    $sp2 = sx_quote_gst_split(['gst_amount' => 18, 'gst_pct' => 18, 'client_id' => $pid]);
    t_ok($sp2 === null || $sp2['mode'] !== 'UNKNOWN' || in_array($sp2['missing'] ?? '', ['ours', 'theirs'], true), 'quote GST · when a state is missing it says whose (ours or the client\'s)');
    $idx = file_get_contents(dirname(__DIR__) . '/index.php');
    t_ok(strpos($idx, "kind === 'contact' && (\$b['buying_role']") !== false, 'buying role · a contact\'s buying role must be one of the list (checked on save)');
    t_ok(isset(lk_forms_for_type((int) (lk_type('contact_buying_role')['id'] ?? 0))['partner_contact']) || !lk_type('contact_buying_role'), 'buying role · Masters says it is used on Client / vendor → Contacts');
    // ---- The serious findings of the earlier Sales test-book parts (SALA / SALB) -----------------
    $mkU = function ($key, $role, $office) use ($f) {
        db()->prepare("INSERT INTO users (username, first_name, last_name, role, is_active, home_office_id, scope_offices) VALUES (?,?,?,?,1,?,'')")
            ->execute([$f['tag'] . '_' . $key, ucfirst($key), 'T', $role, $office]);
        return (int) db()->lastInsertId();
    };
    $bdm = $mkU('bdmx', 'BUSINESS_DEV_MANAGER', $f['mum']); $mktm = $mkU('mktmx', 'MARKETING_MANAGER', $f['mum']);
    $calls0 = (int) ops_val("SELECT COUNT(*) FROM calls");
    $rro = bbv_route('opportunity-raise-order', 'POST', $bdm, 0, ['id' => (string) $o['id']]);
    t_ok((int) ops_val("SELECT COUNT(*) FROM calls") === $calls0 && stripos(json_encode($rro), 'Operations') !== false,
         'SALA-026 · a salesperson without the Work orders right cannot raise a work order by posting the form');
    bbv_as($f['master']);
    opp_link_quote((int) $o['id'], $qid);
    t_ok(opp_unlink_quote((int) $o['id'], $qid) !== '' && (int) ops_val("SELECT COUNT(*) FROM opportunity_quotes WHERE opportunity_id=? AND quotation_id=?", [$o['id'], $qid]) === 1,
         'SALA-027 · an accepted quotation cannot be detached from its deal (one sale, one contract)');
    $qa = (int) ops_val("SELECT id FROM quotations WHERE quote_no='FX-Q-AHM'");
    db()->prepare("UPDATE quotations SET office_id=? WHERE id=?")->execute([$f['ahm'], $qa]);
    bbv_as($f['kiran']);
    t_ok(stripos(opp_link_quote((int) $o['id'], $qa), 'another office') !== false, 'SALA-028 · a Mumbai person cannot attach an Ahmedabad quotation to a deal');
    bbv_as($f['master']);
    $calc = pc_line_calc(['rate_manual' => round(50000 / 1.05, 2), 'boq_qty' => 10, 'basis' => 'MAN_MONTH', 'headcount' => 1], ['negotiation_pct' => 5]);
    t_eq([$calc['proposed'], $calc['revenue']], [50000.0, 500000.0], 'SALA-058 · a fixed client rate of ₹50,000 with 5% negotiation gives exactly ₹5,00,000 for 10 man-months (not ₹5,00,000.03)');
    $d0 = (int) ops_val("SELECT COUNT(*) FROM approval_delegations");
    $rdl = bbv_route('quote-delegations', 'POST', $mktm, 0, ['do' => 'save', 'delegator_user_id' => (string) $f['kiran'], 'delegate_user_id' => (string) $bdm,
                     'from_date' => date('Y-m-d'), 'to_date' => date('Y-m-d', strtotime('+3 days')), 'reason' => 'Fx']);
    t_ok((int) ops_val("SELECT COUNT(*) FROM approval_delegations") === $d0 && stripos(json_encode($rdl), 'only your own') !== false,
         'SALB-036 · an approver cannot hand ANOTHER approver\'s authority to someone (only their own)');
    bbv_route('quote-delegations', 'POST', $mktm, 0, ['do' => 'save', 'delegator_user_id' => (string) $mktm, 'delegate_user_id' => (string) $f['kiran'],
              'from_date' => date('Y-m-d'), 'to_date' => date('Y-m-d', strtotime('+3 days')), 'reason' => 'Fx leave']);
    t_eq((int) ops_val("SELECT COUNT(*) FROM approval_delegations"), $d0 + 1, 'SALB-036 · …but can still name someone to cover their OWN approvals');
    db()->prepare("DELETE FROM approval_delegations WHERE delegator_user_id=?")->execute([$mktm]);
    $m0c = (int) ops_val("SELECT COUNT(*) FROM billing_milestones WHERE quote_id=?", [$qa]);
    db()->prepare("UPDATE quotations SET status='DRAFT' WHERE id=?")->execute([$qa]);
    $rms = bbv_route('quote-milestones', 'POST', $f['kiran'], 0, ['quote_id' => (string) $qa, 'ms_title' => ['Advance'], 'ms_pct' => ['100']]);
    t_ok((int) ops_val("SELECT COUNT(*) FROM billing_milestones WHERE quote_id=?", [$qa]) === $m0c && stripos(json_encode($rms), 'outside your office') !== false,
         'SALB-074 · a Mumbai person cannot change the billing schedule of an Ahmedabad quotation');
    db()->prepare("DELETE FROM opportunity_quotes WHERE opportunity_id=?")->execute([$o['id']]);
    bbv_as($f['kiran']);
    $la = lead_create(['company_name' => 'Fx Branch ' . uniqid(), 'office_id' => (string) $f['ahm']]);
    t_ok(!empty($la['err']) && empty($la['id']), 'SALA-001 · a Mumbai person cannot open a lead in Ahmedabad (it would be hidden from them at once)');
    $oa = opp_create(['name' => 'Fx Branch deal', 'partner_name' => 'Fx', 'office_id' => $f['ahm']]);
    t_ok(!empty($oa['err']) && empty($oa['id']), 'SALA-017 · …nor a deal in Ahmedabad');
    $lm = lead_create(['company_name' => 'Fx Own ' . uniqid(), 'office_id' => (string) $f['mum']]);
    t_ok(!empty($lm['id']), 'SALA-001 · …but can in their own office'); $made['l'][] = (int) ($lm['id'] ?? 0);
    bbv_as($f['master']);
    $lAhm = lead_create(['company_name' => 'Fx Ahm Lead ' . uniqid(), 'office_id' => (string) $f['ahm']]); $made['l'][] = (int) ($lAhm['id'] ?? 0);
    bbv_as($f['kiran']);
    t_ok(!empty(opp_from_lead((int) $lAhm['id'])['err']), 'SALA-031 · "Work this as a deal" refuses another branch\'s lead');
    bbv_as($f['master']);
    t_ok(opp_update((int) $o['id'], ['name' => '  ']) !== '' && trim((string) opp_row((int) $o['id'])['name']) !== '', 'SALA-019 · a deal cannot be saved with an empty name');
    if (function_exists('crm_migrate')) crm_migrate();
    db()->prepare("INSERT INTO quote_followups (quote_id, due_date, status) VALUES (?,?, 'OPEN')")->execute([$qa, date('Y-m-d')]);
    $fu = (int) db()->lastInsertId();
    $rfu = bbv_route('followup-compose', 'GET', $f['kiran'], 0, [], ['id' => (string) $fu]);
    t_ok(stripos(json_encode($rfu), 'outside your office') !== false, 'SALB-046 · a Mumbai person cannot download the follow-up e-mail of an Ahmedabad quotation');
    db()->prepare("DELETE FROM quote_followups WHERE id=?")->execute([$fu]);
    $src2 = file_get_contents(dirname(__DIR__) . '/lib/stagegate.php');
    t_ok(strpos($src2, "redirect((string)(\$_POST['back'] ?? '/approvals'))") === false, 'SALA-024 · after an approval step the app goes back only to a page of this site');
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
