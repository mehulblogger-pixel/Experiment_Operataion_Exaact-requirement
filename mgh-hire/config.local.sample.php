<?php
// =========================================================================
//  MGH Hire — YOUR real settings.  Copy this file to  config.local.php
//  and fill in your own values.  config.local.php is NEVER overwritten by an
//  upgrade, so your database and admin login are always safe.
// =========================================================================
return [

    // --- Database ---------------------------------------------------------
    // Small install / laptop: leave driver as 'sqlite' (nothing else needed).
    // Busy multi-user site: use 'mysql' and fill in the four fields.
    'db' => [
        'driver' => 'sqlite',            // 'sqlite' or 'mysql'
        // 'host' => 'localhost',
        // 'name' => 'your_db_name',
        // 'user' => 'your_db_user',
        // 'pass' => 'your_db_password',
    ],

    // --- First-run administrator -----------------------------------------
    'admin' => [
        'user' => 'admin',
        'name' => 'Administrator',
        'pass' => 'change-this-before-first-login',
    ],

    // Optional: point the SQLite database file somewhere outside the web root.
    // 'sqlite_path' => '/var/data/mghhire/data.sqlite',
];
