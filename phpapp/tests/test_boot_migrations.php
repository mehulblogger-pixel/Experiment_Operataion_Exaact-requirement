<?php
// Guard: every migration that CREATES a table must be wired into boot()'s
// migrate chain (lib/db.php). When it is not, the table is only created if some
// request happens to hit a code path that self-migrates — and on a fresh MySQL
// install a screen that queries it first crashes with "table doesn't exist"
// (this is exactly what hit issued_licences and custom_records in production).
// This test scans the source and fails if any table-migration is left out, so
// the whole class of bug cannot come back.
t_section('Boot wires every table migration');

$libDir = __DIR__ . '/../lib';
$dbSrc  = (string)file_get_contents($libDir . '/db.php');

// Which migrate functions actually create a table? Scan each lib file: for every
// `function xxx_migrate(` that is followed (within the file) by a CREATE TABLE,
// record it. A migrate that only adds columns to an existing table is exempt.
$tableMigrates = [];
foreach (glob($libDir . '/*.php') as $f) {
    $src = (string)file_get_contents($f);
    if (!preg_match_all('/function\s+([a-z_]+_migrate)\s*\(/i', $src, $m, PREG_OFFSET_CAPTURE)) continue;
    foreach ($m[1] as $i => $hit) {
        $name  = $hit[0];
        $start = $m[0][$i][1];
        // Body runs to the next "function " at column 0, or end of file.
        $next  = strpos($src, "\nfunction ", $start + 1);
        $body  = substr($src, $start, $next === false ? null : $next - $start);
        if (stripos($body, 'CREATE TABLE') !== false) $tableMigrates[$name] = true;
    }
}
$tableMigrates = array_keys($tableMigrates);
t_ok(count($tableMigrates) > 30, 'found the table-creating migrations (' . count($tableMigrates) . ')');

// Which migrations does boot() call? (both direct calls and function_exists guards)
preg_match_all('/([a-z_]+_migrate)\s*\(\s*\)/i', $dbSrc, $bm);
$booted = array_unique($bm[1]);

// Every table-creating migration must be among them.
$missing = array_values(array_diff($tableMigrates, $booted));
t_ok(empty($missing), 'every table-creating migration is wired into boot()'
    . (empty($missing) ? '' : ' — MISSING: ' . implode(', ', $missing)));

// Name the specific ones that regressed in production, so the guard is explicit.
foreach (['licissue_migrate', 'cforms_migrate', 'form_tokens_migrate', 'login_attempts_migrate', 'billing_migrate'] as $must)
    t_ok(in_array($must, $booted, true), "boot() calls $must");
