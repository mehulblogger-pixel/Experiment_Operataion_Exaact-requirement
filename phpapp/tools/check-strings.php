<?php
/**
 * Find PHP tags that have been pasted INSIDE a PHP string literal.
 *
 * Why this exists: a find/replace sweep across the codebase (for example a
 * terminology rename) can rewrite a word that happens to sit inside a quoted
 * string — a SQL table name, an e-mail body, a CSS selector — and drop a
 * `<?= ... ?>` into it. The file still parses perfectly, so `php -l` reports
 * nothing, and the damage only shows up at runtime as a SQL syntax error or a
 * literal `<?= e(T('x')) ?>` printed to the user.
 *
 * The check tokenises each file and looks at string tokens only, so it cannot
 * be fooled by a legitimate `<?php` appearing as markup outside PHP.
 *
 * Usage:  php tools/check-strings.php        (run from the phpapp directory)
 * Exit 0 = clean, exit 1 = at least one string carries a PHP tag.
 */

$root = dirname(__DIR__);
$bad = 0;
$scanned = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$files = [];
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    if (strpos($f->getPathname(), DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR) !== false) continue;
    $files[] = $f->getPathname();
}
sort($files);

// Token types that hold string content. T_INLINE_HTML is deliberately excluded:
// that is markup outside PHP, where a `<?` is the start of a real tag.
$stringTokens = [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE];
if (defined('T_HEREDOC')) $stringTokens[] = T_HEREDOC;

foreach ($files as $path) {
    $src = file_get_contents($path);
    if ($src === false) continue;
    $scanned++;
    $tokens = @token_get_all($src);
    if (!$tokens) continue;
    foreach ($tokens as $t) {
        if (!is_array($t)) continue;
        if (!in_array($t[0], $stringTokens, true)) continue;
        // Only real PHP open tags. `<?xml` in a generated document and a bare
        // `<?` from a comparison against a bound placeholder (`due_date<?`)
        // are both legitimate and must not be reported.
        if (!preg_match('/<\?=|<\?php\b/', $t[1])) continue;
        $rel = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
        $snippet = trim(preg_replace('/\s+/', ' ', $t[1]));
        if (strlen($snippet) > 120) $snippet = substr($snippet, 0, 117) . '...';
        echo "PHP TAG INSIDE A STRING: $rel:{$t[2]}\n    $snippet\n";
        $bad++;
    }
}

if ($bad === 0) {
    echo "OK - $scanned PHP files, no PHP tags found inside string literals.\n";
    exit(0);
}
echo "\nFAILED - $bad string(s) carry a PHP tag. These parse fine but break at runtime.\n";
exit(1);
