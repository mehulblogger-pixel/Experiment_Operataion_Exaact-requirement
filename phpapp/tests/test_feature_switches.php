<?php
// Plan 42 step A (owner, 18 Nov 2026) — "switches to make the features on and
// off … for all the features and sub features under each module … it shall be
// easy to get back all the things as it is today".
//   * every screen in the menu is a feature with a switch, grouped by module card;
//   * core screens (jobs, approvals, users, settings, this screen …) never switch off;
//   * OFF: no tile, not in "Find a screen", refused by its address — with its
//     family (leads → lead, lead-new) but never another module's screen;
//   * the existing scattered switches are shown and saved here (same settings);
//   * "Simple start" and "Back to <date>" (the baseline captured once);
//   * only the people who may use Modules & features today (cockpit_can).
t_section('Feature switches — a switch for every screen; Simple start; back to today');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$keep = [];
foreach (['modules_off', 'features_off', 'features_baseline', 'features_changed', 'preorder_checklist_on', 'satisfaction_on', 'portal_enabled', 'careers_enabled', 'vendor_portal_enabled'] as $k)
    $keep[$k] = setting_get($k, null);
$flash = fn($r) => implode(' | ', $r['flash'] ?? []);
//  The routes run in a second process; read back what they saved.
$sync = function () use ($keep) { $c = &settings_cache(); foreach (array_keys($keep) as $k) { $v = ops_val("SELECT svalue FROM settings WHERE skey=?", [$k]); if ($v === null || $v === false) unset($c[$k]); else $c[$k] = (string) $v; } };
try {
    bbv_as($f['master']);
    $cat = feat_catalog(); $all = feat_catalog_keys($cat);
    $cardOf = []; foreach ($cat as $c => $items) foreach ($items as $i) $cardOf[$i['key']] = $c;
    t_ok(count($all) > 60, 'catalogue · every screen of the menu has a switch (' . count($all) . ')');
    t_eq([$cardOf['leads'] ?? '', $cardOf['quotes'] ?? '', $cardOf['project-costings'] ?? ''], ['sales', 'sales', 'sales'], 'catalogue · leads, quotes and project costing sit on the Sales card');
    t_eq($cardOf['invoicing'] ?? '', 'money', 'catalogue · the invoice tracker sits on the Money card');
    foreach (['jobs', 'calls', 'clients', 'vouchers', 'approvals', 'my-approvals', 'users', 'settings', 'change-password', 'workspace/setup/modules'] as $core)
        t_ok(!isset($all[$core]), "core · \"$core\" has no switch (always on)");

    // ---- Off: tile, Find a screen, address ---------------------------------------
    feat_save_off(['leads', 'project-costings', 'reports'], $all);
    $tiles = []; foreach ((ops_area_def('sales')['sections'] ?? []) as $s) foreach ($s['tiles'] as $tt) $tiles[] = trim($tt['route'], '/');
    t_ok(!in_array('leads', $tiles, true) && in_array('opportunities', $tiles, true), 'off · Leads is no longer a tile; Opportunities still is');
    $idx = array_map(fn($i) => trim($i['url'], '/'), ops_nav_index(true));
    t_ok(!in_array('leads', $idx, true) && !in_array('project-costings', $idx, true) && in_array('quotes', $idx, true), 'off · not in "Find a screen"; the rest still is');
    t_ok(!feat_on('/leads') && feat_on('/quotes'), 'off · feat_on answers per screen');
    foreach (['leads', 'lead', 'project-costings', 'project-costing', 'reports'] as $r) t_ok(feat_route_blocked($r) !== '', "address · /$r is refused");
    foreach (['leaderboard', 'quotes', 'quote', 'report-approve', 'report-file', 'report-types', 'report-reviews', 'jobs', 'job-close', 'opportunities'] as $r)
        t_eq(feat_route_blocked($r), '', "address · /$r is not touched");
    $idxPhp = file_get_contents(dirname(__DIR__) . '/index.php');
    t_ok(strpos($idxPhp, 'feat_route_blocked($route)') !== false && strpos($idxPhp, 'feat_deny(') !== false, 'address · index.php refuses a switched-off screen before any handler');
    $rules = feat_client_rules();
    t_ok(count($rules) === 3 && isset($rules[0]['s']), 'links · the page tells app.js which screens are off (to hide their links)');
    // A core screen can never be refused, even with a forged entry.
    setting_set('features_off', json_encode(['jobs' => ['p' => 'jobs', 's' => 'job', 'x' => []], 'approvals' => ['p' => 'approvals', 's' => 'approval', 'x' => []]]));
    t_ok(feat_route_blocked('jobs') === '' && feat_route_blocked('job') === '' && feat_route_blocked('approvals') === '', 'core · jobs and approvals are never refused, even by a forged setting');
    feat_save_off([], $all);
    t_ok(feat_on('/leads') && feat_route_blocked('leads') === '', 'on again · everything is back');

    // ---- The screen: Master Admin only; one card at a time; legacy switches --------
    $r = bbv_route('workspace/setup/modules', 'GET', $f['master'], 0);
    t_ok(strpos($r['out'], 'Quick start') !== false && strpos($r['out'], 'Screens in this module') !== false && strpos($r['out'], 'Pre-order checklist') !== false,
         'screen · Quick start, a switch list per module, and the existing switches');
    $before = (string) ops_val("SELECT svalue FROM settings WHERE skey='features_off'");
    bbv_route('workspace/setup/features-save', 'POST', $f['amit'], 0, ['card' => 'sales', 'on' => []]);
    t_eq((string) ops_val("SELECT svalue FROM settings WHERE skey='features_off'"), $before, 'screen · an inspector cannot switch anything');
    $salesOn = []; foreach ($cat['sales'] as $i) if ($i['key'] !== 'pipelines') $salesOn[$i['key']] = '1';
    $r = bbv_route('workspace/setup/features-save', 'POST', $f['master'], 0, ['card' => 'sales', 'on' => $salesOn, 'legacy_seen' => '1', 'legacy' => ['preorder_checklist_on' => '1']]);
    $sync();
    t_ok(isset(feat_off_entries()['pipelines']) && count(feat_off_entries()) === 1, 'screen · unticking Pipelines on the Sales card switches only it off: ' . $flash($r));
    t_eq((string) setting_get('preorder_checklist_on', ''), '1', 'screen · the existing pre-order checklist switch is saved from here (same setting)');
    $moneyOn = []; foreach ($cat['money'] as $i) $moneyOn[$i['key']] = '1';
    bbv_route('workspace/setup/features-save', 'POST', $f['master'], 0, ['card' => 'money', 'on' => $moneyOn]);
    $sync();
    t_ok(isset(feat_off_entries()['pipelines']), 'screen · saving another card leaves Sales as it was');
    $ch = json_decode((string) setting_get('features_changed', ''), true) ?: [];
    t_ok(($ch['at'] ?? '') !== '', 'history · the last change and who made it are recorded');

    // ---- Presets -------------------------------------------------------------------
    $base = json_decode((string) setting_get('features_baseline', ''), true) ?: [];
    t_ok(isset($base['at'], $base['legacy']), 'baseline · how the company was set up is captured once');
    $r = bbv_route('workspace/setup/features-preset', 'POST', $f['master'], 0, ['preset' => 'simple']);
    $sync();
    t_ok(isset(feat_off_entries()['pipelines']) && !isset(feat_off_entries()['project-costings']), 'Simple start · without "confirm" nothing changes (it asks first)');
    $r = bbv_route('workspace/setup/modules', 'GET', $f['master'], 0, [], ['preset' => 'simple']);
    t_ok(strpos($r['out'], 'Use Simple start?') !== false && strpos($r['out'], 'Nothing is deleted') !== false, 'Simple start · the confirmation lists what goes off and says nothing is deleted');
    $r = bbv_route('workspace/setup/features-preset', 'POST', $f['master'], 0, ['preset' => 'simple', 'confirm' => '1']);
    $sync();
    $offNow = array_keys(feat_off_entries());
    t_ok(in_array('project-costings', $offNow, true) && in_array('advisor', $offNow, true), 'Simple start · project costing and What to fix are off: ' . $flash($r));
    $mo = array_filter(explode(',', (string) setting_get('modules_off', '')));
    t_ok(in_array('hr', $mo, true) && in_array('connect', $mo, true), 'Simple start · Recruitment and the Marketplace modules are off');
    t_ok(!in_array('sales', $mo, true) && !in_array('operations', $mo, true) && !in_array('money', $mo, true), 'Simple start · Sales, Operations and Money stay on');
    t_eq((string) setting_get('preorder_checklist_on', ''), '0', 'Simple start · the pre-order checklist is off');
    $leads = (int) ops_val("SELECT COUNT(*) FROM leads");
    $r = bbv_route('workspace/setup/features-preset', 'POST', $f['master'], 0, ['preset' => 'baseline', 'confirm' => '1']);
    $sync();
    t_eq([(string) setting_get('modules_off', ''), (string) setting_get('features_off', '')], [(string) ($base['modules_off'] ?? ''), (string) ($base['features_off'] ?? '')], 'Back to today · modules and screens exactly as captured: ' . $flash($r));
    t_eq((string) setting_get('preorder_checklist_on', ''), (string) ($base['legacy']['preorder_checklist_on'] ?? '0'), 'Back to today · the existing switches too');
    t_eq((int) ops_val("SELECT COUNT(*) FROM leads"), $leads, 'nothing deleted · the records are all there');
    feat_migrate();
    t_eq((string) (json_decode((string) setting_get('features_baseline', ''), true)['at'] ?? ''), (string) $base['at'], 'baseline · never overwritten by a later run');
} finally {
    foreach ($keep as $k => $v) {
        if ($v === null) { try { db()->prepare("DELETE FROM settings WHERE skey=?")->execute([$k]); } catch (Throwable $e) {} $c = &settings_cache(); unset($c[$k]); unset($c); }
        else setting_set($k, (string) $v);
    }
    bbv_cleanup($f);
}
t_ok(true, 'the test put every setting back');
