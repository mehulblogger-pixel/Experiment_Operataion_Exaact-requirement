<?php
// DEMO-S04 — Marketplace lifecycle exercising the new engines (CLI wrapper).
//   php tools/seed-scenario-s04.php [--status|--remove]
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php'); $libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }
$arg = $argv[1] ?? '';
if ($arg === '--status') { foreach (seed_s04_status() as $k => $v) echo str_pad($k, 8) . ': ' . (is_bool($v) ? ($v ? 'yes' : 'no') : $v) . "\n"; exit(0); }
if ($arg === '--remove') { echo 'Removed ' . seed_s04_remove() . " DEMO-S04 records.\n"; exit(0); }
echo "== DEMO-S04 — Marketplace lifecycle (new engines) ==\n";
$r = seed_s04_load();
foreach ($r['log'] as $l) echo '  ' . $l . "\n";
echo "\n  ┌─ DEMO-S04 DASHBOARD ────────────────────────────\n";
foreach ($r['dashboard'] as [$label, $ok]) echo '  │ ' . str_pad($label, 52) . ' ' . ($ok ? 'PASS' : 'FAIL') . "\n";
echo '  └─ OVERALL: ' . ($r['allpass'] ? 'ALL PASS' : 'SOME FAIL') . " ─────────────\n";
