<?php
// ============================================================================
//  Connect K21 — "client-posted" detection gates the desk's voucher actions.
//
//  A job posted by a CLIENT is reviewed and approved by that client in its
//  portal, so the ops desk shows its vouchers read-only (no approve/pay). A job
//  posted internally / for an agency keeps the desk approve+pay actions.
//  (t_eq is t_eq($got, $want).)
// ============================================================================
t_section('connect client-posted detection (K21)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // A client partner and a non-client (internal/agency) partner.
    db()->prepare("INSERT INTO business_partners (legal_name,is_client,created_at) VALUES ('Client Co',1,?)")->execute([date('c')]);
    $clientPid = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO business_partners (legal_name,is_client,created_at) VALUES ('Agency Co',0,?)")->execute([date('c')]);
    $agencyPid = (int)db()->lastInsertId();

    t_ok(connect_requirement_client_posted(['poster_party_id' => $clientPid]) === true, 'a job posted by a client is client-posted');
    t_ok(connect_requirement_client_posted(['poster_party_id' => $agencyPid]) === false, 'a job posted by a non-client is NOT client-posted');
    t_ok(connect_requirement_client_posted(['poster_party_id' => 0]) === false, 'a job with no poster party is not client-posted');
    t_ok(connect_requirement_client_posted(['poster_party_id' => 99999999]) === false, 'an unknown poster party is not client-posted');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
