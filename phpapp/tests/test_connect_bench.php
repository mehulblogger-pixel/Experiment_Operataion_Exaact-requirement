<?php
// Connect K18 / backlog #7 — the agency bench workspace. Asserts an agency keeps
// a PRIVATE roster (never in the shared cx_professionals pool), only agencies may
// hold a bench, people allocate to requirements and free up on release, and
// utilisation tracks correctly.
t_section('connect agency bench (#7)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // Two organisations: a staffing agency (may hold a bench) and a plain company (may not).
    $agencyId = connect_org_add('BuildForce Staffing', 'MANPOWER_AGENCY');
    $companyId = connect_org_add('Acme Refinery', 'COMPANY');
    t_ok($agencyId > 0 && $companyId > 0, 'an agency and a company org exist');
    t_ok(connect_bench_org_ok($agencyId), 'a staffing agency may hold a bench');
    t_ok(!connect_bench_org_ok($companyId), 'a plain company may NOT hold a bench');

    // A company cannot add bench people.
    [$bad] = connect_bench_add($companyId, ['name' => 'Nope']);
    t_ok(!$bad, 'a non-agency org is refused a bench member');

    // The agency builds its private roster.
    [$ok1, , $b1] = connect_bench_add($agencyId, ['name' => 'Ravi Fitter', 'job_title' => '6G Welder', 'skills' => 'Welding, NDT', 'day_rate' => 2500]);
    [$ok2, , $b2] = connect_bench_add($agencyId, ['name' => 'Sita Rigger', 'job_title' => 'Rigger', 'skills' => 'Rigging']);
    t_ok($ok1 && $ok2 && $b1 > 0 && $b2 > 0, 'the agency adds two people to its bench');
    [$noName] = connect_bench_add($agencyId, ['name' => '  ']);
    t_ok(!$noName, 'a bench member needs a name');

    // --- THE PRIVACY INVARIANT: bench people are NOT in the shared pool --------
    t_eq(0, (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE name IN ('Ravi Fitter','Sita Rigger')"),
        'bench people are NEVER written into the shared self-registered pool');
    // ...and they never surface in the shared talent search.
    if (function_exists('connect_pro_search')) {
        $hits = connect_pro_search(['q' => 'Ravi Fitter']);
        $leak = false; foreach ($hits as $h) if (($h['name'] ?? '') === 'Ravi Fitter') $leak = true;
        t_ok(!$leak, 'a bench person does not appear in the shared talent search');
    } else { t_ok(true, 'shared talent search not present (nothing to leak into)'); }

    // Utilisation before allocation.
    $s0 = connect_bench_summary($agencyId);
    t_eq(2, $s0['total'], 'the bench holds two people');
    t_eq(2, $s0['available'], 'both are available before allocation');

    // Allocate one to a requirement.
    $rid = cx_requirement_create(['title' => 'Shutdown crew', 'discipline_code' => 'WELD', 'poster_name' => 'Acme'], true);
    [$aok, , $allocId] = connect_bench_allocate($b1, $agencyId, $rid);
    t_ok($aok && $allocId > 0, 'an available bench person is allocated to a requirement');
    t_eq('ALLOCATED', (string)ops_val("SELECT availability FROM cx_bench WHERE id=?", [$b1]), 'the allocated person is now marked ALLOCATED');
    $s1 = connect_bench_summary($agencyId);
    t_eq(1, $s1['available'], 'available drops to one');
    t_eq(1, $s1['allocated'], 'allocated is one');

    // No double-allocation to the same requirement.
    [$dupe] = connect_bench_allocate($b1, $agencyId, $rid);
    t_ok(!$dupe, 'the same person cannot be allocated twice to one requirement');

    // Cross-agency safety: another agency cannot allocate this bench person.
    $other = connect_org_add('Rival Staffing', 'MANPOWER_AGENCY');
    [$xok] = connect_bench_allocate($b1, $other, $rid);
    t_ok(!$xok, 'an agency cannot allocate a person who is not on ITS bench');

    // The requirement desk sees the allocation.
    $onReq = connect_bench_allocs_for_requirement($rid);
    t_ok(count($onReq) === 1 && $onReq[0]['bench_name'] === 'Ravi Fitter', 'the requirement shows the allocated agency person');

    // Confirm, then release → the person returns to the bench.
    connect_bench_alloc_set($allocId, $agencyId, 'CONFIRMED');
    t_eq('CONFIRMED', (string)ops_val("SELECT status FROM cx_bench_alloc WHERE id=?", [$allocId]), 'the allocation confirms');
    connect_bench_alloc_set($allocId, $agencyId, 'RELEASED');
    t_eq('AVAILABLE', (string)ops_val("SELECT availability FROM cx_bench WHERE id=?", [$b1]), 'releasing returns the person to the bench');
    $s2 = connect_bench_summary($agencyId);
    t_eq(2, $s2['available'], 'both are available again after release');

    // Taking someone off the bench hides them from the active roster but keeps them.
    connect_bench_toggle($b2, $agencyId);
    t_eq(1, count(connect_bench_list($agencyId, true)), 'an off-bench person drops from the active roster');
    t_eq(2, count(connect_bench_list($agencyId, false)), 'but is retained in the full roster');

    // Bench is org-scoped: the other agency sees none of this agency's people.
    t_eq(0, count(connect_bench_list($other, false)), 'a different agency sees an empty bench (org-scoped, private)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
