<?php
// ============================================================================
//  PHASE 1 · MILESTONE 2 — THE AUTHORITATIVE MODULE REGISTRY
//
//  Entitlement can only be as trustworthy as the registry it is computed from.
//  Everything later in Phase 1 — precedence, route enforcement, API and cron
//  gating, the administrator UX — resolves a route or a permission to a
//  COMMERCIAL module through exactly one path:
//
//      route/permission → access module → licence_owner() → product module
//
//  These tests hold that path closed. They assert no new behaviour; they pin
//  the structure the rest of the phase is about to depend on, so that a module
//  added later cannot quietly arrive without an owner — which is how a paid
//  module becomes reachable for free.
// ============================================================================

t_section('Milestone 2 — module registry');

// ---- One registry, six commercial modules --------------------------------
t_ok(defined('PRODUCT_MODULES'), 'there is a product module registry');
t_eq(count(PRODUCT_MODULES), 6, 'it holds exactly six commercial modules');
foreach (['operations', 'admin', 'sales', 'reporting', 'money', 'hr'] as $k)
    t_ok(isset(PRODUCT_MODULES[$k]), "the registry declares '$k'");

// Keys are the commercial identity. A duplicate or a renamed key would split
// one product into two and make entitlement unanswerable.
t_eq(count(array_unique(array_keys(PRODUCT_MODULES))), count(PRODUCT_MODULES),
     'every product module is uniquely identified');
foreach (PRODUCT_MODULES as $k => $m) {
    t_ok(preg_match('/^[a-z][a-z0-9_]*$/', $k) === 1, "'$k' is a plain lowercase key");
    t_ok(is_string($m[0]) && $m[0] !== '', "'$k' has a label");
    t_ok(is_array($m[2]), "'$k' declares the access modules it covers");
}

// ---- Recruitment is 'hr', and nothing else -------------------------------
// A second identifier for the same product is the fastest way to sell a module
// twice and enforce it never.
t_ok(isset(PRODUCT_MODULES['hr']), 'Recruitment is registered as hr');
t_eq(PRODUCT_MODULES['hr'][0], 'People & hiring', 'with its existing label');
t_eq(licence_owner('hiring'), 'hr', "the 'hiring' access module belongs to hr");
foreach (['recruitment', 'talent', 'hiring', 'recruitment_module'] as $rival)
    t_ok(!isset(PRODUCT_MODULES[$rival]), "'$rival' is NOT a competing commercial module");

// ---- Core ----------------------------------------------------------------
t_ok(licence_is_core('admin'), 'admin is core');
foreach (['operations', 'sales', 'reporting', 'money', 'hr'] as $k)
    t_ok(!licence_is_core($k), "'$k' is sellable, not core");

// ---- Quality and Marketplace are not commercial modules ------------------
// Quality lives inside the Operations boundary; splitting it would create a
// second product nobody sold. Marketplace is Milestone 9 and its legacy cloud
// default is not a purchase.
foreach (['quality', 'qms', 'accreditation'] as $k)
    t_ok(!isset(PRODUCT_MODULES[$k]), "Quality is not independently registered ('$k')");
t_ok(!isset(PRODUCT_MODULES['marketplace']), 'Marketplace is not registered — deferred to Milestone 9');
t_ok(!isset(PRODUCT_MODULES['connect']), 'and neither is Connect');

// ---- The bridge: every access module has exactly one owner ---------------
$covers = [];
foreach (PRODUCT_MODULES as $k => [$l, $d, $c, $core])
    foreach ($c as $a) $covers[$a][] = $k;

$multi = array_filter($covers, fn($o) => count($o) > 1);
t_ok(!$multi, 'no access module is claimed by two product modules'
     . ($multi ? ' — ' . implode(', ', array_keys($multi)) : ''));

$catalogue = array_keys(ACCESS_MODULES);
$unowned = array_values(array_diff($catalogue, array_keys($covers)));
t_ok(!$unowned, 'every catalogued access module has an owner'
     . ($unowned ? ' — UNOWNED: ' . implode(', ', $unowned) : ''));

$phantom = array_values(array_diff(array_keys($covers), $catalogue));
t_ok(!$phantom, 'no product module claims an access module that does not exist'
     . ($phantom ? ' — PHANTOM: ' . implode(', ', $phantom) : ''));

t_eq(count($catalogue), count($covers), 'the catalogue and the ownership map are the same size');

// licence_owner() must agree with the registry it is derived from, for every
// key — a static cache that drifted would be invisible everywhere else.
$mismatch = [];
foreach ($covers as $a => $owners) if (licence_owner($a) !== $owners[0]) $mismatch[] = $a;
t_ok(!$mismatch, 'licence_owner() agrees with the registry for every access module'
     . ($mismatch ? ' — ' . implode(', ', $mismatch) : ''));

// Spot-checks across all six, so a wholesale re-parenting cannot pass silently.
foreach ([['calls', 'operations'], ['jobs', 'operations'], ['audits', 'operations'],
          ['masters', 'admin'], ['users', 'admin'], ['portal', 'admin'],
          ['leads', 'sales'], ['quotes', 'sales'], ['idems', 'reporting'],
          ['invoicing', 'money'], ['profitability', 'money'], ['hiring', 'hr']] as [$a, $own])
    t_eq(licence_owner($a), $own, "$a → $own");

// An unknown access module has no owner. This is the current, deliberate
// posture — an access module nobody licensed stays reachable rather than
// vanishing. Pinned here because Milestone 3/5 will revisit exactly this and
// must do so knowingly, not by accident.
t_eq(licence_owner('no_such_access_module'), null,
     'an unknown access module has no owner (fail-open today — Milestone 3/5 decides this)');
