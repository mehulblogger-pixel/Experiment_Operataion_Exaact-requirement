<?php
require __DIR__ . '/harness.php';
$admin = (int) ops_val("SELECT id FROM users WHERE is_superuser=1 ORDER BY id LIMIT 1");
$mm = mkuser('mm1', 'MARKETING_MANAGER');
$bdm = mkuser('bdm1', 'BUSINESS_DEV_MANAGER');
foreach (['MARKETING_MANAGER','BUSINESS_DEV_MANAGER','MARKETING_EXECUTIVE','FINANCE','BRANCH_MANAGER','COORDINATOR'] as $r) {
    $p = role_perms($r);
    show("role $r quotes/crm perms", implode(',', array_values(array_filter($p, fn($x) => strpos($x, 'quote') !== false || strpos($x, 'crm.') === 0))));
}
$q = new_quote($bdm, [['Inspection', '2', '5000']]);
show('BDM created quote', qrow($q));
