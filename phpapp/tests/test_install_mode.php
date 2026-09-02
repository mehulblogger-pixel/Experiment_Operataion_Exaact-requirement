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
    t_ok(install_marketplace_enabled() === true, 'the shared marketplace is available on cloud');

    // licence
    install_mode_set('licence');
    t_eq(install_mode(), 'licence', 'licence mode reads back');
    t_ok(install_is_licence() === true && install_is_cloud() === false, 'licence helpers agree');
    t_eq(install_onboarding_route(), '/setup', 'licence onboarding starts at the set-up-your-company wizard');
    t_ok(install_marketplace_enabled() === false, 'a licence copy is local-only — the shared marketplace is off, even with connect enabled');

    // an invalid mode is rejected and the mode is unchanged
    t_ok(install_mode_set('nonsense') === false, 'an invalid mode is rejected');
    t_eq(install_mode(), 'licence', 'the mode is unchanged after a rejected set');

    install_mode_set($mode0);  // restore
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    if (function_exists('install_mode_set')) install_mode_set($mode0 ?? 'cloud');
}
