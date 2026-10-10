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
$t = nav_trail('idems-approval-rules');
$labels = array_map(fn($s) => $s['label'], $t);
t_ok(in_array('Reporting', $labels, true), '*** report approval rules sit under Reporting');
t_ok(in_array(access_module_label('idems'), $labels, true),
     '*** and under the inspection-reports module, not straight under the area');
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

t_section('Back follows where you came from');

$_SERVER['HTTP_HOST'] = 'example.test';
$_SERVER['HTTP_REFERER'] = 'https://example.test/masters';
t_eq(nav_back('m/agencies'), '/masters', '*** arriving from Masters, Back returns to Masters');

$_SERVER['HTTP_REFERER'] = 'https://example.test/operations?tab=backlog';
t_eq(nav_back('m/agencies'), '/operations?tab=backlog',
     '*** arriving from Operations, Back returns there — query string and all');

// A referrer pointing at the page you are on must not trap you there.
$_SERVER['HTTP_REFERER'] = 'https://example.test/jobs';
t_ok(nav_back('jobs') !== '/jobs', '*** Back never returns to the screen you are already on');

// Another site, or none at all, falls back to the real parent.
$_SERVER['HTTP_REFERER'] = 'https://somewhere-else.example/page';
t_ok(strpos(nav_back('job-edit'), '/job') === 0 || nav_back('job-edit') === '/operations',
     '*** an outside referrer falls back to the true parent, never off-site');
unset($_SERVER['HTTP_REFERER']);
$b = nav_back('quote-approval-rules');
t_ok($b !== '' && $b[0] === '/', '*** with no referrer at all, Back still goes somewhere real: ' . $b);

t_section('The Back link and its words always agree');

//  TWO BUGS LIVED HERE, AND BOTH ARE THE KIND NO UNIT TEST WOULD HAVE FOUND
//  WITHOUT BEING TOLD TO LOOK.
//
//  1. parse_url() returns the referrer host WITHOUT its port; HTTP_HOST keeps
//     it. Compared raw, they never matched on any host carrying a port, so the
//     referrer was treated as off-site and Back fell back to the parent EVERY
//     time — the exact behaviour this function exists to remove, hiding behind
//     a function that looked correct.
//  2. The url and the label were computed separately and drifted at once: the
//     link went back to Masters while the words still read "Equipment &
//     calibration". That is worse than a wrong link, because people read the
//     words and never look at where they land.
$_SERVER['HTTP_HOST'] = 'example.test:8891';          // a host WITH a port
$_SERVER['HTTP_REFERER'] = 'http://example.test:8891/masters';
$i = nav_back_info('equip-new');
t_eq($i['url'], '/masters', '*** a referrer on a host with a port is still recognised as ours');
t_eq($i['label'], 'Masters', '*** and the words name where the link actually goes');

$_SERVER['HTTP_REFERER'] = 'http://example.test:8891/quality';
$i = nav_back_info('equip-new');
t_eq($i['url'], '/quality', '*** came from Quality, Back returns to Quality');
t_eq($i['label'], 'Quality', '*** labelled Quality, not the parent module');

// the url and the label must never be able to disagree
foreach (['masters', 'documents', 'operations', 'quality'] as $from) {
    $_SERVER['HTTP_REFERER'] = 'http://example.test:8891/' . $from;
    $i = nav_back_info('equip-new');
    $t = nav_trail(trim((string)parse_url($i['url'], PHP_URL_PATH), '/'));
    t_eq($i['label'], end($t)['label'],
         "*** Back to /$from is labelled exactly as that screen is named");
}
unset($_SERVER['HTTP_REFERER']);
$_SERVER['HTTP_HOST'] = 'example.test';

t_section('Add and edit screens read like English');

t_eq(nav_screen_label('equip-new'), 'New',  '*** "equip-new" reads as New, not "Equip new"');
t_eq(nav_screen_label('quote-edit'), 'Edit', '*** "quote-edit" reads as Edit');
// the thing being added is already named one step up, so the verb alone is right
$t = nav_trail('equip-new');
t_ok(in_array(access_module_label('equipment'), array_map(fn($x) => $x['label'], $t), true),
     '*** and the module it belongs to is still named in the trail above it');

t_as_nobody();   // leave the session as this file found it
