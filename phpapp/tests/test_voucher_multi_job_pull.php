<?php
// "Pull working days from jobs" must give one line PER JOB, not one per day. An
// inspector allotted several jobs on the same day visited several sites and needs a
// line for each (its own file no / client / vendor / expenses). The day's hours are
// still counted once so attendance is not doubled.
t_section('pulling working days gives one line per job per day');

$pdo = db();

$pdo->prepare("INSERT INTO inspectors (name) VALUES ('VMP Ravi')")->execute();
$insId = (int)$pdo->lastInsertId();

// Four jobs, all for this inspector, all on 14 Aug 2026.
for ($i = 1; $i <= 4; $i++) {
    $pdo->prepare("INSERT INTO jobs (job_code, inspector_id, scheduled_date, inspection_start_date, inspection_end_date, sbu, created_at)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute(["VMP-$i", $insId, '2026-08-14', '2026-08-14', '2026-08-14', 'INDUSTRIAL', date('c')]);
}
// A fifth job on a different day, to be sure per-day still works.
$pdo->prepare("INSERT INTO jobs (job_code, inspector_id, scheduled_date, inspection_start_date, inspection_end_date, created_at)
               VALUES ('VMP-5', ?, '2026-08-15','2026-08-15','2026-08-15', ?)")->execute([$insId, date('c')]);

$pdo->prepare("INSERT INTO vouchers (inspector_id, month, status, created_at) VALUES (?, '2026-08', 'DRAFT', ?)")->execute([$insId, date('c')]);
$v = ops_one("SELECT * FROM vouchers WHERE inspector_id=? AND month='2026-08'", [$insId]);

$added = voucher_generate($v);
t_ok($added === 5, 'all five job-days are pulled (4 on the 14th + 1 on the 15th)');

$on14 = ops_all("SELECT * FROM voucher_entries WHERE voucher_id=? AND entry_date='2026-08-14'", [(int)$v['id']]);
t_ok(count($on14) === 4, 'the 14th has one line per job (four lines), not a single collapsed day');
$jobIds = array_unique(array_map(fn($r) => (int)$r['job_id'], $on14));
t_ok(count($jobIds) === 4, 'each line carries a distinct job');

// The day's hours are counted once: 8 total across the four lines, not 32.
$hoursSum = array_sum(array_map(fn($r) => (float)$r['hours'], $on14));
t_ok($hoursSum === 8.0, 'the day is 8 hours in total (attendance not doubled across the four jobs)');
$eight = array_filter($on14, fn($r) => (float)$r['hours'] === 8.0);
t_ok(count($eight) === 1, 'exactly one line on the day carries the 8 hours');

// Idempotent: pulling again adds nothing.
t_ok(voucher_generate($v) === 0, 're-pulling the same month adds no duplicate lines');
