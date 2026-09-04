<?php
// Phase-0 fix: an invoice must never be born stranded with "No branch is set". On a
// single-branch install, books_invoice_create defaults to the one active office; on a
// multi-branch install it stays unset (the user picks the billing branch). Also: the
// reviewer/approver queue count is safe to call from a dashboard.
t_section('invoice office default + approval-queue count');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    books_migrate();
    // Control the office set inside the transaction.
    db()->exec("UPDATE offices SET is_active=0");
    db()->prepare("INSERT INTO offices (code,name,city,is_active) VALUES ('T1','Test Branch One','Ahmedabad',1)")->execute();
    $only = (int)db()->lastInsertId();
    db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('Office Test Co','Office Test Co',1,'ACTIVE')")->execute();
    $party = (int)db()->lastInsertId();

    // Single active office → the draft inherits it (no more "No branch is set").
    $r1 = books_invoice_create(['partner_id' => $party]);
    t_ok(!empty($r1['id']), 'invoice created');
    $inv1 = books_invoice((int)$r1['id']);
    t_eq((int)$inv1['office_id'], $only, 'single-branch install defaults the invoice to the one active office');
    t_ok(!in_array('No branch is set, so it has no numbering series.', books_issue_missing($inv1), true), 'the "no branch set" blocker is gone');

    // Two active offices → do NOT guess; leave it for the user to choose.
    db()->prepare("INSERT INTO offices (code,name,city,is_active) VALUES ('T2','Test Branch Two','Surat',1)")->execute();
    $two = (int)db()->lastInsertId();
    $r2 = books_invoice_create(['partner_id' => $party]);
    $inv2 = books_invoice((int)$r2['id']);
    t_ok((int)($inv2['office_id'] ?? 0) === 0, 'multi-branch install with no client branch leaves it unset (user chooses)');

    // …UNLESS the client has a billing branch set — then every invoice inherits it,
    // even on a multi-branch install. This is the real fix for "no office is set".
    db()->prepare("UPDATE business_partners SET home_branch_id=? WHERE id=?")->execute([$two, $party]);
    $r2b = books_invoice_create(['partner_id' => $party]);
    t_eq((int)books_invoice((int)$r2b['id'])['office_id'], $two, 'invoice inherits the client\'s billing branch automatically');

    // An explicit office is always honoured.
    $r3 = books_invoice_create(['partner_id' => $party, 'office_id' => $only]);
    t_eq((int)books_invoice((int)$r3['id'])['office_id'], $only, 'an explicitly chosen branch is kept');

    // The reviewer/approver queue count is safe (no user logged in → 0, never throws).
    t_ok(function_exists('idems_awaiting_my_approval_count'), 'the approval-queue count function exists');
    t_ok(is_int(idems_awaiting_my_approval_count()), 'it returns an integer');
    t_eq(idems_awaiting_my_approval_count(), 0, 'with nobody logged in it is 0 (dashboard-safe)');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
