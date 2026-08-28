<?php
// Connect A3 — talent search over the SHARED professional pool. Asserts the M4
// filters (discipline, work type, location, availability) select the right
// self-listed professionals, and that org staff are never returned.
t_section('connect talent search (A3)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // Three self-listed professionals with different profiles.
    connect_pro_register(['name' => 'Welder in Vadodara', 'email' => 'w.vad@example.com', 'password' => 'secret12']);
    $w = connect_pro_id();
    connect_pro_profile_save($w, ['name' => 'Welder in Vadodara', 'disciplines' => ['WELD'], 'work_types' => ['shutdown', 'manday'],
        'base_city' => 'Vadodara', 'availability' => 'AVAILABLE', 'skills' => 'Welding inspection, CSWIP']);

    connect_pro_register(['name' => 'NDT in Surat', 'email' => 'ndt.surat@example.com', 'password' => 'secret12']);
    $n = connect_pro_id();
    connect_pro_profile_save($n, ['name' => 'NDT in Surat', 'disciplines' => ['NDT'], 'work_types' => ['per_visit'],
        'base_city' => 'Surat', 'availability' => 'BUSY', 'skills' => 'UT, RT']);

    connect_pro_register(['name' => 'Painter panIndia', 'email' => 'coat@example.com', 'password' => 'secret12']);
    $c = connect_pro_id();
    connect_pro_profile_save($c, ['name' => 'Painter panIndia', 'disciplines' => ['COAT'], 'work_types' => ['day_rate'],
        'base_city' => 'Mumbai', 'pan_india' => '1', 'availability' => 'AVAILABLE', 'skills' => 'NACE coating']);

    $ids = fn($rows) => array_map(fn($r) => (int)$r['id'], $rows);

    // Discipline filter.
    $weld = $ids(connect_pro_search(['discipline' => 'WELD']));
    t_ok(in_array($w, $weld, true) && !in_array($n, $weld, true), 'discipline filter returns the welder, not the NDT tech');

    // Work-type filter.
    $shut = $ids(connect_pro_search(['work_type' => 'shutdown']));
    t_ok(in_array($w, $shut, true) && !in_array($c, $shut, true), 'work-type filter finds the shutdown-willing professional');

    // Location filter — Vadodara matches base city; pan-India matches any location too.
    $vad = $ids(connect_pro_search(['location' => 'Vadodara']));
    t_ok(in_array($w, $vad, true), 'location filter matches the base city');
    t_ok(in_array($c, $vad, true), 'a pan-India professional matches any location');
    t_ok(!in_array($n, $vad, true), 'a Surat-only professional does not match Vadodara');

    // Availability filter.
    $avail = $ids(connect_pro_search(['available_only' => 1]));
    t_ok(in_array($w, $avail, true) && !in_array($n, $avail, true), 'available-only excludes the busy professional');

    // Free-text.
    $nace = $ids(connect_pro_search(['q' => 'NACE']));
    t_ok(in_array($c, $nace, true) && !in_array($w, $nace, true), 'free-text matches on skills');

    // The pool count reflects registered professionals; org staff are a different table.
    t_ok(connect_pro_pool_count() >= 3, 'the pool count reflects self-listed professionals');
    db()->prepare("INSERT INTO inspectors (name,skills,status,created_at) VALUES ('Org Staff Welder','Welding',?,?)")->execute(['ACTIVE', date('c')]);
    $names = array_map(fn($r) => (string)$r['name'], connect_pro_search(['discipline' => 'WELD']));
    t_ok(!in_array('Org Staff Welder', $names, true), 'talent search never returns an org\'s private staff (it reads only the self-listed pool)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
