<?php
// Owner, 11 Nov 2026 — "Should a client be able to record the booking (days or
// months bought) from their own portal?" — "Yes". The client says how much and
// from when; the price stays the agreed one; once billed it is fixed.
t_section('Client portal — the client records what it bought');
t_as_admin();
connect_market_migrate(); connect_engage_migrate();
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $party = 990000 + (getmypid() % 9999);
    db()->prepare("INSERT INTO inspectors (name,status,created_at) VALUES ('Dev UT','ACTIVE',?)")->execute([date('c')]);
    $ins = (int) db()->lastInsertId();
    $rid = cx_requirement_create(['title' => 'UT for tank floor', 'positions' => 1, 'work_type' => 'day_rate', 'poster_party_id' => $party], true);
    db()->prepare("UPDATE cx_requirements SET poster_party_id=? WHERE id=?")->execute([$party, $rid]);
    $app = cx_application_add($rid, ['inspector_id' => $ins, 'proposed_rate' => 4200]);

    [$n0, $m0] = connect_engage_client_save($rid, $party, ['basis' => 'MAN_DAYS', 'quantity' => 5, 'start_date' => '2026-12-01']);
    t_ok(!$n0, 'before the award there is nothing to book: ' . $m0);
    cx_requirement_award($rid, $app);

    [$x, $mx] = connect_engage_client_save($rid, $party + 1, ['basis' => 'MAN_DAYS', 'quantity' => 5, 'start_date' => '2026-12-01']);
    t_ok(!$x, 'another client cannot book this job: ' . $mx);
    [$q, $mq] = connect_engage_client_save($rid, $party, ['basis' => 'MAN_DAYS', 'start_date' => '2026-12-01']);
    t_ok(!$q, 'man-days need a number of days: ' . $mq);
    [$d, $md] = connect_engage_client_save($rid, $party, ['basis' => 'MAN_MONTHS', 'quantity' => 2, 'start_date' => '2026-12-01']);
    t_ok(!$d, 'man-months need an end date: ' . $md);
    [$ok, $m] = connect_engage_client_save($rid, $party, ['basis' => 'MAN_DAYS', 'quantity' => 5, 'start_date' => '2026-12-01', 'rate' => 1]);
    t_ok($ok, 'the client books 5 man-days from 1 Dec: ' . $m);
    $e = connect_engage_for_requirement($rid);
    t_eq([(float) $e['quantity'], (float) $e['rate'], $e['status']], [5.0, 4200.0, 'BOOKED'], 'the rate is the agreed bid (4,200), not what the client typed');

    db()->prepare("UPDATE cx_engagements SET rate=4500, rate_inclusive='EXCLUSIVE', status='ACTIVE' WHERE id=?")->execute([(int) $e['id']]);
    [$ok2] = connect_engage_client_save($rid, $party, ['basis' => 'MAN_DAYS', 'quantity' => 7, 'start_date' => '2026-12-01']);
    $e2 = connect_engage_for_requirement($rid);
    t_ok($ok2 && (float) $e2['quantity'] === 7.0, 'the client can change how much it booked');
    t_eq([(float) $e2['rate'], $e2['rate_inclusive'], $e2['status']], [4500.0, 'EXCLUSIVE', 'ACTIVE'], 'what the company set on the booking (rate, rate model, status) is kept');
    t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CX_REQUIREMENT' AND entity_id=? AND subject LIKE 'Booking%by the client%'", [$rid]) >= 2, 'each booking by the client is on the job\'s history');

    t_ok(connect_engagement_billable($rid) > 0, 'ARMING · the company sends the job to billing');
    [$l, $ml] = connect_engage_client_save($rid, $party, ['basis' => 'MAN_DAYS', 'quantity' => 1, 'start_date' => '2026-12-01']);
    t_ok(!$l && strpos($ml, 'billing') !== false, 'once billed the client can no longer change it: ' . $ml);
    t_eq((float) connect_engage_for_requirement($rid)['quantity'], 7.0, 'and the billed booking is untouched');

    $view = file_get_contents(__DIR__ . '/../views/portal/hire_req.php');
    t_ok(strpos($view, 'engage_save') !== false && strpos($view, 'Only the company can change the rate') !== false, 'the portal job page has the booking form and says who sets the rate');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
