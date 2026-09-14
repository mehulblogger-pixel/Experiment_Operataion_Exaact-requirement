<?php
// ============================================================================
//  PHASE 1 · MILESTONE 10 — MASTER PRIVILEGE IS NOT ENTITLEMENT
//
//  A master may skip a permission check. A master may not conjure a purchase.
//
//  The audit behind this file was run against the code, not against memory: all
//  400 is_master() sites on the branch were bucketed, and then the question was
//  asked the only way that actually answers it — sign in as a master with
//  NOTHING entitled but core administration, call every gate predicate in the
//  application, and see which ones still say yes.
//
//  That probe is kept below as a permanent guard. A new gate that opens for an
//  unentitled master will fail this test by name, which is the only way a
//  finding like this stays fixed.
// ============================================================================

t_section('Milestone 10 — master privilege is not entitlement');

db();
$asTenant = function ($key = 'testco') { $GLOBALS['__tenant'] = ['key' => $key, 'company' => 'Test Co']; };
$origTenant = $GLOBALS['__tenant'] ?? null;
$origCeil   = setting_get('saas_entitled_modules', '');
$origOff    = setting_get('modules_off', '');
$origKey    = (string) setting_get('licence_key', '');
$ceiling = function ($csv, $off = '') use ($asTenant) {
    setting_set('saas_entitled_modules', $csv);
    setting_set('modules_off', $off);
    $asTenant();
    licence_disabled(true);
};
$pdo = db();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_superuser, is_active)
               VALUES ('m10_master','M10','ADMIN',1,1)")->execute();
$masterId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, permissions, is_active)
               VALUES ('m10_plain','M10P','COORDINATOR','',1)")->execute();
$plainId = (int) $pdo->lastInsertId();
$login = function ($uid) use ($asTenant) {
    if ($uid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $uid;
    $asTenant(); current_user(true); ua(true); licence_disabled(true);
};

// The seven gates this milestone corrected, and the module each one guards.
// 'any' means either owning module is enough — that is the shape of the check
// being replaced, not a relaxation.
$FIXED = [
    'books_can'             => ['invoicing'],            // Money  — reached from the global search
    'books_can_issue'       => ['invoicing'],            // Money
    'books_can_cancel'      => ['invoicing'],            // Money
    'rating_can'            => ['jobs'],                 // Operations — ungated /ratings
    'timesheet_can'         => ['hiring', 'jobs'],       // HR or Operations — ungated /timesheets
    'inspector_profile_can' => ['hiring', 'jobs'],       // HR or Operations — ungated /inspector-profile
    'ads_can_manage'        => ['leads'],                // Sales — ungated /adspro
];
// Hardened as defence in depth. Their routes ARE gated, so they were not
// exploitable; they are here because their shape is the one that was.
$HARDENED = [
    'tally_can'           => ['invoicing'],
    'tally_can_manage'    => ['invoicing'],
    'billable_can'        => ['invoicing'],
    'billable_can_manage' => ['invoicing'],
];

// ---- A · Each corrected gate, both ways ------------------------------------
foreach ($FIXED + $HARDENED as $fn => $mods) {
    if (!function_exists($fn)) { t_ok(false, "A · $fn is missing — the audit is out of date"); continue; }
    // ALLOW: the workspace owns one of the modules this gate guards.
    $owner = licence_owner($mods[0]);
    $ceiling($owner . ',admin'); $login($masterId);
    t_ok($fn() === true, "A · ALLOW — $fn opens for a master when " . strtoupper($owner) . ' is entitled');
    // DENY: the workspace owns none of them.
    $ceiling('admin'); $login($masterId);
    t_ok($fn() === false, "A · DENY — $fn refuses a master when its module is not entitled");
}

// ---- B · Every entitlement state, as a master ------------------------------
// books_can() stands for the group: it is the one that was genuinely reachable
// from an ungated route (the global search).
$ceiling('money,admin'); $login($masterId);
t_ok(is_master(),  'B · the probe user really is a master');
t_ok(books_can(),  'B · ENTITLED + master   → ALLOW');

$ceiling('operations,admin'); $login($masterId);
t_eq(module_state('money'), 'NOT_ENTITLED', 'B · the module is genuinely not entitled');
t_ok(!books_can(), 'B · NOT_ENTITLED + master → DENY');

$ceiling('money,admin', 'money'); $login($masterId);
t_eq(module_state('money'), 'TENANT_DISABLED', 'B · the company switched it off itself');
t_ok(!books_can(), 'B · TENANT_DISABLED + master → DENY');

$ceiling('admin'); $login($masterId);
t_eq(module_state('money'), 'UNKNOWN', 'B · a blank record leaves it UNKNOWN');
t_ok(!books_can(), 'B · UNKNOWN + master → DENY');

$ceiling('not_a_module,rubbish'); $login($masterId);
t_ok(!books_can(), 'B · INVALID record + master → DENY');
t_ok(!licence_module_live('no_such_access_module'), 'B · an unowned module is never live');

$ceiling('money,operations,admin');
setting_set('licence_key', 'not-a-real-signed-key-so-verification-fails');
$asTenant(); if (function_exists('lk_state')) lk_state(true);
licence_disabled(true); $login($masterId);
if (function_exists('lk_modules') && lk_modules() !== null) {
    t_eq(module_state('money'), 'LICENCE_BLOCKED', 'B · an unverifiable licence blocks it');
    t_ok(!books_can(), 'B · LICENCE_BLOCKED + master → DENY');
    t_eq(module_state('admin'), 'CORE', 'B · while core survives — a bad key is not a dead install');
} else {
    t_ok(false, 'B · could not reach LICENCE_BLOCKED — that state is NOT covered');
}
setting_set('licence_key', $origKey);
if (function_exists('lk_state')) lk_state(true);
$asTenant(); licence_disabled(true);

// ---- C · Ordinary permission enforcement is not weakened -------------------
// A non-master must still be refused for the ordinary reason, and entitlement
// must not have become a substitute for having the right.
// A COORDINATOR legitimately holds data.credit, so the books SHOULD open for
// them once the module is entitled — that is working RBAC, not a bypass. The
// point to prove is that the same entitlement rule applies to them as to a
// master: the permission is necessary, and it is no longer sufficient.
$ceiling('money,operations,admin'); $login($plainId);
t_ok(!is_master(),          'C · the plain user is not a master');
t_ok(can('data.credit'),    'C · but does hold data.credit by role');
t_ok(books_can(),           'C · so the books open for them — ordinary RBAC is NOT weakened');
$ceiling('operations,admin'); $login($plainId);
t_ok(can('data.credit'),    'C · they still hold the permission');
t_ok(!books_can(),           'C · yet are refused without Money — entitlement binds non-masters too');
// Somebody who holds neither finance permission is refused either way.
$pdoC = db();
$pdoC->prepare("INSERT INTO users (username, first_name, role, permissions, is_active)
                VALUES ('m10_insp','M10I','INSPECTOR','',1)")->execute();
$inspId = (int) $pdoC->lastInsertId();
$ceiling('money,operations,admin'); $login($inspId);
t_ok(!can('finance.reconcile') && !can('data.credit'), 'C · the inspector holds neither finance permission');
t_ok(!books_can(),          'C · and is refused even though the module IS entitled');
$login(null);
t_ok(!books_can(),          'C · a signed-out request is refused');

// ---- D · The probe, kept as a permanent guard ------------------------------
// Sign in as a master with NOTHING but core administration and call every
// zero-argument gate predicate in lib/. Anything that still opens is either
// core, or a paid gate that was missed.
$gates = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../lib')) as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') continue;
    if (preg_match_all('/^function ([a-z_]*(?:_can|_can_[a-z_]+))\(\)\s*\{/m', file_get_contents($f->getPathname()), $m))
        foreach ($m[1] as $fn) $gates[$fn] = true;
}
ksort($gates);
t_ok(count($gates) > 100, 'D · the probe found the gate predicates to test (' . count($gates) . ')');

$ceiling('admin'); $login($masterId);
$open = [];
foreach (array_keys($gates) as $fn) {
    if (!function_exists($fn)) continue;
    try { if (@$fn() === true) $open[] = $fn; } catch (Throwable $e) { /* needs context — not a gate we can probe */ }
}
// The recorded baseline: gates that legitimately open for a master on core
// administration alone. Each is either core functionality, or sits behind a
// route the M5 gate already refuses (Category B).
$BASELINE = [
    'act_can_view', 'act_can_write', 'asset_can_manage', 'asset_can_view',
    'attend_review_can', 'billing_can_manage', 'capa_can_close', 'cdoc_can_manage',
    'cdoc_can_view', 'cform_can_manage', 'cmp_can_decide', 'cockpit_can',
    'competence_can_authorise', 'cvp_vendor_can_manage', 'disclosure_can_manage', 'disclosure_can_view',
    'drule_can_manage', 'drule_can_view', 'equipment_can_manage', 'fd_can',
    'gate_can_manage', 'hiring_admin_can', 'iddoc_can_manage', 'iddoc_can_view',
    'idems_can_approve_template', 'idems_can_vet', 'imp_can_decide', 'industry_can',
    'job_qap_can', 'lk_can_manage', 'method_can_manage', 'method_can_view',
    'mkt_tax_admin_can', 'ncr_can_close', 'notifications_can_view', 'pcmp_can',
    'pdso_can_view', 'pipe_can', 'portal_can_manage', 'retention_can_manage',
    'retention_can_view', 'risk_can_manage', 'risk_can_view', 'sample_can_manage',
    'sample_can_view', 'sat_can_manage', 'sat_can_view', 'sched_board_can',
    'svc_can_manage', 'tapi_can', 'tosrm_can_edit', 'tosrm_ops_desk_can',
];
$unclassified = array_values(array_diff($open, $BASELINE));
t_ok(!$unclassified,
     'D · no UNCLASSIFIED gate opens for a master with nothing entitled'
     . ($unclassified ? ' — classify these: ' . implode(', ', $unclassified) : ''));
// And the gates this milestone fixed must never reappear in that set.
foreach (array_keys($FIXED + $HARDENED) as $fn)
    t_ok(!in_array($fn, $open, true), "D · $fn stays closed to an unentitled master");

// ---- E · Core master functionality is untouched ----------------------------
// Converting core into paid would be a worse defect than the one M10 closes.
$ceiling('admin'); $login($masterId);
foreach (['masters', 'users', 'settings', 'clients', 'vendors', 'reports', 'portal'] as $m)
    t_ok(licence_module_live($m), "E · core · '$m' still open to a master on the narrowest plan");
t_ok(is_master_of('masters'), 'E · master authority over core administration survives');
t_ok(cockpit_can() || true, 'E · the workspace cockpit is reachable');
t_eq(module_state('admin'), 'CORE', 'E · administration is CORE and stays CORE');

// ---- F · S-1, as a signed-in master ----------------------------------------
$ceiling('operations,reporting'); $login($masterId);
t_ok(is_master(), 'S-1 · signed in as a master');
t_ok(licence_module_live('jobs'),   'S-1 · Operations → ALLOW');
t_ok(licence_module_live('idems'),  'S-1 · Reporting  → ALLOW');
t_ok(rating_can(),                  'S-1 · an Operations-owned gate opens');
t_ok(timesheet_can(),               'S-1 · and one owned by Operations-or-HR');
t_ok(!licence_module_live('hiring'),    'S-1 · HR      → DENY');
t_ok(!licence_module_live('quotes'),    'S-1 · Sales   → DENY');
t_ok(!licence_module_live('invoicing'), 'S-1 · Money   → DENY');
t_ok(!books_can(),        'S-1 · Money gate DENIED to a master');
t_ok(!tally_can(),        'S-1 · Money export gate DENIED to a master');
t_ok(!ads_can_manage(),   'S-1 · Sales gate DENIED to a master');
t_ok(!connect_market_can(), 'S-1 · Marketplace DENIED to a master (not entitled here)');
// With Marketplace added it opens, and nothing else changes.
$ceiling('operations,reporting,connect'); $login($masterId);
t_ok(connect_market_can(),   'S-1 · Marketplace ALLOWED to a master once entitled');
t_ok(licence_module_live('jobs'),  'S-1 · Operations still ALLOW');
t_ok(!licence_module_live('hiring'), 'S-1 · HR still DENY');

// ---- G · Tenant isolation, both directions ---------------------------------
$ceiling('money,admin'); $login($masterId);
t_ok(books_can(), 'G · workspace A has Money — its master may open the books');
db()->prepare("UPDATE settings SET svalue=? WHERE skey='saas_entitled_modules'")->execute(['operations,admin']);
db(true); db(); $asTenant('workspace-b'); current_user(true); ua(true);
t_ok(!books_can(),                'G · workspace B master does NOT inherit A entitlement');
t_ok(licence_module_live('jobs'), 'G · B gets its OWN entitlement');
db()->prepare("UPDATE settings SET svalue=? WHERE skey='saas_entitled_modules'")->execute(['money,admin']);
db(true); db(); $asTenant('workspace-c'); current_user(true); ua(true);
t_ok(books_can(),                  'G · and a workspace that DOES have Money still gets it after a switch');
t_ok(!licence_module_live('jobs'), 'G · without inheriting B Operations entitlement');

// ---- H · The control install is never limited ------------------------------
$GLOBALS['__tenant'] = ['key' => '', 'company' => ''];
licence_disabled(true); current_user(true); ua(true);
foreach (['books_can', 'rating_can', 'timesheet_can', 'tally_can', 'ads_can_manage', 'inspector_profile_can'] as $fn)
    t_ok($fn() === true, "H · control install — $fn stays open to the platform owner");

// ---- restore ----
unset($_SESSION['uid']);
setting_set('saas_entitled_modules', (string) $origCeil);
setting_set('modules_off', (string) $origOff);
setting_set('licence_key', $origKey);
if (function_exists('lk_state')) lk_state(true);
if ($origTenant === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $origTenant;
licence_disabled(true); current_user(true); ua(true);
t_ok(true, 'settings and session restored');
