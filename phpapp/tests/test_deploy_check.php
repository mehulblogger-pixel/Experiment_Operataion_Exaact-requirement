<?php
// ============================================================================
//  The deployment checker must never lie.
//
//  It exists because uploading through a browser File Manager fails quietly:
//  the top-level files are replaced, the ones inside lib/ and views/ are not,
//  and the application keeps serving old code with no error anywhere. The
//  checker is only worth having if its baked-in checksums are in step with the
//  code that ships — a checker reporting "all good" against last week's
//  expectations is worse than no checker, so that is asserted here rather than
//  left to whoever remembers to regenerate it.
//
//  If this fails:   php tools/make_deploy_check.php
// ============================================================================

t_section('Deployment checker');

$root = dirname(__DIR__);
$file = $root . '/deploy-check.php';
t_ok(is_file($file), 'deploy-check.php ships with the application');
if (!is_file($file)) return;

$src = (string) file_get_contents($file);
t_ok(strpos($src, '$EXPECT') !== false, 'it carries its own checksums, so it works when sub-folders did not upload');

// Pull the manifest out without running the page (it would 404 and exit).
$expect = null;
if (preg_match('/\$EXPECT\s*=\s*(\[.*?\n\]);/s', $src, $m)) $expect = eval('return ' . $m[1] . ';');
t_ok(is_array($expect) && count($expect) > 100, 'the manifest covers the whole application');
if (!is_array($expect)) return;

foreach (['index.php', 'lib/saas_tenants.php', 'views/ops/tenants.php', 'tools/phase1_inventory_engine.php'] as $k)
    t_ok(isset($expect[$k]), "it checks $k");

// Server-specific files MUST NOT be checked: config.php holds this install's own
// credentials and tenants.php is the routing cache, so both are meant to differ.
foreach (['config.php', 'tenants.php', 'deploy-check.php'] as $k)
    t_ok(!isset($expect[$k]), "it does not flag $k, which is this server's own");
// The exclusion is by exact path: a view that happens to share a name with a
// server-specific file must still be checked.
t_ok(isset($expect['views/ops/tenants.php']),
     'excluding the routing file does not also exclude the view of the same name');
t_ok(!isset($expect['tools/make_deploy_check.php']), 'and not the build tool that made it');

// Every checksum must match the tree this release is built from.
$drift = [];
foreach ($expect as $rel => $want) {
    $p = $root . '/' . $rel;
    if (!is_file($p)) { $drift[] = $rel . ' (gone)'; continue; }
    if (substr(hash_file('sha256', $p), 0, 16) !== $want['h']) $drift[] = $rel;
}
t_ok(!$drift, 'every checksum matches the shipped code'
     . ($drift ? ' — STALE, run: php tools/make_deploy_check.php (' . implode(', ', array_slice($drift, 0, 5)) . ')' : ''));

// And nothing deployable is left out, or an un-uploaded file would pass unseen.
$missed = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
    $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($root))), '/');
    if (strncmp($rel, 'tests/', 6) === 0) continue;
    if (in_array($rel, ['config.php', 'config.local.php', 'tenants.php', 'deploy-check.php',
                        'tools/make_deploy_check.php', 'tools/deploy_check_template.php'], true)) continue;
    if (!isset($expect[$rel])) $missed[] = $rel;
}
t_ok(!$missed, 'no deployable file is left unchecked'
     . ($missed ? ' — missing: ' . implode(', ', array_slice($missed, 0, 5)) : ''));
