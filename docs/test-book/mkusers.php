<?php
// Throwaway copy only: one test user per role, all in the first office.
$pdo = new PDO('sqlite:' . getenv('SQLITE_PATH'));
$off = (int)$pdo->query("SELECT id FROM offices ORDER BY id LIMIT 1")->fetchColumn();
$roles = ['BUSINESS_DIRECTOR','SBU_HEAD','BRANCH_MANAGER','BRANCH_APP_MANAGER','OPERATION_MANAGER','ASST_MANAGER','COORDINATOR',
          'BUSINESS_DEV_MANAGER','KEY_ACCOUNTS_MANAGER','MARKETING_MANAGER','MARKETING_EXECUTIVE','FINANCE','QUALITY_HEAD','TECHNICAL_HEAD','SR_INSPECTOR','INSPECTOR'];
$cols = array_column($pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC), 'name');
foreach ($roles as $r) {
    $un = 'tb_' . strtolower($r);
    $pdo->prepare("DELETE FROM users WHERE username=?")->execute([$un]);
    $f = ['username' => $un, 'first_name' => ucwords(strtolower(str_replace('_', ' ', $r))), 'last_name' => 'Test', 'email' => $un . '@example.test',
          'role' => $r, 'is_active' => 1, 'is_superuser' => 0, 'home_office_id' => $off, 'password_hash' => password_hash('Pw12345678', PASSWORD_DEFAULT)];
    if (in_array('password', $cols) && !in_array('password_hash', $cols)) { $f['password'] = $f['password_hash']; unset($f['password_hash']); }
    $f = array_intersect_key($f, array_flip($cols));
    $pdo->prepare("INSERT INTO users (" . implode(',', array_keys($f)) . ") VALUES (" . implode(',', array_fill(0, count($f), '?')) . ")")->execute(array_values($f));
}
echo "users ok, office $off\n";
