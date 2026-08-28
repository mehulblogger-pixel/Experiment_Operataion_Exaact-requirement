<?php
// Connect A2 — a self-registered professional browses open requirements and
// applies as themselves. Asserts the application is recorded against the
// professional, dedupes, shows in their own list, and reaches the client/staff
// side (cx_applications_for the requirement).
t_section('connect freelancer apply (A2)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // A registered professional.
    connect_pro_register(['name' => 'Applying Anita', 'email' => 'anita@example.com', 'password' => 'secret12']);
    $pid = connect_pro_id();

    // Two open requirements + one closed.
    $r1 = cx_requirement_create(['title' => 'Welding inspector — Dahej', 'discipline_code' => 'WELD'], true);
    $r2 = cx_requirement_create(['title' => 'NDT technician — Hazira', 'discipline_code' => 'NDT'], true);
    $rClosed = cx_requirement_create(['title' => 'Old job'], true);
    cx_requirement_transition($rClosed, 'CLOSED');

    // The open board a professional sees includes the open ones, not the closed.
    $ids = array_map(fn($r) => (int)$r['id'], cx_open_requirements());
    t_ok(in_array($r1, $ids, true) && in_array($r2, $ids, true), 'open requirements are visible to browse');
    t_ok(!in_array($rClosed, $ids, true), 'a closed requirement is not on the open board');

    // Apply to r1 as the professional.
    $aid = cx_application_add($r1, ['applicant_professional_id' => $pid, 'applicant_name' => 'Applying Anita', 'proposed_rate' => 4200]);
    t_ok($aid > 0, 'the professional can apply');
    t_eq($pid, (int)cx_application_get($aid)['applicant_professional_id'], 'the application is recorded against the professional');

    // Cannot apply twice.
    t_eq(0, cx_application_add($r1, ['applicant_professional_id' => $pid]), 'the professional cannot apply to the same job twice');

    // Shows in the professional's own applications, and on the requirement (client side).
    $mine = connect_pro_applications($pid);
    t_ok(count($mine) === 1 && (int)$mine[0]['requirement_id'] === $r1, 'it appears in the professional\'s own applications');
    $onReq = array_map(fn($a) => (int)$a['id'], cx_applications_for($r1));
    t_ok(in_array($aid, $onReq, true), 'the client/staff side sees the application on the requirement');

    // The applied-map marks r1 but not r2.
    $map = connect_pro_applied_map($pid);
    t_ok(!empty($map[$r1]) && empty($map[$r2]), 'the applied-map reflects exactly what they applied to');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
