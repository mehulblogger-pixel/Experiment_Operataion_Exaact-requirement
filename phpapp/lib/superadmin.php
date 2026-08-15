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

// Recommended plan packaging: which PRODUCT_MODULES each tier grants when a
// licence key is issued. The licence engine already reads `mods` from the key —
// these presets are the map you pick from at issue time. Prices stay in Settings.
function superadmin_tiers() {
    return [
        'STARTER' => ['label' => 'Starter', 'mods' => ['admin', 'operations'],
            'pitch' => 'Core operations for a single office / small TPIA.'],
        'PRO'     => ['label' => 'Professional', 'mods' => ['admin', 'operations', 'sales', 'hr', 'money'],
            'pitch' => 'Adds Recruitment Command Centre, Sales/CRM, invoicing & portals.'],
        'ENTERPRISE' => ['label' => 'Enterprise', 'mods' => ['admin', 'operations', 'sales', 'hr', 'money', 'reporting'],
            'pitch' => 'Everything, incl. the IDEMS report engine and analytics.'],
    ];
}

// ---------------------------------------------------------------------------
//  Role-based seat classes
//
//  A flat per-seat price is unfair when a field inspector — who only ever opens
//  the mobile job screens — costs the same as a branch manager who lives in the
//  whole suite. So a seat has a CLASS, and the class carries the price:
//
//    • FULL   — office staff who use the desktop suite (managers, ED, SBU head,
//               branch/ops/asst managers, coordinators, sales, admin, finance).
//               Priced at the plan's per-seat rate (billing_price_user_month).
//    • FIELD  — inspectors on the field/mobile apps only. A light, flat rate.
//    • PORTAL — external client / vendor logins (client_users). A free bundle,
//               then a token per-head price above it.
//
//  Nothing here is hard-coded that a vendor cannot change: FIELD and PORTAL
//  prices, and the free-portal bundle, are Settings keys with sensible
//  defaults. The FULL price is simply the existing billing per-seat price, so
//  the two models never disagree.
// ---------------------------------------------------------------------------

// Which roles fall into the FIELD (light) class. Everyone else who is a real
// staff login is a FULL seat. Portal users are counted separately (they are not
// in `users`), so they are not listed here.
function superadmin_field_roles() {
    // Configurable: a comma list in Settings can override the default of just
    // the inspector, in case a vendor deploys a second field-only role.
    $raw = trim((string) setting_get('seat_field_roles', ''));
    if ($raw !== '') {
        $r = array_values(array_filter(array_map(fn($s) => strtoupper(trim($s)), explode(',', $raw))));
        if ($r) return $r;
    }
    return ['INSPECTOR'];
}

// The seat-class catalogue: label, price (major units / month) and a one-line
// pitch. FULL tracks the live billing price; FIELD/PORTAL are their own keys.
function superadmin_seat_classes() {
    $bill = function_exists('billing_config') ? billing_config() : [];
    $full = (int) ($bill['price_month'] ?? 0);
    if ($full <= 0) $full = (int) setting_get('billing_price_user_month', 0);
    if ($full <= 0) $full = 1799;                       // Professional default, so the panel is never blank
    $field  = (int) setting_get('billing_price_field_month', 0);  if ($field  <= 0) $field  = 499;
    $portal = (int) setting_get('billing_price_portal_month', 0); // 0 is legitimate — portal can stay free
    if ($portal < 0) $portal = 0;
    $freeBundle = (int) setting_get('billing_portal_free', 5);    if ($freeBundle < 0) $freeBundle = 0;

    return [
        'FULL'  => ['label' => 'Full seat', 'price' => $full, 'free' => 0,
            'desc' => 'Office staff on the desktop suite — managers, coordinators, sales, finance, admin.'],
        'FIELD' => ['label' => 'Field seat', 'price' => $field, 'free' => 0,
            'desc' => 'Inspectors on the field / mobile apps only.'],
        'PORTAL'=> ['label' => 'Portal seat', 'price' => $portal, 'free' => $freeBundle,
            'desc' => 'External client / vendor logins. First ' . $freeBundle . ' free, then a token per head.'],
    ];
}

// Map one staff role to its seat class (FULL / FIELD).
function superadmin_role_class($role) {
    $role = strtoupper((string) $role);
    return in_array($role, superadmin_field_roles(), true) ? 'FIELD' : 'FULL';
}

// Count the active seats in each class and cost them out. Returns the class
// catalogue enriched with `count`, `billable` (after any free bundle) and
// `cost`, plus a grand `total` and a `flat_total` for the "what a flat price
// would have charged" comparison the panel shows.
function superadmin_seat_breakdown() {
    $classes = superadmin_seat_classes();
    $val = function ($sql, $a = []) { try { return (int) ops_val($sql, $a); } catch (Throwable $e) { return 0; } };

    // Staff, grouped by role, from active users.
    $field = superadmin_field_roles();
    $counts = ['FULL' => 0, 'FIELD' => 0, 'PORTAL' => 0];
    $byRole = [];
    try {
        $rows = ops_all("SELECT role, COUNT(*) n FROM users WHERE is_active=1 GROUP BY role");
    } catch (Throwable $e) { $rows = []; }
    foreach ($rows as $r) {
        $role = strtoupper((string) ($r['role'] ?? ''));
        $n = (int) ($r['n'] ?? 0);
        $cls = in_array($role, $field, true) ? 'FIELD' : 'FULL';
        $counts[$cls] += $n;
        $byRole[] = ['role' => $role,
            'label' => (defined('ORG_ROLES') ? (ORG_ROLES[$role] ?? $role) : $role),
            'class' => $cls, 'count' => $n];
    }
    // Portal logins live in their own table; guard for installs without it.
    $counts['PORTAL'] = $val("SELECT COUNT(*) FROM client_users WHERE is_active=1");

    $total = 0; $flatSeats = 0;
    $flatPrice = $classes['FULL']['price'];
    foreach ($classes as $k => &$c) {
        $c['count'] = $counts[$k];
        $c['billable'] = max(0, $counts[$k] - $c['free']);
        $c['cost'] = $c['billable'] * $c['price'];
        $total += $c['cost'];
        if ($k !== 'PORTAL') $flatSeats += $counts[$k];   // a flat model bills every staff seat at the full price
    }
    unset($c);

    usort($byRole, fn($a, $b) => $b['count'] <=> $a['count']);
    return [
        'classes'    => $classes,
        'by_role'    => $byRole,
        'total'      => $total,
        'flat_total' => $flatSeats * $flatPrice,
        'flat_price' => $flatPrice,
        'saving'     => max(0, $flatSeats * $flatPrice - $total),
        'currency'   => function_exists('billing_config') ? (billing_config()['currency'] ?? 'INR') : 'INR',
    ];
}

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
        'tiers'      => superadmin_tiers(),
        'seat_classes' => superadmin_seat_breakdown(),
        'module_labels' => array_map(fn($m) => $m['label'] ?? '', $lic['modules'] ?? []),
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
