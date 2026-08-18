<?php
// Project-site progress reports (Daily / Weekly / Fortnightly / Monthly) must be
// ready-to-fill forms, not blank "no form designed yet" screens. One shared form
// definition serves all four period variants. Guards the build, the shared
// structure, header keys that autofill, and idempotency.
t_section('project-site progress reports');

$ids = idems_build_progress_reports();
foreach (['DPR','WPR','FNR','MPGR'] as $code) {
    t_ok(!empty($ids[$code]) && $ids[$code] > 0, "the $code progress report type exists");
    t_ok(idems_has_schema($ids[$code]), "$code has a designed form (not a blank screen)");
}

// The four are the requested set, named for their period.
$names = [];
foreach ($ids as $code => $tid) $names[$code] = (string)ops_val("SELECT name FROM report_types WHERE id=?", [$tid]);
t_ok(stripos($names['DPR'],'daily') !== false, 'DPR is the Daily Progress Report');
t_ok(stripos($names['WPR'],'weekly') !== false, 'WPR is the Weekly Progress Report');
t_ok(stripos($names['FNR'],'fortnight') !== false, 'FNR is the Fortnightly Progress Report');
t_ok(stripos($names['MPGR'],'monthly') !== false, 'MPGR is the Monthly Progress Report');

// Shared structure: each variant has the progress-summary and photographs pieces
// and a sign-off, and the header keys autofill from the job.
foreach ($ids as $code => $tid) {
    $fields = idems_fields($tid);
    $keys = array_map(fn($f) => $f['fkey'], $fields);
    foreach (['project','client','vendor','location','period_from','period_to','progress_activities','work_done','manpower','issues','photos','signoff'] as $k)
        t_ok(in_array($k, $keys, true), "$code has the '$k' field");
    // The header uses the SAME keys as other reports, so identity autofills.
    foreach (['client','vendor','project','inspection_date','inspector'] as $k)
        t_ok(in_array($k, $keys, true), "$code header '$k' autofills from the job");
    // It carries a photo field and a sign-off block.
    $ftypes = array_column($fields, 'ftype');
    t_ok(in_array('photo', $ftypes, true), "$code has a photographs field");
    t_ok(in_array('sigblock', $ftypes, true), "$code has a sign-off block");
    t_ok(in_array('table', $ftypes, true), "$code has at least one table");
}

// Idempotent — building again neither duplicates types nor doubles the fields.
$before = (int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=?", [$ids['MPGR']]);
$ids2 = idems_build_progress_reports();
t_eq($ids2['MPGR'], $ids['MPGR'], 'rebuilding returns the same Monthly Progress Report type');
$after = (int)ops_val("SELECT COUNT(*) FROM report_fields WHERE report_type_id=?", [$ids['MPGR']]);
t_eq($after, $before, 'rebuilding does not duplicate fields');

// A progress report renders a PDF through the same engine.
$pdo = db();
$pdo->prepare("INSERT INTO report_docs (report_type_id,type_code,irn,status,data,created_at) VALUES (?,?,?,?,?,?)")
    ->execute([$ids['MPGR'], 'MPGR', 'MPR-1', 'draft', json_encode(['overall_progress'=>'62','work_done'=>'Foundation complete.']), date('c')]);
$doc = ops_one("SELECT * FROM report_docs WHERE id=?", [(int)$pdo->lastInsertId()]);
[$secs,$flds] = idems_render_schema($doc);
$pdf = report_pdf_build($doc, $secs, $flds, json_decode($doc['data'],true), idems_doc_files($doc['id']), ['name'=>'T'], idems_report_signatures($doc), '');
t_ok(strlen($pdf) > 800 && strncmp($pdf, '%PDF', 4) === 0, 'a Monthly Progress Report renders a PDF');
