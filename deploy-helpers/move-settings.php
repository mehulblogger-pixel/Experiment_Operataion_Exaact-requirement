<?php
// =============================================================================
//  move-settings.php — ONE-TIME helper: move this site's database and admin
//  settings out of config.php into config.local.php, with one click.
//
//  Why: config.php is part of the application and is REPLACED by every update.
//  A site whose database password lives only in config.php goes down on its
//  next update. config.local.php is never part of an update, so settings kept
//  there survive every upload. This helper writes that file for a person who
//  should not have to edit PHP by hand.
//
//  What it does, in order — and it stops, changing nothing, at the first doubt:
//    1. Refuses if config.local.php already exists (nothing to do; never
//       overwrites).
//    2. Reads the settings the site is using RIGHT NOW (by loading config.php
//       exactly as the application does) and opens that database.
//    3. Asks for the administrator's own sign-in and checks it against that
//       database. Nobody else can run it.
//    4. Writes the SAME values to a temporary file, reads them back and
//       compares, then puts the file in place as config.local.php.
//    5. Loads config.php again (now reading config.local.php), opens the
//       database again and checks it is the very same database, with the same
//       number of user accounts. If anything differs, it removes the new file
//       again, so the site is exactly as before.
//    6. On success it deletes itself.
//
//  It never shows a password, never changes config.php, never changes the
//  database, and never changes the admin password: the admin values are copied
//  unchanged, so the application's "admin password from config" check sees no
//  change and does nothing.
//
//  Upload into the application folder (the one holding index.php and
//  config.php), open https://<site>/move-settings.php, follow the page.
// =============================================================================

declare(strict_types=1);

$DIR     = __DIR__;
$LOCAL   = $DIR . '/config.local.php';
$CONFIG  = $DIR . '/config.php';
$h       = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('X-Frame-Options: DENY');

// ---------------------------------------------------------------------------
//  Helpers
// ---------------------------------------------------------------------------

// Load config.php the way the application does and return its 'db' and 'admin'.
// config.php only builds and returns an array, so loading it twice is safe.
function ms_load_config(string $path): array {
    $cfg = @require $path;
    if (!is_array($cfg) || !isset($cfg['db']) || !is_array($cfg['db'])) {
        throw new RuntimeException('config.php did not return its settings.');
    }
    return $cfg;
}

// Open the configured MySQL database. Returns [PDO, database name in use].
function ms_open(array $db): array {
    $driver = strtolower((string) ($db['driver'] ?? 'mysql'));
    if ($driver !== 'mysql') throw new RuntimeException('This site does not use a MySQL database, so this helper does not apply.');
    $host = (string) ($db['host'] ?? 'localhost');
    $dsn  = 'mysql:host=' . $host . ';dbname=' . (string) ($db['name'] ?? '') . ';charset=utf8mb4';
    if (!empty($db['port'])) $dsn .= ';port=' . (int) $db['port'];
    $pdo = new PDO($dsn, (string) ($db['user'] ?? ''), (string) ($db['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10,
    ]);
    $name = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    return [$pdo, $name];
}

// A small fingerprint of the database, used to prove "same database" afterwards.
function ms_fingerprint(PDO $pdo): array {
    $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $maxId = (int) $pdo->query('SELECT COALESCE(MAX(id),0) FROM users')->fetchColumn();
    return ['users' => $users, 'max_user_id' => $maxId];
}

// Is this the administrator? Checked against the site's own user table.
function ms_is_admin(PDO $pdo, string $user, string $pass): bool {
    if ($user === '' || $pass === '') return false;
    $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$user]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u || empty($u['password_hash']) || !password_verify($pass, (string) $u['password_hash'])) return false;
    if (isset($u['is_active']) && (int) $u['is_active'] !== 1) return false;
    $role = strtoupper((string) ($u['role'] ?? ''));
    return (int) ($u['is_superuser'] ?? 0) === 1 || in_array($role, ['MASTER_ADMIN', 'ADMIN'], true);
}

// The exact text of the new config.local.php. var_export writes each value as a
// correctly quoted PHP string, so passwords with any characters copy safely.
function ms_file_text(array $db, array $admin): string {
    $keepDb = [];
    foreach ($db as $k => $v) if (is_scalar($v) || $v === null) $keepDb[$k] = $v;
    $keepAdmin = [];
    foreach (['user', 'pass'] as $k) if (isset($admin[$k])) $keepAdmin[$k] = (string) $admin[$k];
    $data = ['db' => $keepDb];
    if ($keepAdmin) $data['admin'] = $keepAdmin;
    return "<?php\n"
        . "// This site's real settings. Written by move-settings.php on " . gmdate('Y-m-d H:i') . " UTC,\n"
        . "// copied unchanged from config.php. Never part of an update, so it survives every upload.\n"
        . "// The line below stays commented out on the LIVE site:\n"
        . "// 'test_copy' => true,\n"
        . 'return ' . var_export($data, true) . ";\n";
}

function ms_page(string $title, string $body): void {
    global $h;
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Move settings</title><style>'
       . ':root{--bg:#f5f7fa;--card:#fff;--fg:#1f2937;--mut:#6b7280;--line:#e5e7eb;--ok:#047857;--okbg:#ecfdf5;--bad:#b91c1c;--badbg:#fef2f2;--acc:#1d4ed8}'
       . '@media (prefers-color-scheme:dark){:root{--bg:#0b1220;--card:#111a2e;--fg:#e5e7eb;--mut:#9ca3af;--line:#243049;--ok:#34d399;--okbg:#052e22;--bad:#fca5a5;--badbg:#3b0d0d;--acc:#60a5fa}}'
       . 'body{margin:0;background:var(--bg);color:var(--fg);font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}'
       . '.w{max-width:620px;margin:0 auto;padding:28px 16px}.c{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:22px}'
       . 'h1{font-size:21px;margin:0 0 6px}p{margin:8px 0}.m{color:var(--mut);font-size:13.5px}'
       . '.ok{background:var(--okbg);color:var(--ok);border-radius:10px;padding:12px 14px;margin:12px 0}'
       . '.bad{background:var(--badbg);color:var(--bad);border-radius:10px;padding:12px 14px;margin:12px 0}'
       . 'dl{display:grid;grid-template-columns:max-content 1fr;gap:6px 14px;margin:12px 0}dt{color:var(--mut)}dd{margin:0;word-break:break-all}'
       . 'label{display:block;font-weight:600;font-size:13.5px;margin:14px 0 4px}input{width:100%;box-sizing:border-box;padding:11px;border:1px solid var(--line);border-radius:8px;font-size:15px;background:var(--card);color:var(--fg)}'
       . 'button{margin-top:18px;width:100%;padding:13px;border:0;border-radius:9px;background:var(--acc);color:#fff;font-size:15.5px;font-weight:600;cursor:pointer}'
       . 'a{color:var(--acc)}code{font-size:13px}'
       . '</style></head><body><div class="w"><div class="c"><h1>' . $h($title) . '</h1>' . $body . '</div></div></body></html>';
}

// ---------------------------------------------------------------------------
//  1. Already done?
// ---------------------------------------------------------------------------
if (is_file($LOCAL)) {
    ms_page('Nothing to do', '<div class="ok">This site already keeps its settings in <code>config.local.php</code>.</div>'
        . '<p>This helper never overwrites that file. Please delete <code>move-settings.php</code> from the folder.</p>'
        . '<p><a href="/deploy-check.php">Open the deploy-check page</a></p>');
    exit;
}
if (!is_file($CONFIG)) {
    ms_page('Wrong folder', '<div class="bad">There is no <code>config.php</code> next to this file.</div>'
        . '<p>Upload <code>move-settings.php</code> into the application folder: the one that holds <code>index.php</code> and <code>config.php</code>.</p>');
    exit;
}

// ---------------------------------------------------------------------------
//  2. What the site uses right now
// ---------------------------------------------------------------------------
try {
    $before = ms_load_config($CONFIG);
    [$pdo, $dbName] = ms_open($before['db']);
    $fpBefore = ms_fingerprint($pdo);
} catch (Throwable $e) {
    ms_page('Stopped — nothing was changed', '<div class="bad">The helper could not open the database this site uses now, so it did nothing.</div>'
        . '<p class="m">Reason: ' . $h($e->getMessage()) . '</p><p>Your site is exactly as it was. Please send a screenshot of this page.</p>');
    exit;
}
if ($fpBefore['users'] < 1) {
    ms_page('Stopped — nothing was changed', '<div class="bad">The database this site uses has no user accounts, so this does not look like your working site.</div>'
        . '<p>Nothing was changed. Please send a screenshot of this page.</p>');
    exit;
}

$summary = '<dl><dt>Database in use</dt><dd>' . $h($dbName) . '</dd>'
         . '<dt>Database user</dt><dd>' . $h($before['db']['user'] ?? '') . '</dd>'
         . '<dt>User accounts in it</dt><dd>' . $h($fpBefore['users']) . '</dd>'
         . '<dt>Settings kept in</dt><dd><code>config.php</code> (replaced by every update)</dd></dl>';

// ---------------------------------------------------------------------------
//  Show the form
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ms_page('Move settings to the safe file',
        '<p>This copies your site\'s database and admin settings, <b>unchanged</b>, from <code>config.php</code> into <code>config.local.php</code>, which updates never touch.</p>'
        . $summary
        . '<p class="m">Nothing else changes: not your data, not your admin password, not how the site works. If any check fails, the helper undoes its own step and tells you.</p>'
        . '<form method="post" autocomplete="off">'
        . '<label for="u">Administrator user name</label><input id="u" name="u" required autocapitalize="none" spellcheck="false">'
        . '<label for="p">Administrator password</label><input id="p" name="p" type="password" required>'
        . '<button type="submit">Move my settings</button></form>');
    exit;
}

// ---------------------------------------------------------------------------
//  3. Only the administrator
// ---------------------------------------------------------------------------
if (!ms_is_admin($pdo, trim((string) ($_POST['u'] ?? '')), (string) ($_POST['p'] ?? ''))) {
    sleep(2);   // slows down guessing
    ms_page('Not signed in', '<div class="bad">That user name and password are not an active administrator of this site. Nothing was changed.</div>'
        . '<p><a href="move-settings.php">Try again</a></p>');
    exit;
}

// ---------------------------------------------------------------------------
//  4. Write, read back, compare, put in place
// ---------------------------------------------------------------------------
$tmp  = $DIR . '/config.local.php.tmp-' . bin2hex(random_bytes(4));
$text = ms_file_text($before['db'], (array) ($before['admin'] ?? []));
$undo = static function () use ($LOCAL, $tmp): void { @unlink($tmp); @unlink($LOCAL); };
try {
    if (@file_put_contents($tmp, $text, LOCK_EX) === false) {
        throw new RuntimeException('The application folder is not writable, so the file could not be created.');
    }
    @chmod($tmp, 0640);
    if (function_exists('opcache_invalidate')) @opcache_invalidate($tmp, true);
    $back = @include $tmp;
    $wantDb = []; foreach ($before['db'] as $k => $v) if (is_scalar($v) || $v === null) $wantDb[$k] = $v;
    if (!is_array($back) || ($back['db'] ?? null) !== $wantDb) throw new RuntimeException('The new file did not read back exactly the same database settings.');
    $wantAdmin = []; foreach (['user', 'pass'] as $k) if (isset($before['admin'][$k])) $wantAdmin[$k] = (string) $before['admin'][$k];
    if ($wantAdmin && ($back['admin'] ?? null) !== $wantAdmin) throw new RuntimeException('The new file did not read back exactly the same admin settings.');
    if (is_file($LOCAL)) throw new RuntimeException('A config.local.php appeared while this was running.');
    if (!@rename($tmp, $LOCAL)) throw new RuntimeException('The new file could not be put in place.');
    if (function_exists('opcache_invalidate')) @opcache_invalidate($LOCAL, true);

    // -----------------------------------------------------------------------
    //  5. Load the site's settings again (now from config.local.php) and prove
    //     it is the same database and the same admin values.
    // -----------------------------------------------------------------------
    if (function_exists('opcache_invalidate')) @opcache_invalidate($CONFIG, true);
    $after = ms_load_config($CONFIG);
    foreach (['host', 'name', 'user', 'pass', 'driver', 'port'] as $k) {
        if (($after['db'][$k] ?? null) !== ($before['db'][$k] ?? null)) throw new RuntimeException('After the move the site would read a different database setting (' . $k . ').');
    }
    if (($after['admin']['user'] ?? null) !== ($before['admin']['user'] ?? null)
        || ($after['admin']['pass'] ?? null) !== ($before['admin']['pass'] ?? null)) {
        throw new RuntimeException('After the move the admin settings would differ.');
    }
    [$pdo2, $dbName2] = ms_open($after['db']);
    $fpAfter = ms_fingerprint($pdo2);
    if ($dbName2 !== $dbName || $fpAfter !== $fpBefore) throw new RuntimeException('After the move the site would open a different database.');
} catch (Throwable $e) {
    $undo();
    ms_page('Stopped — nothing was changed', '<div class="bad">A check failed, so the helper removed its new file again. Your site is exactly as it was.</div>'
        . '<p class="m">Reason: ' . $h($e->getMessage()) . '</p><p>Please send a screenshot of this page.</p>');
    exit;
}

// ---------------------------------------------------------------------------
//  6. Done — remove this helper
// ---------------------------------------------------------------------------
$gone = @unlink(__FILE__);
ms_page('Done', '<div class="ok"><b>Your settings are now in <code>config.local.php</code>.</b> Every check passed: the site opens the same database ('
    . $h($dbName) . ', ' . $h($fpAfter['users']) . ' user accounts) with the same admin sign-in.</div>'
    . ($gone ? '<p>This helper has deleted itself.</p>'
             : '<p><b>Please delete <code>move-settings.php</code></b> from the folder now (File Manager → right-click → Delete). It could not delete itself.</p>')
    . '<p>Next: open <a href="/deploy-check.php">the deploy-check page</a> and send a screenshot of "Where your database settings live".</p>');
