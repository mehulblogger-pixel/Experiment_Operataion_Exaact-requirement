<?php
// A12-M static scan: which functions can (transitively) issue DDL, and which transaction bodies call them.
$lib = $argv[1];
$bodies = [];
foreach (glob("$lib/*.php") as $f) {
    $src = file_get_contents($f);
    if (preg_match_all('/^function\s+([a-zA-Z0-9_]+)\s*\(/m', $src, $m, PREG_OFFSET_CAPTURE)) {
        $n = count($m[1]);
        for ($i = 0; $i < $n; $i++) {
            $start = $m[0][$i][1]; $end = $i + 1 < $n ? $m[0][$i + 1][1] : strlen($src);
            $bodies[$m[1][$i][0]] = [basename($f), substr($src, $start, $end - $start)];
        }
    }
}
$ddl = [];
foreach ($bodies as $fn => [$file, $b]) {
    if (preg_match('/(CREATE TABLE|ALTER TABLE|CREATE INDEX|CREATE UNIQUE INDEX|DROP TABLE)/i', $b)) $ddl[$fn] = 'direct';
}
// transitive closure
$calls = [];
foreach ($bodies as $fn => [$file, $b]) {
    preg_match_all('/\b([a-z][a-z0-9_]+)\s*\(/', $b, $mm);
    $calls[$fn] = array_values(array_unique(array_filter($mm[1], fn($c) => isset($bodies[$c]) && $c !== $fn)));
}
do { $changed = false;
    foreach ($calls as $fn => $cs) { if (isset($ddl[$fn])) continue;
        foreach ($cs as $c) if (isset($ddl[$c])) { $ddl[$fn] = "via $c"; $changed = true; break; } }
} while ($changed);
// transaction bodies
foreach ($bodies as $fn => [$file, $b]) {
    if (!preg_match_all('/beginTransaction\(\)/', $b, $x, PREG_OFFSET_CAPTURE)) continue;
    foreach ($x[0] as [$t, $off]) {
        $rest = substr($b, $off);
        $cpos = strpos($rest, 'commit()'); $seg = $cpos === false ? $rest : substr($rest, 0, $cpos);
        preg_match_all('/\b([a-z][a-z0-9_]+)\s*\(/', $seg, $mm);
        $hits = [];
        foreach (array_unique($mm[1]) as $c) if (isset($ddl[$c])) $hits[] = "$c [" . $ddl[$c] . "]";
        echo "$file :: $fn :: " . ($hits ? implode('; ', $hits) : '(no DDL-reaching call)') . "\n";
    }
}
