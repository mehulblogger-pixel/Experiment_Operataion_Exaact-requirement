<?php
// Slice 1 — the professional's job board auto-matches to their profile: a mechanical
// / welding professional sees mechanical + welding work, NOT pure-electrical work,
// automatically. Reuses the existing match scorer. A profile with no skills falls
// back to the full board (never an empty screen).
t_section('professional job board auto-match');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    connect_market_migrate();
    // Three open requirements in different disciplines (words in the title so the
    // token matcher discriminates regardless of extra columns).
    $mk = function ($title, $disc) {
        db()->prepare("INSERT INTO business_partners (legal_name,is_client,status) VALUES ('JB Co',1,'ACTIVE')")->execute();
        $party = (int)db()->lastInsertId();
        return (int)cx_requirement_create([
            'title' => $title, 'poster_party_id' => $party, 'poster_name' => 'JB Co',
            'discipline_code' => $disc, 'location' => 'Ahmedabad', 'work_type' => 'FREELANCE', 'positions' => 1,
            'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+10 days')),
            'rate_min' => 1000, 'rate_max' => 2000, 'rate_unit' => 'day',
        ], true);
    };
    $mech = $mk('Mechanical Fitter Inspection', 'MECH');
    $elec = $mk('Electrical Substation Inspector', 'ELEC');
    $weld = $mk('Welding Inspector CSWIP', 'WELD');

    // A mechanical + welding (CSWIP) professional. (connect_pro_register returns ''
    // on success and stores the id in the session; read it back by e-mail.)
    $mail1 = 'match_' . substr(md5(uniqid('', true)),0,6) . '@ex.com';
    t_eq(connect_pro_register(['name' => 'Match Pro', 'email' => $mail1, 'password' => 'password1', 'discipline_code' => 'MECH']), '', 'the professional registers');
    $pid = (int)ops_val("SELECT id FROM cx_professionals WHERE email=?", [$mail1]);
    db()->prepare("UPDATE cx_professionals SET disciplines='MECH,WELD', skills='mechanical, welding inspection, cswip' WHERE id=?")->execute([$pid]);

    $matched = connect_match_requirements_for_pro($pid, true);
    $ids = array_map(fn($r) => (int)$r['id'], $matched);
    t_ok(in_array($mech, $ids, true), 'the mechanical job is on the matched board');
    t_ok(in_array($weld, $ids, true), 'the welding/CSWIP job is on the matched board');
    t_ok(!in_array($elec, $ids, true), 'the pure-electrical job is NOT on the matched board');
    t_ok(!empty($matched[0]['_reason']), 'each matched job carries a plain-language reason');
    t_ok(isset($matched[0]['_match']) && is_int($matched[0]['_match']), 'each matched job carries a numeric score');

    // "Show all" ignores the filter — every open job appears.
    $all = connect_match_requirements_for_pro($pid, false);
    $allIds = array_map(fn($r) => (int)$r['id'], $all);
    t_ok(in_array($mech, $allIds, true) && in_array($elec, $allIds, true) && in_array($weld, $allIds, true), 'show-all lists every open job incl. electrical');

    // A profile with NO skills → matched board falls back to the full board (never empty).
    $mail2 = 'empty_' . substr(md5(uniqid('', true)),0,6) . '@ex.com';
    connect_pro_register(['name' => 'Empty Pro', 'email' => $mail2, 'password' => 'password1']);
    $pid2 = (int)ops_val("SELECT id FROM cx_professionals WHERE email=?", [$mail2]);
    db()->prepare("UPDATE cx_professionals SET disciplines='', skills='' WHERE id=?")->execute([$pid2]);
    $fallback = connect_match_requirements_for_pro($pid2, true);
    t_ok(count($fallback) >= 3, 'an empty profile falls back to the full open board (no empty screen)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
