<?php
// ============================================================================
//  PHASE 1 · MILESTONE 12 — LOCKED MODULE UX
//
//  Before M12 every refusal in the application went through one line:
//
//      ops_require(false, 'some sentence')  →  flash + redirect('/')
//
//  Whatever had gone wrong — the company never bought the module, the licence
//  lapsed, the workspace switched it off, or this person simply has no right to
//  the screen — the user was bounced to the dashboard with a red toast. They
//  lost their place, got one line and no next step, and a blocked fetch() was
//  answered with a redirect.
//
//  M12 adds a PRESENTER over the existing engine. It decides nothing: M5–M10
//  remain the only thing that controls access, and these tests assert that.
// ============================================================================

t_section('Milestone 12 — locked module UX');

db();
$asTenant = function ($k = 'testco') { $GLOBALS['__tenant'] = ['key' => $k, 'company' => 'Test Co']; };
$origTenant = $GLOBALS['__tenant'] ?? null;
$origCeil = setting_get('saas_entitled_modules', '');
$origOff  = setting_get('modules_off', '');
$origKey  = (string) setting_get('licence_key', '');
$ceiling = function ($csv, $off = '') use ($asTenant) {
    setting_set('saas_entitled_modules', $csv); setting_set('modules_off', $off);
    $asTenant(); licence_disabled(true);
};
$pdo = db();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_superuser, is_active)
               VALUES ('m12_master','M12','ADMIN',1,1)")->execute();
$masterId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, permissions, is_active)
               VALUES ('m12_insp','M12I','INSPECTOR','',1)")->execute();
$inspId = (int) $pdo->lastInsertId();
$login = function ($uid) use ($asTenant) {
    if ($uid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $uid;
    $asTenant(); current_user(true); ua(true); licence_disabled(true);
};

// ---- A · One state model, and it decides nothing ---------------------------
t_ok(function_exists('access_state_for'), 'A · there is one access-state presenter');
t_ok(defined('ACCESS_STATES') && count(ACCESS_STATES) === 8, 'A · it declares all eight user-facing states');
foreach (['CORE','ENTITLED','NOT_ENTITLED','LICENCE_BLOCKED','TENANT_DISABLED','UNKNOWN','INVALID_MODULE','NO_PERMISSION'] as $st)
    t_ok(in_array($st, ACCESS_STATES, true), "A · '$st' is one of them");
// Pure: no output, no session write, stable.
$ceiling('operations,reporting'); $login($masterId);
$before = $_SESSION;
ob_start(); $a1 = access_state_for('hiring'); $noise = ob_get_clean();
t_eq($noise, '', 'A · the presenter prints nothing');
t_eq($_SESSION, $before, 'A · and writes nothing to the session');
t_eq(access_state_for('hiring'), $a1, 'A · asked twice, it answers the same');

// ---- B · Every state gets its OWN sentence (§4 — no generic "access denied") -
$ceiling('operations,reporting'); $login($masterId);
$notEnt = access_state_for('hiring');
t_eq($notEnt['state'], 'NOT_ENTITLED', 'B · a module the company never bought is NOT_ENTITLED');
t_ok(stripos($notEnt['message'], 'subscription') !== false, 'B · and the words say subscription');
t_ok(stripos($notEnt['message'], 'permission') === false, 'B · and never say permission');

$ceiling('operations,reporting,hr'); $login($inspId);
$noPerm = access_state_for('hiring');
t_eq($noPerm['state'], 'NO_PERMISSION', 'B · entitled, but this person may not — NO_PERMISSION');
t_ok(stripos($noPerm['message'], 'permission') !== false, 'B · and the words say permission');
t_ok(stripos($noPerm['message'], 'subscription') === false, 'B · and never say subscription (§14)');
t_ok($notEnt['message'] !== $noPerm['message'], 'B · the two refusals are not the same sentence');

$ceiling('hr,operations', 'hr'); $login($masterId);
$disabled = access_state_for('hiring');
t_eq($disabled['state'], 'TENANT_DISABLED', 'B · a module the company switched off is TENANT_DISABLED');
t_ok(stripos($disabled['message'], 'switched off') !== false, 'B · and says so');
t_ok(stripos($disabled['hint'], 'lost') !== false || stripos($disabled['hint'], 'untouched') !== false,
     'B · and reassures that the recorded work is still there');

$ceiling('operations,reporting'); $login($masterId);
$unknown = access_state_for('not_a_real_module');
t_eq($unknown['state'], 'INVALID_MODULE', 'B · an unknown module is INVALID_MODULE');
t_ok(stripos($unknown['message'], 'not_a_real_module') === false,
     'B · and the internal key is NEVER shown to the user (§22)');

$ceiling('money,operations'); setting_set('licence_key', 'not-a-real-signed-key-so-verification-fails');
$asTenant(); if (function_exists('lk_state')) lk_state(true); licence_disabled(true); $login($masterId);
if (function_exists('lk_modules') && lk_modules() !== null) {
    $blocked = access_state_for('invoicing');
    t_eq($blocked['state'], 'LICENCE_BLOCKED', 'B · an unverifiable licence is LICENCE_BLOCKED');
    t_ok(stripos($blocked['message'], 'licence') !== false, 'B · and the words say licence, not subscription');
    t_ok($blocked['message'] !== $notEnt['message'], 'B · distinguishable from "not subscribed"');
} else {
    t_ok(false, 'B · could not reach LICENCE_BLOCKED — that state is NOT covered');
}
setting_set('licence_key', $origKey); if (function_exists('lk_state')) lk_state(true);
$asTenant(); licence_disabled(true);

// ---- C · Available modules are described as available, not refused ---------
$ceiling('operations,reporting,hr'); $login($masterId);
foreach (['hiring' => 'ENTITLED', 'idems' => 'ENTITLED', 'masters' => 'CORE'] as $m => $want) {
    $a = access_state_for($m);
    t_eq($a['state'], $want, "C · '$m' reports $want");
    t_ok($a['available'], "C · and is marked available");
    t_ok(empty($a['action']['route']), "C · with no upgrade prompt attached");
}

// ---- D · Administrator vs ordinary user (§7, §11) --------------------------
$ceiling('operations,reporting'); $login($masterId);
$adm = access_state_for('hiring');
t_ok(access_can_subscribe(), 'D · the master can act on a subscription');
t_eq($adm['action']['route'], '/subscription', 'D · so they are offered the EXISTING subscription screen');
$login($inspId);
$ord = access_state_for('hiring');
t_ok(!access_can_subscribe(), 'D · an ordinary user cannot');
t_ok(empty($ord['action']['route']), 'D · so no privileged control is offered to them');
t_ok(stripos($ord['hint'], 'administrator') !== false, 'D · they are told who to ask instead');
// No pricing is invented anywhere (§6, §11).
$src = file_get_contents(__DIR__ . '/../lib/access_state.php') . file_get_contents(__DIR__ . '/../views/ops/module_locked.php');
t_ok(!preg_match('/[₹$€£]\s*\d/', $src), 'D · no price is hard-coded into the locked experience');
t_ok(stripos($src, 'razorpay') === false && stripos($src, 'payment') === false,
     'D · and no payment mechanism was invented — it links to what already exists');

// ---- E · A master is still subject to entitlement (§8) ---------------------
$ceiling('operations,reporting'); $login($masterId);
t_ok(is_master(), 'E · signed in as a master');
$m = access_state_for('hiring');
t_eq($m['state'], 'NOT_ENTITLED', 'E · master + unentitled → still NOT_ENTITLED');
t_ok(!$m['available'], 'E · not available');
t_ok(stripos($m['message'], 'administrator, therefore') === false, 'E · and never says being an admin grants it');
$ceiling('operations,reporting,hr'); $login($masterId);
t_ok(access_state_for('hiring')['available'], 'E · master + entitled → available');

// ---- F · The UI is not the boundary (§1) -----------------------------------
// Whatever the lock screen says, the server still refuses.
$ceiling('operations,reporting'); $login($masterId);
t_ok(!can('mod.hiring.view'),               'F · the permission gate still refuses');
t_ok(!ops_module_gate('candidates', true),  'F · the route gate still refuses a direct URL');
t_ok(!ops_module_gate('recruit-export', true), 'F · and a direct export URL');
t_ok(!licence_module_live('hiring'),        'F · and the entitlement engine still says no');
t_ok(!connect_market_can(),                 'F · Marketplace too');
// The presenter must not be wired into anything that decides.
$gate = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($gate, 'if (function_exists(\'access_deny\')) access_deny($mod);') !== false,
     'F · the gate hands a refusal it has ALREADY decided to the presenter');
t_ok(strpos($gate, 'access_state_for') === false,
     'F · and never asks the presenter whether to allow something');

// ---- G · A blocked fetch() gets JSON, not a redirect (§10) -----------------
t_ok(function_exists('access_wants_json'), 'G · the request type is detected');
$saved = $_SERVER;
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
t_ok(access_wants_json(), 'G · an XMLHttpRequest wants JSON');
unset($_SERVER['HTTP_X_REQUESTED_WITH']);
$_SERVER['HTTP_ACCEPT'] = 'application/json';
t_ok(access_wants_json(), 'G · so does an Accept: application/json fetch');
$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
t_ok(!access_wants_json(), 'G · an ordinary page request does not');
$_SERVER = $saved;
$lib = file_get_contents(__DIR__ . '/../lib/access_state.php');
t_ok(strpos($lib, 'http_response_code(403)') !== false, 'G · a refusal returns 403, not 200');
t_ok(strpos($lib, "'reason'  => \$a['is_permission'] ? 'permission' : 'subscription'") !== false,
     'G · and the JSON says which kind of refusal it is');
// Look for a real CALL, not the word: the header comment describes the old
// flash-and-redirect behaviour this file replaced, and a comment is not code.
$libCode = preg_replace('#^\s*//.*$#m', '', $lib);
t_ok(strpos($libCode, 'redirect(') === false, 'G · the presenter never redirects the user away');

// ---- H · One locked experience, not four (§16) -----------------------------
t_ok(is_file(__DIR__ . '/../views/ops/module_locked.php'), 'H · there is exactly one locked screen');
$view = file_get_contents(__DIR__ . '/../views/ops/module_locked.php');
// Same again — the gate's own comment names the function it hands off to.
$gateCode = preg_replace('#^\s*//.*$#m', '', $gate);
t_eq(substr_count($gateCode, 'access_deny('), 2, 'H · and the gate reaches it from its two refusal points');
// Accessibility and responsiveness, using the existing architecture (§23, §24).
t_ok(strpos($view, '<h1') !== false,           'H · the screen has a real heading');
t_ok(strpos($view, '<a class="btn"') !== false, 'H · the action is a real link — keyboard reachable');
t_ok(strpos($view, 'class="panel"') !== false,  'H · it reuses the existing responsive panel style');
t_ok(strpos($view, 'not available') !== false && strpos($view, 'restricted') !== false,
     'H · the state is carried by WORDS, not by colour alone');
t_ok(strpos($view, '<style') === false && strpos($view, '<script') === false,
     'H · no new stylesheet or framework was introduced');
t_ok(strpos($view, 'Back to your dashboard') !== false, 'H · and there is always a way back');

// ---- I · Lifecycle: ON → OFF → ON, nothing destroyed (§12) -----------------
$ceiling('operations,hr'); $login($masterId);
t_ok(access_state_for('hiring')['available'], 'I · ON — the module is available');
$rows = (int) db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$ceiling('operations'); $login($masterId);
t_ok(!access_state_for('hiring')['available'], 'I · OFF — it is locked');
t_eq((int) db()->query("SELECT COUNT(*) FROM users")->fetchColumn(), $rows, 'I · and nothing was deleted');
$ceiling('operations,hr'); $login($masterId);
t_ok(access_state_for('hiring')['available'], 'I · ON again — restored');
t_eq((int) db()->query("SELECT COUNT(*) FROM users")->fetchColumn(), $rows, 'I · with the data still there');

// ---- J · S-1, and the other modules are undisturbed (§19) ------------------
$ceiling('operations,reporting'); $login($masterId);
foreach (['jobs' => true, 'calls' => true, 'idems' => true, 'masters' => true,
          'hiring' => false, 'quotes' => false, 'invoicing' => false] as $m => $ok) {
    $a = access_state_for($m);
    t_eq($a['available'], $ok, "S-1 · '$m' is " . ($ok ? 'available' : 'locked'));
}
t_ok(ops_module_gate('jobs', true) && ops_module_gate('documents', true),
     'S-1 · Operations and Reporting still open normally');

// ---- K · Cross-tenant, both directions -------------------------------------
$ceiling('hr,operations'); $login($masterId);
t_ok(access_state_for('hiring')['available'], 'K · workspace A has HR');
db()->prepare("UPDATE settings SET svalue=? WHERE skey='saas_entitled_modules'")->execute(['operations']);
db(true); db(); $asTenant('workspace-b'); current_user(true); ua(true);
t_ok(!access_state_for('hiring')['available'], 'K · workspace B does not inherit it');
db()->prepare("UPDATE settings SET svalue=? WHERE skey='saas_entitled_modules'")->execute(['hr,operations']);
db(true); db(); $asTenant('workspace-c'); current_user(true); ua(true);
t_ok(access_state_for('hiring')['available'], 'K · and a workspace that does have it still gets it');

// ---- restore ----
unset($_SESSION['uid']);
setting_set('saas_entitled_modules', (string) $origCeil);
setting_set('modules_off', (string) $origOff);
setting_set('licence_key', $origKey);
if (function_exists('lk_state')) lk_state(true);
if ($origTenant === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $origTenant;
licence_disabled(true); current_user(true); ua(true);
t_ok(true, 'settings and session restored');
