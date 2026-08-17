<?php
// A ready-to-use Fire Extinguisher Inspection Report type: a fully-designed
// form so it renders like every other report (never the blank "no form designed
// yet" screen). Guards that it builds with all its sections/fields, reuses the
// autofill header keys, and is idempotent.
t_section('fire extinguisher inspection report type');

$tid = idems_build_fire_extinguisher_report();
t_ok($tid > 0, 'the fire extinguisher report type is created');
$type = ops_one("SELECT * FROM report_types WHERE id=?", [$tid]);
t_eq((string)$type['code'], 'FEXT', 'it carries the FEXT code');
t_ok(stripos($type['name'], 'fire extinguisher') !== false, 'it is named for fire extinguishers');

// It has a real form — so it is NOT a formless report type.
t_ok(idems_has_schema($tid), 'the type has a designed form (schema)');

$secs = idems_sections($tid);
$secTitles = array_map(fn($s) => $s['title'], $secs);
foreach (['Inspection details', 'Reference documents', 'Fire extinguisher schedule',
          'Pressure', 'Instruments', 'Observations', 'Photographs', 'Sign-off'] as $need) {
    $hit = false; foreach ($secTitles as $t) if (stripos($t, $need) !== false) { $hit = true; break; }
    t_ok($hit, "it has a '$need' section");
}

$fields = idems_fields($tid);
$keys = array_map(fn($f) => $f['fkey'], $fields);

// The header uses the SAME keys as the standard report, so identity autofills.
foreach (['client', 'vendor', 'po_number', 'project', 'inspection_date', 'inspector'] as $k)
    t_ok(in_array($k, $keys, true), "the header field '$k' is present (autofills from the job)");

// Fire-extinguisher-specific content is captured.
t_ok(in_array('fe_items', $keys, true), 'there is an extinguisher schedule table');
t_ok(in_array('fe_tests', $keys, true), 'there is a pressure / weight / refill record table');
t_ok(in_array('applicable_standard', $keys, true), 'the applicable standard is captured');
$chk = array_filter($keys, fn($k) => strncmp($k, 'chk_', 4) === 0);
t_ok(count($chk) >= 12, 'the physical & functional checklist spells out every check point (' . count($chk) . ')');

// The tables and sign-off/photos use the right field types.
$byKey = [];
foreach ($fields as $f) $byKey[$f['fkey']] = $f;
t_eq((string)$byKey['fe_items']['ftype'], 'table', 'the extinguisher schedule is a table');
t_eq((string)$byKey['photos']['ftype'], 'photo', 'photographs use the photo field');
t_eq((string)$byKey['signoff']['ftype'], 'sigblock', 'the sign-off is a per-role sign-off block');

// The extinguisher table offers the type & capacity dropdowns.
$defs = idems_table_col_defs($byKey['fe_items']);
$labels = array_map(fn($d) => $d['label'], $defs);
t_ok(in_array('Type', $labels, true) && in_array('Capacity', $labels, true), 'the schedule has Type & Capacity columns');
$typeCol = null; foreach ($defs as $d) if ($d['label'] === 'Type') $typeCol = $d;
t_ok(is_array($typeCol) && $typeCol['type'] === 'select' && count($typeCol['options']) >= 5,
    'the Type column is a dropdown of extinguisher types (' . count($typeCol['options'] ?? []) . ')');

// Idempotent — building again returns the same type, no duplicate.
$before = (int) ops_val("SELECT COUNT(*) FROM report_types WHERE code='FEXT'");
$tid2 = idems_build_fire_extinguisher_report();
$after = (int) ops_val("SELECT COUNT(*) FROM report_types WHERE code='FEXT'");
t_eq($tid2, $tid, 'building again returns the same type');
t_eq($after, $before, 'no duplicate type is created');
