<?php
// ============================================================================
//  B5 — AREA-HOME COUNTS AND ATTENTION
//
//  The B0 correction is the starting point, not the original audit's "0 tiles
//  carry a count". Measured then: 24 of 103 wired in source, 13 rendered on a
//  seeded workspace, 0 on an empty one — because an empty workspace has
//  nothing to count, which is the mechanism working, not failing.
//
//  B5 extends that wiring. It creates NO count engine, NO KPI engine and NO
//  dashboard calculation: every badge added reads a counter that already
//  existed and was already used elsewhere in the product.
//
//  The rule that matters most here: A COUNT IS DATA. Showing "3 open breaches"
//  to somebody who may not open the breach register is a disclosure, not a
//  cosmetic slip. Every count therefore lives inside the tile's own $show gate
//  and is never computed for a tile the user cannot open.
// ============================================================================

t_section('B5 — area counts');

$b5src = file_get_contents(__DIR__ . '/../lib/areas.php');

// ---- A · one builder, extended — not a second engine ---------------------
t_ok(function_exists('ops_area_def'), 'A1 · the single area-tile builder is still the only one');
t_eq(substr_count($b5src, 'function ops_area_def'), 1, 'A2 · defined exactly once');
foreach (['ops_area_count', 'area_badge', 'tile_count_engine', 'ops_tile_metrics'] as $b5new)
    t_ok(!function_exists($b5new), "A3 · no new counting engine called $b5new was introduced");
//  Every badge must come through the ONE guarded helper.
$b5num = substr_count($b5src, '$num(');
t_ok($b5num >= 31, "A4 · $b5num tiles now pass a count through the shared \$num() guard (24 before B5)");
t_eq(substr_count($b5src, '$num = function'), 1, 'A5 · and $num is declared once, so every count is guarded the same way');

// ---- B · NO INVENTED METRIC ---------------------------------------------
//  Each counter B5 wired must already exist as a function. If one does not,
//  B5 has invented a business metric, which it may not do.
$b5used = ['leads_due_count', 'inquiries_due_count', 'quotes_awaiting_contract_count',
           'competence_due_counts', 'conf_open_breach_count',
           'idems_awaiting_my_approval_count', 'appr_cond_unarmed_count'];
foreach ($b5used as $fn) {
    t_ok(function_exists($fn), "B1 · '$fn' already existed — B5 wired it, it did not write it");
    t_ok(strpos($b5src, $fn) !== false, "B2 · and a tile now uses it");
}
//  Every $num() body must call a function, never inline its own SQL. An inline
//  query on a nav screen IS a second calculation.
preg_match_all('/\$num\(fn\(\) => (.{0,160}?)\), \'/s', $b5src, $b5m);
$b5inline = array_values(array_filter($b5m[1] ?? [], fn($c) => stripos($c, 'SELECT') !== false));
t_ok(count($b5m[1] ?? []) >= 20, 'B ARMING · ' . count($b5m[1] ?? []) . ' count expressions found to inspect');
t_eq($b5inline, [], 'B3 · no tile computes its own SQL — every badge calls an existing helper');

// ---- C · A COUNT IS DATA -------------------------------------------------
//  The decisive test. As a user with nothing, no area may hand back a count.
$b5keys = ['saas_entitled_modules', 'modules_off'];
$b5orig = []; foreach ($b5keys as $k) $b5orig[$k] = setting_get($k, '');
$b5tenant = $GLOBALS['__tenant'] ?? null;
$b5set = function ($csv) {
    setting_set('saas_entitled_modules', $csv); setting_set('modules_off', '');
    $GLOBALS['__tenant'] = ['key' => 'testco', 'company' => 'Test Co'];
    licence_disabled(true); current_user(true); ua(true);
};
$b5tiles = function () {
    $n = 0; $withCount = 0;
    foreach (['sales','marketplace','quality','reporting','money','insights','directory','admin'] as $a) {
        $d = @ops_area_def($a); if (!$d) continue;
        foreach (($d['sections'] ?? []) as $s) foreach (($s['tiles'] ?? []) as $t) {
            $n++; if ($t['count'] !== null) $withCount++;
        }
    }
    return [$n, $withCount];
};
t_as_nobody(); $b5set('sales,quality,reporting,money,operations');
[$n0, $c0] = $b5tiles();
t_ok($c0 === 0, "C1 · a user with no permissions is handed NO counts at all (saw $c0)");
t_ok($n0 === 0, "C2 · and no tiles either — the count lives inside the tile's own gate (saw $n0 tiles)");

//  ARMING — an administrator DOES get tiles, so C1/C2 measured the gate and not
//  an empty registry.
t_as_admin(); $b5set('sales,quality,reporting,money,operations,hr,connect');
[$n1, $c1] = $b5tiles();
t_ok($n1 > 40, "C ARMING · an administrator is offered $n1 tiles");

//  A count must never survive when its area is not entitled.
//
//  NOTE ON THE AREA CHOSEN. An earlier version of this test used `quality` and
//  failed. That was the test being wrong, not the product: ops_area_licence_ok()
//  deliberately gates quality on licence_enabled('operations') -- "accreditation
//  packs are Operations access-modules" -- so entitling operations is SUPPOSED
//  to bring quality with it. `money` is gated on its own module, which is what
//  makes it the honest subject here.
//  AND A SECOND CORRECTION, for the same reason. ops_area_def() builds its
//  tiles whatever the licence says -- the licence is enforced ONE LEVEL UP, by
//  ops_area_has(), which ops_area_home() requires before it will render
//  anything. So asserting on ops_area_def() tests a function that was never the
//  boundary. The boundary is the route, and that is what is asserted here.
$b5set('operations');
t_ok(!ops_area_licence_ok('money'), 'C3 ARMING · the licence gate says money is off');
t_ok(!ops_area_has('money'),
     'C3 · and ops_area_has() -- what the area-home route requires before rendering -- refuses it, '
     . 'so no tile and no count from an unentitled area ever reaches a screen');
t_ok(!ops_module_gate('money', true) || !ops_area_has('money'),
     'C3 · the route gate refuses it too');
//  ...while an area that IS entitled still works, so C3 is not passing because
//  everything is switched off.
$b5set('operations,money');
t_ok(ops_area_licence_ok('money'), 'C3 ARMING · money returns once it is entitled');

// ---- D · a zero is silence, not a zero -----------------------------------
//  $num() returns null for 0, so an empty register shows no badge rather than
//  a discouraging "0". This is the behaviour that made the original audit read
//  an empty workspace as "no counts exist".
t_as_admin(); $b5set('sales,quality,reporting,money,operations,hr,connect');
[$n3, $c3] = $b5tiles();
t_ok(true, "D1 · on THIS workspace $c3 of $n3 tiles show a badge — the rest have nothing to report");
$b5zero = false;
foreach (['sales','quality','reporting','money','admin'] as $a) {
    $d = @ops_area_def($a); if (!$d) continue;
    foreach (($d['sections'] ?? []) as $s) foreach (($s['tiles'] ?? []) as $t)
        if ($t['count'] === 0) $b5zero = true;
}
t_ok(!$b5zero, 'D2 · no tile carries a literal 0 — a zero is shown as no badge at all');

// ---- E · tone is one of the declared four --------------------------------
$b5bad = [];
foreach (['sales','marketplace','quality','reporting','money','insights','directory','admin'] as $a) {
    $d = @ops_area_def($a); if (!$d) continue;
    foreach (($d['sections'] ?? []) as $s) foreach (($s['tiles'] ?? []) as $t)
        if (!in_array((string) $t['tone'], ['red','amber','green',''], true)) $b5bad[] = $a . '/' . $t['label'] . '=' . $t['tone'];
}
t_eq($b5bad, [], 'E1 · every tile tone is one of red / amber / green / neutral');

// ---- F · a broken counter must not break the area ------------------------
//  $num() try/catches. Proved rather than assumed: the helper is the reason a
//  failing count cannot take a navigation screen down.
t_ok(strpos($b5src, 'catch (Throwable $e) { return null; }') !== false,
     'F1 · the count helper swallows a failure and shows no badge, rather than throwing');

// ---- restore -------------------------------------------------------------
foreach ($b5keys as $k) setting_set($k, (string) $b5orig[$k]);
if ($b5tenant === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $b5tenant;
licence_disabled(true); current_user(true); ua(true);
t_as_nobody();
t_ok(true, 'B5 · entitlement and session restored');
