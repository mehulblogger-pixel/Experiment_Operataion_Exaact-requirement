<?php
// Award → deployment bridge: a marketplace award becomes a PDSO deputation job.
t_section('connect award → deployment bridge (K0+)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_migrate(); connect_identity_migrate(); connect_deploy_migrate();
    if (function_exists('connect_engage_migrate')) connect_engage_migrate();

    $party = 6001;
    // helper: make an AWARDED requirement whose awarded application points at a subject
    $mkReq = function ($appCols) use ($party) {
        db()->prepare("INSERT INTO cx_requirements (ref_code,title,poster_party_id,status,location,start_date,end_date,created_at,updated_at)
                       VALUES (?,?,?, 'AWARDED', 'Dahej, Gujarat', '2026-09-01','2026-10-15', ?, ?)")
            ->execute(['R-'.substr(md5(uniqid('',true)),0,5), 'Welding inspector', $party, date('c'), date('c')]);
        $rid = (int)db()->lastInsertId();
        $cols = array_merge(['requirement_id'=>$rid,'applicant_name'=>'Awarded Person','status'=>'AWARDED','created_at'=>date('c'),'updated_at'=>date('c')], $appCols);
        $keys = implode(',', array_keys($cols)); $ph = implode(',', array_fill(0, count($cols), '?'));
        db()->prepare("INSERT INTO cx_applications ($keys) VALUES ($ph)")->execute(array_values($cols));
        $aid = (int)db()->lastInsertId();
        db()->prepare("UPDATE cx_requirements SET awarded_application_id=? WHERE id=?")->execute([$aid, $rid]);
        return $rid;
    };

    // --- Award to an internal inspector → assigned deputation job -------------
    db()->prepare("INSERT INTO inspectors (name,email,status,created_at) VALUES ('Deploy Insp','di@site.test','ACTIVE',?)")->execute([date('c')]);
    $insp = (int)db()->lastInsertId();
    $r1 = $mkReq(['inspector_id'=>$insp]);
    [$ok,$msg,$jid] = connect_deploy_from_engagement($r1);
    t_ok($ok && $jid>0, 'deployment created for an inspector award');
    $job = ops_one("SELECT * FROM jobs WHERE id=?", [$jid]);
    t_eq($job['job_type'], 'DEPUTATION', 'the deployment is a PDSO deputation job');
    t_eq((int)$job['inspector_id'], $insp, 'the internal inspector is placed on the job');
    t_eq($job['dep_site'], 'Dahej, Gujarat', 'the requirement location becomes the deputation site');
    t_eq((int)$job['source_requirement_id'], $r1, 'the job links back to the requirement');
    t_ok($job['dep_status'] !== '', 'the deployment gets an initial PDSO status');

    // idempotent
    [$ok2,,$jid2] = connect_deploy_from_engagement($r1);
    t_eq($jid2, $jid, 're-deploying updates the same job (no duplicate)');
    t_eq((int)ops_val("SELECT COUNT(*) FROM jobs WHERE source_requirement_id=?", [$r1]), 1, 'only one deployment job per requirement');

    // PDSO can drive it (reusing the existing engine, not a new one)
    if (function_exists('pdso_set_status')) {
        pdso_set_status($jid, 'MOBILIZED');
        t_eq(ops_val("SELECT dep_status FROM jobs WHERE id=?", [$jid]), 'MOBILIZED', 'PDSO advances the deployment status');
    }

    // --- Award to a marketplace professional NOT linked → unassigned ---------
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,created_at) VALUES ('dep@pro.test','Dep Pro',1,?)")->execute([date('c')]);
    $pro = (int)db()->lastInsertId();
    $r2 = $mkReq(['applicant_professional_id'=>$pro]);
    [$pok,$pmsg,$pjid] = connect_deploy_from_engagement($r2);
    t_ok($pok && $pjid>0, 'deployment created for a professional award');
    t_eq((int)ops_val("SELECT COALESCE(inspector_id,0) FROM jobs WHERE id=?", [$pjid]), 0, 'an unlinked professional deploys UNASSIGNED');
    t_ok(stripos($pmsg, 'not yet linked') !== false, 'the desk is told to link the professional to an inspector');

    // --- Link the professional (Connection #1) then sync → now assigned ------
    db()->prepare("INSERT INTO inspectors (name,email,status,created_at) VALUES ('Dep Pro','dep2@site.test','ACTIVE',?)")->execute([date('c')]);
    $insp2 = (int)db()->lastInsertId();
    connect_identity_link_create($pro, $insp2, 'manual');
    [$sok] = connect_deploy_from_engagement($r2);   // sync
    t_eq((int)ops_val("SELECT inspector_id FROM jobs WHERE id=?", [$pjid]), $insp2, 'after linking, sync places the linked inspector on the deployment');

    // --- Refuses when not awarded --------------------------------------------
    db()->prepare("INSERT INTO cx_requirements (ref_code,title,poster_party_id,status,created_at,updated_at) VALUES ('R-OPEN','x',?, 'OPEN', ?, ?)")->execute([$party, date('c'), date('c')]);
    $rOpen = (int)db()->lastInsertId();
    [$nok,$nmsg] = connect_deploy_from_engagement($rOpen);
    t_ok(!$nok, 'a non-awarded requirement cannot be deployed');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
