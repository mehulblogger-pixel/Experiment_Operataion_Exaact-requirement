<?php
// The inspector voucher is a 12-column editable spreadsheet — unusable behind a
// sideways scroll on a phone. Below 720px the SAME grid reflows into one card per
// day (CSS + data-label only): no change to inputs, names, or the recalc JS, so
// the desktop grid and the save/approve flow are untouched.
t_section('the voucher grid reflows to phone-friendly cards without touching the data flow');

$vd = file_get_contents(__DIR__ . '/../views/ops/voucher_detail.php');

// The mobile reflow exists and is scoped to a media query (desktop unaffected).
t_ok(strpos($vd, '@media (max-width: 720px)') !== false, 'a phone media query drives the reflow');
t_ok(strpos($vd, '.vgrid-head') !== false && strpos($vd, 'class="vgrid-head"') !== false,
    'the column-header row is classed so it can be hidden when stacked');
t_ok(strpos($vd, 'display:block') !== false, 'the grid cells stack (display:block) on a phone');

// Each cell carries a data-label so the stacked card reads "Label: value".
foreach (['Hours', 'KM', 'Mode', 'Line No', 'Attendance / Site'] as $lab) {
    t_ok(strpos($vd, 'data-label="' . $lab) !== false, "cells carry a '$lab' label for the stacked view");
}
// The label is emitted only for non-empty data-label, so action/spacer cells get
// no stray empty label column.
t_ok(strpos($vd, ':not([data-label=""])::before') !== false,
    'empty-labelled cells (actions/spacers) produce no label');

// The recalc JS contract is intact: the classes it queries and writes still exist
// on the EDITABLE rows, so live totals keep working.
foreach (['v-km', 'v-mode', 'v-amt', 'v-travel', 'v-rowtotal', 'v-hours'] as $cls) {
    t_ok(strpos($vd, $cls) !== false, "the recalc hook class .$cls is preserved");
}
t_ok(strpos($vd, "grid.addEventListener('input', recalc)") !== false, 'the live-recalc listener is unchanged');

// Regression guard: the READ-ONLY row total must NOT carry v-rowtotal — the recalc
// script runs on every load and would otherwise find it and overwrite it with a
// zero (read-only rows have no inputs). The editable row total carries the class
// with a data-eid; the read-only one is a plain right-aligned cell.
t_ok(preg_match('/class="v-rowtotal" data-label="Row total [^"]*" data-eid=/', $vd) === 1,
    'the editable row total keeps its recalc class and data-eid');
t_ok(strpos($vd, 'carries NO v-rowtotal class on purpose') !== false,
    'the read-only row total is deliberately left without the recalc class');

// The Date cell is the card title (no repeated label).
t_ok(strpos($vd, 'class="v-date"') !== false, 'the date cell is marked as the card title');
