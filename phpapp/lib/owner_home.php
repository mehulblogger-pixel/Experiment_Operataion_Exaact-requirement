<?php
// ===========================================================================
//  Owner Home — a calm, plain-language landing for the owner / super-admin.
//  It surfaces the few things run day to day (companies, users, prices) and
//  simple on/off switches for the product modules and the Marketplace, tucking
//  the full "engine room" (the Control Panel) behind one Advanced link.
//  Additive: the Control Panel and everything else are unchanged.
// ===========================================================================

function ops_owner_home($method) {
    ops_require(function_exists('is_master') && is_master(),
        'Only the owner / super admin can open the Owner Home.');

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
            // Marketplace ("Connect") on/off — its own kill-switch.
            setting_set('connect_enabled', (($_POST['marketplace'] ?? '') === '1') ? '1' : '0');
        }
        if (function_exists('licence_disabled')) licence_disabled(true);   // reload the off-list cache
        flash('Saved. Your left-hand menu updates on the next page.');
        redirect('/owner');
    }

    // Read the current state for the screen.
    $mods = [];
    if (defined('PRODUCT_MODULES')) foreach (PRODUCT_MODULES as $k => $m) {
        $mods[$k] = ['label' => $m[0], 'desc' => $m[1], 'core' => !empty($m[3]),
                     'on' => function_exists('licence_enabled') ? licence_enabled($k) : true];
    }
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
