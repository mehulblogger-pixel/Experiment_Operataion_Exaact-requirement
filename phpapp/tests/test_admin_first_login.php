<?php
// Security: a customer must never keep the shared factory-default password.
// A freshly created admin is flagged to change it, and index.php's own gate
// (must_change_password) then blocks every screen except the change-password
// screen until they do. This proves the flag is set at seed time and that the
// enforcement helper agrees.
t_section('fresh admin must replace the factory password');

$mc = ops_val("SELECT must_change_pwd FROM users WHERE username='admin'");
t_eq((int)$mc, 1, 'a freshly created admin is flagged to change the default password');

t_ok(function_exists('must_change_password'), 'the enforcement helper exists');
t_ok(must_change_password(['must_change_pwd' => 1]), 'the app forces the change before anything else');
t_ok(!must_change_password(['must_change_pwd' => 0]), 'once changed, the person is no longer forced');
