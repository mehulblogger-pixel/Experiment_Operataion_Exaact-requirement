<?php
// =========================================================================
//  DATABASE SETTINGS — edit these for your MilesWeb MySQL database
//  (create the database + user under  MilesWeb panel → Databases)
// =========================================================================
$DB = [
    'driver' => 'mysql',            // keep 'mysql' on the server
    'host'   => 'localhost',
    'name'   => 'your_db_name',     // <-- your MySQL database name
    'user'   => 'your_db_user',     // <-- your MySQL user
    'pass'   => 'your_db_password', // <-- your MySQL password
];

//  Your admin login. Change the password after first sign-in (in the app).
$ADMIN = ['user' => 'admin', 'pass' => 'admin12345'];
// =========================================================================

// Optional: environment variables override the values above (used for testing).
foreach (['driver'=>'DB_DRIVER','host'=>'DB_HOST','name'=>'DB_NAME','user'=>'DB_USER','pass'=>'DB_PASS'] as $k=>$e) {
    $v = getenv($e); if ($v !== false && $v !== '') $DB[$k] = $v;
}
if (getenv('ADMIN_PASSWORD')) $ADMIN['pass'] = getenv('ADMIN_PASSWORD');

// The SQLite file can be pointed elsewhere the same way, so a check can build a
// throwaway database instead of touching the one in use.
$SQLITE = getenv('SQLITE_PATH');
if (!$SQLITE) $SQLITE = __DIR__ . '/data.sqlite';

return ['db' => $DB, 'admin' => $ADMIN, 'sqlite_path' => $SQLITE];
