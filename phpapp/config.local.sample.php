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
    // ---------------------------------------------------------------------
    //  TEST COPY ONLY. On a copy of the live site made for testing, set this to
    //  true. The copy then sends NO e-mail (every message is logged as "not sent")
    //  and shows a banner on every page. Never set it on the live site.
    // ---------------------------------------------------------------------
    // 'test_copy' => true,
    //
    //  On a test copy, e-mail may still go to the TESTERS' OWN addresses (and to
    //  nobody else), so sign-up codes and reminders can be checked in an inbox:
    // 'test_mail_to' => ['you@yourcompany.com', '*@yourcompany.com'],
    //
    //  Where this install keeps its protected data folder. Leave it out normally.
    //  A test copy uses its own folder (exaact_data_test) automatically.
    // 'data_dir' => '/home/YOUR_USER/exaact_data_test',
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
