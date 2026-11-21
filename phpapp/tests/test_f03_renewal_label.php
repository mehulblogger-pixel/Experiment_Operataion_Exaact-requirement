<?php
// F-03 (test book, 21 Nov 2026). Since DEF-10, Renewals, Today and Sales home
// COUNT contracts inside the window set in Sales settings (sx_renewal_days()),
// but Today and Sales home still WROTE the older contract-warning days
// ("in 30 days"). The words now follow the same window as the count.
t_section('F-03 — Today and Sales home name the same renewal window they count');

require_once __DIR__ . '/_bbv_fixture.php';
$f = bbv_fixture();
$had = setting_get('sales_renewal_days', null);
$hadWarn = setting_get('contract_warn_days', null);
try {
    setting_set('sales_renewal_days', '45');
    setting_set('contract_warn_days', '30');
    t_eq(sx_renewal_days(), 45, 'Sales settings window set to 45 days (test value)');

    $today = (string) (bbv_route('sales-today', 'GET', $f['bd'], 0)['out'] ?? '');
    t_ok($today !== '', 'Today opens for the Business Director');
    t_ok(strpos($today, 'in 45 days') !== false || strpos($today, 'None in the next 45 days') !== false, 'Today · renewal tile / empty line say 45 days');
    t_ok(strpos($today, 'in 30 days') === false && strpos($today, 'next 30 days') === false, 'Today · no "30 days" left from the old setting');

    $home = (string) (bbv_route('sales-home', 'GET', $f['bd'], 0)['out'] ?? '');
    t_ok($home !== '', 'Sales home opens for the Business Director');
    t_ok(strpos($home, 'in 45 days') !== false, 'Sales home · "Renewals due" says in 45 days');
    t_ok(strpos($home, 'in 30 days') === false, 'Sales home · no "in 30 days" from the old setting');
} finally {
    setting_set('sales_renewal_days', $had === null ? '' : (string) $had);
    setting_set('contract_warn_days', $hadWarn === null ? '30' : (string) $hadWarn);
    bbv_cleanup($f);
}
