<?php
// ============================================================================
//  NO ROUTE MAY SIT OUTSIDE THE GATE                        (Journey H9, R-25)
// ============================================================================
//  Found on 2026-10-10 by signing in as a field inspector and asking for all
//  753 routes in turn, which is what UAT Journey H9 means by "type a forbidden
//  address straight into the browser bar". The inspector reached 25. Eight of
//  them were JSON endpoints feeding the client pickers:
//
//      /partner-contact?id=1  ->  a client contact's NAME, EMAIL and MOBILE
//      /partner-address?id=1  ->  that client's site address
//      /partner-sites, /partner-pos, /partner-gaps, /partner-meta,
//      /po-lines, /contract-no-check
//
//  An inspector has no clients access of any kind. The cause was not a weak
//  check — it was NO check: these eight were in neither ops_route_module_map()
//  nor ops_module_family(), so $mod came back null and ops_module_gate()
//  returned without asking anything. Nothing in the interface offered them, and
//  that is precisely the point H9 makes: hiding a menu item is not security.
//
//  Two lessons are encoded below. First, these endpoints stay gated. Second,
//  and more useful: a NEW route that returns partner data must not be able to
//  slip through the same gap unnoticed.
// ============================================================================

t_section('The eight client endpoints are inside the gate');

$CLIENT_JSON = ['partner-contact', 'partner-address', 'partner-sites', 'partner-meta',
                'partner-pos', 'partner-gaps', 'po-lines', 'contract-no-check'];

$ungated = [];
foreach ($CLIENT_JSON as $r) {
    $mod = nav_module_of($r);
    if ($mod === null) { $ungated[] = "/$r has NO module — the gate never runs"; continue; }
    if ($mod !== 'clients') $ungated[] = "/$r is gated on '$mod', expected 'clients'";
}
t_eq($ungated, [], '*** every client JSON endpoint is gated on the clients module'
     . ($ungated ? ' — ' . implode('; ', $ungated) : ''));

t_section('A role without clients access cannot resolve them');

//  The gate asks can("mod.<module>.view"). An inspector's default set must not
//  contain it, or the gate would admit them again by the front door.
$insp = role_defaults('INSPECTOR')['perms'];
t_ok(!in_array('mod.clients.view', $insp, true),
     '*** an inspector holds no clients view right, so the gate refuses all eight');
$coord = role_defaults('COORDINATOR')['perms'];
t_ok(in_array('mod.clients.view', $coord, true),
     '*** a coordinator does hold it, so the client pickers still work for the people who use them');

t_section('A refusal reaches a fetch() as JSON, not as a web page');

//  Without this the picker receives an HTML page, JSON.parse throws, the
//  .catch() swallows it, and the dropdown simply stays empty with nothing said
//  to anybody. 'fetch' is this application's own header value; recognising only
//  'xmlhttprequest' was why that happened.
$keep = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null;
foreach (['fetch', 'XMLHttpRequest', 'xmlhttprequest'] as $v) {
    $_SERVER['HTTP_X_REQUESTED_WITH'] = $v;
    t_ok(access_wants_json(), "*** X-Requested-With: $v is recognised as wanting JSON");
}
unset($_SERVER['HTTP_X_REQUESTED_WITH']);
t_ok(!access_wants_json(), '*** a plain browser request still gets the page, not JSON');
if ($keep !== null) $_SERVER['HTTP_X_REQUESTED_WITH'] = $keep;

//  And the callers say so, or the server cannot tell.
$js = file_get_contents(__DIR__ . '/../assets/js/app.js');
foreach (['partner-sites', 'partner-pos', 'partner-meta', 'po-lines'] as $ep) {
    $i = strpos($js, "fetch('/$ep");
    t_ok($i !== false && strpos(substr($js, $i, 220), "'X-Requested-With':'fetch'") !== false,
         "*** app.js tells the server that its /$ep call is a fetch");
}

t_section('No NEW partner endpoint can slip through the same gap');

//  The generalisation. Any route whose name marks it as partner or client data
//  must resolve to a module, so the gate runs. A new one added without a map
//  entry fails here rather than being found by somebody typing a URL.
$R = [];
foreach (glob(__DIR__ . '/../lib/*.php') as $f) {
    $s = file_get_contents($f);
    preg_match_all('/\$route === \'([a-z0-9\/_-]+)\'/', $s, $m);
    foreach ($m[1] as $r) $R[$r] = 1;
}
//  Two routes are gated by their HANDLER rather than by a module, which is
//  equally safe and was verified by reading them. They are named here, with the
//  guard, so the rule stays strict: anything NOT on this list must resolve to a
//  module, and a new partner route added without either fails below.
$GUARDED_BY_HANDLER = [
    // lib/ops.php ops_client_holds()  — is_master() || settings.manage || is_coordinator_level()
    'client-holds' => 'is_coordinator_level',
    // lib/geofence.php geofence_save_party() — is_master() || master.manage || is_admin_level()
    'partner-geo'  => 'is_admin_level',
];
$leaky = [];
foreach (array_keys($R) as $r) {
    if (!preg_match('/^(partner|client|customer|po)-/', $r)) continue;
    if (nav_module_of($r) !== null) continue;              // the module gate covers it
    if (isset($GUARDED_BY_HANDLER[$r])) continue;          // its handler covers it
    $leaky[] = $r;
}
t_eq($leaky, [], '*** every partner/client route is gated, by its module or by its handler'
     . ($leaky ? ' — NEITHER: ' . implode(', ', $leaky) : ''));

//  And the two handler guards are still really there — an allow-list that stops
//  checking what it allows is just a hole with a comment above it.
$libs = '';
foreach (glob(__DIR__ . '/../lib/*.php') as $f) $libs .= file_get_contents($f);
foreach ($GUARDED_BY_HANDLER as $route => $guard)
    t_ok(strpos($libs, $guard) !== false,
         "*** /$route's handler guard ($guard) still exists");
