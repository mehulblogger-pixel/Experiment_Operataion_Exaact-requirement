<?php
// ============================================================================
//  PHASE 1 · MILESTONE 7 — REPORT / EXPORT ENTITLEMENT ENFORCEMENT
//
//  An export is a door with a different handle. M5 gated the routes and M6 the
//  action paths; M7 asks the remaining question — can a company DOWNLOAD data
//  belonging to a module it has not bought?
//
//  Three ways it still could, all found by reading the code rather than assuming
//  that "report" means the Reporting product:
//
//    F1  project costing — a Sales/HR sheet whose guards fell back to
//        is_admin_level() || is_master(), on routes absent from the gate map, so
//        neither the screen nor its printable sheet ever met entitlement
//    F2  the MIS export — cost / profit / margin columns (Money) on a report
//        mapped to the CORE reports module
//    F3  analytics — ten of twenty metrics read Inspection reporting, exported
//        as CSV or XLSX from routes also mapped to CORE
//
//  Both sides are proved throughout. An implementation that denied everything
//  would pass a DENY-only suite and would be useless.
// ============================================================================

t_section('Milestone 7 — report / export entitlement');

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
$pdo = db();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_superuser, is_active)
               VALUES ('m7_master','M7','ADMIN',1,1)")->execute();
$masterId = (int) $pdo->lastInsertId();
$login = function ($uid) use ($asTenant) {
    if ($uid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $uid;
    $asTenant(); current_user(true); ua(true); licence_disabled(true);
};

// ---- A · Every export ROUTE resolves to its owning module -------------------
// Direct URL and direct export URL alike: the gate is asked without the user
// ever having opened the report screen first.
$exportRoutes = [
    'quotes-export'            => 'sales',       // CSV
    'quote-pdf'                => 'sales',       // PDF
    'crm-template-download'    => 'sales',       // download
    'recruit-export'           => 'hr',          // CSV
    'tally-export'             => 'money',       // export
    'invoice-print'            => 'money',       // print / PDF
    'document-pdf'             => 'reporting',   // PDF
    'report-preview'           => 'reporting',
    'report-template-download' => 'reporting',   // download
    'report-builder'           => 'reporting',
    'report-types'             => 'reporting',
    'voucher-csv'              => 'operations',  // CSV
    'voucher-print'            => 'operations',  // print
    'job-forward-report'       => 'operations',
];
$ceiling('operations,reporting');                 // S-1 plan
$login($masterId);
foreach ($exportRoutes as $route => $product) {
    $want = in_array($product, ['operations', 'reporting'], true);
    t_eq(ops_module_gate($route, true), $want,
        "A · direct export URL '$route' (" . strtoupper($product) . ") is " . ($want ? 'ALLOWED' : 'DENIED') . ' under S-1');
}
// And the mirror: with everything bought, every one of them opens.
$ceiling('operations,reporting,sales,money,hr'); $login($masterId);
foreach (array_keys($exportRoutes) as $route)
    t_ok(ops_module_gate($route, true), "A · ALLOW — '$route' opens for a fully subscribed company");

// ---- B · Project costing (F1) ----------------------------------------------
// The screen and the printable sheet share one gate — ops_projcosting() requires
// pc_can() before it dispatches anything, /project-costing-print included — so a
// report page that is refused can never have an export URL that is not.
$ceiling('sales'); $login($masterId);
t_ok(pc_modules_live(), 'B · ALLOW — Sales & CRM alone makes a costing sheet meaningful');
t_ok(pc_can(),          'B · ALLOW — and a master may open it');
t_ok(pc_can_edit(),     'B · ALLOW — and edit it');
$ceiling('hr'); $login($masterId);
t_ok(pc_can(),          'B · ALLOW — People & hiring alone is equally sufficient');
$ceiling('operations,reporting'); $login($masterId);
t_ok(!pc_modules_live(), 'B · DENY — neither Sales nor Hiring is subscribed');
t_ok(!pc_can(),          'B · DENY — the costing SCREEN is refused, even to a master');
t_ok(!pc_can_edit(),     'B · DENY — and editing');
t_ok(!pc_can_approve(),  'B · DENY — and approval');
// The printable export cannot be reached another way: one guard, both doors.
$src = file_get_contents(__DIR__ . '/../lib/projcosting.php');
t_ok(strpos($src, "ops_require(pc_can(), 'You do not have access to project costing.')") !== false,
     'B · the print route is behind the same guard as the screen (no export back door)');
t_ok(strpos($src, "\$route === 'project-costing-print'") !== false, 'B · and that print route still exists');

// ---- C · The MIS export (F2) ------------------------------------------------
// One value, $S['seeSalary'], feeds BOTH the screen and the CSV, so the report
// and its export can never disagree about which columns exist.
$ceiling('operations,money'); $login($masterId);
$F = mis_filters(); $S = mis_summary($F);
t_ok($S['seeSalary'], 'C · ALLOW — cost / profit / margin columns for a Money-entitled company');
$ceiling('operations,reporting'); $login($masterId);
$F2 = mis_filters(); $S2 = mis_summary($F2);
t_ok(!$S2['seeSalary'], 'C · DENY — profitability columns withheld without Money, even from a master');
// DATA SCOPE MUST NOT CHANGE. Withholding columns must not turn the report into
// "everything this tenant has", nor drop the rows a scoped user may legitimately see.
t_eq(array_keys($S2), array_keys($S), 'C · the report keeps exactly the same shape — no widening, no collapse');
foreach (['jobs', 'tot'] as $k)
    t_ok(array_key_exists($k, $S2), "C · the Operations MIS content ('$k') is untouched");

// ---- D · Analytics metrics (F3) --------------------------------------------
// Ownership is read from the lineage each metric already declares, not invented.
t_eq(tapi_metric_module('reports.total'),   'idems', 'D · a report metric is owned by Inspection reporting');
t_eq(tapi_metric_module('reports.issued'),  'idems', 'D · and so is the issued count');
t_eq(tapi_metric_module('jobs.total'),      'jobs',  'D · a jobs metric is owned by Operations');
t_eq(tapi_metric_module('calls.total'),     'calls', 'D · and a calls metric');
t_eq(tapi_metric_module('ncr.total'),       'ncr',   'D · and a nonconformity metric');
t_eq(tapi_metric_module('portal.active_users'), 'portal', 'D · a portal metric is core administration');
// revenue.invoiced is labelled FINANCE but reads jobs.invoice_amount — its
// lineage says Operations, and the lineage is what decides.
t_eq(tapi_metric_module('revenue.invoiced'), 'jobs', 'D · the FINANCE label does not override the lineage');
t_ok(tapi_metric_module('no_such_metric') === null, 'D · an unknown metric has no owner');

$ctx = ['from' => '', 'to' => ''];
$ceiling('operations,reporting'); $login($masterId);
t_ok(tapi_metric_live('reports.total'), 'D · ALLOW — a Reporting metric is live when Reporting is subscribed');
t_ok(tapi_metric_value('reports.total', $ctx) !== null, 'D · ALLOW — and it resolves to a real value');
t_ok(tapi_metric_live('jobs.total'),    'D · ALLOW — Operations metrics are live');
t_ok(tapi_metric_value('jobs.total', $ctx) !== null, 'D · ALLOW — and resolve');

$ceiling('operations'); $login($masterId);
t_ok(!tapi_metric_live('reports.total'), 'D · DENY — a Reporting metric is not live without Reporting');
foreach (['reports.total','reports.issued','report.tat_avg_days','reports.under_review',
          'release.conditional','release.not_released','vendor.reassess_due'] as $k)
    t_ok(tapi_metric_value($k, $ctx) === null, "D · DENY — '$k' exports NO DATA, never a number");
t_ok(tapi_metric_value('jobs.total', $ctx) !== null,  'D · while entitled Operations metrics still resolve');
t_ok(tapi_metric_value('calls.total', $ctx) !== null, 'D · and still carry real values');
// Unknown lineage fails closed — it withholds a number rather than publishing one.
t_ok(tapi_metric_value('no_such_metric', $ctx) === null, 'D · unknown ownership fails closed');
// Core metrics survive the narrowest plan.
t_ok(tapi_metric_live('portal.active_users'), 'D · core · a portal metric stays live');

// ---- E · The other entitlement states --------------------------------------
$ceiling('');                                            // blank record
$login($masterId);
t_ok(!tapi_metric_live('reports.total'), 'E · blank entitlement — DENY');
t_ok(!pc_modules_live(),                 'E · blank entitlement — costing DENY');
t_ok(!ops_module_gate('recruit-export', true), 'E · blank entitlement — the HR export URL is refused');
t_ok(tapi_metric_live('portal.active_users'), 'E · blank entitlement — core is still reachable');

$ceiling('reporting,sales', 'reporting');                // the company switched it off itself
$login($masterId);
t_ok(!tapi_metric_live('reports.total'), 'E · a module switched off by the company — DENY');
t_ok(!ops_module_gate('document-pdf', true), 'E · and its PDF export URL is refused');
t_ok(pc_modules_live(),                  'E · while Sales, left on, still permits a costing');

// ---- F · Client-supplied parameters cannot authorise an export -------------
$ceiling('operations'); $login($masterId);
$_GET['module'] = 'reporting'; $_GET['report'] = 'reports.total'; $_GET['fmt'] = 'xlsx';
$_GET['tenant'] = 'another-company'; $_POST['module'] = 'money'; $_POST['product'] = 'hr';
$_REQUEST = array_merge($_GET, $_POST);
licence_disabled(true);
t_ok(!tapi_metric_live('reports.total'),       'F · a forged module parameter does not unlock a metric');
t_eq(tapi_metric_value('reports.total', $ctx), null, 'F · nor its exported value');
t_ok(!ops_module_gate('recruit-export', true), 'F · nor an export route');
t_ok(!ops_module_gate('tally-export', true),   'F · for any module named in the request');
t_eq(current_tenant(), 'testco',               'F · a forged tenant parameter does not change the tenant');
t_ok(!pc_modules_live(),                       'F · and costing stays closed');
foreach (['module','report','fmt','tenant'] as $k) unset($_GET[$k]);
foreach (['module','product'] as $k) unset($_POST[$k]);
$_REQUEST = [];

// ---- G · Cross-tenant -------------------------------------------------------
// Company A has Reporting; company B does not. B must not export A's reports,
// and no cached answer may carry across the switch.
$ceiling('reporting'); $login($masterId);
t_ok(tapi_metric_live('reports.total'), 'G · company A may export its report metrics');
$pdo = db();
$pdo->prepare("UPDATE settings SET svalue=? WHERE skey='saas_entitled_modules'")->execute(['operations']);
db(true); db();                            // the switch; config.php re-resolves the tenant
$asTenant();                               // deliberately NO licence_disabled(true)
t_ok(!tapi_metric_live('reports.total'),   'G · company B does not inherit A entitlement for the export');
t_eq(tapi_metric_value('reports.total', $ctx), null, 'G · and the exported value is withheld');
t_ok(!ops_module_gate('document-pdf', true), 'G · and A report PDF route is refused under B');
t_ok(tapi_metric_live('jobs.total'),       'G · while B own Operations entitlement applies');

// ---- H · S-1, through the export paths -------------------------------------
// Operations ON, Reporting ON, HR / Sales / Money OFF — as a MASTER.
$ceiling('operations,reporting'); $login($masterId);
t_ok(ops_module_gate('voucher-csv', true),   'S-1 · Operations export WORKS (CSV)');
t_ok(ops_module_gate('voucher-print', true), 'S-1 · Operations export WORKS (print)');
t_ok(ops_module_gate('document-pdf', true),  'S-1 · Reporting export WORKS (PDF)');
t_ok(ops_module_gate('report-template-download', true), 'S-1 · Reporting export WORKS (download)');
t_ok(tapi_metric_value('reports.total', $ctx) !== null, 'S-1 · Reporting analytics WORKS');
t_ok(!ops_module_gate('recruit-export', true), 'S-1 · HR export DENIED, to a master');
t_ok(!ops_module_gate('quotes-export', true),  'S-1 · Sales export DENIED, to a master');
t_ok(!ops_module_gate('quote-pdf', true),      'S-1 · Sales PDF DENIED');
t_ok(!ops_module_gate('tally-export', true),   'S-1 · Money export DENIED');
t_ok(!ops_module_gate('invoice-print', true),  'S-1 · Money print DENIED');
t_ok(!mis_summary(mis_filters())['seeSalary'], 'S-1 · Money profitability columns DENIED in the MIS export');
t_ok(!pc_can(),                                'S-1 · the Sales/HR costing sheet DENIED');
// Reporting must NOT be collateral damage of HR/Sales/Money enforcement.
t_ok(licence_module_live('idems'),  'S-1 · Reporting remains fully operational');
t_ok(ops_module_gate('reports', true),     'S-1 · the core reports hub still opens');
t_ok(ops_module_gate('analytics', true),   'S-1 · the analytics dashboards still open');
t_ok(ops_module_gate('mis', true),         'S-1 · and the MIS report');

// ---- I · Core exports are preserved ----------------------------------------
// Denying these would be a worse defect than the one M7 closes.
$ceiling(''); $login($masterId);
foreach (['reports' => 'the reports hub', 'analytics-export' => 'the analytics export',
          'mis' => 'the MIS report', 'backup' => 'the workspace backup download',
          'incident-report' => 'the CERT-In incident report'] as $r => $what)
    t_ok(ops_module_gate($r, true), "I · core · $what stays reachable on the narrowest plan");
t_ok(ops_module_gate('verify-pdf', true), 'I · public · the client report-verification download is untouched');

// ---- restore ----
unset($_SESSION['uid']);
setting_set('saas_entitled_modules', (string) $origCeil);
setting_set('modules_off', (string) $origOff);
if ($origTenant === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $origTenant;
licence_disabled(true); current_user(true); ua(true);
t_ok(true, 'settings and session restored');
