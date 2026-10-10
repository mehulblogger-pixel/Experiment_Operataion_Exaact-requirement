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
    $words = trim(str_replace(['-', '/', '_'], ' ', $route));
    return $words === '' ? 'Home' : ucfirst($words);
}

//  THE TRAIL: Home > Area > Module > this screen.
//
//  Every step is permission-checked, because a breadcrumb that leads to a
//  refusal is worse than no breadcrumb. A step the viewer cannot open is left
//  out rather than shown broken. The last entry carries no url — it is where
//  you already are. $leaf is the record's own name when a handler knows it
//  ("INQ-2026-014"), passed through view().
function nav_trail($route, $leaf = null) {
    $route = trim((string)$route, '/');
    $trail = [['label' => 'Home', 'url' => '/']];
    if ($route === '') return $trail;

    $mod  = nav_module_of($route);
    $area = $mod ? nav_area_of_module($mod) : null;

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
//  Where you actually came from, when that is a real screen in this application
//  and not the page you are already standing on. Otherwise the true parent from
//  the trail. Never a hard-coded guess — that is the whole of R-21 and R-22.
//
//  The url and its label are worked out TOGETHER, in one place. Computed apart,
//  they drifted immediately: the link went back to Masters while the words still
//  said "Equipment & calibration", which is worse than a wrong link, because the
//  person reads the words and does not look at where they land.
function nav_back_info($route) {
    $route = trim((string)$route, '/');
    $ref   = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '') {
        //  parse_url() hands back the host WITHOUT the port while HTTP_HOST keeps
        //  it, so comparing them raw never matches on any host that carries one
        //  (a dev server, a staging box on :8080). The referrer was then treated
        //  as off-site and silently ignored — Back fell back to the parent every
        //  single time, which is the bug this whole function exists to kill.
        $host = (string)parse_url($ref, PHP_URL_HOST);
        $here = explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0];
        $path = (string)parse_url($ref, PHP_URL_PATH);
        $q    = (string)parse_url($ref, PHP_URL_QUERY);
        $came = trim($path, '/');
        if (($host === '' || $host === $here) && $path !== ''
            && $came !== $route && strncmp($came, 'login', 5) !== 0) {
            //  Name it the way the trail would name that screen, so "← Masters"
            //  means the Masters screen and nothing else.
            $t     = nav_trail($came);
            $label = end($t)['label'];
            return ['url' => $path . ($q !== '' ? '?' . $q : ''), 'label' => $label];
        }
    }
    $trail = nav_trail($route);
    for ($i = count($trail) - 1; $i >= 0; $i--)
        if (!empty($trail[$i]['url']))
            return ['url' => $trail[$i]['url'], 'label' => $trail[$i]['label']];
    return ['url' => '/', 'label' => 'Home'];
}

function nav_back($route)       { return nav_back_info($route)['url']; }
function nav_back_label($route) { return nav_back_info($route)['label']; }
