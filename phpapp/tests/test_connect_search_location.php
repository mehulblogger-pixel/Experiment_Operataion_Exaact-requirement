<?php
// One-keyword + LOCATION search: taxonomy discovery ranked by the priority tiers.
t_section('connect location-aware talent search (K0+/K-GEO)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_migrate(); connect_geo_augment_professional();
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,disciplines,skills,created_at) VALUES ('loc@pro.test','Loc Test',1,'NDT','Ultrasonic Testing',?)")->execute([date('c')]);
    $pid=(int)db()->lastInsertId();
    connect_profile_tax_backfill($pid);
    connect_geo_save_mobility($pid, ['base_city'=>'Ahmedabad','mobility_mode'=>'RADIUS','travel_radius_km'=>300]);

    $inSurat = connect_pro_search_smart(['q'=>'NDT','location'=>'Surat']);
    $names = array_map(fn($r)=>$r['name'], $inSurat);
    t_ok(in_array('Loc Test',$names,true),'NDT + Surat finds the Ahmedabad pro (within 300 km)');
    $row = null; foreach($inSurat as $r) if($r['name']==='Loc Test') $row=$r;
    t_eq((int)$row['_loc']['tier'],2,'the match is tier 2 (within radius)');
    t_ok((int)$row['_loc']['km'] > 180 && (int)$row['_loc']['km'] < 260,'distance ~200 km is reported');

    $inChennai = connect_pro_search_smart(['q'=>'NDT','location'=>'Chennai']);
    t_ok(!in_array('Loc Test', array_map(fn($r)=>$r['name'],$inChennai), true),'NDT + Chennai excludes them (outside 300 km)');

    // Pan-India professional matches any Indian location
    connect_geo_save_mobility($pid, ['base_city'=>'Ahmedabad','mobility_mode'=>'PAN_INDIA','travel_radius_km'=>300]);
    $pan = connect_pro_search_smart(['q'=>'NDT','location'=>'Chennai']);
    $prow=null; foreach($pan as $r) if($r['name']==='Loc Test') $prow=$r;
    t_ok($prow !== null,'once Pan-India, the same pro matches Chennai');
    t_eq((int)$prow['_loc']['tier'],4,'Pan-India is tier 4');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
