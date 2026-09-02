<?php
// Revamp P11 — candidate pool convergence (read-only). The same human can sit in both
// the recruitment pool (candidates) and the marketplace pool (cx_professionals). This
// detector matches them by the same mobile / e-mail / name keys the app already dedupes
// on and surfaces the overlap — merging nothing, moving no figure. Twin of the
// engagement / revenue / cost reconciliations, on the identity axis.
t_section('candidate pool convergence (Revamp P11)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_migrate();  // ensure cx_professionals exists

    // A recruitment candidate.
    db()->prepare("INSERT INTO candidates (cand_code, first_name, last_name, mobile, email, stage, created_at)
                   VALUES ('CP-CAND-1','Rajesh','Kumar','9876543210','rajesh.k@example.com','RECEIVED',?)")->execute([date('c')]);
    $candId = (int)db()->lastInsertId();
    // A candidate with NO marketplace twin.
    db()->prepare("INSERT INTO candidates (cand_code, first_name, last_name, mobile, email, stage, created_at)
                   VALUES ('CP-CAND-2','Solo','Person','9000000001','solo@example.com','RECEIVED',?)")->execute([date('c')]);
    $soloId = (int)db()->lastInsertId();

    // The SAME person on the marketplace — mobile written with a leading 0 (last-10 still
    // matches) and the same name, but a different e-mail. Should match by mobile (strongest).
    db()->prepare("INSERT INTO cx_professionals (name, email, mobile, verification_tier, availability, is_active, created_at)
                   VALUES ('Rajesh Kumar','different@example.com','09876543210','verified','AVAILABLE',1,?)")->execute([date('c')]);
    $proId = (int)db()->lastInsertId();
    // A DIFFERENT professional who only shares the candidate's e-mail (match by email).
    db()->prepare("INSERT INTO cx_professionals (name, email, mobile, verification_tier, availability, is_active, created_at)
                   VALUES ('R. Kumar','rajesh.k@example.com','9111111111','registered','BUSY',1,?)")->execute([date('c')]);
    $proEmailId = (int)db()->lastInsertId();
    // An INACTIVE professional who matches by mobile — must be ignored.
    db()->prepare("INSERT INTO cx_professionals (name, email, mobile, verification_tier, availability, is_active, created_at)
                   VALUES ('Ghost','ghost@example.com','9876543210','registered','AVAILABLE',0,?)")->execute([date('c')]);

    // Refresh the request-cached pool index now that the pool has changed.
    candpool_pro_index(true);

    $cand = ops_one("SELECT * FROM candidates WHERE id=?", [$candId]);
    $matches = candpool_pro_matches($cand);
    $byId = [];
    foreach ($matches as $m) $byId[$m['pro_id']] = $m['reason'];

    t_ok(isset($byId[$proId]), 'the same-mobile/same-name professional is matched');
    t_eq($byId[$proId], 'mobile', 'mobile is the strongest match reason (beats name)');
    t_ok(isset($byId[$proEmailId]) && $byId[$proEmailId] === 'email', 'a professional sharing only the e-mail matches by e-mail');
    t_ok(!array_filter($matches, fn($m) => strtolower($m['name']) === 'ghost'), 'an inactive professional is not matched');

    // The reverse direction resolves back to the candidate.
    $pro = ops_one("SELECT * FROM cx_professionals WHERE id=?", [$proId]);
    $back = candpool_cand_matches($pro);
    t_ok((bool)array_filter($back, fn($c) => (int)$c['id'] === $candId), 'the reverse lookup finds the candidate for the professional');

    // The solo candidate matches nobody.
    $solo = ops_one("SELECT * FROM candidates WHERE id=?", [$soloId]);
    t_eq(count(candpool_pro_matches($solo)), 0, 'a candidate with no marketplace twin matches nothing');

    // Scan + summary: exactly one overlapping person here (the solo one does not overlap),
    // recorded once with its strongest reason.
    $scan = candpool_scan(500);
    $mine = array_values(array_filter($scan, fn($r) => in_array($r['cand_code'], ['CP-CAND-1','CP-CAND-2'], true)));
    t_eq(count($mine), 1, 'the scan lists exactly the one overlapping candidate (not the solo one)');
    t_eq($mine[0]['cand_code'], 'CP-CAND-1', 'the overlapping row is the right candidate');
    t_eq($mine[0]['reason'], 'mobile', 'the headline row carries the strongest match reason');
    t_ok($mine[0]['match_count'] >= 2, 'the row records that more than one professional matched');

    // It is READ-ONLY: the detector changed no candidate and no professional row.
    t_eq((string)ops_val("SELECT stage FROM candidates WHERE id=?", [$candId]), 'RECEIVED', 'the detector leaves the candidate untouched');
    t_eq((string)ops_val("SELECT verification_tier FROM cx_professionals WHERE id=?", [$proId]), 'verified', 'the detector leaves the professional untouched');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
    if (function_exists('candpool_pro_index')) candpool_pro_index(true);  // drop test rows from the cache
}
