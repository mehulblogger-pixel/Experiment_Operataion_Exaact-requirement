<?php
// Phase 3 §35 — attendance review. An inspector self-marks (that capture already exists); this adds the
// oversight: only ANOMALOUS entries surface, a coordinator/manager SENDS BACK to the inspector (or clears
// / escalates), and it is ADVISORY — the attendance still counts, nothing downstream is gated.
// Additive columns on the existing `attendance` table. Self-contained.
t_section('Phase 3 §35 — attendance review (anomaly flag + send-back, advisory)');

t_ok(function_exists('attend_anomaly') && function_exists('attend_review_scan') && function_exists('attend_review_return')
     && function_exists('attend_review_reset') && function_exists('ops_attendance_review'), 'the review helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/attendreview.php'") !== false, 'the review lib is loaded by the front controller');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "case \$route === 'attendance-review'") !== false, 'the /attendance-review route is dispatched');
t_ok(strpos($ops, "'Attendance to review'") !== false, 'the review count feeds attention_summary');
$att = file_get_contents(__DIR__ . '/../lib/attend.php');
t_ok(strpos($att, 'attend_review_reset') !== false, 're-marking clears a prior send-back (capture-path hook)');

// --- anomaly detection, purely on a row (no DB) ---
$today = date('Y-m-d');
$openPast = ['att_date' => date('Y-m-d', strtotime('-3 day')), 'check_in_at' => '2026-01-01T09:00:00', 'check_out_at' => ''];
t_ok(attend_anomaly($openPast) !== '', 'a past day checked-in-but-never-out is flagged');
$late = ['att_date' => date('Y-m-d', strtotime('-10 day')), 'marked_at' => $today];
t_ok(attend_anomaly($late) !== '', 'a mark made long after the date is flagged');
$clean = ['att_date' => $today, 'status' => 'OFFICE', 'check_in_at' => '2026-01-01T09:00:00', 'check_out_at' => '2026-01-01T18:00:00', 'marked_at' => $today];
t_eq(attend_anomaly($clean), '', 'a clean, timely, closed office mark is NOT flagged');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$prevUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('attend_migrate')) attend_migrate();
    attendreview_migrate();
    $pdo = db();
    // A master (so scope is ALL and review is allowed).
    $pdo->prepare("INSERT INTO users (username, is_superuser, is_active) VALUES ('a35','1',1)")->execute();
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); if (function_exists('ua')) ua(true);
    $pdo->prepare("INSERT INTO inspectors (name) VALUES ('Ravi')")->execute();
    $insp = (int)$pdo->lastInsertId();

    // A clean entry (not flagged) and an anomalous one (past day, never checked out).
    $pdo->prepare("INSERT INTO attendance (att_date, inspector_id, status, check_in_at, check_out_at, marked_at) VALUES (?,?, 'OFFICE','2026-01-01T09:00','2026-01-01T18:00', ?)")
        ->execute([$today, $insp, $today]);
    $pdo->prepare("INSERT INTO attendance (att_date, inspector_id, status, check_in_at, check_out_at, marked_at) VALUES (?,?, 'SITE','2026-01-01T09:00','', ?)")
        ->execute([date('Y-m-d', strtotime('-2 day')), $insp, date('Y-m-d', strtotime('-2 day'))]);
    $bad = (int)$pdo->lastInsertId();

    $queue = attend_review_scan();
    $ids = array_column($queue, 'id');
    t_ok(in_array($bad, $ids, true), 'the anomalous entry is in the review queue');
    t_ok(count($queue) === 1, 'the clean entry is NOT in the queue (only flagged surface)');
    t_ok(trim((string)$queue[0]['flag_reason']) !== '', 'the queued entry carries its flag reason');

    // Advisory: the attendance row is untouched by being flagged (it still counts).
    $before = ops_one("SELECT status, check_in_at FROM attendance WHERE id=?", [$bad]);
    t_ok($before['status'] === 'SITE', 'the flagged attendance itself is unchanged — it still counts (advisory)');

    // Send it back to the inspector → it leaves the queue and is marked RETURNED.
    t_ok(attend_review_return($bad, 'Please confirm your site GPS'), 'the reviewer can send it back');
    t_eq((string) ops_val("SELECT review_status FROM attendance WHERE id=?", [$bad]), 'RETURNED', 'the entry is marked RETURNED');
    t_ok(count(attend_review_scan()) === 0, 'a sent-back entry leaves the coordinator queue');
    t_ok(count(attend_review_returned_for($insp)) === 1, "it shows on the inspector's own returned list");

    // The inspector re-marks (fixes the check-out) → the flag resets and, once clean, it is gone for good.
    $pdo->prepare("UPDATE attendance SET check_out_at='2026-01-01T18:00' WHERE id=?")->execute([$bad]);
    attend_review_reset($bad);
    t_eq((string) ops_val("SELECT review_status FROM attendance WHERE id=?", [$bad]), '', 're-marking clears the review flag');
    t_ok(count(attend_review_scan()) === 0, 'the corrected entry is no longer flagged');
} finally {
    if ($prevUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $prevUid;
    if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}
