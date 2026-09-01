<?php
// ============================================================================
//  ENGAGEMENT PARITY — the reconciliation gate for the first-class Engagement
//  entity (revamp target §3, design law: switch a reader only AFTER parity is
//  proven). The entity already exists (engagements + a nullable engagement_id on
//  calls/jobs/quotations/invoices, dual-read). This runs the idempotent backfill
//  then reports whether every contract_number-threaded record now also carries a
//  matching engagement_id. It reads a live DB read-only apart from the backfill's
//  additive stamping (never drops or rewrites contract_number).
//
//    php tools/engagement-parity.php            # against the configured DB
//    php tools/engagement-parity.php --no-backfill   # report only, no stamping
// ============================================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php'); $libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

$doBackfill = !in_array('--no-backfill', $argv, true);

echo "== ENGAGEMENT PARITY — contract_number ⇄ engagement_id ==\n\n";
if ($doBackfill) {
    $bf = engagement_backfill();
    echo "  Backfill: {$bf['engagements']} engagement(s) created, {$bf['stamped']} record(s) stamped.\n\n";
}
$p = engagement_parity();
printf("  %-12s %10s %10s %12s\n", 'Table', 'Threaded', 'Unstamped', 'Mismatched');
echo "  " . str_repeat('─', 48) . "\n";
foreach ($p['by_table'] as $t => $c)
    printf("  %-12s %10d %10d %12d\n", $t, $c['threaded'], $c['unstamped'], $c['mismatched']);
echo "  " . str_repeat('─', 48) . "\n";
printf("  %-12s %10d %10d %12d\n", 'TOTAL', $p['threaded'], $p['unstamped'], $p['mismatched']);

echo "\n  " . ($p['in_parity']
    ? '✓ IN PARITY — every threaded record carries a matching engagement_id; a reader may switch to engagement_id.'
    : '✗ NOT IN PARITY — ' . $p['unstamped'] . ' unstamped, ' . $p['mismatched'] . ' mismatched. Keep dual-reading (contract_number authoritative).') . "\n";
exit($p['in_parity'] ? 0 : 1);
