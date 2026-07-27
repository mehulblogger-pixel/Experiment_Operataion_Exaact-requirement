<?php
// ============================================================================
//  Which parts of the product this installation has bought
//
//  The owner asked to split the app into six saleable products — Sales,
//  Operations, Money, HR, Reporting, Admin. Measuring the code first said that
//  would be six to ten weeks of refactoring with nothing a customer could see:
//  ops.php alone is ~4,700 lines, is called ~700 times from other modules, and
//  holds the router, the schema, authentication, e-mail, money, HR and masters.
//  See docs/PLATFORM-STRATEGY.md for the numbers.
//
//  This file delivers the commercial outcome instead: one product, licensed by
//  module. A customer who has not bought Sales does not see Sales — the menu
//  group is gone, the routes refuse, the dashboards drop those panels.
//
//  How it manages that in one small file: every screen and every menu item in
//  this app already asks can('mod.<something>.view'). So the licence is applied
//  inside can() itself. Switch a module off and every gate that was already
//  written starts saying no, with nothing else to change and nothing to
//  remember. That is why this is days rather than weeks.
//
//  Two modules can never be switched off — OPERATIONS and ADMIN. An inspection
//  system without calls, deputations, masters and users is not a smaller
//  product; it is not a product. Offering them as options would only create a
//  configuration in which the app is broken.
// ============================================================================

// Saleable module => [label, what it covers, the fine-grained access modules]
const PRODUCT_MODULES = [
    'operations' => ['Operations', 'Inspection calls, deputations, scheduling, availability',
                     ['calls', 'jobs', 'reconcile', 'vouchers'], true],
    'admin'      => ['Administration', 'Masters, users, offices, settings, parties',
                     ['masters', 'users', 'settings', 'clients', 'vendors', 'overheads', 'reports'], true],
    'sales'      => ['Sales & CRM', 'Inquiries, quotations, approvals, sales dashboards',
                     ['inquiries', 'quotes', 'crm_orders', 'crm_reports'], false],
    'reporting'  => ['Inspection reporting', 'The report engine, formats, endorsements, evidence',
                     ['idems'], false],
    'money'      => ['Money', 'Invoicing, profitability, credits and the cost run',
                     ['invoicing', 'profitability'], false],
    'hr'         => ['People & hiring', 'Requisitions, candidates, placement',
                     ['hiring'], false],
];

// Which product module owns a fine-grained access module. Anything not claimed
// below is treated as always available — a new access module must never become
// invisible just because nobody remembered to license it.
function licence_owner($accessModule) {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (PRODUCT_MODULES as $key => [$lbl, $desc, $covers, $core])
            foreach ($covers as $c) $map[$c] = $key;
    }
    return $map[$accessModule] ?? null;
}

function licence_is_core($key) { return !empty(PRODUCT_MODULES[$key][3]); }

// The switched-off list. Held as a setting so it survives an upgrade, and
// overridable by environment variable so an on-premise install can be pinned
// from outside the database — a customer with database access should not be
// able to switch a module on by editing a row.
// Pass $reload = true to re-read after saving. Same shape as term_overrides().
function licence_disabled($reload = false) {
    static $off = null;
    if ($off !== null && !$reload) return $off;
    $env = getenv('MODULES_OFF');
    $raw = ($env !== false && $env !== '') ? $env : (string)setting_get('modules_off', '');
    $off = [];
    foreach (explode(',', $raw) as $k) {
        $k = strtolower(trim($k));
        // A core module named here is ignored on purpose, not obeyed.
        if ($k !== '' && isset(PRODUCT_MODULES[$k]) && !licence_is_core($k)) $off[] = $k;
    }
    return $off = array_values(array_unique($off));
}

function licence_enabled($key) { return !in_array($key, licence_disabled(), true); }

// True when this permission belongs to a module the installation has not
// bought. Only 'mod.<x>.<y>' permissions can be licensed away — a bare
// capability like data.salary is a matter of the person's role, not the
// contract, and licensing it here would silently change who sees salaries.
function licence_blocks($perm) {
    if (strncmp($perm, 'mod.', 4) !== 0) return false;
    $parts = explode('.', $perm);
    if (count($parts) < 3) return false;
    $owner = licence_owner($parts[1]);
    return $owner !== null && !licence_enabled($owner);
}

// What to save from the Settings screen. Core modules are never written.
function licence_save($posted) {
    $off = [];
    foreach (PRODUCT_MODULES as $key => [$lbl, $desc, $covers, $core]) {
        if ($core) continue;
        if (empty($posted['mod_on'][$key])) $off[] = $key;
    }
    setting_set('modules_off', implode(',', $off));
    // Re-read at once: the person who just saved must see the result on the
    // very next screen, not on their next sign-in.
    licence_disabled(true);
    return $off;
}

// The plain-English state, for the Settings screen and for support.
function licence_summary() {
    $out = [];
    foreach (PRODUCT_MODULES as $key => [$lbl, $desc, $covers, $core])
        $out[$key] = ['label' => $lbl, 'desc' => $desc, 'core' => $core,
                      'on' => $core ? true : licence_enabled($key)];
    return $out;
}
