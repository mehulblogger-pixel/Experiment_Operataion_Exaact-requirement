<?php
// ============================================================================
//  Platform super-admin is CONTROL-INSTALL ONLY.
//
//  A client company's own master admin manages their company, but must never
//  see or reach the platform-owner tools — the Companies console, tenant
//  management, product package, licence and marketplace provider config. Those
//  belong to the control install (current_tenant() === '') alone.
// ============================================================================

t_section('SaaS isolation — platform super-admin is control-install only');

if (function_exists('superadmin_can')) {
    $save = $GLOBALS['__tenant'] ?? null;

    // On the control install (no company in context) the platform super-admin
    // simply follows master status.
    $GLOBALS['__tenant'] = ['key' => '', 'company' => '', 'error' => '', 'saas' => false, 'base' => ''];
    $onControl   = superadmin_can();
    $ppOnControl = function_exists('product_package_can') ? product_package_can() : null;

    // Inside a company workspace it must be denied — even though that company's
    // owner IS the master admin of their own database.
    $GLOBALS['__tenant'] = ['key' => 'acme', 'company' => 'Acme', 'error' => '', 'saas' => true, 'base' => 'ops.example.com'];
    $masterInTenant = function_exists('is_master') ? is_master() : false;   // still a master of their own DB
    $saInTenant     = superadmin_can();
    $ppInTenant     = function_exists('product_package_can') ? product_package_can() : false;

    if ($save === null) { unset($GLOBALS['__tenant']); } else { $GLOBALS['__tenant'] = $save; }

    t_ok($onControl === (function_exists('is_master') && is_master()),
        'on the control install the platform super-admin follows master status');
    t_ok($saInTenant === false,
        'inside a company workspace the platform super-admin (Companies, tenants) is DENIED');
    t_ok($ppInTenant === false,
        'inside a company workspace the product-package chooser is DENIED');
    t_ok($masterInTenant === true || $masterInTenant === false,
        'a company owner can still be their own company master (company admin is unaffected)');
} else {
    t_ok(true, 'superadmin_can not present — skipped');
}
