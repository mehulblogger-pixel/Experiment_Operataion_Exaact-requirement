<?php
// ---------------------------------------------------------------------------
//  UAT 1.3.5 / 1.5.5 / 1.6.1 / 1.6.4 / 1.7.1 — ONE STEP BACK, on every screen.
//
//  Eight of twelve failures in one session were the same complaint, in the
//  owner's words:
//
//    "when clicked on back does not go one step upward but goes directly to
//     the admin"
//    "no option to move just one step back like back arrow or button"
//    "when we press back on chrome it takes outside of the admin"
//
//  Both halves were real. There was no back control on 129 of the 156 screens
//  — 27 views hand-rolled their own and everything else had nothing — and the
//  BROWSER's Back button is no answer for a screen reached by typing its
//  address: there is no history behind it, so it leaves the application.
//
//  nav_back_info() already computed the right destination and was used by
//  those 27 views. The layout now draws it for every screen, so a screen
//  cannot be a dead end and a new screen gets the control without anybody
//  remembering to add one.
// ---------------------------------------------------------------------------

t_ok(function_exists('nav_back_info'), 'the back-destination helper exists');

$layout = file_get_contents(__DIR__ . '/../views/layout_top.php');
t_ok(strpos($layout, 'crumb-back') !== false,
    'the layout draws a back control for every screen');
t_ok(strpos($layout, 'nav_back_info($cur)') !== false,
    'and it asks nav_back_info() where back actually goes, rather than guessing');

//  It must never point at the screen you are already on — that is a control
//  that looks like an exit and does nothing, which is worse than none.
t_ok(strpos($layout, "!== \$cur") !== false,
    'a back link to the current screen is suppressed');

//  The control is styled, and big enough to hit on a phone: the people who
//  get stranded are inspectors in the field.
$css = file_get_contents(__DIR__ . '/../assets/css/app.css');
t_ok(strpos($css, '.crumb-back') !== false, 'the back control is styled');
t_ok(preg_match('/\.crumb-back\{[^}]*min-height:44px|@media[^{]*\{[^}]*\.crumb-back\{[^}]*min-height:44px/s', $css)
     || strpos($css, 'min-height:44px') !== false,
    'and carries a 44px tap target on a phone');
t_ok(substr_count($css, '{') === substr_count($css, '}'),
    'the stylesheet is still balanced');

// ---- where back actually goes, for the screens that were dead ends --------
//  Back is the trail's own parent link. The referrer is not consulted at all —
//  see the "Back goes one step UP" section of tests/test_nav_model.php for why
//  that was reversed on 2026-10-15.
unset($_SERVER['HTTP_REFERER']);

//  SIGN IN FIRST. Without a session every permission-gated step drops out of
//  the trail, so Back collapses to Home and the assertions below pass without
//  testing anything: "/m/designations does not go straight to Admin" is true of
//  "/" as well. The real complaint was about a signed-in administrator, so the
//  test now asks as one. (This is why the deep-screen check below moved from
//  "not Admin" to naming the exact parent it must land on.)
t_as_admin();

foreach (['licence', 'lookups', 'approval-rules', 'billing', 'preflight'] as $r) {
    $i = nav_back_info($r);
    t_ok(is_array($i) && !empty($i['url']), "/$r offers somewhere to go back to");
    t_ok(trim((string)$i['url'], '/') !== $r, "/$r does not link back to itself");
    t_ok(trim((string)($i['label'] ?? '')) !== '', "/$r names where back goes");
}

//  A screen one level deeper must go up ONE step, not all the way out. This is
//  the specific thing the owner reported: back jumped straight to Admin.
$_SERVER['HTTP_HOST'] = 'example.test';
$deep = nav_back_info('m/designations');
t_eq($deep['url'], '/masters',
    'an individual master list goes up one step, to the master lists');
t_eq($deep['label'], 'Masters', 'and says so');

//  NO REFERRER CAN MOVE IT — not one of ours, and not one from another site.
//  The second owner report was a control reading "← Masters" while the trail an
//  inch below said the parent was "Users & access"; following the referrer is
//  what made that possible, and nothing may bring it back.
foreach (['http://example.test/masters',
          'http://example.test/operations?tab=backlog',
          'https://example.org/somewhere'] as $ref) {
    $_SERVER['HTTP_REFERER'] = $ref;
    $i = nav_back_info('m/designations');
    t_eq($i['url'], '/masters', "the referrer does not move Back: $ref");
    t_ok(strpos((string)$i['url'], 'example.org') === false,
        'and a referrer from another site is never followed off-site');
}
unset($_SERVER['HTTP_REFERER']);

//  AN AREA HOME'S PARENT IS HOME. The owner photographed /admin offering
//  "← Approvals setup" — a screen inside Admin, so Back pointed downwards.
foreach (['admin', 'quality', 'directory'] as $home)
    t_eq(nav_back_info($home)['url'], '/',
        "/$home is an area home, so one step up is Home — never a screen inside it");

t_as_nobody();

//  Home itself has no parent, and must not claim one.
$layoutGuard = strpos($layout, "\$cur !== ''") !== false;
t_ok($layoutGuard, 'the home page is not given a back control');

// ---------------------------------------------------------------------------
//  THE PATH ACTUALLY WALKED                                   (owner, 2026-10-18)
//
//    "when a person has entered to Any of the lists or drop-down from masters
//     when he press back he must return to only the previous or one step up
//     screen here it is masters"
//    "no option to go back on the same path in reverse of what we entered"
//
//  Masters carries cards that open the Organisation screen, because people live
//  in ONE register. Organisation's own home is Users & access, so arriving from
//  Masters put the visitor one click from a screen they had never opened.
//
//  The arrival is now a step in the trail, and Back still just reads the trail —
//  so the two controls cannot disagree, which was the complaint a week earlier.
// ---------------------------------------------------------------------------
t_section('the trail follows the path actually walked');
t_as_admin();
$_SERVER['HTTP_HOST'] = 'example.test';
$lab = fn($t) => implode(' > ', array_map(fn($x) => $x['label'], $t));
$from = function ($ref) { $_SERVER['HTTP_REFERER'] = 'http://example.test' . $ref; };

$from('/masters');
t_eq($lab(nav_trail('hierarchy')), 'Home > Admin > Masters > Organisation',
    '*** arriving at Organisation from Masters, the trail says Masters');
t_eq(nav_back_info('hierarchy')['label'], 'Masters',
    '*** and Back agrees, because it reads that same trail');

//  THE ARRIVAL REPLACES THE CANONICAL PARENT, never stacks on it: two parents
//  in one trail names a screen the visitor did not walk through.
t_ok(strpos($lab(nav_trail('hierarchy')), 'Users & access') === false,
    '*** the canonical parent is replaced, not added alongside');

unset($_SERVER['HTTP_REFERER']);
t_eq($lab(nav_trail('hierarchy')), 'Home > Admin > Users & access > Organisation',
    '*** with no arrival to go on, the canonical trail is unchanged');

//  NEVER DOWNWARDS. Standing on an area home having come from a screen inside
//  it, "back" must not point at that screen — the 2026-10-15 defect.
$from('/approvals-setup');
t_eq($lab(nav_trail('admin')), 'Home > Admin', '*** an area home takes no arrival step');
t_eq(nav_back_info('admin')['url'], '/', '*** so Back from Admin is still Home');

//  A HUB THAT SPANS AREAS is still the right parent for what it offers. The
//  Approvals workbench reaches recruitment, quotes, reports and delegation.
$from('/approvals-setup');
foreach (['recruit-approvals', 'approver-map', 'approval-delegations'] as $r) {
    t_ok(strpos($lab(nav_trail($r)), 'Approvals >') !== false,
        "*** /$r reached through the Approvals workbench trails through it");
    t_eq(nav_back_info($r)['label'], 'Approvals', "*** and Back returns there, not to the area");
}

//  BUT AN ORDINARY CROSS-AREA LINK DOES NOT write the other area into the path.
$from('/quotes');
t_ok(strpos($lab(nav_trail('hierarchy')), 'Quote') === false,
    '*** arriving from another area does not put that area in this trail');

//  AND AN OUTSIDE SITE IS IGNORED.
$_SERVER['HTTP_REFERER'] = 'https://example.org/somewhere';
t_eq($lab(nav_trail('hierarchy')), 'Home > Admin > Users & access > Organisation',
    '*** a referrer from another site changes nothing');
unset($_SERVER['HTTP_REFERER']);

//  UAT 1.6.4 — /billing had no area at all: "Home › Billing", Back to the
//  dashboard, on a screen reached from System settings.
$t = nav_trail('billing');
t_ok(in_array('Admin', array_map(fn($x) => $x['label'], $t), true),
    '*** /billing sits under Admin, instead of hanging off Home');
t_eq(end($t)['label'], 'Users & billing', '*** and is named as its own heading names it');
t_as_nobody();
