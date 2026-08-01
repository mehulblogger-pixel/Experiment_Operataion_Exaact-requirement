<?php
// =========================================================================
//  TEMPLATE DEFAULTS — placeholders only.
//
//  Do NOT put your real database or admin details here. This file is part of
//  the application and gets replaced whenever you upload a new version, so
//  anything you type here would be wiped on the next upload.
//
//  Your REAL settings go in  config.local.php  (see the block lower down).
//  That file is never part of an upload, so your database name, user, password
//  and admin login stay safe no matter what else you upload.
// =========================================================================
$DB = [
    'driver' => 'mysql',            // keep 'mysql' on the server
    'host'   => 'localhost',
    'name'   => 'your_db_name',     // <-- set the real one in config.local.php
    'user'   => 'your_db_user',     // <-- set the real one in config.local.php
    'pass'   => 'your_db_password', // <-- set the real one in config.local.php
];

//  Default admin login. The real password should be set in config.local.php.
$ADMIN = ['user' => 'admin', 'pass' => 'admin12345'];

// =========================================================================
//  config.local.php — YOUR server's real settings, kept OUT of every upload.
//
//  If a file named  config.local.php  sits next to this one, the values in it
//  win over the placeholders above. This is what makes uploading files safe:
//  your database and admin details live only in config.local.php, which the
//  developer never sends you, so an upload can never overwrite them.
//
//  config.local.php looks like this (fill in your own values):
//
//    <?php
//    return [
//      'db'    => ['name' => 'mghai_ops', 'user' => 'mghai_user', 'pass' => 'secret'],
//      'admin' => ['user' => 'admin', 'pass' => 'your-admin-password'],
//    ];
//
//  See config.local.sample.php in this folder for a ready-to-copy version.
// =========================================================================
$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        if (!empty($local['db'])    && is_array($local['db']))    $DB    = array_merge($DB, $local['db']);
        if (!empty($local['admin']) && is_array($local['admin'])) $ADMIN = array_merge($ADMIN, $local['admin']);
        if (!empty($local['sqlite_path'])) $SQLITE_LOCAL = $local['sqlite_path'];
    }
}

// Optional: environment variables override everything above (used for testing).
foreach (['driver'=>'DB_DRIVER','host'=>'DB_HOST','name'=>'DB_NAME','user'=>'DB_USER','pass'=>'DB_PASS'] as $k=>$e) {
    $v = getenv($e); if ($v !== false && $v !== '') $DB[$k] = $v;
}
if (getenv('ADMIN_PASSWORD')) $ADMIN['pass'] = getenv('ADMIN_PASSWORD');

// The SQLite file can be pointed elsewhere the same way, so a check can build a
// throwaway database instead of touching the one in use.
$SQLITE = getenv('SQLITE_PATH');
if (!$SQLITE && isset($SQLITE_LOCAL)) $SQLITE = $SQLITE_LOCAL;
if (!$SQLITE) $SQLITE = __DIR__ . '/data.sqlite';

return ['db' => $DB, 'admin' => $ADMIN, 'sqlite_path' => $SQLITE];
