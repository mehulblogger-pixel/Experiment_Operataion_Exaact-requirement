<?php
// Connect M10 — crew / bulk booking. Asserts a requirement can carry a position
// manifest, the crew rollup sums it, and the award→invoice bridge bills the
// WHOLE crew when positions exist (else the single-role figure).
t_section('connect crew booking (M10)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('Shutdown Co',1,'ACTIVE')")->execute();
    $client = (int)db()->lastInsertId();
    $rid = cx_requirement_create(['title' => 'Refinery shutdown crew', 'poster_party_id' => $client, 'positions' => 1], true);

    // Not a crew until positions are added.
    t_ok(!cx_is_crew($rid), 'a requirement starts as a single-role (not a crew)');

    // Build the crew: 10 UT techs @4000, 5 welding inspectors @5000, 1 lead @8000.
    cx_position_add($rid, ['role' => 'UT technician', 'discipline_code' => 'NDT', 'quantity' => 10, 'rate' => 4000, 'shift_pattern' => '12h day']);
    cx_position_add($rid, ['role' => 'Welding inspector', 'discipline_code' => 'WELD', 'quantity' => 5, 'rate' => 5000]);
    cx_position_add($rid, ['role' => 'Lead inspector', 'discipline_code' => 'MECH', 'quantity' => 1, 'rate' => 8000]);
    t_ok(cx_is_crew($rid), 'adding positions makes it a crew');
    t_eq(0, cx_position_add($rid, ['role' => '']), 'a position needs a role');

    // Rollup: 3 positions, 16 people, 10*4000 + 5*5000 + 1*8000 = 73,000.
    $c = cx_crew_summary($rid);
    t_eq(3, $c['positions'], '3 positions in the manifest');
    t_eq(16, $c['headcount'], '16 people across the crew');
    t_eq(73000.0, (float)$c['value'], 'the crew value sums the manifest (73,000)');

    // Award the requirement so the bridge can bill it.
    db()->prepare("INSERT INTO inspectors (name,status,created_at) VALUES ('Crew Lead','ACTIVE',?)")->execute([date('c')]);
    $ins = (int)db()->lastInsertId();
    $a = cx_application_add($rid, ['inspector_id' => $ins]);
    cx_application_transition($a, 'SHORTLISTED'); cx_requirement_transition($rid, 'SHORTLISTING'); cx_requirement_award($rid, $a);

    // The bridge bills the WHOLE crew.
    $ev = connect_engagement_billable($rid);
    t_ok($ev > 0, 'the crew engagement becomes a billable event');
    $row = ops_one("SELECT * FROM billable_events WHERE id=?", [$ev]);
    t_eq(73000.0, (float)$row['amount'], 'the billable amount is the crew total, not a single rate');
    t_eq(16, (int)$row['qty'], 'the billable quantity is the crew headcount');
    t_eq($client, (int)$row['party_id'], 'the client to bill is carried through');

    // Removing a position updates the rollup.
    $first = cx_positions_for($rid)[0];
    cx_position_delete((int)$first['id'], $rid);
    t_eq(2, cx_crew_summary($rid)['positions'], 'removing a position updates the manifest');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
