<?php
// Owner decision T1 (16 Nov 2026): the app runs on India time. Both hosted sites
// have a UTC server clock and the app used to set no zone, so every recorded and
// shown time was 5 h 30 min behind India and "today" turned over at 05:30.
// lib/timezone.php sets Asia/Kolkata and lib/db.php loads it first, so every
// entry point that loads the app gets India time. This proves the zone is set,
// that the dates the app writes use it, that the zone holds whatever the server
// default is, and that the deploy-check page reads (and reports) it.
t_section('app clock: India time (owner decision T1)');

t_ok(defined('APP_TIMEZONE') && APP_TIMEZONE === 'Asia/Kolkata', 'APP_TIMEZONE is Asia/Kolkata');
t_ok(date_default_timezone_get() === 'Asia/Kolkata', 'the booted app runs on Asia/Kolkata');
t_ok(date('P') === '+05:30', 'date() writes the India offset (+05:30)');
t_ok(substr(date('c'), -6) === '+05:30', "date('c') — the app's usual record stamp — carries +05:30");

// A moment that is still "yesterday" in UTC but already "today" in India: the
// case the owner's sites got wrong (work done between midnight and 05:30).
$ts = gmmktime(20, 0, 0, 11, 16, 2026);                                // 16 Nov 20:00 UTC = 17 Nov 01:30 IST
t_ok(date('Y-m-d', $ts) === '2026-11-17', 'a moment after Indian midnight is dated the Indian day (17 Nov, not 16 Nov)');
t_ok(date('H:i', $ts) === '01:30', 'and shows the Indian clock time (01:30, not 20:00)');

// The zone is set by the app, not borrowed from the server: switch the process
// to UTC (what both servers are) and apply again.
date_default_timezone_set('UTC');
app_timezone_apply();
t_ok(date_default_timezone_get() === 'Asia/Kolkata', 'applying the app clock overrides a UTC server default');

// Durations are unaffected: the six-hour CERT-In window is an instant + 6 h.
$noticed = strtotime('2026-11-17T01:30:00+05:30');
t_ok(date('c', $noticed + 6 * 3600) === '2026-11-17T07:30:00+05:30', 'a six-hour deadline lands six hours later on the India clock');

// Records written before the change with date('c') keep their own offset and
// still read as the correct instant.
t_ok(strtotime('2026-11-16T20:00:00+00:00') === $ts, 'an older UTC-stamped record (date(\'c\') with +00:00) still reads as the same instant');

// deploy-check.php reads the zone from lib/timezone.php (without loading it).
$tpl = (string) file_get_contents(__DIR__ . '/../tools/deploy_check_template.php');
t_ok(strpos($tpl, "lib/timezone.php") !== false && strpos($tpl, 'the app uses ') !== false,
    'the deploy-check page reports the clock the app uses');
$src = (string) file_get_contents(__DIR__ . '/../lib/timezone.php');
t_ok((bool) preg_match("/define\\('APP_TIMEZONE',\\s*'([A-Za-z_\\/+-]+)'\\)/", $src, $m) && $m[1] === 'Asia/Kolkata',
    'the deploy-check pattern finds Asia/Kolkata in lib/timezone.php');
