<?php
// ============================================================================
//  The onboarding gaps found by the test book (22 Oct 2026), each now fixed:
//
//   CLI-001  a marketplace company was told "sign in now" and the sign-in page
//            said "Not found" (portal off by default); no portal "forgot
//            password"; no GSTIN / PAN on the marketplace company sign-up.
//   CLI-002  the plans pages were in no menu.
//   ONB-002  the request form promised an e-mail and sent none.
//   ONB-003  the public request form had no form token, robot trap or limit.
//   ONB-010  approving a request created no owner login and sent nothing.
//   ONB-031  the dashboard's "Say who you are" stayed unticked after the
//            setup wizard was given the company name.
// ============================================================================

t_section('Onboarding gaps from the test book — fixed');

// ---- CLI-001: the portal door opens for marketplace accounts --------------------
$pOn0 = (string) setting_get('portal_enabled', '0');
$mAdd0 = (string) setting_get('marketplace_addon', '');
$cOn0 = (string) setting_get('connect_enabled', '1');
setting_set('portal_enabled', '0');
setting_set('marketplace_addon', '0');
t_ok(!portal_door_open(), 'portal off and marketplace off: the portal sign-in stays hidden (unchanged)');
setting_set('marketplace_addon', '1'); setting_set('connect_enabled', '1');
t_ok(portal_door_open(), 'marketplace on: the sign-in page opens even with the client portal off (it used to say "Not found")');
t_ok(portal_account_allowed(['created_by' => 'self-service']), 'a company that joined the marketplace itself may sign in');
t_ok(!portal_account_allowed(['created_by' => 'Some Staff']), 'a client invited by staff stays out while staff keep the client portal off');
setting_set('portal_enabled', '1');
t_ok(portal_account_allowed(['created_by' => 'Some Staff']), 'with the client portal on, invited clients sign in as before');
setting_set('portal_enabled', '0');

// ---- CLI-001: portal forgotten password ---------------------------------------------
portal_migrate();
$pid = (int) ops_val("SELECT id FROM business_partners ORDER BY id LIMIT 1");
if (!$pid) { db()->prepare("INSERT INTO business_partners (legal_name, is_client, status) VALUES ('Gap Co',1,'ACTIVE')")->execute(); $pid = (int) db()->lastInsertId(); }
$em = 'gap+' . getmypid() . '@portal.test';
db()->prepare("INSERT INTO client_users (partner_id,email,name,password_hash,is_active,must_change,perms,created_by,created_at) VALUES (?,?,?,?,1,0,'','self-service',?)")
    ->execute([$pid, $em, 'Gap Person', password_hash('Old-Pass-123!', PASSWORD_DEFAULT), date('c')]);
$cu = (int) db()->lastInsertId();
portal_reset_request($em);
$mail = ops_one("SELECT body FROM email_log WHERE to_addr=? AND kind='portal_reset' ORDER BY id DESC LIMIT 1", [$em]);
t_ok($mail && preg_match('#/portal/reset\?t=([a-f0-9]{48})#', (string) $mail['body'], $m), 'forgot password e-mails a one-hour link');
$token = $m[1] ?? '';
$row = ops_one("SELECT reset_hash FROM client_users WHERE id=?", [$cu]);
t_ok($row['reset_hash'] !== '' && $row['reset_hash'] !== $token, 'only a hash of the link is stored, never the link itself');
t_eq(portal_reset_problem('nonsense'), 'This link is not valid — it may already have been used. Ask for a new one below.', 'a made-up link is refused');
t_ok(portal_reset_finish($token, 'New-Pass-456!', 'Different-1!') !== '', 'the two new passwords must match');
t_eq(portal_reset_finish($token, 'New-Pass-456!x', 'New-Pass-456!x'), '', 'a valid link sets the new password');
$u = ops_one("SELECT password_hash FROM client_users WHERE id=?", [$cu]);
t_ok(password_verify('New-Pass-456!x', (string) $u['password_hash']), 'and the new password works');
t_ok(portal_reset_problem($token) !== '', 'the link works only once');
$before = (int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='portal_reset'");
portal_reset_request('nobody-' . getmypid() . '@portal.test');
t_eq((int) ops_val("SELECT COUNT(*) FROM email_log WHERE kind='portal_reset'"), $before, 'an unknown address gets nothing (and the page says the same either way)');
unset($_SESSION['cuid']);
db()->prepare("DELETE FROM client_users WHERE id=?")->execute([$cu]);

// ---- CLI-001: GSTIN / PAN on the marketplace company sign-up ---------------------------
[$ok, $msg] = connect_org_register(['name' => 'Gap Tax Co', 'contact_email' => 'tax+' . getmypid() . '@portal.test',
    'password' => 'Gap-Pass-789!', 'gstin' => '24ABCDE1234F1Z']);
t_ok(!$ok && strpos((string) $msg, 'GSTIN') !== false, 'a wrong GSTIN is refused with a sentence');
[$ok, $msg] = connect_org_register(['name' => 'Gap Tax Co', 'contact_email' => 'tax+' . getmypid() . '@portal.test',
    'password' => 'Gap-Pass-789!', 'pan' => 'ABC12']);
t_ok(!$ok && strpos((string) $msg, 'PAN') !== false, 'a wrong PAN is refused with a sentence');

// ---- CLI-002: plans in the menus ------------------------------------------------------
$top = file_get_contents(dirname(__DIR__) . '/views/portal/top.php');
t_ok(strpos($top, "\$links['portal/plans']") !== false, 'the company/agency portal menu has "Plan & credits"');
t_ok(strpos(file_get_contents(dirname(__DIR__) . '/views/pro/top.php'), 'href="/pro/plans"') !== false, 'the professional menu has "Plan & credits"');
t_ok(strpos($top, "'Complaints &amp; appeals'") === false, 'menu labels are not double-escaped (they showed "&amp;")');

// ---- ONB-002 / ONB-003: the request form ------------------------------------------------
tenant_signup_migrate();
$_SERVER['REMOTE_ADDR'] = '198.51.100.' . (getmypid() % 200);
db()->prepare("DELETE FROM tenant_requests WHERE ip=?")->execute([$_SERVER['REMOTE_ADDR']]);
[$ok] = tenant_signup_submit(['company' => 'Gap Request Co', 'contact_name' => 'R Person', 'email' => 'req+' . getmypid() . '@gap.test']);
t_ok($ok, 'a request is received');
t_ok((bool) ops_one("SELECT id FROM email_log WHERE to_addr=? AND kind='tenant_request_ack'", ['req+' . getmypid() . '@gap.test']),
    'and the acknowledgement e-mail the page promises is sent');
for ($i = 0; $i < 4; $i++) tenant_signup_submit(['company' => 'Gap R' . $i, 'email' => "r$i+" . getmypid() . '@gap.test']);
[$ok, $msg] = tenant_signup_submit(['company' => 'Gap R9', 'email' => 'r9+' . getmypid() . '@gap.test']);
t_ok(!$ok && strpos($msg, 'Too many') !== false, 'more than 5 requests an hour from one connection are refused');
$gs = file_get_contents(dirname(__DIR__) . '/views/public/get_started.php');
t_ok(strpos($gs, 'name="_csrf"') !== false && strpos($gs, 'name="website"') !== false, 'the request form carries a form token and a robot trap');
db()->prepare("DELETE FROM tenant_requests WHERE ip=?")->execute([$_SERVER['REMOTE_ADDR']]);

// ---- ONB-010: approving a request creates the owner login ------------------------------------
$root = dirname(__DIR__); $regFile = $root . '/tenants.php';
$hadReg = is_file($regFile); $regBak = $hadReg ? file_get_contents($regFile) : null;
file_put_contents($regFile, "<?php return " . var_export(['base_domain' => 'ops.example.com', 'aliases' => [], 'tenants' => []], true) . ";\n");
$rem = 'approve+' . getmypid() . '@gap.test';
[$ok, , $rid] = tenant_signup_submit(['company' => 'Gap Approve Co', 'contact_name' => 'A Owner', 'email' => $rem, 'sub' => 'gapappr' . getmypid()]);
[$ok, $msg] = tenant_request_approve($rid);
t_ok($ok, 'approving a request creates the company');
$key = (string) tenant_request_get($rid)['tenant_sub'];
t_eq(saas_login_lookup($rem), $key, 'its owner can sign in with their e-mail (approval used to create no login)');
t_eq((string) tenant_request_get($rid)['status'], 'PROVISIONED', 'the request is marked set up — nothing left to finish by hand');
t_ok((bool) ops_one("SELECT id FROM email_log WHERE to_addr=? AND kind='saas_new_owner'", [$rem]), 'the owner is e-mailed how to sign in');
$t = saas_tenant_get($key);
t_ok(($t['source'] ?? '') === 'self' && ($t['plan'] ?? '') === 'TRIAL', 'it starts on the same free trial as a self sign-up');
saas_company_purge($key, 'test clean-up');
if ($hadReg && $regBak !== null) { file_put_contents($regFile, $regBak); } else { @unlink($regFile); }
require $root . '/config.php';

// ---- ONB-031: "Say who you are" ----------------------------------------------------------
$src = file_get_contents($root . '/lib/setup.php');
t_ok(strpos($src, "setting_set('company_name'") !== false, 'the setup wizard also records the company name the dashboard checks');

setting_set('portal_enabled', $pOn0); setting_set('marketplace_addon', $mAdd0); setting_set('connect_enabled', $cOn0);
