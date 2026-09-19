<?php
// DEMO-S02 — Agency scenario seed (CLI wrapper over lib/seed_scenario_s02.php).
//   php tools/seed-scenario-s02.php            load / refresh (idempotent)
//   php tools/seed-scenario-s02.php --remove   remove all DEMO-S02 data
//   php tools/seed-scenario-s02.php --status    report what exists
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php'); $libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

// Phase 6 · Batch 1 — act as the Master Admin, exactly as the admin button does.
// The scenario writes identity relationships, and those writers now ask their own
// authority question rather than trusting whoever called them. Without an actor
// there is no authority, and the seed would quietly produce a scenario missing
// its links. The in-app route is already master-only; this says the same thing
// on the command line.
$suid = (int) (ops_val("SELECT id FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1") ?: 0);
if ($suid) { $_SESSION['uid'] = $suid; current_user(true); ua(true); }

$arg = $argv[1] ?? '';
if ($arg === '--status') { $s = seed_s02_status(); foreach ($s as $k => $v) echo str_pad($k, 8) . ': ' . (is_bool($v) ? ($v ? 'yes' : 'no') : $v) . "\n"; exit(0); }
if ($arg === '--remove') { echo 'Removed ' . seed_s02_remove() . " DEMO-S02 records.\n"; exit(0); }

echo "== DEMO-S02 — Apex Agency / Bench → Requirement → Job → Report ==\n";
$r = seed_s02_load();
foreach ($r['log'] as $l) echo '  ' . $l . "\n";
$c = $r['commercial'];
echo "\n  Commercial (staff-only): Amit margin ₹" . number_format($c['amit']['margin']) . " · Rohit ₹" . number_format($c['rohit']['margin'])
   . " · TOTAL rev ₹" . number_format($c['total']['rev']) . " / cost ₹" . number_format($c['total']['cost']) . " / margin ₹" . number_format($c['total']['margin']) . "\n";
echo "\n  ┌─ DEMO-S02 SCENARIO DASHBOARD ────────────────────────────\n";
foreach ($r['dashboard'] as [$label, $ok]) echo '  │ ' . str_pad($label, 42) . ' ' . ($ok ? 'PASS' : 'FAIL') . "\n";
echo '  └─ OVERALL: ' . ($r['allpass'] ? 'ALL PASS' : 'SOME FAIL') . " ─────────────────\n";
