<?php
// Simplification, step 1 — exact duplicates are removed from their secondary
// area and collected in a single "Parked (to review)" section. Nothing deleted:
// the real screen still lives in its home and the route is untouched.
t_section('parked duplicates (simplify step 1)');

$a = (string)file_get_contents(__DIR__ . '/../lib/areas.php');

t_ok(strpos($a, 'Parked — duplicates & extras (to review)') !== false,
    'a single Parked holding section exists');
t_ok(strpos($a, "'Analytics — duplicate', '/analytics'") !== false,
    'the Analytics duplicate is parked (route intact)');
t_ok(strpos($a, "'Vendor register — duplicate', '/vendors'") !== false,
    'the Vendor register duplicate is parked (route intact)');

// The secondary copies are gone from their old areas…
t_ok(strpos($a, "'Analytics', '/analytics', 'KPI dashboards and drill-down.'") === false,
    'the Analytics tile is removed from Quality');
t_ok(strpos($a, "'Vendor register', '/vendors', 'Vendors and their profiles.'") === false,
    'the Vendor register tile is removed from Reporting');

// …but the canonical homes still have them (Insights analytics, Directory vendors).
t_ok(strpos($a, "'Analytics & performance', '/analytics'") !== false,
    'Analytics still lives in Insights (its home)');
t_ok(strpos($a, "T_REG('vendor'), '/vendors'") !== false,
    'Vendors still lives in Directory (its home)');
