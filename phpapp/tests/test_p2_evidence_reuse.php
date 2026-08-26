<?php
// Phase 2 §68 — evidence reuse across jobs. De-duplication at upload only catches the same photo
// used twice in ONE report (report_doc_id + sha1). The stronger anomaly is the same bytes appearing
// under two DIFFERENT jobs — a photo carried from one inspection to another. This surfaces it for a
// human (advisory, never a block or delete), reusing the sha1 already stored on every file.
t_section('Phase 2 §68 — evidence reuse across jobs');

t_ok(function_exists('evidence_reuse_groups') && function_exists('evidence_reuse_count'), 'the reuse scanner helpers exist');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('idems_migrate')) idems_migrate();
    if (function_exists('trust_migrate')) trust_migrate();
    $pdo = db();
    $base = evidence_reuse_count();

    // Two reports on two DIFFERENT jobs, plus one extra report on job 1.
    $pdo->prepare("INSERT INTO report_docs (job_id, irn, type_code, deleted) VALUES (9101,'IRN-A','FIR',0)")->execute(); $dA = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_docs (job_id, irn, type_code, deleted) VALUES (9102,'IRN-B','FIR',0)")->execute(); $dB = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO report_docs (job_id, irn, type_code, deleted) VALUES (9101,'IRN-C','FIR',0)")->execute(); $dC = (int)$pdo->lastInsertId();

    // The SAME sha1 under two different jobs (A on job 9101, B on job 9102) — a reuse.
    $shared = str_repeat('a', 40);
    $pdo->prepare("INSERT INTO report_files (report_doc_id, file_name, kind, sha1) VALUES (?, 'shot.jpg','photo',?)")->execute([$dA, $shared]);
    $pdo->prepare("INSERT INTO report_files (report_doc_id, file_name, kind, sha1) VALUES (?, 'shot.jpg','photo',?)")->execute([$dB, $shared]);
    // A different sha1 reused within the SAME job (dA + dC both on job 9101) — NOT cross-job, must not count.
    $sameJob = str_repeat('b', 40);
    $pdo->prepare("INSERT INTO report_files (report_doc_id, file_name, kind, sha1) VALUES (?, 'form.jpg','photo',?)")->execute([$dA, $sameJob]);
    $pdo->prepare("INSERT INTO report_files (report_doc_id, file_name, kind, sha1) VALUES (?, 'form.jpg','photo',?)")->execute([$dC, $sameJob]);
    // A unique file — never counts.
    $pdo->prepare("INSERT INTO report_files (report_doc_id, file_name, kind, sha1) VALUES (?, 'unique.jpg','photo',?)")->execute([$dA, str_repeat('c', 40)]);

    t_eq(evidence_reuse_count(), $base + 1, 'exactly the one cross-job reuse is counted (same-job reuse is not)');

    $groups = evidence_reuse_groups(30);
    $mine = null; foreach ($groups as $g) if ($g['sha1'] === $shared) $mine = $g;
    t_ok($mine !== null, 'the cross-job reuse group is returned by the scanner');
    t_ok($mine && (int)$mine['jobs'] === 2, 'the group reports the two distinct jobs');
    $jobIds = $mine ? array_map(fn($m) => (int)$m['job_id'], $mine['members']) : [];
    t_ok(in_array(9101, $jobIds, true) && in_array(9102, $jobIds, true), 'both job ids appear in the group members');

    // A deleted report drops out of the scan.
    $pdo->prepare("UPDATE report_docs SET deleted=1 WHERE id=?")->execute([$dB]);
    t_eq(evidence_reuse_count(), $base, 'once one side is deleted, it is no longer a cross-job reuse');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The readiness surface carries the advisory count, and the review screen renders the panel.
$tr = trust_readiness();
t_ok(array_key_exists('reuse', $tr), 'trust_readiness() exposes the reuse count');
$v = file_get_contents(__DIR__ . '/../views/ops/evidence_review.php');
t_ok(strpos($v, '$reuse') !== false, 'the evidence-review screen shows the reuse panel');
$src = file_get_contents(__DIR__ . '/../lib/trust.php');
t_ok(strpos($src, "'reuse' => \$docId ? [] : evidence_reuse_groups") !== false, 'the reuse groups are only loaded on the whole-queue view');
