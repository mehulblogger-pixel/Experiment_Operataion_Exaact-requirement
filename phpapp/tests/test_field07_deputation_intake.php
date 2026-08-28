<?php
// Field-finding #7 — lead intake for manpower deputation. A lead can be for man-day work (a short
// assignment, no fixed site) or a man-month deputation (people placed at the customer's site). The
// intake captures the type, how many people, and the skills/qualifications; and — conditionally —
// a man-month REQUIRES the site details, while a man-day carries none.
t_section('Field #7 — manday/manmonth deputation intake (conditional site details)');

leads_migrate();

// The columns exist after migrate.
$cols = array_map(fn($r) => $r['name'], ops_all("PRAGMA table_info(leads)"));
foreach (['deputation_kind','manpower_count','manpower_skills','site_location'] as $c)
    t_ok(in_array($c, $cols, true), "leads.$c column exists");

// A man-day lead: no site needed; any site typed is dropped (man-day carries none).
$r = lead_create(['company_name' => 'Manday Co', 'deputation_kind' => 'MANDAY',
                  'manpower_count' => 2, 'manpower_skills' => 'NDT Level II', 'site_location' => 'ignored']);
t_ok(!empty($r['id']), 'a man-day lead saves without site details');
$row = ops_one("SELECT deputation_kind, manpower_count, manpower_skills, site_location FROM leads WHERE id=?", [$r['id']]);
t_eq('MANDAY', $row['deputation_kind'], 'the deputation type is stored');
t_eq(2, (int)$row['manpower_count'], 'the headcount is stored');
t_eq('NDT Level II', $row['manpower_skills'], 'the skills are stored');
t_eq('', (string)$row['site_location'], 'a man-day lead carries no site, even if one was typed');

// A man-month lead WITHOUT site is refused.
$bad = lead_create(['company_name' => 'Manmonth Co', 'deputation_kind' => 'MANMONTH', 'manpower_count' => 4]);
t_ok(!empty($bad['err']) && stripos($bad['err'], 'site') !== false,
     'a man-month deputation without site details is refused');

// A man-month lead WITH site saves and keeps everything.
$ok = lead_create(['company_name' => 'Manmonth Co', 'deputation_kind' => 'MANMONTH', 'manpower_count' => 4,
                   'manpower_skills' => '2× welding inspector CSWIP', 'site_location' => 'Dahej plant, Gujarat']);
t_ok(!empty($ok['id']), 'a man-month lead with site details saves');
$row2 = ops_one("SELECT deputation_kind, manpower_count, site_location FROM leads WHERE id=?", [$ok['id']]);
t_eq('MANMONTH', $row2['deputation_kind'], 'man-month type stored');
t_eq('Dahej plant, Gujarat', $row2['site_location'], 'the site details are kept for a man-month');

// A lead that is not a deputation stores a blank type (unchanged behaviour for ordinary leads).
$plain = lead_create(['company_name' => 'Plain Co']);
t_eq('', (string)ops_val("SELECT deputation_kind FROM leads WHERE id=?", [$plain['id']]), 'an ordinary lead has no deputation type');
// An unrecognised type is treated as none, never stored raw.
$junk = lead_create(['company_name' => 'Junk Co', 'deputation_kind' => 'WEEKLY']);
t_eq('', (string)ops_val("SELECT deputation_kind FROM leads WHERE id=?", [$junk['id']]), 'an unknown deputation type is dropped');

// The form captures it, with the conditional site block wired.
$form = file_get_contents(__DIR__ . '/../views/ops/lead_form.php');
t_ok(strpos($form, 'name="deputation_kind"') !== false && strpos($form, 'name="manpower_count"') !== false
     && strpos($form, 'name="manpower_skills"') !== false && strpos($form, 'name="site_location"') !== false,
     'the lead form captures type, count, skills and site');
t_ok(strpos($form, "sel.value === 'MANMONTH'") !== false && strpos($form, 'inp.required = mm') !== false,
     'the site block is shown and required only for a man-month');

// The edit handler enforces the same rule (man-month needs a site).
$src = file_get_contents(__DIR__ . '/../lib/leads.php');
t_ok(substr_count($src, "\$dep === 'MANMONTH' && \$site === ''") >= 2,
     'both create and edit enforce "man-month requires site"');

// The detail surfaces it.
$det = file_get_contents(__DIR__ . '/../views/ops/lead_detail.php');
t_ok(strpos($det, 'Manpower deputation') !== false && strpos($det, "LEAD_DEPUTATION[\$l['deputation_kind']]") !== false,
     'the lead detail shows the deputation when present');

// Clean up (shared DB, no rollback).
db()->prepare("DELETE FROM leads WHERE company_name IN ('Manday Co','Manmonth Co','Plain Co','Junk Co')")->execute();
