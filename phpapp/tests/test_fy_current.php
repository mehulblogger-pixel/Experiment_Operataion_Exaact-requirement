<?php
// A global "current financial year" in Settings: changing it changes the year
// every register opens on, while a per-screen ?fy= still overrides it, and
// "Automatic" follows today.
t_section('global current-FY setting drives what registers open on');

// Automatic (default): active FY follows today, and a register with no ?fy=
// opens on it.
setting_set('fy_current', 'AUTO');
unset($_GET['fy']);
t_eq(active_fy(), current_fy(), 'AUTO follows today');
t_ok(!fy_is_pinned(), 'AUTO is not treated as pinned');
t_eq(fy_filter()['fy'], current_fy(), 'a register with no filter opens on this year');

// Pin a specific, different year: registers now open on it, and the pinned year
// is an offered option even with no data in it.
$pinned = fy_label((int)substr(current_fy(), 0, 4) - 2);   // two years back
setting_set('fy_current', $pinned);
t_eq(active_fy(), $pinned, 'pinning a year makes it the active FY');
t_ok(fy_is_pinned(), 'a pinned year that differs from today reads as pinned');
t_eq(fy_filter()['fy'], $pinned, 'a register with no filter now opens on the pinned year');
t_ok(in_array($pinned, fy_options(), true), 'the pinned year is a selectable option even with no data in it');

// A per-screen ?fy= still wins over the pin.
$other = fy_label((int)substr(current_fy(), 0, 4) - 1);
$_GET['fy'] = $other;
t_eq(fy_filter()['fy'], $other, 'a per-screen ?fy= overrides the pinned year');
$_GET['fy'] = 'ALL';
t_ok(fy_filter()['all'], '"All years" still shows everything');
unset($_GET['fy']);

// Garbage / cleared pin falls back to following today.
setting_set('fy_current', 'not-a-year');
t_eq(active_fy(), current_fy(), 'a malformed pin falls back to today');
setting_set('fy_current', 'AUTO');   // leave the box as we found it
