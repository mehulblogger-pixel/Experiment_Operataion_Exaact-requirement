<?php
// Hiring home for marketplace clients: saved searches + home aggregate (K0+).
t_section('connect hiring home (K0+)');
$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    connect_hiring_migrate(); connect_pro_migrate(); connect_privacy_migrate();
    $party = 8801;

    // --- Saved searches -------------------------------------------------------
    [$ok,$msg] = connect_hiring_saved_search_save($party, '', 'q=welding+inspector&location=Dahej');
    t_ok($ok, 'a search is saved');
    $ss = connect_hiring_saved_searches($party);
    t_eq(count($ss), 1, 'one saved search stored');
    t_eq($ss[0]['label'], 'welding inspector · near Dahej', 'a readable label is derived from the query string');
    // idempotent by query string (updates label, no duplicate)
    connect_hiring_saved_search_save($party, 'Dahej welders', 'q=welding+inspector&location=Dahej');
    $ss = connect_hiring_saved_searches($party);
    t_eq(count($ss), 1, 'saving the same query again does not duplicate');
    t_eq($ss[0]['label'], 'Dahej welders', 'the label is updated in place');
    // empty query refused
    [$eok] = connect_hiring_saved_search_save($party, 'x', '');
    t_ok(!$eok, 'an empty search is not saved');
    // ownership-scoped delete
    connect_hiring_saved_search_delete((int)$ss[0]['id'], 9999);
    t_eq(count(connect_hiring_saved_searches($party)), 1, 'another party cannot delete the search');
    connect_hiring_saved_search_delete((int)$ss[0]['id'], $party);
    t_eq(count(connect_hiring_saved_searches($party)), 0, 'owner deletes the search');

    // --- Home aggregate over the marketplace engine ---------------------------
    // one open requirement with two applicants (one still to review)
    db()->prepare("INSERT INTO cx_requirements (ref_code,title,poster_party_id,status,location,created_at,updated_at) VALUES ('CX-H1','Welding inspector',?, 'OPEN','Dahej', ?, ?)")->execute([$party, date('c'), date('c')]);
    $rid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_professionals (email,name,is_active,created_at) VALUES ('h1@pro.test','Ravi',1,?)")->execute([date('c')]);
    $p1 = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO cx_applications (requirement_id,applicant_professional_id,applicant_name,status,created_at,updated_at) VALUES (?,?,?, 'APPLIED', ?, ?)")->execute([$rid,$p1,'Ravi',date('c'),date('c')]);
    db()->prepare("INSERT INTO cx_applications (requirement_id,applicant_professional_id,applicant_name,status,created_at,updated_at) VALUES (?,?,?, 'REJECTED', ?, ?)")->execute([$rid,$p1,'Ravi',date('c'),date('c')]);

    $home = connect_hiring_home($party);
    t_eq((int)$home['counts']['open_reqs'], 1, 'home counts the open requirement');
    t_eq((int)$home['counts']['awaiting'], 1, 'home counts only the applicant still awaiting a decision');
    t_eq((int)$home['open_reqs'][0]['_apps'], 2, 'the requirement shows its total applicants');
    t_eq((int)$home['open_reqs'][0]['_pending'], 1, 'and how many need review');

    // --- Contact-request status surfaces on the home --------------------------
    connect_privacy_reveal_request($p1, $party, 'My Company');
    $home = connect_hiring_home($party);
    t_eq(count($home['contact_requests']), 1, 'a sent contact request appears on the home');
    t_eq(strtoupper((string)$home['contact_requests'][0]['status']), 'REQUESTED', 'shown as awaiting approval');
    connect_privacy_reveal_approve($p1, $party);
    $home = connect_hiring_home($party);
    t_eq(strtoupper((string)$home['contact_requests'][0]['status']), 'GRANTED', 'flips to granted once the pro approves');
} finally { if ($own && db()->inTransaction()) db()->rollBack(); }
