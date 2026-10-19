<?php
// Build a fresh throwaway DB with offices, users per role and partners.
@unlink(__DIR__ . '/probe.sqlite'); putenv('PROBE_DB=' . __DIR__ . '/probe.sqlite'); putenv('FRESH=1');
require __DIR__ . '/boot.php'; require __DIR__ . '/lib.php';
db()->prepare("INSERT INTO offices (name,code,is_active) VALUES ('Probe HQ','PHQ',1)")->execute();
$off = (int)db()->lastInsertId();
$ids = ['office' => $off];
$ids['master'] = (int)(val("SELECT id FROM users WHERE is_superuser=1 ORDER BY id LIMIT 1") ?: mkuser('pmaster', 'MASTER_ADMIN', $off, 1));
db()->prepare("UPDATE users SET home_office_id=? WHERE id=?")->execute([$off, $ids['master']]);
foreach (['FINANCE' => 'pfin', 'BUSINESS_DEV_MANAGER' => 'pbdm', 'MARKETING_EXECUTIVE' => 'pmkx', 'MARKETING_MANAGER' => 'pmkm',
          'BUSINESS_DIRECTOR' => 'pbd', 'BRANCH_MANAGER' => 'pbm', 'OPERATION_MANAGER' => 'pom', 'COORDINATOR' => 'pcoord'] as $role => $u)
    $ids[strtolower($role)] = mkuser($u, $role, $off);
$ids['p_active']  = mkpartner('Acme Valves Pvt Ltd', '24AAACA1111A1Z5');
$ids['p_black']   = mkpartner('Blacklisted Forge Ltd', '24AAACB2222B1Z5', ['status' => 'BLACKLISTED']);
$ids['p_inact']   = mkpartner('Dormant Pumps Ltd', '24AAACD3333D1Z5', ['status' => 'INACTIVE']);
$ids['p_blocked'] = mkpartner('Blocked Castings Ltd', '24AAACE4444E1Z5', ['hold' => 'BLOCKED']);
$ids['p_hold']    = mkpartner('Held Fittings Ltd', '24AAACF5555F1Z5', ['hold' => 'HOLD']);
file_put_contents(__DIR__ . '/ids.json', json_encode($ids, JSON_PRETTY_PRINT));
echo json_encode($ids), "\n";
echo "licence quotes: ", var_export(function_exists('licence_enabled') ? licence_enabled('sales') : null, true), "\n";
