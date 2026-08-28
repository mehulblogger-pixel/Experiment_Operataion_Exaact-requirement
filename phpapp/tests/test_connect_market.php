<?php
// Connect K2a — the manpower marketplace core: post a requirement, apply,
// shortlist, offer, award. Asserts the happy path AND that illegal lifecycle
// moves are refused, and that a professional cannot apply to the same
// requirement twice.
t_section('connect manpower marketplace (K2a)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // Two professionals in the pool to apply.
    db()->prepare("INSERT INTO inspectors (name,status,created_at) VALUES ('Meena NDT','ACTIVE',?)")->execute([date('c')]);
    $i1 = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspectors (name,status,created_at) VALUES ('Sunil Welder','ACTIVE',?)")->execute([date('c')]);
    $i2 = (int)db()->lastInsertId();

    // --- post a requirement (create then post) -----------------------------
    $rid = cx_requirement_create(['title' => 'UT technician for a shutdown at Hazira', 'positions' => 2, 'work_type' => 'shutdown'], false);
    $r = cx_requirement_get($rid);
    t_eq('DRAFT', $r['status'], 'a new requirement starts as DRAFT');
    t_ok(strpos((string)$r['ref_code'], 'CX-REQ-') === 0, 'it gets a CX-REQ ref code');

    t_ok(cx_requirement_transition($rid, 'OPEN'), 'DRAFT → OPEN is allowed (post)');
    t_ok(!cx_requirement_transition($rid, 'AWARDED'), 'OPEN → AWARDED is refused (must shortlist first)');

    // --- applications -------------------------------------------------------
    $a1 = cx_application_add($rid, ['inspector_id' => $i1, 'proposed_rate' => 4000]);
    $a2 = cx_application_add($rid, ['inspector_id' => $i2, 'proposed_rate' => 3800]);
    t_ok($a1 > 0 && $a2 > 0, 'two professionals apply');
    t_eq(0, cx_application_add($rid, ['inspector_id' => $i1]), 'the same professional cannot apply twice');
    t_eq('APPLIED', cx_application_get($a1)['status'], 'an application starts APPLIED');

    // --- illegal application move is refused --------------------------------
    t_ok(!cx_application_transition($a1, 'ACCEPTED'), 'APPLIED → ACCEPTED is refused (must go via shortlist/offer)');
    t_ok(cx_application_transition($a1, 'SHORTLISTED'), 'APPLIED → SHORTLISTED is allowed');
    t_ok(cx_application_transition($a2, 'REJECTED'), 'the other application can be rejected');

    // --- shortlisting + award ----------------------------------------------
    t_ok(cx_requirement_transition($rid, 'SHORTLISTING'), 'OPEN → SHORTLISTING is allowed');
    t_ok(cx_requirement_award($rid, $a1), 'award drives the chosen application to ACCEPTED and the requirement to AWARDED');
    t_eq('AWARDED', cx_requirement_get($rid)['status'], 'requirement is AWARDED');
    t_eq('ACCEPTED', cx_application_get($a1)['status'], 'the awarded application is ACCEPTED');
    t_eq($a1, (int)cx_requirement_get($rid)['awarded_application_id'], 'the award is recorded on the requirement');

    // --- terminal guard -----------------------------------------------------
    t_ok(cx_requirement_transition($rid, 'CLOSED'), 'AWARDED → CLOSED is allowed');
    t_ok(!cx_requirement_transition($rid, 'OPEN'), 'a CLOSED requirement cannot reopen');

    // --- summary counts -----------------------------------------------------
    $s = cx_market_summary();
    t_ok($s['total'] >= 1 && $s['apps'] >= 2, 'summary counts requirements and applications');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
