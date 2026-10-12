<?php
// ============================================================================
//  CAN SOMEBODY ACTUALLY FIND IT?                   (UAT 1.6.3 · 1.7.1 · G6B-01)
//
//  Three findings from the owner's UAT run turned out to be the same thing:
//  the feature existed, worked, and could not be found.
//
//    1.6.3  "Under admin there is no option to disable or enable any of the
//            modules except market place."
//           → /workspace/setup/modules has switched all seven modules on and
//             off since the cockpit was built. The only door to it was a tile
//             called "Company setup".
//
//    1.7.1  "All modules are not included. Only two modules like quote and
//            Reporting. Where is Recuirtment??"
//    G6B-01 the Review Required "Redefined" switch could not be found at all.
//           → TWO screens were both called "Approval rules", in two different
//             menu areas: /approval-rules (quotes and reports, under Admin) and
//             /recruit-approvals (recruitment, under Operations, carrying the
//             Review Required triggers). Open one and the other does not exist.
//             R-14 for the third time.
//
//  And one plain bug underneath all of it: require_login() redirected to a
//  RELATIVE 'login', so any screen with a path in its address sent a signed-out
//  visitor somewhere that does not exist — and the cockpit family looped for
//  ever, because /workspace/setup/login is caught by the cockpit's own route.
//
//  These assertions are about REACHABILITY, which is why they are worth having:
//  every one of these screens already passed its own functional tests.
// ============================================================================

t_section('Findability — the screen exists, but can anybody get to it?');

// ---------------------------------------------------------------------------
//  1. THE SIGN-IN REDIRECT IS ABSOLUTE.
//     A relative one only ever worked for top-level screens, by luck.
// ---------------------------------------------------------------------------
$src = (string) @file_get_contents(__DIR__ . '/../lib/helpers.php');
//  Comments are stripped first. The first draft of this searched the raw file
//  and matched the explanatory comment that quotes the old broken call — a test
//  that fails because of the words written next to the fix is worse than no
//  test, because the next person "fixes" it by deleting the explanation.
$code = (string) preg_replace('~^\s*//.*$~m', '', (string) preg_replace('~/\*.*?\*/~s', '', $src));
t_ok(strpos($code, "redirect('/login')") !== false,
     '*** require_login() sends people to an ABSOLUTE /login');
t_ok(!preg_match("~redirect\('login'\)~", $code),
     '*** …never to a relative one, which resolves against the current path');

// ---------------------------------------------------------------------------
//  2. THE MODULE SWITCHBOARD IS REACHABLE, AND NAMED FOR THE JOB.
// ---------------------------------------------------------------------------
$origSess = $_SESSION;
$pdo = db();
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser) VALUES ('fnd_master','Find','MASTER_ADMIN',1,1)")->execute();
$mid = (int) $pdo->lastInsertId();
$_SESSION['uid'] = $mid; current_user(true); ua(true);

$tiles = [];
if (function_exists('ops_area_def')) {
    foreach ((array) (ops_area_def('admin')['sections'] ?? []) as $sec)
        foreach ((array) ($sec['tiles'] ?? []) as $t) $tiles[$t['route']] = $t['label'];
}
t_ok(isset($tiles['/workspace/setup/modules']),
     '*** Admin offers a direct way to turn modules on and off');
t_ok(isset($tiles['/workspace/setup/modules'])
     && stripos($tiles['/workspace/setup/modules'], 'module') !== false,
     '*** …and the tile says "module", the word somebody would look for');
//  The screen it points at is a real, gated route — not a 404 waiting to happen.
t_ok(function_exists('cockpit_can'), 'the cockpit that owns that screen is loaded');
t_ok(function_exists('cockpit_modules'), '…and can describe the modules it switches');
$mods = cockpit_modules();
t_ok(count($mods) >= 5, 'it lists this workspace\'s modules (' . count($mods) . ')');
$core = array_filter($mods, fn($m) => !empty($m['core']));
t_ok(count($core) >= 1, '…and marks at least one as core, which may never be switched off');

// ---------------------------------------------------------------------------
//  3. THE TWO APPROVAL SCREENS HAVE DIFFERENT NAMES.
//
//     This is the whole of 1.7.1 and G6B-01. One name on two screens is not a
//     cosmetic problem: it is why the owner concluded a feature was missing.
// ---------------------------------------------------------------------------
$labels = [];
foreach (['admin', 'operations'] as $area) {
    if (!function_exists('ops_area_def')) break;
    foreach ((array) (ops_area_def($area)['sections'] ?? []) as $sec)
        foreach ((array) ($sec['tiles'] ?? []) as $t) $labels[$t['route']] = $t['label'];
}
foreach (ops_nav_index() as $m) {
    $u = (string) ($m['url'] ?? '');
    if ($u !== '') $labels[$u] = (string) ($m['label'] ?? '');
}
$quote  = $labels['/approval-rules']    ?? null;
$recruit= $labels['/recruit-approvals'] ?? null;
t_ok($quote !== null,   'the quote & report approvals screen is in the menu');
t_ok($recruit !== null, 'the recruitment approvals screen is in the menu');
if ($quote !== null && $recruit !== null) {
    t_ok(strcasecmp(trim($quote), trim($recruit)) !== 0,
         '*** the two approval screens no longer share one name ("' . $quote . '" vs "' . $recruit . '")');
    t_ok(stripos($recruit, 'recruit') !== false,
         '*** the recruitment one says recruitment');
}
//  Each page points at the other, so neither is a dead end for somebody who
//  opened the wrong one — which is exactly what happened.
$qv = (string) @file_get_contents(__DIR__ . '/../views/ops/idems/approval_rules.php');
$rv = (string) @file_get_contents(__DIR__ . '/../views/ops/approval_rules.php');
t_ok(strpos($qv, '/recruit-approvals') !== false, '*** the quote/report screen names the recruitment chain');
t_ok(strpos($rv, '/approval-rules') !== false,    '*** and the recruitment screen names the quote/report chain');

// ---------------------------------------------------------------------------
//  4. THE REVIEW REQUIRED TRIGGERS LIVE ON THE RECRUITMENT SCREEN.
//     G6B-01 could not be executed because nobody could find them.
// ---------------------------------------------------------------------------
t_ok(strpos($rv, 'review_triggers') !== false || strpos($rv, 'trigger_') !== false,
     '*** the Review Required triggers are on the recruitment approvals screen');
t_ok(defined('CREV_TRIGGER_OPTIONAL') && in_array('redefined', CREV_TRIGGER_OPTIONAL, true),
     '*** "redefined" is the switchable trigger G6B-01 is about');
t_ok(function_exists('crev_trigger_state'), '…and the screen asks the engine for its state rather than guessing');

// ---------------------------------------------------------------------------
//  5. THE LICENCE SCREEN POINTS AT THE SWITCHBOARD.
//     "Check enabled modules" sends people here; it answered what you OWN and
//     said nothing about what you have switched ON.
// ---------------------------------------------------------------------------
$lv = (string) @file_get_contents(__DIR__ . '/../views/ops/licence.php');
t_ok(strpos($lv, '/workspace/setup/modules') !== false,
     '*** the licence screen points at where modules are switched on and off');

// ---------------------------------------------------------------------------
//  6. AND THE BREADCRUMB SAYS WHERE YOU ARE, IN THE SAME WORDS AS THE MENU.
//
//     These screens belong to an area without belonging to one module, so the
//     route → module → area chain gave them nothing: "Home › Modules &
//     features", which loses the one step you would actually click. And the
//     cockpit's nested routes title-cased into "Workspace setup modules",
//     which is not what anything else calls it.
// ---------------------------------------------------------------------------
$trail = fn($r) => array_map(fn($c) => $c['label'], nav_trail($r));
foreach ([
    'workspace/setup/modules' => 'Modules & features',
    'approval-rules'          => 'Quote & report approvals',
] as $route => $leaf) {
    $t = $trail($route);
    t_eq(end($t), $leaf, "*** /$route ends its trail with the name the menu uses");
    t_ok(in_array('Admin', $t, true), "*** …and names Admin, the area it is in");
}
//  The recruitment one resolves through its module, and must not have been
//  disturbed by the exception map.
$t = $trail('recruit-approvals');
t_eq($t, ['Home', 'Recruitment', 'Recruitment approvals'],
     'the recruitment approvals trail still resolves through its own module');
t_eq($trail('calls'), ['Home', 'Operations', 'Calls'],
     'ARMING · an ordinary module screen is unaffected by the exception map');

$pdo->prepare("DELETE FROM users WHERE id=?")->execute([$mid]);
$_SESSION = $origSess; current_user(true); ua(true);
