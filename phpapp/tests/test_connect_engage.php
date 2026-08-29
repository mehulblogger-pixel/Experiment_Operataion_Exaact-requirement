<?php
// Connect K20 — freelancer engagements/bookings + the withdraw gap-fill. Asserts a
// booking captures its BASIS (man-days / man-months / deputation / continuous /
// frequency), only after award, computes a value where it makes sense, shows in the
// freelancer's own bookings, and that a professional can withdraw a live application.
// (t_eq is t_eq($got, $want).)
t_section('connect engagements & bookings (K20)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_register(['name' => 'Book Bina', 'email' => 'bina@example.com', 'password' => 'secret12']);
    $pid = connect_pro_id();
    connect_pro_register(['name' => 'Other Om', 'email' => 'om2@example.com', 'password' => 'secret12']);
    $pid2 = connect_pro_id();

    // --- withdraw (gap-fill) on its own requirement -------------------------
    $ridW = cx_requirement_create(['title' => 'Painting inspector', 'discipline_code' => 'WELD'], true);
    $aidW = cx_application_add($ridW, ['applicant_professional_id' => $pid, 'applicant_name' => 'Book Bina']);
    t_ok(in_array('WITHDRAWN', CX_APP_STATUSES, true), 'WITHDRAWN is a known application status');
    [$xok] = connect_pro_withdraw($pid2, $aidW);
    t_ok(!$xok, 'a professional cannot withdraw someone else\'s application');
    [$wok] = connect_pro_withdraw($pid, $aidW);
    t_ok($wok, 'the owner withdraws their live application');
    t_eq((string)ops_val("SELECT status FROM cx_applications WHERE id=?", [$aidW]), 'WITHDRAWN', 'the application is now WITHDRAWN');
    [$again] = connect_pro_withdraw($pid, $aidW);
    t_ok(!$again, 'a withdrawn application cannot be withdrawn again');

    // --- booking on an awarded requirement ----------------------------------
    $rid = cx_requirement_create(['title' => 'Turnaround QA/QC', 'discipline_code' => 'WELD', 'poster_name' => 'Nayara', 'location' => 'Vadinar'], true);
    $aid = cx_application_add($rid, ['applicant_professional_id' => $pid, 'applicant_name' => 'Book Bina']);

    // Booking is refused before award.
    [$notyet] = connect_engage_save_for_requirement($rid, ['basis' => 'MAN_DAYS', 'quantity' => 10, 'rate' => 2500]);
    t_ok(!$notyet, 'a booking cannot be recorded before the requirement is awarded');

    db()->prepare("UPDATE cx_applications SET status='ACCEPTED' WHERE id=?")->execute([$aid]);
    db()->prepare("UPDATE cx_requirements SET status='AWARDED', awarded_application_id=? WHERE id=?")->execute([$aid, $rid]);

    // MAN_DAYS: value = qty × rate.
    [$ok1, , $eid] = connect_engage_save_for_requirement($rid, ['basis' => 'MAN_DAYS', 'quantity' => 10, 'rate' => 2500, 'rate_unit' => 'day', 'start_date' => '2026-09-01']);
    t_ok($ok1 && $eid > 0, 'a man-days booking is recorded once awarded');
    $eng = connect_engage_for_requirement($rid);
    t_eq((string)$eng['basis'], 'MAN_DAYS', 'the basis is stored');
    t_eq((string)$eng['subject_kind'], 'professional', 'the awarded professional is the subject');
    t_eq((int)$eng['subject_id'], $pid, 'the subject is the right professional');
    $d = connect_engage_describe($eng);
    t_eq((int)$d['total'], 25000, 'man-days value is quantity × rate (10 × 2500)');
    t_ok(strpos($d['commitment'], 'man-days') !== false, 'the commitment reads in man-days');

    // Re-save switches basis (one engagement per requirement — upsert).
    [$ok2] = connect_engage_save_for_requirement($rid, ['basis' => 'MAN_MONTHS', 'quantity' => 6, 'rate' => 60000, 'rate_unit' => 'month', 'start_date' => '2026-09-01', 'end_date' => '2027-03-01']);
    t_ok($ok2, 'the booking can be switched to man-months');
    $eng = connect_engage_for_requirement($rid);
    t_eq((string)$eng['basis'], 'MAN_MONTHS', 'the basis switched');
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_engagements WHERE requirement_id=?", [$rid]), 1, 'still exactly one engagement per requirement (upsert)');
    t_eq((int)connect_engage_describe($eng)['total'], 360000, 'man-months value is months × rate (6 × 60000)');

    // CONTINUOUS / FREQUENCY: no fixed total, honest description.
    connect_engage_save_for_requirement($rid, ['basis' => 'CONTINUOUS', 'rate' => 55000, 'rate_unit' => 'month', 'start_date' => '2026-09-01']);
    t_ok(connect_engage_describe(connect_engage_for_requirement($rid))['total'] === null, 'a continuous engagement has no fixed total (ongoing)');
    [$freqBad] = connect_engage_save_for_requirement($rid, ['basis' => 'FREQUENCY', 'rate' => 3000, 'rate_unit' => 'visit']);
    t_ok(!$freqBad, 'a frequency booking requires the frequency described');
    connect_engage_save_for_requirement($rid, ['basis' => 'FREQUENCY', 'rate' => 3000, 'rate_unit' => 'visit', 'frequency_note' => '2 days / week', 'start_date' => '2026-09-01']);
    t_ok(strpos(connect_engage_describe(connect_engage_for_requirement($rid))['commitment'], '2 days') !== false, 'a frequency booking shows its cadence');

    // The freelancer sees their booking; another professional does not.
    $mine = connect_engage_for_professional($pid);
    t_ok(count($mine) === 1 && (int)$mine[0]['requirement_id'] === $rid, 'the booking appears under the professional\'s own bookings');
    t_ok(strpos((string)$mine[0]['req_title'], 'Turnaround') !== false, 'the booking carries the requirement title');
    t_eq(count(connect_engage_for_professional($pid2)), 0, 'another professional sees none of this booking (subject-scoped)');

    // Lifecycle: mark active → the professional summary reflects it.
    connect_engage_set_status((int)connect_engage_for_requirement($rid)['id'], 'ACTIVE');
    t_eq((int)connect_engage_summary_pro($pid)['active'], 1, 'marking active updates the professional booking summary');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
