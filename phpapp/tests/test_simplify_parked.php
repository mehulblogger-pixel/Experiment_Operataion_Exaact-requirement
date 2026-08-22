<?php
// Simplification, step 1 (resolved) — exact duplicates were parked in one place
// for review, then dropped after review. The duplicate MENU tiles are gone; the
// real screens keep their canonical homes and their routes are untouched.
t_section('parked duplicates resolved — dropped (simplify step 1)');

$a = (string)file_get_contents(__DIR__ . '/../lib/areas.php');

// The holding section and both duplicate tiles are gone.
t_ok(strpos($a, 'Parked — duplicates & extras') === false,
    'the Parked holding section has been removed after review');
t_ok(strpos($a, "'Analytics — duplicate', '/analytics'") === false,
    'the Analytics duplicate tile is dropped');
t_ok(strpos($a, "'Vendor register — duplicate', '/vendors'") === false,
    'the Vendor register duplicate tile is dropped');

// The secondary copies never came back to their old areas.
t_ok(strpos($a, "'Analytics', '/analytics', 'KPI dashboards and drill-down.'") === false,
    'Analytics did not return to Quality');
t_ok(strpos($a, "'Vendor register', '/vendors', 'Vendors and their profiles.'") === false,
    'the Vendor register did not return to Reporting');

// The canonical homes still have them (Insights analytics, Directory vendors),
// so nothing was lost — only the duplicate shortcut was removed.
t_ok(strpos($a, "'Analytics & performance', '/analytics'") !== false,
    'Analytics still lives in Insights (its home)');
t_ok(strpos($a, "T_REG('vendor'), '/vendors'") !== false,
    'Vendors still lives in Directory (its home)');
