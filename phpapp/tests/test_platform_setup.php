<?php
// ============================================================================
//  Platform setup (owner, 22 Oct 2026): the platform's own e-mail used by every
//  company without its own, with a separate sender for account & password
//  e-mails; the payment keys check; the go-live checklist.
// ============================================================================

t_section('Platform setup — platform e-mail, account sender, payment check, checklist');

$root = dirname(__DIR__);
$f = platform_mail_file();
$had = is_file($f); $bak = $had ? file_get_contents($f) : null;
@unlink($f);
$ownHost = (string) setting_get('smtp_host', '');
setting_set('smtp_host', '');
putenv('OPS_SMTP_HOST');

t_ok(!platform_mail_ready() && smtp_config('reminder') === null, 'nothing set anywhere: no e-mail can be sent (and the checklist says so)');

$err = platform_mail_save(['host' => 'smtp.example.test', 'port' => 587,
    'general'  => ['name' => 'EXAACT', 'from' => 'noreply@exaact.test', 'user' => 'noreply@exaact.test', 'pass' => 'gen-secret'],
    'accounts' => ['name' => 'EXAACT Accounts', 'from' => 'accounts@exaact.test', 'user' => '', 'pass' => '']]);
t_eq($err, '', 'the platform e-mail is saved');
t_ok(platform_mail_ready(), 'and is ready');
$real = realpath($f); $app = realpath($root);
t_ok($real && strncmp($real, $app . DIRECTORY_SEPARATOR, strlen($app) + 1) !== 0 || !tenant_safe_data_dir()[1],
    'it is kept outside the application folder (in the protected data folder), never in a company database');
t_eq(substr(sprintf('%o', fileperms($f)), -3), '600', 'and only the server can read the file');

$gen = smtp_config('reminder');
t_eq($gen['from'] ?? '', 'noreply@exaact.test', 'a reminder goes from the general address');
$acc = smtp_config('password_reset');
t_eq($acc['from'] ?? '', 'accounts@exaact.test', 'a password reset goes from the separate account address');
t_eq($acc['user'] ?? '', 'noreply@exaact.test', 'with no login of its own, the account sender uses the general mailbox ("send as")');
foreach (['selfserve_code', 'saas_new_owner', 'portal_reset', 'pro_reset', 'join'] as $k)
    t_eq(smtp_config($k)['from'] ?? '', 'accounts@exaact.test', '"' . $k . '" is an account e-mail');

platform_mail_save(['host' => 'smtp.example.test', 'port' => 587,
    'general'  => ['name' => 'EXAACT', 'from' => 'noreply@exaact.test', 'user' => 'noreply@exaact.test', 'pass' => ''],
    'accounts' => ['name' => 'EXAACT Accounts', 'from' => 'accounts@exaact.test', 'user' => 'accounts@exaact.test', 'pass' => 'acct-secret']]);
$acc = smtp_config('password_reset');
t_ok(($acc['user'] ?? '') === 'accounts@exaact.test' && ($acc['pass'] ?? '') === 'acct-secret', 'the account sender can have its own mailbox login');
t_eq(smtp_config('reminder')['pass'] ?? '', 'gen-secret', 'a blank password on save keeps the one already stored');
t_ok(platform_mail_save(['host' => 'x', 'general' => ['from' => 'not-an-address']]) !== '', 'a wrong "from" address is refused');

// A company with its own e-mail settings uses its own.
setting_set('smtp_host', 'smtp.company.test'); setting_set('smtp_user', 'me@company.test'); setting_set('smtp_pass', 'x');
t_eq(smtp_config('reminder')['host'] ?? '', 'smtp.company.test', 'a company that set up its own e-mail sends through its own');
setting_set('smtp_host', '');

// Inside a hosted company: the reader sees the company, replies reach the company.
$regFile = $root . '/tenants.php'; $hadReg = is_file($regFile); $regBak = $hadReg ? file_get_contents($regFile) : null;
file_put_contents($regFile, "<?php return " . var_export(['base_domain' => 'ops.example.com', 'aliases' => [], 'tenants' => []], true) . ";\n");
$prev = $_SESSION['saas_tenant'] ?? null;
$res = saas_company_create(['company' => 'Mail Co ' . getmypid(), 'owner_email' => 'mail+' . getmypid() . '@mail.test', 'plan' => 'STARTER', 'source' => 'console']);
saas_enter_tenant($res['key']); saas_tenant_ensure_ready($res['key']);
setting_set('company_email', 'office@mailco.test');
$in = smtp_config('reminder');
t_ok(strpos((string) $in['from_name'], 'Mail Co') === 0 && strpos((string) $in['from_name'], 'via EXAACT') !== false, 'inside a company the sender name is "<company> (via EXAACT)"');
t_eq($in['reply_to'] ?? '', 'office@mailco.test', 'and replies go to the company\'s own e-mail');
t_ok(!superadmin_can(), 'a company\'s administrators can never open Platform setup (or see the platform mailbox)');
saas_leave_tenant();
if (function_exists('db_reset')) db_reset();
require $root . '/config.php';                       // back on the main site before removing the company
$pr = saas_company_purge($res['key'], 'test clean-up');
t_ok($pr['ok'], 'the test company is removed again');
if ($prev === null) { unset($_SESSION['saas_tenant']); } else { $_SESSION['saas_tenant'] = $prev; }
if ($hadReg && $regBak !== null) { file_put_contents($regFile, $regBak); } else { @unlink($regFile); }
require $root . '/config.php';

// ---- Payment keys check ---------------------------------------------------------
$keep = ['rzp_key_id' => (string) setting_get('rzp_key_id', ''), 'rzp_key_secret' => (string) setting_get('rzp_key_secret', '')];
setting_set('rzp_key_id', ''); setting_set('rzp_key_secret', '');
t_ok(!platform_payment_check()['ok'], 'no keys saved: the check says so');
t_ok(strpos(platform_mail_explain('SMTP: connect failed: php_network_getaddresses: getaddrinfo for x failed'), 'mail server name') !== false, 'an unknown mail server is explained in plain words');
t_ok(strpos(platform_mail_explain('SMTP expected 235, got 535 5.7.3 Authentication unsuccessful'), 'login or password') !== false, 'a refused login is explained in plain words');
setting_set('rzp_key_id', 'rzp_test_abc'); setting_set('rzp_key_secret', 's');
$GLOBALS['__rzp_transport'] = fn($m, $u) => [($m === 'GET' && strpos($u, '/v1/orders') !== false) ? 200 : 500, '{"items":[]}'];
$r = platform_payment_check();
t_ok($r['ok'] && $r['mode'] === 'test', 'working TEST keys are reported as working, in test mode');
$GLOBALS['__rzp_transport'] = fn() => [401, '{}'];
$r = platform_payment_check();
t_ok(!$r['ok'] && strpos($r['msg'], 'refused the keys') !== false, 'wrong keys are reported plainly');
unset($GLOBALS['__rzp_transport']);
foreach ($keep as $k => $v) setting_set($k, $v);

// ---- The checklist ----------------------------------------------------------------
$items = array_column(platform_checklist(), 'ok', 'key');
foreach (['cloud', 'mail', 'mail_test', 'mail_acct', 'pay', 'pay_live', 'prices', 'storage', 'trial', 'cron', 'signup'] as $k)
    t_ok(array_key_exists($k, $items), 'the checklist covers "' . $k . '"');
t_ok($items['mail'] === true && $items['mail_acct'] === true, 'with the platform e-mail saved, e-mail and the account sender read as ready');

@unlink($f);
if ($had && $bak !== null) { file_put_contents($f, $bak); @chmod($f, 0600); }
if (function_exists('opcache_invalidate')) @opcache_invalidate($f, true);
setting_set('smtp_host', $ownHost);
