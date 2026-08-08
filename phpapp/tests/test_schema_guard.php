<?php
// Partial-upload safety net: a dynamic INSERT/UPDATE must skip a column that is
// missing on the table (some files uploaded, some not) instead of crashing the
// whole save. This is what turned the nil_chargeable_heads / issued_licences
// class of crash into a graceful degrade.
t_section('Schema guard — existing_columns_only');

// A throwaway table with a known set of columns.
db()->exec("CREATE TABLE IF NOT EXISTS sg_probe (id INTEGER PRIMARY KEY, a TEXT, b TEXT)");

$have = table_columns('sg_probe');
t_ok(isset($have['a']) && isset($have['b']), 'table_columns lists real columns');
t_ok(!isset($have['zzz']), 'table_columns does not invent a column');

// A field list mixing real and (not-yet-uploaded) columns is filtered to real.
$fields = ['a', 'b', 'not_yet_migrated', 'also_missing'];
$kept = existing_columns_only('sg_probe', $fields);
t_eq(implode(',', $kept), 'a,b', 'a missing column is dropped from the save list');

// If introspection finds nothing (unknown table), the list is returned intact —
// never blank a save on a false negative.
$kept2 = existing_columns_only('sg_no_such_table', ['x', 'y']);
t_eq(implode(',', $kept2), 'x,y', 'an unknown table returns the list unchanged (no false blanking)');

// The real save lists are all present on the seeded schema, so nothing is dropped
// in a complete install.
t_eq(count(existing_columns_only('calls', call_save_fields())), count(call_save_fields()), 'a complete calls table keeps every save field');
t_eq(count(existing_columns_only('jobs', job_save_fields())), count(job_save_fields()), 'a complete jobs table keeps every save field');
