<?php
// Owner, 8 Nov 2026: Super Admin → Companies showed a company as "2 bought",
// its Plan box as "Starter" and its date as 23 Oct 2026 — while the company's
// own workspace held 15 logins (all in use) and was paid until Oct 2027.
//  • the console's copy is refreshed only by the nightly job, so it was days old;
//  • "Save plan" would have switched the company to Starter's modules and ended
//    its paid period, because "own choice" was not in the list and the old date
//    was pre-filled;
//  • on hosts without exec() (the in-process push), "Save seats"/"Save plan"
//    pushed the stale number and could cut logins the company paid for.
t_section('Super Admin sees and keeps a self-serve company\'s real logins');

$root    = dirname(__DIR__);
$regFile = $root . '/tenants.php';
$hadReg  = is_file($regFile);
$regBak  = $hadReg ? file_get_contents($regFile) : null;
$prevSess = $_SESSION['saas_tenant'] ?? null;
file_put_contents($regFile, "<?php return " . var_export(['base_domain' => 'ops.example.com', 'aliases' => [], 'tenants' => []], true) . ";\n");
selfserve_migrate();

$res = saas_company_create(['company' => 'Seats Live Co ' . getmypid(), 'owner_email' => 'seats+' . getmypid() . '@selfserve.test',
    'owner_name' => 'S Owner', 'plan' => 'TRIAL', 'source' => 'self']);
t_ok(!empty($res['ok']), 'a self-serve company is created for the check');
$key = (string) ($res['key'] ?? '');
saas_tenant_upsert($key, ['source' => 'self']);

// Inside the company: it chose its own plan — 15 logins, paid a year ahead.
$paidUntil = date('Y-m-d', strtotime('+1 year'));
$mods = ['operations', 'sales'];
saas_enter_tenant($key);
saas_tenant_ensure_ready($key);
saas_plan_switch_to($mods, 15);
setting_set('billing_paid_until', $paidUntil);
$used = (int) ops_val("SELECT COUNT(*) FROM users WHERE COALESCE(is_active,1)=1");
saas_leave_tenant();
if (function_exists('db_reset')) db_reset();

// The console's copy is out of date: "2 bought", an old date.
saas_tenant_upsert($key, ['plan' => 'CUSTOM', 'extra_user_seats' => 2, 'paid_until' => date('Y-m-d', strtotime('-30 days')),
    'plan_expiry' => date('Y-m-d', strtotime('-16 days')), 'enabled_modules' => json_encode(array_merge(['admin'], $mods))]);
t_eq(saas_tenant_seat_limit($key), 2, 'set-up: the console believes 2 logins');

// 1) Opening the company refreshes it from its workspace.
$sw = selfserve_sweep(['dry' => true, 'only' => $key]);
t_eq((int) ($sw['checked'] ?? 0), 1, 'the one-company refresh opens only that company');
t_eq((int) ($sw['facts'][$key]['seat_limit'] ?? 0), 15, 'it reads the 15 logins the company really has');
t_eq((int) ($sw['facts'][$key]['seats_used'] ?? -1), $used, 'and how many are in use');
$t = saas_tenant_get($key);
t_eq(saas_tenant_seat_limit($key), 15, 'the console now shows 15, not 2');
t_eq((string) ($t['paid_until'] ?? ''), $paidUntil, 'and the date it is really paid until');

// 2) A stale number can never cut what the company paid for (in-process push).
saas_tenant_upsert($key, ['extra_user_seats' => 2]);
t_ok(saas_push_to_tenant_inproc($key), 'the in-process push runs');
saas_enter_tenant($key);
$lim = (int) setting_get('saas_seat_limit', 0);
saas_leave_tenant(); if (function_exists('db_reset')) db_reset();
t_eq($lim, 15, 'pushing 2 leaves the company its 15 paid logins');

// 3) The platform owner can raise it.
saas_tenant_upsert($key, ['extra_user_seats' => 20]);
saas_push_to_tenant_inproc($key);
saas_enter_tenant($key);
$lim = (int) setting_get('saas_seat_limit', 0);
$block = saas_seat_block();
saas_leave_tenant(); if (function_exists('db_reset')) db_reset();
t_eq($lim, 20, 'setting 20 on the console gives the company 20 logins');
t_eq($block, '', 'and it can add a login again');

// 4) Keeping "own choice" and saving a date keeps its modules.
saas_tenant_set_plan($key, 'CUSTOM', $paidUntil);
$after = saas_tenant_modules($key);
t_ok(in_array('operations', $after, true) && in_array('sales', $after, true), 'saving its date keeps the modules it bought');
t_eq((string) (saas_tenant_get($key)['plan'] ?? ''), 'CUSTOM', 'and it stays on its own choice');

// 5) The screen shows it as it is.
$h = (string) file_get_contents($root . '/lib/saas_tenants.php');
$i = strpos($h, "if (\$do === 'company_plan'");
t_ok($i !== false && strpos(substr($h, $i, 900), 'Choose a plan from the list. Nothing was saved.') !== false,
     'a plan that is neither on the list nor the current one is refused');
$plans = saas_console_plans(); $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$sel = saas_tenant_get($key); $sel['live_seats_used'] = 20; $sel['live_seat_limit'] = 20;
$v = (string) file_get_contents($root . '/views/ops/saas_companies.php');
$a = strpos($v, '<form method="post">' . "\n    <input type=\"hidden\" name=\"do\" value=\"company_plan\">");
$b = strpos($v, 'name="do" value="company_storage"');
t_ok($a !== false && $b !== false, 'the plan and seats forms are found');
ob_start();
try { eval('?>' . substr($v, $a, strrpos(substr($v, 0, $b), '<form method="post">') - $a)); } catch (Throwable $ex) { echo 'THREW ' . $ex->getMessage(); }
$html = ob_get_clean();
t_ok(strpos($html, 'THREW') === false, 'the forms render');
t_ok(preg_match('/<option value="CUSTOM" selected>Own choice/', $html) === 1, 'the Plan box shows "Own choice", selected');
t_ok(preg_match('/<option value="STARTER"[^>]*selected/', $html) === 0, 'and never pre-selects Starter');
t_ok(strpos($html, 'value="' . $paidUntil . '"') !== false, 'the date shown is the one it is really paid until');
t_ok(strpos($html, 'Logins this company has (total)') !== false && strpos($html, 'In use now: 20 of 20 logins') !== false,
     'the seats box says the total and how many are in use');

// 6) A payment on the old per-person screen never becomes a second login cap.
saas_enter_tenant($key);
$prevBs = setting_get('billing_seats', ''); $prevBp = setting_get('billing_paid_until', '');
setting_set('billing_seats', '1');                              // an old "1 seat paid" left behind
setting_set('billing_paid_until', date('Y-m-d', strtotime('+1 year')));
if (function_exists('lk_state')) lk_state(true);
$g = billing_grant();
$lkBlock = function_exists('lk_seat_block') ? lk_seat_block() : '';
$saasBlock = saas_seat_block();
// Owner, 8 Nov: after paying on the old screen every module vanished — the
// grant turned the licence state from "no key" into a key that lists no modules.
licence_disabled(true);
$opsOn = licence_enabled('operations'); $salesOn = licence_enabled('sales');
setting_set('billing_seats', (string) $prevBs);
saas_leave_tenant(); if (function_exists('db_reset')) db_reset();
if (function_exists('lk_state')) lk_state(true);
t_ok(empty($g['active']), 'inside a company the old per-person grant is not in force');
t_eq($lkBlock, '', 'so it cannot stop a new login');
t_eq($saasBlock, '', 'the plan (20 logins) is what decides');
t_ok($opsOn && $salesOn, 'and the modules the company bought stay switched on (an old per-person payment had switched them all off)');

// Clean up.
saas_company_purge($key, 'test clean-up');
if ($hadReg) file_put_contents($regFile, $regBak); else @unlink($regFile);
if ($prevSess === null) unset($_SESSION['saas_tenant']); else $_SESSION['saas_tenant'] = $prevSess;
if (function_exists('db_reset')) db_reset();

t_section('inside a company, logins are bought on "Your subscription" only');
$b = (string) file_get_contents($root . '/lib/billing.php');
$i = strpos($b, 'function ops_billing(');
$blk = substr($b, $i, 1400);
t_ok(strpos($blk, "current_tenant() !== '' && \$route !== 'billing-verify'") !== false && strpos($blk, "redirect('/subscription')") !== false,
     'the old per-person seat screen hands a company over to its plan');
t_ok(strpos($blk, "'billing-verify'") !== false, 'a payment already under way still finishes');
$s = (string) file_get_contents($root . '/views/ops/settings.php');
t_ok(strpos($s, "\$setTile('/subscription', '💳', 'Your subscription'") !== false, 'Settings shows "Your subscription" inside a company');
$p = (string) file_get_contents($root . '/lib/saas_tenants.php');
$j = strpos($p, 'function saas_push_to_tenant_inproc(');
$k = strpos($p, 'saas_enter_tenant($key);', $j);
$l = strpos($p, '$limit = saas_tenant_seat_limit($key);', $j);
t_ok($l !== false && $l < $k, 'the push reads the console\'s numbers before it switches into the company');
$lc = (string) file_get_contents($root . '/lib/licencekey.php');
$i = strpos($lc, 'function ops_licence(');
t_ok(strpos(substr($lc, $i, 1200), "redirect('/subscription')") !== false, 'the licence-key screen is not shown inside a company; it goes to Your subscription');
