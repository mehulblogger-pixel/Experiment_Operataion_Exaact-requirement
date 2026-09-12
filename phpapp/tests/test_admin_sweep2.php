<?php
// Second admin-gating sweep: custom-form nav groups follow the plan.
t_section('Admin sweep 2 — custom-form nav groups');
if (!function_exists('cform_nav_groups')) { t_ok(true, 'cform_nav_groups not present — skipped'); return; }

$savedOff = (string) setting_get('modules_off', '');
$savedKey = (string) setting_get('licence_key', '');
setting_set('licence_key', ''); if (function_exists('lk_state')) lk_state(true);
setting_set('modules_off', 'sales,operations,money,reporting');
if (function_exists('licence_disabled')) licence_disabled(true);

$g = cform_nav_groups();
t_ok(in_array('Directory', $g, true) && in_array('Insights', $g, true) && in_array('My work', $g, true),
    'generic groups (Directory / Insights / My work) are always offered');
t_ok(!in_array('Operations', $g, true) && !in_array('Sales', $g, true) && !in_array('Reporting', $g, true)
    && !in_array('Money', $g, true) && !in_array('Quality & accreditation', $g, true),
    'module-specific groups are hidden for a recruitment plan');

// With everything on (control-style), all groups return.
setting_set('modules_off', ''); if (function_exists('licence_disabled')) licence_disabled(true);
t_ok(in_array('Operations', cform_nav_groups(), true), 'a full plan sees every group');

setting_set('modules_off', $savedOff);
setting_set('licence_key', $savedKey);
if (function_exists('lk_state')) lk_state(true);
if (function_exists('licence_disabled')) licence_disabled(true);
t_ok(true, 'state restored');
