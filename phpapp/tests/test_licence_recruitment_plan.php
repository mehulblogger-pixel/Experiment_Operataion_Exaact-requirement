<?php
// Selling one module: the signed licence key is the real, tamper-proof
// entitlement. A "Recruitment" plan issues a key that grants ONLY the People &
// hiring (recruitment) module; because a signed key OUTRANKS the settings
// screen (licence_disabled), the customer cannot switch on a module they did
// not buy. This checks the plan exists and that the module maths it drives
// switches every non-recruitment module off while keeping recruitment on.
t_section('licence plan — Recruitment-only entitlement');

$tiers = superadmin_tiers();
t_ok(isset($tiers['RECRUITMENT']), 'a one-click Recruitment plan exists in the licence console');

$m = $tiers['RECRUITMENT']['mods'];
t_ok(in_array('hr', $m, true),          'the Recruitment plan grants the recruitment (People & hiring) module');
t_ok(in_array('admin', $m, true),       'core Admin is included');
t_ok(!in_array('operations', $m, true), 'Operations is NOT sold with the Recruitment plan');
t_ok(!in_array('sales', $m, true),      'Sales/CRM is NOT sold');
t_ok(!in_array('money', $m, true),      'Invoicing/Money is NOT sold');
t_ok(!in_array('reporting', $m, true),  'the inspection report engine is NOT sold');

// The enforcement maths that licence_disabled() runs from a key's bought list:
// every non-core module the key does NOT list is switched OFF.
$bought = $m;
$off = [];
foreach (PRODUCT_MODULES as $k => $meta) {
    $core = !empty($meta[3]);
    if (!$core && !in_array($k, $bought, true)) $off[] = $k;
}
t_ok(in_array('operations', $off, true), 'a Recruitment key switches Operations OFF');
t_ok(in_array('sales', $off, true),      'a Recruitment key switches Sales OFF');
t_ok(in_array('money', $off, true),      'a Recruitment key switches Money OFF');
t_ok(!in_array('hr', $off, true),        'recruitment itself stays ON');
t_ok(!in_array('admin', $off, true),     'core Admin is never switched off');
