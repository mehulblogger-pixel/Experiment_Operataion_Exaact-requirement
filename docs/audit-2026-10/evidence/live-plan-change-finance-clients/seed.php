<?php
chdir(getenv('APPROOT'));
$idx = file_get_contents('index.php'); preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm);
foreach ($mm[1] as $rel) { $f = '.' . $rel; if (is_file($f)) require_once $f; }
boot(); setup_mark_done(); portal_migrate(); mkt_plans_migrate(); mkt_subs_migrate(); if (function_exists('mkt_billing_migrate')) mkt_billing_migrate();
$pw = password_hash('demo12345', PASSWORD_DEFAULT);
db()->prepare("INSERT INTO users (username,password_hash,first_name,email,role,is_superuser,is_active) VALUES ('live.fin',?,'Fin','live.fin@demo.test','FINANCE',0,1)")->execute([$pw]);
db()->prepare("INSERT INTO business_partners (legal_name,display_name,is_client,status) VALUES ('Live Client Ltd','Live Client Ltd',1,'ACTIVE')")->execute(); $cid = (int)db()->lastInsertId();
db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,created_at) VALUES (?,?,?,?,1,0,?)")->execute([$cid,'buyer@live.test','Live Buyer',$pw,date('c')]);
$pro = null; $st = null; foreach (mkt_plans_all('CLIENT') as $p) { if ($p['code']==='CL_PRO') $pro=$p; if ($p['code']==='CL_START') $st=$p; }
db()->prepare("INSERT INTO mkt_subscriptions (subscriber_kind,subscriber_id,plan_id,plan_code,period,amount,started_at,expires_at,status,auto_renew,created_at) VALUES ('CLIENT',?,?,?,'MONTH',4999,?,?,'ACTIVE',1,?)")
   ->execute([$cid,(int)$pro['id'],$pro['code'],date('Y-m-d',strtotime('-5 days')),date('Y-m-d',strtotime('+25 days')),date('c')]);
setting_set('marketplace_addon', '1');
file_put_contents(getenv('OUT'), json_encode(['client' => $cid, 'starter' => (int)$st['id']]));
echo "seeded\n";
