<?php
// ============================================================================
//  COST RECONCILIATION — the cost-side twin of the revenue reconciliation gate
//  (revamp target P8, design law: never trust one copy over the other until they
//  are proven to agree). A job's sub-contractor cost is written twice: the legacy
//  figure typed on the job (jobs.subcon_cost) and the SUBCON row a committed cost
//  run allocates to the ledger (cost_allocations). This reports, per job, whether
//  those two copies agree. It reads the live DB READ-ONLY — it writes nothing,
//  moves no figure and switches no reader.
//
//    php tools/cost-reconciliation.php           # against the configured DB
//    php tools/cost-reconciliation.php --list     # also list every diverging job
// ============================================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php'); $libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

$showList = in_array('--list', $argv, true);
$fm = fn($n) => number_format((float)$n, 2);

echo "== COST RECONCILIATION — job subcon_cost ⇄ committed cost ledger ==\n\n";
$s = costrecon_summary();
printf("  %-22s %12s\n", 'Candidate jobs', $s['candidates']);
printf("  %-22s %12s\n", 'Reconciled', $s['reconciled']);
printf("  %-22s %12s\n", 'Diverging', $s['diverging']);
printf("  %-22s %12s\n", 'Legacy-only', $s['legacy_only']);
printf("  %-22s %12s\n", 'Ledger-only', $s['ledger_only']);
echo "  " . str_repeat('─', 36) . "\n";
printf("  %-22s %12s\n", 'Legacy cost total', $fm($s['legacy_total']));
printf("  %-22s %12s\n", 'Ledger cost total', $fm($s['ledger_total']));

if ($showList) {
    $rows = costrecon_list(500);
    if ($rows) {
        echo "\n  Diverging jobs:\n";
        printf("  %-16s %14s %14s %14s\n", 'Job', 'Legacy', 'Ledger', 'Delta');
        echo "  " . str_repeat('─', 60) . "\n";
        foreach ($rows as $r)
            printf("  %-16s %14s %14s %14s\n", substr($r['job_code'], 0, 16),
                   $fm($r['legacy']), $fm($r['ledger']), $fm($r['legacy'] - $r['ledger']));
    }
}

echo "\n  " . ($s['green']
    ? '✓ GREEN — every job\'s sub-contractor cost matches the committed cost ledger. Both copies agree.'
    : '✗ NOT GREEN — ' . $s['diverging'] . ' job(s) disagree (' . $s['legacy_only'] . ' legacy-only, '
      . $s['ledger_only'] . ' ledger-only). Re-run the month\'s cost run or correct the job. Read-only until then.') . "\n";
exit($s['green'] ? 0 : 1);
