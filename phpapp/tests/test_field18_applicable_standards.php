<?php
// Field-finding #18 — each inspection report should carry a block listing the applicable
// standards (Standard Number, edition, etc.) as a LIST. Implemented as a structured, repeatable
// "Applicable Standards" table (Standard No. · Title/Subject · Edition/Year · Clause(s) applied),
// shipped in the default inspection-report schemas and available as a one-click builder section
// for any existing report type — the same pattern the system already uses for Reference documents.
t_section('Field #18 — Applicable Standards list on inspection reports');

if (function_exists('idems_seed_report_types')) idems_seed_report_types();

// The default inspection-report schema ships the block, as a repeatable table with the asked-for
// columns (a number, an edition/year, and the clauses).
$rows = ops_all(
    "SELECT rt.code, s.title AS section, f.label, f.ftype, f.table_cols
     FROM report_fields f
     JOIN report_sections s ON s.id = f.section_id
     JOIN report_types rt ON rt.id = f.report_type_id
     WHERE f.fkey = 'applicable_standards'");
t_ok(count($rows) >= 1, 'at least one inspection report type ships an Applicable Standards block by default');

$one = $rows[0];
t_eq('Applicable Standards', $one['section'], 'it lives in its own "Applicable Standards" section');
t_eq('table', $one['ftype'], 'it is a repeatable table (a LIST), not a single free-text line');
$cols = $one['table_cols'];
t_ok(stripos($cols, 'Standard No') !== false,   'the list has a Standard Number column');
t_ok(stripos($cols, 'Edition') !== false || stripos($cols, 'Year') !== false, 'the list has an Edition / Year column');
t_ok(stripos($cols, 'Clause') !== false,        'the list has a Clause(s) column');
t_ok(stripos($cols, 'Title') !== false || stripos($cols, 'Subject') !== false, 'the list has a Title / Subject column');

// The one-click builder section exists, guards against duplicates, and uses the same columns —
// so ANY existing report type can gain the block in one click (the retrofit path).
$src = file_get_contents(__DIR__ . '/../lib/idems.php');
t_ok(strpos($src, "\$do === 'add_standards'") !== false, 'the report builder offers a one-click "Applicable Standards" section');
$h = strpos($src, "\$do === 'add_standards'");
$blk = substr($src, $h, 1200);
t_ok(strpos($blk, "COUNT(*) FROM report_sections WHERE report_type_id=? AND title=?") !== false,
     'the one-click add refuses to create a duplicate Applicable Standards section');
t_ok(strpos($blk, "'applicable_standards'") !== false && strpos($blk, 'Standard No.|merge') !== false,
     'the one-click add inserts the applicable_standards list with the same columns');

// The builder palette shows the button.
$builder = file_get_contents(__DIR__ . '/../views/ops/idems/builder.php');
t_ok(strpos($builder, "'add_standards'") !== false && stripos($builder, 'Applicable Standards') !== false,
     'the builder palette offers the Applicable Standards section');
