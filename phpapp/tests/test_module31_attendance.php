<?php
// Module 31 — Attendance / Reconciliation. A read-only cross-check over the existing
// stores (site presence, voucher hours, attendance status, the daily cap) that flags the
// five anomalies — including "impossible timing", which had no check before. Nothing is
// blocked; no store is changed.
t_section('Module 31 — attendance reconciliation flags');

$lib = file_get_contents(__DIR__ . '/../lib/timesheet.php');
$view = file_get_contents(__DIR__ . '/../views/ops/timesheet.php');

t_ok(function_exists('attend_anomalies'), 'attend_anomalies() exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    $pdo = db();
    $pdo->prepare("INSERT INTO inspectors (name, status) VALUES ('Recon One','ACTIVE')")->execute();
    $ins = (int)$pdo->lastInsertId();
    $from = '2026-07-01'; $to = '2026-07-31';
    $mkVisit = fn($job, $kind, $at) => $pdo->prepare("INSERT INTO site_visits (job_id, inspector_id, kind, at, created_at) VALUES (?,?,?,?,?)")->execute([$job, $ins, $kind, $at, date('c')]);
    $mkHours = function ($date, $h) use ($pdo, $ins) {
        $v = ops_one("SELECT id FROM vouchers WHERE inspector_id=? AND month=?", [$ins, substr($date, 0, 7)]);
        if (!$v) { $pdo->prepare("INSERT INTO vouchers (inspector_id, month, status, created_at) VALUES (?,?, 'DRAFT', ?)")->execute([$ins, substr($date, 0, 7), date('c')]); $vid = (int)$pdo->lastInsertId(); } else $vid = (int)$v['id'];
        $pdo->prepare("INSERT INTO voucher_entries (voucher_id, entry_date, day_type, hours) VALUES (?,?, 'WORK', ?)")->execute([$vid, $date, $h]);
    };

    // Impossible timing: EXIT before ENTRY on the same job/day.
    $mkVisit(701, 'ENTRY', '2026-07-05T14:00:00+00:00');
    $mkVisit(701, 'EXIT',  '2026-07-05T09:00:00+00:00');
    // Missing check-out: an ENTRY on a past day with no EXIT.
    $mkVisit(702, 'ENTRY', '2026-07-06T09:00:00+00:00');
    // Overlapping jobs: two jobs the same day, both with valid windows.
    $mkVisit(703, 'ENTRY', '2026-07-07T08:00:00+00:00'); $mkVisit(703, 'EXIT', '2026-07-07T10:00:00+00:00');
    $mkVisit(704, 'ENTRY', '2026-07-07T11:00:00+00:00'); $mkVisit(704, 'EXIT', '2026-07-07T13:00:00+00:00');
    // On site, no hours: valid window, no voucher hours that day.
    $mkVisit(705, 'ENTRY', '2026-07-08T08:00:00+00:00'); $mkVisit(705, 'EXIT', '2026-07-08T16:00:00+00:00');
    // Excessive hours: a day well over the cap.
    $mkHours('2026-07-09', 12.0);

    $an = attend_anomalies($ins, $from, $to);
    $types = array_column($an, 'type');
    t_ok(in_array('impossible', $types, true), 'a check-out before check-in is flagged as impossible timing (the previously-absent check)');
    t_ok(in_array('missing_checkout', $types, true), 'an entry with no exit on a past day is flagged as a missing check-out');
    t_ok(in_array('overlap', $types, true), 'two jobs on the same day are flagged as overlapping');
    t_ok(in_array('no_hours', $types, true), 'on site with no voucher hours is flagged');
    t_ok(in_array('excessive', $types, true), 'a day over the daily cap is flagged as excessive hours');

    // A clean inspector → no flags.
    $pdo->prepare("INSERT INTO inspectors (name, status) VALUES ('Recon Clean','ACTIVE')")->execute();
    $clean = (int)$pdo->lastInsertId();
    $mkC = fn($job, $kind, $at) => $pdo->prepare("INSERT INTO site_visits (job_id, inspector_id, kind, at, created_at) VALUES (?,?,?,?,?)")->execute([$job, $clean, $kind, $at, date('c')]);
    $mkC(710, 'ENTRY', '2026-07-03T09:00:00+00:00'); $mkC(710, 'EXIT', '2026-07-03T17:00:00+00:00');
    $v = ops_one("SELECT id FROM vouchers WHERE inspector_id=? AND month='2026-07'", [$clean]);
    $vid = $v ? (int)$v['id'] : (function () use ($pdo, $clean) { $pdo->prepare("INSERT INTO vouchers (inspector_id, month, status, created_at) VALUES (?, '2026-07','DRAFT', ?)")->execute([$clean, date('c')]); return (int)$pdo->lastInsertId(); })();
    $pdo->prepare("INSERT INTO voucher_entries (voucher_id, entry_date, day_type, hours) VALUES (?, '2026-07-03', 'WORK', 8.0)")->execute([$vid]);
    t_ok(attend_anomalies($clean, $from, $to) === [], 'a clean month with matching presence and hours has no flags');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The timesheet surfaces the flags; reuses the cap; read-only.
t_ok(strpos($lib, 'attend_anomalies($insId, $from, $to)') !== false, 'the timesheet computes the reconciliation flags');
t_ok(strpos($view, 'Reconciliation flags') !== false, 'the timesheet shows the reconciliation section');
t_ok(strpos($lib, 'hours_cap()') !== false, 'excessive-hours reuses the existing daily cap (no new threshold)');
t_ok(!preg_match('/can\(\x27(attendance|recon)\.flag/', $lib), 'Module 31 introduces no new permission constant');
