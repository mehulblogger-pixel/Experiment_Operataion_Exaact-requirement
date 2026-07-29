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
    // CORRECTING AN EARLIER DECISION. 'operations' was marked core, which meant
    // it could never be switched off — so "Sales & CRM sold on its own" was a
    // line in a settings screen and not a thing that could actually be
    // delivered. It is not core: a trading company, a consultancy or an agency
    // buying the CRM has no deputations to schedule. What IS core is
    // administration, because every install needs masters, users and settings.
    'operations' => ['Operations', 'Inspection calls, deputations, scheduling, availability',
                     ['calls', 'jobs', 'reconcile', 'vouchers', 'equipment', 'competence', 'impartiality',
                      'identity', 'complaints', 'ncr', 'capa', 'audits', 'datacontrol', 'confidentiality',
                      'overheads'], false],
    'admin'      => ['Administration', 'Masters, users, offices, settings, parties',
                     ['masters', 'users', 'settings', 'clients', 'vendors', 'reports', 'portal'], true],
    'sales'      => ['Sales & CRM', 'Leads, pipelines, enquiries, quotations, approvals, activity, sales dashboards',
                     ['leads', 'inquiries', 'quotes', 'crm_orders', 'crm_reports'], false],
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

    // A SIGNED LICENCE OUTRANKS THE SETTINGS SCREEN. When one is present it is
    // the contract, and what the customer ticked in Settings is irrelevant —
    // otherwise the switch that sells the product could be flipped by the person
    // who did not buy it. With no licence in force this returns null and
    // everything below behaves exactly as it did before licensing existed.
    if (function_exists('lk_modules')) {
        $bought = lk_modules();
        if ($bought !== null) {
            $off = [];
            foreach (PRODUCT_MODULES as $k => [$lbl, $desc, $covers, $core])
                if (!$core && !in_array($k, $bought, true)) $off[] = $k;
            return $off;
        }
    }

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

// ---- The administrator does not own what was not bought ---------------------
// can() already refuses a permission belonging to an unlicensed module. But
// thirty-odd screens guard themselves with `can('mod.x.view') || is_master()`,
// and that bare is_master() walks straight past the licence. The result was an
// administrator on a Sales-only install being offered the equipment register,
// the nonconformity register and the report engine — modules that installation
// had not bought and whose screens would have been half-empty.
//
// Being the administrator means you can do anything the PRODUCT does. It does
// not mean you own modules that are switched off. So: master, but only for a
// module this installation actually has.
//
// $modules is one access-module key, or several — true if ANY of them is
// licensed, matching the `A or B` shape of the guards it replaces.
function is_master_of($modules) {
    if (!is_master()) return false;
    foreach ((array)$modules as $m) {
        $owner = licence_owner($m);
        if ($owner === null || licence_enabled($owner)) return true;   // unclaimed or bought
    }
    return false;
}

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
