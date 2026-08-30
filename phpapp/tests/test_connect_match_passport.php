<?php
// Matching upgrade — score professionals against the taxonomy graph + location,
// with plain-language reasons ("why 87%"). Additive to the token scorer.
t_section('connect matching: graph + location enrichment');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_migrate(); connect_geo_augment_professional();
    $node = fn($t,$k)=>((connect_tax_resolve($t,[$k])[0]['id'] ?? 0));
    $pvi = $node('pressure vessel inspector','ROLE'); $ut = $node('ultrasonic testing','METHOD');
    t_ok($pvi && $ut, 'the role/method nodes exist');

    // A well-matched professional (role + skill + within radius of Surat).
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,skills,created_at) VALUES ('m1@pro.test','Strong Match',1,'',?)")->execute([date('c')]);
    $p1=(int)db()->lastInsertId();
    connect_profile_tax_attach($p1,$pvi,'PRIMARY_ROLE'); connect_profile_tax_attach($p1,$ut,'SKILL');
    connect_geo_save_mobility($p1, ['base_city'=>'Ahmedabad','mobility_mode'=>'RADIUS','travel_radius_km'=>300]);
    // A weak professional (unrelated, no location).
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,skills,created_at) VALUES ('m2@pro.test','Weak Match',1,'gardening',?)")->execute([date('c')]);
    $p2=(int)db()->lastInsertId();

    $reqId = cx_requirement_create(['title'=>'Pressure vessel inspector for FAT','discipline_code'=>'NDT','location'=>'Surat','poster_party_id'=>0], true);
    $req = cx_requirement_get($reqId);
    $rows = connect_match_for_requirement($req, 20);
    $find = function($name) use ($rows){ foreach($rows as $r) if($r['name']===$name) return $r; return null; };
    $s = $find('Strong Match'); $w = $find('Weak Match');
    t_ok($s !== null, 'the strong professional is returned');
    t_ok($s['score'] > ($w['score'] ?? 0), 'graph+location lifts the strong match above the weak one ('.($s['score']??0).' vs '.($w['score']??0).')');
    $reasons = implode(' | ', $s['reasons'] ?? []);
    t_ok(strpos($reasons,'Pressure Vessel Inspector') !== false, 'reasons name the matched role');
    t_ok((int)($s['loc']['tier'] ?? 0) === 2, 'location tier 2 (within radius) recorded');
    t_ok(strpos($reasons,'Within 300 km') !== false || strpos($reasons,'km') !== false, 'reasons include the location match');
    // the weak one, if returned with a location, is flagged outside area
    if ($w) t_ok(in_array('⚠ Outside declared area', $w['reasons'] ?? [], true), 'the weak (no-location) professional is flagged outside area');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
