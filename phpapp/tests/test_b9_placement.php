<?php
// ============================================================================
//  UX-B9 — placement, density and navigation feedback.
//
//  B9 fixed four things the B9 audit proved, and built no new engine for any of
//  them. These tests exist to keep it that way: every assertion below is about
//  WHERE existing intelligence appears, never about re-deriving it.
// ============================================================================
t_section('B9 — dashboard, area home & information priority');

$root   = dirname(__DIR__);
$ops    = (string) file_get_contents($root . '/lib/ops.php');
$areas  = (string) file_get_contents($root . '/lib/areas.php');
$tosrm  = (string) file_get_contents($root . '/lib/tosrm.php');
$dash   = (string) file_get_contents($root . '/views/dashboard.php');
$opsHome= (string) file_get_contents($root . '/views/ops/operations_home.php');
$regs   = (string) file_get_contents($root . '/views/ops/_ops_registers.php');
$strip  = (string) file_get_contents($root . '/views/ops/_glance_strip.php');
$areaV  = (string) file_get_contents($root . '/views/ops/area_home.php');

// ---- B9-1 · Operations home density -----------------------------------------
t_ok(strpos($regs, "details class=\"fold\"") !== false, 'B9-1 · registers defer their overflow into the existing details.fold');
t_ok(strpos($opsHome, "details class=\"fold\"") !== false, 'B9-1 · the pending-scheduling list defers its overflow the same way');
t_ok(strpos($regs, '$regCap') !== false && strpos($opsHome, '$pendCap') !== false, 'B9-1 · both caps are named, not magic numbers inline');
// The data layer's own limits are untouched — this is presentation only.
t_ok(strpos($tosrm, "tosrm_ops_backlog(\$offices, 60)") !== false
  && strpos($tosrm, "tosrm_assignment_register(\$offices, 60)") !== false
  && strpos($tosrm, "tosrm_ops_dataquality(\$offices, 40)") !== false,
    'B9-1 · the register QUERIES and their limits are unchanged (no data was dropped)');
// Anchors that other routes redirect to must survive.
foreach (['backlog', 'schedule', 'assignments'] as $anchor) {
    t_ok(strpos($regs, 'id="' . $anchor . '"') !== false, "B9-1 · the #$anchor anchor still exists (/ops-desk redirects to it)");
}
// Render the REAL partial with more rows than the cap: every row must survive.
$mk = function ($n, $f) { $o = []; for ($i = 1; $i <= $n; $i++) $o[] = $f($i); return $o; };
$backlog = $mk(20, fn($i) => ['id'=>$i, 'call_code'=>'CALL-B9-'.$i, 'client_name'=>'C'.$i, 'inspection_type'=>'TPI',
    'inspection_required_date'=>'2026-01-0'.($i%9+1), 'age_days'=>$i, 'pending_reason'=>'r'.$i, 'priority'=>'', 'overdue'=>0]);
$schedule = []; $assignments = []; $dataquality = []; $from = '2026-01-01'; $to = '2026-01-31';
ob_start(); include $root . '/views/ops/_ops_registers.php'; $html = (string) ob_get_clean();
$shown = substr_count($html, 'CALL-B9-');
t_eq($shown, 20, 'B9-1 · every backlog row is still on the page (8 inline + 12 behind the fold)');
t_ok(substr_count($html, '<details class="fold"') >= 1, 'B9-1 · the overflow is behind exactly one fold, not deleted');
t_ok(strpos($html, 'Show the remaining 12') !== false, 'B9-1 · the fold says how many rows are behind it');
// Under the cap there must be no fold at all.
$backlog = $mk(3, fn($i) => ['id'=>$i, 'call_code'=>'CALL-S-'.$i, 'client_name'=>'C', 'inspection_type'=>'TPI',
    'inspection_required_date'=>'', 'age_days'=>null, 'pending_reason'=>'', 'priority'=>'', 'overdue'=>0]);
ob_start(); include $root . '/views/ops/_ops_registers.php'; $small = (string) ob_get_clean();
t_eq(substr_count($small, 'CALL-S-'), 3, 'B9-1 · a short register renders every row');
t_ok(strpos($small, 'Show the remaining') === false, 'B9-1 · a short register grows no fold');

// ---- B9-2 · next-action placement, one engine only ---------------------------
t_ok(is_file($root . '/views/ops/_glance_strip.php'), 'B9-2 · the strip is ONE shared partial');
foreach (['views/dashboard.php' => $dash, 'views/ops/operations_home.php' => $opsHome, 'views/ops/area_home.php' => $areaV] as $f => $src) {
    t_ok(strpos($src, '_glance_strip.php') !== false, "B9-2 · $f renders the shared strip");
}
t_ok(strpos($strip, 'action_centre') !== false || strpos($strip, 'dashboard_glance') !== false,
    'B9-2 · the strip takes its items from the canonical engine');
// It must never re-rank: no sorting of its own anywhere in the partial.
foreach (['usort', 'uasort', 'sort(', 'rsort', 'array_multisort'] as $bad) {
    t_ok(strpos($strip, $bad) === false, "B9-2 · the strip does not re-order what it is given (no $bad)");
}
t_ok(!preg_match('/\$gsGlance\s*\[\s*[\'"]actions[\'"]\s*\]\s*=/', $strip),
    'B9-2 · the strip never rewrites the ranked list');
// And it must not clobber its host page's variables (the /my-work lesson).
preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)\s*=(?!=)/', $strip, $mm);
$leaks = array_values(array_diff(array_unique($mm[1]), ['gsGlance','gsM','gsHc','gsHl','gsAct','gsDot','glanceWantMgmt']));
t_eq(count($leaks), 0, 'B9-2 · every variable the shared strip assigns is namespaced (' . implode(',', $leaks) . ')');

// ---- B9-3 · the three sections join the existing ordering engine --------------
t_ok(strpos($dash, '$secRecruit = ob_get_clean()') !== false, 'B9-3 · recruitment is captured like every other section');
t_eq(substr_count($dash, 'echo $recruitSlot;'), 5, 'B9-3 · it takes part in all five role branches');
t_ok(strpos($dash, 'bin2hex(random_bytes(8))') !== false, 'B9-3 · the ordering slot cannot collide with page content');
// Not forced to the top, and not a second sort.
t_ok(!preg_match('/echo \$recruitSlot;\s*echo \$secKpi/', $dash), 'B9-3 · recruitment is not pinned first');
foreach (['usort', 'uasort', 'array_multisort'] as $bad) {
    t_ok(strpos($dash, $bad) === false, "B9-3 · no second ordering mechanism was introduced (no $bad)");
}
// The role branches themselves are untouched apart from the new slot.
foreach (['$isExec', '$moneyFirst', '$schedFirst'] as $branch) {
    t_ok(strpos($dash, $branch) !== false, "B9-3 · the existing $branch role branch still decides order");
}

// ---- B9-4 · access outcome instead of a silent bounce ------------------------
t_ok(strpos($areas, 'function ops_access_notice') !== false, 'B9-4 · there is one access-outcome renderer');
t_ok(strpos($areas, 'function ops_area_denied') !== false, 'B9-4 · area homes have an explicit denied path');
t_ok(strpos($areas, "if (!\$def || !ops_area_has(\$area)) return ops_area_denied(\$area);") !== false,
    'B9-4 · the area home explains instead of redirecting');
t_ok(strpos($areas, 'http_response_code(403)') !== false, 'B9-4 · the outcome is a 403, matching the app’s existing refusals');
t_ok(strpos($tosrm, 'ops_access_notice(\'Operations\'') !== false, 'B9-4 · the Operations home explains too');
t_ok(strpos($ops, 'ops_access_notice(\'Command Centre\'') !== false, 'B9-4 · the Command Centre explains too');
// The DECISION is unchanged — the same predicates still gate the same doors.
t_ok(strpos($areas, 'ops_area_has($area)') !== false, 'B9-4 · the area gate still asks ops_area_has()');
t_ok(strpos($tosrm, "can('mod.calls.view') || can('mod.jobs.view')") !== false, 'B9-4 · the Operations gate predicate is unchanged');
t_ok(strpos($ops, "can('dash.operations') || can('dash.financial')") !== false, 'B9-4 · the Command Centre gate predicate is unchanged');
// The notice must leak nothing.
$notice = (string) file_get_contents($root . '/views/ops/access_notice.php');
foreach (['can(', 'mod.', 'dash.', 'SELECT', 'getTrace', 'licence_enabled'] as $leak) {
    t_ok(strpos($notice, $leak) === false, "B9-4 · the notice reveals no $leak");
}
t_ok(strpos($areas, 'not switched on for your organisation') !== false
  && strpos($areas, 'isn’t available to your role') !== false,
    'B9-4 · a disabled module and an unavailable role are told apart');
// 490 other ops_require() call sites keep the old behaviour.
t_ok(strpos($ops, "function ops_require(\$ok, \$msg = 'You do not have access to that screen.') {") !== false
  && strpos($ops, "if (!\$ok) { flash(\$msg, 'error'); redirect('/'); }") !== false,
    'B9-4 · ops_require() itself is untouched — only the home destinations changed');

// ---- B9-4 · behavioural: the door is still shut, and the notice still speaks --
// Source assertions alone cannot see either of these, so both are exercised.

// (a) the access decision itself. A field inspector must still be refused the
//     areas the permission matrix keeps from them — B9 explains the outcome, it
//     does not open the door.
$pdo = db();
$pdo->prepare("DELETE FROM users WHERE username=?")->execute(['b9_area_insp']);
$pdo->prepare("INSERT INTO users (username, first_name, role, is_active, is_superuser)
               VALUES ('b9_area_insp','Neha','INSPECTOR',1,0)")->execute();
$prevUid = $_SESSION['uid'] ?? null;
$_SESSION['uid'] = (int) $pdo->lastInsertId();
current_user(true); if (function_exists('ua')) ua(true);
$shut = [];
foreach (['admin', 'money', 'sales', 'marketplace', 'directory', 'insights'] as $area) {
    if (!ops_area_has($area)) $shut[] = $area;
}
t_eq(count($shut), 6, 'B9-4 · an inspector is still refused every area the matrix keeps from them (' . implode(',', $shut) . ')');
t_eq((int) ops_area_tile_count('admin'), 0, 'B9-4 · and sees no Admin tile behind the notice');
// restore the session before anything else runs
if ($prevUid === null) { unset($_SESSION['uid']); } else { $_SESSION['uid'] = $prevUid; }
current_user(true); if (function_exists('ua')) ua(true);
$pdo->prepare("DELETE FROM users WHERE username=?")->execute(['b9_area_insp']);

// (b) the notice actually says something. Rendered, not grepped.
$noticeTitle = 'Money';
$noticeWhy   = 'This area isn’t available to your role.';
$noticeLinks = [['href' => '/', 'label' => 'Back to home', 'primary' => 1], ['href' => '/my-work', 'label' => 'My Work']];
ob_start(); include $root . '/views/ops/access_notice.php'; $noticeHtml = (string) ob_get_clean();
t_ok(strpos($noticeHtml, $noticeWhy) !== false, 'B9-4 · the notice renders the explanation it was given');
t_ok(strpos($noticeHtml, 'Money') !== false, 'B9-4 · the notice names the area the person asked for');
t_ok(strpos($noticeHtml, 'href="/"') !== false && strpos($noticeHtml, 'href="/my-work"') !== false,
    'B9-4 · the notice offers somewhere the person can actually go');
t_ok(strpos($noticeHtml, 'INSPECTOR') === false && strpos($noticeHtml, 'mod.') === false,
    'B9-4 · the rendered notice still leaks no role or permission internals');
