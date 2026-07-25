<?php
// ---------------------------------------------------------------------------
//  Duplicate function declarations
//
//  PHP only complains at the moment BOTH files are loaded, which for a view is
//  the moment somebody opens that one screen. `php -l` never sees it: each file
//  parses perfectly on its own. That is how views/detail.php shipped with its
//  own fdate(), and the client and vendor pages died with "Cannot redeclare"
//  while every other screen was fine.
//
//  So: collect every unconditional function declaration across the app and
//  report any name declared in more than one file. Declarations guarded by
//  function_exists() are legitimate fallbacks and are skipped.
// ---------------------------------------------------------------------------
$root = dirname(__DIR__);
$skip = ['/vendor/', '/node_modules/', '/data/'];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
    $path = $f->getPathname();
    foreach ($skip as $s) if (strpos($path, $s) !== false) continue 2;
    $files[] = $path;
}
sort($files);

$seen = [];   // name => [ [file, line], ... ]
foreach ($files as $path) {
    $src = file_get_contents($path);
    $t = token_get_all($src);
    $guarded = false;
    for ($i = 0, $n = count($t); $i < $n; $i++) {
        $tok = $t[$i];
        if (is_array($tok) && $tok[0] === T_STRING && strcasecmp($tok[1], 'function_exists') === 0) {
            // A fallback declaration: if (!function_exists('x')) { function x(){} }
            $guarded = true;
            continue;
        }
        if (!is_array($tok) || $tok[0] !== T_FUNCTION) continue;
        // Look ahead for the name; an anonymous function or an arrow fn has none.
        $j = $i + 1;
        while ($j < $n && is_array($t[$j]) && in_array($t[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) $j++;
        if ($j >= $n || !is_array($t[$j]) || $t[$j][0] !== T_STRING) continue;   // closure
        $name = $t[$j][1];
        // Methods live in a class and never clash globally.
        $isMethod = false;
        for ($k = $i - 1; $k >= 0 && $k > $i - 8; $k--) {
            if (!is_array($t[$k])) continue;
            if (in_array($t[$k][0], [T_PUBLIC, T_PRIVATE, T_PROTECTED, T_STATIC, T_ABSTRACT, T_FINAL], true)) { $isMethod = true; break; }
        }
        if ($isMethod) { continue; }
        if ($guarded) { $guarded = false; continue; }
        $line = $tok[2];
        $seen[strtolower($name)][] = [str_replace($root . '/', '', $path), $line, $name];
    }
}

$bad = 0;
foreach ($seen as $lc => $places) {
    if (count($places) < 2) continue;
    // Two declarations in the SAME file is a plain redeclare and php -l finds it;
    // the dangerous case is the same name in two files that get loaded together.
    $filesFor = array_unique(array_map(fn($p) => $p[0], $places));
    if (count($filesFor) < 2) continue;
    $bad++;
    echo "DUPLICATE function {$places[0][2]}() declared in " . count($filesFor) . " files:\n";
    foreach ($places as [$file, $line, $name]) echo "    $file:$line\n";
}

if ($bad) {
    echo "\n$bad duplicate function name(s). PHP fatals with \"Cannot redeclare\" the moment\n";
    echo "both files are loaded — for a view, that is the moment somebody opens that screen.\n";
    exit(1);
}
echo 'OK - ' . count($files) . " PHP files, no function declared in two places.\n";
