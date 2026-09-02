<?php
// P11 follow-through — confirm a candidate and a marketplace professional are the same
// person, as an ADDITIVE, REVERSIBLE link (nothing merged, nothing deleted). The safe
// first step the P11 detector was built to enable, mirroring person_link_rows but across
// the two pools. Reuses the cx_identity_link ledger (candidate-axis row: inspector_id=0).
t_section('candidate ↔ professional confirm/link (P11)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_identity_migrate();
    connect_pro_migrate();

    db()->prepare("INSERT INTO candidates (cand_code, first_name, last_name, mobile, email, stage, created_at)
                   VALUES ('CPL-1','Meera','Nair','9820011223','meera.n@example.com','INTERVIEW',?)")->execute([date('c')]);
    $cand = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_professionals (name, email, mobile, verification_tier, is_active, created_at)
                   VALUES ('Meera Nair','meera.n@example.com','9820011223','verified',1,?)")->execute([date('c')]);
    $pro = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_professionals (name, email, mobile, is_active, created_at)
                   VALUES ('Other Person','other@example.com','9000000009',1,?)")->execute([date('c')]);
    $pro2 = (int)db()->lastInsertId();

    // no link yet
    t_ok(connect_identity_of_candidate($cand) === null, 'a fresh candidate has no confirmed marketplace link');

    // confirm the match
    [$ok, $msg, $lid] = connect_identity_candidate_link_create($cand, $pro, 'manual');
    t_ok($ok === true && $lid > 0, 'the candidate↔professional link is created');
    $link = connect_identity_of_candidate($cand);
    t_ok($link !== null && (int)$link['professional_id'] === $pro && (int)$link['inspector_id'] === 0,
        'the resolver returns the link (professional set, inspector_id 0 for the candidate axis)');

    // idempotent: confirming the same pair again is a no-op success
    [$ok2, , $lid2] = connect_identity_candidate_link_create($cand, $pro, 'manual');
    t_ok($ok2 === true && (int)$lid2 === (int)$lid, 'confirming the same pair again returns the existing link');

    // a candidate already linked cannot be linked to a DIFFERENT professional
    [$ok3, $msg3] = connect_identity_candidate_link_create($cand, $pro2, 'manual');
    t_ok($ok3 === false, 'a candidate already confirmed to one professional is refused a second link');

    // validation: missing / non-existent ids are rejected
    t_ok(connect_identity_candidate_link_create(0, $pro)[0] === false, 'a missing candidate id is rejected');
    t_ok(connect_identity_candidate_link_create($cand, 999999)[0] === false, 'a non-existent professional is rejected');

    // the scan marks the candidate confirmed
    if (function_exists('candpool_pro_index')) candpool_pro_index(true);
    $row = null; foreach (candpool_scan(500) as $r) if ($r['cand_code'] === 'CPL-1') $row = $r;
    t_ok($row !== null && !empty($row['confirmed']), 'the candidate-pool worklist marks the confirmed row');

    // ADDITIVE + REVERSIBLE: nothing on either record was mutated, and unlink leaves both intact
    t_eq((string)ops_val("SELECT stage FROM candidates WHERE id=?", [$cand]), 'INTERVIEW', 'the candidate row is untouched by the link');
    t_eq((string)ops_val("SELECT verification_tier FROM cx_professionals WHERE id=?", [$pro]), 'verified', 'the professional row is untouched by the link');
    [$uok] = connect_identity_unlink($lid);
    t_ok($uok === true, 'the link can be removed');
    t_ok(connect_identity_of_candidate($cand) === null, 'after unlink the candidate has no active link');
    t_eq((int)ops_val("SELECT COUNT(*) FROM candidates WHERE id=?", [$cand]), 1, 'unlink deletes NO candidate record');
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE id=?", [$pro]), 1, 'unlink deletes NO professional record');

    // after unlink the candidate may be linked afresh (e.g. to the right professional)
    t_ok(connect_identity_candidate_link_create($cand, $pro2, 'manual')[0] === true, 'after unlink a new link can be made');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    if (function_exists('candpool_pro_index')) candpool_pro_index(true);
}
