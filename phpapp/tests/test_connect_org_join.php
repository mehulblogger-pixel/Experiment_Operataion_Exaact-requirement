<?php
// Connect B1 — self-service organisation onboarding. Asserts an organisation can
// apply for itself (landing PENDING), input is validated, a platform admin
// approves it to ACTIVE, and the pending count reflects the queue.
t_section('connect organisation onboarding (B1)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $before = connect_org_pending_count();

    // Self-apply.
    $id = connect_org_apply(['name' => 'Dahej Fabricators', 'org_type' => 'COMPANY', 'contact_name' => 'Ravi', 'contact_email' => 'ravi@dahej.test', 'contact_mobile' => '9990001111']);
    t_ok($id > 0, 'an organisation can apply for itself');
    $o = connect_org_get($id);
    t_eq('PENDING', $o['status'], 'a self-applied organisation lands PENDING');
    t_eq('ravi@dahej.test', $o['contact_email'], 'the applying contact is captured');
    t_eq('', (string)$o['approved_by'], 'it is not approved yet');

    // Validation.
    t_eq(0, connect_org_apply(['name' => '', 'org_type' => 'COMPANY', 'contact_email' => 'x@y.test']), 'a nameless application is rejected');
    t_eq(0, connect_org_apply(['name' => 'X', 'org_type' => 'NONSENSE', 'contact_email' => 'x@y.test']), 'an unknown type is rejected');
    t_eq(0, connect_org_apply(['name' => 'X', 'org_type' => 'COMPANY', 'contact_email' => 'not-an-email']), 'a bad e-mail is rejected');

    // The pending queue grew by exactly one.
    t_eq($before + 1, connect_org_pending_count(), 'the pending count reflects the new application');

    // Admin approves.
    connect_org_approve($id);
    $o2 = connect_org_get($id);
    t_eq('ACTIVE', $o2['status'], 'approval activates the organisation');
    t_eq($before, connect_org_pending_count(), 'the pending count drops back after approval');

    // Approving an already-active org is a harmless no-op (still ACTIVE).
    connect_org_approve($id);
    t_eq('ACTIVE', connect_org_get($id)['status'], 're-approving is a no-op');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
