<?php
// =========================================================================
//  MGH Hire — configuration (TEMPLATE DEFAULTS, placeholders only)
//
//  Do NOT put real database or admin details here. This file ships with the
//  product and is replaced on every upgrade, so anything typed here is wiped
//  on the next upload.
//
//  Your REAL settings live in  config.local.php  (never part of an upgrade).
//  Copy config.local.sample.php to config.local.php and fill it in.
// =========================================================================

$DB = [
    'driver' => 'sqlite',           // 'sqlite' (laptop / small install) or 'mysql'
    'host'   => 'localhost',
    'name'   => 'mghhire',
    'user'   => 'mghhire',
    'pass'   => 'change-me',
];

// Default first-run administrator. Set the real password in config.local.php.
$ADMIN = ['user' => 'admin', 'name' => 'Administrator', 'pass' => 'admin12345'];

// ---- config.local.php wins over everything above -------------------------
$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        if (!empty($local['db'])    && is_array($local['db']))    $DB    = array_merge($DB, $local['db']);
        if (!empty($local['admin']) && is_array($local['admin'])) $ADMIN = array_merge($ADMIN, $local['admin']);
        if (!empty($local['sqlite_path'])) $SQLITE_LOCAL = $local['sqlite_path'];
    }
}

// ---- Environment variables override (used by the laptop launchers/tests) --
foreach (['driver'=>'DB_DRIVER','host'=>'DB_HOST','name'=>'DB_NAME','user'=>'DB_USER','pass'=>'DB_PASS'] as $k=>$e) {
    $v = getenv($e); if ($v !== false && $v !== '') $DB[$k] = $v;
}
if (getenv('ADMIN_PASSWORD')) $ADMIN['pass'] = getenv('ADMIN_PASSWORD');

// ---- SQLite file location -------------------------------------------------
$SQLITE = getenv('SQLITE_PATH');
if (!$SQLITE && isset($SQLITE_LOCAL)) $SQLITE = $SQLITE_LOCAL;
if (!$SQLITE) $SQLITE = __DIR__ . '/data.sqlite';

return [
    'db'          => $DB,
    'admin'       => $ADMIN,
    'sqlite_path' => $SQLITE,
];
