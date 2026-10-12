<?php
// ============================================================================
//  THE 390px PASS                                            (UAT Journey G · R-19)
//
//  R-19 had been open since the beginning: "individual screens have been
//  corrected as they were found, but no screen-by-screen audit at 390px has
//  been done." It has now been done, in a real browser, across all 156 openable
//  screens. Before: 2 screens dragged the page sideways and 152 carried a
//  control under the 44px touch floor. After: 0 and 0.
//
//  The sweep itself needs a browser and cannot live here. What CAN live here is
//  the shape of the fixes, because every one of them was a SHARED cause and
//  each would be silently lost by an edit that looked harmless:
//
//    · .tbl-wrap was never defined at all — 13 views wrapped tables in a class
//      that did nothing, so those tables pushed the page instead of scrolling;
//    · `repeat(2,1fr)` does not shrink below min-content, so 50 inline grids
//      across 24 views were latent page-wideners; collapsing the template was
//      not enough on its own, because a child asking for `grid-column:1/3`
//      makes the grid create the second track again;
//    · the top bar's three controls appear on every screen, which is why one
//      fix moved 152 of them;
//    · four configuration screens carry private button families written before
//      .btn settled at 44px.
//
//  And the stylesheet's braces are counted, because this pass introduced an
//  unterminated @media block that silently swallowed every rule after it.
// ============================================================================

t_section('R-19 — the 390px pass holds');

$css = (string) @file_get_contents(__DIR__ . '/../assets/css/app.css');
t_ok($css !== '', 'the stylesheet is readable');

//  1. BRACES BALANCE. An unterminated block takes everything after it with it.
$depth = 0; $wentNegative = false;
for ($i = 0, $n = strlen($css); $i < $n; $i++) {
    $c = $css[$i];
    if ($c === '{') $depth++;
    elseif ($c === '}') { $depth--; if ($depth < 0) $wentNegative = true; }
}
t_eq($depth, 0, '*** every block in the stylesheet is closed — no rule is swallowed by an open one');
t_ok(!$wentNegative, '*** …and none is closed twice');

//  2. THE SHARED FIXES ARE PRESENT.
$has = fn($needle) => strpos($css, $needle) !== false;
t_ok($has('.tbl-wrap{'), '*** .tbl-wrap is defined — 13 views wrap tables in it');
t_ok(preg_match('~\.tbl-wrap\{[^}]*overflow-x:auto~', $css) === 1,
     '*** …and it actually scrolls, which is what those views assumed');
t_ok($has('[style*="grid-template-columns:repeat"]'),
     '*** the inline 1fr grids collapse on a phone');
t_ok($has('[style*="grid-template-columns:repeat"] > [style*="grid-column"]'),
     '*** …and a child spanning to line 3 cannot re-create the second column');
t_ok($has('.topbar-slim .tb-cmdk'), '*** the top bar carries a touch-target floor');
foreach (['.rp-btn', '.qtx-btn', '.qtx-tab', '.pw-eye', '.ch-mode-btn'] as $fam)
    t_ok(strpos($css, $fam) !== false, "the $fam button family is raised to the touch floor");

//  3. EVERY VIEW THAT USES .tbl-wrap IS COVERED BY THE ONE DEFINITION.
$users = 0;
foreach (array_merge(glob(__DIR__ . '/../views/ops/*.php') ?: [],
                     glob(__DIR__ . '/../views/ops/*/*.php') ?: []) as $f)
    if (strpos((string) @file_get_contents($f), 'tbl-wrap') !== false) $users++;
t_ok($users >= 10, 'ARMING · .tbl-wrap is used widely enough to matter (' . $users . ' views)');

// ---------------------------------------------------------------------------
//  AND THE CRASH THE SWEEP FOUND.
//
//  /approval-delegations answered HTTP 500 for everybody. The handler passed
//  rcc_users(), which returns a map of id => NAME — a list of strings — and the
//  view iterates it as database rows, asking each for ['id'] and ['username'].
//  PHP 8 threw mid-render, so the page half-drew and then printed an error box.
//  No test opened this screen, which is why only a sweep could find it.
// ---------------------------------------------------------------------------
t_section('R-19 — the screen the sweep found broken');
$people = ops_all("SELECT id, first_name, last_name, username
                     FROM users WHERE COALESCE(is_active,1)=1 ORDER BY first_name, last_name, username");
t_ok(is_array($people), 'the delegation screen is given a list');
if ($people) {
    $first = $people[0];
    t_ok(is_array($first), '*** …of ROWS, not of names');
    t_ok(array_key_exists('id', $first), '*** each row carries the id the screen asks for');
    t_ok(array_key_exists('username', $first), '*** …and the username it falls back to');
}
//  The shape that crashed it, stated so nobody passes it again.
if (function_exists('rcc_users')) {
    $map = rcc_users();
    if ($map) {
        $v = reset($map);
        t_ok(is_string($v), 'ARMING · rcc_users() really does return names, not rows — do not pass it here');
    }
}
$src = (string) @file_get_contents(__DIR__ . '/../lib/recruit_approval.php');
$code = (string) preg_replace('~^\s*//.*$~m', '', (string) preg_replace('~/\*.*?\*/~s', '', $src));
t_ok(strpos($code, "'people'   => ops_all(") !== false,
     '*** the handler passes rows to the delegation screen');
t_ok(!preg_match("~'people'\s*=>\s*function_exists\('rcc_users'\)~", $code),
     '*** …and never the id => name map again');
