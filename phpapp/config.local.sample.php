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

    // ---------------------------------------------------------------------
    //  OPTIONAL — SaaS one-click "Add a company" (multi-company / cloud mode)
    //
    //  Only needed if you run this as a single-URL SaaS serving many client
    //  companies AND you want the app to create each client's database by
    //  itself (one click, no manual step). This is safe on your OWN server
    //  (a VPS). Provide a MySQL user allowed to CREATE DATABASE and CREATE
    //  USER — on a VPS that is usually 'root' or a dedicated admin user.
    //
    //  Leave this out entirely on shared hosting, or if you prefer to create
    //  each client's database by hand and just point the console at it.
    // ---------------------------------------------------------------------
    // 'saas_db_admin' => [
    //     'host'   => 'localhost',
    //     'user'   => 'root',          // a MySQL user that can create databases + users
    //     'pass'   => 'the-admin-password',
    //     'prefix' => '',              // optional: prepended to every client DB name/user
    // ],
];
