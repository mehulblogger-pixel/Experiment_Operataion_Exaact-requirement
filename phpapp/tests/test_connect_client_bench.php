<?php
// Client private bench / roster — relationship over cx_professionals (K0+).
t_section('connect client private bench (K0+)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_migrate(); connect_client_bench_migrate();
    $A = 3001; $B = 3002;   // two client parties
    db()->prepare("INSERT INTO cx_professionals (email,name,headline,base_city,is_active,created_at) VALUES ('b1@pro.test','Vikram Rao','NDT Level II','Surat',1,?)")->execute([date('c')]);
    $pro = (int)db()->lastInsertId();

    // --- Add from marketplace + relationship (no duplicate person) -------------
    [$ok,,$bid] = connect_client_bench_add($A, ['professional_id'=>$pro,'source'=>'marketplace','private_note'=>'Great on shutdowns','client_rating'=>5,'preferred'=>1,'preferred_rate'=>4500], 'Buyer A');
    t_ok($ok && $bid>0, 'a marketplace professional is added to the bench');
    t_ok(connect_client_bench_has($A,$pro), 'on_bench is true for client A');
    t_ok(!connect_client_bench_has($B,$pro), 'client B does not see A on their bench');
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE id=?", [$pro]), 1, 'no duplicate professional record is created');
    // same person can sit on TWO clients' benches
    connect_client_bench_add($B, ['professional_id'=>$pro], 'Buyer B');
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_client_bench WHERE professional_id=?", [$pro]), 2, 'the same professional is on two benches via a relationship');

    // --- Idempotent add (updates, no duplicate row) ---------------------------
    connect_client_bench_add($A, ['professional_id'=>$pro,'private_note'=>'Updated note']);
    t_eq((int)ops_val("SELECT COUNT(*) FROM cx_client_bench WHERE client_party_id=? AND professional_id=?", [$A,$pro]), 1, 're-adding the same pro does not duplicate the bench row');

    // --- Private data is CLIENT-scoped (privacy §17) --------------------------
    $listA = connect_client_bench_list($A); $rowA = $listA[0];
    t_eq($rowA['private_note'], 'Updated note', "A sees A's private note");
    t_eq((int)$rowA['client_rating'], 5, 'A rating preserved');
    // B cannot read or change A's row
    [$u] = connect_client_bench_update((int)$rowA['id'], $B, ['private_note'=>'hacked']);
    t_ok(!$u, 'another client cannot edit A\'s private bench row');
    t_eq(connect_client_bench_list($A)[0]['private_note'], 'Updated note', "A's note is untouched by B");
    connect_client_bench_remove((int)$rowA['id'], $B);
    t_ok(connect_client_bench_has($A,$pro), 'another client cannot remove A\'s bench entry');

    // --- Manual entry (source C) + later linking ------------------------------
    [$mok,,$mid] = connect_client_bench_add($A, ['manual_name'=>'Offline Welder','manual_role'=>'CSWIP','manual_city'=>'Pune']);
    t_ok($mok && $mid>0, 'a manual (off-platform) person can be added');
    t_eq((int)ops_val("SELECT professional_id FROM cx_client_bench WHERE id=?", [$mid]), 0, 'a manual entry has no professional_id');
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,created_at) VALUES ('welder@pro.test','Offline Welder',1,?)")->execute([date('c')]);
    $pro2 = (int)db()->lastInsertId();
    [$lok] = connect_client_bench_link($mid, $A, $pro2);
    t_ok($lok, 'a manual entry links to a real marketplace profile');
    t_eq((int)ops_val("SELECT professional_id FROM cx_client_bench WHERE id=?", [$mid]), $pro2, 'the manual entry now points at the professional (no duplicate person)');

    // --- Preferred pins to the top of the list --------------------------------
    $list = connect_client_bench_list($A);
    t_ok((int)$list[0]['preferred']===1, 'a preferred professional is pinned to the top');

    // --- "Previous" source excludes those already benched ---------------------
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,created_at) VALUES ('prev@pro.test','Past Applicant',1,?)")->execute([date('c')]);
    $pro3 = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_requirements (ref_code,title,poster_party_id,status,created_at,updated_at) VALUES ('CB-1','x',?, 'OPEN', ?, ?)")->execute([$A, date('c'), date('c')]);
    $rid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_applications (requirement_id,applicant_professional_id,applicant_name,status,created_at,updated_at) VALUES (?,?,?, 'APPLIED', ?, ?)")->execute([$rid,$pro3,'Past Applicant',date('c'),date('c')]);
    $prev = connect_client_bench_previous($A);
    $ids = array_map(fn($r)=>(int)$r['professional_id'], $prev);
    t_ok(in_array($pro3,$ids,true), 'a past applicant appears in the "previous" picker');
    t_ok(!in_array($pro,$ids,true), 'someone already on the bench is excluded from "previous"');

    t_eq(connect_client_bench_count($A), 2, 'bench count reflects A\'s entries (the marketplace pro + the now-linked manual entry)');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
