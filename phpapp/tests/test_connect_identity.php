<?php
// Unified professional identity — link inspector ↔ marketplace pro (K0+).
t_section('connect unified professional identity (K0+)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_migrate(); connect_identity_migrate();
    // person records in BOTH stores, same human (shared e-mail)
    db()->prepare("INSERT INTO inspectors (name,emp_code,email,mobile,status,created_at) VALUES ('Arjun Rao','E-101','arjun@site.test','9811122233','ACTIVE',?)")->execute([date('c')]);
    $insp = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_professionals (email,name,mobile,is_active,created_at) VALUES ('arjun@site.test','Arjun Rao','9811122233',1,?)")->execute([date('c')]);
    $pro = (int)db()->lastInsertId();
    // an unrelated professional (different email/mobile)
    db()->prepare("INSERT INTO cx_professionals (email,name,mobile,is_active,created_at) VALUES ('other@x.test','Other Person','9000000000',1,?)")->execute([date('c')]);
    $other = (int)db()->lastInsertId();

    // --- Suggestions detect the same person by e-mail --------------------------
    $sug = connect_identity_suggestions();
    $hit = null; foreach ($sug as $s) if ((int)$s['professional_id']===$pro && (int)$s['inspector_id']===$insp) $hit=$s;
    t_ok($hit !== null, 'a shared e-mail suggests a link');
    t_eq($hit['basis'], 'email', 'the suggestion names the e-mail basis');

    // --- Link creation + guards ------------------------------------------------
    [$g1] = connect_identity_link_create($pro, 999999); t_ok(!$g1, 'linking to a non-existent inspector is refused');
    [$g2] = connect_identity_link_create(999999, $insp); t_ok(!$g2, 'linking a non-existent professional is refused');
    [$ok,$msg,$lid] = connect_identity_link_create($pro, $insp, 'email_match');
    t_ok($ok && $lid>0, 'the two records link');
    // idempotent same pair
    [$ok2,,$lid2] = connect_identity_link_create($pro, $insp);
    t_ok($ok2 && (int)$lid2===(int)$lid, 're-linking the same pair is a harmless success');

    // --- Bidirectional resolve -------------------------------------------------
    t_eq((int)connect_identity_of_professional($pro)['inspector_id'], $insp, 'professional resolves to its inspector');
    t_eq((int)connect_identity_of_inspector($insp)['professional_id'], $pro, 'inspector resolves to its professional');
    $roles = connect_identity_roles(['inspector_id'=>$insp]);
    t_ok($roles['is_professional'] && $roles['is_inspector'] && $roles['linked'], 'roles show one person wearing both hats');
    t_eq((int)$roles['professional_id'], $pro, 'roles resolve the counterpart id');

    // --- A linked record cannot be double-linked -------------------------------
    db()->prepare("INSERT INTO inspectors (name,email,status,created_at) VALUES ('Someone Else','x2@site.test','ACTIVE',?)")->execute([date('c')]);
    $insp2 = (int)db()->lastInsertId();
    [$dl] = connect_identity_link_create($pro, $insp2);
    t_ok(!$dl, 'a professional already linked cannot be linked to a second inspector');

    // once linked, it drops out of suggestions
    $sug2 = connect_identity_suggestions();
    $still = false; foreach ($sug2 as $s) if ((int)$s['professional_id']===$pro) $still=true;
    t_ok(!$still, 'a linked pair no longer appears as a suggestion');

    // --- Matcher dedupe collapses the linked person ----------------------------
    $rows = [
        ['kind'=>'inspector','id'=>$insp,'name'=>'Arjun Rao','score'=>80,'reasons'=>[]],
        ['kind'=>'professional','id'=>$pro,'name'=>'Arjun Rao','score'=>60,'reasons'=>[]],
        ['kind'=>'professional','id'=>$other,'name'=>'Other Person','score'=>50,'reasons'=>[]],
    ];
    $ded = connect_identity_dedupe_rows($rows);
    t_eq(count($ded), 2, 'the linked person appears once, the unrelated one stays');
    $kept = null; foreach ($ded as $r) if (($r['kind']==='inspector') && (int)$r['id']===$insp) $kept=$r;
    t_ok($kept !== null, 'the higher-scoring (inspector) row is the one kept');
    $droppedPro = false; foreach ($ded as $r) if ($r['kind']==='professional' && (int)$r['id']===$pro) $droppedPro=true;
    t_ok(!$droppedPro, 'the lower-scoring duplicate is dropped');
    t_ok(!empty($kept['also_identity']), 'the kept row is annotated as also being the other role');

    // --- Unlink restores two separate identities -------------------------------
    connect_identity_unlink($lid);
    t_ok(connect_identity_of_professional($pro) === null, 'after unlink the professional has no inspector link');
    $sug3 = connect_identity_suggestions();
    $back = false; foreach ($sug3 as $s) if ((int)$s['professional_id']===$pro && (int)$s['inspector_id']===$insp) $back=true;
    t_ok($back, 'an unlinked pair is suggested again');
    $ded2 = connect_identity_dedupe_rows($rows);
    t_eq(count($ded2), 3, 'with no link, the matcher keeps both rows');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
