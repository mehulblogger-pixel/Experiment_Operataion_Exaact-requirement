<?php
// ---------------------------------------------------------------------------
//  THE UAT GUIDE MUST NAME SCREENS THAT EXIST, AND NAME THEM THE WAY THE MENU
//  DOES.                                                        (owner, 2026-10-15)
//
//    "Also incorporate exact location where to access the testing point. Just
//     check what you have written it's stupidity when you mention screen from
//     above or something like that. I am not a code person."
//
//  71 of the 335 checks carried no location at all, and an earlier draft of the
//  guide sent the owner to /connect-match — a screen that has never existed.
//  Both failures are the same kind: a document describing the application from
//  memory instead of from the application.
//
//  So the guide's addresses are now checked against the router, and its menu
//  paths against the menu. A screen that moves, or is renamed, or is removed,
//  fails here — in the suite — rather than in the owner's afternoon.
// ---------------------------------------------------------------------------
t_section('the UAT guide points at screens that exist');

$guide = __DIR__ . '/../../docs/phase7/UAT-MANUAL-EXECUTION-GUIDE-2026.10.1.md';
t_ok(is_file($guide), 'the manual execution guide is in the repository');
$md = (string) file_get_contents($guide);

//  ---- every address it offers is a route the application serves ------------
preg_match_all('~operations\.mghaiapps\.com(/[A-Za-z0-9/?=&._-]*)~', $md, $m);
$addrs = array_values(array_unique($m[1]));
t_ok(count($addrs) > 40, 'it offers a typed address for the screens it names (' . count($addrs) . ')');

$served = function ($route) {
    $r = ltrim((string) parse_url($route, PHP_URL_PATH), '/');
    if ($r === '') return true;                               // the dashboard
    if (strncmp($r, 'm/', 2) === 0) return true;              // a master list
    //  The cockpit serves everything under workspace/setup/ from one prefix
    //  match, so no individual sub-route is ever named in the map.
    if (strncmp($r, 'workspace/setup', 15) === 0) return true;
    if (function_exists('ops_route_module_map') && isset(ops_route_module_map()[$r])) return true;
    //  The map does not hold every route — the router also matches many by
    //  name — so fall back to the front controller's own source. This is the
    //  same question the gate asks, read from the file that answers it.
    static $src = null;
    if ($src === null) $src = file_get_contents(__DIR__ . '/../lib/ops.php')
                            . file_get_contents(__DIR__ . '/../index.php');
    return strpos($src, "'" . $r . "'") !== false;
};
$dead = [];
foreach ($addrs as $a) if (!$served($a)) $dead[] = $a;
t_eq($dead, [], '*** no address in the guide is a screen that does not exist'
     . ($dead ? ' — ' . implode(', ', $dead) : ''));

//  ---- the menu paths name areas the rail actually has ----------------------
preg_match_all('~Left menu → [^A-Za-z]*([A-Za-z &]+?)(?: →|<br>|\s*\|)~u', $md, $am);
$areas = array_values(array_unique(array_map('trim', $am[1])));
$known = array_keys(nav_area_routes());
$known[] = 'Dashboard'; $known[] = 'Search records'; $known[] = 'My Work';
$known[] = 'My tasks'; $known[] = 'Command Centre'; $known[] = 'group';
$known[] = 'What to fix'; $known[] = 'Where the flow is broken'; $known[] = 'Owner home';
$known[] = 'Me'; $known[] = 'My signature'; $known[] = 'Change password'; $known[] = 'Two-factor';
$odd = array_values(array_diff($areas, $known));
t_eq($odd, [], '*** every "Left menu → X" in the guide is a real rail entry'
     . ($odd ? ' — ' . implode(' | ', $odd) : ''));

//  ---- and it no longer refers to a screen only in relation to another ------
//  This is the exact wording the owner objected to. It must not come back.
foreach ([
    'Stay on the screen from the previous step',
    'same screen as above',
    'the screen from above',
] as $phrase)
    t_ok(stripos($md, $phrase) === false, 'the guide never says "' . $phrase . '"');

//  Every row that names a check also gives it a location. A table row whose
//  first cell is a check id and whose location cell is empty is the defect.
$rows = 0; $bare = [];
foreach (explode("\n", $md) as $ln) {
    if (strncmp($ln, '| ', 2) !== 0) continue;
    $c = explode('|', $ln);
    if (count($c) < 4) continue;
    $id = trim($c[1], " *\t");
    //  A bare letter is a JOURNEY heading in the summary table ("A | Recruitment"),
    //  not a check. Requiring the digit keeps those out.
    if (!preg_match('/^(\d+\.\d+\.\d+|[A-Z]\d+(\.\d+)?|G6B-\d+\.\d+)$/', $id)) continue;
    $rows++;
    $joined = implode(' ', array_slice($c, 2));
    //  A record that can only be made by acting on ANOTHER named record is
    //  allowed to say so — "Open D4 → Start recruiting" and "Move D6 to the
    //  final stage, then stop" are exact instructions, not vague ones. What is
    //  banned is a pointer with no referent ("the previous screen", "as
    //  above"), which is what the owner objected to. The reference must name
    //  the record, so it still works read out of order.
    $derived = (bool) preg_match('/\b(Open|Move)\s+D\d\b/', $joined);
    if (!$derived
        && strpos($joined, 'Left menu') === false && strpos($joined, 'operations.mghaiapps.com') === false
        && stripos($joined, 'type the address') === false && stripos($joined, 'site address') === false)
        $bare[] = $id;
}
t_ok($rows > 80, 'the guide tabulates its checks (' . $rows . ' rows)');
t_eq($bare, [], '*** every tabulated check says where to go'
     . ($bare ? ' — ' . implode(', ', array_slice($bare, 0, 12)) : ''));
