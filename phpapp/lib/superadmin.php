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

// Platform super-admin — the provider's own control surface (companies & tenants,
// product package, licence, marketplace provider config, system tools). It belongs
// ONLY to the control install (the platform owner). A client COMPANY's own master
// admin manages their company through the normal Admin screens, but must never see
// or reach the platform tools, so this is false inside any company workspace.
function superadmin_can() {
    return function_exists('is_master') && is_master()
        && (!function_exists('current_tenant') || current_tenant() === '')
        && superadmin_host_ok();
}

// ---------------------------------------------------------------------------
//  ITS OWN ADDRESS (owner, 22 Oct 2026: "Super admin shall have complete
//  different login"). The platform owner may give Super Admin its own web
//  address (e.g. operations-admin.mghaiapps.com) and LOCK it there:
//    • Super Admin screens then open only at that address — never on the
//      address every company uses;
//    • at that address only platform-owner (Master Admin) accounts can sign
//      in, and company e-mails are never routed into their workspaces there;
//    • browsers keep a separate sign-in per address, so the two never mix.
//  The lock can only be switched on FROM the new address (which proves it
//  works), and can be undone without the app: an empty file named
//  "superadmin-unlock" in the protected data folder, or the server setting
//  OPS_SUPERADMIN_ANY_HOST=1.
// ---------------------------------------------------------------------------
function request_host() {
    return strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
}
// Where the address and the lock are kept. In cloud mode: the workspace
// registry (tenants.php), so they read the same whichever database the request
// is standing in. On a single-company install: the main site's settings.
function superadmin_cfg_store() {
    return function_exists('tenant_registry_file') && is_file(tenant_registry_file()) ? 'registry' : 'settings';
}
function superadmin_cfg() {
    if (superadmin_cfg_store() === 'registry') {
        $r = tenant_registry();
        return ['host' => strtolower(trim((string) ($r['superadmin_host'] ?? ''))), 'locked' => !empty($r['superadmin_locked'])];
    }
    if (!function_exists('setting_get') || (function_exists('current_tenant') && current_tenant() !== '')) return ['host' => '', 'locked' => false];
    return ['host' => strtolower(trim((string) setting_get('superadmin_host', ''))), 'locked' => (string) setting_get('superadmin_host_locked', '') === '1'];
}
function superadmin_cfg_write($host, $locked, $oldHost = null) {
    if (superadmin_cfg_store() === 'registry') {
        $reg = tenant_registry();
        // The address opens the platform's own site, never a company: it is
        // listed among the main site's own addresses.
        $old = strtolower((string) ($oldHost ?? ($reg['superadmin_host'] ?? '')));
        $al = array_values(array_filter(array_map('strtolower', (array) ($reg['aliases'] ?? [])), fn($a) => $a !== $old || $a === $host));
        if ($host !== '' && !in_array($host, $al, true)) $al[] = $host;
        $reg['aliases'] = $al;
        $reg['superadmin_host'] = $host;
        $reg['superadmin_locked'] = $host !== '' && $locked;
        return tenant_registry_write($reg);
    }
    setting_set('superadmin_host', $host);
    setting_set('superadmin_host_locked', $host !== '' && $locked ? '1' : '');
    return '';
}
function superadmin_host() { return superadmin_cfg()['host']; }
function superadmin_locked() { $c = superadmin_cfg(); return $c['host'] !== '' && $c['locked']; }
function superadmin_unlock_override() {
    if ((string) getenv('OPS_SUPERADMIN_ANY_HOST') === '1') return true;
    $dir = function_exists('tenant_safe_data_dir') ? (string) tenant_safe_data_dir()[0] : '';
    return $dir !== '' && is_file(rtrim($dir, '/') . '/superadmin-unlock');
}
// Is THIS request on the Super Admin address?
function on_superadmin_host() {
    $h = superadmin_host();
    return $h !== '' && request_host() === $h;
}
// May Super Admin be used from where this request came in?
function superadmin_host_ok() {
    if (request_host() === '') return true;                       // the nightly job and tests: no web address
    if (!superadmin_locked() || superadmin_unlock_override()) return true;
    return on_superadmin_host();
}
// The platform-owner screens, so an old link on the main address is sent on.
const SUPERADMIN_ROUTES = ['owner', 'platform-setup', 'companies', 'tenants', 'super-admin', 'control-panel',
                           'pricing-usage', 'marketplace-plans', 'marketplace-toggle', 'product-package', 'feature-gates', 'gst-invoices'];
// The address every company uses (for "sign in over there" messages).
function superadmin_main_address() {
    $base = function_exists('tenant_registry') ? strtolower((string) (tenant_registry()['base_domain'] ?? '')) : '';
    return $base !== '' ? 'https://' . $base : '';
}
// Runs on every request, before any page. Does nothing until an address is saved.
//  • ON the Super Admin address: only the platform owner's own sign-in exists —
//    no public pages, no company sessions, no company accounts.
//  • On the main address, once LOCKED: a Super Admin screen is sent on to the
//    Super Admin address (where the owner signs in separately).
function superadmin_address_gate($route) {
    $host = superadmin_host();
    if ($host === '' || request_host() === '') return;
    $u = function_exists('current_user') ? current_user() : null;
    if (on_superadmin_host()) {
        $inCompany = function_exists('current_tenant') && current_tenant() !== '';
        $signedInHere = (string) ($_SESSION['login_host'] ?? '') === $host;
        if ($u && ($inCompany || !$signedInHere || user_role($u) !== 'MASTER_ADMIN')) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
            $_SESSION['sso_msg'] = $signedInHere || $inCompany || user_role($u) !== 'MASTER_ADMIN'
                ? superadmin_wrong_door_msg() : 'Please sign in again at this address.';
            redirect('/login');
        }
        if (!$u && !in_array($route, ['login', 'logout', 'forgot', 'reset'], true)) redirect('/login');
        if ($u && $route === '') redirect('/owner');
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && superadmin_locked() && !superadmin_unlock_override()
        && in_array($route, SUPERADMIN_ROUTES, true) && $u
        && (!function_exists('current_tenant') || current_tenant() === '') && user_role($u) === 'MASTER_ADMIN') {
        $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
        redirect('https://' . $host . '/' . $route . ($qs !== '' ? '?' . $qs : ''));
    }
}
function superadmin_wrong_door_msg() {
    $main = superadmin_main_address();
    return 'This address is only for the platform owner (Super Admin). '
        . ($main !== '' ? 'Company users sign in at ' . $main . '/login' : 'Company users sign in at the main address') . '.';
}

// ---- Setting the address up (Platform setup → Super Admin address) -----------------
// When the main address is itself a sub-domain (operations.mghaiapps.com), the
// suggestion is a sibling one level deep (operations-admin.mghaiapps.com), not
// admin.operations.mghaiapps.com: many hosting panels (MilesWeb's among them)
// create the DNS record and certificate only for one-level sub-domains, so a
// nested address never opens.
function superadmin_host_suggest() {
    $b = function_exists('tenant_registry') ? strtolower((string) (tenant_registry()['base_domain'] ?? '')) : '';
    if ($b === '') return '';
    $parts = explode('.', $b);
    $tail2 = implode('.', array_slice($parts, -2));
    $twoLevel = in_array($tail2, ['co.in', 'net.in', 'org.in', 'firm.in', 'gen.in', 'ind.in', 'co.uk', 'org.uk', 'com.au', 'co.nz', 'com.sg'], true);
    $isSub = count($parts) > ($twoLevel ? 3 : 2);
    return $isSub ? $parts[0] . '-admin.' . implode('.', array_slice($parts, 1)) : 'admin.' . $b;
}
// Save (or clear, with '') the Super Admin address. Returns '' or the reason it was refused.
function superadmin_host_save($host) {
    $host = strtolower(trim((string) $host));
    $host = preg_replace('#^https?://#', '', $host);
    $host = rtrim(explode('/', $host)[0], '.');
    if (superadmin_locked()) return 'Unlock Super Admin first — then the address can be changed.';
    if ($host !== '' && !preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host))
        return 'That is not a web address. Type it like ' . (superadmin_host_suggest() ?: 'admin.your-domain.com') . ' — no https://, no slash.';
    $reg = function_exists('tenant_registry') ? tenant_registry() : ['base_domain' => '', 'aliases' => [], 'tenants' => []];
    $base = strtolower((string) ($reg['base_domain'] ?? ''));
    if ($host !== '' && ($host === $base || $host === 'www.' . $base))
        return 'That is the address every company uses. Super Admin needs a different one, such as ' . (superadmin_host_suggest() ?: 'admin.your-domain.com') . '.';
    if ($host !== '' && $base !== '' && substr($host, -strlen('.' . $base)) === '.' . $base) {
        $label = substr($host, 0, -strlen('.' . $base));
        if (isset($reg['tenants'][$label]) || (function_exists('saas_tenant_get') && saas_tenant_get($label)))
            return 'A company already uses the name "' . $label . '". Choose another, such as ' . (superadmin_host_suggest() ?: 'admin.' . $base) . '.';
    }
    return superadmin_cfg_write($host, false, superadmin_host());
}
// Ask cPanel to create the address, pointing at the same app folder.
function superadmin_host_cpanel() {
    $host = superadmin_host();
    if ($host === '') return ['ok' => false, 'msg' => 'Save the address first.'];
    if (!function_exists('cpanel_configured') || !cpanel_configured())
        return ['ok' => false, 'msg' => 'cPanel is not connected. Create the address by hand: cPanel → Domains (or Subdomains).'];
    $c = cpanel_config();
    $p = explode('.', $host, 2);
    $root = $c['base_domain'] !== '' && substr($host, -strlen('.' . $c['base_domain'])) === '.' . $c['base_domain'] ? $c['base_domain'] : ($p[1] ?? '');
    $label = substr($host, 0, -strlen('.' . $root));
    if ($label === '' || $c['docroot'] === '')
        return ['ok' => false, 'msg' => 'The cPanel settings have no document root yet. Create the address by hand: cPanel → Domains (or Subdomains), with the same folder as the main site.'];
    $r = cpanel_api('SubDomain', 'addsubdomain', ['domain' => $label, 'rootdomain' => $root, 'dir' => $c['docroot']]);
    return $r['ok']
        ? ['ok' => true, 'msg' => $host . ' was created. The free security certificate (AutoSSL) can take up to an hour; then open https://' . $host . '/login.']
        : ['ok' => false, 'msg' => 'cPanel did not create it (' . (implode(' ', (array) $r['errors']) ?: 'refused') . '). Create it by hand: cPanel → Domains (or Subdomains).'];
}
// Lock or unlock. Locking only works FROM the new address: that proves it
// opens, has its certificate, and that the owner can sign in there.
function superadmin_lock_set($on) {
    if (!$on) return superadmin_cfg_write(superadmin_host(), false);
    if (superadmin_host() === '') return 'Save the Super Admin address first.';
    if (!on_superadmin_host())
        return 'Open https://' . superadmin_host() . '/platform-setup, sign in there, and lock it from that page. '
             . 'This makes sure the address works before the main address stops showing Super Admin.';
    return superadmin_cfg_write(superadmin_host(), true);
}

// Workspace names no company may take (they are addresses of the platform itself).
function platform_reserved_keys() {
    $r = ['admin', 'www', 'mail', 'webmail', 'cpanel', 'whm', 'api', 'app', 'ftp', 'start', 'login'];
    $h = superadmin_host();
    if ($h !== '') $r[] = explode('.', $h)[0];
    return array_values(array_unique($r));
}

// Recommended plan packaging: which PRODUCT_MODULES each tier grants when a
// licence key is issued. The licence engine already reads `mods` from the key —
// these presets are the map you pick from at issue time. Prices stay in Settings.
function superadmin_tiers() {
    return [
        'STARTER' => ['label' => 'Starter', 'mods' => ['admin', 'operations'],
            'pitch' => 'Core operations for a single office / small TPIA.'],
        'RECRUITMENT' => ['label' => 'Recruitment', 'mods' => ['admin', 'hr'],
            'pitch' => 'Recruitment & Selection only — requisitions, candidates, interviews, offer & onboarding. Operations, Sales, Money and the report engine are not licensed.'],
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
// Every price is the platform owner's saved setting (lib/commercial.php);
// 0 = not set yet — never an invented figure.
function superadmin_seat_classes() {
    $bill = function_exists('billing_config') ? billing_config() : [];
    $full = max(0, (int) ($bill['price_month'] ?? 0));
    if ($full <= 0) $full = max(0, (int) setting_get('billing_price_user_month', 0));
    $field  = max(0, (int) setting_get('billing_price_field_month', 0));
    $portal = max(0, (int) setting_get('billing_price_portal_month', 0));
    $freeBundle = max(0, (int) setting_get('billing_portal_free', 0));

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
    ops_require(superadmin_can(), 'Only the platform owner (Super Admin), on the main site, can open the control panel — a company\'s own administrators never can.');
    view('ops/super_admin', ['d' => superadmin_data()]);
    return true;
}
