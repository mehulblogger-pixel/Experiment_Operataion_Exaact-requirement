<?php
chdir(getenv('APPROOT'));
$idx = file_get_contents('index.php'); preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm);
foreach ($mm[1] as $rel) { $f = '.' . $rel; if (is_file($f)) require_once $f; }
boot(); setup_mark_done();
$pw = password_hash('demo12345', PASSWORD_DEFAULT);
foreach ([['live.coord','COORDINATOR'],['live.insp','INSPECTOR'],['live.fin','FINANCE']] as [$u,$r])
    db()->prepare("INSERT INTO users (username,password_hash,first_name,email,role,is_superuser,is_active) VALUES (?,?,?,?,?,0,1)")->execute([$u,$pw,$r,$u.'@demo.test',$r]);
db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_vendor,status) VALUES ('Live Vendor Works','Live Vendor Works',1,'ACTIVE')")->execute();
if (function_exists('licence_enable')) { foreach (['ncr','quality','accreditation'] as $m) try { licence_enable($m); } catch (Throwable $e) {} }
echo "seeded\n";
