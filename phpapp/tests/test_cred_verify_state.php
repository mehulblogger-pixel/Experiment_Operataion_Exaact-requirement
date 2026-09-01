<?php
// Credential verification-state ladder (Stage 3 / §15). Derived from the data a
// credential already carries — verified flag, a supporting file, expiry — into
// the state a client understands: Self-declared → Document attached → Verified →
// Expired. Highest (least-trusted-blocking / expired) wins.
t_section('credential verification-state ladder');

$future = date('Y-m-d', time() + 400 * 86400);
$soon   = date('Y-m-d', time() + 30 * 86400);
$past   = date('Y-m-d', time() - 10 * 86400);

// DECLARED — self-declared, no document, no verification
t_eq(connect_cred_verify_state(['verified' => 0, 'file_id' => 0, 'expiry_date' => $future])['code'], 'DECLARED', 'no document + not verified → Self-declared');
// DOCUMENTED — a supporting file attached but not verified
t_eq(connect_cred_verify_state(['verified' => 0, 'file_id' => 5, 'expiry_date' => $future])['code'], 'DOCUMENTED', 'a file attached, not verified → Document attached');
// VERIFIED — checked by the platform/authority
$v = connect_cred_verify_state(['verified' => 1, 'file_id' => 5, 'expiry_date' => $future]);
t_eq($v['code'], 'VERIFIED', 'verified flag → Verified');
t_eq($v['tone'], 'ok', 'a verified, well-in-date cert is green');
// VERIFIED but expiring soon → still verified, but amber
t_eq(connect_cred_verify_state(['verified' => 1, 'file_id' => 5, 'expiry_date' => $soon])['tone'], 'warn', 'verified + expiring soon reads amber');
// EXPIRED wins over everything — even a once-verified cert
t_eq(connect_cred_verify_state(['verified' => 1, 'file_id' => 5, 'expiry_date' => $past])['code'], 'EXPIRED', 'a lapsed valid-to is Expired regardless of verification');
t_eq(connect_cred_verify_state(['verified' => 0, 'file_id' => 0, 'expiry_date' => $past])['code'], 'EXPIRED', 'a self-declared lapsed cert is Expired too');
// a cert with no expiry is judged on verification alone
t_eq(connect_cred_verify_state(['verified' => 1, 'file_id' => 0, 'expiry_date' => ''])['code'], 'VERIFIED', 'no expiry + verified → Verified');
