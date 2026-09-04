<?php
// Connect K19 / backlog #8 — labour-market analytics. Asserts the read-only
// aggregations over the cx_* tables: headline KPIs (incl. fill rate & pool),
// supply-vs-demand by discipline with an honest gap, the hiring funnel, rate
// benchmarks, pool growth and the verification mix. Pure reads — no new schema.
t_section('connect labour-market analytics (#8)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // --- supply: two welding professionals, one available + verified ---------
    connect_pro_register(['name' => 'Ana Weld', 'email' => 'ana@example.com', 'password' => 'secret12']);
    $p1 = connect_pro_id();
    connect_pro_profile_save($p1, ['name' => 'Ana Weld', 'skills' => 'Welding', 'disciplines' => ['WELD'], 'availability' => 'AVAILABLE', 'day_rate_min' => 2400]);
    [$o, , $vid] = connect_verify_submit('professional', $p1, 'PAN', 'AAPFU0939F');
    connect_verify_review($vid, 'VERIFIED', '', 'Coord');
    connect_pro_register(['name' => 'Ben Weld', 'email' => 'ben@example.com', 'password' => 'secret12']);
    $p2 = connect_pro_id();
    connect_pro_profile_save($p2, ['name' => 'Ben Weld', 'skills' => 'Welding', 'disciplines' => ['WELD'], 'availability' => 'BUSY']);

    // --- demand: three welding requirements, one awarded --------------------
    $r1 = cx_requirement_create(['title' => 'Welder 1', 'discipline_code' => 'WELD', 'location' => 'Jamnagar', 'rate_min' => 2000, 'rate_max' => 3000], true);
    $r2 = cx_requirement_create(['title' => 'Welder 2', 'discipline_code' => 'WELD', 'location' => 'Jamnagar', 'rate_min' => 2500, 'rate_max' => 3500], true);
    $r3 = cx_requirement_create(['title' => 'Welder 3', 'discipline_code' => 'WELD', 'location' => 'Surat'], true);
    // r3 receives an application, is shortlisted, then awarded.
    $aid = cx_application_add($r3, ['applicant_professional_id' => $p1, 'applicant_name' => 'Ana Weld']);
    db()->prepare("UPDATE cx_applications SET status='SHORTLISTED' WHERE id=?")->execute([$aid]);
    db()->prepare("UPDATE cx_requirements SET status='AWARDED', awarded_application_id=?, posted_at=?, closed_at=? WHERE id=?")
        ->execute([$aid, date('c', strtotime('-4 days')), date('c'), $r3]);

    // --- headline -----------------------------------------------------------
    $h = connect_analytics_headline();
    t_ok($h['posted'] >= 3, 'posted requirements are counted');
    t_ok($h['awarded'] >= 1, 'an awarded requirement is counted');
    t_ok($h['fill_rate'] > 0 && $h['fill_rate'] <= 100, 'fill rate is a sane percentage');
    t_ok($h['pool'] >= 2, 'the active professional pool is counted');
    t_ok($h['supply'] >= $h['pool'], 'supply includes pool (+ any bench)');
    t_ok($h['avg_days_to_award'] >= 3 && $h['avg_days_to_award'] <= 6, 'avg days-to-award reflects the ~4-day fill');

    // --- supply vs demand ---------------------------------------------------
    $ds = connect_analytics_demand_supply();
    $weld = null; foreach ($ds as $row) if ($row['code'] === 'WELD') $weld = $row;
    t_ok($weld !== null, 'welding appears in the supply/demand table');
    t_eq(2, $weld['demand'], 'two OPEN welding requirements are demand (the awarded one is not)');
    t_eq(1, $weld['supply'], 'only the one AVAILABLE welder counts as supply (BUSY excluded)');
    t_eq(-1, $weld['gap'], 'the gap is -1 (demand outstrips supply)');

    // --- funnel -------------------------------------------------------------
    $funnel = [];
    foreach (connect_analytics_funnel() as $f) $funnel[$f['key']] = (int)$f['value'];
    t_ok($funnel['posted'] >= 3, 'the funnel starts with posted');
    t_ok($funnel['applied'] >= 1, 'the funnel counts requirements that received applicants');
    t_ok($funnel['shortlisted'] >= 1, 'the funnel counts shortlisted requirements');
    t_ok($funnel['awarded'] >= 1, 'the funnel counts awarded requirements');
    t_ok($funnel['posted'] >= $funnel['awarded'], 'the funnel narrows (posted >= awarded)');

    // --- rates --------------------------------------------------------------
    $rates = connect_analytics_rates();
    $rw = null; foreach ($rates as $r) if ($r['code'] === 'WELD') $rw = $r;
    t_ok($rw !== null, 'welding has a rate benchmark');
    t_ok($rw['ask_min'] > 0 && $rw['ask_max'] >= $rw['ask_min'], 'the requirement ask band is populated');
    t_eq(2400, $rw['pool_avg'], 'the pool day-rate average reflects the one rated professional');

    // --- growth + verification mix ------------------------------------------
    $growth = connect_analytics_growth();
    t_eq(6, count($growth), 'growth returns six months');
    $thisMonth = end($growth);
    t_ok($thisMonth['value'] >= 2, 'this month counts the newly registered professionals');

    $mix = [];
    foreach (connect_analytics_verif_mix() as $v) $mix[$v['key']] = (int)$v['value'];
    t_ok(($mix['id_verified'] ?? 0) >= 1, 'the verification mix counts the ID-verified professional');
    t_ok(($mix['registered'] ?? 0) >= 1, 'and the still-registered one');

    // --- locations + insight ------------------------------------------------
    $locs = connect_analytics_locations();
    $jam = null; foreach ($locs as $l) if ($l['location'] === 'Jamnagar') $jam = $l;
    t_ok($jam && $jam['value'] >= 2, 'demand-by-location surfaces Jamnagar');
    t_ok(stripos(connect_analytics_insight(), 'Welding') !== false, 'the plain-language insight names the tight welding market');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
