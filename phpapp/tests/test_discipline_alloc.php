<?php
// #1 Discipline-aware allocation. When a job says which trade / discipline it
// needs, the engineers who hold that trade must be suggested first and tagged,
// while everyone else stays on the list (nothing is hidden). Also checks the
// need is a persisted job field and the trade list helper is available.
t_section('discipline-aware allocation (#1)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // Two people with different disciplines. trade_id stores a Trade lookup-value
    // id; the ranking only compares the ids, so plain distinct ids are enough.
    $mech = team_member_create('Mech Discipline One', 'FIELD');
    $elec = team_member_create('Elec Discipline Two', 'FIELD');
    t_ok($mech > 0 && $elec > 0, 'two engineers are created');
    db()->prepare("UPDATE inspectors SET trade_id=? WHERE id=?")->execute([990001, $mech]);
    db()->prepare("UPDATE inspectors SET trade_id=? WHERE id=?")->execute([990002, $elec]);

    $bp = function ($name, $client, $vendor) {
        db()->prepare("INSERT INTO business_partners (legal_name, display_name, code, is_client, is_vendor, status, created_at)
                       VALUES (?,?,?,?,?,?,?)")->execute([$name, $name, strtoupper(substr($name,0,3)), $client, $vendor, 'ACTIVE', 'x']);
        return (int)db()->lastInsertId();
    };
    $client = $bp('Disc Client', 1, 0);
    $vendor = $bp('Disc Vendor', 0, 1);
    $call = ['client_id' => $client, 'vendor_id' => $vendor, 'id' => 0];

    // With no discipline asked for, trade_match is null and neither is favoured
    // on discipline grounds.
    $any = inspector_suggestions($call, []);
    $byId = []; foreach ($any as $s) $byId[(int)$s['id']] = $s;
    t_ok(array_key_exists($mech, $byId) && $byId[$mech]['trade_match'] === null, 'with no discipline required, nobody is a discipline match');

    // Require the mechanical discipline: the mechanical engineer is flagged and
    // ranks above the electrical one; the electrical one is still present.
    $req = inspector_suggestions($call, [], 0, 990001);
    $rank = []; $flag = [];
    foreach ($req as $ix => $s) { $rank[(int)$s['id']] = $ix; $flag[(int)$s['id']] = $s['trade_match']; }
    t_ok($flag[$mech] === true,  'the matching engineer is tagged a discipline match');
    t_ok($flag[$elec] === false, 'the non-matching engineer is tagged not-a-match');
    t_ok(isset($rank[$mech], $rank[$elec]) && $rank[$mech] < $rank[$elec],
        'the matching-discipline engineer is suggested above the non-matching one');
    t_ok(isset($rank[$elec]), 'the non-matching engineer is still on the list (nobody is hidden)');

    // The full picker list carries each person's trade so the form can rank and
    // label it.
    $list = inspectors_list(true);
    $has = false; foreach ($list as $r) if ((int)$r['id'] === $mech) { $has = array_key_exists('trade_id', $r); break; }
    t_ok($has, 'inspectors_list() exposes trade_id for the picker');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The required discipline is a persisted job field, and the trade-list helper
// the form and the availability board rely on exists.
t_ok(in_array('req_trade_id', job_save_fields(), true), 'req_trade_id is a saved job field');
t_ok(function_exists('trade_options'), 'trade_options() is available for the picker and the availability board');
$src = (string)file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($src, "ensure_column('jobs', 'req_trade_id'") !== false, 'the jobs table gains a req_trade_id column');
