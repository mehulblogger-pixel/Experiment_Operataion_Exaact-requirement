<?php
// ============================================================================
//  CONNECT — Universal Location & Mobility Engine  (K-GEO, additive & reusable)
//
//  ONE geo + mobility engine for every profile type (freelancer, inspector,
//  engineer, PM, …) and every matching workflow. Not a free-text "preferred
//  location": structured Country → State → City with coordinates, a mobility
//  model (radius / selected places / Pan-India / international) with the strict
//  conditional rules, and a priority-tier matcher used by search + matching.
//
//  STRICTLY ADDITIVE: a new `cx_geo_places` master + a `cx_profile_places` link
//  table, and structured columns added to cx_professionals via ensure_column.
//  The existing free-text base_city / pan_india / overseas / travel_radius_km
//  keep working; the structured fields sit alongside them.
//
//  MOBILITY MODEL (per profile):
//    base (country/state/city + lat/lng)  — always required
//    pan_india        — available anywhere in India (disables radius/selected)
//    overseas + intl_regions[]            — international regions
//    travel_radius_km — RADIUS mode (base ± km)
//    cx_profile_places (SELECTED mode)    — explicit states/cities
//  These are DIFFERENT concepts, never merged into one field.
// ============================================================================

function connect_geo_migrate() {
    static $done = false; if ($done) return; $done = true;
    $pk = function_exists('pk_clause') ? pk_clause() : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    // Searchable place hierarchy with coordinates. kind: COUNTRY | REGION | STATE | CITY.
    db()->exec("CREATE TABLE IF NOT EXISTS cx_geo_places (
        id $pk, kind VARCHAR(10) DEFAULT 'CITY', name VARCHAR(120) DEFAULT '', slug VARCHAR(120) DEFAULT '',
        parent_id INT DEFAULT 0, country_code VARCHAR(4) DEFAULT '', region_code VARCHAR(16) DEFAULT '',
        state_name VARCHAR(80) DEFAULT '', lat REAL DEFAULT 0, lng REAL DEFAULT 0,
        status VARCHAR(10) DEFAULT 'ACTIVE', sort_order INT DEFAULT 0)");
    // A professional's SELECTED working places (states or cities).
    db()->exec("CREATE TABLE IF NOT EXISTS cx_profile_places (
        id $pk, pro_id INT DEFAULT 0, place_id INT DEFAULT 0, created_at VARCHAR(30) DEFAULT '')");
    foreach ([
        "CREATE INDEX ix_cx_geo_kind ON cx_geo_places (kind, status)",
        "CREATE INDEX ix_cx_geo_slug ON cx_geo_places (slug)",
        "CREATE INDEX ix_cx_pplaces ON cx_profile_places (pro_id)",
    ] as $ix) { try { db()->exec($ix); } catch (Throwable $e) {} }
}

/** Structured base + mobility columns on cx_professionals (alongside the legacy
 *  free-text fields). Kept separate from migrate() because cx_professionals is
 *  created later at boot; called once the table exists, and lazily before a save. */
function connect_geo_augment_professional() {
    static $done = false; if ($done) return; $done = true;
    if (!function_exists('ensure_column')) return;
    foreach ([
        ['base_place_id', 'INT DEFAULT 0'], ['base_state', "VARCHAR(80) DEFAULT ''"], ['base_country', "VARCHAR(4) DEFAULT 'IN'"],
        ['base_lat', 'REAL DEFAULT 0'], ['base_lng', 'REAL DEFAULT 0'], ['base_pincode', "VARCHAR(12) DEFAULT ''"],
        ['mobility_mode', "VARCHAR(16) DEFAULT ''"], ['intl_regions', "VARCHAR(240) DEFAULT ''"],
    ] as [$col, $type]) { try { ensure_column('cx_professionals', $col, $type); } catch (Throwable $e) {} }
}

/** International regions offered (multi-select). */
function connect_geo_regions() {
    return ['GCC' => 'GCC / Middle East', 'APAC' => 'Asia-Pacific', 'EUROPE' => 'Europe',
            'NAM' => 'North America', 'AFRICA' => 'Africa', 'WORLD' => 'Worldwide'];
}
/** Travel-radius presets (km) — Custom allowed via a free number. */
function connect_geo_radius_presets() { return [25, 50, 75, 100, 150, 200, 300, 500]; }

function geo_norm($s) { $s = strtolower(trim((string)$s)); return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $s))); }

/** Great-circle distance in km between two lat/lng points. */
function geo_haversine($lat1, $lng1, $lat2, $lng2) {
    $lat1 = (float)$lat1; $lng1 = (float)$lng1; $lat2 = (float)$lat2; $lng2 = (float)$lng2;
    if (($lat1 == 0.0 && $lng1 == 0.0) || ($lat2 == 0.0 && $lng2 == 0.0)) return INF;
    $R = 6371.0; $dLat = deg2rad($lat2 - $lat1); $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

// ---- places -----------------------------------------------------------------

function connect_geo_place_add($kind, $name, array $o = []) {
    connect_geo_migrate();
    $kind = strtoupper($kind); $name = trim((string)$name); if ($name === '') return 0;
    $slug = geo_norm($name) . '|' . $kind . '|' . strtoupper((string)($o['state_name'] ?? ''));
    $ex = (int)ops_val("SELECT id FROM cx_geo_places WHERE slug=?", [$slug]);
    if ($ex) return $ex;
    db()->prepare("INSERT INTO cx_geo_places (kind,name,slug,parent_id,country_code,region_code,state_name,lat,lng,status,sort_order)
                   VALUES (?,?,?,?,?,?,?,?,?, 'ACTIVE', ?)")
        ->execute([$kind, $name, $slug, (int)($o['parent_id'] ?? 0), strtoupper((string)($o['country_code'] ?? '')),
                   strtoupper((string)($o['region_code'] ?? '')), (string)($o['state_name'] ?? ''),
                   (float)($o['lat'] ?? 0), (float)($o['lng'] ?? 0), (int)($o['sort'] ?? 0)]);
    return (int)db()->lastInsertId();
}
function connect_geo_place_get($id) { connect_geo_migrate(); return ops_one("SELECT * FROM cx_geo_places WHERE id=?", [(int)$id]) ?: null; }
/** Autocomplete over places (city/state/country), name-prefix first. */
function connect_geo_search($q, $kinds = ['CITY','STATE'], $limit = 20) {
    connect_geo_migrate();
    $n = geo_norm($q); if ($n === '') return [];
    $kw = $kinds ? " AND kind IN (" . implode(',', array_fill(0, count($kinds), '?')) . ")" : '';
    $ka = array_map('strtoupper', $kinds);
    return ops_all("SELECT * FROM cx_geo_places WHERE status='ACTIVE' AND slug LIKE ?$kw
                    ORDER BY (slug LIKE ?) DESC, kind, name LIMIT " . max(1, (int)$limit),
                   array_merge(['%' . $n . '%'], $ka, [$n . '%'])) ?: [];
}
/** Resolve a free-text location to the best place (city preferred). */
function connect_geo_resolve($q) {
    $hits = connect_geo_search($q, ['CITY','STATE','COUNTRY'], 5);
    return $hits[0] ?? null;
}

// ---- the priority-tier matcher (used by search + matching) ------------------

/**
 * Match a professional's mobility against a job place. Returns
 *   ['tier'=>1..5|0, 'label'=>, 'km'=>|null]  (lower tier = stronger).
 *   1 Exact city · 2 Within radius · 3 Selected region/city · 4 Pan-India ·
 *   5 International · 0 No match.
 * $job is a cx_geo_places row (or ['lat','lng','state_name','country_code','id']).
 */
function connect_location_match($pro, $job) {
    connect_geo_migrate();
    if (!$job) return ['tier' => 0, 'label' => 'No job location', 'km' => null];
    $jCountry = strtoupper((string)($job['country_code'] ?? 'IN')) ?: 'IN';
    $jState   = (string)($job['state_name'] ?? '');
    $jId      = (int)($job['id'] ?? 0);
    $proId    = (int)($pro['id'] ?? 0);

    // 1 — exact city
    if ($jId > 0 && (int)($pro['base_place_id'] ?? 0) === $jId) return ['tier' => 1, 'label' => 'Based here', 'km' => 0.0];

    // 2 — within declared travel radius (RADIUS mode / any radius set)
    $radius = (float)($pro['travel_radius_km'] ?? 0);
    if ($radius > 0 && $jCountry === strtoupper((string)($pro['base_country'] ?? 'IN'))) {
        $km = geo_haversine($pro['base_lat'] ?? 0, $pro['base_lng'] ?? 0, $job['lat'] ?? 0, $job['lng'] ?? 0);
        if ($km !== INF && $km <= $radius) return ['tier' => 2, 'label' => 'Within ' . (int)$radius . ' km', 'km' => round($km)];
    }

    // 3 — explicitly selected working places (same city or same state)
    if ($proId > 0) {
        $sel = ops_all("SELECT p.id, p.kind, p.state_name FROM cx_profile_places pp JOIN cx_geo_places p ON p.id=pp.place_id WHERE pp.pro_id=?", [$proId]) ?: [];
        foreach ($sel as $s) {
            if ((int)$s['id'] === $jId && $jId > 0) return ['tier' => 3, 'label' => 'Works in this city', 'km' => null];
            if ($jState !== '' && strcasecmp((string)$s['state_name'], $jState) === 0) return ['tier' => 3, 'label' => 'Works in ' . $jState, 'km' => null];
            if (strtoupper((string)$s['kind']) === 'STATE' && $jState !== '' && strcasecmp((string)$s['name'] ?? '', $jState) === 0) return ['tier' => 3, 'label' => 'Works in ' . $jState, 'km' => null];
        }
    }

    // 4 — Pan-India
    if (!empty($pro['pan_india']) && $jCountry === 'IN') return ['tier' => 4, 'label' => 'Available Pan-India', 'km' => null];

    // 5 — international / travel-based
    if (!empty($pro['overseas'])) {
        $regions = array_filter(array_map('trim', explode(',', strtoupper((string)($pro['intl_regions'] ?? '')))));
        $jobRegion = strtoupper((string)($job['region_code'] ?? ''));
        if (in_array('WORLD', $regions, true) || ($jobRegion !== '' && in_array($jobRegion, $regions, true)) || ($jCountry !== 'IN' && $regions))
            return ['tier' => 5, 'label' => 'Open to international', 'km' => null];
    }
    return ['tier' => 0, 'label' => 'Outside declared area', 'km' => null];
}

// ---- mobility read/write on a professional ---------------------------------

/** Save a professional's structured base + mobility (conditional rules enforced
 *  server-side: Pan-India clears radius/selected; the UI mirrors this). */
function connect_geo_save_mobility($proId, array $in) {
    connect_geo_migrate(); connect_geo_augment_professional();
    $proId = (int)$proId; if ($proId <= 0) return false;
    $place = (int)($in['base_place_id'] ?? 0) > 0 ? connect_geo_place_get((int)$in['base_place_id']) : connect_geo_resolve((string)($in['base_city'] ?? ''));
    $panIndia = !empty($in['pan_india']) ? 1 : 0;
    $overseas = !empty($in['overseas']) ? 1 : 0;
    $mode = strtoupper((string)($in['mobility_mode'] ?? ''));
    // STRICT conditional logic: Pan-India disables radius + selected places.
    $radius = $panIndia ? 0 : max(0, (int)($in['travel_radius_km'] ?? 0));
    $regions = $overseas ? implode(',', array_values(array_intersect(array_keys(connect_geo_regions()), array_map('strtoupper', (array)($in['intl_regions'] ?? []))))) : '';
    db()->prepare("UPDATE cx_professionals SET base_place_id=?, base_city=?, base_state=?, base_country=?, base_lat=?, base_lng=?, base_pincode=?,
                     mobility_mode=?, travel_radius_km=?, pan_india=?, overseas=?, intl_regions=? WHERE id=?")
        ->execute([$place ? (int)$place['id'] : 0, $place ? $place['name'] : (string)($in['base_city'] ?? ''),
                   $place ? (string)$place['state_name'] : (string)($in['base_state'] ?? ''),
                   $place ? (string)$place['country_code'] : (string)($in['base_country'] ?? 'IN'),
                   $place ? (float)$place['lat'] : 0, $place ? (float)$place['lng'] : 0, (string)($in['base_pincode'] ?? ''),
                   $mode, $radius, $panIndia, $overseas, $regions, $proId]);
    // Selected places (only meaningful when not Pan-India).
    db()->prepare("DELETE FROM cx_profile_places WHERE pro_id=?")->execute([$proId]);
    if (!$panIndia) foreach ((array)($in['selected_places'] ?? []) as $pidRaw) {
        $pid = (int)$pidRaw; if ($pid > 0 && connect_geo_place_get($pid))
            db()->prepare("INSERT INTO cx_profile_places (pro_id,place_id,created_at) VALUES (?,?,?)")->execute([$proId, $pid, date('c')]);
    }
    return true;
}

/** Seed a pragmatic starter geo set — India + states + major industrial cities
 *  (with coordinates) + international regions/countries. Idempotent (insert-if-
 *  empty); admin-extensible afterwards. */
function connect_geo_seed() {
    connect_geo_migrate();
    if ((int)ops_val("SELECT COUNT(*) FROM cx_geo_places") > 0) return; // already seeded

    $india = connect_geo_place_add('COUNTRY', 'India', ['country_code' => 'IN', 'region_code' => 'APAC']);
    // States (name only; cities carry the coords).
    $states = ['Gujarat','Maharashtra','Tamil Nadu','Karnataka','Andhra Pradesh','Telangana','Odisha','West Bengal',
               'Delhi','Haryana','Uttar Pradesh','Rajasthan','Madhya Pradesh','Kerala','Punjab','Jharkhand','Chhattisgarh','Assam'];
    $stId = [];
    foreach ($states as $s) $stId[$s] = connect_geo_place_add('STATE', $s, ['country_code' => 'IN', 'parent_id' => $india, 'state_name' => $s]);

    // City => [state, lat, lng] — industrial/inspection hubs.
    $cities = [
        'Jamnagar'=>['Gujarat',22.4707,70.0577], 'Surat'=>['Gujarat',21.1702,72.8311], 'Ahmedabad'=>['Gujarat',23.0225,72.5714],
        'Vadodara'=>['Gujarat',22.3072,73.1812], 'Dahej'=>['Gujarat',21.7051,72.5959], 'Hazira'=>['Gujarat',21.1000,72.6300],
        'Bharuch'=>['Gujarat',21.7051,72.9959], 'Ankleshwar'=>['Gujarat',21.6266,73.0020], 'Mundra'=>['Gujarat',22.8394,69.7218],
        'Mumbai'=>['Maharashtra',19.0760,72.8777], 'Pune'=>['Maharashtra',18.5204,73.8567], 'Nagpur'=>['Maharashtra',21.1458,79.0882],
        'Nashik'=>['Maharashtra',19.9975,73.7898], 'Chennai'=>['Tamil Nadu',13.0827,80.2707], 'Coimbatore'=>['Tamil Nadu',11.0168,76.9558],
        'Trichy'=>['Tamil Nadu',10.7905,78.7047], 'Bengaluru'=>['Karnataka',12.9716,77.5946], 'Mangalore'=>['Karnataka',12.9141,74.8560],
        'Visakhapatnam'=>['Andhra Pradesh',17.6868,83.2185], 'Vijayawada'=>['Andhra Pradesh',16.5062,80.6480],
        'Hyderabad'=>['Telangana',17.3850,78.4867], 'Paradip'=>['Odisha',20.3167,86.6100], 'Bhubaneswar'=>['Odisha',20.2961,85.8245],
        'Rourkela'=>['Odisha',22.2604,84.8536], 'Kolkata'=>['West Bengal',22.5726,88.3639], 'Haldia'=>['West Bengal',22.0667,88.0698],
        'New Delhi'=>['Delhi',28.6139,77.2090], 'Gurgaon'=>['Haryana',28.4595,77.0266], 'Faridabad'=>['Haryana',28.4089,77.3178],
        'Panipat'=>['Haryana',29.3909,76.9635], 'Noida'=>['Uttar Pradesh',28.5355,77.3910], 'Kanpur'=>['Uttar Pradesh',26.4499,80.3319],
        'Lucknow'=>['Uttar Pradesh',26.8467,80.9462], 'Jaipur'=>['Rajasthan',26.9124,75.7873], 'Kota'=>['Rajasthan',25.2138,75.8648],
        'Barmer'=>['Rajasthan',25.7521,71.3967], 'Bhopal'=>['Madhya Pradesh',23.2599,77.4126], 'Indore'=>['Madhya Pradesh',22.7196,75.8577],
        'Kochi'=>['Kerala',9.9312,76.2673], 'Ludhiana'=>['Punjab',30.9010,75.8573], 'Bathinda'=>['Punjab',30.2110,74.9455],
        'Jamshedpur'=>['Jharkhand',22.8046,86.2029], 'Ranchi'=>['Jharkhand',23.3441,85.3096], 'Bhilai'=>['Chhattisgarh',21.1938,81.3509],
    ];
    foreach ($cities as $name => $c) {
        [$st, $lat, $lng] = $c;
        connect_geo_place_add('CITY', $name, ['country_code' => 'IN', 'region_code' => 'APAC', 'parent_id' => $stId[$st] ?? $india, 'state_name' => $st, 'lat' => $lat, 'lng' => $lng]);
    }

    // International regions + a few GCC countries (region-based matching).
    foreach (connect_geo_regions() as $code => $name) connect_geo_place_add('REGION', $name, ['region_code' => $code]);
    $gcc = ['Saudi Arabia'=>'SA','United Arab Emirates'=>'AE','Qatar'=>'QA','Oman'=>'OM','Kuwait'=>'KW','Bahrain'=>'BH'];
    foreach ($gcc as $cn => $cc) connect_geo_place_add('COUNTRY', $cn, ['country_code' => $cc, 'region_code' => 'GCC']);
}
