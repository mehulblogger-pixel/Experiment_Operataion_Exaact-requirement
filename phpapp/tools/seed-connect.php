<?php
// ============================================================================
//  Load (or remove) the CONNECT marketplace scenario data from the command line.
//
//  Complements tools/seed-demo.php (which seeds the ERP world). This one fills
//  the Connect marketplace: every user type and every lifecycle status.
//
//  Usage, from the phpapp directory:
//      php tools/seed-connect.php            load it
//      php tools/seed-connect.php --force    reload even if already loaded
//      php tools/seed-connect.php --remove   take it out again
//      php tools/seed-connect.php --status   report what is there
// ============================================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');

$idx = @file_get_contents(__DIR__ . '/../index.php');
$libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }

$arg = $argv[1] ?? '';
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'Cannot reach the database: ' . $e->getMessage() . "\n"); exit(1); }
function say($s) { echo $s . "\n"; }

if ($arg === '--status') {
    say('Connect seed loaded : ' . (setting_get('connect_seed_v1') ? ('yes (' . setting_get('connect_seed_v1') . ')') : 'no'));
    say('Requirements        : ' . (int)ops_val("SELECT COUNT(*) FROM cx_requirements"));
    say('Vouchers            : ' . (int)ops_val("SELECT COUNT(*) FROM cx_engagement_vouchers"));
    say('Professionals       : ' . (int)ops_val("SELECT COUNT(*) FROM cx_professionals"));
    exit(0);
}
if ($arg === '--remove') { $r = connect_seed_remove(); say('Removed ' . (int)($r['deleted'] ?? 0) . ' records.'); exit(0); }

$t0 = microtime(true);
$r = connect_seed_scenarios($arg === '--force');
if (!empty($r['skipped'])) { say('Already loaded. Use --remove first, or --force to reload.'); exit(0); }

$total = 0; foreach ($r['counts'] as $v) $total += (int)$v;
say(sprintf('Loaded %d Connect records in %.1f seconds.', $total, microtime(true) - $t0));
say('');
say('Counts:'); foreach ($r['counts'] as $k => $v) printf("  %-16s %d\n", $k, $v);
say('');
say('Logins (password: demo12345):');
foreach ($r['logins'] as $l) printf("  %-16s %-30s %-22s %s\n", $l['type'], $l['who'], $l['login'] . ' @ ' . $l['url'], $l['note'] ?? '');
say('');
say('Scenarios covered:');
foreach ($r['scenarios'] as $s) printf("  • %-42s %s\n", $s['what'], $s['detail']);
