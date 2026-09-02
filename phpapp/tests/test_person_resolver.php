<?php
// Gap-8 (EXTEND) — the unified PERSON as a resolve-through-link view (never a merge). Three
// identity records for one human (marketplace professional, internal inspector, recruitment
// candidate) joined by cx_identity_link now resolve to one person from ANY of them, and their
// credentials read across all pools on the one Gap-5 ladder — the "inheritance" a merge would
// give, without merging, moving or deleting any record.
t_section('unified person resolver (Gap 8)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_identity_migrate(); connect_pro_migrate();
    $future = date('Y-m-d', strtotime('+2 years'));

    // One human in three pools.
    db()->prepare("INSERT INTO cx_professionals (email, name, mobile, is_active, created_at) VALUES ('anita.person@ex.com','Anita Rao','9820055001',1,?)")->execute([date('c')]);
    $P = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO inspectors (name, skills, status, created_at) VALUES ('Anita Rao','Welding','ACTIVE',?)")->execute([date('c')]);
    $I = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO candidates (cand_code, first_name, last_name, mobile, email, stage, created_at) VALUES ('PSN-1','Anita','Rao','9820055001','anita.person@ex.com','INTERVIEW',?)")->execute([date('c')]);
    $C = (int)db()->lastInsertId();

    // Links through the professional hub (pro↔inspector, pro↔candidate). No merge.
    db()->prepare("INSERT INTO cx_identity_link (professional_id, inspector_id, status, linked_at) VALUES (?,?, 'LINKED', ?)")->execute([$P, $I, date('c')]);
    db()->prepare("INSERT INTO cx_identity_link (professional_id, candidate_id, status, linked_at) VALUES (?,?, 'LINKED', ?)")->execute([$P, $C, date('c')]);

    // Credentials in two different pools.
    db()->prepare("INSERT INTO cx_pro_certs (pro_id, name, expiry_date, verified) VALUES (?, 'CSWIP 3.1', ?, 1)")->execute([$P, $future]);
    db()->prepare("INSERT INTO inspector_certs (inspector_id, name, valid_to, verify_status) VALUES (?, 'ASNT NDT II', ?, 'VERIFIED')")->execute([$I, $future]);

    // resolve from EACH identity finds all three (incl. the transitive candidate↔inspector via the hub)
    foreach (['candidate' => $C, 'inspector' => $I, 'professional' => $P] as $kind => $id) {
        $r = connect_person_resolve($kind, $id);
        t_ok(in_array($P, $r['professional_ids'], true), "resolve($kind) finds the professional");
        t_ok(in_array($I, $r['inspector_ids'], true), "resolve($kind) finds the inspector");
        t_ok(in_array($C, $r['candidate_ids'], true), "resolve($kind) finds the candidate");
    }
    t_ok(connect_person_is_linked(connect_person_resolve('inspector', $I)) === true, 'the person reads as linked across pools');
    t_eq(connect_person_name(connect_person_resolve('candidate', $C)), 'Anita Rao', 'the person resolves to one name');

    // credentials gathered across BOTH pools, each on the one ladder
    $creds = connect_person_credentials(connect_person_resolve('candidate', $C));
    t_eq(count($creds), 2, 'credentials are gathered from every linked pool');
    $srcs = array_column($creds, 'source');
    t_ok(in_array('professional', $srcs, true) && in_array('inspector', $srcs, true), 'both the pro cert and the inspector cert are included');
    t_ok(count(array_filter($creds, fn($c) => ($c['state']['code'] ?? '') === 'VERIFIED')) === 2, 'both read VERIFIED on the unified ladder');

    // summary
    $sum = connect_person_summary('candidate', $C);
    t_ok($sum['linked'] === true && $sum['credentials'] === 2 && $sum['verified'] === 2, 'the summary tallies the whole person');

    // an UNLINKED candidate resolves to only itself
    db()->prepare("INSERT INTO candidates (cand_code, first_name, last_name, stage, created_at) VALUES ('PSN-2','Solo','Person','RECEIVED',?)")->execute([date('c')]);
    $solo = (int)db()->lastInsertId();
    $rs = connect_person_resolve('candidate', $solo);
    t_ok(connect_person_is_linked($rs) === false && $rs['candidate_ids'] === [$solo] && $rs['professional_ids'] === [], 'an unlinked identity resolves to only itself');

    // read-only: resolving mutated no record
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE id=?", [$P]), 1, 'the professional record is untouched');
    t_eq((int)ops_val("SELECT COUNT(*) FROM candidates WHERE id=?", [$C]), 1, 'the candidate record is untouched');
    t_eq((int)ops_val("SELECT COUNT(*) FROM inspectors WHERE id=?", [$I]), 1, 'the inspector record is untouched');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
