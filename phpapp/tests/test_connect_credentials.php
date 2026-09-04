<?php
// Structured certifications + project experience on the passport.
t_section('connect passport credentials & projects (K0+)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_cred_migrate(); connect_pro_migrate();
    // expiry status helper
    t_eq(connect_cred_cert_status(''),'','no expiry → no status');
    t_eq(connect_cred_cert_status(date('Y-m-d', strtotime('-1 day'))),'EXPIRED','past date → expired');
    t_eq(connect_cred_cert_status(date('Y-m-d', strtotime('+30 days'))),'EXPIRING','within 60 days → expiring');
    t_eq(connect_cred_cert_status(date('Y-m-d', strtotime('+400 days'))),'VALID','far future → valid');

    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,created_at) VALUES ('cred@pro.test','Cred Pro',1,?)")->execute([date('c')]);
    $pid=(int)db()->lastInsertId();

    // add a cert that resolves to a taxonomy CERTIFICATION node → mirrors to cx_profile_tax
    [$ok,,$cid] = connect_cred_cert_save($pid, ['name'=>'CSWIP 3.1','authority'=>'TWI','cert_number'=>'X123','level'=>'3.1','discipline'=>'Welding','issue_date'=>'2024-01-01','expiry_date'=>date('Y-m-d', strtotime('+2 years'))]);
    t_ok($ok && $cid>0,'certification saved');
    $c = connect_cred_certs($pid)[0];
    t_eq($c['status'],'VALID','stored cert computes VALID status');
    t_ok((int)$c['node_id']>0,'cert links to a CERTIFICATION taxonomy node');
    t_ok((int)ops_val("SELECT COUNT(*) FROM cx_profile_tax WHERE pro_id=? AND relation='CERTIFICATION'", [$pid]) >= 1,'cert mirrors into cx_profile_tax for search/matching');

    // edit the cert (update path)
    connect_cred_cert_save($pid, ['id'=>$cid,'name'=>'CSWIP 3.1','authority'=>'TWI UK']);
    t_eq(connect_cred_certs($pid)[0]['authority'],'TWI UK','cert edit updates in place');
    t_eq(count(connect_cred_certs($pid)),1,'edit did not create a duplicate');

    // --- Verification is NEVER self-declared; it flows through the ledger -----
    connect_verify_migrate();
    // a self-declared 'verified' in the save payload must be ignored
    [$vok,,$vcid] = connect_cred_cert_save($pid, ['name'=>'NEBOSH IGC','authority'=>'NEBOSH','verified'=>1]);
    t_eq((int)connect_cred_certs($pid)[0]['verified'] ?? 0, (int)ops_val("SELECT verified FROM cx_pro_certs WHERE id=?", [$vcid]), 'read is consistent');
    t_eq((int)ops_val("SELECT verified FROM cx_pro_certs WHERE id=?", [$vcid]), 0, 'caller cannot self-declare a cert verified');
    // requesting verification with no document is refused
    [$rok,$rmsg] = connect_cred_cert_request_verify($pid, $vcid);
    t_ok(!$rok, 'verification refused without an evidence document');
    // attach a doc → request → goes PENDING and files a ledger check
    db()->prepare("UPDATE cx_pro_certs SET file_id=777 WHERE id=?")->execute([$vcid]);
    [$rok2] = connect_cred_cert_request_verify($pid, $vcid);
    t_ok($rok2, 'verification requested once a document is attached');
    $vc = ops_one("SELECT * FROM cx_pro_certs WHERE id=?", [$vcid]);
    t_eq($vc['verify_status'], 'PENDING', 'cert badge is Pending after request');
    t_eq((int)$vc['verified'], 0, 'still not verified while pending');
    $checkId = (int)$vc['verify_check_id']; t_ok($checkId > 0, 'a moderation ledger check was filed and linked');
    // a moderator VERIFIES the check → reconcile flips the cert badge + the tax mirror
    connect_verify_review($checkId, 'VERIFIED');
    $vc2 = connect_cred_certs($pid); $me2 = null; foreach ($vc2 as $r) if ((int)$r['id']===$vcid) $me2=$r;
    t_eq($me2['verify_status'], 'VERIFIED', 'moderator decision reconciles to Verified on read');
    t_eq((int)$me2['verified'], 1, 'cert is verified only after a real decision');
    connect_cred_cert_delete($vcid, $pid);

    // project experience
    [$pok,,$prid] = connect_cred_project_save($pid, ['title'=>'Refinery shutdown 2025','role'=>'NDT Technician','industry'=>'Oil & Gas','location'=>'Dahej','equipment'=>'Pressure piping','scope'=>'RT/UT','start_date'=>'2025-03-01','end_date'=>'2025-04-15']);
    t_ok($pok && $prid>0,'project saved');
    $pr = connect_cred_projects($pid)[0];
    t_eq($pr['role'],'NDT Technician','project role stored');
    t_eq($pr['industry'],'Oil & Gas','project industry stored');

    // deletes are ownership-scoped
    connect_cred_cert_delete($cid, 99999); t_eq(count(connect_cred_certs($pid)),1,'another owner cannot delete the cert');
    connect_cred_cert_delete($cid, $pid);  t_eq(count(connect_cred_certs($pid)),0,'owner deletes the cert');
    connect_cred_project_delete($prid, $pid); t_eq(count(connect_cred_projects($pid)),0,'owner deletes the project');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
