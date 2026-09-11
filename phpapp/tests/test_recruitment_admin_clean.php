<?php
// ============================================================================
//  A Recruitment-only company sees a Recruitment-only Admin panel.
//
//  A recruitment agency's plan buys Administration + People & hiring only
//  (Operations, Reporting, Sales and Money are switched off). The Admin area
//  must then show only what manpower hiring & recruitment needs — masters,
//  people/access, and the company's own configuration (settings, terminology,
//  form designer, company profile). The inspection/sales set-once screens —
//  Service scope, Report formats by service and the Ads Pro connection — used
//  to leak in because they were gated by a bare is_master() that walks straight
//  past the licence. This proves they are gone, and that the recruitment
//  essentials remain, and that a typed URL to the inspection screens is also
//  refused (route-level defence, not just a hidden tile).
// ============================================================================

t_section('Recruitment-only company → Recruitment-only Admin panel');

if (!function_exists('ops_area_def') || !function_exists('licence_enabled')) {
    t_ok(true, 'areas / licence not present — skipped');
    return;
}

$pdo = db();

// Become the company master admin, exactly as a real owner is.
$pdo->prepare("INSERT INTO users (username,first_name,role,is_active,is_superuser) VALUES ('rec_master','R','MASTER_ADMIN',1,1)")->execute();
$_SESSION['uid'] = (int) $pdo->lastInsertId();
current_user(true); if (function_exists('ua')) ua(true);
if (!is_master()) { t_ok(true, 'could not become master in this run — skipping'); return; }

// Simulate the Recruitment plan's module set: admin + hr on, the rest off.
$savedOff = (string) setting_get('modules_off', '');
setting_set('modules_off', 'operations,reporting,sales,money');
licence_disabled(true);

// If a signed licence is pinning the modules (lk_modules), the setting is
// ignored — then this environment can't exercise the gate, so skip cleanly.
if (licence_enabled('operations') || licence_enabled('reporting') || licence_enabled('sales')) {
    setting_set('modules_off', $savedOff); licence_disabled(true);
    t_ok(true, 'a signed licence pins the modules here — skipping the recruitment-plan gate check');
    return;
}

// Collect every tile label the Admin panel would render for this company.
$def = ops_area_def('admin');
$labels = [];
foreach (($def['sections'] ?? []) as $s) foreach ($s['tiles'] as $tile) $labels[] = $tile['label'];

// The inspection / sales set-once screens must be GONE.
t_ok(!in_array('Service scope', $labels, true),
    'Service scope (an inspection concept) is removed from a recruitment company\'s Admin');
t_ok(!in_array('Report formats by service', $labels, true),
    'Report formats by service (a reporting concept) is removed from a recruitment company\'s Admin');
t_ok(!in_array('Ads Pro connection', $labels, true),
    'the Ads Pro connection (a sales concept) is removed from a recruitment company\'s Admin');

// The recruitment essentials must REMAIN — the panel is trimmed, not gutted.
t_ok(in_array('Masters', $labels, true),
    'Masters (the lists behind recruitment dropdowns) stays');
t_ok(in_array('System settings', $labels, true),
    'System settings stays');
t_ok(in_array('Terminology / wording', $labels, true),
    'Terminology stays (a recruiter renames Requirement / Candidate to suit their process)');
t_ok(in_array('Form Designer', $labels, true),
    'Form Designer stays (edit the Requirement & Candidate forms)');
t_ok(in_array((string) T_REG('user'), $labels, true) || in_array('Users', $labels, true),
    'the people/users screen stays');

// Route-level defence: the inspection service screens refuse a typed URL when
// their module is off, not only hide the tile. (String-level assertion so the
// test never triggers ops_require's redirect.)
$opsSrc = (string) file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($opsSrc, "'service-scope' => 'operations', 'service-formats' => 'reporting'") !== false,
    'ops_module_gate refuses /service-scope and /service-formats when their module is off');

// Now flip Operations back on (a Staffing / TPIA / Enterprise install) and prove
// Service scope returns — the gate is by module, it does not hard-delete anything.
setting_set('modules_off', 'sales');   // Staffing: only sales off
licence_disabled(true);
$defFull = ops_area_def('admin');
$labelsFull = [];
foreach (($defFull['sections'] ?? []) as $s) foreach ($s['tiles'] as $tile) $labelsFull[] = $tile['label'];
t_ok(in_array('Service scope', $labelsFull, true),
    'Service scope returns for an Operations company (the gate is by module, nothing is hard-removed)');

// Restore the environment for the rest of the suite.
setting_set('modules_off', $savedOff);
licence_disabled(true);
unset($_SESSION['uid']);
current_user(true); if (function_exists('ua')) ua(true);
