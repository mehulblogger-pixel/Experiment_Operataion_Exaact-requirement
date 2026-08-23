<?php
// The voucher pull uses a job's BOOKED dates (inspection_dates / scheduled day),
// the same source as the My-Jobs schedule — not the job's whole start→end span. A
// continuous deputation must not flood every day of the month; the voucher must
// agree with the calendar.
t_section('voucher pull follows booked dates, not the whole date range');

$pdo = db();
$pdo->prepare("INSERT INTO inspectors (name) VALUES ('VBD Ravi')")->execute();
$insId = (int)$pdo->lastInsertId();

// A month-long deputation but only three BOOKED inspection days.
$pdo->prepare("INSERT INTO jobs (job_code, inspector_id, scheduled_date, inspection_start_date, inspection_end_date, inspection_dates, created_at)
               VALUES ('VBD-1', ?, '2026-09-01','2026-09-01','2026-09-30','2026-09-03,2026-09-10,2026-09-17', ?)")
    ->execute([$insId, date('c')]);

$pdo->prepare("INSERT INTO vouchers (inspector_id, month, status, created_at) VALUES (?, '2026-09', 'DRAFT', ?)")->execute([$insId, date('c')]);
$v = ops_one("SELECT * FROM vouchers WHERE inspector_id=? AND month='2026-09'", [$insId]);

$added = voucher_generate($v);
t_ok($added === 3, 'only the three booked days are pulled — not all 30 days of the range');
$rows = ops_all("SELECT entry_date FROM voucher_entries WHERE voucher_id=? ORDER BY entry_date", [(int)$v['id']]);
$dates = array_map(fn($r) => $r['entry_date'], $rows);
t_ok($dates === ['2026-09-03','2026-09-10','2026-09-17'], 'the pulled dates match the booked inspection_dates exactly');

// A job with no booked dates and no schedule → a single line on its start day, not
// the whole range.
$pdo->prepare("INSERT INTO inspectors (name) VALUES ('VBD Amit')")->execute();
$insId2 = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO jobs (job_code, inspector_id, inspection_start_date, inspection_end_date, created_at)
               VALUES ('VBD-2', ?, '2026-09-05','2026-09-25', ?)")->execute([$insId2, date('c')]);
$pdo->prepare("INSERT INTO vouchers (inspector_id, month, status, created_at) VALUES (?, '2026-09', 'DRAFT', ?)")->execute([$insId2, date('c')]);
$v2 = ops_one("SELECT * FROM vouchers WHERE inspector_id=? AND month='2026-09'", [$insId2]);
t_ok(voucher_generate($v2) === 1, 'a job with only a start/end (no booked dates) yields a single day, not the range');
