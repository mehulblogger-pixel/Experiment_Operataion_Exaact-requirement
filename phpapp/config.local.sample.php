<?php
// =========================================================================
//  YOUR SERVER'S REAL SETTINGS
//
//  1. Copy this file and rename the copy to  config.local.php
//     (cPanel File Manager -> right-click -> Copy, then rename to
//      config.local.php).
//  2. Fill in the four values below with your real details.
//  3. Save.
//
//  config.local.php is NEVER part of an upload from the developer, so once it
//  exists your database and admin login are safe -- you can upload any other
//  file (including config.php) without ever wiping these again.
//
//  Where to find the database values: cPanel -> Databases -> MySQL Databases.
// =========================================================================
return [
    'db' => [
        'name' => 'your_db_name',     // the real MySQL database name
        'user' => 'your_db_user',     // the real MySQL user
        'pass' => 'your_db_password', // that user's password
    ],
    'admin' => [
        'user' => 'admin',            // your admin login name (usually 'admin')
        'pass' => 'admin12345',       // <-- change to your chosen admin password
    ],
];
