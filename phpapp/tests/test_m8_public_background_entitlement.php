<?php
// ============================================================================
//  PHASE 1 · MILESTONE 8 — PUBLIC ROUTES & BACKGROUND EXECUTION
//
//  M5–M7 secured requests made by a signed-in person. M8 covers the two kinds
//  of execution where nobody is signed in to ask about:
//
//    · the PUBLIC careers site, including the application POST — securing the
//      page is not securing the submission, so both are proved here
//    · the NIGHTLY RUN, which was doing paid-module work for every workspace
//      regardless of what that workspace had bought
//
//  How cron resolves a workspace matters, and is asserted below:
//      php cron.php                        -> no HTTP host, so the CONTROL
//                                             install, which is never limited
//      https://<workspace>/cron.php?key=…  -> THAT workspace, and entitlement
//                                             then applies to every step
//
//  There is no cron = master. A background process asks the same question a
//  signed-in user does, through the same engine.
// ============================================================================

t_section('Milestone 8 — public routes and background execution');

db();
$asTenant = function ($key = 'testco') { $GLOBALS['__tenant'] = ['key' => $key, 'company' => 'Test Co']; };
$origTenant = $GLOBALS['__tenant'] ?? null;
$origCeil   = setting_get('saas_entitled_modules', '');
$origOff    = setting_get('modules_off', '');
$origCareer = setting_get('careers_enabled', '0');
$ceiling = function ($csv, $off = '') use ($asTenant) {
    setting_set('saas_entitled_modules', $csv);
    setting_set('modules_off', $off);
    $asTenant();
    licence_disabled(true);
};
$pdo = db();
$pdo->prepare("INSERT INTO users (username, first_name, role, is_superuser, is_active)
               VALUES ('m8_master','M8','ADMIN',1,1)")->execute();
$masterId = (int) $pdo->lastInsertId();
$login = function ($uid) use ($asTenant) {
    if ($uid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $uid;
    $asTenant(); current_user(true); ua(true); licence_disabled(true);
};
$candidates = function () { return (int) db()->query("SELECT COUNT(*) FROM candidates")->fetchColumn(); };

// ---- A · The public careers page --------------------------------------------
setting_set('careers_enabled', '1');
$ceiling('hr');
t_ok(careers_enabled(),  'A · HR entitled + Careers switched on  -> the public page serves');
setting_set('careers_enabled', '0'); $asTenant(); licence_disabled(true);
t_ok(!careers_enabled(), 'A · HR entitled + Careers switched off -> the page does not serve');
// ...and switching Careers off must NOT cost the company the rest of Recruitment.
t_ok(licence_module_live('hiring'), 'A · but the Recruitment module itself is untouched by that switch');
t_ok(module_state('hr') === 'ENTITLED', 'A · HR remains ENTITLED — Careers is opt-in, never a dependency');
setting_set('careers_enabled', '1');
$ceiling('operations');
t_ok(!careers_enabled(), 'A · HR NOT entitled + the Careers flag left ON -> still refused');
$ceiling('');
t_ok(!careers_enabled(), 'A · blank entitlement -> refused');
$ceiling('hr', 'hr');
t_ok(!careers_enabled(), 'A · a company that switched HR off itself -> refused');

// ---- B · The application SUBMISSION, not just the page ----------------------
// Securing the GET route is not securing the POST. This creates a real
// requisition and submits a real application against it, both ways round, and
// counts candidate rows to prove no recruitment data is written when refused.
$pdo = db();
$reqOk = true;
try {
    $pdo->prepare("INSERT INTO requisitions (req_code, designation, status, careers_published, created_at)
                   VALUES ('M8-REQ-1','Site Supervisor','OPEN',1,?)")->execute([date('c')]);
    $reqId = (int) $pdo->lastInsertId();
} catch (Throwable $e) { $reqOk = false; $reqId = 0; }
t_ok($reqOk && $reqId > 0, 'B · a published opening exists to apply to');

setting_set('careers_enabled', '1');
$ceiling('operations');                       // HR NOT entitled
$job = ops_one("SELECT * FROM requisitions WHERE id=?", [$reqId]);
$before = $candidates();
[$ok, $msg, $cid] = careers_apply($job, [
    'first_name' => 'Direct', 'last_name' => 'Poster', 'email' => 'direct.poster@example.test',
    'mobile' => '9876500099', 'experience_years' => '4', 'message' => 'Posted straight at the endpoint.',
], []);
t_ok($ok === false,           'B · DENY — a direct application POST is refused without HR');
t_eq((int) $cid, 0,           'B · DENY — and no candidate id is returned');
t_eq($candidates(), $before,  'B · DENY — and NOTHING was written to the recruitment pipeline');
t_ok(strpos(strtolower((string) $msg), 'licen') === false
     && strpos(strtolower((string) $msg), 'entitle') === false
     && strpos(strtolower((string) $msg), 'module') === false,
     'B · DENY — the refusal exposes no licence or module internals to the public');

$ceiling('hr');                               // HR entitled
$asTenant(); licence_disabled(true);
$before = $candidates();
[$ok2, $msg2, $cid2] = careers_apply($job, [
    'first_name' => 'Genuine', 'last_name' => 'Applicant', 'email' => 'genuine.applicant@example.test',
    'mobile' => '9876500098', 'experience_years' => '6', 'message' => 'Keen to join.',
], []);
t_ok($ok2 === true,           'B · ALLOW — an entitled company still accepts applications normally');
t_ok((int) $cid2 > 0,         'B · ALLOW — and a real candidate is created');
t_eq($candidates(), $before + 1, 'B · ALLOW — exactly one candidate, through the normal pipeline');

// Parameter tampering cannot reopen the door.
$ceiling('operations');
$_GET['module'] = 'hr'; $_POST['module'] = 'hr'; $_GET['tenant'] = 'somebody-else';
$_POST['saas_entitled_modules'] = 'hr'; $_REQUEST = array_merge($_GET, $_POST);
$asTenant(); licence_disabled(true);
$before = $candidates();
[$ok3, , $cid3] = careers_apply($job, ['first_name' => 'Forged', 'email' => 'forged@example.test'], []);
t_ok($ok3 === false,          'B · a forged module parameter does not reopen the submission');
t_eq($candidates(), $before,  'B · and still writes nothing');
t_eq(current_tenant(), 'testco', 'B · a forged tenant parameter does not change the tenant');
foreach (['module','tenant'] as $k) unset($_GET[$k]);
foreach (['module','saas_entitled_modules'] as $k) unset($_POST[$k]);
$_REQUEST = [];

// ---- C · The nightly run — every paid step is gated, by the right module ----
// Read from cron.php itself, so removing a guard fails this test.
$cron = file_get_contents(__DIR__ . '/../cron.php');
$PAID = [
    'joblock_sweep' => 'jobs', 'equipment_run_cal_reminders' => 'equipment',
    'auth_run_maintenance' => 'competence', 'crm_run_followups' => 'quotes',
    'crm_expire_quotes' => 'quotes', 'ar_overdue_reminders' => 'invoicing',
    'idems_run_sla_escalations' => 'idems', 'tosrm_run_recurring' => 'calls',
    'billable_events_sync' => 'invoicing', 'ops_run_mis_digest' => 'jobs',
    'ncr_run_reminders' => 'ncr', 'capa_actions_overdue' => 'capa',
    'capa_run_reminders' => 'capa', 'cmp_run_reminders' => 'complaints',
    'idems_vendor_run_reminders' => 'idems', 'sitedoc_expiring' => 'identity',
    'competence_due' => 'competence', 'ads_on' => 'leads',
    'books_bridge_drain' => 'invoicing', 'cdoc_run_reminders' => 'datacontrol',
    'idems_reseal_failed' => 'idems', 'jobs_backfill_cost_basis' => 'jobs',
    'appr_tick' => 'hiring',
];
foreach ($PAID as $fn => $mod) {
    t_ok(strpos($cron, "\$m8('$mod', ") !== false && strpos($cron, "&& function_exists('$fn')") !== false,
        "C · the nightly '$fn' step is gated on '$mod'");
    // and every module named is a real, owned access module
    t_ok(licence_owner($mod) !== null, "C · '$mod' is a real owned access module");
}
t_ok(strpos($cron, "\$m8('hiring', 'People & hiring')") !== false
     && strpos($cron, 'confirm_lapsed_placement_fees();') !== false,
     "C · the HR placement-fee step is gated");
t_ok(strpos($cron, "\$m8sales = \$m8('quotes', 'Sales & CRM');") !== false,
     'C · the Sales contract steps are gated');
t_ok(strpos($cron, "\$m8('jobs', 'Operations') ? ops_run_reminders()") !== false,
     'C · the Operations reminder step is gated');
// CORE maintenance must NOT be gated — one lapsed module must never stop it.
foreach (['audit_trim_old' => 'audit trimming', 'licsync_checkin' => 'licence sync',
          'licence_run_reminders' => 'licence reminders', 'integrity_run' => 'integrity checks',
          'iddoc_encrypt_backfill' => 'identity-document encryption',
          'engagement_backfill' => 'the engagement backfill'] as $fn => $what) {
    t_ok(strpos($cron, "if (function_exists('$fn')") !== false
      || strpos($cron, "function_exists('$fn') &&") !== false,
        "C · core · $what still runs whatever the plan");
}
// And no single early exit that would take the whole run down with one module.
// cron.php DOES name licence_module_live once, inside its own per-step helper —
// what must not exist is a module check that exits the whole run.
t_ok(preg_match('/licence_module_live\([^)]*\)[^;]*\)\s*(\{[^}]*)?exit/', $cron) !== 1,
     'C · no module check exits the whole nightly run');
// One LINE mentions it — the helper's own `!function_exists(x) || x(...)` guard,
// which names it twice. Every step then goes through $m8().
t_eq(count(array_filter(explode("\n", $cron), fn($l) => strpos($l, 'licence_module_live') !== false)), 1,
     'C · entitlement is asked through ONE helper, not re-implemented per step');
t_ok(substr_count($cron, '$m8(') >= 20,
     'C · and that helper is applied per step, so core work survives a lapsed module');

// ---- D · The frequent Sales sync -------------------------------------------
$ads = file_get_contents(__DIR__ . '/../cron_ads.php');
t_ok(strpos($ads, "licence_module_live('leads')") !== false, "D · the advertising lead sync is gated on Sales & CRM");
t_ok(strpos($ads, "licence_module_live('leads')") < strpos($ads, "ads_on()"),
     'D · and asked BEFORE the feature switch — entitlement decides what may be switched on');

// ---- E · Every background module, in every entitlement state ----------------
$states = [
    'operations,reporting'  => ['jobs' => true,  'idems' => true,  'hiring' => false, 'quotes' => false, 'invoicing' => false],
    'hr'                    => ['jobs' => false, 'idems' => false, 'hiring' => true,  'quotes' => false, 'invoicing' => false],
    ''                      => ['jobs' => false, 'idems' => false, 'hiring' => false, 'quotes' => false, 'invoicing' => false],
];
foreach ($states as $csv => $want) {
    $ceiling($csv); $login($masterId);
    $label = $csv === '' ? 'blank entitlement' : "ceiling '$csv'";
    foreach ($want as $mod => $live)
        t_eq(licence_module_live($mod), $live, "E · $label — background '$mod' " . ($live ? 'RUNS' : 'does NOT run'));
}
// A module the company switched off itself, and an unowned one.
$ceiling('hr,operations', 'hr'); $login($masterId);
t_ok(!licence_module_live('hiring'), 'E · tenant-disabled — background HR work does not run');
t_ok(licence_module_live('jobs'),    'E · while the module left on still runs');
t_ok(!licence_module_live('not_a_real_module'), 'E · an unowned module fails closed in the background too');
t_ok(licence_module_live('settings'), 'E · core background work is never withheld');

// ---- F · There is no cron = master -----------------------------------------
$ceiling('operations'); $login($masterId);
t_ok(is_master(),                    'F · the process is running as a master user');
t_ok(!licence_module_live('hiring'), 'F · and master still does not unlock HR background work');
t_ok(!is_master_of('hiring'),        'F · nor master authority over it');
$login(null);                                    // nobody signed in at all, as in cron
t_ok(!licence_module_live('hiring'), 'F · with nobody signed in, the answer is the same');
t_ok(licence_module_live('jobs'),    'F · and the entitled module still runs unattended');

// ---- G · Tenant isolation across a background process ----------------------
// Both directions, in this one process, with no cache reload called on purpose.
$ceiling('hr'); $login($masterId);
t_ok(licence_module_live('hiring'), 'G · workspace A has HR — its HR job may run');
db()->prepare("UPDATE settings SET svalue=? WHERE skey='saas_entitled_modules'")->execute(['operations']);
db(true); db(); $asTenant('workspace-b');
t_ok(!licence_module_live('hiring'), 'G · workspace B does NOT inherit A entitlement');
t_ok(licence_module_live('jobs'),    'G · B gets its OWN entitlement');
// ...and the reverse, so the test cannot pass by simply denying after a switch.
db()->prepare("UPDATE settings SET svalue=? WHERE skey='saas_entitled_modules'")->execute(['hr']);
db(true); db(); $asTenant('workspace-c');
t_ok(licence_module_live('hiring'),  'G · a workspace that DOES have HR still gets it after a switch');
t_ok(!licence_module_live('jobs'),   'G · and does not inherit B Operations entitlement');

// ---- H · How cron resolves a workspace -------------------------------------
// Asserted because the whole model rests on it: on the command line there is no
// HTTP host, so config.php resolves the control install, which is never limited.
$GLOBALS['__tenant'] = ['key' => '', 'company' => ''];
licence_disabled(true);
foreach (['hiring','idems','invoicing','quotes','jobs'] as $m)
    t_ok(licence_module_live($m), "H · control install — background '$m' is never withheld");
$cfg = file_get_contents(__DIR__ . '/../config.php');
t_ok(strpos($cfg, "HTTP_HOST") !== false, 'H · the workspace is resolved from the HTTP host, not from any request parameter');

// ---- I · S-1, through the public and background paths ----------------------
$ceiling('operations,reporting'); $login($masterId);
t_ok(licence_module_live('jobs'),   'S-1 · Operations background work WORKS');
t_ok(licence_module_live('calls'),  'S-1 · Operations recurring calls WORK');
t_ok(licence_module_live('idems'),  'S-1 · Reporting background work WORKS');
t_ok(licence_module_live('settings'), 'S-1 · core background work WORKS');
t_ok(!licence_module_live('hiring'),   'S-1 · HR background work DENIED');
t_ok(!licence_module_live('quotes'),   'S-1 · Sales background work DENIED');
t_ok(!licence_module_live('leads'),    'S-1 · the Sales lead sync DENIED');
t_ok(!licence_module_live('invoicing'),'S-1 · Money background work DENIED');
setting_set('careers_enabled', '1'); $asTenant(); licence_disabled(true);
t_ok(!careers_enabled(),               'S-1 · the public careers page DENIED');
$before = $candidates();
[$sOk] = careers_apply($job, ['first_name' => 'S1', 'email' => 's1@example.test'], []);
t_ok($sOk === false,                   'S-1 · a public application POST DENIED');
t_eq($candidates(), $before,           'S-1 · and writes nothing');

// ---- restore ----
unset($_SESSION['uid']);
setting_set('saas_entitled_modules', (string) $origCeil);
setting_set('modules_off', (string) $origOff);
setting_set('careers_enabled', (string) $origCareer);
if ($origTenant === null) unset($GLOBALS['__tenant']); else $GLOBALS['__tenant'] = $origTenant;
licence_disabled(true); current_user(true); ua(true);
t_ok(true, 'settings and session restored');
