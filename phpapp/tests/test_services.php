<?php
// ============================================================================
//  SERVICE SCOPE ENGINE — service independence, activation & override.
//  Boots the real app on a throwaway SQLite db, then exercises:
//    1. Migration seeds the catalog; every service is offered & in scope by
//       default (backward compatible — nothing that worked yesterday changes).
//    2. Global catalog toggle removes a service everywhere (menu gate).
//    3. Per-scope resolution: most-specific-active wins
//       (Assignment > PO > Project > Contract > Client > catalog default).
//    4. Independence: turning one service off never affects another.
//    5. Optional dependencies: none by default; configured ones are scoped and
//       advisory (svc_unmet_dependencies), imposing no forced workflow.
// ============================================================================
$__standalone = empty($GLOBALS['__test_db']);
if ($__standalone) { require __DIR__ . '/lib.php'; require __DIR__ . '/bootstrap.php'; }

if (!function_exists('ok'))   { function ok($cond, $msg) { return t_ok($cond, $msg); } }
if (!function_exists('head')) { function head($t) { t_section($t); } }

// ---------------------------------------------------------------------------
head('1. Migration — catalog seeded, everything on by default');
$cat = svc_catalog();
ok(count($cat) === count(SERVICE_CATALOG), 'service_catalog seeded (' . count($cat) . ' services)');
$codes = array_map(fn($c) => $c['code'], $cat);
foreach (['INSPECTION','EXPEDITING','VENDOR_ASSESSMENT','VENDOR_AUDIT','RELEASE','DEPUTATION'] as $need)
    ok(in_array($need, $codes, true), "catalog contains $need");
// Backward compatible: with nothing configured, every service is active in scope.
ok(svc_active('VENDOR_AUDIT', ['client_id'=>1]), 'a service with no override is active by default');
ok(svc_active('INSPECTION', []), 'a service is active even with an empty context');
// Idempotent re-seed.
services_seed_catalog(); services_seed_catalog(true);
ok(count(svc_catalog()) === count(SERVICE_CATALOG), 'catalog seed is idempotent');

// ---------------------------------------------------------------------------
head('2. Global catalog toggle — offered vs not offered (menu gate)');
ok(svc_globally_active('EXPEDITING'), 'EXPEDITING globally active by default');
svc_set_global('EXPEDITING', false);
ok(!svc_globally_active('EXPEDITING'), 'turning EXPEDITING off globally hides it');
ok(!svc_active('EXPEDITING', ['client_id'=>1]), 'a globally-off service is never in scope anywhere');
ok(svc_resolve('EXPEDITING', ['client_id'=>1])['source'] === 'global-off', 'resolution reports source=global-off');
svc_set_global('EXPEDITING', true);
ok(svc_globally_active('EXPEDITING'), 'turning it back on restores it');

// ---------------------------------------------------------------------------
head('3. Per-scope resolution — most specific active wins');
// Client 100: turn Vendor Audit OFF at client level.
svc_set_scope('CLIENT', 100, 'VENDOR_AUDIT', false, 'client does not buy audits');
ok(!svc_active('VENDOR_AUDIT', ['client_id'=>100]), 'client-level OFF makes the service inactive for that client');
ok(svc_resolve('VENDOR_AUDIT', ['client_id'=>100])['source'] === 'CLIENT', 'resolution source is CLIENT');
// A different client is unaffected.
ok(svc_active('VENDOR_AUDIT', ['client_id'=>101]), 'another client is unaffected (default active)');
// Contract 200 under client 100 turns it BACK ON — more specific wins.
svc_set_scope('CONTRACT', 200, 'VENDOR_AUDIT', true, 'this contract includes an audit');
ok(svc_active('VENDOR_AUDIT', ['client_id'=>100,'contract_id'=>200]), 'contract-level ON overrides client-level OFF (more specific wins)');
ok(svc_resolve('VENDOR_AUDIT', ['client_id'=>100,'contract_id'=>200])['source'] === 'CONTRACT', 'source is CONTRACT');
// Assignment 300 turns it OFF again — most specific of all wins.
svc_set_scope('ASSIGNMENT', 300, 'VENDOR_AUDIT', false, 'not for this one assignment');
ok(!svc_active('VENDOR_AUDIT', ['client_id'=>100,'contract_id'=>200,'assignment_id'=>300]), 'assignment-level OFF beats contract & client');
// Clearing the client override falls back to catalog default (active).
svc_set_scope('CLIENT', 100, 'VENDOR_AUDIT', null);
ok(svc_active('VENDOR_AUDIT', ['client_id'=>100]), 'clearing the override falls back to the catalog default');
// default_scope_active flips the catalog baseline.
svc_set_default_scope('VENDOR_ASSESSMENT', false);
ok(!svc_active('VENDOR_ASSESSMENT', ['client_id'=>555]), 'catalog default OFF makes a service opt-in per client');
svc_set_scope('CLIENT', 555, 'VENDOR_ASSESSMENT', true);
ok(svc_active('VENDOR_ASSESSMENT', ['client_id'=>555]), 'a client can opt back in above an OFF default');
svc_set_default_scope('VENDOR_ASSESSMENT', true);

// ---------------------------------------------------------------------------
head('4. Independence — one service never gates another');
$map = svc_scope_map(['client_id'=>100]);
ok($map['INSPECTION'] === true && $map['EXPEDITING'] === true, 'Inspection & Expediting active independently');
// Turn every OTHER service off for client 100 (dynamically, so new catalog
// services don't break this); Inspection must still be active.
$others = array_values(array_filter(array_map(fn($c)=>$c['code'], svc_catalog()), fn($c)=>$c!=='INSPECTION'));
foreach ($others as $c) svc_set_scope('CLIENT', 100, $c, false);
ok(svc_active('INSPECTION', ['client_id'=>100]), 'Inspection runs with every other service switched off');
ok(!svc_active('RELEASE', ['client_id'=>100]) && !svc_active('DEPUTATION', ['client_id'=>100]), 'the others are genuinely off');
$active = svc_active_codes(['client_id'=>100]);
ok($active === ['INSPECTION'], 'only Inspection is in scope for client 100 (' . implode(',', $active) . ')');
// Clean up client 100 overrides.
foreach ($others as $c) svc_set_scope('CLIENT', 100, $c, null);

// ---------------------------------------------------------------------------
head('5. Optional dependencies — none by default; configured = scoped & advisory');
ok(svc_dependencies('RELEASE', ['client_id'=>1]) === [], 'no dependency configured by default (no forced workflow)');
ok(svc_unmet_dependencies('RELEASE', ['client_id'=>1], []) === [], 'nothing blocks Release when nothing is configured');
// A client that DOES require Inspection before Release.
svc_set_dependency('RELEASE', 'INSPECTION', ['level'=>'CLIENT', 'ref_id'=>700, 'condition'=>'final release only']);
$unmet = svc_unmet_dependencies('RELEASE', ['client_id'=>700], []);
ok(count($unmet) === 1 && $unmet[0]['requires_code'] === 'INSPECTION', 'configured dependency surfaces as unmet when inspection not done');
// Once inspection is satisfied, the dependency is met.
ok(svc_unmet_dependencies('RELEASE', ['client_id'=>700], ['INSPECTION']) === [], 'dependency is met once Inspection is satisfied');
// A DIFFERENT client has no such dependency — it stays independent.
ok(svc_unmet_dependencies('RELEASE', ['client_id'=>701], []) === [], 'the dependency is scoped: another client stays independent');
// If the prerequisite service is itself not in scope, it cannot block.
svc_set_scope('CLIENT', 700, 'INSPECTION', false);
ok(svc_unmet_dependencies('RELEASE', ['client_id'=>700], []) === [], 'a prerequisite that is not in scope cannot block the dependent service');
svc_set_scope('CLIENT', 700, 'INSPECTION', null);
// Remove the dependency.
$deps = svc_all_dependencies();
foreach ($deps as $d) svc_delete_dependency($d['id']);
ok(svc_all_dependencies() === [], 'dependencies can be removed — back to fully independent');

// ---------------------------------------------------------------------------
if ($__standalone) {
    $g = $GLOBALS['__t'];
    echo "\n==================== SERVICES: {$g['pass']} passed, {$g['fail']} failed ====================\n";
    exit($g['fail'] === 0 ? 0 : 1);
}
