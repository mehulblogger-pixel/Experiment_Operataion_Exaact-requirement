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

// Is the SHARED external marketplace available here? Only on the cloud platform,
// and only when the marketplace itself is switched on. A licence copy is local-only
// (its own people), so the "find external freelancers across a shared pool" surfaces
// are off — the honest posture for a private, offline-capable install.
function install_marketplace_enabled() {
    if (!install_is_cloud()) return false;
    return function_exists('connect_enabled') ? connect_enabled() : true;
}

// Where a brand-new user should be sent to start: the guided welcome, either way.
function install_onboarding_route() {
    return install_is_licence() ? '/setup' : '/join';
}
