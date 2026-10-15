<?php
// ============================================================================
//  EVERY SCREEN KNOWS WHERE IT SITS                                    (R-24)
// ============================================================================
//  The application had no model of its own shape: 277 hand-typed breadcrumbs,
//  80 different parents, nothing checking any of them, and 343 of 405 screens
//  with no way back at all. Worse, 228 of 404 screens are reachable from more
//  than one place, so a hard-coded parent is wrong by construction — the Job
//  screen is linked from thirty different files.
//
//  lib/nav.php answers "where does this screen sit?" from three maps that
//  already existed. This file holds it to three promises:
//
//    1. it covers essentially every route the router serves
//    2. it NEVER points at a screen the router does not serve
//    3. Back goes where you came from, not to a fixed guess
//
//  Promise 2 matters most. A trail nobody can trust is worse than a short one.
// ============================================================================

//  A route is "served" if ANY lib file dispatches it, not just ops.php. The
//  first draft of this test read ops.php alone and reported /equipment as a dead
//  link — it is served by ops_equipment() in lib/equipment.php. A test that
//  defines the application as one file will keep finding ghosts.
$ROUTES = [];
foreach (glob(__DIR__ . '/../lib/*.php') as $f) {
    $src = file_get_contents($f);
    preg_match_all('/\$route === \'([a-z0-9\/_-]+)\'/', $src, $m);
    foreach ($m[1] as $r) $ROUTES[] = $r;
    preg_match_all('/in_array\(\$route,\s*\[([^\]]*)\]/', $src, $im);
    foreach ($im[1] as $blk) {
        preg_match_all('/\'([a-z0-9\/_-]{2,})\'/', $blk, $x);
        foreach ($x[1] as $r) $ROUTES[] = $r;
    }
}
$ROUTES = array_values(array_unique(array_filter($ROUTES, fn($r) => $r !== '' && $r !== '-')));
$SERVED = array_flip($ROUTES);

//  The trail is permission-checked by design, so it must be judged as a signed-in
//  person sees it. Judged signed-out, every area and module step is (correctly)
//  withheld and the test would be measuring the refusal, not the model.
t_as_admin();

t_section('The model covers the application');

$placed = 0; $lost = [];
foreach ($ROUTES as $r) {
    if (nav_module_of($r) !== null) $placed++; else $lost[] = $r;
}
$pct = (int)round(100 * $placed / max(1, count($ROUTES)));
t_ok($pct >= 60, "*** $placed of " . count($ROUTES) . " routes ($pct%) resolve to a module");
// Every route still gets a trail, even one that resolves to no module.
$noTrail = [];
foreach ($ROUTES as $r) { $t = nav_trail($r); if (!is_array($t) || !$t) $noTrail[] = $r; }
t_eq($noTrail, [], '*** every route gets a trail, even those with no module'
     . ($noTrail ? ' — NONE: ' . implode(', ', array_slice($noTrail, 0, 5)) : ''));

t_section('The trail never points at a screen that does not exist');

//  THIS IS THE ONE THAT MATTERS. A breadcrumb to a route the router does not
//  serve is a dead end the user meets instead of the screen they wanted.
$dead = [];
foreach (array_keys(ACCESS_MODULES) as $mod) {
    $home = nav_module_home($mod);
    if ($home === null) continue;                    // deliberately absent — fine
    if (!isset($SERVED[$home])) $dead[] = "$mod -> /$home";
}
t_eq($dead, [], '*** every module home in the map is a route the router serves'
     . ($dead ? ' — DEAD: ' . implode(', ', $dead) : ''));

$deadArea = [];
foreach (nav_area_routes() as $label => $r)
    if (!isset($SERVED[$r])) $deadArea[] = "$label -> /$r";
t_eq($deadArea, [], '*** every area home is a route the router serves'
     . ($deadArea ? ' — DEAD: ' . implode(', ', $deadArea) : ''));

t_section('The trail is well formed');

$bad = [];
foreach (array_slice($ROUTES, 0, 400) as $r) {
    $t = nav_trail($r);
    if ($t[0]['label'] !== 'Home' || $t[0]['url'] !== '/') { $bad[] = "$r: does not start at Home"; continue; }
    $last = $t[count($t) - 1];
    if ($last['url'] !== null) { $bad[] = "$r: the last step is a link to itself"; continue; }
    $seen = [];
    foreach ($t as $step) {
        if (isset($seen[$step['label']])) { $bad[] = "$r: '{$step['label']}' twice"; break; }
        $seen[$step['label']] = 1;
    }
}
t_eq($bad, [], '*** every trail starts at Home, ends where you are, repeats nothing'
     . ($bad ? ' — ' . implode('; ', array_slice($bad, 0, 5)) : ''));

t_section('The owner\'s own example');

// "I am in approval-rules - Reporting and I click back it goes directly to the
//  reporting modules which is of no use."
//
//  UPDATED 2026-10-15, ON THE OWNER'S SECOND REPORT, AND THE CHANGE IS RECORDED
//  BECAUSE IT REVERSES AN EARLIER ONE.
//
//  The area step read "Reporting" because the trail asked the PERMISSION
//  grouping where this screen lives. The menu never agreed: this screen is
//  reached from Admin → Approvals, and there is no route to it from Reporting
//  at all. The owner then found the Approvals entry filed under a heading
//  called "Report configuration" and asked: "Really under reporting???" — the
//  same disagreement, seen from the menu side.
//
//  So the trail now names the area the MENU puts the screen in, and the module
//  step below it still says which module's rules these are. The owner's
//  original complaint is unaffected and still guarded: the point was that Back
//  must not dump you at an area index, and it does not — it goes to the
//  inspection-report register, one step up, as the next assertions require.
$t = nav_trail('idems-approval-rules');
$labels = array_map(fn($s) => $s['label'], $t);
t_ok(in_array('Admin', $labels, true),
     '*** report approval rules sit where the menu puts them, under Admin');
t_ok(!in_array('Reporting', $labels, true),
     '*** and NOT under Reporting, which has no route to this screen');
t_ok(in_array(access_module_label('idems'), $labels, true),
     '*** and under the inspection-reports module, not straight under the area');
//  Back goes one step up to that module's own register — never to the area index.
$b = nav_back_info('idems-approval-rules');
t_eq($b['label'], access_module_label('idems'),
     '*** so Back lands on the inspection reports, which was the owner\'s whole point');
t_eq(end($labels), 'Report approval rules', '*** the trail ends on the screen you are on');

// The same screen name under Sales must land somewhere different, which is the
// whole point: one hard-coded parent could never serve both.
$q = array_map(fn($s) => $s['label'], nav_trail('quote-approval-rules'));
t_ok(in_array('Sales', $q, true), '*** quotation approval rules sit under Sales instead');
t_ok($q !== $labels, '*** the two approval-rules screens have DIFFERENT trails');

t_section('Every area can appear in a trail, including the two with their own handlers');

//  A SILENT LOSS, CAUGHT ONLY BECAUSE A RECRUITMENT TEST NOTICED.
//
//  Operations and Recruitment are served by their own handlers, not by the
//  generic area engine — so ops_area_def() knows nothing about them and
//  ops_area_has() answers false for both. Gating the trail on that one function
//  dropped the area step from two of the busiest areas in the product, and
//  every screen under them lost a level without anything failing.
$lost = [];
foreach (['jobs' => 'Operations', 'calls' => 'Operations', 'candidates' => 'Recruitment',
          'quotes' => 'Sales', 'documents' => 'Reporting', 'invoicing' => 'Money',
          'ncr' => 'Quality & Accreditation', 'users' => 'Admin'] as $route => $area) {
    $labels = array_map(fn($x) => $x['label'], nav_trail($route));
    if (!in_array($area, $labels, true)) $lost[] = "$route is missing '$area'";
}
t_eq($lost, [], '*** every area reaches the trail of the screens beneath it'
     . ($lost ? ' — ' . implode('; ', $lost) : ''));

t_section('Back goes one step UP — never where you came from');

//  THIS SECTION ASSERTED THE OPPOSITE UNTIL 2026-10-15, AND WAS CHANGED ON THE
//  OWNER'S REPORT. It is written down here because reversing a test is the one
//  edit that can quietly destroy the thing the test protected.
//
//  It used to assert that Back follows the referrer. Two screenshots killed it:
//
//    · Standing on /admin, arrived from /approvals-setup, the control read
//      "← Approvals setup" — a screen INSIDE Admin. Back pointed downwards.
//    · Standing on /hierarchy, arrived from /masters, it read "← Masters"
//      while the breadcrumb an inch below said the parent was "Users & access".
//      Two navigation controls on one screen, contradicting each other.
//
//  "Check the breadcrumbs which does not go one step above it is pathetic."
//
//  The browser already has "where I came from" — a button on every desktop, a
//  swipe on every phone. Spending the one in-page control on a duplicate of it
//  left "what is this screen inside of?" with no control at all, which is the
//  question you need precisely when the browser's Back is useless: you typed
//  the address, followed a link from an email, or arrived after a redirect.
//
//  So the contract below is now the strict one, and it is stronger than what it
//  replaced in three ways: the destination is DERIVED from the trail rather
//  than kept in step with it by hand; the two controls cannot disagree, because
//  one is built from the other; and no referrer — including one an attacker
//  chooses — can move it.

$_SERVER['HTTP_HOST'] = 'example.test';
unset($_SERVER['HTTP_REFERER']);

//  Back IS the trail's last link. Not "usually", not "when there is no
//  referrer" — by construction, on every screen the router serves.
foreach (['m/agencies', 'equip-new', 'jobs', 'hierarchy', 'users', 'masters',
          'approvals-setup', 'approval-rules', 'recruit-approvals', 'documents'] as $r) {
    $i = nav_back_info($r);
    $t = nav_trail($r);
    $parent = null;
    for ($k = count($t) - 1; $k >= 0; $k--) if (!empty($t[$k]['url'])) { $parent = $t[$k]; break; }
    t_eq($i['url'],   $parent['url'] ?? '/',    "*** /$r — Back is the trail's own parent link");
    t_eq($i['label'], $parent['label'] ?? 'Home', "*** /$r — and is labelled exactly as the trail labels it");
}

//  A REFERRER CANNOT MOVE IT. This is the assertion that would fail first if
//  anybody reinstated the old behaviour, and it is also a small security
//  property: the referrer is attacker-chosen on any cross-site navigation, and
//  an in-page control that follows it is a control somebody else aims.
foreach (['https://example.test/operations?tab=backlog',
          'https://example.test/masters',
          'https://somewhere-else.example/page',
          'http://example.test:8891/quality'] as $ref) {
    $_SERVER['HTTP_REFERER'] = $ref;
    t_eq(nav_back('equip-new'), nav_back_info('equip-new')['url'],
         '*** the referrer does not move Back: ' . $ref);
}
unset($_SERVER['HTTP_REFERER']);
$fixed = nav_back('equip-new');
$_SERVER['HTTP_REFERER'] = 'https://example.test/quality';
t_eq(nav_back('equip-new'), $fixed, '*** and the answer is the same with a referrer as without one');
unset($_SERVER['HTTP_REFERER']);

//  Back never returns to the screen you are standing on, which would trap you.
foreach (['jobs', 'masters', 'admin', 'quality'] as $r)
    t_ok(trim((string) nav_back($r), '/') !== $r, "*** /$r — Back never points at the screen you are on");

//  And it always goes somewhere the application actually serves.
foreach (['quote-approval-rules', 'equip-new', 'm/designations'] as $r) {
    $b = nav_back($r);
    t_ok($b !== '' && $b[0] === '/', "*** /$r — Back goes somewhere real: $b");
}

//  An area Home's parent is Home — never a screen inside that area. This is the
//  exact defect the owner photographed on /admin.
foreach (['admin', 'quality', 'reporting', 'money', 'directory'] as $home) {
    $b = nav_back_info($home);
    t_eq($b['url'], '/', "*** /$home is an area Home, so one step up is Home itself");
}

t_section('Add and edit screens read like English');

t_eq(nav_screen_label('equip-new'), 'New',  '*** "equip-new" reads as New, not "Equip new"');
t_eq(nav_screen_label('quote-edit'), 'Edit', '*** "quote-edit" reads as Edit');
// the thing being added is already named one step up, so the verb alone is right
$t = nav_trail('equip-new');
t_ok(in_array(access_module_label('equipment'), array_map(fn($x) => $x['label'], $t), true),
     '*** and the module it belongs to is still named in the trail above it');

t_as_nobody();   // leave the session as this file found it

// ---------------------------------------------------------------------------
//  R-35 — ONE TRAIL PER SCREEN, INCLUDING THE ONES DRAWN BY A SHARED HEADER.
//
//  R-24 removed 277 hand-typed breadcrumbs, but org_head() in lib/orgadmin.php
//  is a shared HEADER rather than a screen, so its typed crumb survived and
//  produced a second trail on every screen in the Organisation & people module
//  at once. The owner's screenshot of Login accounts shows both, stacked.
//  A screen with two breadcrumbs saying different things is worse than one with
//  none: it tells you that the application does not know where you are.
// ---------------------------------------------------------------------------
t_section('R-35 — no screen draws its own second breadcrumb');
$crumbSources = [];
foreach (array_merge(glob(dirname(__DIR__) . '/views/ops/*.php') ?: [],
                     glob(dirname(__DIR__) . '/views/ops/*/*.php') ?: [],
                     glob(dirname(__DIR__) . '/lib/*.php') ?: []) as $f) {
    $src = (string) @file_get_contents($f);
    //  The one legitimate writer is the central model in layout_top.php.
    if (basename($f) === 'layout_top.php') continue;
    //  A CSS rule that merely hides .crumbs when printing is not a second trail.
    $code = (string) preg_replace('~<style.*?</style>~s', '', $src);
    if (preg_match('~<(nav|div)\s+class="crumbs"~', $code)) $crumbSources[] = basename($f);
}
t_eq($crumbSources, [], '*** nothing outside layout_top.php draws a breadcrumb'
     . ($crumbSources ? ' — found in ' . implode(', ', $crumbSources) : ''));

