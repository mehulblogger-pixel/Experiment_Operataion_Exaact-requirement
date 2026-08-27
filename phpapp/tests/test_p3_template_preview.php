<?php
// Phase 3 §8 — report-template persona preview. Shows, for a report type, which fields the recipient
// (client/vendor) sees on the finished report versus which are internal-only, and flags conditional /
// scored fields — from the columns the template already stores (hidden, cond_field, weight/max_score).
// Read-only. Self-contained.
t_section('Phase 3 §8 — report-template persona preview');

t_ok(function_exists('template_persona_preview') && function_exists('template_field_persona') && function_exists('ops_template_preview'),
     'the preview helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/tmplpreview.php'") !== false, 'the preview lib is loaded by the front controller');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "case \$route === 'report-preview'") !== false, 'the /report-preview route is dispatched');
$bld = file_get_contents(__DIR__ . '/../views/ops/idems/builder.php');
t_ok(strpos($bld, '/report-preview?type=') !== false, 'the builder links to the persona preview');

// Field classification is read straight from the stored columns.
t_eq(template_field_persona(['hidden' => 0])['persona'], 'RECIPIENT', 'a visible field is shown to the recipient');
t_eq(template_field_persona(['hidden' => 1])['persona'], 'INTERNAL', 'a hidden field is internal-only');
t_ok(template_field_persona(['cond_field' => 'x'])['conditional'] === true, 'a field with a condition is flagged conditional');
t_ok(template_field_persona(['max_score' => 5])['scored'] === true, 'a field with a max score is flagged scored');
t_ok(template_field_persona(['weight' => 0, 'max_score' => 0])['scored'] === false, 'an unscored field is not flagged');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('idems_migrate')) idems_migrate();
    $pdo = db();
    $pdo->prepare("INSERT INTO report_types (code, name, active) VALUES ('T8','Persona Test', 1)")->execute();
    $tid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_sections (report_type_id, title, sort_order) VALUES (?, 'Findings', 10)")->execute([$tid]);
    $sid = (int)$pdo->lastInsertId();
    // Three fields: one recipient-visible, one internal (hidden), one conditional+scored.
    $pdo->prepare("INSERT INTO report_fields (report_type_id, section_id, fkey, label, ftype, hidden, sort_order) VALUES (?,?, 'obs','Observation','text', 0, 10)")->execute([$tid, $sid]);
    $pdo->prepare("INSERT INTO report_fields (report_type_id, section_id, fkey, label, ftype, hidden, sort_order) VALUES (?,?, 'calc','Internal calc','number', 1, 20)")->execute([$tid, $sid]);
    $pdo->prepare("INSERT INTO report_fields (report_type_id, section_id, fkey, label, ftype, hidden, cond_field, max_score, sort_order) VALUES (?,?, 'grade','Grade','select', 0, 'obs', 5, 30)")->execute([$tid, $sid]);

    $pv = template_persona_preview($tid);
    t_ok($pv !== null && count($pv['sections']) === 1, 'the template preview loads its one section');
    t_eq($pv['counts']['fields'], 3, 'all three fields are counted');
    t_eq($pv['counts']['recipient'], 2, 'two fields reach the recipient (observation + grade)');
    t_eq($pv['counts']['internal'], 1, 'one field is internal-only (the hidden calc)');
    t_eq($pv['counts']['conditional'], 1, 'one field is conditional (the grade)');
    t_eq($pv['counts']['scored'], 1, 'one field is scored (the grade)');

    // The internal calc field must never be classed as recipient-visible (the leak this preview catches).
    $calc = null;
    foreach ($pv['sections'][0]['fields'] as $f) if ($f['label'] === 'Internal calc') $calc = $f;
    t_eq($calc['persona'], 'INTERNAL', 'the hidden calc is flagged internal, not shown to the recipient');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
