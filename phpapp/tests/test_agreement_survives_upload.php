<?php
// ============================================================================
//  The accepted licence agreement must survive an upload, and a database that
//  will not open must never be presented as "you need to install".
//
//  Both faults were seen live, hours apart:
//   • acceptance was a file INSIDE the application folder — the folder the
//     update method clears — so every upload erased it and the software
//     demanded the agreement again;
//   • when the database password was changed and the application could no
//     longer connect, the safety net (setup_done) went quiet too, and the
//     software offered to install itself over a live system.
// ============================================================================

t_section('Licence acceptance survives an upload');

$appDir = dirname(__DIR__);
$path   = agreement_file();

t_ok(is_string($path) && $path !== '', 'the acceptance file has a location');
$safeDir = function_exists('tenant_safe_data_dir') ? tenant_safe_data_dir()[0] : '';
if ($safeDir !== '' && $safeDir !== $appDir) {
    t_eq(dirname($path), rtrim($safeDir, '/'),
         'and it is kept outside the application folder, where an upload cannot reach it');
    t_ok(strncmp(realpath(dirname($path)) ?: dirname($path), realpath($appDir), strlen(realpath($appDir))) !== 0,
         'confirmed: not inside the folder the update method clears');
}

// An acceptance recorded by an older version, still in the app folder, must be
// honoured — nobody should be asked to sign the same agreement twice.
$legacy = $appDir . '/licence-agreement.json';
$hadLegacy = is_file($legacy);
$safeFile = ($safeDir !== '' && $safeDir !== $appDir) ? rtrim($safeDir, '/') . '/licence-agreement.json' : '';
$hadSafe = $safeFile !== '' && is_file($safeFile);
if (!$hadLegacy && !$hadSafe && $safeFile !== '') {
    file_put_contents($legacy, json_encode(['accepted_at' => '2026-01-01T00:00:00+00:00', 'name' => 'Prior Install']));
    $resolved = agreement_file();
    t_eq($resolved, $safeFile, 'an older acceptance in the app folder is lifted to the safe location');
    t_ok(is_file($legacy), 'and the original is COPIED, never moved, so a failed copy cannot lose it');
    t_ok(agreement_accepted() || agreement_exempt(), 'the acceptance is still honoured after the lift');
    @unlink($safeFile); @unlink($legacy);
    t_ok(!is_file($safeFile) && !is_file($legacy), 'fixtures cleaned up');
} else {
    t_ok(true, 'acceptance files already present — lift not exercised, nothing removed');
}

t_section('A database that will not open is not an installation');

$src = (string) file_get_contents($appDir . '/lib/agreement.php');
t_ok(function_exists('agreement_db_down_page'), 'there is a page for an unreachable database');
t_ok(strpos($src, 'setup_test_db($d)') !== false,
     'the gate tests the configured database before offering the agreement');
t_ok(strpos($src, 'setup_config_placeholder()') !== false,
     'and only when real credentials are configured, so a genuinely new install still installs');
t_ok(strpos($src, 'http_response_code(503)') !== false,
     'the page answers 503, not 200 — this is an outage, not a form');
foreach (['config.local.php', 'single</b> quotes', 'nothing to install'] as $needle)
    t_ok(strpos($src, $needle) !== false, "the page tells the operator about: $needle");
