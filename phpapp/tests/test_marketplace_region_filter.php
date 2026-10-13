<?php
// ---------------------------------------------------------------------------
//  OWNER DECISION (2026-10-13), ratified: a COORDINATOR may use the
//  Marketplace to find a resource in ANY region the resource itself is willing
//  to work in — not only the coordinator's own branch.
//
//  The owner's words: "Coordinator can see market place to find any resource if
//  available in applicable region. Suppose Ahmedabad coordinator is looking for
//  freelancer in Chennai he should be able to filter it accordingly."
//
//  That makes the marketplace DELIBERATELY EXEMPT from the office-scope rule
//  that R-30 tightened everywhere else. The distinction, stated so it cannot
//  drift:
//
//    DISCOVERY  (searching the shared pool)  — NOT office-scoped. A marketplace
//               that only shows you your own branch is not a marketplace.
//    ACTION     (inviting / sourcing / awarding / assigning) — still gated by
//               the module right and, where a job or requirement is involved,
//               by that record's own scope. Finding somebody is not hiring them.
//
//  The region question is answered by the professional's OWN declared mobility,
//  not by the searcher's branch: a Chennai search returns the people who have
//  said they will work in Chennai, and excludes the people who have not.
//
//  Guards the behaviour behind that decision so it cannot silently regress into
//  a branch-clamped search (which would make the module useless) or into an
//  unfiltered dump (which would show a coordinator people who cannot travel).
// ---------------------------------------------------------------------------

t_ok(function_exists('connect_market_can'), 'the marketplace gate exists');
t_ok(function_exists('connect_pro_search_smart'), 'the pool search exists');
t_ok(function_exists('connect_location_match'), 'the location engine exists');
t_ok(function_exists('connect_geo_resolve'), 'a place name can be resolved');

// ---- the owner's decision: a coordinator holds this desk ------------------
//  Asserted against the ROLE BAND the gate actually asks for, so a future
//  narrowing of that band fails here rather than in front of a customer.
$src = file_get_contents(__DIR__ . '/../lib/connect_market.php');
t_ok(strpos($src, 'is_coordinator_level()') !== false,
    'the marketplace gate admits the coordinator band — the owner ratified this on 2026-10-13');

// ---- and it is NOT clamped to the searcher's own office -------------------
//  The whole point of the owner's Ahmedabad -> Chennai example. If somebody
//  later wraps the pool search in scope_clause(), this fails.
foreach (['connect_pro.php', 'connect_market.php'] as $f) {
    $s = file_get_contents(__DIR__ . '/../lib/' . $f);
    t_ok(strpos($s, 'scope_clause(') === false && strpos($s, 'scope_office_clause(') === false,
        "$f does not office-scope the shared pool — discovery is cross-branch by design");
}

connect_geo_seed();

$chennai   = connect_geo_resolve('Chennai');
$ahmedabad = connect_geo_resolve('Ahmedabad');
$coimb     = connect_geo_resolve('Coimbatore');
t_ok($chennai && (int)$chennai['id'] > 0,   'Chennai resolves to a real place');
t_ok($ahmedabad && (int)$ahmedabad['id'] > 0, 'Ahmedabad resolves to a real place');
t_ok($coimb && (int)$coimb['id'] > 0,       'Coimbatore resolves to a real place');
t_eq((string)$chennai['state_name'], 'Tamil Nadu', 'Chennai carries its state');

// ---- a cast of freelancers with different declared mobility ---------------
function rgf_pro($name, array $mob) {
    db()->prepare("INSERT INTO cx_professionals (email,name,mobile,headline,skills,work_types,availability,is_active,verification_tier,passport_token,password_hash,created_at)
                   VALUES (?,?,?,?,?,?,'AVAILABLE',1,'registered',?,?,?)")
        ->execute([strtolower(str_replace(' ', '.', $name)) . '@zz.test', $name, '9000000000',
                   'Welding inspector', 'welding,NDT', 'per_visit', bin2hex(random_bytes(8)), '', date('c')]);
    $id = (int)db()->lastInsertId();
    connect_geo_save_mobility($id, $mob);
    return $id;
}

$pChennai = rgf_pro('ZZ Chennai Based',  ['base_place_id' => (int)$chennai['id']]);
$pAhmOnly = rgf_pro('ZZ Ahmedabad Only', ['base_place_id' => (int)$ahmedabad['id']]);
$pPanIn   = rgf_pro('ZZ Pan India',      ['base_place_id' => (int)$ahmedabad['id'], 'pan_india' => 1]);
$pTnState = rgf_pro('ZZ Works In TN',    ['base_place_id' => (int)$coimb['id'],
                                          'selected_places' => [(int)$chennai['id']]]);
$pRadius  = rgf_pro('ZZ Travels 500km',  ['base_place_id' => (int)$coimb['id'], 'travel_radius_km' => 500]);

// ---- the owner's exact scenario: searching Chennai from Ahmedabad ---------
$rows = connect_pro_search_smart(['location' => 'Chennai'], 60);
$byId = [];
foreach ($rows as $r) $byId[(int)$r['id']] = $r;

t_ok(isset($byId[$pChennai]), 'a Chennai-based freelancer is found by a Chennai search');
t_eq((int)$byId[$pChennai]['_loc']['tier'], 1, 'the Chennai-based one ranks tier 1 (based here)');

t_ok(isset($byId[$pPanIn]),  'a Pan-India freelancer is offered for Chennai');
t_eq((int)$byId[$pPanIn]['_loc']['tier'], 4, 'Pan-India ranks tier 4');

t_ok(isset($byId[$pTnState]), 'somebody who selected Chennai is offered');
t_eq((int)$byId[$pTnState]['_loc']['tier'], 3, 'a selected city/state ranks tier 3');

t_ok(isset($byId[$pRadius]), 'somebody whose travel radius reaches Chennai is offered');
t_eq((int)$byId[$pRadius]['_loc']['tier'], 2, 'within-radius ranks tier 2');

// THE IMPORTANT NEGATIVE. An Ahmedabad-only freelancer has declared no
// willingness to work in Chennai, so a Chennai search must NOT offer them.
// Without this the coordinator is handed people who cannot take the job.
t_ok(!isset($byId[$pAhmOnly]),
    'an Ahmedabad-only freelancer is NOT offered for Chennai — "applicable region" is the resource\'s own declaration');

// ---- the ordering is useful, not arbitrary -------------------------------
$tiers = [];
foreach ($rows as $r) if (($r['_loc']['tier'] ?? 0) > 0) $tiers[] = (int)$r['_loc']['tier'];
$sorted = $tiers; sort($sorted);
t_eq($tiers, $sorted, 'results come back nearest-first, so the best-placed person is at the top');

// ---- and the reverse direction works too, so this is not Chennai-specific -
$rowsAhm = connect_pro_search_smart(['location' => 'Ahmedabad'], 60);
$ahmIds = array_map(fn($r) => (int)$r['id'], $rowsAhm);
t_ok(in_array($pAhmOnly, $ahmIds, true), 'the Ahmedabad-only freelancer IS offered for Ahmedabad');
t_ok(!in_array($pChennai, $ahmIds, true), 'the Chennai-only freelancer is not offered for Ahmedabad');

// ---- every tier the engine can award is reachable and labelled -----------
foreach ($rows as $r) {
    if (($r['_loc']['tier'] ?? 0) > 0)
        t_ok(trim((string)($r['_loc']['label'] ?? '')) !== '',
            'each result says WHY it matched the region (' . $r['name'] . ')');
}

// ---- a location that is not a known place must not silently return the pool
$rowsJunk = connect_pro_search_smart(['location' => 'Zzqqxx Nowhere'], 60);
t_ok(!in_array($pChennai, array_map(fn($r) => (int)$r['id'], $rowsJunk), true),
    'an unrecognised place does not fall back to showing everybody');

// ---------------------------------------------------------------------------
//  FINDABILITY. The owner's requirement is "he should be able to filter it
//  accordingly" — which is not met if the filter only works for people who
//  type the exact seeded spelling. Measured before this was added: "Madras"
//  and "Chenai" both resolved to nothing, so an honest search for a city with
//  professionals in it returned an empty list and read as "nobody there".
// ---------------------------------------------------------------------------

t_ok(function_exists('connect_geo_alias_map'), 'former place names are mapped');
t_ok(function_exists('connect_geo_suggest'),   'an unrecognised place can be corrected');
t_ok(function_exists('connect_geo_pick_list'), 'the screen can offer a place picker');

// A twenty-year veteran types the name they have always used.
foreach ([['Madras', 'Chennai'], ['Bombay', 'Mumbai'], ['Calcutta', 'Kolkata'],
          ['Bangalore', 'Bengaluru'], ['Baroda', 'Vadodara'], ['Vizag', 'Visakhapatnam']] as [$old, $now]) {
    $p = connect_geo_resolve($old);
    t_ok($p !== null, "\"$old\" resolves rather than failing");
    if ($p) t_eq((string)$p['name'], $now, "\"$old\" resolves to $now");
}

// And the whole search works through the old name, end to end.
$rowsMadras = connect_pro_search_smart(['location' => 'Madras'], 60);
$madrasIds  = array_map(fn($r) => (int)$r['id'], $rowsMadras);
t_ok(in_array($pChennai, $madrasIds, true),
    'searching "Madras" finds the Chennai-based freelancer');
t_ok(!in_array($pAhmOnly, $madrasIds, true),
    'searching "Madras" still excludes the Ahmedabad-only freelancer');

// A typo is corrected rather than silently returning nothing.
$sug = connect_geo_suggest('Chenai', 3);
t_ok($sug !== [], 'a misspelling produces at least one suggestion');
t_ok(in_array('Chennai', array_map(fn($r) => (string)$r['name'], $sug), true),
    '"Chenai" suggests Chennai');

// A genuinely unrelated word must NOT be "corrected" to something arbitrary —
// a wrong confident suggestion is worse than none.
t_eq(connect_geo_suggest('Zzqqxxwv', 3), [], 'nonsense produces no false suggestion');

// The picker is populated and carries the state, so two same-named towns can be told apart.
$pick = connect_geo_pick_list();
t_ok(count($pick) > 20, 'the place picker offers a usable list');
$names = array_map(fn($r) => (string)$r['name'], $pick);
t_ok(in_array('Chennai', $names, true) && in_array('Ahmedabad', $names, true),
    'the picker contains the cities in the owner\'s example');
foreach ($pick as $r) { t_ok(array_key_exists('state_name', $r), 'each pickable place carries its state'); break; }

// Cities come before states in the picker, because a city is the common case.
$firstState = null; $lastCity = null;
foreach ($pick as $i => $r) {
    if ((string)$r['kind'] === 'CITY') $lastCity = $i;
    if ((string)$r['kind'] === 'STATE' && $firstState === null) $firstState = $i;
}
if ($firstState !== null && $lastCity !== null)
    t_ok($lastCity < $firstState, 'cities are offered before states');
