<?php
// Owner, 21 Nov 2026 — Sales redesign Stage 1 (Sales home, Today, Pipeline board,
// Sales dashboard), authorised with: "Preserve every existing field, button,
// filter and action ... All calculations remain unchanged ... All original
// permissions are preserved."
//
// Each screen is drawn twice through the production dispatcher, as the same
// person on the same data: once with the OLD view (tests/fixtures/pre_stage1/)
// and once with the NEW one. Every figure must read the same; every link, form
// and amount on the old page must be on the new one; who is refused, and who
// sees payments, must not change. Then the navigation decisions are checked.
require_once __DIR__ . '/_bbv_fixture.php';

function s1_draw($uid, $route, array $get = [], $old = false) {
    $root = dirname(__DIR__);
    $engine = $GLOBALS['__test_engine'] ?? 'sqlite';
    $env = $engine === 'sqlite'
        ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
        : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST')) . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
          . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER')) . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
    if ($old) $env .= ' STAGE1_OLD=1';
    $raw = (string) shell_exec($env . ' php ' . escapeshellarg($root . '/tests/_stage1_worker.php') . ' ' . (int) $uid . ' ' . escapeshellarg($route) . ' ' . escapeshellarg(json_encode((object) $get)) . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $l) { $d = json_decode(trim($l), true); if (is_array($d)) return $d; }
    return ['done' => 'no-json: ' . substr($raw, 0, 200), 'figs' => [], 'hrefs' => [], 'forms' => [], 'money' => [], 'nav' => [], 'bn' => [], 'rail' => null, 'refused' => false, 'len' => 0];
}

t_section('Sales Stage 1 — the same figures, links, forms and access as before, in the new look');
$f = bbv_fixture();
$made = ['leads' => [], 'opps' => []];
$keepFeat = setting_get('features_off', null);
try {
    bbv_as($f['master']);
    leads_migrate(); if (function_exists('opp_migrate')) opp_migrate();
    //  Work for the Mumbai branch manager: leads due overdue / today / this week / with no date, and two deals.
    $d = fn($n) => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));
    foreach ([['S1 Overdue Lead', -3], ['S1 Today Lead', 0], ['S1 Week Lead', 3], ['S1 NoStep Lead', null]] as [$nm, $on]) {
        $r = lead_create(['company_name' => $nm . ' ' . $f['tag'], 'contact_name' => 'Ravi', 'contact_phone' => '9825012345', 'value' => 250000,
                          'office_id' => $f['mum'], 'owner_user_id' => $f['kiran'], 'next_action' => 'Call', 'next_action_on' => $on === null ? '' : $d(max(0, $on))]);
        $made['leads'][] = $lid = (int) ($r['id'] ?? 0);
        if ($on !== null && $on < 0) db()->prepare("UPDATE leads SET next_action_on=? WHERE id=?")->execute([$d($on), $lid]);
        if ($on === null) db()->prepare("UPDATE leads SET next_action_on=NULL WHERE id=?")->execute([$lid]);
    }
    foreach ([['S1 Deal A', 1200000, -2], ['S1 Deal B', 480000, 0]] as [$nm, $v, $on]) {
        $r = opp_create(['name' => $nm . ' ' . $f['tag'], 'partner_name' => 'S1 Client ' . $f['tag'], 'value' => $v, 'office_id' => $f['mum'],
                         'owner_user_id' => $f['kiran'], 'next_action' => 'Follow up', 'next_action_on' => $d(0), 'contact_phone' => '9825012345']);
        $made['opps'][] = $oid = (int) ($r['id'] ?? 0);
        t_ok($oid > 0, 'ARMING · deal "' . $nm . '" made' . (!empty($r['err']) ? ' — ' . $r['err'] : ''));
        if ($on < 0) db()->prepare("UPDATE opportunities SET next_action_on=? WHERE id=?")->execute([$d($on), $oid]);
    }

    $people = ['Business Director' => $f['bd'], 'Branch Manager (Mumbai)' => $f['kiran'], 'Business Unit Head' => $f['sbu'], 'Master admin' => $f['master'],
               'Admin' => $f['admin'], 'Finance, Mumbai' => $f['meera'], 'Finance, central' => $f['rakesh'], 'Coordinator (no Sales)' => $f['neha']];
    $pages = [['sales-home', []], ['sales-home', ['who' => 'all']], ['sales-today', []], ['sales-today', ['who' => 'all']],
              ['sales-board', []], ['sales-board', ['who' => 'all']], ['sales-dashboard', []], ['sales-dashboard', ['p' => 'month', 'who' => 'all']]];
    $compare = function ($label, $uid, $route, $get) {
        $o = s1_draw($uid, $route, $get, true); $n = s1_draw($uid, $route, $get, false);
        $pg = '/' . $route . ($get ? '?' . http_build_query($get) : '');
        t_eq($n['done'], $o['done'], "$label · $pg — the page opens (or is refused) exactly as before");
        t_eq($n['refused'], $o['refused'], "$label · $pg — refused as before");
        $fd = []; foreach ($o['figs'] as $k => $v) if (($n['figs'][$k] ?? null) !== $v) $fd[] = "$k: old \"$v\" new \"" . ($n['figs'][$k] ?? '—') . '"';
        t_eq($fd, [], "$label · $pg — all " . count($o['figs']) . ' figures read the same');
        //  Stage 6 (owner, 21 Nov 2026 — test-book finding F-02): a Sales screen whose door (the route's
        //  module gate) refuses this person is no longer offered as a tile or in the Sales bar.
        $refusedAtDoor = function ($href) use ($uid) {
            if (!in_array($href, ['/leads', '/opportunities', '/quotes', '/inquiries', '/clients?view=sales'], true)) return false;
            $keep = $_SESSION['uid'] ?? null; bbv_as($uid);
            $base = ltrim((string) strtok($href, '?'), '/');
            $mod = ops_route_module_map()[$base] ?? ops_module_family($base);
            $no = $mod && !can("mod.$mod.view");
            if ($keep) bbv_as($keep);
            return $no;
        };
        t_eq(array_values(array_filter(array_diff(array_unique($o['hrefs']), $n['hrefs']), fn($h) => !$refusedAtDoor($h))), [], "$label · $pg — every link of the old page is on the new one (except screens that refuse this person at the door — F-02)");
        t_eq(array_values(array_diff(array_unique($o['forms']), $n['forms'])), [], "$label · $pg — every form (same address and fields) is on the new one");
        t_eq(array_values(array_diff($o['money'], $n['money'])), [], "$label · $pg — every amount of the old page is on the new one");
        return [$o, $n];
    };
    foreach ($people as $label => $uid) foreach ($pages as [$route, $get]) $compare($label, $uid, $route, $get);

    t_section('Sales Stage 1 — payments only for people who may see receivables (unchanged)');
    $a = s1_draw($f['kiran'], 'sales-today', ['who' => 'all']); $b = s1_draw($f['rakesh'], 'sales-today', []);
    bbv_as($f['kiran']); $kAr = function_exists('sx_receivables') && sx_receivables() !== null;
    t_eq(isset($a['figs']['Overdue ₹']), $kAr, 'branch manager: the "Overdue ₹" figure shows only if receivables are open to them');

    t_section('Sales Stage 1 — "Today" switched off: the figures still open the nearest screen that is on (as before)');
    setting_set('features_off', json_encode(['sales-today' => ['at' => date('c')]]));
    $sc = &settings_cache(); unset($sc['features_off']); unset($sc);
    $compare('Branch Manager, Today off', $f['kiran'], 'sales-home', []);
    if ($keepFeat === null) db()->prepare("DELETE FROM settings WHERE skey='features_off'")->execute(); else setting_set('features_off', $keepFeat);
    $sc = &settings_cache(); unset($sc['features_off']); unset($sc);

    t_section('Sales Stage 1 — navigation (owner decisions 1, 3, 4)');
    foreach ($people as $label => $uid) {
        bbv_as($uid);
        $can = function_exists('sx_can') && sx_can();
        $area = function_exists('ops_area_def') ? ops_area_def('sales') : null;
        $routes = []; foreach (($area['sections'] ?? []) as $s) foreach ($s['tiles'] as $t) $routes[] = $t['route'];
        $page = s1_draw($uid, 'sales', []);
        if (function_exists('ops_area_has') && ops_area_has('sales'))
            t_eq($page['rail'], $can ? '/sales-home' : '/sales', "$label — the left menu's Sales opens " . ($can ? 'Sales home' : 'the Sales menu page (Sales home is not open to them)'));
        else t_eq($page['rail'], null, "$label — no Sales in the left menu, as before");
        if (!$can) continue;
        $n = s1_draw($uid, 'sales-home', []);
        t_eq(array_values(array_diff($n['nav'], array_merge($routes, ['/sales']))), [], "$label — the Sales section bar lists only Sales screens they may open");
        t_eq(array_values(array_diff($n['bn'], $routes)), [], "$label — the phone bottom bar lists only screens they may open");
        t_ok(in_array('/sales', $n['nav'], true), "$label — \"All Sales screens\" (/sales) is one tap away");
    }
    $old = s1_draw($f['kiran'], 'sales', []);
    t_ok($old['done'] === 'return' && $old['len'] > 1000, 'the Sales menu page /sales still opens (nothing redirects)');

    t_section('Sales Stage 1 — the new pages use the shared kit, not the old Sales look');
    foreach (['sx_home', 'sx_today', 'sx_board', 'sx_dash'] as $v) {
        $src = (string) file_get_contents(dirname(__DIR__) . '/views/ops/' . $v . '.php');
        t_ok(strpos($src, "_sxw_ui.php") !== false && strpos($src, 'sx_kpi(') === false, "$v draws with the Sales workspace kit");
    }
} finally {
    foreach ($made['leads'] as $id) if ($id) { db()->prepare("DELETE FROM lead_stage_history WHERE lead_id=?")->execute([$id]); db()->prepare("DELETE FROM leads WHERE id=?")->execute([$id]); }
    foreach ($made['opps'] as $id) if ($id) { try { db()->prepare("DELETE FROM opportunity_stage_history WHERE opportunity_id=?")->execute([$id]); } catch (Throwable $e) {} db()->prepare("DELETE FROM opportunities WHERE id=?")->execute([$id]); }
    if ($keepFeat === null) db()->prepare("DELETE FROM settings WHERE skey='features_off'")->execute(); else setting_set('features_off', $keepFeat);
    bbv_cleanup($f);
}
t_ok(true, 'the test removed its own rows');
