<?php
// ============================================================================
//  SUPER ADMIN — CONTROL PANEL
//
//  One screen that unifies what was scattered across the master-only pages:
//  licence & seats, product modules, subscription/billing, tenant workspaces,
//  the Books link, and system/data tools. Read-mostly: every action links to
//  the existing handler (tenants / licence / billing / seed-demo), so this adds
//  a control surface without duplicating logic. Master Admin only.
// ============================================================================

function superadmin_can() { return function_exists('is_master') && is_master(); }

// Gather everything the panel shows, guarding each source so a missing module
// never breaks the page.
function superadmin_data() {
    $g = fn($fn, ...$a) => function_exists($fn) ? $fn(...$a) : null;
    $val = function ($sql, $a = []) { try { return (int)ops_val($sql, $a); } catch (Throwable $e) { return 0; } };

    $lic  = $g('lk_summary') ?: [];
    $bill = $g('billing_config') ?: [];
    $reg  = $g('tenant_registry') ?: ['tenants' => [], 'base_domain' => ''];
    $tenants = is_array($reg['tenants'] ?? null) ? $reg['tenants'] : [];

    $activeUsers = $val("SELECT COUNT(*) FROM users WHERE is_active=1");
    $allUsers    = $val("SELECT COUNT(*) FROM users");
    $seats  = (int)($lic['seats'] ?? 0);
    $used   = (int)($lic['used'] ?? $activeUsers);

    return [
        'lic'        => $lic,
        'bill'       => $bill,
        'tenants'    => $tenants,
        'base_domain'=> (string)($reg['base_domain'] ?? ''),
        'saas'       => (bool)($g('saas_enabled') ?? false),
        'self'       => (bool)($g('billing_self_managed') ?? true),
        'active_users' => $activeUsers,
        'all_users'  => $allUsers,
        'seats'      => $seats,
        'used'       => $used,
        'books'      => [
            'licensed'  => (bool)($g('books_licensed') ?? false),
            'connected' => (bool)($g('books_connected') ?? false),
            'url'       => (string)($g('books_app_url') ?? ''),
        ],
        'db_driver'  => function_exists('db_driver') ? db_driver() : '—',
        'fy'         => function_exists('current_fy') ? current_fy() : '',
        'demo_loaded'=> $val("SELECT COUNT(*) FROM users WHERE username IN ('director','coord.amd')") > 0,
        'counts'     => [
            'offices'  => $val("SELECT COUNT(*) FROM offices"),
            'partners' => $val("SELECT COUNT(*) FROM business_partners"),
            'calls'    => $val("SELECT COUNT(*) FROM calls"),
            'jobs'     => $val("SELECT COUNT(*) FROM jobs"),
            'reqs'     => $val("SELECT COUNT(*) FROM requisitions"),
            'candidates'=> $val("SELECT COUNT(*) FROM candidates"),
            'quotes'   => $val("SELECT COUNT(*) FROM quotations"),
            'invoices' => $val("SELECT COUNT(*) FROM invoices"),
        ],
    ];
}

function ops_super_admin($method) {
    ops_require(superadmin_can(), 'Only the Master Admin can open the control panel.');
    view('ops/super_admin', ['d' => superadmin_data()]);
    return true;
}
