<?php
// ===========================================================================
//  INSTALL MODE — is this copy of EXAACT the shared CLOUD platform, or a private
//  LICENCE install a customer runs on their own server / laptop?
//
//  The two are sold differently and must onboard differently:
//    • CLOUD   — hosted, shared. Companies self-register via /join and meet in a
//                shared marketplace. Onboarding = "join the platform".
//    • LICENCE — one company owns their private copy (their own server, or a
//                laptop/desktop acting as the server, on SQLite). There is no
//                shared pool; the copy runs that company's OWN operations.
//                Onboarding = "set up your company". The external marketplace is
//                OFF (a future paid "Marketplace Connect" add-on can bridge to
//                the cloud pool). Their data never leaves their machine.
//
//  It is a single setting, chosen once at install and rarely changed. Default is
//  'cloud' so the existing hosted instance keeps behaving exactly as before.
//  This is only the deployment/onboarding posture — it grants no permission and
//  changes no data.
// ===========================================================================

function install_mode_options() {
    return [
        'cloud'   => 'Cloud platform (hosted, shared marketplace)',
        'licence' => 'Licence install (private copy — this company only)',
    ];
}
function install_mode() {
    $m = strtolower((string)(function_exists('setting_get') ? setting_get('install_mode', 'cloud') : 'cloud'));
    return isset(install_mode_options()[$m]) ? $m : 'cloud';
}
function install_is_cloud()   { return install_mode() === 'cloud'; }
function install_is_licence() { return install_mode() === 'licence'; }
function install_mode_label() { return install_mode_options()[install_mode()] ?? install_mode(); }

function install_mode_set($mode) {
    $mode = strtolower((string)$mode);
    if (!isset(install_mode_options()[$mode])) return false;
    if (function_exists('setting_set')) setting_set('install_mode', $mode);
    return true;
}

// ---------------------------------------------------------------------------
//  MARKETPLACE ("Connect") as a PAID ADD-ON — for BOTH cloud and licence.
//
//  The marketplace is an upsell that sits on top of the operations platform:
//    • CLOUD   — the platform IS the marketplace, so the add-on is ON by default.
//    • LICENCE — a private operations copy. Connect is a paid bridge to the shared
//                pool, so the add-on is OFF by default and switched on per customer
//                (in Super Admin) when they buy it. Their own operations are
//                untouched either way.
//  `connect_enabled` remains the hard on/off kill-switch; the add-on entitlement is
//  the commercial layer on top of it. Both must be on for the marketplace to appear.
// ---------------------------------------------------------------------------

/** Is the paid marketplace add-on entitled on this install? Cloud → default ON, licence → default OFF. */
function marketplace_addon_on() {
    $default = install_is_cloud() ? '1' : '0';
    $v = function_exists('setting_get') ? (string)setting_get('marketplace_addon', $default) : $default;
    return $v === '1';
}
function marketplace_addon_set($on) {
    if (function_exists('setting_set')) setting_set('marketplace_addon', $on ? '1' : '0');
    return true;
}

// Is the Connect marketplace available here? True when the add-on is entitled AND the
// hard kill-switch is on. Works for a licence-with-Connect just as for the cloud.
function install_marketplace_enabled() {
    if (function_exists('connect_enabled') && !connect_enabled()) return false;   // hard off
    return marketplace_addon_on();
}

// Where the bare root sends an unauthenticated visitor.
//   • CLOUD (marketplace is the public face) → the Connect front door.
//   • LICENCE (a private operations copy, even one that bought Connect) → staff sign-in.
function install_front_route() {
    return (install_is_cloud() && install_marketplace_enabled()) ? '/connect' : '/login';
}

// Where a brand-new user should be sent to start: the guided welcome, either way.
function install_onboarding_route() {
    return install_is_licence() ? '/setup' : '/join';
}
