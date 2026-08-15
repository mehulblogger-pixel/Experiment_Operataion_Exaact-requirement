<?php
// ============================================================================
//  Load (or remove) the demo data from the command line.
//
//  Why this exists: the seed writes a few thousand rows, and over a network to
//  MySQL that is a few thousand round trips. Shared hosting stops a web page at
//  30 seconds, so on a slow server "Load demo data" could come back with
//  nothing and no explanation. The command line has no such limit.
//
//  Usage, from the phpapp directory:
//      php tools/seed-demo.php            load it
//      php tools/seed-demo.php --force    load it even if the flag says loaded
//      php tools/seed-demo.php --remove   take it out again
//      php tools/seed-demo.php --status   say what is there without changing it
//
//  On MilesWeb this is cPanel → Terminal, or a one-off cron command.
// ============================================================================

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }

chdir(__DIR__ . '/..');
// Load exactly the library files the web front controller loads, in its order,
// by reading index.php's own require list. A hand-kept list here fell behind —
// it did not load seed_demo_c.php (all the part-three modules — CRM funnel,
// recruitment, project costing, the compliance registers), nor the lazy-table
// migrations (confidentiality, custom forms) — so a command-line seed quietly
// skipped them and left those registers empty. Deriving the list from index.php
// keeps the command line identical to the button, now and after any new module.
$idx = @file_get_contents(__DIR__ . '/../index.php');
$libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) {
    $libs = $mm[1];
}
// Fallback to a minimal core if index.php could not be read for any reason.
if (!$libs) foreach (['db','helpers','ops','lookups','licence','access','terms','compliance',
                      'confidentiality','customforms','crm','costing','seed_demo','seed_demo_c'] as $m)
    $libs[] = "/lib/$m.php";
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }

$arg = $argv[1] ?? '';

try { boot(); }
catch (Throwable $e) { fwrite(STDERR, "Cannot reach the database: " . $e->getMessage() . "\n"); exit(1); }

function say($s) { echo $s . "\n"; }

if ($arg === '--status') {
    say('Flag says loaded : ' . (demo_seeded() ? 'yes' : 'no'));
    say('Flag is stale    : ' . (demo_flag_is_stale() ? 'YES — loaded flag, no data behind it' : 'no'));
    foreach (demo_coverage() as $label => $c)
        printf("  %-34s %s\n", $label,
            $c['state'] === 'ok' ? $c['rows'] . ' rows'
                : ($c['state'] === 'missing' ? 'TABLE NOT BUILT (' . $c['table'] . ')' : 'NO DEMO DATA'));
    exit(0);
}

if ($arg === '--remove') {
    $r = seed_demo_remove();
    if (!empty($r['error'])) { fwrite(STDERR, 'Could not remove: ' . $r['error'] . "\n"); exit(1); }
    say('Removed ' . (int)($r['deleted'] ?? 0) . ' records.');
    exit(0);
}

$t0 = microtime(true);
$r = seed_demo($arg === '--force');
if (!empty($r['skipped'])) {
    say('Already loaded. Use --remove first, or --force to load anyway.');
    exit(0);
}
if (!empty($r['error'])) { fwrite(STDERR, 'FAILED: ' . $r['error'] . "\n"); exit(1); }

$total = 0;
foreach ($r['counts'] as $k => $v) if (is_numeric($v)) $total += (int)$v;
say(sprintf('Loaded %d records in %.1f seconds.', $total, microtime(true) - $t0));

// The pack is added after the core commits, so a register that could not fill
// is named rather than silently missing.
foreach ($r['failed'] ?? [] as $f) say('  NOT LOADED: ' . $f);

$gaps = demo_coverage_gaps();
if ($gaps) {
    say('Modules still showing no demo data:');
    foreach ($gaps as $g) say('  - ' . $g);
} else {
    say('Every module has demo data.');
}
say('Sign in as admin, or as director / account / insp.ravi with password demo12345.');
