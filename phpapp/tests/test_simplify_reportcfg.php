<?php
// Simplification, step 3 — set-once report configuration moves out of Reporting
// into Admin, so Reporting stays about writing reports. Outright home-move;
// routes and screens untouched.
t_section('report config moved to Admin (simplify step 3)');

$a = (string)file_get_contents(__DIR__ . '/../lib/areas.php');
$reporting = substr($a, strpos($a, "case 'reporting':"), 1600);
$admin = substr($a, strpos($a, "case 'admin':"), 7000);   // full admin case (it has grown with Terminology + Form Designer cards)

// UPDATED IN MILESTONE 11 — the assertion is about WHERE the tile lives, not
// what it is called. M11 renamed the Admin tile to 'Report templates' and gave
// it an explicit destination ('/templates?kind=report'), because the same label
// pointing at the same bare router existed in two areas and which screen you got
// depended on your permissions rather than on the tile you clicked.
//
// The home-move assertion below is unchanged in substance: move the tile back to
// Reporting and it still fails. It simply no longer pins the old wording.
foreach ([['Approver mapping', '/approver-map'], ['Approval rules', '/approval-rules']] as [$lbl, $rt]) {
    t_ok(strpos($reporting, "'$lbl', '$rt'") === false, "$lbl is removed from Reporting");
    t_ok(strpos($admin, "'$lbl', '$rt'") !== false, "$lbl now lives under Admin");
}
t_ok(strpos($reporting, "'/templates") === false, 'the templates tile is removed from Reporting');
t_ok(strpos($admin, "'/templates?kind=report'") !== false, 'the report-template tile now lives under Admin');
t_ok(strpos($admin, "'Report templates'") !== false, 'M11 — and names the library it opens');
t_ok(strpos($reporting, "'Audit trail', '/audit-log'") === false, 'the audit trail is removed from Reporting');
t_ok(strpos($admin, "'Report audit trail', '/audit-log'") !== false, 'the audit trail now lives under Admin');

// Reporting keeps the things you use daily.
t_ok(strpos($reporting, "T_REG('report'), '/documents'") !== false, 'Reporting still has the report register');
t_ok(strpos($reporting, "'Technical writing', '/writing-assistant'") !== false, 'Reporting still has writing help');
t_ok(strpos($admin, "'approver-map','approval-rules'") !== false, 'the moved routes highlight Admin now');
