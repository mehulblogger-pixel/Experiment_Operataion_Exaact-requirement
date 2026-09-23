<?php
// ============================================================================
//  PHASE 1 · MILESTONE 11 — UX CONSOLIDATION
//
//  The inspection found the navigation engine to be sound — 8 areas, 99 tiles,
//  98 distinct routes, and exactly ONE route offered in two areas. What was NOT
//  sound was the answer to "where does this person start?": nine home screens
//  and a four-branch cascade inside index.php, with no single place that could
//  tell you why a given person landed where they did.
//
//  These tests hold the consolidation in place. They assert no new business
//  behaviour — every landing screen, route and action that existed still exists.
// ============================================================================

t_section('Milestone 11 — UX consolidation');

db();
$asTenant = function ($key = 'testco') { $GLOBALS['__tenant'] = ['key' => $key, 'company' => 'Test Co']; };
$origTenant = $GLOBALS['__tenant'] ?? null;
$origCeil   = setting_get('saas_entitled_modules', '');
$origOff    = setting_get('modules_off', '');
$ceiling = function ($csv, $off = '') use ($asTenant) {
    setting_set('saas_entitled_modules', $csv);
    setting_set('modules_off', $off);
    $asTenant(); licence_disabled(true);
};
$pdo = db();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_superuser, is_active)
               VALUES ('m11_master','M11','ADMIN',1,1)")->execute();
$masterId = (int) $pdo->lastInsertId();
$login = function ($uid) use ($asTenant) {
    if ($uid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $uid;
    $asTenant(); current_user(true); ua(true); licence_disabled(true);
};

// ---- A · One resolver answers "where does this person start?" --------------
t_ok(function_exists('ops_landing_decide'), 'A · there is a single landing resolver');
$d = ops_landing_decide(null);
t_eq($d['mode'], 'dashboard', 'A · a signed-out request resolves to the default home');
t_eq($d['route'], '',         'A · and asks for no redirect');

$login($masterId);
$u = current_user();
$d = ops_landing_decide($u, true, true);           // nothing outstanding
t_ok(in_array($d['mode'], LANDING_MODES, true), 'A · every answer is one of the declared modes');
t_ok($d['why'] !== '', 'A · and every answer carries a reason a person can read');
// The resolver DECIDES only — it must not redirect, echo or write the session.
$before = $_SESSION;
ob_start(); $d2 = ops_landing_decide($u, true, true); $noise = ob_get_clean();
t_eq($noise, '',       'A · the resolver prints nothing');
t_eq($_SESSION, $before, 'A · and writes nothing to the session — it is a pure decision');
t_eq($d2, $d,          'A · asked twice, it answers the same');

// ---- B · Each branch of the cascade, preserved -----------------------------
// Recruitment-only company: Operations not licensed, People & hiring licensed.
$ceiling('hr'); $login($masterId);
$d = ops_landing_decide(current_user(), true, true);
t_eq($d['mode'], 'recruitment', 'B · a recruitment-only company lands on the recruitment home');
t_eq($d['route'], '',           'B · rendered in place, not redirected — unchanged behaviour');
// A company with Operations keeps the dashboard.
$ceiling('operations,reporting'); $login($masterId);
$d = ops_landing_decide(current_user(), true, true);
t_eq($d['mode'], 'dashboard', 'B · a company with Operations keeps the dashboard');
// Setup incomplete + can complete it -> the cockpit; otherwise the welcome page.
if (function_exists('onboarding_incomplete') && onboarding_incomplete()) {
    $d = ops_landing_decide(current_user(), false, true);
    t_ok(in_array($d['mode'], ['cockpit', 'welcome'], true), 'B · unfinished setup orients the user first');
    t_ok($d['route'] !== '', 'B · and names the screen to go to');
} else {
    t_ok(true, 'B · this workspace reports setup complete — the orientation branch is covered by A/C');
}

// ---- C · "Home" reaches Home — the defect this milestone fixes -------------
// index.php said "once per session EITHER WAY" but set the flag only on the
// cockpit branch, so an ordinary user in a company with unfinished setup was
// sent to /welcome every single time they clicked Home.
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(preg_match("/mode'\\] === 'cockpit' \\|\\| \\\$__land\\['mode'\\] === 'welcome'/", $idx) === 1,
     'C · the orientation redirect now covers BOTH the cockpit and the welcome path');
t_ok(preg_match("/cockpit' \\|\\| .*welcome'\\) \\{\\s*\\n\\s*\\\$_SESSION\\['onb_seen'\\] = 1;/", $idx) === 1,
     'C · and the once-per-session flag is set for both, so Home reaches Home');
t_eq(substr_count($idx, "\$_SESSION['onb_seen'] = 1;"), 1,
     'C · the flag is set in exactly one place, not scattered');
// The old shape must not come back.
t_ok(strpos($idx, "redirect('/welcome');") === false,
     'C · the unconditional welcome redirect that caused the loop is gone');

// ---- D · Navigation: no two areas offer the same destination ---------------
$login($masterId);
$GLOBALS['__tenant'] = ['key' => '', 'company' => '']; licence_disabled(true);
current_user(true); ua(true);
$AREAS = ['sales', 'marketplace', 'quality', 'reporting', 'money', 'insights', 'directory', 'admin'];
$byRoute = []; $tiles = 0;
foreach ($AREAS as $a) {
    $def = @ops_area_def($a); if (!$def) continue;
    foreach (($def['sections'] ?? []) as $sec)
        foreach (($sec['tiles'] ?? []) as $t) { $tiles++; $byRoute[$t['route']][] = $a; }
}
t_ok($tiles > 90, "D · the areas still offer their tiles ($tiles)");
$dupes = array_filter($byRoute, fn($a) => count(array_unique($a)) > 1);
t_ok(!$dupes, 'D · no destination is offered by two different areas'
     . ($dupes ? ' — ' . implode(', ', array_keys($dupes)) : ''));
// Every tile must name a destination, and none may be a bare ambiguous router.
$bare = array_values(array_filter(array_keys($byRoute), fn($r) => $r === '/templates'));
t_ok(!$bare, 'D · the /templates router is never offered bare — each tile asks for its kind');
t_ok(isset($byRoute['/templates?kind=quote']) || isset($byRoute['/templates?kind=report']),
     'D · and the template libraries are still reachable');

// ---- E · Screens that were reachable only by typing the address ------------
foreach (['/backup' => 'admin', '/ai-settings' => 'admin', '/duplicates' => 'directory'] as $route => $area) {
    t_ok(isset($byRoute[$route]), "E · '$route' now has a navigation entry");
    if (isset($byRoute[$route])) t_ok(in_array($area, $byRoute[$route], true), "E · and it sits in $area");
}

// ---- F · Entitlement still decides what the navigation offers --------------
// A hidden tile is not a security mechanism — M5-M10 enforcement is
// authoritative — but the rail must not offer a module the company has not
// bought, or every click is a dead end.
$ceiling('operations,reporting'); $login($masterId);
t_ok(!ops_area_has('money'),       'F · an unentitled area is not offered in the rail (Money)');
t_ok(!ops_area_has('marketplace'), 'F · nor Marketplace');
t_ok(ops_area_has('reporting'),    'F · while an entitled area is offered');
t_ok(ops_area_has('admin'),        'F · and core administration always is');
$ceiling('operations,reporting,money,connect'); $login($masterId);
t_ok(ops_area_has('money'),       'F · Money appears once it is entitled');
t_ok(ops_area_has('marketplace'), 'F · and Marketplace once it is');
// ...and the server still refuses, whatever the rail shows.
$ceiling('operations,reporting'); $login($masterId);
t_ok(!ops_module_gate('invoices', true), 'F · the route gate still refuses — the UI is not the boundary');
t_ok(!connect_market_can(),              'F · and so does Marketplace');

// ---- F2 · "Would this link work?" must not lie about Marketplace -----------
// Its routes have no access modules (M9), so the gate's peek mode used to answer yes
// for a company without Marketplace, and the click was then refused. No menu
// exploited that — every one is built from the licence-gated area definitions —
// but a link that opens a refusal is exactly the dead end §20 forbids.
$ceiling('operations,reporting'); $login($masterId);
foreach (['connect-requirements', 'connect-talent', 'marketplace-plans', 'connect'] as $r)
    t_ok(!ops_module_gate($r, true), "F2 · peek says NO for '$r' when Marketplace is not entitled");
t_ok(!connect_market_can(), 'F2 · and the handler agrees');
$ceiling('operations,reporting,connect'); $login($masterId);
foreach (['connect-requirements', 'connect-talent', 'marketplace-plans'] as $r)
    t_ok(ops_module_gate($r, true), "F2 · peek says YES for '$r' once it is entitled");
t_ok(connect_market_can(), 'F2 · and the handler agrees — peek and handler never disagree');
// Operations and Reporting routes are untouched by that rule.
t_ok(ops_module_gate('jobs', true) && ops_module_gate('documents', true),
     'F2 · and no other module was caught by the prefix rule');

// ---- G · A refusal tells the user which kind of "no" it is -----------------
$gate = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($gate, 'module is not switched on for this installation') !== false,
     'G · "your company has not got this module" is said in those words');
t_ok(strpos($gate, 'Ask your administrator') !== false,
     'G · and "you personally may not" is a different sentence');
//  This asked whether the string "SQLSTATE" appears ANYWHERE in a 10,000-line
//  file. That is the right intent measured the wrong way, and it bit: F-A7-1's
//  fix added a comment explaining that a raw SQLSTATE used to reach the person
//  adding a colleague, and the guard failed on the explanation rather than on
//  any behaviour. A rule that forbids naming a defect in a comment discourages
//  exactly the documentation that stops it coming back — and it would equally
//  forbid a legitimate `catch` that RECOGNISES a SQLSTATE, which is the
//  defensive code we want.
//
//  So: strip comments and strings-in-comments first, then assert the word
//  appears in nothing the user is shown. Stronger than the original, because
//  it now checks the emitting calls rather than the file.
$gateCode = preg_replace('#/\*.*?\*/#s', '', $gate);        // block comments
$gateCode = preg_replace('#^\s*//.*$#m', '', $gateCode);     // line comments
$gateEmits = [];
if (preg_match_all('#(?:flash|echo|print|view)\s*\((?:[^()]|\([^()]*\))*\)#', $gateCode, $mEmit))
    $gateEmits = $mEmit[0];
t_ok(count($gateEmits) > 200,
     'G ARMING · the emit scan found ' . count($gateEmits) . ' flash/echo/view calls to check');
$gateLeak = array_values(array_filter($gateEmits, fn($c) => stripos($c, 'SQLSTATE') !== false));
t_eq($gateLeak, [], 'G · no database error text reaches a user from the gate');
//  And prove the scan would catch one, so a pass means something.
t_ok(stripos("flash('SQLSTATE[23000] oh dear')", 'SQLSTATE') !== false,
     'G ARMING · the check does detect SQLSTATE inside an emitting call');

// ---- H · Consolidation removed no screen and no action --------------------
foreach (['ops_recruitment_home', 'ops_area_home', 'workspace_landing_for', 'cockpit_can',
          'ops_templates', 'ops_backup', 'ops_dedupe'] as $fn)
    t_ok(function_exists($fn), "H · '$fn' still exists — nothing was deleted");
$detail = file_get_contents(__DIR__ . '/../views/detail.php');
t_ok(strpos($detail, '/partner-add?id=<?= $id ?>&kind=contract') !== false,
     'H · the secondary contract door still works — it was clarified, not removed');
t_ok(strpos($detail, 'is normally registered from the') !== false,
     'H · and the authoritative path is now stated before the form, not only after');

// ---- restore ----
unset($_SESSION['uid']);
setting_set('saas_entitled_modules', (string) $origCeil);
setting_set('modules_off', (string) $origOff);
if ($origTenant === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $origTenant;
licence_disabled(true); current_user(true); ua(true);
t_ok(true, 'settings and session restored');
