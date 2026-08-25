<?php
// Module 06 — Applicability. Read-only surfacing over the EXISTING applicability
// mechanism (job/call `deliverables` + the service->report mapping): show which
// report formats apply to a job (and where each came from), and which do not.
// Nothing is hidden or blocked — the create form keeps its "never narrow to
// nothing" contract and its escape hatch.
t_section('Module 06 — job applicability (surface & formalize, additive)');

$idm  = file_get_contents(__DIR__ . '/../lib/idems.php');
$view = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');

t_ok(function_exists('idems_job_applicability'), 'idems_job_applicability() exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('idems_migrate')) idems_migrate();
    $types = array_column(idems_types(true), 'code');
    // Need at least two known report types to distinguish applicable vs not.
    $a = $types[0] ?? 'IR'; $b = $types[1] ?? ($types[0] ?? 'ER');

    // A job that owes exactly one format, chosen on the call (no service mapping).
    $job = ['id'=>0, 'deliverables'=>$a, 'service_code'=>'', 'client_id'=>0, 'call_id'=>0];
    $ap = idems_job_applicability($job);
    $appCodes = array_column($ap['applicable'], 'code');
    t_ok($appCodes === [$a], 'the applicable list is exactly the job\'s agreed deliverable(s)');
    t_ok($ap['applicable'][0]['source'] === 'manual', 'a deliverable with no service mapping is sourced as chosen-on-the-call');
    $naCodes = array_column($ap['not_applicable'], 'code');
    t_ok(!in_array($a, $naCodes, true) && in_array($b, $naCodes, true),
        'the not-applicable list is the catalogue minus the agreed formats');

    // Provenance from the service mapping: a house default reads as "service".
    if (function_exists('svc_report_codes')) {
        $svcCodes = svc_report_codes('INSPECTION', 0);
        if ($svcCodes) {
            $job2 = ['id'=>0, 'deliverables'=>implode(',', $svcCodes), 'service_code'=>'INSPECTION', 'client_id'=>0, 'call_id'=>0];
            $ap2 = idems_job_applicability($job2);
            $srcs = array_values(array_unique(array_column($ap2['applicable'], 'source')));
            t_ok($srcs === ['service'], 'formats that come from the service agreement are sourced as "service"');
        } else { t_ok(true, 'service mapping empty in this build — provenance test skipped'); }
    } else { t_ok(true, 'no service mapping in this build — provenance test skipped'); }

    // A job with NO deliverables → nothing applicable, full catalogue not-applicable
    // (i.e. no lock; the create form would offer everything).
    $none = idems_job_applicability(['id'=>0, 'deliverables'=>'', 'service_code'=>'', 'client_id'=>0, 'call_id'=>0]);
    t_ok($none['applicable'] === [], 'a job with no agreed deliverables has an empty applicable list (no lock)');

    // A format already WRITTEN against the job is not offered as "not applicable".
    db()->prepare("INSERT INTO report_docs (type_code, status, job_id, deleted, created_at) VALUES (?, 'DRAFT', 777001, 0, ?)")->execute([$b, date('c')]);
    $jobW = ['id'=>777001, 'deliverables'=>$a, 'service_code'=>'', 'client_id'=>0, 'call_id'=>0];
    $apW = idems_job_applicability($jobW);
    t_ok(!in_array($b, array_column($apW['not_applicable'], 'code'), true),
        'a format already written against the job is not listed as not-applicable (it is shown as off-list instead)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The job screen surfaces the source note and the collapsed not-applicable list.
t_ok(strpos($view, 'idems_job_applicability($job)') !== false, 'the job screen computes applicability');
t_ok(strpos($view, 'not applicable to this') !== false && strpos($view, 'add anyway') !== false,
    'the job screen shows the not-applicable formats with an add-anyway escape hatch');
t_ok(strpos($view, '$srcLabels[$srcMap[$code]]') !== false, 'each agreed format shows where it came from');

// Preserved contracts: the create form still never narrows to nothing and keeps its escape link.
t_ok(strpos($idm, 'if ($narrow) $types = $narrow;') !== false, 'the create form still never narrows the type list to nothing');
$form = file_get_contents(__DIR__ . '/../views/ops/idems/doc_form.php');
t_ok(strpos($form, 'Need a different one') !== false, 'the create form keeps its "Need a different one?" escape hatch');

// No new permission.
t_ok(!preg_match('/can\(\x27idems\.(applic|applicability)/', $idm), 'Module 06 introduces no new permission constant');
