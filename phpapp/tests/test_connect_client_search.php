<?php
// Client-facing talent search + privacy-safe cards + contact-reveal loop (K0+).
t_section('connect client search & contact reveal (K0+)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_pro_migrate(); connect_privacy_migrate(); connect_cred_migrate();

    // A listed professional and an unlisted one.
    db()->prepare("INSERT INTO cx_professionals (email,name,mobile,headline,skills,availability,is_active,created_at) VALUES ('find1@pro.test','Anil Welder','98111','CSWIP welding inspector','welding,ndt','AVAILABLE',1,?)")->execute([date('c')]);
    $pid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,created_at) VALUES ('find2@pro.test','Hidden Person',1,?)")->execute([date('c')]);
    $hid = (int)db()->lastInsertId();
    connect_privacy_save($hid, ['contact'=>'on_request','rate'=>'band','identity'=>'full','listed'=>'0']); // paused discovery
    connect_privacy_save($pid, ['contact'=>'on_request','rate'=>'band','identity'=>'first_initial','listed'=>'1']);

    $client = 4242; // a client party id

    // --- Search returns listed pros only, as privacy-safe cards ---------------
    $cards = connect_client_search($client, ['q'=>''], 100);
    $byId = []; foreach ($cards as $c) $byId[$c['id']] = $c;
    t_ok(isset($byId[$pid]), 'a listed professional appears in client search');
    t_ok(!isset($byId[$hid]), 'a professional who paused discovery is NOT shown to clients');

    $card = $byId[$pid];
    t_eq($card['display_name'], 'Anil W.', 'stranger sees the masked identity on the card');
    t_eq($card['contact_state'], 'request', 'on_request contact offers a Request-contact CTA');
    t_eq($card['mobile'], '', 'no phone number leaks onto a stranger card');

    // --- Contact request → pending → professional approves --------------------
    [$rok,$rmsg] = connect_privacy_reveal_request($pid, $client, 'Acme Pipelines');
    t_ok($rok, 'client can request contact');
    t_ok(!connect_privacy_contact_revealed($pid, $client), 'a request alone does NOT reveal contact');
    $card2 = connect_client_card(ops_one("SELECT * FROM cx_professionals WHERE id=?", [$pid]), $client);
    t_eq($card2['contact_state'], 'requested', 'card shows the request is pending');
    // the request shows in the pro inbox
    $inbox = connect_privacy_requests_for_pro($pid);
    t_eq(count($inbox), 1, 'the request lands in the professional inbox');
    t_eq($inbox[0]['client_name'], 'Acme Pipelines', 'the requesting client name is captured');
    // approve
    [$aok] = connect_privacy_reveal_approve($pid, $client);
    t_ok($aok && connect_privacy_contact_revealed($pid, $client), 'approval reveals contact to that client');
    t_eq(count(connect_privacy_requests_for_pro($pid)), 0, 'approved request leaves the inbox');
    $card3 = connect_client_card(ops_one("SELECT * FROM cx_professionals WHERE id=?", [$pid]), $client);
    t_eq($card3['contact_state'], 'shown', 'approved client now sees contact on the card');
    t_eq($card3['mobile'], '98111', 'the real phone number is shown to the approved client');
    t_eq($card3['display_name'], 'Anil Welder', 'the full name is also unmasked for the approved client');
    // a different client is still masked
    $other = connect_client_card(ops_one("SELECT * FROM cx_professionals WHERE id=?", [$pid]), 9999);
    t_eq($other['contact_state'], 'request', 'a different client still has to ask');

    // --- Engagement auto-reveals (awarded requirement), no approval needed ----
    db()->prepare("INSERT INTO cx_requirements (ref_code,title,poster_party_id,status,awarded_application_id,created_at,updated_at) VALUES ('REQ-T1','Weld insp',?, 'AWARDED', 0, ?, ?)")->execute([7777, date('c'), date('c')]);
    $rid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_applications (requirement_id,applicant_professional_id,applicant_name,status,created_at,updated_at) VALUES (?,?,?, 'AWARDED', ?, ?)")->execute([$rid, $pid, 'Anil Welder', date('c'), date('c')]);
    $aid = (int)db()->lastInsertId();
    db()->prepare("UPDATE cx_requirements SET awarded_application_id=? WHERE id=?")->execute([$aid, $rid]);
    t_ok(connect_privacy_engaged($pid, 7777), 'an awarded requirement makes the client+pro engaged');
    $eng = connect_client_card(ops_one("SELECT * FROM cx_professionals WHERE id=?", [$pid]), 7777);
    t_eq($eng['contact_state'], 'shown', 'an engaged client sees contact without asking');
    t_eq($eng['contact_reason'], 'engaged', 'the reason is the engagement');

    // --- Decline path ---------------------------------------------------------
    connect_privacy_reveal_request($pid, 5555, 'Beta Corp');
    connect_privacy_reveal_decline($pid, 5555);
    t_ok(!connect_privacy_contact_revealed($pid, 5555), 'a declined request never reveals contact');
    t_eq(count(connect_privacy_requests_for_pro($pid)), 0, 'a declined request leaves the inbox');

    // --- Verified-cert chips reflect only real (moderated) verifications ------
    connect_verify_migrate();
    [, , $cid] = connect_cred_cert_save($pid, ['name'=>'CSWIP 3.1','authority'=>'TWI']);
    db()->prepare("UPDATE cx_pro_certs SET file_id=501 WHERE id=?")->execute([$cid]);
    connect_cred_cert_request_verify($pid, $cid);
    $before = connect_client_card(ops_one("SELECT * FROM cx_professionals WHERE id=?", [$pid]), 7777);
    t_eq(count($before['verified_certs']), 0, 'a pending cert is NOT shown as verified on the card');
    $chk = (int)ops_val("SELECT verify_check_id FROM cx_pro_certs WHERE id=?", [$cid]);
    connect_verify_review($chk, 'VERIFIED');
    $after = connect_client_card(ops_one("SELECT * FROM cx_professionals WHERE id=?", [$pid]), 7777);
    t_eq($after['verified_certs'], ['CSWIP 3.1'], 'a moderated cert shows as a verified chip');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
