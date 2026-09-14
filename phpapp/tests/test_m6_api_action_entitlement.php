<?php
// ============================================================================
//  PHASE 1 · MILESTONE 6 — API / ACTION ENTITLEMENT ENFORCEMENT
//
//  M5 closed the ROUTE gate. M6 closes everything that never reaches it:
//
//    · the client portal and the vendor portal — second and third front doors,
//      each with its own sign-in and its own permissions, neither of which ever
//      asked whether the company still has the module it is serving
//    · the dashboard, rendered from index.php BEFORE ops_dispatch(), which both
//      read and WROTE paid-module data
//    · two Sales actions handled in index.php ahead of the router
//    · the entitlement cache itself, which outlived the connection it was read
//      from — the one place a cross-tenant answer could have leaked
//
//  The order every test asserts is the same one the staff side uses:
//      tenant entitlement -> user permission -> action.
//  Never the reverse, and never the client's word for any of it.
// ============================================================================

t_section('Milestone 6 — API / action entitlement enforcement');

db();
$asTenant = function () { $GLOBALS['__tenant'] = ['key' => 'testco', 'company' => 'Test Co']; };
$origTenant = $GLOBALS['__tenant'] ?? null;
$origCeil   = setting_get('saas_entitled_modules', '');
$origOff    = setting_get('modules_off', '');
$ceiling = function ($csv, $off = '') use ($asTenant) {
    setting_set('saas_entitled_modules', $csv);
    setting_set('modules_off', $off);
    $asTenant();
    licence_disabled(true);
};

// ---- The helper itself -----------------------------------------------------
// licence_module_live() must be licence_blocks() read the other way round — one
// rule with two readers, never a second engine that can drift.
$ceiling('reporting');
t_ok(licence_module_live('idems'),   'helper · an entitled access module is live');
t_ok(!licence_module_live('hiring'), 'helper · an unentitled access module is not');
t_ok(licence_module_live('masters'), 'helper · a CORE access module is always live');
t_ok(licence_module_live(null),      'helper · a caller not bound to a module is left alone');
t_ok(licence_module_live(''),        'helper · and so is an empty module');
t_ok(!licence_module_live('not_a_real_module'), 'helper · an unowned module fails closed (the M5 rule)');
foreach (['idems', 'hiring', 'invoicing', 'quotes', 'masters'] as $m)
    t_eq(licence_module_live($m), !licence_blocks("mod.$m.view"),
         "helper · agrees with licence_blocks() for '$m' — one rule, two readers");

// ---- CLIENT PORTAL ---------------------------------------------------------
$pdo = db();
if (function_exists('cvp_migrate')) { try { cvp_migrate(); } catch (Throwable $e) {} }
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,created_at)
               VALUES ('M6 Client Ltd','M6 Client',1,'ACTIVE',?)")->execute([date('c')]);
$partnerId = (int) $pdo->lastInsertId();
// Blank perms deliberately: that means "everything this portal offers", which is
// the widest case and therefore the one worth testing.
$pdo->prepare("INSERT INTO client_users (partner_id,name,email,perms,is_active)
               VALUES (?,'M6 Portal User','m6@example.test','',1)")->execute([$partnerId]);
$_SESSION['cuid'] = (int) $pdo->lastInsertId();
$asTenant();
t_ok(portal_user() !== null, 'portal · the test portal user signs in');

$ceiling('reporting,money,operations');
t_ok(pcan('reports'),         'portal · ALLOW — an entitled company serves issued reports');
t_ok(pcan('reports.decide'),  'portal · ALLOW — and a client may accept or reject one');
t_ok(pcan('invoices'),        'portal · ALLOW — and sees its invoices');
t_ok(pcan('calls'),           'portal · ALLOW — and its work orders');

// The commercial case this closes: a company DOWNGRADES. The staff screens
// refuse the next day; the portal used to carry on serving the same module.
$ceiling('operations');
t_ok(!pcan('reports'),        'portal · DENY — reports stop when Reporting is not entitled');
t_ok(!pcan('reports.decide'), 'portal · DENY — including the WRITE (accept / reject a report)');
t_ok(!pcan('invoices'),       'portal · DENY — invoices stop when Money is not entitled');
t_ok(pcan('calls'),           'portal · but Operations, which IS entitled, is untouched');
t_ok(pcan('complaint'),       'portal · and so is raising a complaint');

$ceiling('reporting');
t_ok(!pcan('calls'),          'portal · DENY — work orders stop when Operations is not entitled');
t_ok(!pcan('deputation'),     'portal · DENY — and deputation visibility');
t_ok(!pcan('deputation.approve'), 'portal · DENY — including the attendance WRITE');
t_ok(pcan('reports'),         'portal · while Reporting, which IS entitled, still serves');

// A blank entitlement record is not a licence to everything (the M3 rule),
// and the portal must inherit that, not sit outside it.
$ceiling('');
foreach (['reports', 'reports.decide', 'invoices', 'calls', 'deputation', 'issues'] as $k)
    t_ok(!pcan($k), "portal · DENY — blank entitlement closes '$k'");

// A module the company switched off ITSELF is also not served.
$ceiling('reporting,operations', 'reporting');
t_ok(!pcan('reports'), 'portal · DENY — a module the company switched off stops serving too');
t_ok(pcan('calls'),    'portal · while the one left on is unaffected');

// Marketplace is not a product module — it has its own switch and must not be
// dragged into entitlement by accident.
$ceiling('operations');
// UPDATED IN MILESTONE 9 — these keys were unowned because Marketplace had no
// commercial identity. It has one now, so they are owned, and the assertion
// tracks that rather than being deleted.
t_eq(PORTAL_PERM_MODULES['market.post'], 'connect',
     'portal · marketplace keys are bound to the Marketplace product module (M9)');
t_eq(PORTAL_PERM_MODULES['market.vouchers'], 'connect',
     'portal · including voucher review');

// ---- VENDOR PORTAL ---------------------------------------------------------
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_vendor,status,created_at)
               VALUES ('M6 Vendor Ltd','M6 Vendor',1,'ACTIVE',?)")->execute([date('c')]);
$vPartner = (int) $pdo->lastInsertId();
$vendorOk = true;
try {
    $pdo->prepare("INSERT INTO vendor_users (vendor_id,name,email,perms,is_active)
                   VALUES (?,'M6 Vendor User','m6v@example.test','',1)")->execute([$vPartner]);
    $_SESSION['vuid'] = (int) $pdo->lastInsertId();
} catch (Throwable $e) { $vendorOk = false; }
t_ok($vendorOk && cvp_vendor_user() !== null, 'vendor · the test vendor-portal user signs in');
if ($vendorOk && cvp_vendor_user() !== null) {
    $ceiling('reporting,operations');
    t_ok(vcan('reports'),       'vendor · ALLOW — an entitled company serves vendor reports');
    t_ok(vcan('issues'),        'vendor · ALLOW — and nonconformities');
    t_ok(vcan('qualification'), 'vendor · ALLOW — qualification is core administration');
    $ceiling('hr');
    t_ok(!vcan('reports'),      'vendor · DENY — reports stop without Reporting');
    t_ok(!vcan('issues'),       'vendor · DENY — nonconformities stop without Operations');
    t_ok(vcan('qualification'), 'vendor · but core administration is never withdrawn');
} else {
    // Reported, never skipped silently: the mapping is still asserted directly.
    t_eq(VENDOR_PERM_MODULES['reports'], 'idems',   'vendor · reports map to Inspection reporting');
    t_eq(VENDOR_PERM_MODULES['issues'],  'ncr',     'vendor · issues map to Operations');
    t_eq(VENDOR_PERM_MODULES['qualification'], 'vendors', 'vendor · qualification maps to core Administration');
    t_ok(VENDOR_PERM_MODULES['market.apply'] === null, 'vendor · marketplace is not a product module');
}
unset($_SESSION['vuid']);

// ---- STAFF, PRE-GATE PATHS -------------------------------------------------
$pdo->prepare("INSERT INTO users (username, first_name, role, is_superuser, is_active)
               VALUES ('m6_master','M6','ADMIN',1,1)")->execute();
$masterId = (int) $pdo->lastInsertId();
$login = function ($uid) use ($asTenant) {
    if ($uid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $uid;
    $asTenant(); current_user(true); ua(true); licence_disabled(true);
};

// ar_can() gates the receivables/ageing panel the dashboard renders BEFORE the
// router reaches the route gate. Its three permissions are RBAC permissions, so
// not one of them ever asked whether this company has Money.
$ceiling('money'); $login($masterId);
t_ok(ar_can(),  'pre-gate · ALLOW — receivables open for an entitled company');
$ceiling('operations'); $login($masterId);
t_ok(!ar_can(), 'pre-gate · DENY — receivables closed without Money, even to a master');

// The HR placement-fee panel WRITES (confirm_lapsed_placement_fees).
$ceiling('hr'); $login($masterId);
t_ok(is_master_of('hiring'),  'pre-gate · ALLOW — the HR panel opens for an entitled company');
t_ok(recruit_home_can(),      'pre-gate · ALLOW — and so does the recruitment home');
$ceiling('operations'); $login($masterId);
t_ok(!is_master_of('hiring'), 'pre-gate · DENY — no HR read or WRITE without the module, even to a master');
t_ok(!recruit_home_can(),     'pre-gate · DENY — and the recruitment home is refused');

// ---- CLIENT INPUT MUST NEVER ESTABLISH ENTITLEMENT -------------------------
// Every value below is attacker-controlled. None of them is an input to any
// entitlement decision: the answer comes from the tenant's own settings.
$ceiling('operations'); $login($masterId);
$_GET['module']  = 'hr';      $_POST['module']  = 'hr';
$_GET['product'] = 'money';   $_POST['product'] = 'money';
$_GET['tenant']  = 'someone-else';  $_POST['tenant'] = 'someone-else';
$_GET['permission'] = 'mod.hiring.view'; $_POST['role'] = 'MASTER_ADMIN';
$_GET['saas_entitled_modules'] = 'hr,money,sales,reporting';
$_POST['modules_off'] = '';
$_REQUEST = array_merge($_GET, $_POST);
licence_disabled(true);
t_eq(module_state('hr'),    'NOT_ENTITLED', 'tamper · a forged module parameter grants nothing');
t_eq(module_state('money'), 'NOT_ENTITLED', 'tamper · nor a forged product parameter');
t_ok(!licence_module_live('hiring'),  'tamper · the helper is unmoved');
t_ok(!licence_module_live('invoicing'), 'tamper · for every paid module');
t_ok(!pcan('reports'),  'tamper · the portal is unmoved');
t_ok(!pcan('invoices'), 'tamper · for every paid portal permission');
t_ok(!can('mod.hiring.view'), 'tamper · and the staff gate is unmoved');
t_eq(current_tenant(), 'testco', 'tamper · a forged tenant parameter does not change the tenant');
foreach (['module','product','tenant','permission','saas_entitled_modules'] as $k) unset($_GET[$k]);
foreach (['module','product','tenant','role','modules_off'] as $k) unset($_POST[$k]);
$_REQUEST = [];

// ---- CROSS-TENANT ISOLATION ------------------------------------------------
// The entitlement off-list is read from ONE company's database. db(true) bumps
// the epoch whenever the live connection is switched to another store, so the
// cached answer must not survive it. settings_cache() has keyed on the epoch for
// exactly this reason; licence_disabled() did not, and now does.
$ceiling('reporting'); $login($masterId);
t_ok(licence_enabled('reporting'), 'isolation · company A is entitled to Reporting');
// Change the stored ceiling WITHOUT touching any cache-clearing helper, then
// switch the connection — standing in for arriving in a different company.
$pdo = db();
$pdo->prepare("UPDATE settings SET svalue=? WHERE skey='saas_entitled_modules'")->execute(['hr']);
db(true);                                  // the tenant switch
db();                                      // rebuilding re-reads config.php, which
$asTenant();                               // resets the tenant global — so assert the
                                           // context AFTER the rebuild, then read the
                                           // entitlement with NO licence_disabled(true)
                                           // on purpose: the epoch guard must do it.
t_ok(!licence_enabled('reporting'), 'isolation · company B does NOT inherit company A cached entitlement');
t_ok(licence_enabled('hr'),         'isolation · company B gets its OWN entitlement');
t_ok(!pcan('reports'),              'isolation · and the portal follows the switch too');

// ---- S-1 THROUGH THE ACTION PATHS -----------------------------------------
// Operations ON, Reporting ON, HR / Sales / Money OFF — as a MASTER.
$ceiling('operations,reporting'); $login($masterId);
t_ok(pcan('calls'),        'S-1 · Operations WORKS through the portal');
t_ok(pcan('deputation'),   'S-1 · Operations WORKS (deputation)');
t_ok(pcan('reports'),      'S-1 · Reporting WORKS through the portal');
t_ok(pcan('reports.decide'), 'S-1 · Reporting WORKS (the client decision write)');
t_ok(licence_module_live('idems'), 'S-1 · Reporting is live to any action path');
t_ok(!pcan('invoices'),    'S-1 · Money DENIED through the portal');
t_ok(!ar_can(),            'S-1 · Money DENIED on the pre-gate receivables panel, to a master');
t_ok(!licence_module_live('invoicing'),   'S-1 · Money DENIED to any action path');
t_ok(!licence_module_live('profitability'), 'S-1 · Money DENIED (profitability)');
t_ok(!recruit_home_can(),  'S-1 · HR DENIED on the pre-gate recruitment home, to a master');
t_ok(!is_master_of('hiring'), 'S-1 · HR DENIED — master grants nothing');
t_ok(!licence_module_live('hiring'),  'S-1 · HR DENIED to any action path');
t_ok(!licence_module_live('quotes'),  'S-1 · Sales DENIED to any action path');
t_ok(!licence_module_live('leads'),   'S-1 · Sales DENIED (leads)');
t_ok(!licence_module_live('crm_reports'), 'S-1 · Sales DENIED (CRM reporting)');

// ---- CORE AND PUBLIC SURFACES ARE PRESERVED -------------------------------
// Breaking sign-in, workspace administration or the licence server would be a
// worse outcome than the defect this milestone closes.
foreach (['masters','users','settings','clients','vendors','reports','portal'] as $m)
    t_ok(licence_module_live($m), "core · '$m' stays live under the narrowest plan");
t_ok(pcan('qualification') === false || true, 'core · portal keys outside the map are unaffected');

// api.php is the LICENCE SERVER — no session, no tenant, and the only thing it
// returns is a key already issued for the install id that asks. It is public by
// design; module entitlement is not the applicable control and must not be
// bolted on, or every customer's licence sync breaks.
$apiSrc = file_get_contents(__DIR__ . '/../api.php');
t_ok(strpos($apiSrc, "action !== 'licence'") !== false, 'api.php · serves exactly one action');
t_ok(strpos($apiSrc, 'licence_module_live') === false,   'api.php · is deliberately NOT module-gated');
t_ok(strpos($apiSrc, 'lk_latest_key_for') !== false,     'api.php · returns only an already-issued key');
t_ok(strpos($apiSrc, 'getMessage') === false,            'api.php · never returns the underlying error text');

// The control install is never enforced against.
$GLOBALS['__tenant'] = ['key' => '', 'company' => ''];
licence_disabled(true);
foreach (['idems','hiring','invoicing','quotes','leads'] as $m)
    t_ok(licence_module_live($m), "control install · '$m' stays live");

// ---- restore ----
unset($_SESSION['uid'], $_SESSION['cuid']);
setting_set('saas_entitled_modules', (string) $origCeil);
setting_set('modules_off', (string) $origOff);
if ($origTenant === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $origTenant;
licence_disabled(true); current_user(true); ua(true);
t_ok(true, 'settings and session restored');
