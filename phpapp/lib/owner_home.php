<?php
// ===========================================================================
//  Owner Home — a calm, plain-language landing for the owner / super-admin.
//  It surfaces the few things run day to day (companies, users, prices) and
//  simple on/off switches for the product modules and the Marketplace, tucking
//  the full "engine room" (the Control Panel) behind one Advanced link.
//  Additive: the Control Panel and everything else are unchanged.
// ===========================================================================

function ops_owner_home($method) {
    ops_require(function_exists('superadmin_can') && superadmin_can(),
        'The Owner Home is for the platform owner, on the main site — not inside a company workspace.');

    // Save the module + marketplace switches.
    if ($method === 'POST' && (($_POST['do'] ?? '') === 'modules_save')) {
        $picked = array_map('strval', (array)($_POST['mods'] ?? []));
        $off = [];
        if (defined('PRODUCT_MODULES')) foreach (PRODUCT_MODULES as $k => $m) {
            $core = !empty($m[3]);                      // core (Administration) is always on
            if (!$core && !in_array($k, $picked, true)) $off[] = $k;
        }
        if (function_exists('setting_set')) {
            setting_set('modules_off', implode(',', $off));
            // Marketplace ("Connect") on/off. Owner, 10 Nov 2026 — this page had two
            // switches for the one Marketplace (the module row and a separate row);
            // turning on one and not the other left it hidden. When the module is
            // listed, its tick is the one answer; the separate row is only shown
            // (and read) on an install that does not list the module.
            $mktOn = (defined('PRODUCT_MODULES') && isset(PRODUCT_MODULES['connect']))
                ? in_array('connect', $picked, true)
                : (($_POST['marketplace'] ?? '') === '1');
            setting_set('connect_enabled', $mktOn ? '1' : '0');
        }
        if (function_exists('licence_disabled')) licence_disabled(true);   // reload the off-list cache
        flash('Saved. Your left-hand menu updates on the next page.');
        redirect('/owner');
    }
    return ops_owner_home_render();
}

// One-tap Marketplace on/off, callable from anywhere the owner already is
// (linked from the Super-Admin Control Panel's Product-modules block too).
function ops_marketplace_toggle($method) {
    ops_require(function_exists('superadmin_can') && superadmin_can(),
        'Only the platform owner, on the main site, can change this.');
    if ($method === 'POST' && function_exists('setting_set')) {
        $on = (($_POST['on'] ?? '') === '1');
        setting_set('connect_enabled', $on ? '1' : '0');
        if (function_exists('licence_disabled')) licence_disabled(true);
        flash('Marketplace turned ' . ($on ? 'ON' : 'OFF') . '. Your left-hand menu updates on the next page.');
    }
    $back = (string)($_POST['back'] ?? '');
    redirect($back === 'owner' ? '/owner' : ($back === 'modules' ? '/workspace/setup/modules' : '/super-admin'));
}

function ops_owner_home_render() {
    // Read the current state for the screen.
    $mods = [];
    if (defined('PRODUCT_MODULES')) foreach (PRODUCT_MODULES as $k => $m) {
        $mods[$k] = ['label' => $m[0], 'desc' => $m[1], 'core' => !empty($m[3]),
                     'on' => function_exists('licence_enabled') ? licence_enabled($k) : true];
    }
    // The Marketplace row is the one switch: on only when it is actually live.
    if (isset($mods['connect']) && function_exists('connect_enabled')) $mods['connect']['on'] = $mods['connect']['on'] && connect_enabled();
    $modsOn = 0; foreach ($mods as $mm) if ($mm['on']) $modsOn++;
    $companies = function_exists('saas_tenant_all') ? count(saas_tenant_all()) : 0;
    $seatsUsed = 0;
    try { $seatsUsed = function_exists('lk_seats_used') ? (int) lk_seats_used() : (int) ops_val("SELECT COUNT(*) FROM users WHERE is_active=1"); }
    catch (Throwable $e) {}
    view('ops/owner_home', [
        'mods'       => $mods,
        'mods_on'    => $modsOn,
        'market_on'  => function_exists('connect_enabled') ? connect_enabled() : true,
        'companies'  => $companies,
        'seats_used' => $seatsUsed,
        'cloud'      => function_exists('saas_enabled') && saas_enabled(),
    ]);
    return true;
}
