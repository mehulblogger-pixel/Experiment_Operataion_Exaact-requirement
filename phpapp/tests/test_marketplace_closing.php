<?php
// Owner, 11 Nov 2026 — "Should an open requirement close by itself after some
// days with no award? … there shall be some mechanism, research with respect to
// real world scenario." A closing date, a reminder, a one-tap extend, and an
// untouched posting closing as EXPIRED; a job being shortlisted never does.
t_section('Marketplace — requirements have a closing date');
t_as_admin();
connect_market_migrate(); if (function_exists('cvp_migrate')) cvp_migrate();
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $T = date('Y-m-d');
    $d = fn($n) => date('Y-m-d', strtotime($T . ' ' . ($n >= 0 ? '+' : '') . $n . ' days'));
    t_eq([cx_req_open_days(), cx_req_remind_days()], [30, 3], 'by default a requirement stays open 30 days, with a reminder 3 days before');

    $r1 = cx_requirement_create(['title' => 'No start date', 'positions' => 1], true);
    t_eq(cx_requirement_get($r1)['closes_on'], $d(30), 'posted with no start date: applications close in 30 days');
    $r2 = cx_requirement_create(['title' => 'Starts in 10 days', 'positions' => 1, 'start_date' => $d(10)], true);
    t_eq(cx_requirement_get($r2)['closes_on'], $d(9), 'work starting in 10 days: applications close the day before it starts');
    $r3 = cx_requirement_create(['title' => 'Starts in 90 days', 'positions' => 1, 'start_date' => $d(90)], true);
    t_eq(cx_requirement_get($r3)['closes_on'], $d(30), 'work starting in 90 days: the 30-day window comes first');

    [$e1, $m1] = cx_requirement_extend($r1, 15, 'Client');
    t_ok($e1 && cx_requirement_get($r1)['closes_on'] === $d(45), 'one tap keeps it open 15 days more: ' . $m1);
    [$e2, $m2] = cx_requirement_set_closes_on($r1, $d(-1));
    t_ok(!$e2, 'a closing date in the past is refused: ' . $m2);

    t_section('Marketplace — the daily run: remind, then close what nobody looked at');
    $party = 880000 + (getmypid() % 9999);
    $soon = cx_requirement_create(['title' => 'Closing soon', 'positions' => 1, 'poster_party_id' => $party], true);
    db()->prepare("UPDATE cx_requirements SET poster_party_id=?, closes_on=? WHERE id=?")->execute([$party, $d(2), $soon]);
    $gone = cx_requirement_create(['title' => 'Nobody looked', 'positions' => 1, 'poster_party_id' => $party], true);
    db()->prepare("UPDATE cx_requirements SET poster_party_id=?, closes_on=? WHERE id=?")->execute([$party, $d(-1), $gone]);
    db()->prepare("INSERT INTO inspectors (name,status,created_at) VALUES ('Waiting Applicant','ACTIVE',?)")->execute([date('c')]);
    $wa = cx_application_add($gone, ['inspector_id' => (int) db()->lastInsertId()]);
    $busy = cx_requirement_create(['title' => 'Being shortlisted', 'positions' => 1, 'poster_party_id' => $party], true);
    db()->prepare("UPDATE cx_requirements SET poster_party_id=?, closes_on=?, status='SHORTLISTING' WHERE id=?")->execute([$party, $d(-1), $busy]);
    $old = cx_requirement_create(['title' => 'Posted before closing dates existed', 'positions' => 1], true);
    db()->prepare("UPDATE cx_requirements SET closes_on='', posted_at=? WHERE id=?")->execute([$d(-200) . 'T09:00:00', $old]);

    $s = cx_req_expiry_sweep();
    t_eq(cx_requirement_get($gone)['status'], 'EXPIRED', 'a job past its closing date with nobody shortlisted closes as expired');
    t_eq(cx_application_get($wa)['status'], 'REJECTED', '… and its applicant is no longer left waiting at "Applied"');
    t_eq(cx_requirement_get($busy)['status'], 'SHORTLISTING', 'a job being shortlisted is never closed by the clock');
    t_eq(cx_requirement_get($old)['closes_on'], $d(7), 'an older requirement gets a closing date with a week\'s notice — nothing closes the day this arrives');
    t_eq(cx_requirement_get($old)['status'], 'OPEN', '… and stays open');
    $n = fn($ev, $id) => (int) ops_val("SELECT COUNT(*) FROM portal_notifications WHERE audience='CLIENT' AND partner_id=? AND event=? AND ref_id=?", [$party, $ev, $id]);
    t_eq([$n('MKT_REQ_CLOSING', $soon), $n('MKT_REQ_EXPIRED', $gone), $n('MKT_REQ_DECIDE', $busy)], [1, 1, 1], 'the client is told: closing soon, closed, and "time to decide"');
    t_ok($s['expired'] >= 1 && $s['reminded'] >= 1, 'the run reports what it did');
    cx_req_expiry_sweep();
    t_eq($n('MKT_REQ_CLOSING', $soon), 1, 'a reminder is sent once, not every day');
    t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CX_REQUIREMENT' AND entity_id=? AND subject LIKE 'Closed as expired%'", [$gone]) === 1, 'the closing is on the job\'s history');

    $view = file_get_contents(__DIR__ . '/../views/ops/_cx_closing.php');
    t_ok(strpos($view, 'req_extend') !== false && strpos($view, '+<?= $cxD ?> days') !== false, 'the job page shows the closing date with +7 / +15 / +30 day buttons');
    t_ok(strpos(file_get_contents(__DIR__ . '/../cron.php'), 'cx_req_expiry_sweep') !== false, 'the nightly run does this');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
