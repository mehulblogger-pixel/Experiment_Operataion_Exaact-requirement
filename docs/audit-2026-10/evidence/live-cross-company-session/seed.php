<?php
// seed one company's database: setup done, portal on, one client with one portal user
chdir(getenv('APPROOT'));
$idx = file_get_contents('index.php'); preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm);
foreach ($mm[1] as $rel) { $f = '.' . $rel; if (is_file($f)) require_once $f; }
boot(); setup_mark_done(); portal_migrate(); setting_set('portal_enabled', '1');
$name = getenv('CO');
db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES (?,?,1,'ACTIVE')")->execute(["$name CONFIDENTIAL CLIENT", "$name CONFIDENTIAL CLIENT"]);
$pid = (int)db()->lastInsertId();
db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,created_at) VALUES (?,?,?,?,1,0,?)")
   ->execute([$pid, strtolower($name) . '.buyer@test', "$name Buyer", password_hash(strtolower($name) . '-secret', PASSWORD_DEFAULT), date('c')]);
echo "$name: client_users id " . db()->lastInsertId() . "\n";
