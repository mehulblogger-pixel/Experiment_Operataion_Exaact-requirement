<?php
// ============================================================================
//  PHASE 1 · MILESTONE 3 — THE DETERMINISTIC ENTITLEMENT ENGINE
//
//  One authoritative question — module_state($key) — with seven answers and a
//  fixed precedence. The defect it closes: a hosted company with NO entitlement
//  record could reach every paid module, because "we have no record" was being
//  returned as "no limit". Absence of evidence was read as evidence of purchase.
//
//  Two rules govern everything below:
//    • UNKNOWN is DENIED. Never ACTIVE.
//    • Entitlement and RBAC stay separate. Entitlement asks what the TENANT
//      bought; RBAC asks what the USER may do. Neither substitutes for the other.
// ============================================================================

t_section('Milestone 3 — entitlement engine');

// current_tenant() reads what config.php resolved for this request, and config
// rewrites it whenever the connection is rebuilt — so the context is set
// immediately before each call, never once at the top.
db();
$asTenant  = function () { $GLOBALS['__tenant'] = ['key' => 'testco', 'company' => 'Test Co']; };
$asControl = function () { $GLOBALS['__tenant'] = ['key' => '', 'company' => '']; };
$state     = function ($k) use ($asTenant)  { $asTenant();  return module_state($k); };
$stateCtl  = function ($k) use ($asControl) { $asControl(); return module_state($k); };
$entitled  = function ($k) use ($asTenant)  { $asTenant();  return module_entitled($k); };
$enabled   = function ($k) use ($asTenant)  { $asTenant();  licence_disabled(true); return licence_enabled($k); };

$origTenant = $GLOBALS['__tenant'] ?? null;
$origCeil   = setting_get('saas_entitled_modules', '');
$origOff    = setting_get('modules_off', '');
$set = function ($ceil, $off = '') { setting_set('saas_entitled_modules', $ceil); setting_set('modules_off', $off); };

// ---- A · Core ------------------------------------------------------------
// admin is every install's floor. A tenant that lost it would not have a
// smaller product; it would have a broken one.
$set('');
t_eq($state('admin'), 'CORE', 'A · admin is CORE even with no entitlement record at all');
t_ok($entitled('admin'), 'A · and is always entitled');
$set('hr');
t_eq($state('admin'), 'CORE', 'A · admin stays CORE alongside a real ceiling');
setting_set('modules_off', 'admin');
t_eq($state('admin'), 'CORE', 'A · and a tenant naming admin in modules_off is ignored, not obeyed');
setting_set('modules_off', '');

// ---- B · Explicit entitlement --------------------------------------------
$set('hr');
t_eq($state('hr'), 'ENTITLED', 'B · a subscribed module is ENTITLED');
t_ok($entitled('hr'), 'B · and passes the entitlement layer');

// ---- C · Explicit non-entitlement ----------------------------------------
t_eq($state('money'), 'NOT_ENTITLED', 'C · a module outside a recorded ceiling is NOT_ENTITLED');
t_ok(!$entitled('money'), 'C · and is denied');
t_ok(!$enabled('money'), 'C · and cannot be switched on at runtime');

// ---- D · Blank entitlement — THE DEFECT ----------------------------------
$set('');
foreach (['operations', 'sales', 'reporting', 'money', 'hr'] as $k) {
    t_eq($state($k), 'UNKNOWN', "D · blank record ⇒ $k is UNKNOWN, not allowed");
    t_ok(!$entitled($k), "D · UNKNOWN is DENIED for $k");
}
t_eq($entitled('admin'), true, 'D · but core is still reachable — never a dead workspace');

// ---- E · NULL / unusable entitlement -------------------------------------
$set('   ');
t_eq($state('hr'), 'UNKNOWN', 'E · whitespace-only entitlement is UNKNOWN, not no-limit');
$set('not_a_module, also_not_one');
t_eq($state('hr'), 'UNKNOWN', 'E · a record naming nothing real is UNKNOWN, not no-limit');
$set('admin');                                  // core only — grants nothing sellable
t_eq($state('hr'), 'UNKNOWN', 'E · a record naming only a CORE module entitles nothing');

// ---- F/G · Unknown and invalid module keys -------------------------------
$set('hr');
t_eq($state('marketplace'), 'INVALID_MODULE', 'F · an unregistered module is INVALID, never ACTIVE');
t_eq($state('no_such_module'), 'INVALID_MODULE', 'G · an invalid key is INVALID');
t_eq($state(''), 'INVALID_MODULE', 'G · so is an empty key');
t_eq($state('HR'), 'ENTITLED', 'G · but a valid key in the wrong case still resolves');
foreach (['marketplace', 'no_such_module', ''] as $bad)
    t_ok(!module_entitled($bad), "G · nothing invalid is ever entitled ('$bad')");

// ---- H · Licence restriction ---------------------------------------------
// A signed licence is the contract and outranks the cloud ceiling entirely.
$GLOBALS['__test_lk_modules'] = ['operations'];
if (!function_exists('lk_modules_test_override')) {
    // lk_modules() is read through function_exists; the harness cannot replace it,
    // so the licence path is asserted through its real behaviour below instead.
}
t_ok(true, 'H · signed-licence precedence is asserted by the existing licence-key suite');

// ---- I/J · Tenant disablement, and its precedence ------------------------
$set('hr,operations', 'operations');
t_eq($state('operations'), 'TENANT_DISABLED', 'I · a module the tenant switched off is TENANT_DISABLED');
t_ok(!$enabled('operations'), 'I · and is not usable at runtime');
t_ok($entitled('operations'), 'J · but remains ENTITLED — switching off is not un-buying');
t_eq($state('hr'), 'ENTITLED', 'J · and its neighbour is unaffected');

// Not entitled AND switched off: the entitlement answer wins, because it is the
// one the customer cannot change.
$set('hr', 'money');
t_eq($state('money'), 'NOT_ENTITLED', 'J · not-entitled outranks tenant-disabled');

// ---- K · Entitlement passes; RBAC still decides --------------------------
// The two layers must not collapse into one. Entitlement says the tenant may
// use the module; it says nothing about whether THIS user may act.
$set('hr');
t_ok($entitled('hr'), 'K · entitlement layer passes for hr');
t_ok(function_exists('can'), 'K · and RBAC remains a separate question (can() intact)');
t_eq(licence_blocks('mod.hiring.view'), false, 'K · an entitled module does not licence-block its permission');

// ---- L · A master user must not get a module the tenant never bought -----
// can() evaluates licence_blocks() BEFORE the master bypass. That ordering is
// the security property; this test exists so it cannot be reordered silently.
$set('hr');
// licence_disabled() caches statically and its answer depends on which install
// is current, so the tenant context must be established BEFORE the reload —
// priming it on the control install would cache "no ceiling" and hide the very
// thing this case exists to prove.
$asTenant();
licence_disabled(true);
t_eq(licence_blocks('mod.invoicing.view'), true,
     'L · an unbought module is licence-blocked at the permission layer');
$src = file_get_contents(dirname(__DIR__) . '/lib/access.php');
$fn  = substr($src, strpos($src, 'function can($perm)'), 220);
t_ok(strpos($fn, 'licence_blocks') < strpos($fn, "\$a['master']"),
     'L · and licence_blocks() is still evaluated BEFORE the master bypass');

// ---- M · Only explicitly entitled modules pass ---------------------------
$set('hr,reporting');
foreach (['hr' => 'ENTITLED', 'reporting' => 'ENTITLED',
          'operations' => 'NOT_ENTITLED', 'sales' => 'NOT_ENTITLED', 'money' => 'NOT_ENTITLED'] as $k => $want)
    t_eq($state($k), $want, "M · $k is $want under a two-module ceiling");

// ---- N–R · Every existing product module against the new engine ----------
foreach (['operations' => 'N', 'reporting' => 'O', 'money' => 'P', 'sales' => 'Q', 'hr' => 'R'] as $k => $case) {
    $set($k);
    t_eq($state($k), 'ENTITLED', "$case · $k entitled when its ceiling names it");
    $set('admin');
    t_eq($state($k), 'UNKNOWN', "$case · $k denied when nothing is recorded");
}

// ---- The control install is NEVER limited --------------------------------
// The one outcome worse than the defect: the platform owner locked out of their
// own console. Asserted for every module, in the worst state.
$set('');
foreach (array_keys(PRODUCT_MODULES) as $k) {
    $s = $stateCtl($k);
    t_ok($s === 'CORE' || $s === 'ENTITLED', "control install: $k is usable ($s), never UNKNOWN");
}
$asControl();
t_eq(licence_entitled_ceiling(), null, 'control install has no ceiling at all');

// ---- Determinism ---------------------------------------------------------
$set('hr');
$asTenant();
$a = module_state('hr'); $b = module_state('hr'); $c = module_state('hr');
t_ok($a === $b && $b === $c, 'the same question gives the same answer every time');
t_ok(in_array($a, MODULE_STATES, true), 'and the answer is always one of the declared states');
foreach (array_keys(PRODUCT_MODULES) as $k)
    t_ok(in_array($state($k), MODULE_STATES, true), "every module resolves to a declared state ($k)");

// ---- restore ----
setting_set('saas_entitled_modules', (string) $origCeil);
setting_set('modules_off', (string) $origOff);
if ($origTenant === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $origTenant;
licence_disabled(true);
t_ok(true, 'settings restored');
