<?php
// Passport editor — scalar save must not regress location; expertise attaches.
t_section('connect passport profile (K0+/K-GEO wiring)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_migrate(); connect_geo_augment_professional();
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,created_at) VALUES ('pp@pro.test','PP',1,?)")->execute([date('c')]);
    $pid=(int)db()->lastInsertId();
    // scalar save carries location (hidden fields) — must persist, not blank
    connect_pro_profile_save($pid, ['name'=>'PP','base_city'=>'Surat','pan_india'=>'1','overseas'=>'0','travel_radius_km'=>'100','disciplines'=>['NDT'],'skills'=>'Ultrasonic Testing']);
    $r=ops_one("SELECT base_city,pan_india,travel_radius_km FROM cx_professionals WHERE id=?", [$pid]);
    t_eq((string)$r['base_city'],'Surat','scalar save preserves base_city');
    t_eq((int)$r['pan_india'],1,'scalar save preserves pan_india');
    t_eq((int)$r['travel_radius_km'],100,'scalar save preserves travel radius');
    // profile save also backfills the taxonomy graph (so search finds them)
    t_ok((int)ops_val("SELECT COUNT(*) FROM cx_profile_tax WHERE pro_id=?", [$pid]) >= 1, 'profile save backfills the taxonomy graph');
    // node_lite shape
    $lite=connect_pro_node_lite(['id'=>5,'name'=>'X','kind'=>'ROLE','code'=>'R']);
    t_eq($lite['kind'],'ROLE','node_lite carries kind');
    // mobility save via the geo engine (structured)
    connect_geo_save_mobility($pid, ['base_city'=>'Jamnagar','mobility_mode'=>'RADIUS','travel_radius_km'=>200,'overseas'=>1,'intl_regions'=>['GCC']]);
    $m=ops_one("SELECT base_state,base_lat,travel_radius_km,intl_regions FROM cx_professionals WHERE id=?", [$pid]);
    t_eq((string)$m['base_state'],'Gujarat','mobility save geocodes the base city');
    t_ok((float)$m['base_lat'] != 0.0,'base has coordinates');
    t_eq((string)$m['intl_regions'],'GCC','international region saved');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
