<?php
// ============================================================================
//  EXAACT — DEPLOYMENT CHECK  (administrator browser tool, GENERATED FILE)
//
//  Answers one question: did my upload actually land?
//
//  Deploying happens through a browser File Manager, and the way it fails is
//  quiet — the files at the top level are replaced, the ones inside lib/ and
//  views/ are not, and the application keeps running the old code with no error
//  anywhere. This page compares every PHP file on the server against the
//  release it was built from and names the ones that are stale or missing.
//
//  HOW TO USE IT
//   1. Sign in to EXAACT as usual on your MAIN address (the control install).
//   2. In the same browser open:  /deploy-check.php
//
//  It is ONE file and it carries its own checksums, so it still works when the
//  sub-folders are the very thing that failed to upload.
//
//  READS ONLY. Opens no workspace, runs no migration, writes to no database,
//  changes no file. To anyone who is not a signed-in administrator it returns a
//  plain 404, so it is not discoverable.
//
//  DO NOT EDIT — regenerate with:  php tools/make_deploy_check.php
// ============================================================================

$RELEASE = '__RELEASE__';
$EXPECT  = '__EXPECT__';

$IS_CLI = (PHP_SAPI === 'cli');

// ---- Authenticate against the application's existing login session ---------
$AUTH = null;
if (!$IS_CLI) {
    $httpsNow = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
             || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true,
                              'secure' => $httpsNow, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}
$UID = (int) ($_SESSION['uid'] ?? 0);
$_SESSION['saas_tenant'] = '';            // in memory only: resolve the CONTROL install
$_SERVER['HTTP_HOST']    = '';
$_SERVER['SERVER_NAME']  = $_SERVER['SERVER_NAME'] ?? 'localhost';

$CFG  = @require __DIR__ . '/config.php';
$WHY  = '';                     // why we could not confirm an administrator
if (!$IS_CLI) {
    if (!is_array($CFG))      $WHY = 'config';
    elseif ($UID <= 0)        $WHY = 'signin';
    else {
        try {
            $d = (array) ($CFG['db'] ?? []);
            $pdo = ($d['driver'] ?? '') === 'sqlite'
                ? new PDO('sqlite:' . $CFG['sqlite_path'])
                : new PDO("mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'], [PDO::ATTR_TIMEOUT => 10]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $st = $pdo->prepare("SELECT id, username, is_superuser, is_active FROM users WHERE id = ?");
            $st->execute([$UID]);
            $u = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($u && (int) $u['is_superuser'] === 1 && (int) $u['is_active'] === 1) $AUTH = $u;
            else $WHY = 'notadmin';
        } catch (Throwable $e) { $WHY = 'db'; }
    }
}

// A bare 404 for every refusal was a mistake in a tool whose whole job is to
// tell the operator what is wrong: "page not found" then means BOTH "the file
// did not upload" and "you are not signed in", and those need opposite actions.
//
// So a visitor who is simply not signed in gets a short page saying so. It
// reveals only that the file exists — every file name, size and checksum stays
// behind the administrator check. Someone signed in who is NOT an
// administrator still gets a plain 404.
if (!$IS_CLI && !$AUTH) {
    if ($WHY === 'notadmin') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not Found\n";
        exit;
    }
    $msg = $WHY === 'signin'
        ? 'Sign in to EXAACT as an administrator in this same browser, then reload this page.'
        : ($WHY === 'config'
            ? 'This page could not read config.php. Check that config.php and config.local.php are both in the application folder.'
            : 'This page could not open the database. The settings in config.local.php may be wrong, or the database server may be down.');
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Deployment check</title>'
       . '<div style="max-width:560px;margin:0 auto;padding-block:48px;padding-left:16px;padding-right:16px;'
       . 'font:15px/1.6 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0f172a">'
       . '<h1 style="font-size:20px;margin:0 0 10px">Deployment check</h1>'
       . '<p style="margin:0 0 14px">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
       . '<p style="color:#64748b;font-size:13.5px;margin:0">The file is on the server and reachable &mdash; so if a '
       . '&ldquo;page not found&rdquo; sent you here, that part is already solved.</p></div>';
    exit;
}

// ---- Configuration health -------------------------------------------------
//
// Two files can hold this server's database credentials and only ONE of them is
// read. config.php is part of the application and is replaced by every upload;
// config.local.php is not sent by anyone and therefore survives. A sample file
// filled in by mistake is read by nothing at all, while still leaving a copy of
// the password in the folder.
//
// Nothing here ever prints a password. Values are compared by hash so two files
// can be reported as agreeing or differing without either being shown.
$loadArr = function ($rel) {
    $p = __DIR__ . '/' . $rel;
    if (!is_file($p)) return null;                       // not there
    $r = @include $p;
    return is_array($r) ? $r : false;                    // false = there but unreadable
};
$hasReal = function ($rel) {                             // still the shipped placeholders?
    $p = __DIR__ . '/' . $rel;
    if (!is_file($p)) return false;
    $txt = (string) @file_get_contents($p);
    return $txt !== '' && strpos($txt, 'your_db_name') === false && strpos($txt, 'your_db_password') === false;
};
$fp = fn($v) => $v === '' || $v === null ? '' : substr(hash('sha256', (string) $v), 0, 12);

$cfgLocal  = $loadArr('config.local.php');
$cfgSample = $loadArr('config.local.sample.php');
$liveDb    = (array) ($CFG['db'] ?? []);

$localReal  = is_array($cfgLocal)  && $hasReal('config.local.php');
$sampleReal = is_array($cfgSample) && $hasReal('config.local.sample.php');
$phpReal    = $hasReal('config.php');

// Which file actually supplied the credentials in use.
$source = 'config.php';
if ($localReal && (string) ($cfgLocal['db']['name'] ?? '') === (string) ($liveDb['name'] ?? '')) $source = 'config.local.php';

// The admin password is synced INTO the login on the next page load whenever it
// changes, so a mismatch between the two files is not cosmetic: it silently
// changes who can sign in.
$adminDiffers = $localReal && isset($cfgLocal['admin']['pass'])
             && $fp($cfgLocal['admin']['pass'] ?? '') !== $fp($CFG['admin']['pass'] ?? '');

$cfgRows = [
    ['config.php', is_file(__DIR__ . '/config.php'), $phpReal, true,
     'Part of the application. REPLACED by every upload.'],
    ['config.local.php', is_file(__DIR__ . '/config.local.php'), $localReal, true,
     'Never sent by anyone, so it survives every upload. This is where credentials belong.'],
    ['config.local.sample.php', is_file(__DIR__ . '/config.local.sample.php'), $sampleReal, false,
     'A blank form to copy. The application never reads it.'],
];

$cfgWarn = [];
if (!$localReal && $phpReal)
    $cfgWarn[] = ['bad', 'Your credentials live only in config.php, which every upload replaces. '
                       . 'One upload of that file takes the site down until you type them in again.'];
if ($sampleReal)
    $cfgWarn[] = ['bad', 'config.local.sample.php has real credentials in it, and the application never reads that file. '
                       . 'It is doing nothing except keeping a copy of your password in the folder.'];
if ($localReal && $phpReal)
    $cfgWarn[] = ['warn', 'Both config.php and config.local.php hold real credentials. config.local.php wins, '
                        . 'so config.php can safely be replaced with the clean copy from the code.']; 
if ($adminDiffers)
    $cfgWarn[] = ['warn', 'The administrator password in config.local.php differs from the one in use. '
                        . 'On the next page load the application will change the admin login to match config.local.php.']; 
if (!$cfgWarn && $localReal)
    $cfgWarn[] = ['ok', 'Credentials are in config.local.php only. Uploads cannot touch them.'];

// ---- Compare -------------------------------------------------------------
$ok = []; $stale = []; $missing = [];
foreach ($EXPECT as $rel => $want) {
    $path = __DIR__ . '/' . $rel;
    if (!is_file($path)) { $missing[] = ['f' => $rel, 'want' => $want['s']]; continue; }
    $got = ['s' => (int) filesize($path), 'h' => substr(hash_file('sha256', $path), 0, 16)];
    if ($got['h'] === $want['h']) { $ok[] = $rel; continue; }
    $stale[] = ['f' => $rel, 'want' => $want['s'], 'got' => $got['s'],
                'age' => (int) @filemtime($path)];
}
usort($stale, fn($a, $b) => strcmp($a['f'], $b['f']));
$total = count($EXPECT);
$bad   = count($stale) + count($missing);

// Group the problems by folder — "everything under views/ops is old" is the
// finding, and a flat list of forty files hides it.
$byDir = [];
foreach (array_merge($stale, $missing) as $r) {
    $dir = dirname($r['f']); $dir = $dir === '.' ? '(top level)' : $dir . '/';
    $byDir[$dir] = ($byDir[$dir] ?? 0) + 1;
}
arsort($byDir);

if ($IS_CLI) {
    echo "EXAACT deployment check — release {$RELEASE}\n";
    echo str_repeat('=', 70) . "\n";
    printf("%d of %d files match. %d stale, %d missing.\n", count($ok), $total, count($stale), count($missing));
    foreach ($byDir as $dir => $n) echo "  {$dir}  {$n} file(s) out of date\n";
    foreach ($stale as $r)   echo "  STALE   {$r['f']}  (server " . number_format($r['got']) . " B, release " . number_format($r['want']) . " B)\n";
    foreach ($missing as $r) echo "  MISSING {$r['f']}\n";
    exit($bad ? 1 : 0);
}

$kb = fn($b) => number_format($b / 1024, 2) . ' KB';
$h  = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Deployment check</title>
<style>
  :root{--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;--ok:#047857;--okbg:#ecfdf5;--bad:#b91c1c;--badbg:#fef2f2;--card:#fff;--bg:#f8fafc}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
  .wrap{max-width:920px;margin:0 auto;padding-block:28px;padding-left:16px;padding-right:16px}
  h1{font-size:22px;margin:0 0 4px;letter-spacing:-.01em}
  .rel{color:var(--mut);font-size:13px;margin:0 0 20px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px 20px;margin-bottom:16px}
  .verdict{display:flex;gap:12px;align-items:flex-start;border-radius:14px;padding:18px 20px;margin-bottom:16px}
  .v-ok{background:var(--okbg);border:1px solid #a7f3d0;color:var(--ok)}
  .v-bad{background:var(--badbg);border:1px solid #fca5a5;color:var(--bad)}
  .verdict b{display:block;font-size:17px;margin-bottom:2px}
  .big{font-size:26px;font-weight:700;letter-spacing:-.02em}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px}
  .stat{border:1px solid var(--line);border-radius:11px;padding:12px 14px;background:var(--card)}
  .stat span{display:block;color:var(--mut);font-size:12px;text-transform:uppercase;letter-spacing:.04em}
  table{width:100%;border-collapse:collapse;font-size:13.5px}
  th,td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--line);vertical-align:top}
  th{color:var(--mut);font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
  code{font:12.5px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all}
  .tag{display:inline-block;padding:1px 7px;border-radius:999px;font-size:11px;font-weight:700}
  .t-stale{background:#fef3c7;color:#92400e}.t-miss{background:var(--badbg);color:var(--bad)}
  .scroll{overflow-x:auto}
  .steps{margin:10px 0 0 18px;padding:0}.steps li{margin:5px 0}
  @media (prefers-color-scheme:dark){:root:not([data-theme="light"]){--ink:#e2e8f0;--mut:#94a3b8;--line:#1e293b;--card:#0f172a;--bg:#020617;--okbg:#052e22;--badbg:#2c0b0b}}
  :root[data-theme="dark"]{--ink:#e2e8f0;--mut:#94a3b8;--line:#1e293b;--card:#0f172a;--bg:#020617;--okbg:#052e22;--badbg:#2c0b0b}
</style>
<div class="wrap">
  <h1>Deployment check</h1>
  <p class="rel">Release <code><?= $h($RELEASE) ?></code> · signed in as <?= $h($AUTH['username'] ?? '') ?></p>

  <?php if (!$bad): ?>
    <div class="verdict v-ok"><span style="font-size:22px">&#10003;</span>
      <div><b>Every file on this server matches the release.</b>
        All <?= (int) $total ?> files are up to date. The upload landed correctly.</div></div>
  <?php else: ?>
    <div class="verdict v-bad"><span style="font-size:22px">&#9888;&#65039;</span>
      <div><b><?= (int) $bad ?> file<?= $bad === 1 ? '' : 's' ?> did not upload.</b>
        The application is still running older code for these. Upload them again &mdash; overwrite, do not delete first.</div></div>
  <?php endif; ?>

  <div class="grid" style="margin-bottom:16px">
    <div class="stat"><span>Up to date</span><div class="big" style="color:var(--ok)"><?= count($ok) ?></div></div>
    <div class="stat"><span>Stale</span><div class="big" style="color:<?= $stale ? 'var(--bad)' : 'inherit' ?>"><?= count($stale) ?></div></div>
    <div class="stat"><span>Missing</span><div class="big" style="color:<?= $missing ? 'var(--bad)' : 'inherit' ?>"><?= count($missing) ?></div></div>
    <div class="stat"><span>Checked</span><div class="big"><?= (int) $total ?></div></div>
  </div>

  <div class="card">
    <h2 style="font-size:15px;margin:0 0 4px">Where your database settings live</h2>
    <p style="color:var(--mut);font-size:13px;margin:0 0 12px">Passwords are never shown on this page.</p>
    <div class="scroll"><table>
      <tr><th>File</th><th>On the server</th><th>Has real details</th><th>Read by the app</th></tr>
      <?php foreach ($cfgRows as [$f, $exists, $real, $used, $note]): ?>
      <tr>
        <td><code><?= $h($f) ?></code><br><span style="color:var(--mut);font-size:12px"><?= $h($note) ?></span></td>
        <td><?= $exists ? 'yes' : '<span style="color:var(--mut)">no</span>' ?></td>
        <td><?= $real ? '<b>yes</b>' : '<span style="color:var(--mut)">no</span>' ?></td>
        <td><?= $used ? 'yes' : '<span style="color:var(--mut)">never</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <p style="font-size:13.5px;margin:12px 0 0">In use right now: database <code><?= $h($liveDb['name'] ?? '?') ?></code>
      as user <code><?= $h($liveDb['user'] ?? '?') ?></code>, taken from <code><?= $h($source) ?></code>.</p>
    <?php foreach ($cfgWarn as [$kind, $text]): ?>
      <div style="margin-top:10px;padding:11px 13px;border-radius:10px;font-size:13.5px;<?=
        $kind === 'bad'  ? 'border:1px solid #fca5a5;background:var(--badbg);color:var(--bad)' :
       ($kind === 'warn' ? 'border:1px solid #fcd34d;background:#fffbeb;color:#78350f'
                         : 'border:1px solid #a7f3d0;background:var(--okbg);color:var(--ok)') ?>">
        <?= $h($text) ?>
      </div>
    <?php endforeach; ?>
    <?php if ($sampleReal || (!$localReal && $phpReal)): ?>
      <h3 style="font-size:13.5px;margin:14px 0 4px">How to put this right &mdash; in your File Manager</h3>
      <ol class="steps" style="font-size:13.5px">
        <?php if ($sampleReal && !$localReal): ?>
          <li><b>Rename</b> <code>config.local.sample.php</code> to <code>config.local.php</code>. Check first that the
            database name, user and password in it are the ones in use above &mdash; if they are not, correct them before renaming.</li>
        <?php elseif ($sampleReal && $localReal): ?>
          <li><b>Replace</b> <code>config.local.sample.php</code> with the blank copy from the code, or delete it.
            <code>config.local.php</code> already holds your real settings, so this file is only a spare copy of your password.</li>
        <?php else: ?>
          <li><b>Copy</b> <code>config.local.sample.php</code>, rename the copy to <code>config.local.php</code>,
            and put your real database name, user and password in it.</li>
        <?php endif; ?>
        <li><b>Reload the site.</b> If it still works, the new file is being read.</li>
        <li><b>Then</b> upload the clean <code>config.php</code> from the code over the one on the server, so your password
          is in one place only. Do this <em>last</em>, and only after step&nbsp;2 worked.</li>
      </ol>
      <p style="color:var(--mut);font-size:12.5px;margin:8px 0 0">Keep the administrator user and password the same in both
        files while you do this. If they differ, the application resets the admin login to match on the next page load.</p>
    <?php endif; ?>
  </div>

  <?php if ($byDir): ?>
  <div class="card">
    <h2 style="font-size:15px;margin:0 0 10px">Which folders are out of date</h2>
    <div class="scroll"><table>
      <tr><th>Folder</th><th>Files to re-upload</th></tr>
      <?php foreach ($byDir as $dir => $n): ?>
        <tr><td><code><?= $h($dir) ?></code></td><td><?= (int) $n ?></td></tr>
      <?php endforeach; ?>
    </table></div>
    <p style="color:var(--mut);font-size:13px;margin:12px 0 0">Re-upload these folders whole, overwriting what is there. Do not delete anything first.</p>
  </div>
  <?php endif; ?>

  <?php if ($stale || $missing): ?>
  <div class="card">
    <h2 style="font-size:15px;margin:0 0 10px">Every file that needs re-uploading</h2>
    <div class="scroll"><table>
      <tr><th>File</th><th></th><th>On this server</th><th>In the release</th></tr>
      <?php foreach ($missing as $r): ?>
        <tr><td><code><?= $h($r['f']) ?></code></td><td><span class="tag t-miss">missing</span></td>
            <td style="color:var(--mut)">not there</td><td><?= $h($kb($r['want'])) ?></td></tr>
      <?php endforeach; ?>
      <?php foreach ($stale as $r): ?>
        <tr><td><code><?= $h($r['f']) ?></code></td><td><span class="tag t-stale">old</span></td>
            <td><?= $h($kb($r['got'])) ?><?= $r['age'] ? '<br><span style="color:var(--mut);font-size:12px">' . $h(date('j M Y, H:i', $r['age'])) . '</span>' : '' ?></td>
            <td><?= $h($kb($r['want'])) ?></td></tr>
      <?php endforeach; ?>
    </table></div>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2 style="font-size:15px;margin:0 0 6px">If a file keeps showing as old after you re-upload it</h2>
    <ol class="steps">
      <li>Check you uploaded it into the <b>same folder</b> it is listed under above &mdash; a file dropped at the top level instead of inside <code>lib/</code> or <code>views/ops/</code> will not be used.</li>
      <li>Restart PHP in your hosting panel (<b>Developer Tools &rarr; Restart PHP container</b> on mPanel). PHP can keep a compiled copy of the old file in memory until it is restarted.</li>
      <li>Then reload this page. It reads the files fresh every time.</li>
    </ol>
  </div>

  <p style="color:var(--mut);font-size:12.5px">This page reads files and nothing else. It opens no workspace, runs no migration and writes to no database. Delete it once the deployment is confirmed.</p>
</div>
