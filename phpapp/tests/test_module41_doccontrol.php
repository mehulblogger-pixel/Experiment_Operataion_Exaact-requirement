<?php
// Module 41 — Document control (ISO 17020 §8.3). The controlled-document register exists with
// versioning/supersession, but its review-due signal never reached the cron dispatcher or the
// compliance board, and supersession was the one lifecycle event not on the sealed trail. Add
// cdoc_readiness()/cdoc_run_reminders(), the compliance row, and the supersede audit log.
t_section('Module 41 — controlled-document review readiness + reminder');

$cd   = file_get_contents(__DIR__ . '/../lib/controldocs.php');
$comp = file_get_contents(__DIR__ . '/../lib/compliance.php');
$cron = file_get_contents(__DIR__ . '/../cron.php');

t_ok(function_exists('cdoc_readiness'), 'cdoc_readiness() exists');
t_ok(function_exists('cdoc_run_reminders'), 'cdoc_run_reminders() exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$savedEnv = getenv('QAC_EMAIL');
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('cdocs_migrate')) cdocs_migrate();
    if (function_exists('idems_migrate')) idems_migrate();
    $pdo = db();

    // A current document past its review date, and one approved & in date.
    $pdo->prepare("INSERT INTO controlled_docs (doc_code, title, status, review_due, approved_on, created_at) VALUES ('DOC-OVR','Old SOP','CURRENT','2020-01-01','2019-01-01', ?)")->execute([date('c')]);
    $pdo->prepare("INSERT INTO controlled_docs (doc_code, title, status, review_due, approved_on, created_at) VALUES ('DOC-OK','Good SOP','CURRENT', ?, '2026-01-01', ?)")
        ->execute([date('Y-m-d', strtotime('+300 days')), date('c')]);
    // A current document never approved.
    $pdo->prepare("INSERT INTO controlled_docs (doc_code, title, status, review_due, approved_on, created_at) VALUES ('DOC-NA','Unapproved SOP','CURRENT','', '', ?)")->execute([date('c')]);

    $r = cdoc_readiness();
    t_ok($r['current'] >= 3, 'readiness counts the current documents');
    t_ok($r['review_overdue'] >= 1, 'a current document past its review date is counted as overdue');
    t_ok($r['never_approved'] >= 1, 'a current document with no recorded approval is counted');

    // The reminder fires when there is an overdue/unapproved doc and a recipient exists.
    putenv('QAC_EMAIL=quality@test.local');
    t_ok(cdoc_run_reminders() === 1, 'the reminder fires when documents are past review / unapproved');

    // Supersession is now written to the sealed trail.
    if (function_exists('cdoc_supersede')) {
        $ins = ops_one("SELECT id FROM controlled_docs WHERE doc_code='DOC-OK'");
        $before = (int)ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity='controlled_doc' AND action='SUPERSEDED'");
        cdoc_supersede((int)$ins['id'], 'Rev 2');
        $after = (int)ops_val("SELECT COUNT(*) FROM idems_audit WHERE entity='controlled_doc' AND action='SUPERSEDED'");
        t_ok($after === $before + 1, 'superseding a document writes a SUPERSEDED entry to the sealed trail');
        // The old row is superseded, the new row is a draft.
        t_eq((string)ops_val("SELECT status FROM controlled_docs WHERE id=?", [(int)$ins['id']]), 'SUPERSEDED', 'the old revision is marked superseded');
    }
} finally {
    if ($savedEnv === false) putenv('QAC_EMAIL'); else putenv('QAC_EMAIL=' . $savedEnv);
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
t_ok(strpos($comp, 'cdoc_readiness()') !== false, 'controlled-document readiness is on the compliance board');
t_ok(strpos($comp, '§8.3') !== false, 'the board row cites §8.3');
t_ok(strpos($cron, 'cdoc_run_reminders') !== false, 'the review reminder is wired into cron');
t_ok(strpos($cron, 'cdoc_reminder_week') !== false, 'the reminder is guarded (at most weekly)');
t_ok(strpos($cd, "idems_log('controlled_doc'") !== false && strpos($cd, "'SUPERSEDED'") !== false,
     'supersession is logged to the sealed trail (the previously-missing event)');
