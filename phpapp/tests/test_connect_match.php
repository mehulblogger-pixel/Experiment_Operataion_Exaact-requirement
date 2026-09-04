<?php
// Connect K3 — matching & recommendation. Asserts the scorer composes skills,
// reputation, credentials and eligibility; ranks a well-fitting professional
// above a poor fit; sinks a BLOCKED professional to the bottom; and excludes
// anyone who already applied.
t_section('connect matching & recommendation (K3)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // A strong welding fit, a weak fit, and one blocked by a lapsed certificate.
    db()->prepare("INSERT INTO inspectors (name,skills,status,created_at) VALUES ('Welder Wendy','Welding inspection, NDT (UT, RT)','ACTIVE',?)")->execute([date('c')]);
    $strong = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspectors (name,skills,status,created_at) VALUES ('Painter Pete','Coating and painting inspection','ACTIVE',?)")->execute([date('c')]);
    $weak = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspectors (name,skills,status,created_at) VALUES ('Blocked Bharat','Welding inspection','ACTIVE',?)")->execute([date('c')]);
    $blocked = (int)db()->lastInsertId();

    // The requirement asks for a welding inspector.
    $rid = cx_requirement_create(['title' => 'Welding inspector for pressure-vessel FAT', 'discipline_code' => 'WELD',
        'description' => 'Welding and NDT witness', 'start_date' => date('Y-m-d', strtotime('+10 days'))], true);
    $req = cx_requirement_get($rid);

    // The scorer rewards the welding fit over the painter.
    $sStrong = cx_match_score(ops_one("SELECT * FROM inspectors WHERE id=?", [$strong]), $req);
    $sWeak   = cx_match_score(ops_one("SELECT * FROM inspectors WHERE id=?", [$weak]), $req);
    t_ok($sStrong['score'] > $sWeak['score'], 'a welding professional scores above a painter for a welding job');
    t_ok($sStrong['parts']['skills'] > $sWeak['parts']['skills'], 'the skills component drives the gap');

    // Ranked recommendations: strong fit is first.
    $recs = connect_match_for_requirement($req, 10);
    t_ok(!empty($recs), 'recommendations are produced');
    t_eq($strong, (int)$recs[0]['id'], 'the best-fit welding professional is ranked first');
    t_ok(in_array($recs[0]['reason'], ['Best match','Highly rated','Verified & ready','Strong skills fit','Eligible now'], true), 'the top card carries a plain-language reason');

    // Give the blocked professional a lapsed MANDATORY-style cert so eligibility BLOCKS,
    // then confirm they sink below an eligible peer of similar skills.
    db()->prepare("INSERT INTO inspector_certs (inspector_id,name,valid_to,status,is_mandatory,created_at) VALUES (?,?,?,?,1,?)")
        ->execute([$blocked, 'CSWIP (mandatory)', date('Y-m-d', strtotime('-5 days')), 'EXPIRED', date('c')]);
    $recs2 = connect_match_for_requirement($req, 10);
    $ids = array_map(fn($r) => (int)$r['id'], $recs2);
    $posBlocked = array_search($blocked, $ids, true);
    $posStrong  = array_search($strong, $ids, true);
    if ($posBlocked !== false) t_ok($posStrong < $posBlocked, 'a blocked professional sinks below an eligible one');

    // Excludes someone who already applied.
    cx_application_add($rid, ['inspector_id' => $strong]);
    $recs3 = connect_match_for_requirement($req, 10);
    t_ok(!in_array($strong, array_map(fn($r) => (int)$r['id'], $recs3), true), 'a professional who already applied is not recommended again');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
