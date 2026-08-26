<?php
// Module 38 — Notification Centre. Every ops_mail() call is already logged to email_log (recipient,
// subject, kind, sent_ok, error), but that table was displayed NOWHERE — "did the client actually
// get the report-issued email?" and "did last night's reminders go out or did SMTP fail?" were
// answerable only by raw SQL, and cron SMTP failures vanished silently. Add a read-only notification/
// outbox log over email_log (+ a failed-count helper). Touches no sender; stores nothing new.
t_section('Module 38 — notification / outbox log over email_log');

// In the test harness the web renderer view() is not loaded; stub it to capture the
// data the handler passes, so we can assert on the handler's own filtering SQL.
if (!function_exists('view')) { function view($tpl, $data = []) { $GLOBALS['__nv'] = $data; } }

t_ok(function_exists('ops_notifications'),      'the /notifications handler exists');
t_ok(function_exists('notifications_can_view'), 'notifications_can_view() gate exists');
t_ok(function_exists('email_failed_count'),     'email_failed_count() helper exists');

// The route is dispatched and mapped to a core module.
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "case \$route === 'notifications':") !== false, 'the /notifications route is dispatched');
t_ok(strpos($ops, "'notifications'=>'admin'") !== false, 'the route is mapped to the core admin module');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
$savedUid = $_SESSION['uid'] ?? null;
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();
    // A master user, so the admin-gated handler renders.
    $pdo->prepare("INSERT INTO users (username, role, is_superuser, is_active) VALUES ('notifmaster','MASTER_ADMIN',1,1)")->execute();
    $_SESSION['uid'] = (int)$pdo->lastInsertId(); current_user(true); if (function_exists('ua')) ua(true);

    $mk = function ($to, $subj, $kind, $ok, $err = '') use ($pdo) {
        $pdo->prepare("INSERT INTO email_log (to_addr, cc_addr, subject, body, kind, sent_ok, error, created_at)
                       VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$to, '', $subj, 'body', $kind, $ok, $err, date('c')]);
    };
    $base = email_failed_count(30);
    $mk('client@x.com', 'Report IC-1 issued', 'report_issued', 1);
    $mk('', 'CAPA overdue digest', 'capa', 0, 'no recipient');   // the CC-only-never-sent class of failure
    $mk('smtp@x.com', 'Assignment JOB-1', 'assignment', 0, 'SMTP connect failed');

    // email_failed_count sees the two failures.
    t_eq(email_failed_count(30), $base + 2, 'email_failed_count() counts the failed sends');

    // Run the handler (via the view() stub) and assert on the data it hands the view.
    $subjects = function () { return array_map(fn($r) => $r['subject'], $GLOBALS['__nv']['rows'] ?? []); };

    $_GET = []; ops_notifications('GET');
    t_ok(in_array('Report IC-1 issued', $subjects(), true),  'a sent notification appears in the log');
    t_ok(in_array('CAPA overdue digest', $subjects(), true), 'a failed (no-recipient) notification appears too');
    t_ok(($GLOBALS['__nv']['stats']['failed'] ?? 0) >= 2, 'the 30-day failed count is surfaced');
    t_ok(($GLOBALS['__nv']['stats']['norecip'] ?? 0) >= 1, 'the no-recipient failures (the CC-only silent-drop) are counted');

    $_GET = ['failed' => '1']; ops_notifications('GET');
    t_ok(in_array('CAPA overdue digest', $subjects(), true),  'the failed-only view keeps the failed send');
    t_ok(!in_array('Report IC-1 issued', $subjects(), true),  'the failed-only view hides the successful send');

    $_GET = ['kind' => 'report_issued']; ops_notifications('GET');
    t_ok(in_array('Report IC-1 issued', $subjects(), true),   'filtering by category keeps that category');
    t_ok(!in_array('CAPA overdue digest', $subjects(), true), 'filtering by category excludes others');

    $_GET = ['q' => 'Assignment JOB-1']; ops_notifications('GET');
    t_ok(in_array('Assignment JOB-1', $subjects(), true), 'a subject search finds the match');
    t_ok(!in_array('Report IC-1 issued', $subjects(), true), 'a subject search excludes non-matches');
    $_GET = [];
} finally {
    if ($savedUid === null) unset($_SESSION['uid']); else $_SESSION['uid'] = $savedUid;
    if (function_exists('current_user')) current_user(true); if (function_exists('ua')) ua(true);
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
$view = file_get_contents(__DIR__ . '/../views/ops/notifications.php');
$settings = file_get_contents(__DIR__ . '/../views/ops/settings.php');
t_ok(strpos($view, 'FROM email_log') === false, 'the view holds no SQL (the handler queries; the view renders)');
t_ok(strpos($ops, 'FROM email_log') !== false, 'the handler reads the existing email_log table');
t_ok(strpos($settings, '/notifications') !== false, 'the settings screen links to the notification log');
// The mail primitive and its logging are untouched (read-only module).
t_ok(strpos($ops, 'function ops_mail') !== false, 'the ops_mail sender is unchanged (this module only reads its log)');
