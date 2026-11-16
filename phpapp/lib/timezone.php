<?php
// ============================================================================
//  The application's clock: India time.
//
//  Owner decision T1 (16 Nov 2026). Both hosted sites run on servers whose
//  clock is UTC (deploy-check, 16 Nov 2026), and the app never set a time zone,
//  so every time it recorded or showed was 5 h 30 min behind India and "today"
//  turned over at 05:30 instead of midnight: work done at 02:00 was dated the
//  day before, a CERT-In "noticed at" time read 5½ hours early, and due dates,
//  ageing and month-ends near midnight fell on the wrong day.
//
//  Every time the app uses comes from PHP (no SQL NOW()/CURRENT_TIMESTAMP is
//  used anywhere), so setting PHP's zone once, at the start of every request,
//  cron run and test, is the whole fix. Loaded by lib/db.php, which every entry
//  point (index.php, api.php, cron.php, cron_ads.php, manifest.php) requires
//  first, and read by deploy-check.php to show which zone the app uses.
//
//  Records stored before this change keep the times they were written with:
//  values written with date('c') carry their own "+00:00" and stay exact;
//  plain "Y-m-d H:i:s" values from before the change read 5½ hours early. Both
//  sites held test data only when this was switched on (owner, 16 Nov 2026).
//
//  One zone for the whole platform, because every customer company is in India
//  today. A per-company zone is a later change, if a company abroad signs up.
// ============================================================================

if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Asia/Kolkata');

if (!function_exists('app_timezone_apply')) {
    function app_timezone_apply(): void { date_default_timezone_set(APP_TIMEZONE); }
}

app_timezone_apply();
