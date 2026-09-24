<?php
// ============================================================================
//  /my-work — the view-name collision regression.
//
//  THE DEFECT (fixed): view($name, $vars) ran extract($vars) over its own
//  $name parameter, which is the VIEW IDENTIFIER. ops_my_work() passed
//  ['name' => <the signed-in person's name>], so view() went looking for
//  views/<person's name>.php:
//
//      "Zoya Kapoor"  -> views/Zoya Kapoor.php -> missing -> HTTP 500
//      "admin"        -> views/admin.php       -> EXISTS  -> the Admin area home
//
//  The second case is why this survived four weeks: the account most likely to
//  be used for testing saw a wrong-but-plausible page instead of an error.
//
//  Everything here renders through the PRODUCTION view(), in a separate process
//  (tests/_my_work_view_worker.php) that loads the function's genuine bytes out
//  of index.php. A test that re-creates view()'s extract-and-include locally
//  cannot reproduce this defect at all — that is precisely how the old test
//  passed while the screen was broken.
//
//  See docs/phase7/MY-WORK-DEFECT-FIX.md.
// ============================================================================
t_section('My Work — the display name can never become the view identifier');

// Every render below happens in a SEPARATE PROCESS (tests/_my_work_view_worker.php)
// through the production view(). Three other test files install a capturing stub
// view(); whichever runs first owns the global name, so an in-process render could
// silently go through a stub — the exact mistake that hid this defect for a month.
$root   = dirname(__DIR__);
$engine = $GLOBALS['__test_engine'] ?? 'sqlite';
$env    = $engine === 'sqlite'
    ? 'DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg((string) getenv('SQLITE_PATH'))
    : 'DB_DRIVER=mysql DB_HOST=' . escapeshellarg((string) getenv('DB_HOST'))
      . ' DB_NAME=' . escapeshellarg((string) getenv('DB_NAME'))
      . ' DB_USER=' . escapeshellarg((string) getenv('DB_USER'))
      . ' DB_PASS=' . escapeshellarg((string) getenv('DB_PASS'));
$worker = function (array $args) use ($root, $env) {
    $cmd = $env . ' php ' . escapeshellarg($root . '/tests/_my_work_view_worker.php');
    foreach ($args as $a) $cmd .= ' ' . escapeshellarg((string) $a);
    $raw = (string) shell_exec($cmd . ' 2>&1');
    foreach (array_reverse(explode("\n", trim($raw))) as $line) {
        $d = json_decode(trim($line), true);
        if (is_array($d)) return $d;
    }
    return ['ok' => false, 'error' => 'no JSON from worker: ' . substr($raw, 0, 300)];
};

// The worker must be rendering through the PRODUCTION view(). It fingerprints,
// by reflection, whatever function it actually loaded; that must match the bytes
// in index.php. This is what stops the harness quietly regressing to a local
// imitation of view() — the original sin that hid this defect for four weeks.
$__idx = (string) file_get_contents($root . '/index.php');
$__i   = strpos($__idx, 'function view($name, $vars = []) {');
$__fn  = substr($__idx, $__i, strpos($__idx, "\n}\n", $__i) - $__i + 3);
$expectSha = hash('sha256', rtrim($__fn));
$probe = $worker(['handler', 'Fingerprint', 'Probe', 'COORDINATOR', 0]);
t_eq((string) ($probe['viewSha'] ?? ''), $expectSha,
    'the worker rendered through the view() that is actually in index.php, not an imitation');

// ---- A · the renderer resolves its file from something extract() cannot touch
$vsrc = (string) file_get_contents($root . '/index.php');
$vsrc = substr($vsrc, strpos($vsrc, 'function view($name, $vars = []) {'));
$vsrc = substr($vsrc, 0, strpos($vsrc, "\n}\n") + 3);
$posCapture = strpos($vsrc, '$__view');
$posExtract = strpos($vsrc, 'extract($vars)');
t_ok($posCapture !== false, 'view() keeps the view identifier in a private local');
t_ok($posCapture !== false && $posExtract !== false && $posCapture < $posExtract,
    'the identifier is captured BEFORE extract() runs — caller data cannot overwrite it');
t_ok(strpos($vsrc, '"/views/$__view.php"') !== false,
    'the file view() opens is built from the private local, not from $name');

// ---- B · no application call site hands view() a key called "name"
// Tokenised, not text-matched: a comment that merely MENTIONS ['name' => …]
// (there is one, inside view() itself, explaining this very defect) is not a
// call site, and a text scan would flag it forever.
$appFiles = [];
$walk = function ($dir) use (&$walk, &$appFiles) {
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..' || $f === 'tests' || $f === '.git') continue;
        $p = $dir . '/' . $f;
        if (is_dir($p)) $walk($p);
        elseif (substr($f, -4) === '.php') $appFiles[] = $p;
    }
};
$walk(dirname(__DIR__));
$scan = function ($file) {
    $t = token_get_all((string) file_get_contents($file));
    $hits = [];
    for ($i = 0; $i < count($t); $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_STRING || strtolower($t[$i][1]) !== 'view') continue;
        // a definition is not a call site; neither is $obj->view(...) or Cls::view(...)
        $prev = $i - 1; while ($prev >= 0 && is_array($t[$prev]) && $t[$prev][0] === T_WHITESPACE) $prev--;
        if ($prev >= 0 && is_array($t[$prev]) && in_array($t[$prev][0], [T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW], true)) continue;
        $nx = $i + 1; while ($nx < count($t) && is_array($t[$nx]) && $t[$nx][0] === T_WHITESPACE) $nx++;
        if ($nx >= count($t) || $t[$nx] !== '(') continue;
        // walk the argument list; flag a constant string 'name' used as an array key
        $depth = 0;
        for ($j = $nx; $j < count($t); $j++) {
            $tok = $t[$j];
            if ($tok === '(' || $tok === '[') { $depth++; continue; }
            if ($tok === ')' || $tok === ']') { $depth--; if ($depth === 0) break; continue; }
            if (is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING && trim($tok[1], "'\"") === 'name') {
                $k = $j + 1; while ($k < count($t) && is_array($t[$k]) && $t[$k][0] === T_WHITESPACE) $k++;
                if ($k < count($t) && is_array($t[$k]) && $t[$k][0] === T_DOUBLE_ARROW) {
                    $hits[] = $tok[2];
                }
            }
        }
    }
    return $hits;
};
$offenders = []; $callSites = 0;
foreach ($appFiles as $p) {
    foreach ($scan($p) as $ln) $offenders[] = str_replace(dirname(__DIR__) . '/', '', $p) . ':' . $ln;
}
t_eq(count($offenders), 0, 'no application call site passes view() a key called "name" (' . implode(', ', $offenders) . ')');
// and the scanner is not vacuous: it must still find the collision if reintroduced.
$probe = sys_get_temp_dir() . '/vnc_scan_probe_' . getmypid() . '.php';
file_put_contents($probe, "<?php\n// a comment mentioning ['name' => 'x'] must NOT be flagged\nview('ops/my_work', ['lanes' => [], 'name' => 'Zoya Kapoor']);\n");
$probeHits = $scan($probe);
@unlink($probe);
t_eq(count($probeHits), 1, 'the collision scanner detects a real call site (and ignores a comment that mentions one)');

// ---- C · the real handler, for every role, whatever the person is called ----
// Renders through ops_my_work() -> view() -> views/ops/my_work.php: the whole
// production path short of the HTTP router.
$roles = [
    ['ADMIN',             'Zoya',   'Kapoor', 1],
    ['COORDINATOR',       'Anita',  'Desai',  0],
    ['SR_INSPECTOR',      'Imran',  'Shaikh', 0],
    ['INSPECTOR',         'Neha',   'Rao',    0],
    ['FINANCE',           'Farah',  'Mistry', 0],
    ['OPERATION_MANAGER', 'Vikram', 'Sen',    0],
];
foreach ($roles as [$role, $first, $last, $super]) {
    $r = $worker(['handler', $first, $last, $role, $super]);
    t_ok(!empty($r['ok']), "$role ($first $last) rendered without error" . (empty($r['ok']) ? ' — ' . ($r['error'] ?? '?') : ''));
    t_ok(!empty($r['isMyWork']), "$role ($first $last) opens the real My Work screen");
    t_ok(empty($r['isAdminArea']), "$role ($first $last) is NOT served the Admin area home");
    t_ok(empty($r['failPanel']), "$role ($first $last) does not get the \"screen could not be loaded\" panel");
    t_ok(strpos((string) ($r['html'] ?? ''), 'Everything waiting on ' . $first . ' ' . $last) !== false,
        "$role sees their own name in the subtitle — the name is data, not a view identifier");
}

// ---- D · the critical name: "admin", the one that used to mask the defect ---
// No first/last name at all, so user_name() falls back to the USERNAME — which is
// how the real admin account behaves, and why it was served views/admin.php.
$r = $worker(['handler', '', '', 'ADMIN', 1]);
t_ok(!empty($r['isMyWork']) && empty($r['isAdminArea']),
    'a user whose display name falls back to their username still gets My Work');

$r = $worker(['handler', 'admin', '', 'ADMIN', 1]);
t_eq((string) ($r['displayName'] ?? ''), 'admin', 'the probe user really is called "admin"');
t_ok(!empty($r['isMyWork']),    'a person called "admin" gets My Work');
t_ok(empty($r['isAdminArea']),  'a person called "admin" is NOT silently sent to the Admin area home');

// ---- E · names that collide with real view files, and awkward names ---------
// views/ holds admin.php, dashboard.php, list.php, form.php, notfound.php,
// login.php, detail.php … any of those would have been rendered instead. The
// traversal cases matter most: before the fix, '../index' made view() require
// the front controller itself.
$nasty = ['admin', 'dashboard', 'list', 'form', 'notfound', 'login', 'detail',
          'Zoya Kapoor', 'MacDonald', "O'Brien", 'Anne-Marie Smith', 'R2 D2',
          'ops/my_work', '../index', '../config', 'Dashboard'];
foreach ($nasty as $n) {
    $r = $worker(['handler', $n, '', 'COORDINATOR', 0]);
    t_ok(!empty($r['isMyWork']) && empty($r['isAdminArea']) && empty($r['failPanel']),
        'display name ' . var_export($n, true) . ' still renders My Work');
}

// ---- F · nothing about My Work's contract changed ---------------------------
$ops = (string) file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "\$route === 'my-work'") !== false, 'the /my-work route is unchanged');
t_ok(!preg_match('/can\(\x27mod\.mywork/', $ops), 'no new permission was invented');
t_ok(strpos($ops, "'userName'") !== false && !preg_match("/'name'\s*=>\s*function_exists\('user_name'\)/", $ops),
    'ops_my_work() passes the display name under a key that is not "name"');
t_ok(strpos((string) file_get_contents(__DIR__ . '/../views/ops/my_work.php'), '$userName') !== false,
    'the My Work template reads the renamed variable');

// The worker deletes each probe user as it goes; prove none survived.
// LIKE 'vnc%' rather than an escaped underscore: ESCAPE '\\' is not portable
// between SQLite and MariaDB, and every probe username this file creates starts
// with "vnc_", so the looser pattern is both sufficient and engine-neutral.
t_eq((int) ops_val("SELECT COUNT(*) FROM users WHERE username LIKE 'vnc%'"), 0,
    'every probe user created by this test is removed');
