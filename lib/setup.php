<?php
// ============================================================================
//  Web setup wizard
//
//  Two problems this solves, both for somebody who cannot use a terminal and
//  edits files only through cPanel:
//
//   Phase A — the database will not connect. Instead of a dead error telling
//     them to hand-edit config.php, show a form that takes the database name,
//     user and password, TESTS them, and writes them into config.local.php
//     (the untracked file an upload never overwrites). This is the same file a
//     wrong upload once wiped; now it can be (re)written from the browser.
//
//   Phase B — the database works but the app has never been set up. Walk them
//     once through the few things only they can decide: their admin password,
//     the company name, the industry, and the basics (financial year,
//     currency, a privacy contact). Then never show it again.
//
//  Nothing here needs the database to be up until Phase B, and Phase A writes
//  no secret anywhere the web can read.
// ============================================================================

// --- Phase A: database connection ------------------------------------------

// True when config.php is still carrying the shipped placeholders — i.e. no
// real settings have been provided yet, in config.local.php or otherwise.
function setup_config_placeholder() {
    try {
        $cfg = require __DIR__ . '/../config.php';
        $d = $cfg['db'] ?? [];
        return ($d['driver'] ?? '') !== 'sqlite'
            && (($d['user'] ?? '') === 'your_db_user'
                || ($d['name'] ?? '') === 'your_db_name'
                || ($d['pass'] ?? '') === 'your_db_password');
    } catch (Throwable $e) { return true; }
}

// Try a set of database credentials without disturbing the live connection.
// Returns '' on success, or a plain-English reason.
function setup_test_db(array $db) {
    $host = trim((string)($db['host'] ?? 'localhost')) ?: 'localhost';
    $name = trim((string)($db['name'] ?? ''));
    $user = trim((string)($db['user'] ?? ''));
    $pass = (string)($db['pass'] ?? '');
    if ($name === '' || $user === '') return 'The database name and user are both needed.';
    try {
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
        $pdo->query('SELECT 1');
        return '';
    } catch (PDOException $e) {
        $m = $e->getMessage();
        if (stripos($m, 'access denied') !== false || strpos($m, '1045') !== false)
            return 'The database name is right but the user or password is not. Check them under cPanel → Databases.';
        if (stripos($m, 'unknown database') !== false || strpos($m, '1049') !== false)
            return 'The user and password are accepted, but there is no database by that name. On shared hosting the name usually carries an account prefix.';
        if (stripos($m, 'connect') !== false || strpos($m, '2002') !== false)
            return 'The database server could not be reached. On most shared hosting the host is “localhost”.';
        return 'Could not connect: ' . $m;
    }
}

// Write config.local.php next to config.php. Returns '' on success, or a reason
// (the usual one being that the folder is not writable by the web server).
function setup_write_local_config(array $db, ?array $admin = null) {
    $file = __DIR__ . '/../config.local.php';
    $q = function ($s) { return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], (string)$s) . "'"; };
    $lines = [];
    $lines[] = '<' . '?php';   // split so no literal PHP tag sits inside a string
    $lines[] = '// Written by the web setup wizard. Your real settings — kept out of every';
    $lines[] = '// upload, so they are never overwritten. You can also edit it by hand.';
    $lines[] = 'return [';
    $lines[] = '    \'db\' => [';
    $lines[] = '        \'host\' => ' . $q($db['host'] ?? 'localhost') . ',';
    $lines[] = '        \'name\' => ' . $q($db['name'] ?? '') . ',';
    $lines[] = '        \'user\' => ' . $q($db['user'] ?? '') . ',';
    $lines[] = '        \'pass\' => ' . $q($db['pass'] ?? '') . ',';
    $lines[] = '    ],';
    if ($admin && ($admin['user'] ?? '') !== '') {
        $lines[] = '    \'admin\' => [';
        $lines[] = '        \'user\' => ' . $q($admin['user']) . ',';
        $lines[] = '        \'pass\' => ' . $q($admin['pass'] ?? '') . ',';
        $lines[] = '    ],';
    }
    $lines[] = '];';
    $body = implode("\n", $lines) . "\n";
    // Preserve an existing admin block if the caller didn't supply one.
    if (!$admin && is_file($file)) {
        try {
            $cur = require $file;
            if (is_array($cur) && !empty($cur['admin']['user'])) {
                return setup_write_local_config($db, $cur['admin']);
            }
        } catch (Throwable $e) { /* fall through and write db-only */ }
    }
    $ok = @file_put_contents($file, $body);
    if ($ok === false) {
        return 'Could not write config.local.php — the app folder is not writable by the web server. '
             . 'Create the file by hand in cPanel → File Manager instead (see config.local.sample.php).';
    }
    @chmod($file, 0600);
    return '';
}

// Handle the POST from the Phase-A form. Tests the credentials; on success
// writes config.local.php and sends them back to the front page (which now
// connects); on failure re-renders the form with the reason. Always exits.
function setup_handle_db_post() {
    if (($_POST['_csrf'] ?? '') !== ($_SESSION['csrf'] ?? "\0"))
        setup_render_db_form($_POST, 'This form expired. It has been reloaded — please submit it again.', true);
    $db = [
        'host' => trim((string)($_POST['db_host'] ?? 'localhost')) ?: 'localhost',
        'name' => trim((string)($_POST['db_name'] ?? '')),
        'user' => trim((string)($_POST['db_user'] ?? '')),
        'pass' => (string)($_POST['db_pass'] ?? ''),
    ];
    $err = setup_test_db($db);
    if ($err !== '') setup_render_db_form($_POST, $err, true);
    $err = setup_write_local_config($db);
    if ($err !== '') setup_render_db_form($_POST, $err, true);
    header('Location: /');
    exit;
}

// The standalone Phase-A page. Standalone because the database — and therefore
// every setting, including branding — is exactly what is missing here. Always
// exits after rendering.
function setup_render_db_form(array $vals = [], $msg = '', $isError = false) {
    if (!headers_sent()) http_response_code($isError ? 200 : 200);
    $token = function_exists('csrf_token') ? csrf_token() : '';
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES); };
    $host = $vals['db_host'] ?? 'localhost';
    $name = $vals['db_name'] ?? '';
    $user = $vals['db_user'] ?? '';
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Set up the database</title>';
    echo '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:44px auto;padding:26px;border:1px solid #e2ddd6;border-radius:12px;color:#333">';
    echo '<h2 style="margin:0 0 6px;color:#1e40af">Connect your database</h2>';
    echo '<p style="color:#555;font-size:14px;margin:0 0 16px">Enter the database details from '
       . '<b>cPanel → Databases → MySQL Databases</b>. The app will test them and save them into '
       . '<code>config.local.php</code>, which no future upload can overwrite.</p>';
    if ($msg !== '') {
        $bg = $isError ? '#fdecea' : '#eafaf1'; $bd = $isError ? '#e6b0aa' : '#a9dfbf'; $fg = $isError ? '#922b21' : '#1e6b3a';
        echo '<div style="background:' . $bg . ';border:1px solid ' . $bd . ';color:' . $fg . ';padding:10px 12px;border-radius:8px;font-size:14px;margin-bottom:14px">' . $e($msg) . '</div>';
    }
    $inp = 'width:100%;padding:9px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;box-sizing:border-box';
    $lab = 'display:block;font-size:12px;color:#555;font-weight:600;margin:12px 0 4px';
    echo '<form method="post" action="/setup-db">';
    echo '<input type="hidden" name="_csrf" value="' . $e($token) . '">';
    echo '<label style="' . $lab . '">Database name</label><input style="' . $inp . '" name="db_name" value="' . $e($name) . '" placeholder="e.g. mghai_operations" autofocus>';
    echo '<label style="' . $lab . '">Database user</label><input style="' . $inp . '" name="db_user" value="' . $e($user) . '" placeholder="e.g. mghai_admin">';
    echo '<label style="' . $lab . '">Password</label><input style="' . $inp . '" type="password" name="db_pass" placeholder="the database user’s password">';
    echo '<label style="' . $lab . '">Host</label><input style="' . $inp . '" name="db_host" value="' . $e($host) . '" placeholder="localhost">';
    echo '<button type="submit" style="margin-top:18px;width:100%;padding:11px;background:#1e40af;color:#fff;border:0;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer">Test &amp; save</button>';
    echo '</form>';
    echo '<p style="color:#888;font-size:12px;margin-top:14px">Nothing is saved until the test passes. If the folder is not writable, you can still create '
       . '<code>config.local.php</code> by hand — copy <code>config.local.sample.php</code>.</p>';
    echo '</div>';
    exit;
}
