<?php
// ============================================================================
//  A LINE COMMENT MUST NOT END PHP MODE.
//
//  PHP ends a // or # comment at a close tag, not only at the newline. So a
//  comment that quotes one — while explaining an echo tag, or documenting a
//  regular expression — silently ENDS PHP MODE, and the rest of that comment,
//  plus everything below it in the file, becomes literal output.
//
//  Why this earns a permanent test rather than care:
//    * In a VIEW it prints the rest of the template's source, queries included,
//      to the browser. That is information disclosure, not a typo.
//    * In a LIBRARY every function below the comment stops existing, so the
//      failure surfaces somewhere else entirely as "undefined function".
//    * It never looks wrong, and php -l passes: the file IS valid PHP. It just
//      does something completely different from what it reads like.
//
//  This trap was hit three times while writing one feature. Once is a typo;
//  three times is a missing test.
//
//  The rule: describe a close tag in words ("an echo tag"), or put it in a
//  block comment or a string, where it is harmless.
// ============================================================================

t_section('No line comment ends PHP mode');

/**
 * Every offending line in one file, as [line, description].
 *
 * Detection, not a text search. A line comment normally swallows its own
 * newline, so the token after it belongs to the next line. When the token
 * immediately after a line comment is a CLOSE TAG, a close tag must have sat
 * inside that comment and terminated it early.
 *
 * That shape alone is NOT the bug — and this is what the first draft of this
 * test got wrong, flagging 12 innocent files. Templates throughout this repo
 * write a comment and then close PHP on the same line, with the close tag last:
 * ending PHP mode there is precisely what the author meant, and nothing is lost
 * because nothing followed it.
 *
 * It is a bug only when TEXT FOLLOWS the close tag on the same line: the author
 * was still writing prose, and that prose — and the whole rest of the file — is
 * now output. So the test is: a close tag inside a line comment, AND something
 * other than whitespace after it before the newline.
 */
function t_close_tag_offenders($file) {
    $src = @file_get_contents($file);
    if ($src === false || $src === '') return [];
    $toks = @token_get_all($src);
    if (!is_array($toks)) return [];
    $out = [];
    for ($i = 0, $n = count($toks); $i < $n; $i++) {
        $t = $toks[$i];
        if (!is_array($t) || $t[0] !== T_COMMENT) continue;
        if (substr($t[1], 0, 2) === '/*') continue;              // block comment: safe
        $nx = $toks[$i + 1] ?? null;
        $isClose = is_array($nx) ? ($nx[0] === T_CLOSE_TAG) : ($nx === '?' . '>');
        if (!$isClose) continue;
        //  THE DISCRIMINATOR. A close tag token swallows the one newline that
        //  follows it, so its own text ends in "\n" when it sits at the end of a
        //  line — the intended form. A close tag with text still to come on the
        //  same line has no newline in its token, and that text is the leak.
        $closeTxt = is_array($nx) ? $nx[1] : (string) $nx;
        if (strpos($closeTxt, "\n") !== false) continue;          // intended: line ended here
        $after = $toks[$i + 2] ?? null;
        $tail  = $after === null ? '' : (is_array($after) ? $after[1] : (string) $after);
        $leak  = trim(explode("\n", $tail)[0]);
        if ($leak === '') continue;                                // close tag at end of file
        $out[] = [$t[2], trim($t[1]) . '  →  now printed to the page: "' . substr($leak, 0, 60) . '"'];
    }
    return $out;
}

// ---------------------------------------------------------------------------
//  ARMING — the detector must catch a planted offender, and must clear every
//  innocent shape. Without both halves a green sweep below proves nothing.
// ---------------------------------------------------------------------------
$ctDir = sys_get_temp_dir() . '/exaact_ct_' . getmypid();
@mkdir($ctDir, 0777, true);
$CT = '?' . '>';          // built by concatenation so THIS file is never an offender
$ctWrite = function ($name, $body) use ($ctDir) {
    $p = $ctDir . '/' . $name; file_put_contents($p, $body); return $p;
};

//  1 · the real bug: prose continues after the close tag.
$ctBad = $ctWrite('bad.php',
    "<?php\n\$a = 1;\n//  explaining an echo tag like <?= \$x " . $CT . " ends PHP mode right here\n"
  . "function ct_never_defined() { return 1; }\n");
$hits = t_close_tag_offenders($ctBad);
t_eq(count($hits), 1, 'ARMING · the detector catches a planted offender');
t_eq($hits[0][0] ?? 0, 3, 'ARMING · and reports the line it is on');
//  Prove the planted file really is broken, and really is valid PHP — which is
//  exactly why nothing else catches it.
t_ok(strpos((string) shell_exec('php -l ' . escapeshellarg($ctBad) . ' 2>&1'), 'No syntax errors') !== false,
     'ARMING · php -l calls the broken file clean, so linting would never find this');
t_ok(strpos((string) shell_exec('php ' . escapeshellarg($ctBad) . ' 2>&1'), 'ends PHP mode right here') !== false,
     'ARMING · and running it really does print the comment as page output');

//  2 · every innocent shape, in one file, must be silent.
$ctGood = $ctWrite('good.php',
    "<?php\n"
  . "\$re = '/<\\?.*?\\" . $CT . "/s';        // a close tag inside a STRING is harmless\n"
  . "/*  and inside a block comment " . $CT . " it is harmless too  */\n"
  . "\$b = 2;\n"
  . "if (\$b) { " . $CT . "\n"
  . "  <p>html</p>\n"
  . "  <?php // the idiomatic form: comment, then close, nothing after it " . $CT . "\n"
  . "  <p>more html</p>\n"
  . "  <?php }\n");
t_eq(t_close_tag_offenders($ctGood), [],
     'ARMING · a close tag in a string, in a block comment, and the idiomatic '
   . '"comment then close, nothing after" form are all left alone');
array_map('unlink', glob($ctDir . '/*.php')); @rmdir($ctDir);

// ---------------------------------------------------------------------------
//  THE SWEEP — every PHP file that ships.
// ---------------------------------------------------------------------------
t_nothrow('no shipped PHP file ends PHP mode inside a comment', function () {
    $root = dirname(__DIR__);
    $skip = ['/vendor/', '/node_modules/', '/.git/', '/data/', '/uploads/', '/storage/'];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $scanned = 0; $bad = [];
    foreach ($it as $f) {
        if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
        $path = str_replace('\\', '/', $f->getPathname());
        foreach ($skip as $s) if (strpos($path, $s) !== false) continue 2;
        $scanned++;
        foreach (t_close_tag_offenders($path) as [$ln, $why])
            $bad[] = substr($path, strlen($root) + 1) . ':' . $ln . '  ' . $why;
    }
    t_ok($scanned > 300, "ARMING · $scanned PHP files were scanned");
    t_ok($bad === [], $bad === []
        ? "no line comment ends PHP mode ($scanned files scanned)"
        : count($bad) . ' place(s) end PHP mode inside a comment:' . "\n      " . implode("\n      ", $bad));
});
