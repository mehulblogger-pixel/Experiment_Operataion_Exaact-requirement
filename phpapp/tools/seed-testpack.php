<?php
// ============================================================================
//  TEST PACK — command line. The logic lives in lib/seed_testpack.php (and the
//  group files lib/seed_testpack_*.php), so this and the Admin → System
//  Settings button run the SAME code.
//
//  TEST COPY ONLY. On any site whose config.local.php does not say test_copy,
//  this refuses and changes nothing.
//
//  Usage (from phpapp/):
//    php tools/seed-testpack.php            load (already loaded: adds nothing)
//    php tools/seed-testpack.php --fresh    remove the pack, then load it again
//    php tools/seed-testpack.php --remove   remove the pack (only its own records)
//    php tools/seed-testpack.php --status   what is loaded, which logins are missing
// ============================================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php');
$libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

// Refuse before anything else is looked at.
if (!function_exists('app_is_test_copy') || !app_is_test_copy()) {
    fwrite(STDERR, "Refused: this is not the test copy. The test pack only runs where config.local.php sets test_copy.\n");
    exit(2);
}
// Act as the Master Admin, exactly as the button does (the engine asks again).
$suid = (int) (ops_val("SELECT id FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1") ?: 0);
if ($suid) { $_SESSION['uid'] = $suid; current_user(true); ua(true); }

$arg = $argv[1] ?? '';
$show = function (array $s) {
    echo 'Test pack loaded : ' . ($s['loaded'] ? 'yes (' . $s['loaded_at'] . ')' : 'no') . "\n";
    echo 'Records          : ' . array_sum($s['counts'] ?? []) . "\n";
    foreach (($s['counts'] ?? []) as $t => $n) echo '   ' . str_pad($t, 22) . $n . "\n";
    echo 'Tests mapped     : ' . (int) ($s['tests'] ?? 0) . "\n";
    echo 'Groups           : ' . implode(', ', array_keys($s['groups'] ?? [])) . "\n";
    echo 'Missing logins   : ' . (($s['missing_logins'] ?? []) ? implode(', ', $s['missing_logins']) : 'none') . "\n";
};
if ($arg === '--status') { $show(tp_status()); exit(0); }
if ($arg === '--remove') {
    $r = tp_remove();
    if (empty($r['ok'])) { fwrite(STDERR, 'Refused: ' . ($r['error'] ?? '') . "\n"); exit(2); }
    echo 'Removed ' . (int) $r['deleted'] . " test-pack records.\n"; exit(0);
}
$r = tp_load($arg === '--fresh');
if (!empty($r['error'])) { fwrite(STDERR, 'Refused: ' . $r['error'] . "\n"); exit(2); }
echo !empty($r['already']) ? "Already loaded — nothing added (use --fresh to start again).\n" : "Loaded.\n";
foreach (($r['errors'] ?? []) as $k => $m) echo "Group $k failed: $m\n";
$show($r);
exit(empty($r['errors']) ? 0 : 1);
