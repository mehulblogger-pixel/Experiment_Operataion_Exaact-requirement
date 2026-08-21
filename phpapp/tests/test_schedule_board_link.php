<?php
// The scheduling board should open the SPECIFIC job/call, not the full list.
t_section('scheduling board opens the specific job');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $insp = team_member_create('Board Person One', 'FIELD');
    db()->prepare("INSERT INTO jobs (job_code, inspector_id, scheduled_date, inspection_dates, closed_flag, created_at) VALUES (?,?,?,?,?,?)")
        ->execute(['JOB-BRD-1', $insp, '2026-08-25', '2026-08-25', 0, 'x']);
    $jid = (int)db()->lastInsertId();

    $ret = availability_matrix(null, '2026-08-25', '2026-08-25');
    t_ok(count($ret) >= 5, 'availability_matrix now also returns a busy → job-id map');
    $busyId = $ret[4];
    t_ok((int)($busyId[$insp]['2026-08-25'] ?? 0) === $jid, 'the busy cell carries the specific job id');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

$sb = (string)file_get_contents(__DIR__ . '/../views/ops/schedule_board.php');
t_ok(strpos($sb, "/job?id='.(int)\$jid") !== false, 'a job cell links to that job, not the whole jobs list');
t_ok(strpos($sb, 'href="/jobs"') === false, 'the old /jobs full-list link on a board cell is gone');
t_ok(strpos($sb, "/job-new?call='.(int)\$s_call") !== false, 'the Allocate button uses ?call= (the param the route reads), not ?call_id=');
