<?php
// App-wide static safety net: every library and every view must parse.
// The full app already boots in bootstrap.php (so a lib parse/҂runtime error
// would have failed there); this additionally lints every VIEW file — which the
// app loads only when a page is rendered, so a syntax error in one hides until
// somebody opens that screen (exactly the class of bug behind the nav breakage).
t_section('every library and view parses (app-wide)');

$root = dirname(__DIR__);

$walk = function ($dir) use (&$walk) {
    $out = [];
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        if (is_dir($p)) $out = array_merge($out, $walk($p));
        elseif (substr($f, -4) === '.php') $out[] = $p;
    }
    return $out;
};

$files = array_merge($walk($root . '/lib'), $walk($root . '/views'));
$bad = [];
foreach ($files as $f) {
    $o = []; $rc = 0;
    exec('php -l ' . escapeshellarg($f) . ' 2>&1', $o, $rc);
    if ($rc !== 0) $bad[] = str_replace($root . '/', '', $f) . ' — ' . trim(implode(' ', $o));
}
t_ok(empty($bad), 'all ' . count($files) . ' library + view files parse'
    . ($bad ? "\n        " . implode("\n        ", $bad) : ''));
