<?php
// ============================================================================
//  Generates deploy-check.php — a SINGLE, self-contained file the operator can
//  upload to prove an upload actually landed.
//
//  Why it is one file with the checksums baked in: the operator deploys through
//  a browser File Manager, and the failure this tool exists to catch is exactly
//  "the files in the sub-folders did not get replaced". A checker that needed a
//  second file, or a file inside lib/, could fail the same way and report
//  nothing. One root-level file can always be uploaded, and it carries its own
//  expectations with it.
//
//  Run before committing a release:   php tools/make_deploy_check.php
// ============================================================================

$root = dirname(__DIR__);

// Server-specific files are never checked: config.php holds this install's own
// database credentials and tenants.php is the routing cache, so both SHOULD
// differ from the repository. Tests are not deployed, the generated file cannot
// check itself, and the two build files below never leave the repository.
//
// MATCHED BY EXACT PATH, NOT BY FILENAME. Excluding the basename 'tenants.php'
// also excluded views/ops/tenants.php — which is precisely the kind of file
// that silently fails to upload, so the checker would have stayed blind to the
// one thing it was built to catch.
$skip = ['config.php', 'config.local.php', 'tenants.php', 'deploy-check.php',
         'tools/make_deploy_check.php', 'tools/deploy_check_template.php'];
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
    $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($root))), '/');
    if (strncmp($rel, 'tests/', 6) === 0) continue;
    if (in_array($rel, $skip, true)) continue;
    $files[$rel] = ['s' => $f->getSize(), 'h' => substr(hash_file('sha256', $f->getPathname()), 0, 16)];
}
ksort($files);

$rev = trim((string) @shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --short HEAD 2>/dev/null')) ?: 'local';
$release = $rev . ' · ' . gmdate('Y-m-d H:i') . ' UTC · ' . count($files) . ' files';

$map = "[\n";
foreach ($files as $rel => $m) $map .= "    " . var_export($rel, true) . " => ['s'=>{$m['s']},'h'=>'{$m['h']}'],\n";
$map .= "]";

$tpl = file_get_contents(__DIR__ . '/deploy_check_template.php');
$out = str_replace(['__RELEASE__', "'__EXPECT__'"], [$release, $map], $tpl);
file_put_contents($root . '/deploy-check.php', $out);
echo "deploy-check.php written — " . $release . "\n";
