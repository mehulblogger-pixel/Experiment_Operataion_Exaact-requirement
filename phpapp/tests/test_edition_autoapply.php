<?php
// "Build once, sell many ways." The SAME code, shipped as a Recruitment build,
// configures itself to Recruitment-only on first boot from a delivery marker
// (the EXAACT_EDITION env var, or an edition.txt file in the package) with no
// clicks. This checks the marker parsing, the one-shot guards, and that a
// recruitment marker triggers the recruitment-only provisioning. It fully
// snapshots and restores the global product/role settings so the shared test
// database is left exactly as it was found.
t_section('delivery edition — a Recruitment build self-configures');

t_ok(function_exists('edition_marker'),    'edition_marker() is defined');
t_ok(function_exists('edition_autoapply'), 'edition_autoapply() is defined');

// --- snapshot every setting the apply path can touch, so we can restore it ---
$keys = ['product_package', 'modules_off', 'packs_enabled', 'connect_enabled', 'edition_applied', 'role_access'];
$snap = [];
foreach ($keys as $k) $snap[$k] = setting_get($k, null);

$restore = function () use ($keys, $snap) {
    $cache = &settings_cache();
    foreach ($keys as $k) {
        if ($snap[$k] === null) {                    // was unset — remove it entirely
            try { db()->prepare("DELETE FROM settings WHERE skey=?")->execute([$k]); } catch (Throwable $e) {}
            unset($cache[$k]);
        } else {
            setting_set($k, $snap[$k]);
        }
    }
    if (function_exists('packs_enabled'))    packs_enabled(true);
    if (function_exists('licence_disabled')) licence_disabled(true);
};

// 1) No marker on a fresh install -> stays the full platform.
putenv('EXAACT_EDITION');                             // ensure unset
setting_set('product_package', '');
setting_set('edition_applied', '');
t_eq(edition_autoapply(), '', 'with no marker, a fresh install stays the full platform');

// 2) Marker parsing: value is read and lower-cased (env takes precedence).
putenv('EXAACT_EDITION=Recruitment');
t_eq(edition_marker(), 'recruitment', 'the delivery marker is read and normalised');

// 3) A recruitment marker on a fresh install configures Recruitment-only.
setting_set('product_package', '');
setting_set('edition_applied', '');
t_eq(edition_autoapply(), 'recruitment', 'a recruitment build self-configures on first boot');
t_ok(strpos((string)setting_get('modules_off', ''), 'operations') !== false, 'the other modules are switched off');
t_eq((string)setting_get('product_package', ''), 'RECRUITMENT_HR', 'the recruitment package is recorded');
t_eq((string)setting_get('edition_applied', ''), 'recruitment', 'the one-shot marker is stamped');

// 4) It never runs twice.
t_eq(edition_autoapply(), '', 'it does not re-apply on later boots');

// 5) It never overrides a package the admin has already chosen.
setting_set('edition_applied', '');                   // clear the one-shot guard...
setting_set('product_package', 'ENTERPRISE');         // ...but a package is already set
t_eq(edition_autoapply(), '', 'a chosen product package is never overridden');

putenv('EXAACT_EDITION');                             // unset the env for other tests
$restore();                                            // leave the shared DB exactly as found

// Confirm the restore actually cleaned up (nothing leaks to later tests).
t_eq((string)setting_get('product_package', ''), (string)($snap['product_package'] ?? ''), 'global product package restored');
