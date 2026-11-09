<?php
// Owner, 9 Nov 2026 (item 6): "This shall also be displayed somewhere in company
// admin that this company has only this many users and this many users are
// created and this many are left."
t_section('Logins: in the plan, created, left — shown in company admin');
$keep = (string) setting_get('saas_seat_limit', '');
$used = (int) ops_val("SELECT COUNT(*) FROM users WHERE is_active=1");
setting_set('saas_seat_limit', (string) ($used + 3));
$c = saas_login_counts();
t_eq([$c['limit'], $c['used'], $c['left']], [$used + 3, $used, 3], 'allowed, created and left come from the plan');
$h = saas_login_strip_html();
t_ok(strpos($h, 'Logins in your plan') !== false && strpos($h, 'Created (active)') !== false && strpos($h, '>Left<') !== false, 'the strip names all three');
t_ok(strpos($h, 'are in use') === false, 'no warning while logins are left');
setting_set('saas_seat_limit', (string) $used);
t_ok(strpos(saas_login_strip_html(), 'All ' . $used . ' logins are in use') !== false, 'when none are left it says so, and how to get more');
setting_set('saas_seat_limit', '0');
t_eq(saas_login_counts()['left'], null, 'no limit: nothing is "left" to count');
setting_set('saas_seat_limit', $keep);
$u = (string) file_get_contents(__DIR__ . '/../views/ops/users.php');
$f = (string) file_get_contents(__DIR__ . '/../views/ops/user_form.php');
$a = (string) file_get_contents(__DIR__ . '/../lib/areas.php');
t_ok(strpos($u, 'saas_login_strip_html()') !== false, 'shown on Users');
t_ok(strpos($f, 'saas_login_strip_html()') !== false, 'and on Add a person');
t_ok(strpos($a, "' created · '") !== false, 'and on the Your subscription tile in Admin');
