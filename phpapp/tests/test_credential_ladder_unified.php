<?php
// Gap-5 (EXTEND) — the ONE credential verification ladder (Declared→Documented→Verified→Expired)
// must read across BOTH pools. It was applied only to marketplace professional certs
// (expiry_date/verified/file_id); internal inspectors ran a parallel vocabulary. connect_cred_verify_state
// now also maps inspector-cert fields (valid_to/verify_status/file_name) into the same ladder, with the
// marketplace behaviour unchanged (a pro cert carries none of the inspector fields).
t_section('unified credential verification ladder (Gap 5)');

$future = date('Y-m-d', strtotime('+2 years'));
$past   = date('Y-m-d', strtotime('-2 days'));

// --- marketplace professional certs: unchanged behaviour (regression) ---
t_eq(connect_cred_verify_state(['expiry_date' => $past, 'verified' => 1])['code'], 'EXPIRED', 'pro: an expired cert reads EXPIRED');
t_eq(connect_cred_verify_state(['expiry_date' => $future, 'verified' => 1])['code'], 'VERIFIED', 'pro: a verified in-date cert reads VERIFIED');
t_eq(connect_cred_verify_state(['file_id' => 7])['code'], 'DOCUMENTED', 'pro: a cert with a document reads DOCUMENTED');
t_eq(connect_cred_verify_state(['name' => 'x'])['code'], 'DECLARED', 'pro: a bare cert reads DECLARED');

// --- internal inspector certs: now read through the SAME ladder ---
t_eq(connect_cred_verify_state(['valid_to' => $future, 'verify_status' => 'VERIFIED'])['code'], 'VERIFIED', 'inspector: a VERIFIED in-date cert reads VERIFIED');
t_eq(connect_cred_verify_state(['valid_to' => $past, 'verify_status' => 'VERIFIED'])['code'], 'EXPIRED', 'inspector: an expired cert reads EXPIRED (valid_to)');
t_eq(connect_cred_verify_state(['valid_to' => $future, 'file_name' => 'cswip.pdf'])['code'], 'DOCUMENTED', 'inspector: a cert with a file reads DOCUMENTED');
t_eq(connect_cred_verify_state(['valid_to' => $future, 'verify_status' => 'UNDER_VERIFICATION'])['code'], 'DOCUMENTED', 'inspector: an under-verification cert reads DOCUMENTED');
t_eq(connect_cred_verify_state(['valid_to' => $future])['code'], 'DECLARED', 'inspector: a bare cert reads DECLARED');
t_eq(connect_cred_verify_state(['valid_to' => $future, 'verify_status' => 'REJECTED'])['code'], 'REJECTED', 'inspector: a rejected verdict stands (REJECTED)');
t_eq(connect_cred_verify_state(['valid_to' => $future, 'verify_status' => 'SUPERSEDED'])['code'], 'SUPERSEDED', 'inspector: a superseded verdict stands (SUPERSEDED)');

// --- cross-pool consistency: the same rung is the same code regardless of pool ---
$pro  = connect_cred_verify_state(['expiry_date' => $future, 'verified' => 1]);
$insp = connect_cred_verify_state(['valid_to' => $future, 'verify_status' => 'VERIFIED']);
t_eq($pro['code'], $insp['code'], 'a verified pro cert and a verified inspector cert land on the same ladder rung');
t_ok(in_array($pro['tone'], ['ok', 'warn'], true) && $pro['tone'] === $insp['tone'], 'and carry the same tone');

// --- backward compatibility: a marketplace cert with none of the inspector fields is unchanged ---
$before = connect_cred_verify_state(['expiry_date' => $future, 'verified' => 0, 'file_id' => 0]);
t_eq($before['code'], 'DECLARED', 'a pro cert with no inspector fields still reads DECLARED (unchanged)');
