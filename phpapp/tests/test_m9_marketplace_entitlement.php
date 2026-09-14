<?php
// ============================================================================
//  PHASE 1 · MILESTONE 9 — MARKETPLACE / CONNECT AS A COMMERCIAL MODULE
//
//  Forty libraries, twenty-one routes, its own public front door, its own
//  freelancer portal — and until now no commercial identity at all. The only
//  thing in front of the whole subsystem was connect_enabled(), a setting that
//  DEFAULTED TO ON and which a company could flip for itself.
//
//  M9 gives it the identity the product already half-had: PRODUCT_PACKAGES
//  carries an optional 'connect' key and the setting is called connect_enabled,
//  so 'connect' names the existing commercial concept rather than inventing a
//  parallel one.
//
//  The enforcement point is connect_enabled() itself, because that is the one
//  function the whole subsystem already runs through — fifteen of the sixteen
//  connect_*_can() gates, the public front door, the freelancer portal, the
//  organisation join page and the client portal's hiring tiles.
//
//  Both directions are proved throughout. A marketplace that denied everybody
//  would pass a DENY-only suite and would be worth nothing.
// ============================================================================

t_section('Milestone 9 — Marketplace / Connect entitlement');

db();
$asTenant = function ($key = 'testco') { $GLOBALS['__tenant'] = ['key' => $key, 'company' => 'Test Co']; };
$origTenant  = $GLOBALS['__tenant'] ?? null;
$origCeil    = setting_get('saas_entitled_modules', '');
$origOff     = setting_get('modules_off', '');
$origConnect = setting_get('connect_enabled', '1');
$origKey     = (string) setting_get('licence_key', '');
$ceiling = function ($csv, $off = '') use ($asTenant) {
    setting_set('saas_entitled_modules', $csv);
    setting_set('modules_off', $off);
    $asTenant();
    licence_disabled(true);
};
$pdo = db();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_superuser, is_active)
               VALUES ('m9_master','M9','ADMIN',1,1)")->execute();
$masterId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, permissions, is_active)
               VALUES ('m9_plain','M9P','COORDINATOR','',1)")->execute();
$plainId = (int) $pdo->lastInsertId();
$login = function ($uid) use ($asTenant) {
    if ($uid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $uid;
    $asTenant(); current_user(true); ua(true); licence_disabled(true);
};
setting_set('connect_enabled', '1');               // the company's own switch is ON throughout

// ---- A · Commercial identity ------------------------------------------------
t_ok(isset(PRODUCT_MODULES['connect']), 'A · Marketplace has a product-module identity');
t_eq(PRODUCT_MODULES['connect'][0], 'Marketplace & Connect', 'A · with a display name');
t_ok(!licence_is_core('connect'),  'A · it is sellable, not core');
t_eq(PRODUCT_MODULES['connect'][2], [], 'A · and claims no access modules — no RBAC surface is minted');
t_ok(!isset(PRODUCT_MODULES['marketplace']), 'A · there is exactly one marketplace identity, not two');
// It must not have taken ownership of anything that belongs to another product.
foreach (['hiring' => 'hr', 'idems' => 'reporting', 'invoicing' => 'money',
          'quotes' => 'sales', 'jobs' => 'operations', 'masters' => 'admin'] as $a => $own)
    t_eq(licence_owner($a), $own, "A · '$a' still belongs to $own, not to Marketplace");
// Entitlement resolves through the product key, the route 'admin' already takes.
t_ok(licence_owner('connect') === null, 'A · connect is a PRODUCT key, not an access module');

// ---- B · The entitlement states ---------------------------------------------
$ceiling('connect'); $login($masterId);
t_eq(module_state('connect'), 'ENTITLED',      'B · a subscribed marketplace is ENTITLED');
t_ok(licence_module_live('connect'),           'B · and live');
t_ok(connect_enabled(),                        'B · and the subsystem opens');

$ceiling('operations,reporting'); $login($masterId);
t_eq(module_state('connect'), 'NOT_ENTITLED',  'B · outside a recorded ceiling it is NOT_ENTITLED');
t_ok(!licence_module_live('connect'),          'B · and not live');
t_ok(!connect_enabled(),                       'B · and the subsystem is closed');

$ceiling(''); $login($masterId);
t_eq(module_state('connect'), 'UNKNOWN',       'B · a blank entitlement record is UNKNOWN');
t_ok(!connect_enabled(),                       'B · and UNKNOWN is DENIED, never assumed');

$ceiling('connect', 'connect'); $login($masterId);
t_eq(module_state('connect'), 'TENANT_DISABLED', 'B · a company may switch off what it owns');
t_ok(!connect_enabled(),                       'B · and it is then closed');

$ceiling('not_a_module, rubbish'); $login($masterId);
t_ok(!connect_enabled(),                       'B · an entitlement record naming nothing real grants nothing');

// Licence-blocked: an unverifiable signed licence outranks any cloud ceiling.
$ceiling('connect,operations');
setting_set('licence_key', 'not-a-real-signed-key-so-verification-fails');
$asTenant();
if (function_exists('lk_state')) lk_state(true);
licence_disabled(true); $login($masterId);
if (function_exists('lk_modules') && lk_modules() !== null) {
    t_eq(module_state('connect'), 'LICENCE_BLOCKED', 'B · an unverifiable licence blocks the marketplace');
    t_ok(!connect_enabled(),                         'B · and the subsystem is closed');
    t_eq(module_state('admin'), 'CORE',              'B · while core survives — a bad key is not a dead install');
} else {
    t_ok(false, 'B · could not put the licence into an enforcing state — LICENCE_BLOCKED NOT covered');
}
setting_set('licence_key', $origKey);
if (function_exists('lk_state')) lk_state(true);
$asTenant(); licence_disabled(true);

// ---- C · The company's own switch still means what it meant ----------------
$ceiling('connect'); $login($masterId);
setting_set('connect_enabled', '0'); $asTenant(); licence_disabled(true);
t_ok(!connect_enabled(), 'C · an entitled company may still switch the marketplace off');
setting_set('connect_enabled', '1'); $asTenant(); licence_disabled(true);
t_ok(connect_enabled(),  'C · and back on again');
// What it can no longer do is switch ON what it never bought.
$ceiling('operations'); $login($masterId);
setting_set('connect_enabled', '1'); $asTenant(); licence_disabled(true);
t_ok(!connect_enabled(), 'C · but it cannot switch ON a marketplace it has not bought');

// ---- D · Master users -------------------------------------------------------
$ceiling('connect'); $login($masterId);
t_ok(is_master(),            'D · the test user is a master');
t_ok(connect_market_can(),   'D · ALLOW — master + entitled marketplace');
$ceiling('operations'); $login($masterId);
t_ok(is_master(),            'D · still a master');
t_ok(!connect_market_can(),  'D · DENY — master + UNENTITLED marketplace');
$ceiling('connect', 'connect'); $login($masterId);
t_ok(!connect_market_can(),  'D · DENY — master + tenant-disabled marketplace');
$ceiling(''); $login($masterId);
t_ok(!connect_market_can(),  'D · DENY — master + blank entitlement');
// A non-master is unaffected either way.
$ceiling('connect'); $login($plainId);
t_ok(!is_master(),           'D · the plain user is not a master');
$ceiling('operations'); $login($plainId);
t_ok(!connect_market_can(),  'D · and is denied when the marketplace is not entitled');

// ---- E · Every marketplace gate follows the module --------------------------
// Fifteen of the sixteen connect_*_can() gates consult connect_enabled(); the
// sixteenth (concierge) delegates to connect_market_can(). Both directions.
$GATES = ['connect_market_can', 'connect_analytics_can', 'connect_bench_can',
          'connect_channels_can', 'connect_channels_manage_can', 'connect_concierge_can',
          'connect_identity_admin_can', 'connect_match_weights_can', 'connect_msg_staff_can',
          'connect_passport_can', 'connect_qualtax_can', 'connect_qualtax_manage_can',
          'connect_verify_can', 'connect_taxonomy_can', 'connect_taxonomy_admin_can',
          'connect_source_can'];
$ceiling('operations,reporting'); $login($masterId);
$openWhenDenied = [];
foreach ($GATES as $g) if (function_exists($g) && $g() === true) $openWhenDenied[] = $g;
t_ok(!$openWhenDenied, 'E · DENY — no marketplace gate opens without the module'
     . ($openWhenDenied ? ' — OPEN: ' . implode(', ', $openWhenDenied) : ''));
$ceiling('connect,operations,reporting'); $login($masterId);
$shutWhenAllowed = [];
foreach ($GATES as $g) if (function_exists($g) && $g() !== true) $shutWhenAllowed[] = $g;
t_ok(!$shutWhenAllowed, 'E · ALLOW — every marketplace gate opens again for a master once entitled'
     . ($shutWhenAllowed ? ' — STILL SHUT: ' . implode(', ', $shutWhenAllowed) : ''));

// ---- F · Public marketplace surfaces ---------------------------------------
// The public front door, the freelancer portal and the organisation join page
// all run through the same switch, so none of them is a way round it.
$ceiling('operations'); $login(null);
t_ok(!connect_enabled(),          'F · DENY — the public marketplace front door');
t_ok(!connect_pro_portal_on(),    'F · DENY — the freelancer portal');
$ceiling('connect'); $login(null);
t_ok(connect_enabled(),           'F · ALLOW — an entitled company still serves them');
t_ok(connect_pro_portal_on(),     'F · ALLOW — including the freelancer portal');

// ---- G · Marketplace data through another route ----------------------------
// M7's lesson: route ownership is not data ownership. The marketplace tiles a
// CLIENT sees in their own portal, and what a VENDOR sees in theirs, are
// marketplace data and now carry the marketplace's owner.
t_eq(PORTAL_PERM_MODULES['market.post'], 'connect',     'G · client-portal hiring is marketplace-owned');
t_eq(PORTAL_PERM_MODULES['market.vouchers'], 'connect', 'G · client-portal voucher review too');
t_eq(VENDOR_PERM_MODULES['market.apply'], 'connect',    'G · vendor-portal applications too');
$pdo = db();
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,created_at)
               VALUES ('M9 Client','M9 Client',1,'ACTIVE',?)")->execute([date('c')]);
$pid = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO client_users (partner_id,name,email,perms,is_active)
               VALUES (?,'M9 Portal','m9@example.test','',1)")->execute([$pid]);
$_SESSION['cuid'] = (int) $pdo->lastInsertId();
$ceiling('connect,operations'); $asTenant();
t_ok(portal_user() !== null, 'G · the test portal user signs in');
t_ok(pcan('market.post'),    'G · ALLOW — an entitled company offers marketplace hiring in the portal');
$ceiling('operations'); $asTenant();
t_ok(!pcan('market.post'),     'G · DENY — it disappears when the marketplace is not entitled');
t_ok(!pcan('market.vouchers'), 'G · DENY — including voucher review');
t_ok(pcan('calls'),            'G · while Operations, which IS entitled, is untouched');
unset($_SESSION['cuid']);

// ---- H · Operations and Reporting are not collateral damage ----------------
// The rule: Marketplace OFF is not Operations OFF.
$ceiling('operations,reporting'); $login($masterId);
t_ok(!connect_enabled(),                  'H · the marketplace is off');
t_ok(licence_module_live('jobs'),         'H · Operations still runs');
t_ok(licence_module_live('calls'),        'H · and its calls');
t_ok(licence_module_live('idems'),        'H · Reporting still runs');
t_ok(can('mod.jobs.view'),                'H · an Operations screen still opens');
t_ok(can('mod.idems.view'),               'H · and a Reporting screen');
t_ok(ops_module_gate('jobs', true),       'H · the Operations route gate still allows');
t_ok(ops_module_gate('documents', true),  'H · and the Reporting route gate');
t_ok(licence_module_live('masters'),      'H · core administration is untouched');

// ---- I · Lifecycle: ON -> OFF -> ON, with nothing destroyed ----------------
$ceiling('connect,operations'); $login($masterId);
t_ok(connect_enabled(), 'I · ON — the marketplace is open');
$pdo = db();
$hadTable = true;
try { $pdo->exec("CREATE TABLE IF NOT EXISTS cx_requirements (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, created_at TEXT)"); }
catch (Throwable $e) { $hadTable = false; }
if ($hadTable) {
    try { $pdo->prepare("INSERT INTO cx_requirements (title, created_at) VALUES ('M9 lifecycle requirement', ?)")->execute([date('c')]); } catch (Throwable $e) {}
}
$rowsBefore = (int) $pdo->query("SELECT COUNT(*) FROM cx_requirements")->fetchColumn();
t_ok($rowsBefore > 0, 'I · there is marketplace data on record');

$ceiling('operations'); $login($masterId);
t_ok(!connect_enabled(), 'I · OFF — access disappears immediately');
$rowsDuring = (int) db()->query("SELECT COUNT(*) FROM cx_requirements")->fetchColumn();
t_eq($rowsDuring, $rowsBefore, 'I · OFF — and the historical data is NOT deleted');

$ceiling('connect,operations'); $login($masterId);
t_ok(connect_enabled(), 'I · ON again — reactivation restores access');
t_eq((int) db()->query("SELECT COUNT(*) FROM cx_requirements")->fetchColumn(), $rowsBefore,
     'I · ON again — with the same data still there');

// ---- J · Client-supplied values cannot buy the marketplace -----------------
$ceiling('operations'); $login($masterId);
$_GET['module'] = 'connect'; $_POST['module'] = 'connect';
$_GET['connect_enabled'] = '1'; $_POST['connect_enabled'] = '1';
$_GET['tenant'] = 'someone-else'; $_POST['saas_entitled_modules'] = 'connect';
$_REQUEST = array_merge($_GET, $_POST);
licence_disabled(true);
t_ok(!connect_enabled(),       'J · a forged module parameter does not open the marketplace');
t_ok(!connect_market_can(),    'J · nor the staff gate');
t_eq(module_state('connect'), 'NOT_ENTITLED', 'J · the engine is unmoved');
t_eq(current_tenant(), 'testco', 'J · and a forged tenant parameter changes nothing');
foreach (['module','connect_enabled','tenant'] as $k) unset($_GET[$k]);
foreach (['module','connect_enabled','saas_entitled_modules'] as $k) unset($_POST[$k]);
$_REQUEST = [];

// ---- K · Tenant isolation, both directions ---------------------------------
$ceiling('connect'); $login($masterId);
t_ok(connect_enabled(), 'K · workspace A has the marketplace');
db()->prepare("UPDATE settings SET svalue=? WHERE skey='saas_entitled_modules'")->execute(['operations']);
db(true); db(); $asTenant('workspace-b'); current_user(true); ua(true);
t_ok(!connect_enabled(),          'K · workspace B does NOT inherit A entitlement');
t_ok(licence_module_live('jobs'), 'K · B gets its OWN entitlement');
db()->prepare("UPDATE settings SET svalue=? WHERE skey='saas_entitled_modules'")->execute(['connect']);
db(true); db(); $asTenant('workspace-c'); current_user(true); ua(true);
t_ok(connect_enabled(),            'K · and a workspace that DOES have it still gets it after a switch');
t_ok(!licence_module_live('jobs'), 'K · without inheriting B Operations entitlement');

// ---- L · S-1, with the marketplace on its own footing ----------------------
// Operations ON, Reporting ON, HR / Sales / Money OFF.
$ceiling('operations,reporting'); $login($masterId);
t_ok(licence_module_live('jobs'),      'S-1 · Operations WORKS');
t_ok(licence_module_live('idems'),     'S-1 · Reporting WORKS');
t_ok(!licence_module_live('hiring'),   'S-1 · HR DENIED');
t_ok(!licence_module_live('quotes'),   'S-1 · Sales DENIED');
t_ok(!licence_module_live('invoicing'),'S-1 · Money DENIED');
t_ok(!connect_enabled(),               'S-1 · Marketplace DENIED — it follows its own entitlement');
t_ok(!connect_market_can(),            'S-1 · to a master as well');
// ...and with the marketplace added, it opens without disturbing anything else.
$ceiling('operations,reporting,connect'); $login($masterId);
t_ok(connect_enabled(),                'S-1 · Marketplace ALLOWED once subscribed');
t_ok(licence_module_live('jobs'),      'S-1 · Operations still WORKS');
t_ok(licence_module_live('idems'),     'S-1 · Reporting still WORKS');
t_ok(!licence_module_live('hiring'),   'S-1 · HR still DENIED');
t_ok(!licence_module_live('quotes'),   'S-1 · Sales still DENIED');
t_ok(!licence_module_live('invoicing'),'S-1 · Money still DENIED');

// ---- M · The control install is never limited ------------------------------
$GLOBALS['__tenant'] = ['key' => '', 'company' => ''];
setting_set('connect_enabled', '1');
$GLOBALS['__tenant'] = ['key' => '', 'company' => '']; licence_disabled(true);
t_ok(connect_enabled(), 'M · the platform owner console keeps its own marketplace');
t_ok(licence_module_live('connect'), 'M · and the module is live there');

// ---- restore ----
unset($_SESSION['uid'], $_SESSION['cuid']);
setting_set('saas_entitled_modules', (string) $origCeil);
setting_set('modules_off', (string) $origOff);
setting_set('connect_enabled', (string) $origConnect);
setting_set('licence_key', $origKey);
if (function_exists('lk_state')) lk_state(true);
if ($origTenant === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $origTenant;
licence_disabled(true); current_user(true); ua(true);
t_ok(true, 'settings and session restored');
