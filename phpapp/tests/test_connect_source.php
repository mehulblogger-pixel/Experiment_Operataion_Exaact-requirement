<?php
// Inspection request → unified manpower sourcing across pools (K0+).
t_section('connect inspection sourcing (K0+)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_migrate(); connect_identity_migrate(); connect_client_bench_migrate(); connect_deploy_migrate();

    // A client + a call + an inspection job needing "welding" people.
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status,created_at) VALUES ('Client Co','Client Co',1,'ACTIVE',?)")->execute([date('c')]);
    $client = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO calls (call_code,client_id,created_at) VALUES ('CALL-1',?,?)")->execute([$client, date('c')]);
    $call = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO jobs (job_code,call_id,inspection_type,service_code,dep_site,inspection_start_date,inspection_end_date,created_at) VALUES ('JOB-SRC1',?, 'Welding inspection','WELD','Hazira','2026-09-01','2026-09-30',?)")->execute([$call, date('c')]);
    $job = (int)db()->lastInsertId();

    // an internal inspector with welding skills, and a marketplace pro with welding skills
    db()->prepare("INSERT INTO inspectors (name,skills,email,status,created_at) VALUES ('Insp Welder','welding inspection, CSWIP','iw@site.test','ACTIVE',?)")->execute([date('c')]);
    $insp = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_professionals (email,name,headline,skills,disciplines,is_active,created_at) VALUES ('mw@pro.test','Market Welder','Welding inspector','welding inspection, CSWIP','WELD',1,?)")->execute([date('c')]);
    $pro = (int)db()->lastInsertId();

    // --- Pseudo-req is built from the job -------------------------------------
    $req = connect_source_pseudo_req(ops_one("SELECT * FROM jobs WHERE id=?", [$job]));
    t_ok(stripos($req['title'], 'Welding') !== false, 'the pseudo-requirement carries the job’s inspection type');
    t_eq($req['location'], 'Hazira', 'the job site becomes the requirement location');

    // --- Unified candidates: internal + marketplace, annotated ---------------
    $cands = connect_source_candidates($job, 20);
    $byKey = []; foreach ($cands as $c) $byKey[$c['kind'].':'.$c['id']] = $c;
    t_ok(isset($byKey['inspector:'.$insp]), 'an internal inspector is sourced');
    t_ok(isset($byKey['professional:'.$pro]), 'a marketplace professional is sourced');
    t_eq($byKey['inspector:'.$insp]['source'], 'internal', 'the inspector is tagged internal');
    t_ok($byKey['inspector:'.$insp]['assignable'], 'an internal inspector is assignable now');
    t_ok($byKey['professional:'.$pro]['needs_link'] && !$byKey['professional:'.$pro]['assignable'],
         'an unlinked marketplace professional is NOT assignable (needs an inspector link)');

    // --- Controlled assignment ------------------------------------------------
    // assign the internal inspector → jobs.inspector_id set
    [$aok,$amsg] = connect_source_assign($job, 'inspector', $insp);
    t_ok($aok, 'assigning an internal inspector succeeds');
    t_eq((int)ops_val("SELECT inspector_id FROM jobs WHERE id=?", [$job]), $insp, 'the inspector is placed on the job');

    // assigning an UNLINKED professional is refused (ISO gate)
    [$rok,$rmsg] = connect_source_assign($job, 'professional', $pro);
    t_ok(!$rok && stripos($rmsg,'Link') !== false, 'an unlinked professional cannot staff an inspection job');

    // link the professional to a NEW inspector, then assign via the link
    db()->prepare("INSERT INTO inspectors (name,email,status,created_at) VALUES ('Market Welder','mw2@site.test','ACTIVE',?)")->execute([date('c')]);
    $insp2 = (int)db()->lastInsertId();
    connect_identity_link_create($pro, $insp2, 'manual');
    $cands2 = connect_source_candidates($job, 20);
    foreach ($cands2 as $c) if ($c['kind']==='professional' && (int)$c['id']===$pro) { t_ok($c['assignable'] && (int)$c['assign_inspector_id']===$insp2, 'after linking, the professional becomes assignable via its inspector'); }
    [$pok] = connect_source_assign($job, 'professional', $pro);
    t_ok($pok, 'a linked professional can now staff the inspection job');
    t_eq((int)ops_val("SELECT inspector_id FROM jobs WHERE id=?", [$job]), $insp2, 'the linked inspector is placed on the job');

    // --- Client-bench preference ---------------------------------------------
    connect_client_bench_add($client, ['professional_id'=>$pro]);
    $cands3 = connect_source_candidates($job, 20);
    $proRow = null; foreach ($cands3 as $c) if ($c['kind']==='professional' && (int)$c['id']===$pro) $proRow=$c;
    t_ok($proRow && !empty($proRow['on_client_bench']), 'a professional on the client’s bench is flagged');
    t_eq($cands3[0]['kind'].':'.$cands3[0]['id'], 'professional:'.$pro, 'a client-bench candidate is pinned to the top');

    // --- Closed job cannot be assigned ---------------------------------------
    db()->prepare("UPDATE jobs SET closed_flag=1 WHERE id=?")->execute([$job]);
    [$cok] = connect_source_assign($job, 'inspector', $insp);
    t_ok(!$cok, 'a closed job cannot be re-sourced');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
