<?php
// Defensive fallback view.
//
// The /admin route is served by ops_area_home() -> views/ops/area_home.php, and
// nothing in the current codebase calls view('admin'). But a stale or partial
// deployment can leave older code that still asks for this file — historically it
// did not exist, so that request fataled the whole app ("Failed opening required
// '.../views/admin.php'"). This file makes that legacy call render the Admin area
// home instead of crashing. It is belt-and-braces only; a clean full redeploy is
// the real fix. Safe to keep either way.

if (function_exists('ops_area_def') && function_exists('ops_area_tile_count')
    && ops_area_tile_count('admin') > 0) {
    $def = ops_area_def('admin');
    if ($def && is_file(__DIR__ . '/ops/area_home.php')) {
        require __DIR__ . '/ops/area_home.php';
        return;
    }
}
?>
<div class="panel">
  <h2 style="margin-top:0">Admin</h2>
  <p class="muted" style="margin:0 0 12px">Pick an admin screen from the left menu.</p>
  <p style="margin:0"><a class="btn" href="/settings">Open Settings</a></p>
</div>
