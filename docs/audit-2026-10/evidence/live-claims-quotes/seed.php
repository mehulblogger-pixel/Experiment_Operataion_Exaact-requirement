<?php
// Throwaway SQLite only — never the real database.
chdir('/home/user/Experiment_Operataion_Exaact-requirement/phpapp');
$idx = file_get_contents('index.php');
preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm);
foreach ($mm[1] as $rel) { $f = '.' . $rel; if (is_file($f)) require_once $f; }
boot();
$pdo = db();
$pdo->prepare("INSERT INTO users (username,password_hash,first_name,last_name,email,role,is_superuser,is_active) VALUES ('live.admin',?,'Live','Admin','live.admin@demo.test','MASTER_ADMIN',1,1)")->execute([password_hash('demo12345', PASSWORD_DEFAULT)]);
$pdo->prepare("INSERT INTO inspectors (name,status,created_at) VALUES ('Live Engineer','ACTIVE',?)")->execute([date('c')]); $ins = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO vouchers (inspector_id, month, status, created_at) VALUES (?, '2097-07', 'DRAFT', ?)")->execute([$ins, date('c')]); $v = (int)$pdo->lastInsertId();
$ids = [];
foreach ([1, 2] as $n) { $pdo->prepare("INSERT INTO voucher_entries (voucher_id, entry_date, day_type, hours) VALUES (?, '2097-07-05', 'WORK', 0)")->execute([$v]); $ids[] = (int)$pdo->lastInsertId(); }
echo json_encode(['voucher' => $v, 'entries' => $ids]), "\n";
