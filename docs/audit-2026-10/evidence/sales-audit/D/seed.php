<?php
// Seeds a small known world: 2 offices, one user per role, a client, approval rules.
// Sales records are created later by recon.php through the real handlers.
$GLOBALS['__fresh'] = 1;
require __DIR__ . '/lib_boot.php';
if (function_exists('setup_mark_done')) setup_mark_done();
$pdo = db();
$pdo->prepare("INSERT INTO offices (name,is_active) VALUES ('Alpha Office',1)")->execute(); $A = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO offices (name,is_active) VALUES ('Beta Office',1)")->execute();  $B = (int)$pdo->lastInsertId();
$mk = function ($u, $role, $off, $super = 0, $active = 1) use ($pdo) {
    $pdo->prepare("INSERT INTO users (username,first_name,last_name,email,role,is_superuser,is_active,home_office_id) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$u, ucfirst($u), 'Probe', "$u@example.invalid", $role, $super, $active, $off]);
    return (int)$pdo->lastInsertId();
};
$U = [
  'master' => $mk('pmaster', 'MASTER_ADMIN', $A, 1),
  'bd'     => $mk('pbd', 'BUSINESS_DIRECTOR', $A),
  'bmA'    => $mk('pbma', 'BRANCH_MANAGER', $A),
  'bmB'    => $mk('pbmb', 'BRANCH_MANAGER', $B),
  'coA'    => $mk('pcoa', 'COORDINATOR', $A),
  'mm'     => $mk('pmm', 'MARKETING_MANAGER', $A),
  'bdmA'   => $mk('pbdma', 'BUSINESS_DEV_MANAGER', $A),
  'kamA'   => $mk('pkama', 'KEY_ACCOUNTS_MANAGER', $A),
  'meA'    => $mk('pmea', 'MARKETING_EXECUTIVE', $A),
  'fin'    => $mk('pfin', 'FINANCE', $A),
  'insA'   => $mk('pinsa', 'INSPECTOR', $A),
  'gone'   => $mk('pgone', 'MARKETING_MANAGER', $A, 0, 0),   // deactivated
];
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('Probe Client Ltd','Probe Client',1,'ACTIVE')")->execute(); $C = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('Second Client Ltd','Second Client',1,'ACTIVE')")->execute(); $C2 = (int)$pdo->lastInsertId();
crm_migrate();
file_put_contents(__DIR__ . '/ids.json', json_encode(['A' => $A, 'B' => $B, 'U' => $U, 'C' => $C, 'C2' => $C2], JSON_PRETTY_PRINT));
echo "seeded: ", json_encode(['A' => $A, 'B' => $B, 'U' => $U, 'C' => $C]), "\n";
