<?php
// DEMO-S05 — Convergence & reconciliation (read-only dual-truth detectors) — CLI wrapper.
//   php tools/seed-scenario-s05.php [--status|--remove]
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php'); $libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }
$arg = $argv[1] ?? '';
if ($arg === '--status') { foreach (seed_s05_status() as $k => $v) echo str_pad($k, 8) . ': ' . (is_bool($v) ? ($v ? 'yes' : 'no') : $v) . "\n"; exit(0); }
if ($arg === '--remove') { echo 'Removed ' . seed_s05_remove() . " DEMO-S05 records.\n"; exit(0); }
echo "== DEMO-S05 — Convergence & reconciliation (read-only detectors) ==\n";
$r = seed_s05_load();
foreach ($r['log'] as $l) echo '  ' . $l . "\n";
echo "\n  ┌─ DEMO-S05 DASHBOARD ────────────────────────────────\n";
foreach ($r['dashboard'] as [$label, $ok]) echo '  │ ' . str_pad($label, 56) . ' ' . ($ok ? 'PASS' : 'FAIL') . "\n";
echo '  └─ OVERALL: ' . ($r['allpass'] ? 'ALL PASS' : 'SOME FAIL') . " ─────────────\n";
exit($r['allpass'] ? 0 : 1);
