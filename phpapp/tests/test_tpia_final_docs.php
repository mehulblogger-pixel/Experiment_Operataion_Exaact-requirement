<?php
// TPIA Phase 1 item 11 / D7 (owner, 25 Nov 2026): the Final Inspection Report and
// the Release Note are required at close ONLY when ticked in the call's (or the
// job's) "Types of report required". Scenario 17: a stage-1 job without "Release
// Note" ticked closes without one; with it ticked, it is still required.
t_section('TPIA item 11 — final documents only when ticked');
setting_set('require_final_docs_on_close', '1');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $pdo = db();
    $call = function ($del) use ($pdo) {
        $pdo->prepare("INSERT INTO calls (call_code, deliverables, status, created_at) VALUES (?,?,'OPEN',?)")->execute(['FD-' . mt_rand(1000, 999999), $del, 'x']);
        return (int)$pdo->lastInsertId();
    };
    $job = function ($callId, $del, $freq = 'DAILY') use ($pdo) {
        $pdo->prepare("INSERT INTO jobs (job_code, call_id, deliverables, reporting_frequency, created_at) VALUES (?,?,?,?,?)")->execute(['FDJ-' . mt_rand(1000, 999999), $callId, $del, $freq, 'x']);
        return ops_one("SELECT * FROM jobs WHERE id=?", [(int)$pdo->lastInsertId()]);
    };
    //  Owner, 28 Nov 2026: a Release Note is owed only when the material was released — the jobs
    //  that must still ask for one carry a released report (rules for hold / abortive / rejected:
    //  tests/test_owner28_rn_exceptions.php).
    $released = function ($j) use ($pdo) {
        $pdo->prepare("INSERT INTO report_docs (job_id, type_code, status, result, release_status, deleted, data, created_at) VALUES (?,?,?,?,?,0,'[]',?)")
            ->execute([(int)$j['id'], 'IR', 'ISSUED', 'ACCEPTED', 'RELEASED', 'x']);
        return $j;
    };
    $stage1 = $job($call('IR'), 'IR');
    t_eq(job_final_docs_missing($stage1), [], 'scenario 17: a stage-1 job with only the Inspection Report ticked closes without FIR or Release Note');
    $withRn = $released($job($call('IR,RN'), 'IR,RN'));
    t_eq(job_final_docs_missing($withRn), ['a Release Note'], '… with "Release Note" ticked, the Release Note is still required (and only it)');
    $withIrn = $released($job($call(''), 'IRN'));
    t_eq(job_final_docs_missing($withIrn), ['a Release Note'], '"Inspection Release Note" ticked also asks for it');
    $both = $released($job($call(''), 'IR,FINAL_REP,RN'));
    t_eq(count(job_final_docs_missing($both)), 2, 'both ticked: both required');
    $fromCall = $job($call('FINAL_REP'), '');
    t_eq(job_final_docs_missing($fromCall), ['a Final Inspection Report'], 'a job with nothing ticked reads its call\'s ticks');
    $none = $job($call(''), '');
    t_eq(job_final_docs_missing($none), [], 'nothing ticked anywhere: nothing demanded');
    // Filed → satisfied.
    $pdo->prepare("INSERT INTO report_docs (job_id, type_code, status, deleted, data, created_at) VALUES (?,?,?,0,'[]',?)")->execute([(int)$withRn['id'], 'RN', 'ISSUED', 'x']);
    t_eq(job_final_docs_missing($withRn), [], 'once the Release Note is on file the job can close');
    // The existing exits are kept.
    t_eq(job_final_docs_missing(array_merge($both, ['billing_stop_kind' => 'TERMINATED'])), [], 'a terminated job still closes without them');
    t_eq(job_final_docs_missing($job($call('RN'), 'RN', 'NOREPORT')), [], 'a "No report" job still owes none');
    setting_set('require_final_docs_on_close', '0');
    t_eq(job_final_docs_missing($both), [], 'the company switch still turns the check off');
    setting_set('require_final_docs_on_close', '1');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
