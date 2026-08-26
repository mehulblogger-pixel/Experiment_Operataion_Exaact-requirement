<?php
// Module 48 — Report template builder. The .docx template has validation + a draft→review→approve
// lifecycle; the FORM SCHEMA had neither, so a duplicate field key or an option-less choice could go
// live and silently break report entry. Add idems_format_validate() — the missing twin — as a
// read-only warn/preview. First coverage of format integrity.
t_section('Module 48 — report-format integrity validation');

$idems = file_get_contents(__DIR__ . '/../lib/idems.php');

t_ok(function_exists('idems_format_validate'), 'idems_format_validate() exists');
t_ok(function_exists('idems_template_validate'), 'the .docx template validator (its twin) still exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('idems_migrate')) idems_migrate();
    $pdo = db();

    $pdo->prepare("INSERT INTO report_types (code, name, active) VALUES ('FMT-A','Format A',1)")->execute();
    $tid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_sections (report_type_id, title, sort_order) VALUES (?, 'Sec', 1)")->execute([$tid]);
    $sid = (int)$pdo->lastInsertId();
    $mkF = function ($fkey, $ftype, $opts = '', $req = 0, $cond = '', $tcols = '') use ($pdo, $tid, $sid) {
        $pdo->prepare("INSERT INTO report_fields (report_type_id, section_id, fkey, label, ftype, options, required, cond_field, table_cols, sort_order)
                       VALUES (?,?,?,?,?,?,?,?,?, 1)")
            ->execute([$tid, $sid, $fkey, ucfirst($fkey), $ftype, $opts, $req, $cond, $tcols]);
    };

    // A clean form passes.
    $mkF('name', 'text');
    $mkF('grade', 'select', "A\nB\nC");
    t_eq(idems_format_validate($tid)['level'], 'PASS', 'a clean form validates as PASS');

    // Duplicate field key → ERROR.
    $mkF('name', 'text');   // duplicate fkey 'name'
    $v = idems_format_validate($tid);
    t_eq($v['level'], 'ERROR', 'a duplicate field key is an ERROR');
    t_ok(count(array_filter($v['issues'], fn($i) => strpos($i['msg'], 'share the key') !== false)) >= 1, 'the duplicate-key issue names the key');

    // A choice field with no options → WARNING (on a separate clean type).
    $pdo->prepare("INSERT INTO report_types (code, name, active) VALUES ('FMT-B','Format B',1)")->execute();
    $tB = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_sections (report_type_id, title, sort_order) VALUES (?, 'S', 1)")->execute([$tB]);
    $sB = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_fields (report_type_id, section_id, fkey, label, ftype, options, sort_order) VALUES (?,?, 'pick','Pick','select','', 1)")->execute([$tB, $sB]);
    $pdo->prepare("INSERT INTO report_fields (report_type_id, section_id, fkey, label, ftype, table_cols, sort_order) VALUES (?,?, 'tbl','Table','table','', 2)")->execute([$tB, $sB]);
    $pdo->prepare("INSERT INTO report_fields (report_type_id, section_id, fkey, label, ftype, cond_field, sort_order) VALUES (?,?, 'extra','Extra','text','ghost_field', 3)")->execute([$tB, $sB]);
    $vB = idems_format_validate($tB);
    t_eq($vB['level'], 'WARNING', 'option-less choice / empty table / dangling condition are WARNINGs');
    $msgs = implode(' | ', array_column($vB['issues'], 'msg'));
    t_ok(strpos($msgs, 'no options') !== false, 'the option-less choice is flagged');
    t_ok(strpos($msgs, 'no columns') !== false, 'the empty table is flagged');
    t_ok(strpos($msgs, 'no field with that key exists') !== false, 'the dangling condition field is flagged');

    // A format with no fields → WARNING (usable-but-blank).
    $pdo->prepare("INSERT INTO report_types (code, name, active) VALUES ('FMT-EMPTY','Empty',1)")->execute();
    $tE = (int)$pdo->lastInsertId();
    t_eq(idems_format_validate($tE)['level'], 'WARNING', 'a format with no fields warns');

    // It blocks nothing — the same shape as the template validator, advisory.
    t_ok(isset($v['issues']) && is_array($v['issues']), 'returns the {level, issues} shape like idems_template_validate');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
$builder = file_get_contents(__DIR__ . '/../views/ops/idems/builder.php');
$types   = file_get_contents(__DIR__ . '/../views/ops/idems/report_types.php');
t_ok(strpos($builder, 'Format integrity') !== false, 'the builder shows a format-integrity panel');
t_ok(strpos($types, 'formChecks') !== false, 'the report-types list shows a per-type integrity pill');
t_ok(strpos($idems, 'blocks nothing') !== false || strpos($idems, 'warn/preview') !== false, 'the format validator is documented as advisory');
t_ok(strpos($idems, 'function idems_template_validate') !== false, 'the .docx template validator is unchanged (additive twin)');
