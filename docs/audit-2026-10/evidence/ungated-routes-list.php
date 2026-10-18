<?php
/**
 * EXAACT audit, GAP-008 — which dispatched routes carry NO module gate?
 *
 * ops.php applies the module gate as:
 *      if ($mod && !can("mod.$mod.view")) { access_deny($mod); ... }
 * so a route whose module resolves to null or '' passes that gate untouched.
 * It may still enforce something of its own further in — many do — but it is
 * NOT protected by the module/licence check, and that is what this lists.
 *
 * $mod is resolved by ops_route_module_map() first, then by the prefix
 * fallback ops_module_family(). Both are consulted here, in that order, exactly
 * as the dispatcher does.
 *
 * Read-only. Prints a TSV so the per-role probe can consume it.
 *
 *   cd phpapp && php ../docs/audit-2026-10/evidence/ungated-routes-list.php
 */
$root = dirname(__DIR__, 3) . '/phpapp';
require $root . '/tests/lib.php';
require $root . '/tests/bootstrap.php';

$routes = array_values(array_filter(array_map('trim',
    file(dirname(__FILE__) . '/routes-dispatched.txt'))));
$map = function_exists('ops_route_module_map') ? ops_route_module_map() : [];

$ungated = [];
$gated = 0;
foreach ($routes as $r) {
    $mod = $map[$r] ?? null;
    if (!$mod && function_exists('ops_module_family')) $mod = ops_module_family($r);
    if ($mod) { $gated++; continue; }
    $ungated[] = $r;
}

fwrite(STDERR, "dispatched routes       : " . count($routes) . "\n");
fwrite(STDERR, "carry a module gate     : $gated\n");
fwrite(STDERR, "NO module gate          : " . count($ungated) . "\n");

echo "route\n";
foreach ($ungated as $r) echo $r . "\n";
