<?php
// Install mode — is this the shared CLOUD platform or a private LICENCE copy? It decides how
// onboarding works and whether the shared marketplace is available. Default 'cloud' keeps the
// existing hosted instance behaving exactly as before. A pure posture flag — no data, no permission.
t_section('install mode (cloud vs licence)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $mode0 = install_mode();

    // default / cloud
    install_mode_set('cloud');
    t_eq(install_mode(), 'cloud', 'cloud mode reads back');
    t_ok(install_is_cloud() === true && install_is_licence() === false, 'cloud helpers agree');
    t_eq(install_onboarding_route(), '/join', 'cloud onboarding starts at the shared join flow');

    // the shared marketplace is available on cloud (when connect is on)
    if (function_exists('setting_set')) setting_set('connect_enabled', '1');
    setting_set('marketplace_addon', install_is_cloud() ? '1' : '0'); // start from the mode default
    t_ok(install_marketplace_enabled() === true, 'the shared marketplace is available on cloud');
    t_ok(marketplace_addon_on() === true, 'the Connect add-on is ON by default on cloud');
    // Slice 5 — the front door: cloud shows the public marketplace…
    t_eq(install_front_route(), '/connect', 'cloud opens on the Connect front door');
    // …unless the add-on is switched off, then even cloud opens on staff sign-in.
    marketplace_addon_set(false);
    t_ok(install_marketplace_enabled() === false, 'add-on OFF → marketplace unavailable even on cloud');
    t_eq(install_front_route(), '/login', 'with the add-on off, the root opens on staff sign-in');
    marketplace_addon_set(true);

    // licence
    install_mode_set('licence');
    t_eq(install_mode(), 'licence', 'licence mode reads back');
    t_ok(install_is_licence() === true && install_is_cloud() === false, 'licence helpers agree');
    t_eq(install_onboarding_route(), '/setup', 'licence onboarding starts at the set-up-your-company wizard');
    // Slice 5 — the add-on defaults OFF for a licence, and its front door is staff sign-in.
    setting_set('marketplace_addon', '0');
    t_ok(marketplace_addon_on() === false, 'the Connect add-on is OFF by default on a licence copy');
    t_ok(install_marketplace_enabled() === false, 'a licence without the add-on has no marketplace, even with connect enabled');
    t_eq(install_front_route(), '/login', 'a licence copy opens on the staff sign-in');
    // Slice 5 — but a licence CAN buy Connect: turning the add-on on makes the marketplace available…
    marketplace_addon_set(true);
    t_ok(install_marketplace_enabled() === true, 'a licence WITH the paid add-on has the marketplace (decoupled from cloud-only)');
    // …yet it STILL opens on staff sign-in, not the public /connect page (a private ops copy).
    t_eq(install_front_route(), '/login', 'a licence-with-Connect still opens on the staff sign-in, not /connect');

    // an invalid mode is rejected and the mode is unchanged
    t_ok(install_mode_set('nonsense') === false, 'an invalid mode is rejected');
    t_eq(install_mode(), 'licence', 'the mode is unchanged after a rejected set');

    install_mode_set($mode0);  // restore
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    if (function_exists('install_mode_set')) install_mode_set($mode0 ?? 'cloud');
}
