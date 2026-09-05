<?php
// Revamp P6 — product-package presets. Applying a package sets ONLY the two
// existing switches (industry pack + product bundles) and remembers the choice.
// CONFIGURE, non-destructive.
//
// This test mutates global settings, so it captures the EXACT prior values and
// restores them (so later test files are unaffected). The licence-effect checks
// only run when the modules_off setting is authoritative — on an install pinned
// by a signed licence key the key wins over this chooser (documented), so those
// checks are skipped there and the deterministic setting-contract is asserted
// instead.
t_section('product package presets (Revamp P6)');

$prev = [
    'modules_off'     => (string)setting_get('modules_off', ''),
    'packs_enabled'   => (string)setting_get('packs_enabled', 'inspection'),
    'product_package' => (string)setting_get('product_package', ''),
    'connect_enabled' => (string)setting_get('connect_enabled', '1'),
];
// The modules_off setting is authoritative only when no signed licence enforces a set.
$settingsPath = !function_exists('lk_modules') || lk_modules() === null;

// Assert a package writes the two switches + remembers itself (deterministic),
// and — when the settings path is live — that the bundles actually flip.
$apply_ok = function ($key, $wantPacks, $wantOff, $keptOn) use ($settingsPath) {
    t_ok(product_package_apply($key), "$key applies");
    t_eq((string)setting_get('packs_enabled', ''), $wantPacks, "$key sets the industry pack");
    $off = _pp_norm(setting_get('modules_off', '')); $wo = $wantOff; sort($wo);
    t_eq($off, $wo, "$key writes the right off-bundles");
    t_eq((string)setting_get('product_package', ''), $key, "$key is remembered");
    if ($settingsPath) {
        foreach ($wantOff as $b) t_ok(!licence_enabled($b), "$key hides the $b bundle");
        foreach ($keptOn as $b) t_ok(licence_enabled($b), "$key keeps the $b bundle");
        t_eq(product_package_current(), $key, "$key reads back as current");
    } else {
        t_ok(true, "$key: signed licence active — effect checks skipped (setting contract verified)");
    }
};

try {
    $pkgs = product_packages();
    t_ok(isset($pkgs['TPIA'], $pkgs['STAFFING'], $pkgs['RECRUITMENT'], $pkgs['ENTERPRISE']), 'the four packages are defined');
    t_ok(!product_package_apply('NOPE'), 'an unknown package is refused');
    t_ok(licence_is_core('admin'), 'admin is core and can never be switched off');

    $apply_ok('TPIA',        'inspection', ['sales', 'hr'],         ['operations', 'reporting', 'money']);
    $apply_ok('STAFFING',    '',           ['sales'],               ['operations', 'reporting', 'money', 'hr']);
    $apply_ok('RECRUITMENT', '',           ['operations', 'reporting'], ['sales', 'hr', 'money']);
    $apply_ok('ENTERPRISE',  'inspection', [],                      ['operations', 'sales', 'reporting', 'money', 'hr']);

    // In-house HR recruitment preset: recruitment + admin only, and it pins the
    // marketplace off via the optional 'connect' key.
    t_ok(isset($pkgs['RECRUITMENT_HR']), 'the in-house HR recruitment package is defined');
    $apply_ok('RECRUITMENT_HR', '', ['operations', 'reporting', 'sales', 'money'], ['hr']);
    if ($settingsPath && function_exists('connect_enabled')) {
        t_ok(!connect_enabled(), 'RECRUITMENT_HR switches the marketplace off');
        // An older preset without a 'connect' key must NOT touch the marketplace.
        setting_set('connect_enabled', '1'); if (function_exists('connect_enabled')) connect_enabled();
        product_package_apply('TPIA');
        t_ok(setting_get('connect_enabled','1') === '1', 'applying TPIA leaves the marketplace switch untouched');
    }

    // A hand-tuned change reads back as "custom" (settings path only).
    if ($settingsPath) {
        setting_set('product_package', 'ENTERPRISE');   // stored says ENTERPRISE…
        setting_set('modules_off', 'hr'); licence_disabled(true);   // …but a bundle was turned off by hand
        t_eq(product_package_current(), '', 'a hand-tuned configuration reads back as custom, not the stale stored label');
    } else {
        t_ok(true, 'custom-detection check skipped under a signed licence');
    }
} finally {
    setting_set('modules_off', $prev['modules_off']);
    setting_set('packs_enabled', $prev['packs_enabled']);
    setting_set('product_package', $prev['product_package']);
    setting_set('connect_enabled', $prev['connect_enabled']);
    if (function_exists('licence_disabled')) licence_disabled(true);
    if (function_exists('packs_enabled')) packs_enabled(true);
}
