<?php
// Gap-6 (EXTEND) — near-duplicate requirement detection. The framework lists "duplicate
// requirement" as an edge case to handle; only a deliberate clone existed. connect_requirement_duplicates()
// warns when a NEW requirement looks like one the same client already has open — same discipline,
// location, similar title, overlapping dates. Read-only, advisory (never blocks the post).
t_section('near-duplicate requirement detection (Gap 6)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_market_migrate();
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('Dup Co',1,'ACTIVE')")->execute();
    $client = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('Other Co',1,'ACTIVE')")->execute();
    $other = (int)db()->lastInsertId();

    // An OPEN requirement the client already has.
    $existing = [
        'title' => 'Welding Inspector for FAT at Dahej', 'poster_party_id' => $client, 'poster_name' => 'Dup Co',
        'discipline_code' => 'WELD', 'location' => 'Dahej', 'work_type' => 'FREELANCE', 'positions' => 1,
        'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+20 days')), 'rate_min' => 8000, 'rate_max' => 12000, 'rate_unit' => 'day',
    ];
    $eid = (int)cx_requirement_create($existing, true);
    t_ok($eid > 0, 'the existing requirement is posted');

    // A near-duplicate: same client, same discipline + location + very similar title + overlapping dates.
    $near = ['title' => 'Welding Inspector for FAT at Dahej site', 'poster_party_id' => $client,
             'discipline_code' => 'WELD', 'location' => 'Dahej', 'start_date' => date('Y-m-d', strtotime('+2 days')), 'end_date' => date('Y-m-d', strtotime('+18 days'))];
    $dupes = connect_requirement_duplicates($near);
    t_ok((bool)array_filter($dupes, fn($d) => (int)$d['id'] === $eid), 'a near-duplicate from the same client is detected');
    $hit = null; foreach ($dupes as $d) if ((int)$d['id'] === $eid) $hit = $d;
    t_ok($hit && in_array('same discipline', $hit['reasons'], true) && in_array('same location', $hit['reasons'], true), 'the reasons name the matching signals');

    // exceptId excludes the requirement itself (so re-saving does not warn about itself).
    t_ok(!array_filter(connect_requirement_duplicates(array_merge($near, ['poster_party_id' => $client]), $eid), fn($d) => (int)$d['id'] === $eid),
        'exceptId excludes the requirement being edited');

    // A DIFFERENT client posting the same thing is NOT a duplicate of this client's.
    t_eq(count(connect_requirement_duplicates(array_merge($near, ['poster_party_id' => $other]))), 0, 'a different client is not flagged');

    // A clearly different requirement (other discipline, other place, other title) is not flagged.
    $diff = ['title' => 'Electrical Supervisor night shift', 'poster_party_id' => $client, 'discipline_code' => 'ELEC', 'location' => 'Mumbai',
             'start_date' => date('Y-m-d', strtotime('+200 days')), 'end_date' => date('Y-m-d', strtotime('+220 days'))];
    t_eq(count(connect_requirement_duplicates($diff)), 0, 'a clearly different requirement is not flagged');

    // read-only: detection created / changed nothing
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_requirements WHERE poster_party_id=?", [$client]), 1, 'detection creates no requirement');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
