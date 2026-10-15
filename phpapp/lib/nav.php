<?php
// ============================================================================
//  THE NAVIGATION MODEL — where does this screen sit?                  (R-24)
// ============================================================================
//  The application could render every screen and decide who may open it, but
//  nothing anywhere could answer that one question. So every breadcrumb was
//  typed by hand into its own file — 277 of them, 80 different parents, no map,
//  nothing checking any of it — and "Back" was a fixed guess.
//
//  That guess is wrong by construction: 228 of 404 screens are reachable from
//  more than one place, and the Job screen from thirty. One hard-coded parent
//  cannot be right for thirty arrivals. The owner put it exactly: "track and
//  back track are not at all linked".
//
//  Nothing here is new knowledge. Three maps already existed and had never been
//  introduced to each other:
//
//    route  -> module   ops_route_module_map() + ops_module_family()  (the permission gate)
//    module -> area     permission_nav_groups()                        (the permissions screen)
//    area   -> home     the area routes the router already serves      (the left rail)
//
//  Chained, they give every screen a real trail. See
//  docs/phase7/APPLICATION-STRUCTURE-AUDIT.md for the measurements.
// ============================================================================

//  Area label (as permission_nav_groups names it) => the route of its Home.
//  These nine are the areas the router actually serves; see ops.php's dispatch
//  for 'sales', 'operations', 'recruitment' and the rest.
function nav_area_routes() {
    return [
        'Sales'                   => 'sales',
        //  MARKETPLACE WAS MISSING FROM THIS LIST UNTIL 2026-10-15, and the rail
        //  has carried it for months. The cost was invisible until something
        //  checked: all fourteen Marketplace screens had NO area step in their
        //  breadcrumb and nothing to go back to but Home — "Home › Connect
        //  talent", from a tile the menu calls "Talent search".
        //
        //  Found by tests/test_uat_guide_locations.php, which cross-checks the
        //  owner's test guide against the real rail. The guide said "Left menu →
        //  🧑‍🏭 Marketplace", the rail agreed, and this list did not.
        'Marketplace'             => 'marketplace',
        'Operations'              => 'operations',
        'Recruitment'             => 'recruitment',
        'Reporting'               => 'reporting',
        'Money'                   => 'money',
        'Quality & Accreditation' => 'quality',
        'Insights'                => 'insights',
        'Directory'               => 'directory',
        'Admin'                   => 'admin',
    ];
}

//  Which module owns a route. The gate's map first, then the prefix fallback
//  the gate itself relies on, so a screen nobody added to the map still finds
//  its place instead of falling out of the trail.
function nav_module_of($route) {
    $base = (strncmp($route, 'm/', 2) === 0) ? 'masters' : $route;
    $map  = function_exists('ops_route_module_map') ? ops_route_module_map() : [];
    if (isset($map[$base])) return $map[$base];
    return function_exists('ops_module_family') ? ops_module_family($base) : null;
}

//  Which area owns a module — read straight off the permissions screen's own
//  grouping, so the breadcrumb and the access editor can never disagree about
//  where something lives. (That disagreement is exactly what R-14 was.)
function nav_area_of_module($mod) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        if (function_exists('permission_nav_groups')) {
            foreach (permission_nav_groups() as $label => $keys) {
                foreach ($keys as $k)
                    if (preg_match('/^mod\.(\w+)\.view$/', $k, $m)) $cache[$m[1]] = $label;
            }
        }
    }
    return $cache[$mod] ?? null;
}

//  A module's own list screen, where one exists and is worth a breadcrumb step.
//
//  DELIBERATELY INCOMPLETE. A module with no entry here simply does not get that
//  level, and the trail reads Home > Area > This screen. That is a smaller lie
//  than a breadcrumb that leads somewhere the router does not serve: a trail
//  nobody can trust is worse than a shorter one. Every entry below is asserted
//  against the real router in tests/test_nav_model.php.
function nav_module_home($mod) {
    static $home = [
        'inquiries' => 'inquiries',   'quotes'     => 'quotes',
        'calls'     => 'calls',       'jobs'       => 'jobs',
        'vouchers'  => 'vouchers',    'invoicing'  => 'invoicing',
        'profitability' => 'profitability',
        'competence'    => 'competence',   'impartiality' => 'impartiality',
        'identity'      => 'identity',     'complaints'   => 'complaints',
        'leads'         => 'leads',        'ncr'          => 'ncr',
        'confidentiality' => 'confidentiality', 'capa'    => 'capa',
        'masters'   => 'masters',     'reports'    => 'reports',
        'users'     => 'users',       'settings'   => 'settings',
        // modules whose list screen is not named after the module
        'idems'       => 'documents',        'hiring'     => 'candidates',
        'equipment'   => 'equipment',        'audits'     => 'internal-audits',
        'datacontrol' => 'data-control',     'portal'     => 'portal-users',
        'overheads'   => 'office-finance',   'crm_reports'=> 'crm-dashboard',
        'reconcile'   => 'attendance-recon',
        // crm_orders and vendors have no single list screen of their own — left out
        // on purpose rather than pointed at something that only half fits.
    ];
    return $home[$mod] ?? null;
}

//  A screen's own name. The route name is already close to English in this
//  application ('quote-approval-rules'), so it is title-cased rather than
//  re-typed into yet another map that would drift. Overrides are only for the
//  handful where that reads badly.
function nav_screen_label($route) {
    static $override = [
        'idems-approval-rules'  => 'Report approval rules',
        'quote-approval-rules'  => 'Quotation approval rules',
        'approval-delegations'  => 'Approval delegations',
        'my-approvals'          => 'My approvals',
        'recruit-approvals'     => 'Recruitment approvals',
        //  R-33 — the cockpit's nested routes title-case into nonsense
        //  ("Workspace setup modules"). Named as the menu names them, so the
        //  trail, the tile and the screen's own heading all agree.
        'approval-rules'          => 'Quote & report approvals',
        'workspace/setup'         => 'Company setup',
        'workspace/setup/modules' => 'Modules & features',
        'workspace/setup/forms'   => 'Forms & fields',
        'data-control'          => 'Data & information control',
        'office-finance'        => 'Office finance',
        'attendance-recon'      => 'Attendance reconcile',
        'crm-dashboard'         => 'Sales reports',
        'portal-users'          => 'Client portal users',
        'internal-audits'       => 'Internal audits',
        //  These match the screen's own <h1> exactly. A breadcrumb that says
        //  "My work" above a heading that says "My Work" is the small
        //  inconsistency ADR-002 exists to stop — and the one the My Work
        //  renderer test caught the moment the trail was centralised.
        'my-work'               => 'My Work',
        'my-approvals'          => 'My approvals',
        'report-types'          => 'Report types',
    ];
    if (isset($override[$route])) return $override[$route];
    if (strncmp($route, 'm/', 2) === 0) $route = substr($route, 2);
    //  "Equip new" and "Quote edit" read like database rows. The module is
    //  already named one step up the trail, so the verb alone is clearer.
    if (preg_match('/^(.*)-(new|edit|add)$/', $route, $vm))
        return $vm[2] === 'edit' ? 'Edit' : 'New';
    //  CALL IT WHAT THE TILE CALLED IT.
    //
    //  Title-casing the route is a guess, and it guessed wrong wherever the
    //  menu had chosen better words: the Marketplace tile reads "Talent
    //  search", and the trail under it read "Connect talent". The person
    //  clicked a tile; the trail should repeat the words on that tile back to
    //  them, not a tidied-up version of the address.
    //
    //  Asked AFTER the explicit overrides above, so a deliberate name still
    //  wins, and after the new/edit rule, so "New" stays "New".
    $tile = nav_menu_label($route);
    if ($tile !== null) return $tile;
    $words = trim(str_replace(['-', '/', '_'], ' ', $route));
    return $words === '' ? 'Home' : ucfirst($words);
}

//  The label the MENU gives a route, if any tile points at it. Built once from
//  the same area definitions the rail and the area homes are drawn from, so
//  there is no second list of names to keep in step.
function nav_menu_label($route) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        if (function_exists('ops_area_def')) {
            foreach (nav_area_routes() as $areaRoute) {
                $def = ops_area_def($areaRoute);
                foreach (($def['sections'] ?? []) as $sec)
                    foreach ($sec['tiles'] as $t) {
                        $r = ltrim((string) parse_url((string) $t['route'], PHP_URL_PATH), '/');
                        if ($r !== '' && !isset($cache[$r])) $cache[$r] = $t['label'];
                    }
            }
        }
    }
    return $cache[ltrim((string) $route, '/')] ?? null;
}

//  THE TRAIL: Home > Area > Module > this screen.
//
//  Every step is permission-checked, because a breadcrumb that leads to a
//  refusal is worse than no breadcrumb. A step the viewer cannot open is left
//  out rather than shown broken. The last entry carries no url — it is where
//  you already are. $leaf is the record's own name when a handler knows it
//  ("INQ-2026-014"), passed through view().
//  A HANDFUL OF SCREENS BELONG TO AN AREA WITHOUT BELONGING TO ONE MODULE.
//
//  The trail normally reads route → module → area. That works for the 400-odd
//  screens that sit inside one module, and gives nothing at all for the few that
//  deliberately span several: the cockpit configures the whole workspace, and
//  the quote & report approvals screen governs two modules at once. Those were
//  left with a two-step trail — "Home › Modules & features" — which tells you
//  the one thing you already knew and loses the step you would actually click.
//
//  Stating their area directly is honest: these screens genuinely have an area
//  and genuinely have no single module. It is a map of EXCEPTIONS and is checked
//  only after the module route fails, so it can never override the real one.
/**
 *  Which area a route belongs to — asked of the MENU, not of a hand-kept list.
 *
 *  This was five routes typed out by hand, so every screen the list did not
 *  name fell through to no area at all. Reported from UAT 1.3.5: opening a
 *  master list from Admin → Masters produced the trail "Home › Lookups" — no
 *  Admin step, because `lookups` was not one of the five.
 *
 *  The trail's job is to retrace the path the person actually walked, so the
 *  authority has to be the thing they walked through: the menu in areas.php.
 *  Asking the permission grouping instead (nav_area_of_module) is what put
 *  Masters under "Directory" while the menu lists it under Admin — a trail that
 *  contradicts the menu is worse than no trail, because it teaches the wrong
 *  place to look next time.
 *
 *  Prefix entries in the menu ("m/") match the routes beneath them, which is how
 *  every individual master list inherits the trail without being listed.
 */
function nav_route_area($route) {
    static $cache = null;
    $route = trim((string)$route, '/');
    if ($route === '') return null;
    if ($cache === null) {
        $cache = [];
        if (function_exists('ops_area_routes')) {
            foreach (nav_area_routes() as $label => $areaRoute) {
                foreach ((array) ops_area_routes($areaRoute) as $r) {
                    //  Only the LEADING slash goes: a trailing one is the menu
                    //  saying "and everything under here" ("m/"), and trimming
                    //  it turns the prefix into a plain route that matches
                    //  nothing but itself.
                    $r = ltrim((string) $r, '/');
                    if ($r === '') continue;
                    //  An area's OWN home is not inside itself. Mapping it to
                    //  its area makes the trail end on the area's name, and the
                    //  Back link then reads "Quality & Accreditation" where it
                    //  should read the screen's own name, "Quality".
                    if ($r === ltrim((string) $areaRoute, '/')) continue;
                    if (!isset($cache[$r])) $cache[$r] = $label;
                }
            }
        }
        //  Kept for the handful of routes the area engine does not enumerate.
        foreach ([
            'workspace/setup' => 'Admin', 'workspace/setup/modules' => 'Admin',
            'workspace/setup/forms' => 'Admin', 'approval-rules' => 'Admin',
            'licence' => 'Admin',
        ] as $r => $a) { if (!isset($cache[$r])) $cache[$r] = $a; }
    }
    if (isset($cache[$route])) return $cache[$route];
    //  A prefix the menu declared, e.g. "m/" owning "m/designations".
    foreach ($cache as $r => $label) {
        if (substr($r, -1) === '/' && strncmp($route, $r, strlen($r)) === 0) return $label;
    }
    return null;
}

function nav_trail($route, $leaf = null) {
    $route = trim((string)$route, '/');
    $trail = [['label' => 'Home', 'url' => '/']];
    if ($route === '') return $trail;

    $mod  = nav_module_of($route);
    //  THE MENU DECIDES WHERE A SCREEN LIVES. The permission grouping only
    //  answers for screens the menu does not list.
    //
    //  This order was the other way round, and it produced a trail that
    //  contradicted the menu the person had just used. /masters is reached by
    //  every single user through Admin → Masters, and the breadcrumb said
    //  "Home › Directory › Masters", because permission_nav_groups() files the
    //  masters module under Directory. So Back landed them in Directory — an
    //  area some of them had never opened.
    //
    //  The two maps answer different questions and only one of them is about
    //  place. permission_nav_groups() groups PERMISSIONS for the access editor:
    //  it says which checkboxes sit together, not which menu a screen hangs
    //  under. areas.php IS the menu. A trail that disagrees with the menu is
    //  worse than a short one, because it teaches the wrong place to look next
    //  time — and the owner reported exactly that outcome.
    //
    //  The module step below is unchanged, so a record still trails through its
    //  own register (Home › Admin › Users & access › Hierarchy).
    $area = nav_route_area($route) ?: ($mod ? nav_area_of_module($mod) : null);

    if ($area !== null) {
        $ar = nav_area_routes()[$area] ?? null;
        //  Can this person actually open the area's Home?
        //
        //  ops_area_has() alone is the WRONG question, and answering it that way
        //  silently cost Operations and Recruitment their place in every trail:
        //  those two are served by their own handlers rather than the generic
        //  area engine, so ops_area_def() knows nothing about them and
        //  ops_area_has() returns false for both. Two of the busiest areas in
        //  the product would have had no breadcrumb step at all.
        //
        //  So: ask the licence peek, which exists precisely to answer "would
        //  this link work?", and ask the area engine as well only where it has
        //  something to say.
        $open = $ar !== null
            && (!function_exists('ops_module_gate') || ops_module_gate($ar, true))
            && (!function_exists('ops_area_def') || !ops_area_def($ar)
                || !function_exists('ops_area_has') || ops_area_has($ar));
        if ($open) $trail[] = ['label' => $area, 'url' => '/' . $ar];
    }
    if ($mod) {
        $home   = nav_module_home($mod);
        $label  = access_module_label($mod);
        $canSee = !function_exists('can') || can('mod.' . $mod . '.view');
        $last   = end($trail);
        //  Three reasons to leave this step out, all of them "it adds nothing":
        //  the module IS this screen; the module's name is the area's name
        //  (Recruitment is both, after R-14); or the viewer cannot open it.
        if ($home && $home !== $route && $canSee && $label !== $last['label'])
            $trail[] = ['label' => $label, 'url' => '/' . $home];
    }
    //  Finally, the screen itself. When the trail already names it — you are
    //  standing on an area Home, or on the module's own list — the step stays
    //  for its name but loses its link rather than being dropped: a trail must
    //  always end where you are, and must never end on a link to this page.
    $self = nav_screen_label($route);
    $i    = count($trail) - 1;
    if ($trail[$i]['label'] === $self || $trail[$i]['url'] === '/' . $route) {
        $trail[$i]['url'] = null;
    } else {
        $trail[] = ['label' => $self, 'url' => null];
    }
    if ($leaf !== null && trim((string)$leaf) !== '') {
        // the record's own name sits below the screen that lists it
        $trail[count($trail) - 1]['url'] = '/' . $route;
        $trail[] = ['label' => (string)$leaf, 'url' => null];
    }
    return $trail;
}

//  WHERE BACK SHOULD GO, AND WHAT TO CALL IT.
//
//  ONE STEP UP THE TRAIL. Always. Never where you came from.
//
//  This used to prefer the referrer, on the reasoning that "where you actually
//  came from" is the most useful destination. Measured against the real app,
//  it is not — it is actively wrong, and the owner reported it twice in one
//  session: "Check the breadcrumbs which does not go one step above it is
//  pathetic."
//
//  Two failures, both reproduced over HTTP before this was changed:
//
//    · Standing on /admin having arrived from /approvals-setup, the control
//      read "← Approvals setup" — a screen INSIDE Admin. "Back" pointed down.
//    · Standing on /hierarchy having arrived from /masters, it read
//      "← Masters" while the breadcrumb directly underneath it said the parent
//      was "Users & access". The two controls contradicted each other on the
//      same screen, an inch apart.
//
//  The deeper reason is that the browser ALREADY has "where I came from", on
//  every device, with a swipe on a phone. Spending the one in-page control on a
//  duplicate of it leaves the question it cannot answer — "what is this screen
//  inside of?" — with no control at all. That question is the one you need when
//  you arrived by typing an address, by following a link from an email, or
//  after a redirect, which is exactly when the browser's own Back is useless.
//
//  So: the first linked step from the end of the trail, which is the parent.
//  The trail is drawn immediately below this control, so the two now always
//  agree by construction — the control is literally the trail's own last link.
function nav_back_info($route) {
    $route = trim((string)$route, '/');
    $trail = nav_trail($route);
    for ($i = count($trail) - 1; $i >= 0; $i--)
        if (!empty($trail[$i]['url']))
            return ['url' => $trail[$i]['url'], 'label' => $trail[$i]['label']];
    return ['url' => '/', 'label' => 'Home'];
}

function nav_back($route)       { return nav_back_info($route)['url']; }
function nav_back_label($route) { return nav_back_info($route)['label']; }
