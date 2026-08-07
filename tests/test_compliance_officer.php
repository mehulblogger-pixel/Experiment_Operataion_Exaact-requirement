<?php
// Compliance & security: the grievance officer set in Settings is the SAME one
// the CERT-In incident report prints (a key mismatch used to drop it), and the
// one-click "money roles" 2FA preset returns real, sign-in-worthy roles.
t_section('Compliance officer & 2FA money-roles');

// The one-click 2FA preset names roles that actually exist and that move money
// or change permissions — never an empty or bogus list.
$money = twofa_money_roles();
t_ok(count($money) >= 3, 'the money-roles preset names several roles');
foreach ($money as $r) t_ok(isset(ORG_ROLES[$r]), "money role $r is a real role ($r)");
t_ok(in_array('FINANCE', $money, true), 'Finance is a money role');
t_ok(in_array('MASTER_ADMIN', $money, true), 'the Master Admin is a money role');
t_ok(!in_array('INSPECTOR', $money, true), 'a field inspector is not swept into the money roles');

// The grievance officer entered in Settings must appear in the CERT-In report —
// the report used to read a different, never-written setting key.
setting_set('grievance_name', 'Asha Mehta');
setting_set('grievance_email', 'privacy@example.com');
setting_set('grievance_phone', '+91 79 1234 5678');
$g = grievance_officer();
t_eq($g['name'], 'Asha Mehta', 'the grievance officer name is read back');

// Build a minimal incident and render the CERT-In e-mail; the officer must be in it.
compliance_migrate();
db()->prepare("INSERT INTO security_incidents (ref, kind, detected_at, summary, status, created_at)
    VALUES (?,?,?,?,?,?)")->execute(['INC-TEST-1', 'DATA_BREACH', date('c'), 'Test incident', 'OPEN', date('c')]);
$inc = ops_one("SELECT * FROM security_incidents WHERE ref='INC-TEST-1'");
$mail = incident_report_email($inc);
$body = (string)($mail['body'] ?? '');
t_ok(strpos($body, 'Asha Mehta') !== false, 'the CERT-In report names the grievance officer from Settings');
t_ok(strpos($body, 'privacy@example.com') !== false, 'the CERT-In report carries the officer e-mail from Settings');
