<?php
// Public-disclosure consent register (ISO §4.2, Roadmap Step 29).
t_section('public-disclosure consent (ISO 4.2)');

[$id, $err] = disclosure_save([
    'subject' => 'Company name on the public certificate register', 'scope' => 'Accreditation body directory',
    'status' => 'REQUESTED',
]);
t_ok($id > 0 && $err === '', 'a disclosure is recorded');
t_eq(disclosure_get($id)['status'], 'REQUESTED', 'it starts as requested');
t_ok(disclosure_counts()['pending'] >= 1, 'a pending request is counted');

// No subject is refused.
[$bad, $e2] = disclosure_save(['subject' => '  ']);
t_ok($bad === 0 && $e2 !== '', 'a disclosure with no subject is refused');

// Marking it consented stamps the date automatically if left blank.
[$eid, $e3] = disclosure_save(['subject' => 'Company name on the public certificate register',
    'scope' => 'Accreditation body directory', 'status' => 'CONSENTED', 'consent_by' => 'Client PM'], $id);
t_ok($eid === $id && $e3 === '', 'it can be marked consented');
$d = disclosure_get($id);
t_eq($d['status'], 'CONSENTED', 'status is CONSENTED');
t_ok($d['consent_on'] !== '', 'the consent date is stamped');
t_ok(disclosure_counts()['pending'] === 0, 'it is no longer counted as pending');
