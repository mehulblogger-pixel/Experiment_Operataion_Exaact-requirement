<?php
// Owner, 10 Nov 2026 — "Marketplace seems to be most non functional, not
// understood and very very complex." Each fix here is checked against what the
// owner hit: award needing hidden steps, applicants left waiting, closing a job
// stopping its billing, billing ignoring the booking, ratings saved against
// nobody, a posted requirement that could not be corrected, one work-type list,
// and messages signed with another product's name.
t_section('Marketplace — one press awards, the others are told');
t_as_admin();
connect_market_migrate();
if (function_exists('connect_engage_migrate')) connect_engage_migrate();
if (function_exists('connect_ratings_migrate')) connect_ratings_migrate();

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $ins = [];
    foreach (['Asha UT', 'Bala MPI', 'Chirag RT'] as $n) {
        db()->prepare("INSERT INTO inspectors (name,status,created_at) VALUES (?,'ACTIVE',?)")->execute([$n, date('c')]);
        $ins[] = (int) db()->lastInsertId();
    }
    $rid = cx_requirement_create(['title' => 'UT technician, refinery shutdown', 'positions' => 2, 'work_type' => 'shutdown', 'rate_min' => 3000, 'rate_max' => 4000], false);
    t_ok(cx_requirement_transition($rid, 'OPEN'), 'ARMING · the requirement is posted');
    $a = [];
    foreach ($ins as $i) $a[] = cx_application_add($rid, ['inspector_id' => $i, 'proposed_rate' => 3500]);
    cx_application_transition($a[2], 'SHORTLISTED');

    t_ok(cx_requirement_award($rid, $a[0]), 'an applicant is awarded straight from "Applied" on an open requirement');
    t_eq(cx_requirement_get($rid)['status'], 'AWARDED', 'the requirement is awarded without a separate "start shortlisting" step');
    t_eq(cx_application_get($a[0])['status'], 'ACCEPTED', 'the chosen applicant is accepted');
    t_eq([cx_application_get($a[1])['status'], cx_application_get($a[2])['status']], ['REJECTED', 'REJECTED'],
        'everyone else still waiting is closed as not selected');
    t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CX_REQUIREMENT' AND entity_id=? AND subject LIKE 'Awarded to%'", [$rid]) >= 1,
        'the award is written to the history');

    t_section('Marketplace — a finished job can still be billed, one way only');
    t_ok(cx_requirement_transition($rid, 'CLOSED'), 'ARMING · the job is closed');
    [$bok, $bmsg] = connect_engage_save_for_requirement($rid, ['basis' => 'MAN_DAYS', 'quantity' => 5, 'rate' => 3600, 'rate_unit' => 'day']);
    t_ok($bok, 'a booking can still be recorded after closing: ' . $bmsg);
    $be = connect_engagement_billable($rid);
    t_ok($be > 0, 'and a closed requirement can be sent to billing');
    $row = connect_engagement_billable_row($rid);
    t_eq([(float) $row['qty'], (float) $row['rate'], (float) $row['amount']], [10.0, 3600.0, 36000.0],
        'billing uses what was booked: 5 days × 2 positions at 3,600 = 36,000');

    t_eq(connect_bridge_unit('remote_review'), 'review', 'a remote document review is billed per review');
    t_eq(connect_requirement_voucher_invoices($rid), 0, 'no voucher has produced an invoice for this job');

    t_section('Marketplace — ratings reach the people they are about');
    $rt = cx_rating_add($rid, 'CLIENT_TO_PRO', ['stars' => 4, 'would_rehire' => 1]);
    t_ok($rt > 0, 'the client rates the professional');
    $r = ops_one("SELECT * FROM cx_ratings WHERE id=?", [$rt]);
    t_eq([(int) $r['application_id'], (int) $r['ratee_inspector_id']], [$a[0], $ins[0]], 'the rating is tied to the award and to the person awarded');
    t_eq(cx_rating_summary_for_inspector($ins[0])['count'], 1, 'and shows on that person\'s marketplace record');

    t_section('Marketplace — a posted requirement can be corrected until it is awarded');
    $r2 = cx_requirement_create(['title' => 'MPI tech', 'positions' => 1, 'work_type' => 'per_visit'], false);
    cx_requirement_transition($r2, 'OPEN');
    [$u1, $m1] = cx_requirement_update($r2, ['title' => 'MPI technician — Dahej', 'positions' => 3, 'work_type' => 'manday', 'rate_min' => 2000, 'rate_max' => 2500, 'location' => 'Dahej']);
    t_ok($u1, 'an open requirement takes corrected details: ' . $m1);
    $g = cx_requirement_get($r2);
    t_eq([$g['title'], (int) $g['positions'], $g['work_type'], $g['location'], $g['status']], ['MPI technician — Dahej', 3, 'manday', 'Dahej', 'OPEN'],
        'title, positions, work type and place change; the status does not');
    [$u2, $m2] = cx_requirement_update($r2, ['title' => 'x', 'rate_min' => 5000, 'rate_max' => 100]);
    t_ok(!$u2 && strpos($m2, 'lowest rate') !== false, 'a lowest rate above the highest is refused: ' . $m2);
    cx_requirement_update($r2, ['title' => 'MPI technician — Dahej', 'work_type' => 'not-a-kind-of-work', 'positions' => 3]);
    t_eq(cx_requirement_get($r2)['work_type'], 'manday', 'a kind of work that is not on the list is not saved');
    [$u3] = cx_requirement_update($r2, ['title' => '']);
    t_ok(!$u3, 'a blank title is refused');
    [$u4, $m4] = cx_requirement_update($rid, ['title' => 'Changed after the award']);
    t_ok(!$u4 && cx_requirement_get($rid)['title'] === 'UT technician, refinery shutdown', 'an awarded or closed requirement cannot be changed: ' . $m4);

    t_section('Marketplace — one list of kinds of work, messages under the company\'s name');
    $wt = cx_pro_work_types();
    t_ok(isset($wt['manday'], $wt['short_project'], $wt['per_visit'], $wt['remote_review']), 'man-day deputation and short projects can be chosen on a posting');
    $concierge = file_get_contents(__DIR__ . '/../views/ops/connect_concierge.php') . file_get_contents(__DIR__ . '/../views/ops/connect_requirements.php');
    t_ok(substr_count($concierge, 'cx_pro_work_types()') >= 2, 'the desk and the guided post both use that one list');
    $co = (string) (doc_identity()['name'] ?? '');
    $msg = connect_channel_render('Hi {name}, a new job is open. — ITSN', ['name' => 'Asha']);
    t_ok(strpos($msg, 'ITSN') === false && ($co === '' || strpos($msg, $co) !== false), 'an old template signed "ITSN" goes out under the company\'s name: ' . $msg);
    t_eq(connect_channel_render('— {company}', ['company' => 'Acme Inspection']), '— Acme Inspection', '{company} fills in the company name');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
