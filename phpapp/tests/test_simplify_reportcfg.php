<?php
// Simplification, step 3 — set-once report configuration moves out of Reporting
// into Admin, so Reporting stays about writing reports. Outright home-move;
// routes and screens untouched.
t_section('report config moved to Admin (simplify step 3)');

$a = (string)file_get_contents(__DIR__ . '/../lib/areas.php');
$reporting = substr($a, strpos($a, "case 'reporting':"), 1600);
$admin = substr($a, strpos($a, "case 'admin':"), 3500);

foreach ([['Approver mapping', '/approver-map'], ['Approval rules', '/approval-rules'],
          ['Document templates', '/templates']] as [$lbl, $rt]) {
    t_ok(strpos($reporting, "'$lbl', '$rt'") === false, "$lbl is removed from Reporting");
    t_ok(strpos($admin, "'$lbl', '$rt'") !== false, "$lbl now lives under Admin");
}
t_ok(strpos($reporting, "'Audit trail', '/audit-log'") === false, 'the audit trail is removed from Reporting');
t_ok(strpos($admin, "'Report audit trail', '/audit-log'") !== false, 'the audit trail now lives under Admin');

// Reporting keeps the things you use daily.
t_ok(strpos($reporting, "T_REG('report'), '/documents'") !== false, 'Reporting still has the report register');
t_ok(strpos($reporting, "'Technical writing', '/writing-assistant'") !== false, 'Reporting still has writing help');
t_ok(strpos($admin, "'approver-map','approval-rules'") !== false, 'the moved routes highlight Admin now');
