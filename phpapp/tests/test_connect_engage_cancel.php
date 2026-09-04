<?php
// Cancellation / no-show / emergency replacement (Stage 7). A booking can end
// early with a recorded reason + kind (status stays CANCELLED), which frees the
// resource and surfaces the work as "needs cover" until a replacement covers it.
t_section('engagement cancellation, no-show & replacement');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_engage_migrate();
    connect_engage_migrate(); // idempotent
    t_ok(count(connect_engage_cancel_kinds()) >= 3, 'the cancel-kind vocabulary is present');

    // a professional booked 10–20 Oct for a client (party 4242)
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,created_at) VALUES ('cancel.pro@demo.test','Cancel Pro',1,?)")->execute([date('c')]);
    $pro = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_engagements (requirement_id,subject_kind,subject_id,subject_name,poster_party_id,start_date,end_date,status,created_at) VALUES (77,'professional',?,'Cancel Pro',4242,'2026-10-10','2026-10-20','BOOKED',?)")->execute([$pro, date('c')]);
    $eid = (int)db()->lastInsertId();

    // the resource is committed → a conflict shows on an overlapping window
    t_eq(connect_conflict_check($pro, '2026-10-12', '2026-10-15')['status'], 'CONFLICT', 'a live booking conflicts before cancellation');

    // no-show cancellation with a reason
    [$ok, $msg] = connect_engage_cancel($eid, 'NO_SHOW', 'Did not report to site on day 1', 'coord');
    t_ok($ok, 'the booking can be cancelled as a no-show');
    $row = ops_one("SELECT status, cancel_kind, cancel_reason FROM cx_engagements WHERE id=?", [$eid]);
    t_eq($row['status'], 'CANCELLED', 'status becomes CANCELLED');
    t_eq($row['cancel_kind'], 'NO_SHOW', 'the kind records it was a no-show');
    t_ok(strpos((string)$row['cancel_reason'], 'day 1') !== false, 'the reason is recorded');

    // freeing: the resource is now available again on the same window
    t_eq(connect_conflict_check($pro, '2026-10-12', '2026-10-15')['status'], 'CLEAR', 'after cancellation the resource is free again');

    // it surfaces as needing cover, scoped to the client
    $cover = connect_engage_needs_cover(4242);
    $found = false; foreach ($cover as $c) if ((int)$c['id'] === $eid) $found = true;
    t_ok($found, 'the cancelled booking appears in needs-cover for its client');
    t_ok(count(connect_engage_needs_cover(9999)) === 0, 'needs-cover is scoped to the client');

    // a replacement booking covers it → it leaves the needs-cover list
    db()->prepare("INSERT INTO cx_engagements (requirement_id,subject_kind,subject_id,subject_name,poster_party_id,start_date,end_date,status,created_at) VALUES (77,'professional',?,'Replacement Pro',4242,'2026-10-12','2026-10-20','BOOKED',?)")->execute([$pro, date('c')]);
    $rep = (int)db()->lastInsertId();
    t_ok(connect_engage_mark_replacement($rep, $eid), 'a new booking can be marked as the replacement');
    $found2 = false; foreach (connect_engage_needs_cover(4242) as $c) if ((int)$c['id'] === $eid) $found2 = true;
    t_ok(!$found2, 'once covered, the cancelled booking leaves needs-cover');

    // an unknown engagement never errors
    [$ok2] = connect_engage_cancel(0, 'CANCELLED', 'x');
    t_ok(!$ok2, 'cancelling an unknown engagement fails cleanly');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
