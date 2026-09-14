<?php
// ============================================================================
//  PHASE 1 · MILESTONE 5 — RUNTIME MODULE ENFORCEMENT
//
//  Milestone 3 made the entitlement ANSWER correct. Milestone 5 makes the
//  running application ASK. The safety check found four ways a request could
//  reach a paid module without the question ever being put:
//
//    A · a route nobody added to the gate map was gated by nothing at all
//    B · the public careers page is served before the router reaches the gate
//    C · is_master() short-circuits entitlement — a master saw every module
//    D · an access module owned by no product module was treated as free
//
//  Every test below is written as a pair: the DENY it must now produce, and the
//  ALLOW that proves an entitled company sees no change. A fail-closed engine
//  that also fails closed on paying customers is not a fix.
// ============================================================================

t_section('Milestone 5 — runtime module enforcement');

db();
$asTenant  = function () { $GLOBALS['__tenant'] = ['key' => 'testco', 'company' => 'Test Co']; };
$origTenant = $GLOBALS['__tenant'] ?? null;
$origCeil   = setting_get('saas_entitled_modules', '');
$origOff    = setting_get('modules_off', '');
$origCareer = setting_get('careers_enabled', '0');

// setting_set() rebuilds the connection and config.php re-resolves the tenant,
// so the context is re-asserted after every write, never once at the top.
$ceiling = function ($csv) use ($asTenant) {
    setting_set('saas_entitled_modules', $csv);
    setting_set('modules_off', '');
    $asTenant();
    licence_disabled(true);
};

// ---- A · An unmapped route inside a paid module's family -------------------
// ops_module_gate() gated on an explicit route→module map. 203 dispatched routes
// were not in it. Anything the map did not name was reached ungated — including
// routes that plainly belong to a module the company has not bought.
t_eq(ops_module_family('candidate-scorecard-print'), 'hiring', 'A · an unmapped candidate route resolves to hiring');
t_eq(ops_module_family('requisition-clone'),         'hiring', 'A · an unmapped requisition route resolves to hiring');
t_eq(ops_module_family('invoice-reprint'),        'invoicing', 'A · an unmapped invoice route resolves to invoicing');
t_eq(ops_module_family('lead-merge'),                 'leads', 'A · an unmapped lead route resolves to leads');
t_eq(ops_module_family('quote-duplicate'),           'quotes', 'A · an unmapped quote route resolves to quotes');
// The table is a hand-verified list, NOT a heuristic: a derived recogniser read
// 'issue-licence' as the NCR module and 'approval-rules' as Sales. Anything the
// table does not name stays unclaimed rather than being guessed at.
t_eq(ops_module_family('issue-licence'),   null, 'A · an unrelated route is not guessed into a module');
t_eq(ops_module_family('approval-rules'),  null, 'A · nor is a cross-cutting route');
t_eq(ops_module_family('dashboard'),       null, 'A · nor the dashboard');

$ceiling('operations,admin');
t_ok(!ops_module_gate('candidate-scorecard-print', true), 'A · DENY — an unmapped hiring route with no HR entitlement');
t_ok(!ops_module_gate('invoice-reprint', true),           'A · DENY — an unmapped invoicing route with no Money entitlement');
$ceiling('hr,money');
t_ok(ops_module_gate('candidate-scorecard-print', true),  'A · ALLOW — the same route once HR is entitled');
t_ok(ops_module_gate('invoice-reprint', true),            'A · ALLOW — the same route once Money is entitled');
// Over-blocking is a failure too. A route belonging to nothing must still open.
t_ok(ops_module_gate('trace', true),     'A · a deliberately ungated route is untouched');
t_ok(ops_module_gate('dashboard', true), 'A · the dashboard is untouched');

// ---- B · The public careers page -------------------------------------------
// index.php serves /careers and exits BEFORE ops_dispatch(), so the route gate
// never sees it. A company with no HR entitlement could publish a public hiring
// site and take applications into a module it had not bought.
setting_set('careers_enabled', '1');
$ceiling('operations,admin');
t_ok(!careers_enabled(), 'B · DENY — the careers page is closed with no HR entitlement, even with the setting ON');
$ceiling('hr');
t_ok(careers_enabled(),  'B · ALLOW — an HR-entitled company publishes exactly as before');
// Opt-in is preserved. Entitlement permits the careers page; it never imposes it.
setting_set('careers_enabled', '0'); $asTenant(); licence_disabled(true);
t_ok(!careers_enabled(), 'B · entitlement does not make the careers page mandatory — the setting still decides');
setting_set('careers_enabled', '1');

// ---- C · The master bypass -------------------------------------------------
// is_master() is ua()['master'] — a flag read with no knowledge of licensing.
// Ordering it after can() does NOT close the bypass: can() returns false and
// is_master() then returns true anyway. Only asking the entitlement question
// about the module in play closes it, which is what is_master_of() does.
$pdo = db();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_superuser, is_active)
               VALUES ('m5_master','M5','ADMIN',1,1)")->execute();
$masterId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO users (username, first_name, role, permissions, is_active)
               VALUES ('m5_plain','M5P','COORDINATOR','',1)")->execute();
$plainId = (int) $pdo->lastInsertId();
$login = function ($uid) use ($asTenant) {
    if ($uid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $uid;
    $asTenant(); current_user(true); ua(true); licence_disabled(true);
};

$ceiling('reporting'); $login($masterId);
t_ok(is_master(),                   'C · the test master is a master');
t_ok(is_master_of('idems'),         'C · ALLOW — master keeps authority over an entitled paid module');
t_ok(can('mod.idems.view'),         'C · and the ordinary gate agrees');

$ceiling('operations,admin'); $login($masterId);
t_ok(is_master(),                   'C · still a master');
t_ok(!is_master_of('idems'),        'C · DENY — master grants nothing in a module the company has not bought');
t_ok(!can('mod.idems.view'),        'C · and the ordinary gate denies');
t_ok(!is_master_of('invoicing'),    'C · DENY — the same for Money');
t_ok(!is_master_of(['invoicing','profitability']), 'C · DENY — and for every module named at a site');
// A site naming a core module alongside a paid one keeps master authority through
// the core module. That is correct, not a leak: the screen admits those users too.
t_ok(is_master_of(['idems','clients']), 'C · master keeps authority where a CORE module is also named');

$login($plainId);
t_ok(!is_master_of('idems'),        'C · a non-master is never granted by is_master_of()');
$ceiling('reporting'); $login($plainId);
t_ok(!is_master_of('idems'),        'C · not even when the module is fully entitled');
$login(null);
t_ok(!is_master_of('idems'),        'C · and a signed-out request is never a master');

// ---- D · An access module owned by no product module -----------------------
// licence_blocks() returned false for an unowned access module — "nobody
// licensed this, so it is free". That is the same absence-of-evidence defect
// Milestone 3 closed on the ceiling, one layer further in.
$ceiling('operations,admin'); $login($masterId);
t_ok(licence_owner('not_a_real_module') === null, 'D · the fixture module is genuinely unowned');
t_ok(licence_blocks('mod.not_a_real_module.view'), 'D · DENY — an unowned access module is refused, not waved through');
t_ok(!can('mod.not_a_real_module.view'),           'D · and can() refuses it even for a master');
// 'admin' is a PRODUCT key used as a gate value at three routes (M2 anomaly A1).
// It must resolve to the core product module, or notifications / integrations /
// system-status would have been shut off for every installation.
t_ok(!licence_blocks('mod.admin.view'), 'D · the admin product key still resolves — core stays reachable');
foreach (['masters','users','settings','clients','vendors','reports','portal'] as $m)
    t_ok(!licence_blocks("mod.$m.view"), "D · core access module '$m' is never blocked");
// A real, owned, entitled module is still allowed — D denies the unowned, not the unpaid-for-nothing.
$ceiling('reporting'); $login($masterId);
t_ok(!licence_blocks('mod.idems.view'), 'D · an owned, entitled access module passes');
$ceiling('operations,admin'); $login($masterId);
t_ok(licence_blocks('mod.idems.view'),  'D · an owned, NOT entitled access module is blocked');

// ---- S-1 · The mandatory acceptance scenario -------------------------------
// Operations ON, Reporting ON, HR / Sales / Money OFF. Signed in as a MASTER —
// the hardest case, because before M5 a master saw everything.
$ceiling('operations,reporting'); $login($masterId);
t_eq(module_state('operations'), 'ENTITLED', 'S-1 · Operations is entitled');
t_eq(module_state('reporting'),  'ENTITLED', 'S-1 · Reporting is entitled');
foreach (['hr','sales','money'] as $m)
    t_eq(module_state($m), 'NOT_ENTITLED', "S-1 · $m is not entitled");

t_ok(can('mod.jobs.view'),   'S-1 · PASS — Operations opens (jobs)');
t_ok(can('mod.calls.view'),  'S-1 · PASS — Operations opens (calls)');
t_ok(ops_module_gate('jobs', true),  'S-1 · PASS — the Operations route gate allows');
t_ok(can('mod.idems.view'),  'S-1 · PASS — Reporting opens');
t_ok(ops_module_gate('documents', true), 'S-1 · PASS — the Reporting route gate allows');
t_ok(is_master_of('idems'),  'S-1 · PASS — master authority survives inside an entitled module');

t_ok(!can('mod.hiring.view'),       'S-1 · DENIED — HR, to a master');
t_ok(!can('mod.invoicing.view'),    'S-1 · DENIED — Money, to a master');
t_ok(!can('mod.profitability.view'),'S-1 · DENIED — Money (profitability), to a master');
t_ok(!can('mod.leads.view'),        'S-1 · DENIED — Sales, to a master');
t_ok(!can('mod.quotes.view'),       'S-1 · DENIED — Sales (quotes), to a master');
foreach (['candidates','requisitions','recruitment','careers-admin'] as $r)
    t_ok(!ops_module_gate($r, true), "S-1 · DENIED — the HR route '$r' is refused at the gate");
foreach (['invoices','receipts','to-bill','profitability'] as $r)
    t_ok(!ops_module_gate($r, true), "S-1 · DENIED — the Money route '$r' is refused at the gate");
t_ok(!careers_enabled(), 'S-1 · DENIED — the public careers page does not serve');
t_ok(!is_master_of('hiring'), 'S-1 · DENIED — master grants nothing in HR');

// ---- The control install is never enforced against -------------------------
// The one outcome worse than the defect: the platform owner locked out of their
// own console, or a self-hosted single business losing the product it paid for.
$GLOBALS['__tenant'] = ['key' => '', 'company' => ''];
licence_disabled(true); current_user(true); ua(true);
foreach (array_keys(PRODUCT_MODULES) as $k)
    t_ok(licence_enabled($k), "control install: $k stays switched on ($k)");
t_ok(ops_module_gate('candidate-scorecard-print', true), 'control install: an unmapped hiring route still opens');

// ---- restore ----
unset($_SESSION['uid']);
setting_set('saas_entitled_modules', (string) $origCeil);
setting_set('modules_off', (string) $origOff);
setting_set('careers_enabled', (string) $origCareer);
if ($origTenant === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $origTenant;
licence_disabled(true); current_user(true); ua(true);
t_ok(true, 'settings and session restored');
