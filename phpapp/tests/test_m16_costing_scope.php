<?php
// ============================================================================
//  PHASE 1 · MILESTONE 16 — PROJECT COSTING SCOPE (product decision, Option B)
//
//  M14 found that project costings were branch-GLOBAL: the register applied no
//  scope clause at all, so everyone holding the costing permission saw every
//  branch's day rates, overheads, contingency, negotiation margin and expected
//  revenue. That was not a leak against the product's own design — list and
//  detail agreed — so M14 recorded it and referred it for a business decision
//  rather than changing it.
//
//  The decision taken in M16 is OPTION B: costings follow branch scope.
//
//  These tests hold that decision in place. They also pin the part that is easy
//  to get wrong: an UNASSIGNED costing stays visible to every branch, because
//  scope_office_clause() deliberately shows unfiled work to everyone, and a gate
//  that disagreed with its own list would hide records the register intends to
//  be seen.
// ============================================================================

t_section('Milestone 16 — project costing follows branch scope (Option B)');

$pdo = db();
$now = date('c');
$origSession = $_SESSION;

foreach ([[93, 'M16 Branch A'], [94, 'M16 Branch B']] as $o) {
    try { $pdo->prepare("INSERT INTO offices (id,name,is_active) VALUES (?,?,1)")->execute($o); } catch (Throwable $e) {}
}
if (function_exists('pc_migrate')) pc_migrate();

$mk = function ($office) use ($pdo, $now) {
    $pdo->prepare("INSERT INTO project_costings (code,title,office_id,status,created_at) VALUES (?,?,?,'DRAFT',?)")
        ->execute(['M16-PC-' . ($office ?: 'NONE'), 'commercial detail', $office, $now]);
    return (int) $pdo->lastInsertId();
};
$pcB    = $mk(94);      // another branch's costing
$pcA    = $mk(93);      // the viewer's own
$pcNone = $mk(null);    // filed to no branch at all

// The viewer: scoped to Branch A only, and holding the costing permission.
$pdo->prepare("INSERT INTO users (username,first_name,role,scope_offices,home_office_id,is_active)
               VALUES ('m16_branch_a','CostingA','ADMIN','93',93,1)")->execute();
$_SESSION['uid'] = (int) $pdo->lastInsertId();
if (function_exists('auth_bind_workspace')) auth_bind_workspace();
current_user(true); ua(true);

t_eq(scope_offices(), [93], 'the viewer is scoped to one branch');
t_ok(!is_master(),          'and is not a master');
t_ok(function_exists('pc_can') && pc_can(), 'yet HOLDS the costing permission — the decision is about SCOPE, not permission');

// ---- the register ---------------------------------------------------------
$codes = array_column(pc_all(), 'code');
t_ok(!in_array('M16-PC-94', $codes, true), 'the REGISTER no longer shows another branch\'s costing');
t_ok(in_array('M16-PC-93', $codes, true),  'it still shows the viewer\'s own');
t_ok(in_array('M16-PC-NONE', $codes, true), 'and an UNASSIGNED costing stays visible to every branch');

// ---- the object gate, which must agree with the register ------------------
t_ok(function_exists('pc_scope_gate'), 'the module has one gate for every costing route');
t_ok(!scope_office_allows(ops_val("SELECT office_id FROM project_costings WHERE id=?", [$pcB])),
     'the GATE refuses another branch\'s costing — detail and register agree');
t_ok(scope_office_allows(ops_val("SELECT office_id FROM project_costings WHERE id=?", [$pcA])),
     'and allows the viewer\'s own');
t_ok(scope_office_allows(ops_val("SELECT office_id FROM project_costings WHERE id=?", [$pcNone])),
     'and allows the unassigned one, exactly as the register does');

// The gate runs on ENTRY to the module, so every route is covered by construction
// — including the ones that name the costing `costing_id` rather than `id`.
$src = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/projcosting.php'));
$p = strpos($src, 'function ops_projcosting($route, $method) {');
t_ok($p !== false && strpos(substr($src, $p, 300), 'pc_scope_gate()') !== false,
     'and it runs on entry to the module, not per route');
$g = strpos($src, 'function pc_scope_gate');
t_ok($g !== false && strpos(substr($src, $g, 600), "costing_id") !== false,
     'the gate resolves BOTH spellings of the id, so no route escapes through the other one');

// ---- the owning branch keeps full use -------------------------------------
$pdo->prepare("INSERT INTO users (username,first_name,role,scope_offices,home_office_id,is_active)
               VALUES ('m16_branch_b','CostingB','ADMIN','94',94,1)")->execute();
$_SESSION['uid'] = (int) $pdo->lastInsertId();
if (function_exists('auth_bind_workspace')) auth_bind_workspace();
current_user(true); ua(true);
$codesB = array_column(pc_all(), 'code');
t_ok(in_array('M16-PC-94', $codesB, true), 'Branch B still sees its OWN costing — not over-scoped');
t_ok(!in_array('M16-PC-93', $codesB, true), 'and not Branch A\'s');
t_ok(in_array('M16-PC-NONE', $codesB, true), 'and the unassigned one is still everybody\'s');

// ---- head office / master is unaffected ------------------------------------
$pdo->prepare("INSERT INTO users (username,first_name,role,is_superuser,is_active)
               VALUES ('m16_master','HeadOffice','ADMIN',1,1)")->execute();
$_SESSION['uid'] = (int) $pdo->lastInsertId();
if (function_exists('auth_bind_workspace')) auth_bind_workspace();
current_user(true); ua(true);
$codesM = array_column(pc_all(), 'code');
t_ok(in_array('M16-PC-93', $codesM, true) && in_array('M16-PC-94', $codesM, true),
     'head office (ALL scope) still sees every branch — Option B scopes branches, it does not blind the owner');

// ---- leave the database as we found it ------------------------------------
foreach ([$pcA, $pcB, $pcNone] as $rid) {
    try { db()->prepare("DELETE FROM project_costings WHERE id=?")->execute([(int) $rid]); } catch (Throwable $e) {}
}
foreach (['m16_branch_a', 'm16_branch_b', 'm16_master'] as $un) {
    try { db()->prepare("DELETE FROM users WHERE username=?")->execute([$un]); } catch (Throwable $e) {}
}
foreach ([93, 94] as $oid) { try { db()->prepare("DELETE FROM offices WHERE id=?")->execute([$oid]); } catch (Throwable $e) {} }

$_SESSION = $origSession;
current_user(true); ua(true);

// ============================================================================
//  M16 — THE MARKETPLACE-DESK ENTITLEMENT BYPASS
//
//  Found on a LIVE HOST, not by reading code: signed in as a master on a
//  workspace entitled to Operations + Reporting only, /marketplace-escrow and
//  /financial-control both served their Marketplace screens.
//
//  The cause is the M10 anti-pattern on routes M9 added after that audit ran:
//
//      ops_require(is_master() || connect_market_can(), …)
//
//  is_master() short-circuits the OR, so connect_market_can() — which DOES check
//  the licence — was never consulted. Swapping the order would have been
//  cosmetic; the master branch still wins wherever it sits. It is deleted
//  instead, because connect_market_can() already returns true for a master when
//  Connect is live and false for everyone when it is not.
// ============================================================================

t_section('Milestone 16 — marketplace desk is not exempt from entitlement');

foreach (['lib/mkt_escrow.php' => 'escrow', 'lib/mkt_ledger.php' => 'financial control'] as $rel => $what) {
    $src = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../' . $rel));
    t_ok(strpos($src, 'connect_market_can()') !== false,
         $what . ' · the desk still asks the entitlement-aware helper');
    t_ok(!preg_match('/ops_require\(\s*\(?function_exists\(\'is_master\'\)\s*&&\s*is_master\(\)\)?\s*\|\|/', $src),
         $what . ' · ATTACK BLOCKED — no bare is_master() short-circuits that helper any more');
}

// And the helper it now relies on must genuinely be licence-aware, or deleting
// the master branch would have achieved nothing.
$cm = preg_replace('#^\s*//.*$#m', '', file_get_contents(__DIR__ . '/../lib/connect_market.php'));
$p  = strpos($cm, 'function connect_market_can');
t_ok($p !== false && strpos(substr($cm, $p, 400), 'connect_enabled()') !== false,
     'and connect_market_can() refuses everyone — master included — when Connect is not live');
$p2 = strpos($cm, 'function connect_enabled');
t_ok($p2 !== false && strpos(substr($cm, $p2, 400), 'licence_module_live') !== false,
     'because connect_enabled() is itself bound to the licence');
