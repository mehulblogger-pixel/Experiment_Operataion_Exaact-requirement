<?php
// ---------------------------------------------------------------------------
//  UAT 1.3.5, reported by the owner on 2026-10-13:
//
//    "There is no back button or arrow under admin - masters when clicked on
//     any master list or drop down it shows as home - lookups with no back or
//     arrow button."
//
//  Two defects, one root cause.
//
//  nav_route_area() was five routes typed out by hand, so every screen it did
//  not name got NO area step at all — hence "Home › Lookups" for a screen the
//  person reached from Admin. And where a module WAS known, the area came from
//  permission_nav_groups(), which groups modules for the Roles screen: a
//  different question, with a different answer. It files `masters` under
//  "Directory" while the menu offers it under Admin, so the trail contradicted
//  the menu the person had just clicked.
//
//  The rule now, asserted here: THE BREADCRUMB RETRACES THE MENU. The menu in
//  areas.php is the authority, because it is what the person walked through;
//  the permission grouping is only a fallback for a route the menu never lists.
//  A trail that disagrees with the menu is worse than no trail — it teaches the
//  wrong place to look next time.
// ---------------------------------------------------------------------------

t_ok(function_exists('nav_trail'), 'the breadcrumb builder exists');
t_ok(function_exists('nav_route_area'), 'a route can be placed in an area');
t_ok(function_exists('ops_area_routes'), 'the menu can be asked what it owns');

// ---- the area now comes from the menu, for every route the menu lists ------
t_eq(nav_route_area('lookups'),  'Admin', 'the master-list screen is in Admin, as the menu lists it');
t_eq(nav_route_area('masters'),  'Admin', 'Masters is in Admin, not Directory');
t_eq(nav_route_area('users'),    'Admin', 'the user register is in Admin');
t_eq(nav_route_area('access'),   'Admin', 'Roles & permissions is in Admin');
t_eq(nav_route_area('settings'), 'Admin', 'System settings is in Admin');

// A prefix the menu declared ("m/") covers every list beneath it, so a new
// master list never has to be added to a second place to get its trail.
t_eq(nav_route_area('m/designations'), 'Admin', 'an individual master list inherits Admin from the "m/" prefix');
t_eq(nav_route_area('m/offices'),      'Admin', 'and so does every other one');

// ---- the hand-kept exceptions still answer --------------------------------
t_eq(nav_route_area('workspace/setup'),         'Admin', 'company setup keeps its area');
t_eq(nav_route_area('workspace/setup/modules'), 'Admin', 'the module switchboard keeps its area');
t_eq(nav_route_area('licence'),                 'Admin', 'the licence screen keeps its area');

// ---- a route nobody lists must not be invented into an area ---------------
t_eq(nav_route_area('zzz-not-a-route'), null, 'an unknown route gets no area rather than a wrong one');
t_eq(nav_route_area(''), null, 'the empty route gets no area');

// ---- OWNERSHIP beats the menu, and the menu beats nothing -----------------
//
//  My first attempt made the menu win outright and broke two things that were
//  right: report approval rules appear on the Admin menu but are OWNED by
//  inspection reports, and the owner asked for that screen to sit under
//  Reporting so Back reaches the reports rather than the admin index. The rule
//  the code now follows, and this pins:
//
//    a route that belongs to a module  -> the module's area
//    a route that belongs to nothing   -> the area whose menu lists it
//
//  /lookups is the second case, and is the defect that was reported: it is
//  owned by no module, so before this it had no area at all.
t_eq(nav_module_of('lookups'), null, '/lookups is owned by no module');
$trail  = nav_trail('lookups');
$labels = array_map(fn($c) => $c['label'], $trail);
t_eq($trail[0]['label'], 'Home', '/lookups starts at Home');
t_ok(count($trail) >= 2, '/lookups no longer has a bare one-step trail');

//  And the screen that IS owned resolves to its owner's area, menu placement
//  aside. Asserted on the resolution rather than on the rendered trail, because
//  nav_trail drops the area step for a viewer who cannot open that area's home,
//  and this file has no signed-in user. The rendered trail for this screen is
//  covered, with that setup, by test_nav_model.
if (function_exists('nav_area_of_module')) {
    $m = nav_module_of('idems-approval-rules');
    if ($m) {
        t_eq(nav_area_of_module($m), 'Reporting',
            'report approval rules are owned by Reporting, though the Admin menu lists them');
        t_ok(nav_route_area('idems-approval-rules') === 'Admin',
            'the menu does list them under Admin — which is exactly why ownership has to win');
    }
}

//  An area home must not be filed inside itself.
t_eq(nav_route_area('quality'), null, "an area's own home route has no parent area");
t_eq(nav_route_area('money'),   null, 'the same for every other area home');

//  An area's own home route must still be named after the screen, not swallowed
//  by the area label — nav_back_info reads the last step to word the Back link.
foreach (['quality', 'operations', 'money'] as $home) {
    $t3 = nav_trail($home);
    $last = end($t3);
    t_ok(!empty($last['label']), "/$home ends on a named step for the Back link");
}

// ---- the missing way back (the other half of 1.3.5) -----------------------
//  Both screens are reached from Admin and had nothing to return to. Asserted
//  on the markup, because this is exactly the kind of control that gets lost in
//  a later edit and nobody notices until a customer is stranded.
$ml = file_get_contents(__DIR__ . '/../views/ops/master_list.php');
t_ok(strpos($ml, 'href="/masters"') !== false,
    'an individual master list offers a way back to all master lists');
t_ok(strpos($ml, '←') !== false, 'and it is drawn as a back control');

$lk = file_get_contents(__DIR__ . '/../views/ops/lookups.php');
t_ok(strpos($lk, 'href="/admin"') !== false, 'the Masters screen offers a way back to Admin');
t_ok(strpos($lk, '←') !== false, 'and it is drawn as a back control');

//  The screen that already had one must keep it.
$lv = file_get_contents(__DIR__ . '/../views/ops/lookup_values.php');
t_ok(strpos($lv, 'href="/lookups"') !== false,
    'the values screen keeps the back control it already had');
