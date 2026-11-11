<?php
// Owner, 11 Nov 2026 — "One person, one requirement award … if the professional
// who is awarded the job either cancels or is unable to attend the job as
// required same can be awarded to the other professional."
t_section('Marketplace — one person per award, and a replacement when they cannot do it');
t_as_admin();
connect_market_migrate(); connect_engage_migrate();
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $ins = [];
    foreach (['Anil UT', 'Bina UT', 'Chetan UT', 'Dilip UT'] as $n) {
        db()->prepare("INSERT INTO inspectors (name,status,created_at) VALUES (?,'ACTIVE',?)")->execute([$n, date('c')]);
        $ins[] = (int) db()->lastInsertId();
    }
    $rid = cx_requirement_create(['title' => 'UT technician — 10 days', 'positions' => 1, 'work_type' => 'day_rate', 'poster_party_id' => 4242], true);
    $a = []; foreach ([4000, 3800, 3900, 3500] as $k => $rate) $a[] = cx_application_add($rid, ['inspector_id' => $ins[$k], 'proposed_rate' => $rate]);
    cx_application_transition($a[3], 'REJECTED');            // the client turned Dilip down
    t_ok(cx_requirement_award($rid, $a[0]), 'ARMING · the job is awarded to Anil');
    t_eq(count(array_filter(cx_applications_for($rid), fn($x) => $x['status'] === 'ACCEPTED')), 1, 'one person holds the award');
    [$bk] = connect_engage_save_for_requirement($rid, ['basis' => 'MAN_DAYS', 'quantity' => 10, 'rate' => 4000, 'start_date' => '2026-12-01']);
    t_ok($bk, 'ARMING · 10 man-days booked at 4,000');
    t_ok(!cx_requirement_takes_applicants(cx_requirement_get($rid)), 'while Anil\'s booking is live, nobody else is invited onto the job');

    $cand = array_map(fn($x) => (int) $x['id'], cx_replacement_candidates($rid));
    sort($cand);
    t_eq($cand, [$a[1], $a[2]], 'the replacements on offer are those closed only because Anil was awarded — not Dilip, whom the client turned down');
    [$r0, $m0] = cx_requirement_replace($rid, $a[1], ['reason' => 'x']);
    t_ok(!$r0, 'a reason for the change is required: ' . $m0);
    [$r1, $m1] = cx_requirement_replace($rid, $a[3], ['kind' => 'NO_SHOW']);
    t_ok(!$r1, 'someone the client turned down cannot be given the job: ' . $m1);

    [$ok, $msg] = cx_requirement_replace($rid, $a[1], ['kind' => 'NO_SHOW', 'reason' => 'did not report on day 4', 'worked' => 3], 'Coordinator');
    t_ok($ok, 'Anil did not turn up after 3 days; the job goes to Bina: ' . $msg);
    $r = cx_requirement_get($rid);
    t_eq([$r['status'], (int) $r['awarded_application_id']], ['AWARDED', $a[1]], 'the requirement stays awarded — now to Bina');
    $oa = cx_application_get($a[0]);
    t_ok($oa['status'] === 'WITHDRAWN' && $oa['replaced_at'] !== '', 'Anil\'s award is withdrawn, with when it happened');
    t_eq(cx_application_get($a[1])['status'], 'ACCEPTED', 'Bina is accepted');
    t_eq(count(array_filter(cx_applications_for($rid), fn($x) => $x['status'] === 'ACCEPTED')), 1, 'still exactly one person holds the award');

    $engs = ops_all("SELECT * FROM cx_engagements WHERE requirement_id=? ORDER BY id", [$rid]);
    t_eq(count($engs), 2, 'Anil\'s booking is kept as history; Bina has her own');
    t_eq([$engs[0]['status'], $engs[0]['cancel_kind'], (float) $engs[0]['worked_qty']], ['CANCELLED', 'NO_SHOW', 3.0], 'Anil: ended as a no-show, 3 days worked');
    t_eq([$engs[1]['status'], (float) $engs[1]['quantity'], (float) $engs[1]['rate'], (int) $engs[1]['replaces_engagement_id']], ['BOOKED', 7.0, 3800.0, (int) $engs[0]['id']],
        'Bina: booked for the 7 days left, at her own rate 3,800, linked to the booking she replaces');
    t_ok(!in_array((int) $engs[0]['id'], array_map(fn($x) => (int) $x['id'], connect_engage_needs_cover()), true), 'the job no longer shows as needing cover');
    t_ok((int) ops_val("SELECT COUNT(*) FROM activities WHERE entity_kind='CX_REQUIREMENT' AND entity_id=? AND subject LIKE '%in place of%'", [$rid]) === 1, 'the change is on the job\'s history');

    t_ok(connect_engagement_billable($rid) > 0, 'the job can be billed');
    $b = connect_engagement_billable_row($rid);
    t_eq([(float) $b['qty'], (float) $b['amount']], [10.0, 38600.0], 'billing covers both: 3 days × 4,000 (Anil) + 7 days × 3,800 (Bina) = 38,600');
    t_ok(strpos((string) $b['calc_rule'], 'Anil UT 3 day(s) worked') !== false && strpos((string) $b['calc_rule'], 'Bina UT 7 day(s)') !== false, 'and the billing note says who did what');

    t_section('Marketplace — a no-show with nothing worked is not billed');
    $r2 = cx_requirement_create(['title' => 'MPI — 2 days', 'positions' => 1, 'work_type' => 'day_rate'], true);
    $x = cx_application_add($r2, ['inspector_id' => $ins[0], 'proposed_rate' => 3000]);
    cx_requirement_award($r2, $x);
    connect_engage_save_for_requirement($r2, ['basis' => 'MAN_DAYS', 'quantity' => 2, 'rate' => 3000, 'start_date' => '2026-12-05']);
    $e2 = connect_engage_for_requirement($r2);
    connect_engage_cancel((int) $e2['id'], 'NO_SHOW', 'never arrived');
    t_eq(connect_engagement_billable($r2), 0, 'nothing worked, nothing billed');
    t_ok(cx_requirement_takes_applicants(cx_requirement_get($r2)), 'and the job now takes people again, so a replacement can be invited');
    t_eq(cx_requirement_replace($r2, $x, ['kind' => 'NO_SHOW'])[0], false, 'the person who did not turn up cannot be "replaced" by themselves');
    cx_requirement_transition($r2, 'CLOSED');
    t_ok(!cx_requirement_takes_applicants(cx_requirement_get($r2)), 'a closed job takes nobody');
    t_eq(cx_requirement_replace($r2, $x, ['kind' => 'NO_SHOW'])[0], false, 'and a closed job cannot be given to someone else');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
