<?php
// Owner, 25 Oct 2026 (test book ONB-041): a company that bought Sales alone saw
// every module in "Go to…" (🧭) and every role — Coordinator, Branch Manager,
// Inspector — in the role list. The palette now asks the left menu's question,
// and the operations roles belong to Operations.
t_section('A Sales-only company: the 🧭 palette and the role list show only what it bought');

$prevOff = (string) setting_get('modules_off', ''); $prevUid = $_SESSION['uid'] ?? null;
try {
    $off = []; foreach (PRODUCT_MODULES as $k => $m) if (empty($m[3]) && $k !== 'sales') $off[] = $k;
    setting_set('modules_off', implode(',', $off)); licence_disabled(true);
    $_SESSION['uid'] = (int) ops_val("SELECT id FROM users WHERE username='admin'"); current_user(true); ua(true);

    $areas = array_unique(array_column(ops_nav_index(true), 'area'));
    foreach (['Quality & Accreditation', 'Reporting', 'Money', 'Operations', 'Recruitment', 'Marketplace'] as $a)
        t_ok(!in_array($a, $areas, true), "\"$a\" is not offered in Go to… (not bought)");
    t_ok(in_array('Sales', $areas, true), 'Sales is offered');
    $urls = array_column(ops_nav_index(true), 'url');
    t_ok(!in_array('/asset-register', $urls, true), 'Asset issuance (gear for field engineers) is not offered');
    t_ok(!in_array('/analytics', $urls, true), 'Analytics & performance (operations and money KPIs) is not offered');
    foreach (ops_nav_index(true) as $e) {
        $area = strtolower(explode(' ', $e['area'])[0]);
        if (in_array($area, ['sales', 'insights', 'directory', 'admin'], true) && $e['url'] === '/' . $area)
            t_ok(ops_area_has($area), "every area Go to… offers is also on the left menu ($area)");
    }

    $roles = roles_for_licence();
    foreach (['COORDINATOR', 'INSPECTOR', 'SR_INSPECTOR', 'ASST_MANAGER', 'OPERATION_MANAGER', 'QUALITY_HEAD', 'TECHNICAL_HEAD', 'FINANCE'] as $r)
        t_ok(!isset($roles[$r]), "$r is not offered to a Sales-only company");
    foreach (['MARKETING_EXECUTIVE', 'MARKETING_MANAGER', 'BUSINESS_DEV_MANAGER', 'KEY_ACCOUNTS_MANAGER', 'BRANCH_MANAGER', 'BUSINESS_DIRECTOR', 'SBU_HEAD'] as $r)
        t_ok(isset($roles[$r]), "$r is offered");
    t_ok(isset(roles_for_licence(null, 'COORDINATOR')['COORDINATOR']), 'a person who is already a Coordinator keeps that role on their record');
    t_eq(role_default_for_licence($roles), 'MARKETING_EXECUTIVE', 'a new person starts as Marketing Executive, not Coordinator');

    // People & hiring alone: the Coordinator and Operation Manager run recruitment too.
    $off2 = array_values(array_diff($off, ['hr'])); $off2[] = 'sales';
    setting_set('modules_off', implode(',', $off2)); licence_disabled(true);
    $r2 = roles_for_licence();
    t_ok(isset($r2['COORDINATOR']) && isset($r2['OPERATION_MANAGER']) && !isset($r2['INSPECTOR']), 'with only People & hiring: Coordinator and Operation Manager are offered, Inspector is not');
} finally {
    setting_set('modules_off', $prevOff); licence_disabled(true);
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    current_user(true); ua(true);
}
