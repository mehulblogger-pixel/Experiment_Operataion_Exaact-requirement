<?php
// ============================================================================
//  What this build is, and whether this server can run it
//
//  Two things a shipped product needs and this one did not have:
//
//   1. A VERSION. Without it nobody can answer "which build are you running?",
//      which is the first question on every support call. It is a constant in
//      this file rather than a setting, because it describes the CODE, not the
//      installation — a database restored onto older files must not claim to be
//      the newer version.
//
//   2. A PRE-FLIGHT CHECK. The requirements were written in INSTALL.md, which
//      is read after something has already gone wrong. This lists them against
//      what the server actually has, in the app, before anybody reports a
//      mystery. Optional extensions are shown as what you lose, not as errors,
//      because the app really does keep working without them.
//
//  The check is deliberately reachable while signed in only — it describes the
//  server, and that is not something to hand to a stranger.
// ============================================================================

// Bump on every release. Date-based: a support call quotes it and the date is
// immediately meaningful, which "2.14.3" is not to the person reading it out.
const APP_VERSION = '2026.07.1';
const APP_VERSION_DATE = '2026-07-27';

// [key, label, required?, what you lose without it]
function preflight_checks() {
    $php   = PHP_VERSION;
    $phpOk = version_compare($php, '8.0.0', '>=');
    $out = [
        ['php', 'PHP ' . $php, $phpOk, true,
         $phpOk ? 'PHP 8.0 or newer' : 'This app needs PHP 8.0 or newer. Ask the host to switch the version.'],
    ];
    $need = [
        ['pdo',      'PDO',            true,  'The app cannot talk to a database at all.'],
        ['json',     'JSON',           true,  'Nothing works without it.'],
        ['mbstring', 'mbstring',       true,  'Names and units with accents or symbols break.'],
        ['session',  'Sessions',       true,  'Nobody can stay signed in.'],
        ['openssl',  'OpenSSL',        true,  'Passwords and two-step sign-in are weakened.'],
        ['zip',      'Zip',            false, 'Word (.docx) report and quotation export is switched off.'],
        ['gd',       'GD (images)',    false, 'Photos are stored uncompressed and PNG signatures cannot be drawn on PDFs.'],
        ['curl',     'cURL',           false, 'The optional AI writing assistant cannot reach its provider.'],
        ['fileinfo', 'Fileinfo',       false, 'Upload type-checking falls back to a simpler test.'],
    ];
    foreach ($need as [$ext, $label, $req, $lose]) {
        $have = extension_loaded($ext);
        $out[] = [$ext, $label, $have, $req, $have ? '' : $lose];
    }
    // The driver actually in use has to be present, not just PDO itself.
    $drv = function_exists('db_driver') ? db_driver() : 'mysql';
    $dext = $drv === 'sqlite' ? 'pdo_sqlite' : 'pdo_mysql';
    $have = extension_loaded($dext);
    $out[] = [$dext, $dext . ' (this install uses ' . $drv . ')', $have, true,
              $have ? '' : 'config.php says driver "' . $drv . '" but that PDO driver is not installed.'];

    // Uploads: the app's own limit is useless if the web server rejects first.
    $appMb = function_exists('upload_max_mb') ? upload_max_mb() : 12;
    $iniUp = preflight_bytes(ini_get('upload_max_filesize'));
    $iniPost = preflight_bytes(ini_get('post_max_size'));
    $upOk = $iniUp >= $appMb * 1024 * 1024 && $iniPost >= $appMb * 1024 * 1024;
    $out[] = ['uploads', 'Upload limits (php.ini ' . ini_get('upload_max_filesize') . ' / ' . ini_get('post_max_size') . ')',
              $upOk, false,
              $upOk ? '' : 'The app allows ' . $appMb . ' MB but php.ini stops it sooner, so large files fail at the '
                         . 'web server before the app sees them. Raise upload_max_filesize and post_max_size.'];

    // HTTPS: not a PHP thing, but the single most consequential one.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $out[] = ['https', 'HTTPS', $https, false,
              $https ? '' : 'Sessions travel in clear text, and the installable phone app will NOT work — '
                          . 'browsers refuse a service worker over plain HTTP.'];
    return $out;
}

function preflight_bytes($v) {
    $v = trim((string)$v);
    if ($v === '') return 0;
    $n = (float)$v;
    switch (strtolower(substr($v, -1))) {
        case 'g': return (int)($n * 1024 * 1024 * 1024);
        case 'm': return (int)($n * 1024 * 1024);
        case 'k': return (int)($n * 1024);
    }
    return (int)$n;
}

// Anything required and missing. Empty means the server can run this build.
function preflight_blockers() {
    $bad = [];
    foreach (preflight_checks() as [$k, $label, $ok, $req, $note])
        if ($req && !$ok) $bad[] = $label;
    return $bad;
}

function ops_preflight() {
    ops_require(is_admin_level() || is_master(), 'Only a manager can see the server check.');
    view('ops/preflight', [
        'checks'  => preflight_checks(),
        'version' => APP_VERSION,
        'built'   => APP_VERSION_DATE,
        'driver'  => function_exists('db_driver') ? db_driver() : '—',
        'modules' => function_exists('licence_summary') ? licence_summary() : [],
    ]);
}
