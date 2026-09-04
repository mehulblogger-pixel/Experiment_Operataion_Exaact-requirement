<?php
// ============================================================================
//  DEMO-S01 — Scenario seed (CLI wrapper). The scenario logic lives in
//  lib/seed_scenario_s01.php so the SAME code powers this command and the
//  admin button (Admin → System Settings → DEMO-S01 scenario).
//
//  Usage (from phpapp/):
//    php tools/seed-scenario-s01.php            load / refresh (idempotent)
//    php tools/seed-scenario-s01.php --remove   remove all DEMO-S01 data
//    php tools/seed-scenario-s01.php --status   report what exists
// ============================================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php');
$libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

$arg = $argv[1] ?? '';
if ($arg === '--status') {
    $s = seed_s01_status();
    echo 'DEMO-S01 loaded : ' . ($s['loaded'] ? 'yes' : 'no') . "\n";
    echo 'Professionals   : ' . $s['pros'] . "\n";
    echo 'Client party    : ' . $s['client'] . "\n";
    echo 'Report          : ' . $s['report'] . "\n";
    exit(0);
}
if ($arg === '--remove') { echo 'Removed ' . seed_s01_remove() . " DEMO-S01 records.\n"; exit(0); }

echo "== DEMO-S01 — Transmission Technician Marketplace Journey ==\n";
$r = seed_s01_load();
foreach ($r['log'] as $line) echo '  ' . $line . "\n";
echo "\n  ┌─ DEMO-S01 SCENARIO DASHBOARD ────────────────────────────\n";
foreach ($r['dashboard'] as [$label, $ok]) echo '  │ ' . str_pad($label, 40) . ' ' . ($ok ? 'PASS' : 'FAIL') . "\n";
echo '  └─ OVERALL: ' . ($r['allpass'] ? 'ALL PASS' : 'SOME FAIL') . " ─────────────────\n";
