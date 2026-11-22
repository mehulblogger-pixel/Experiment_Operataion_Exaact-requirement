<?php
// Operations Batch 1 (owner, 22 Nov 2026): Operations home in the Sales look, with the
// owner's six answers —
//   1. one area-bar colour for every area, from the theme (--area-bar);
//   2. the phone bottom bar per area is a company setting ("admin can set that");
//   3. Punch in / out one tap away on the phone for anyone who may punch TODAY — under the
//      existing rules only (nobody who could not punch before can now);
//   4. the "Nothing is removed" note is gone;
//   5. the TPIA dashboards (/analytics) are linked from the Operations bar, for those who may open them;
//   6. built as the approved design; the Inspector's /operations is unchanged.
t_section('Operations Batch 1 — Operations home in the Sales look');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$pdo = db();
$hadBn = setting_get('area_bottom_bar', null);
$made = ['jobs' => [], 'visits' => []];
putenv('BBV_RAW=1');
try {
    bbv_as($f['master']);
    if (function_exists('schedule_migrate')) schedule_migrate();
    $page = function ($uid) { $r = bbv_route('operations', 'GET', $uid, 0, [], []); return [(string) ($r['html'] ?? ''), (string) ($r['out'] ?? ''), $r]; };

    // ---- 1 + 6: the page, the shared bar, the colour token --------------------------------
    [$h, $txt] = $page($f['bd']);
    t_ok($h !== '' && strpos($h, 'class="rec sxw ox has-bn"') !== false, 'Director · /operations opens in the Sales kit');
    t_ok(strpos($h, 'aria-label="Operations screens"') !== false && strpos($h, 'All Operations screens') !== false, 'Director · the Operations area bar is drawn, with "All Operations screens"');
    t_ok(strpos($h, 'id="screens"') !== false && strpos($h, 'href="#screens"') !== false, '"All Operations screens" lands on the tabs of this page');
    $css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/app.css');
    t_ok(strpos($css, ':root{--area-bar:color-mix(in srgb,var(--brand) 78%,#000)}') !== false && strpos($css, '.sxw{--sxw-teal:var(--area-bar)') !== false,
         'one theme-based bar colour (--area-bar) on :root, used by every area bar');
    foreach (['New requests', 'Unscheduled', 'Clarification open', 'Ready to schedule', 'Overdue', 'Critical', 'Report pending'] as $k)
        t_ok(strpos($h, 'data-k="' . $k . '"') !== false, 'figure card kept: ' . $k);
    t_ok(strpos($h, 'class="kpi sxw-kpi') !== false, 'the figures use the app-wide KPI card');
    foreach (['Backlog &amp; registers', 'Work intake &amp; delivery', 'TPIA service lines', 'Controls, time &amp; people'] as $tab)
        t_ok(strpos($h, 'data-tab="' . $tab . '"') !== false, 'tab kept: ' . html_entity_decode($tab));
    foreach (['/call-new', '/schedule', '/my-work', '/operations?dr=fy', '/operations#backlog', '/operations#schedule'] as $href)
        t_ok(strpos($h, 'href="' . $href . '"') !== false, 'Director · link kept: ' . $href);

    // ---- 4: the note is gone -----------------------------------------------------------------
    t_ok(stripos($txt, 'Nothing is removed') === false && stripos($txt, 'lived inside the old') === false, 'the "Nothing is removed" note is gone');

    // ---- the bar per role: only screens the person may open ------------------------------
    $barOf = function ($html) { preg_match('#<nav class="sxw-nav" aria-label="Operations screens">(.*?)</nav>#s', $html, $m); preg_match_all('#href="([^"]+)"#', $m[1] ?? '', $mm); return $mm[1] ?? []; };
    foreach (['bd' => 'Director', 'sunil' => 'Ops Manager', 'kiran' => 'Branch Manager', 'neha' => 'Coordinator', 'meera' => 'Finance (Mumbai)'] as $who => $lab) {
        [$h2] = $page($f[$who]);
        if ($h2 === '' || strpos($h2, 'Operations screens') === false) { t_ok(true, $lab . ' · cannot open Operations (unchanged rule) — no bar'); continue; }
        bbv_as($f[$who]);
        $tiles = area_screen_tiles('operations');
        $bar = $barOf($h2);
        $extra = array_values(array_filter($bar, fn($r) => $r !== '#screens' && !isset($tiles[$r])));
        t_eq($extra, [], $lab . ' · every bar link is a screen they may open');
        //  5 — TPIA dashboards only for someone the analytics hub admits.
        $tapi = function_exists('tapi_can') && tapi_can();
        t_eq(in_array('/analytics', $bar, true), $tapi, $lab . ' · TPIA dashboards link ' . ($tapi ? 'shown (may open /analytics)' : 'hidden (may not open /analytics)'));
        bbv_as($f['master']);
    }

    // ---- 6: the Inspector's /operations is unchanged (the access notice) -----------------
    $ri = bbv_route('operations', 'GET', $f['amit'], 0, [], []);
    t_ok(stripos((string) ($ri['out'] ?? ''), 'isn’t available to your role') !== false || stripos((string) ($ri['out'] ?? ''), "isn't available") !== false,
         'Inspector · /operations still says "This area isn’t available to your role"');

    // ---- 2: the phone bottom bar is a setting --------------------------------------------
    setting_set('area_bottom_bar', '');
    t_eq(area_bn_routes('operations', 'office'), ['/operations', '/calls', '/jobs', '/schedule', '/my-work'], 'default office bar: Home · calls · jobs · Schedule · My work');
    t_eq(area_bn_routes('operations', 'field'), ['/', '/my-jobs', '/vouchers', '/my-work'], 'default field bar: Home · My Jobs · Voucher · My Work');
    t_eq(area_bn_routes('sales', 'office'), ['/sales-home', '/leads', '/sales-board', '/sales-today', '/sales-dashboard'], 'Sales keeps today\'s bar');
    bbv_as($f['master']);
    $err = area_bn_save(['operations' => ['office' => ['/schedule', '/calls', '/calls', '', '/operations', '/not-a-screen', '/jobs', '/my-work']]]);
    t_eq(area_bn_routes('operations', 'office'), ['/schedule', '/calls', '/operations', '/jobs', '/my-work'], 'saved in the order picked, repeats and blanks dropped');
    t_ok(strpos($err, '/not-a-screen') !== false, 'a route the bar does not offer is refused, with the reason');
    t_eq(area_bn_routes('sales', 'office'), ['/sales-home', '/leads', '/sales-board', '/sales-today', '/sales-dashboard'], 'saving one area leaves the others as they were');
    $err = area_bn_save(['operations' => ['office' => ['/operations', '/calls', '/jobs', '/schedule', '/availability', '/my-work']]]);
    t_eq(count(area_bn_routes('operations', 'office')), AREA_BN_MAX, 'at most ' . AREA_BN_MAX . ' items are kept');
    t_ok(strpos($err, 'more than') !== false, 'more than ' . AREA_BN_MAX . ' is reported');
    //  A person sees only the items they may open.
    area_bn_save(['operations' => ['office' => ['/operations', '/calls', '/contract-openings', '/my-work']]]);
    bbv_as($f['neha']);
    $mayOpen = isset(area_screen_tiles('operations')['/contract-openings']);
    $items = array_column(area_bn_items('operations', 'office'), 0);
    t_eq(in_array('/contract-openings', $items, true), $mayOpen, 'Coordinator · "Contract openings" in the bar only if they may open it (' . ($mayOpen ? 'may' : 'may not') . ')');
    t_ok(in_array('/my-work', $items, true), 'My Work is offered to everyone');
    bbv_as($f['master']);
    //  The Settings screen offers it.
    $rs = bbv_route('settings', 'GET', $f['master'], 0, [], []);
    $hs = (string) ($rs['html'] ?? '');
    t_ok(strpos($hs, 'id="bottom-bar"') !== false && strpos($hs, 'name="bn[operations][office][]"') !== false && strpos($hs, 'name="bn[operations][field][]"') !== false,
         'Settings → Display & terms → "Phone bottom bar" offers the Operations office and field bars');
    t_eq(substr_count($hs, 'name="bn[operations][office][]"'), AREA_BN_MAX, AREA_BN_MAX . ' positions per bar');
    setting_set('area_bottom_bar', '');

    // ---- 3: Punch — one tap, existing rules only -----------------------------------------
    bbv_as($f['amit']);
    t_eq(area_bn_punch(), null, 'Inspector with no visit today · no Punch button (nothing to punch)');
    $pdo->prepare("INSERT INTO jobs (inspector_id, scheduled_date, stage, created_at) VALUES (?,?, 'SCHEDULED', ?)")->execute([$f['amit_ins'], date('Y-m-d'), date('c')]);
    $jid = (int) $pdo->lastInsertId(); $made['jobs'][] = $jid;
    $pdo->prepare("INSERT INTO job_visits (job_id, visit_date, inspector_id, status) VALUES (?,?,?, 'PLANNED')")->execute([$jid, date('Y-m-d'), $f['amit_ins']]);
    $made['visits'][] = (int) $pdo->lastInsertId();
    $p = area_bn_punch();
    t_ok($p !== null && $p['label'] === 'Punch in' && $p['next'] === 'ENTRY', 'Inspector with a visit today · "Punch in" in the bottom bar');
    t_eq($p['href'] ?? '', '/my-work#punchBar', 'where the page has no punch bar, the button opens My Work at the punch bar');
    $items = array_column(area_bn_field_items(), 2);
    t_eq($items, ['Home', 'My Jobs', 'Voucher', 'My Work'], 'field bar: Home · My Jobs · Voucher (the words it always had) · My Work');
    //  The field bar as drawn by the layout.
    $isInsp = true; $_SERVER['REQUEST_URI'] = '/my-jobs';
    ob_start(); include dirname(__DIR__) . '/views/layout_bottom.php'; $lb = (string) ob_get_clean();
    t_ok(strpos($lb, 'class="botnav"') !== false && strpos($lb, 'data-punch="ENTRY"') !== false && strpos($lb, 'href="/my-work"') !== false,
         'the field bar on the page carries My Work and the Punch button');
    $js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/app.js');
    t_ok(strpos($js, "closest('a[data-punch]')") !== false && strpos($js, "'pbOut' : 'pbIn'") !== false, 'the button drives the existing punch bar (same photo, location and server checks)');
    //  Not widened: an office login with no team-member record cannot punch, visit or not.
    bbv_as($f['neha']);
    t_eq(area_bn_punch(), null, 'Coordinator without a team-member record · no Punch (the rule is not widened)');
    bbv_as($f['bd']);
    t_eq(area_bn_punch(), null, 'Director without a team-member record · no Punch');
    //  Once punched in, the button says Punch out.
    bbv_as($f['amit']);
    $pdo->prepare("INSERT INTO site_visits (job_id, inspector_id, kind, at) VALUES (?,?, 'ENTRY', ?)")->execute([$jid, $f['amit_ins'], date('Y-m-d') . ' 09:00:00']);
    $sv = (int) $pdo->lastInsertId();
    t_eq(area_bn_punch()['label'] ?? '', 'Punch out', 'after arriving, the button says "Punch out"');
    $pdo->exec("DELETE FROM site_visits WHERE id=" . $sv);
} finally {
    putenv('BBV_RAW');
    $pdo = db();
    if ($made['visits']) $pdo->exec("DELETE FROM job_visits WHERE id IN (" . implode(',', array_map('intval', $made['visits'])) . ")");
    if ($made['jobs']) $pdo->exec("DELETE FROM jobs WHERE id IN (" . implode(',', array_map('intval', $made['jobs'])) . ")");
    setting_set('area_bottom_bar', $hadBn === null ? '' : (string) $hadBn);
    $_SERVER['REQUEST_URI'] = '/';
    bbv_cleanup($f);
}
