<?php
// ---------------------------------------------------------------------------
//  GATE 5 — existing-data audit for workforce activation.
//
//  Answers the five questions that must be answered BEFORE any existing record
//  is touched, and changes NOTHING unless you ask it to:
//
//      php tools/g5-data-audit.php              ← read-only report (do this first)
//      php tools/g5-data-audit.php --apply      ← correct the safe rows only
//
//  Run the report, read it, and only then decide. The --apply pass can only ever
//  move somebody from "Active" to "Joining pending", and only when ALL of these
//  hold: they came from a recruitment hire, they have no joining date, and there
//  is no record of them ever having worked. Anybody who HAS worked is left alone
//  and listed for a human to backfill the date — demoting a working colleague
//  would be a worse mistake than the one being corrected.
//
//  People added directly through the Masters door are never touched at all.
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php');
$libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

$apply = in_array('--apply', $argv, true);
$line  = str_repeat('-', 74);

echo "\n", $line, "\n  GATE 5 — WORKFORCE ACTIVATION: EXISTING DATA\n", $line, "\n";
echo "  Database driver : ", db_driver(), "\n";
echo "  Mode            : ", $apply ? "APPLY (safe rows will be corrected)" : "REPORT ONLY (nothing will change)", "\n\n";

$s = wf_joining_survey();
if (($s['recruited'] ?? -1) < 0) {
    fwrite(STDERR, "Could not read the inspectors/candidates tables — STOPPING without changing anything.\n");
    exit(3);
}

printf("  %-52s %6d\n", 'Team members created from a recruitment hire',      $s['recruited']);
printf("  %-52s %6d\n", '  …of those, currently Active',                    $s['recruited_active']);
printf("  %-52s %6d\n", '  …already correctly Joining pending',             $s['already_pending']);
printf("  %-52s %6d\n", 'Active people added directly (never touched)',     $s['direct_active']);
echo "\n";
printf("  %-52s %6d\n", 'SAFE TO CORRECT (no joining date, never worked)',  $s['reclassify']);
printf("  %-52s %6d\n", 'AMBIGUOUS — has worked but no joining date',       $s['ambiguous']);
echo "\n";

if ($s['ids_reclassify']) {
    echo "  Safe to correct — Active today, but never started:\n";
    foreach ($s['ids_reclassify'] as $r)
        printf("    #%-5d %-28s %-10s  candidate %s\n", (int)$r['id'], substr((string)$r['name'], 0, 28),
               (string)$r['emp_code'], (string)($r['cand_code'] ?: ('#' . (int)$r['cand_id'])));
    echo "\n";
}
if ($s['ids_ambiguous']) {
    echo "  AMBIGUOUS — these people HAVE worked, so they plainly started; what is\n";
    echo "  missing is the joining DATE. They are deliberately NOT changed. Record\n";
    echo "  the real joining date on each candidate record instead:\n";
    foreach ($s['ids_ambiguous'] as $r)
        printf("    #%-5d %-28s %-10s  candidate %s\n", (int)$r['id'], substr((string)$r['name'], 0, 28),
               (string)$r['emp_code'], (string)($r['cand_code'] ?: ('#' . (int)$r['cand_id'])));
    echo "\n";
}

if (!$apply) {
    echo $line, "\n";
    echo $s['reclassify'] > 0
        ? "  Nothing has been changed. Re-run with --apply to correct the " . $s['reclassify'] . " safe row(s).\n"
        : "  Nothing to correct. No changes are needed on this database.\n";
    if ($s['ambiguous'] > 0)
        echo "  The " . $s['ambiguous'] . " ambiguous row(s) above are never corrected automatically — they need\n"
           . "  a real joining date, which only a person can supply.\n";
    echo $line, "\n\n";
    exit(0);
}

$r = wf_joining_migrate(true);
echo $line, "\n";
echo "  Corrected to Joining pending : ", (int)$r['applied'], "\n";
echo "  Left for human review        : ", (int)$r['skipped_ambiguous'], "\n";
echo "  Recorded in the configuration ledger as wf_joining_migrated_at.\n";
echo $line, "\n\n";
