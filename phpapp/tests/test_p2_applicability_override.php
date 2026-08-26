<?php
// Phase 2 §6 — the applicability engine let an inspector "add anyway" a report type that is NOT on
// the job's agreed deliverables, promising the UI it would be "flagged not allocated" — but nothing
// was stored and no override was audited. Now raising such a report captures a reason, flags the
// report not_allocated, and records an APPLICABILITY_OVERRIDE audit event (reason/person/time).
t_section('Phase 2 §6 — applicability override is captured + audited');

t_ok(function_exists('idems_applicability_is_override'), 'the override detector exists');
t_ok(in_array('APPLICABILITY_OVERRIDE', AUDIT_ACTIONS_ALL, true), 'APPLICABILITY_OVERRIDE is a catalogued action');
t_ok(in_array('APPLICABILITY_OVERRIDE', AUDIT_HIGH_RISK, true), 'it is flagged high-risk for review');
t_ok(AUDIT_ACTION_LABELS['APPLICABILITY_OVERRIDE'] === 'Report raised outside agreed deliverables', 'it has a plain-English label');

$idm = file_get_contents(__DIR__ . '/../lib/idems.php');
$jobv = file_get_contents(__DIR__ . '/../views/ops/job_detail.php');
$docv = file_get_contents(__DIR__ . '/../views/ops/idems/doc_detail.php');
t_ok(strpos($idm, "ensure_column('report_docs', 'not_allocated'") !== false, 'the not_allocated + reason columns exist');
t_ok(strpos($idm, "idems_log('report_doc', \$id, 'APPLICABILITY_OVERRIDE'") !== false, 'create logs the override when the type is off-deliverables');
t_ok(strpos($jobv, "override_reason") !== false && strpos($jobv, 'prompt(') !== false, 'the "add anyway" link prompts for and passes a reason');
t_ok(strpos($docv, 'Not on the agreed deliverables') !== false, 'the report detail surfaces the not-allocated flag');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('idems_migrate')) idems_migrate();
    $pdo = db();
    // A job whose agreed deliverables include ONLY 'IC' (Inspection). Then 'DIM' is not applicable.
    $pdo->prepare("INSERT INTO jobs (job_code, deliverables, closed_flag) VALUES ('JOB-APPL','IC',0)")->execute();
    $jid = (int)$pdo->lastInsertId();
    // Ensure an active 'DIM' report type exists so it lands on the NOT-applicable list.
    $exists = ops_val("SELECT COUNT(*) FROM report_types WHERE code='DIM'");
    if (!$exists) $pdo->prepare("INSERT INTO report_types (code, name, active) VALUES ('DIM','Dimensional',1)")->execute();

    // The detector: a type on the deliverables is not an override; a type off them is.
    t_ok(idems_applicability_is_override($jid, 'IC') === false, 'a report type ON the deliverables is not an override');
    t_ok(idems_applicability_is_override($jid, 'DIM') === true, 'a report type OFF the deliverables IS an override');
    t_ok(idems_applicability_is_override($jid, '') === false, 'an empty type is never an override');
    t_ok(idems_applicability_is_override(0, 'DIM') === false, 'no job id → not an override (safe)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
