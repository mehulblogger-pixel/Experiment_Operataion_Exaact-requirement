<?php
// ---------------------------------------------------------------------------
//  GUARD RAIL FOR GAP-008 — A ROUTE MUST NOT LOSE ITS PERMISSION GATE.
//
//  The dispatcher gates a screen like this (ops.php):
//
//      if ($mod && !can("mod.$mod.view")) { access_deny($mod); … }
//
//  $mod comes from ops_route_module_map(), or failing that from the prefix
//  fallback ops_module_family(). When neither yields a module, that line does
//  nothing: the route passes the module check and the licence check untouched.
//
//  The 2026-10 audit measured 200 of 565 dispatched routes in exactly that
//  position, tested all of them per role, and found no reachable hole — 158
//  enforce their own check and the rest are public, personal, read-only or
//  hubs. The full result is in docs/audit-2026-10/06-DATA-AND-PERMISSION-AUDIT.md.
//
//  So this file does NOT try to force a module onto every route; many
//  legitimately have none. It guards the thing that would actually hurt: a
//  route that IS gated today silently losing its gate tomorrow, through a
//  rename, a tidy-up of the map, or a prefix that stops matching.
//
//  tests/fixtures/routes_gated.tsv is the inventory taken when this was
//  measured: 365 routes, each with the module it resolved to and whether the
//  map or the prefix fallback supplied it. If a route vanishes from that
//  resolution, this test names it.
//
//  WHEN THIS FAILS, READ THE MESSAGE BEFORE EDITING THE FIXTURE. Two cases:
//    · the route was renamed or retired on purpose  -> update the fixture
//    · the route still exists and lost its gate     -> that is the bug
//  Blanket-regenerating the fixture to get green would throw away the only
//  thing standing between a lost gate and nobody noticing.
// ---------------------------------------------------------------------------
t_section('GAP-008 guard rail — no route may lose its permission gate');

$fx = __DIR__ . '/fixtures/routes_gated.tsv';
t_ok(is_file($fx), 'the gated-route inventory exists');

$expected = [];
foreach (file($fx) as $line) {
    $line = rtrim($line, "\r\n");
    if ($line === '' || $line[0] === '#') continue;
    $p = explode("\t", $line);
    if (count($p) >= 2) $expected[$p[0]] = $p[1];
}
t_ok(count($expected) > 300, 'the inventory holds the routes measured in October (' . count($expected) . ')');

//  Resolve each one the way the dispatcher does: the map first, then the
//  prefix fallback. Deliberately NOT reading the fixture's "resolved-by"
//  column — it does not matter which of the two supplies the module, only
//  that one of them still does.
$lost = [];
$moved = [];
foreach ($expected as $route => $mod) {
    $map = ops_route_module_map();
    $now = $map[$route] ?? null;
    if (!$now && function_exists('ops_module_family')) $now = ops_module_family($route);
    if (!$now)            $lost[]  = $route;
    elseif ($now !== $mod) $moved[] = "$route: $mod -> $now";
}

//  $got first, $want second — so a failure reads "want '', got '<the route>'"
//  and names the offender rather than inverting it.
t_eq(implode(', ', $lost), '',
     'no route lost its permission gate (any route named here now resolves to NO '
   . 'module, so it passes the module and licence checks untouched)');

//  A module CHANGE is not automatically wrong — a screen can legitimately move
//  between modules — but it changes who may open the screen, so it must be a
//  decision somebody made rather than a side effect. Per CLAUDE.md that also
//  means docs/02-permission-matrix.md moves in the same commit.
t_eq(implode(' | ', $moved), '',
     'no route quietly changed module (a change moves who may open the screen, '
   . 'so it belongs in the permission matrix too)');

// ---------------------------------------------------------------------------
t_section('GAP-010 safety condition — every money route stays gated');

//  The books module's write gate accepts can('data.credit') — a permission
//  defined as "See the inter-office credit figures" (access.php:133), i.e. a
//  READ right about a figure on a job screen, standing in for a right that
//  should say "may change the books".
//
//  The owner reviewed the resulting behaviour on 2026-10-18 and accepted it
//  (a coordinator, branch manager and business director may record a receipt
//  and start a draft invoice; Accounts still issues). The permission matrix
//  row 215 was corrected to match.
//
//  That acceptance rests on ONE condition: every money route also carries a
//  module gate, so data.credit alone is never enough to reach a write. It held
//  when measured — all 17 routes in the books handler — but two of them get
//  their module from the PREFIX FALLBACK rather than the map, which a rename
//  would break. This asserts the condition directly, so it cannot quietly stop
//  being true.
$money = ['invoices', 'invoice', 'invoice-new', 'invoice-issue', 'invoice-cancel',
          'invoice-line-add', 'invoice-line-delete', 'invoice-print',
          'invoice-set-branch', 'receipts', 'receipt', 'receipt-new',
          'receipt-allocate', 'receipt-unallocate', 'credit-note-new',
          'to-bill', 'ledger'];
$map = ops_route_module_map();
foreach ($money as $r) {
    $mod = $map[$r] ?? null;
    if (!$mod && function_exists('ops_module_family')) $mod = ops_module_family($r);
    t_ok($mod !== null && $mod !== '',
         "/$r still requires a module, so data.credit alone cannot reach it");
}
