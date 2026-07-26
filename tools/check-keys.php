<?php
// ---------------------------------------------------------------------------
//  Duplicate keys in a literal array
//
//  PHP does not warn about ['a'=>1, 'a'=>2]. It quietly keeps the last one.
//  In ops_masters() that is a screen silently pointing at the wrong table: a
//  second 'expense-heads' entry meant the Office expense heads master saved
//  its rows into the voucher expense columns instead. Everything parsed,
//  nothing fataled, and the data went to the wrong place.
//
//  So: walk every literal array in the app and report any string key written
//  twice at the same nesting level in the same array.
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

$bad = 0;
foreach ($files as $path) {
    $t = token_get_all(file_get_contents($path));
    // One frame per open [ or array( — keys seen so far, and where.
    $stack = [];
    $prevSignificant = null;   // to tell an array literal from a subscript

    for ($i = 0, $n = count($t); $i < $n; $i++) {
        $tok = $t[$i];
        if (is_array($tok) && in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;

        // Every bracket and paren pushes a frame so the stack stays balanced —
        // a `)` closing an ordinary function call must not pop an array frame.
        // Only real array literals get a frame that collects keys.
        if ($tok === '[') {
            // `$a['x']` and `foo()['x']` are subscripts, not new arrays.
            $isSubscript = is_string($prevSignificant)
                ? in_array($prevSignificant, [']', ')', '"'], true)
                : ($prevSignificant && in_array($prevSignificant[0], [T_VARIABLE, T_STRING, T_CONSTANT_ENCAPSED_STRING], true));
            $stack[] = $isSubscript ? null : [];
            $prevSignificant = $tok;
            continue;
        }
        if ($tok === '(') {
            $isArray = is_array($prevSignificant) && $prevSignificant[0] === T_ARRAY;
            $stack[] = $isArray ? [] : null;
            $prevSignificant = $tok;
            continue;
        }
        if ($tok === ']' || $tok === ')') {
            if ($stack) array_pop($stack);
            $prevSignificant = $tok;
            continue;
        }
        // A string immediately followed by => is a key in the innermost array.
        if (is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING) {
            $j = $i + 1;
            while ($j < $n && is_array($t[$j]) && in_array($t[$j][0], [T_WHITESPACE, T_COMMENT], true)) $j++;
            if ($j < $n && is_array($t[$j]) && $t[$j][0] === T_DOUBLE_ARROW && $stack) {
                $top = count($stack) - 1;
                if (is_array($stack[$top])) {
                    $key = trim($tok[1], "'\"");
                    if (isset($stack[$top][$key])) {
                        $rel = str_replace($root . '/', '', $path);
                        echo "DUPLICATE key '$key' in the same array — $rel:{$stack[$top][$key]} and :{$tok[2]}\n";
                        $bad++;
                    } else {
                        $stack[$top][$key] = $tok[2];
                    }
                }
            }
        }
        $prevSignificant = $tok;
    }
}

if ($bad) {
    echo "\n$bad duplicate array key(s). PHP keeps the LAST one and says nothing, so the\n";
    echo "first entry is dead code — or worse, a screen wired to the wrong table.\n";
    exit(1);
}
echo 'OK - ' . count($files) . " PHP files, no array key written twice in the same array.\n";
