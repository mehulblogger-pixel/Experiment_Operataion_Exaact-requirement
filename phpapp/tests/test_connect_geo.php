<?php
// ============================================================================
//  Connect K-GEO — universal Location & Mobility engine.
//
//  Structured geo is seeded at boot; this proves the distance engine and the
//  PRIORITY-TIER matcher the whole marketplace relies on:
//    1 exact city · 2 within radius · 3 selected region · 4 Pan-India ·
//    5 international · 0 outside. Plus the strict conditional rule (Pan-India
//  disables the travel radius). (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('connect location & mobility engine (K-GEO)');

connect_geo_migrate();
t_ok((int)ops_val("SELECT COUNT(*) FROM cx_geo_places WHERE kind='CITY'") > 20, 'the geo master is seeded with cities (coordinates)');

// --- distance engine --------------------------------------------------------
$ahd = connect_geo_resolve('Ahmedabad'); $surat = connect_geo_resolve('Surat'); $mumbai = connect_geo_resolve('Mumbai');
t_ok($ahd && $surat && $mumbai, 'places resolve from free text');
$dAS = geo_haversine($ahd['lat'], $ahd['lng'], $surat['lat'], $surat['lng']);
t_ok($dAS > 180 && $dAS < 260, 'Ahmedabad→Surat is ~200 km (' . round($dAS) . ')');
$dMS = geo_haversine($mumbai['lat'], $mumbai['lng'], $surat['lat'], $surat['lng']);
t_ok($dMS > 200 && $dMS < 300, 'Mumbai→Surat is ~230 km (' . round($dMS) . ')');
t_ok(geo_haversine(0, 0, 21, 72) === INF, 'a missing coordinate yields INF (never a false "0 km")');

// --- the five priority tiers, against a Surat job ---------------------------
$job = $surat;
// 1 exact city
t_eq(connect_location_match(['id' => 1, 'base_place_id' => (int)$surat['id']], $job)['tier'], 1, 'based in the job city → tier 1');
// 2 within radius
t_eq(connect_location_match(['id' => 2, 'base_country' => 'IN', 'base_lat' => $ahd['lat'], 'base_lng' => $ahd['lng'], 'travel_radius_km' => 300], $job)['tier'], 2, 'Ahmedabad + 300 km radius covers Surat → tier 2');
// outside radius
t_eq(connect_location_match(['id' => 3, 'base_country' => 'IN', 'base_lat' => $mumbai['lat'], 'base_lng' => $mumbai['lng'], 'travel_radius_km' => 50], $job)['tier'], 0, 'Mumbai + 50 km does NOT cover Surat → tier 0');
// 4 Pan-India
t_eq(connect_location_match(['id' => 4, 'pan_india' => 1], $job)['tier'], 4, 'Pan-India covers an Indian job → tier 4');
// 5 international
$sa = connect_geo_resolve('Saudi Arabia');
t_ok($sa !== null, 'a GCC country resolves');
t_eq(connect_location_match(['id' => 5, 'overseas' => 1, 'intl_regions' => 'GCC'], $sa)['tier'], 5, 'overseas + GCC covers a Saudi job → tier 5');
t_eq(connect_location_match(['id' => 6, 'pan_india' => 1], $sa)['tier'], 0, 'Pan-India does NOT cover an overseas job → tier 0');

// --- 3 selected places (needs a real pro row) -------------------------------
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,created_at) VALUES ('geo@pro.test','Geo Pro',1,?)")->execute([date('c')]);
    $pid = (int)db()->lastInsertId();
    $guj = connect_geo_resolve('Gujarat');
    db()->prepare("INSERT INTO cx_profile_places (pro_id,place_id,created_at) VALUES (?,?,?)")->execute([$pid, (int)$guj['id'], date('c')]);
    t_eq(connect_location_match(['id' => $pid], $job)['tier'], 3, 'a professional who selected Gujarat matches a Surat job → tier 3');

    // --- strict conditional rule: Pan-India disables the travel radius -------
    connect_geo_save_mobility($pid, ['base_city' => 'Jamnagar', 'pan_india' => 1, 'travel_radius_km' => 200, 'selected_places' => [(int)$guj['id']]]);
    $saved = ops_one("SELECT pan_india, travel_radius_km, base_lat FROM cx_professionals WHERE id=?", [$pid]);
    t_eq((int)$saved['pan_india'], 1, 'Pan-India saved');
    t_eq((float)$saved['travel_radius_km'], 0.0, 'Pan-India CLEARS the travel radius (strict conditional rule)');
    t_ok((float)$saved['base_lat'] != 0.0, 'the base city resolved to real coordinates');
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_profile_places WHERE pro_id=?", [$pid]), 0, 'Pan-India clears selected places too');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
