<?php
// Test book ONB-006 step 3 (owner, 25 Oct 2026): deletion days below the warning
// interval must be called out — in the saved message, with the numbers actually
// stored, and on the panel itself for as long as the numbers stay that way.
t_section('Self sign-up — deleting before the first warning is called out');

t_ok(selfserve_days_problem(['notice_days' => 15, 'delete_days' => 10]) !== '', '10 days to deletion with a 15-day warning: a problem');
t_ok(selfserve_days_problem(['notice_days' => 15, 'delete_days' => 15]) !== '', 'equal numbers: still a problem (no warning would go first)');
t_eq(selfserve_days_problem(['notice_days' => 15, 'delete_days' => 90]), '', '90 days with a 15-day warning: fine');

$h = (string) file_get_contents(__DIR__ . '/../lib/saas_tenants.php');
t_ok(strpos($h, "flash('Saved (' . \$nums . ') — but ' . \$why, 'warning')") !== false, 'the save says the numbers stored and the problem, in amber');
$v = (string) file_get_contents(__DIR__ . '/../views/ops/saas_companies.php');
t_ok(strpos($v, 'selfserve_days_problem($ssc)') !== false, 'the panel keeps showing the problem until it is fixed');

// ONB-020: the welcome screen asks for a password only on a self-installed copy.
$s = (string) file_get_contents(__DIR__ . '/../views/ops/setup.php');
t_ok(strpos($s, "\$askPw = !\$inWorkspace && ") !== false, 'inside a company workspace the welcome screen does not ask for the password again');
