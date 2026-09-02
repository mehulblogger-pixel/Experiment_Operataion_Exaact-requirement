<?php
// ============================================================================
//  CANDIDATE POOL CONVERGENCE — one person, two pools (revamp target P11). The
//  same human can sit in both the recruitment pool (candidates) and the
//  marketplace pool (cx_professionals). This reports, READ-ONLY, every candidate
//  that is also a known marketplace professional — matched by the same mobile /
//  e-mail / name keys the app already dedupes on. It merges nothing, deletes
//  nothing and moves no figure; each pool keeps its own record.
//
//    php tools/candidate-pool.php            # summary
//    php tools/candidate-pool.php --list     # also list each overlapping person
// ============================================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php'); $libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

$showList = in_array('--list', $argv, true);

echo "== CANDIDATE POOL CONVERGENCE — candidate ⇄ marketplace professional ==\n\n";
$s = candpool_summary();
printf("  %-24s %10s\n", 'Recruitment pool', $s['candidates']);
printf("  %-24s %10s\n", 'Marketplace pool', $s['professionals']);
echo "  " . str_repeat('─', 36) . "\n";
printf("  %-24s %10s\n", 'Overlapping people', $s['overlap']);
printf("  %-24s %10s\n", '  by mobile', $s['by_mobile']);
printf("  %-24s %10s\n", '  by e-mail', $s['by_email']);
printf("  %-24s %10s\n", '  by name (soft)', $s['by_name']);

if ($showList) {
    $rows = candpool_scan(1000);
    if ($rows) {
        echo "\n  Overlapping people:\n";
        printf("  %-14s %-22s %-22s %-10s\n", 'Candidate', 'Name', 'Professional', 'Matched');
        echo "  " . str_repeat('─', 72) . "\n";
        foreach ($rows as $r)
            printf("  %-14s %-22s %-22s %-10s\n", substr($r['cand_code'], 0, 14),
                   substr($r['cand_name'], 0, 22), substr($r['pro_name'] ?: ('#' . $r['pro_id']), 0, 22), $r['reason']);
    }
}

echo "\n  " . ($s['overlap'] === 0
    ? 'No overlap — the two pools contain different people.'
    : $s['overlap'] . ' person(s) exist in BOTH pools. Read-only — a recruiter can now see the person is already on the bench; nothing is merged.') . "\n";
exit(0);
