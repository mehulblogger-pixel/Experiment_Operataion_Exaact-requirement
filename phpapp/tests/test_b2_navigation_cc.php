<?php
// ============================================================================
//  B2 — NAVIGATION AND THE RECRUITMENT COMMAND CENTRE
//
//  The Command Centre put "Needs attention today" FOURTEENTH of twenty
//  headings. Measured in Chromium at 1280x900: you had to scroll 2,018px --
//  past six analytics sections, more than two screenfuls -- before the page
//  told you anything you could act on. Everything above it was reporting.
//
//  B2 reorders that page. It does not change a number, a query, a filter, a
//  permission or a route. The guards below exist to keep it that way: the
//  easiest way to "simplify" a dense screen is to delete things off it, and
//  that is exactly what must not happen here.
//
//  The second finding: /hiring-requests -- the register for the first step of
//  the recruitment chain -- had no door anywhere. Not in the rail, not an area
//  tile, not on the Command Centre. You could only reach the list from inside
//  an individual request, so you had to already be there to get there.
// ============================================================================

t_section('B2 — navigation and Command Centre');

$b2cc  = file_get_contents(__DIR__ . '/../views/ops/recruitment_cc.php');
$b2nav = file_get_contents(__DIR__ . '/../views/layout_top.php');

// ---- A · arming ----------------------------------------------------------
t_ok(strlen($b2cc) > 20000, 'A1 ARMING · the Command Centre view was read (' . strlen($b2cc) . ' bytes)');
//  Bands carry a style attribute in several places, so the pattern must allow
//  any attributes before the heading -- an earlier version matched only 8 of them.
preg_match_all('/<div class="band[^>]*><h2>([^<]+)<\/h2>/', $b2cc, $mB);
$b2bands = array_map(fn($s) => html_entity_decode(trim($s)), $mB[1] ?? []);
t_ok(count($b2bands) >= 10, 'A2 ARMING · ' . count($b2bands) . ' section bands found to order-check');

// ---- B · action before analysis -----------------------------------------
$idx = function ($needle) use ($b2bands) {
    foreach ($b2bands as $i => $b) if (stripos($b, $needle) !== false) return $i;
    return -1;
};
$iAttention = $idx('Needs attention today');
$iTracker   = $idx('Ownership, deployment');
$iAnalysis  = $idx('Analysis');
$iConv      = $idx('Conversion, speed');
$iFunnel    = $idx('Funnel, outcomes');
$iTrend     = $idx('Trend, department load');
$iDrop      = $idx('Why we lose candidates');

foreach (['Needs attention today' => $iAttention, 'Ownership/tracker' => $iTracker,
          'Analysis divider' => $iAnalysis, 'Conversion' => $iConv, 'Funnel' => $iFunnel,
          'Trend' => $iTrend, 'Drop reasons' => $iDrop] as $n => $i)
    t_ok($i >= 0, "B ARMING · the '$n' section is still on the page (index $i)");

t_eq($iAttention, 0, 'B1 · "Needs attention today" is the FIRST section on the page (was 14th of 20)');
t_ok($iTracker < $iConv, 'B2 · the requirement tracker — active work — comes before the analytics');
t_ok($iAnalysis < $iConv && $iAnalysis < $iFunnel && $iAnalysis < $iTrend && $iAnalysis < $iDrop,
     'B3 · every analytics section sits BELOW the Analysis divider');
t_ok($iAttention < $iAnalysis && $iTracker < $iAnalysis,
     'B4 · and every action section sits ABOVE it');
//  The analytics keep their own original order relative to each other.
t_ok($iConv < $iFunnel && $iFunnel < $iTrend && $iTrend < $iDrop,
     'B5 · within the analysis group the original order is preserved — nothing was shuffled');

// ---- C · NOTHING WAS DELETED --------------------------------------------
//  Pinned from the screen as it stood before B2. "Simplifying" a dense page by
//  quietly dropping destinations is the failure mode this phase must not have.
$b2before = [
 '/availability','/candidate-new','/candidate?id=','/candidates','/candidates?stage=',
 '/candidates?stage=OFFERED','/careers-admin','/comp-setup','/departments','/doc-templates',
 '/my-approvals','/positions','/positions-import','/positions-org','/project-costings',
 '/recruit-approvals','/recruit-export?dataset=candidates','/recruit-export?dataset=funnel',
 '/recruit-export?dataset=offers','/recruit-export?dataset=requisitions','/recruit-pipelines',
 '/recruitment','/requisition-new','/requisition?id=','/requisitions',
];
preg_match_all('/href="([^"]+)"/', $b2cc, $mH);
$b2now = array_values(array_unique(array_map(fn($h) => preg_replace('/<\?=.*/', '', $h), $mH[1] ?? [])));
$b2gone = array_values(array_diff($b2before, $b2now));
t_ok(count($b2before) === 25, 'C ARMING · 25 destinations were pinned from the pre-B2 screen');
t_eq($b2gone, [], 'C1 · every destination the screen offered before B2 is still offered');
$b2added = array_values(array_diff($b2now, $b2before));
t_eq($b2added, ['/hiring-requests'], 'C2 · exactly one destination was added, and it is the missing register');

// ---- D · the hiring-request door, correctly gated ------------------------
t_ok(strpos($b2cc, 'href="/hiring-requests"') !== false,
     'D1 · the Command Centre now offers the hiring-request register');
t_ok(preg_match('/hreq_can_view\(\)[^?]*\?>\s*\n?\s*<a class="btn secondary" href="\/hiring-requests"/', $b2cc) === 1
     || substr_count($b2cc, 'hreq_can_view()') >= 2,
     'D2 · it is behind hreq_can_view() — the same gate the handler itself uses');
t_ok(function_exists('hreq_can_view'), 'D3 ARMING · that gate really exists');
//  The neutral sentence must NOT declare a preferred path: ADR-001 is open and
//  is the owner's decision, not this phase's.
t_ok(stripos($b2cc, 'Hiring starts either way') !== false,
     'D4 · both ways in are stated');
foreach (['preferred way', 'you should raise', 'always start', 'the correct way', 'must start'] as $b2claim)
    t_ok(stripos($b2cc, $b2claim) === false,
         "D5 · and the page does NOT declare a preferred path ('$b2claim' absent) — ADR-001 is the owner's call");

// ---- E · the rail ---------------------------------------------------------
preg_match_all('/<span class="s-ic">([^<]+)<\/span>/u', $b2nav, $mI);
$b2icons = $mI[1] ?? [];
t_ok(count($b2icons) >= 18, 'E ARMING · ' . count($b2icons) . ' rail icons found');
$b2dupes = array_values(array_filter(array_count_values($b2icons), fn($n) => $n > 1));
t_eq($b2dupes, [], 'E1 · no two rail entries share an icon'
     . ($b2dupes ? ' — ' . implode(', ', array_keys(array_filter(array_count_values($b2icons), fn($n) => $n > 1))) : ''));
//  ARMING — prove the scan would catch a collision.
$b2probe = ['🧭', '🔍', '🧭'];
t_ok(count(array_filter(array_count_values($b2probe), fn($n) => $n > 1)) === 1,
     'E ARMING · the duplicate scan does detect a collision when one exists');

// ---- F · no route was invented -------------------------------------------
//  Every destination on the page must be a route the app already dispatches.
$b2src = file_get_contents(__DIR__ . '/../lib/ops.php');
$b2missing = [];
foreach ($b2now as $h) {
    if ($h === '' || $h[0] !== '/') continue;
    $r = ltrim(explode('?', $h)[0], '/');
    if ($r === '') continue;
    if (strpos($b2src, "'" . $r . "'") === false) $b2missing[] = $h;
}
t_eq($b2missing, [], 'F1 · every link on the page points at a route the app already dispatches');

// ---- G · the two recruitment homes are both still reachable --------------
t_ok(function_exists('ops_recruitment_home'), 'G1 · the compact recruitment home still exists');
t_ok(function_exists('ops_recruitment_cc'),   'G2 · and so does the Command Centre — neither was merged away');
t_ok(strpos($b2cc, 'href="/recruitment"') !== false,
     'G3 · the Command Centre still links to the compact one');

// ---- H · PERMISSION AND ENTITLEMENT — navigation is not a security boundary
//  The whole point of B2 is to make things easier to FIND. That must not make
//  anything easier to DO. The server is asked directly, in each of the three
//  states the brief names, and it must refuse in two of them regardless of what
//  any menu shows.
$b2keys = ['saas_entitled_modules', 'modules_off'];
$b2orig = []; foreach ($b2keys as $k) $b2orig[$k] = setting_get($k, '');
$b2tenant = $GLOBALS['__tenant'] ?? null;
$b2set = function ($csv) {
    setting_set('saas_entitled_modules', $csv); setting_set('modules_off', '');
    $GLOBALS['__tenant'] = ['key' => 'testco', 'company' => 'Test Co'];
    licence_disabled(true); current_user(true); ua(true);
};

t_as_admin();
$b2set('hr,operations,reporting');
t_ok(ops_module_gate('hiring-requests', true),
     'H1 · with the module entitled, the hiring-request route is allowed');
t_ok(ops_module_gate('recruitment-cc', true), 'H2 · and so is the Command Centre');

//  1. A workspace that has NOT bought the module.
$b2set('operations,reporting');
t_ok(!ops_module_gate('hiring-requests', true),
     'H3 · a workspace without the module is refused the hiring-request route');
t_ok(!ops_module_gate('recruitment-cc', true),
     'H4 · and the Command Centre too — the new link cannot become a way in');
t_ok(!ops_module_gate('requisition-new', true),
     'H5 · and requirement creation, which the page also offers');

//  2. Entitled, but the PERSON lacks the permission.
$b2set('hr,operations,reporting');
t_as_nobody();
t_ok(!hreq_can_view(),
     'H6 · a signed-out / unprivileged user does not pass hreq_can_view() — the gate on the new link');
t_ok(!(function_exists('recruit_home_can') && recruit_home_can()),
     'H7 · nor the gate the Command Centre itself uses');

//  3. ARMING — prove the gate is capable of saying yes, or H3–H7 prove nothing.
t_as_admin(); $b2set('hr,operations,reporting');
t_ok(hreq_can_view(), 'H ARMING · an entitled administrator DOES pass the gate');

// ---- restore -------------------------------------------------------------
foreach ($b2keys as $k) setting_set($k, (string) $b2orig[$k]);
if ($b2tenant === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $b2tenant;
licence_disabled(true); current_user(true); ua(true);
t_as_nobody();
t_ok(true, 'B2 · entitlement and session restored');
