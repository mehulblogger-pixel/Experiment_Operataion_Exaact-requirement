<?php
// The Operations desk's cumulative "pending scheduling" panel — every call this
// branch still has to put an engineer on (same-office and cross-office alike).
t_section('cumulative pending-scheduling panel');
if (function_exists('tosrm_xo_migrate')) tosrm_xo_migrate();

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO offices (code, name) VALUES (?,?)")->execute(['AMD', 'Ahmedabad']);
    $off = (int)db()->lastInsertId();

    // A same-office call with no scheduled job → pending scheduling.
    db()->prepare("INSERT INTO calls (call_code, executing_office_id, ibo_office_id, status, inspection_required_date, inspection_type, created_at)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute(['C-SAME-1', $off, $off, 'OPEN', '2026-08-30', 'INSPECTION', 'x']);
    $c1 = (int)db()->lastInsertId();

    // A call that already has a scheduled job → NOT pending.
    db()->prepare("INSERT INTO calls (call_code, executing_office_id, ibo_office_id, status, created_at) VALUES (?,?,?,?,?)")
        ->execute(['C-SAME-2', $off, $off, 'OPEN', 'x']);
    $c2 = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO jobs (job_code, call_id, scheduled_date, closed_flag, created_at) VALUES (?,?,?,?,?)")
        ->execute(['J-2', $c2, '2026-08-20', 0, 'x']);

    $ids = array_map(fn($r) => (int)$r['id'], tosrm_pending_scheduling([$off]));
    t_ok(in_array($c1, $ids, true), 'a same-office call with no engineer yet is pending scheduling');
    t_ok(!in_array($c2, $ids, true), 'a call that already has a scheduled job is not pending');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

$oh = (string)file_get_contents(__DIR__ . '/../views/ops/operations_home.php');
t_ok(strpos($oh, 'Pending scheduling — every') !== false, 'the Operations desk shows the cumulative pending-scheduling panel');
$src = (string)file_get_contents(__DIR__ . '/../lib/tosrm.php');
t_ok(strpos($src, "'pending' => \$pending") !== false, 'the Operations home is given the pending list');
