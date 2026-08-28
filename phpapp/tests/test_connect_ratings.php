<?php
// Connect K9 — two-way ratings on a marketplace engagement. Asserts a rating is
// only allowed once the requirement is awarded/closed, one per direction, stars
// clamped, and the per-professional summary aggregates correctly.
t_section('connect two-way ratings (K9)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO inspectors (name,status,created_at) VALUES ('Rated Ravi','ACTIVE',?)")->execute([date('c')]);
    $pro = (int)db()->lastInsertId();
    $rid = cx_requirement_create(['title' => 'UT tech for shutdown'], true); // OPEN

    // Not allowed while merely OPEN.
    t_eq(0, cx_rating_add($rid, 'CLIENT_TO_PRO', ['stars' => 5, 'ratee_inspector_id' => $pro]), 'no rating while the requirement is only OPEN');

    // Award it (needs a shortlisted application), then rating becomes allowed.
    $a = cx_application_add($rid, ['inspector_id' => $pro]);
    cx_application_transition($a, 'SHORTLISTED');
    cx_requirement_transition($rid, 'SHORTLISTING');
    cx_requirement_award($rid, $a);
    $req = cx_requirement_get($rid);
    t_ok(cx_rating_allowed($req), 'rating is allowed once AWARDED');

    // Client rates the professional; stars clamp to 1..5.
    $r1 = cx_rating_add($rid, 'CLIENT_TO_PRO', ['stars' => 9, 'would_rehire' => 1, 'ratee_inspector_id' => $pro, 'comment' => 'Thorough']);
    t_ok($r1 > 0, 'the client can rate the professional');
    t_eq(5, (int)ops_val("SELECT stars FROM cx_ratings WHERE id=?", [$r1]), 'stars clamp to a maximum of 5');

    // One rating per direction.
    t_eq(0, cx_rating_add($rid, 'CLIENT_TO_PRO', ['stars' => 3, 'ratee_inspector_id' => $pro]), 'the same direction cannot be rated twice');

    // The other direction is independent.
    $r2 = cx_rating_add($rid, 'PRO_TO_CLIENT', ['stars' => 4]);
    t_ok($r2 > 0, 'the professional can rate the client independently');

    // Invalid direction refused.
    t_eq(0, cx_rating_add($rid, 'SIDEWAYS', ['stars' => 5]), 'an invalid direction is refused');

    // Per-professional summary.
    $sum = cx_rating_summary_for_inspector($pro);
    t_eq(1, $sum['count'], 'the professional has one client rating');
    t_eq(5.0, (float)$sum['avg_stars'], 'the average reflects the clamped 5-star rating');
    t_eq(100, (int)$sum['rehire_pct'], 'would-work-again is 100%');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
