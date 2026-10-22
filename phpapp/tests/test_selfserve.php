<?php
// ============================================================================
//  Self-serve companies — the owner's rules of 22 Oct 2026 (lib/selfserve.php)
//
//   • a company signs itself up, proves its e-mail with a code, and is created
//     at once on a 30-day trial of every module, keeping the password it chose;
//   • after the trial (or a paid period) it is VIEW-ONLY: reading works, saving
//     is refused except the screens that let it pay;
//   • a trial has a storage limit; new files are refused when it is full;
//   • plan changes follow the owner's Q-L rule (upgrade now with credit,
//     renew and downgrade at period end);
//   • a payment is applied from the server's own record, once;
//   • a never-paid trial nobody uses is warned every 15 days and deleted at 90;
//     a company that has ever paid is never deleted.
// ============================================================================

t_section('Self-serve companies — sign up, trial, view-only, plan, inactivity');

$root    = dirname(__DIR__);
$regFile = $root . '/tenants.php';
$hadReg  = is_file($regFile);
$regBak  = $hadReg ? file_get_contents($regFile) : null;
$prevSess = $_SESSION['saas_tenant'] ?? null;
file_put_contents($regFile, "<?php return " . var_export(['base_domain' => 'ops.example.com', 'aliases' => [], 'tenants' => []], true) . ";\n");

selfserve_migrate();
setting_set('selfserve_signup_enabled', '1');
foreach (['saas_trial_days', 'saas_trial_storage_mb', 'saas_trial_seats', 'saas_inactive_notice_days',
          'saas_inactive_delete_days', 'saas_autodelete_enabled'] as $k) setting_set($k, '');

$cfg = selfserve_cfg();
t_eq($cfg['trial_days'], 30, 'the trial is 30 days by default (owner, 22 Oct)');
t_eq($cfg['notice_days'], 15, 'unused trials are warned every 15 days by default');
t_eq($cfg['delete_days'], 90, 'unused trials are deleted after 90 days by default');
t_eq($cfg['storage_mb'], 0, 'no storage number is invented — 0 (no limit) until the platform owner sets one');
t_ok(selfserve_ready(), 'self-serve is ready when cloud mode has a base domain');

$__mails = [];
$GLOBALS['__mail_capture'] = function ($to, $subject, $body, $kind) use (&$__mails) { $__mails[] = compact('to', 'subject', 'body', 'kind'); };
$lastMail = function ($to, $kind) use (&$__mails) {
    foreach (array_reverse($__mails) as $m) if ($m['to'] === $to && $m['kind'] === $kind) return $m;
    return null;
};
$codeFor = function ($email) use ($lastMail) {
    $r = $lastMail($email, 'selfserve_code');
    return ($r && preg_match('/^\s+(\d{6})\s*$/m', (string) $r['body'], $m)) ? $m[1] : '';
};
$_SERVER['REMOTE_ADDR'] = '203.0.113.' . (getmypid() % 200);
$good = ['company' => 'Selfserve Test Co', 'owner_name' => 'Asha Owner', 'email' => 'owner+' . getmypid() . '@selfserve.test',
         'phone' => '+91 98765 43210', 'password' => 'Trial-Pass-2026!x', 'agree' => '1'];

// ---- Step 1: validation -----------------------------------------------------
[$ok, $m] = selfserve_start(['company' => ''] + $good);
t_ok(!$ok && strpos($m, 'company name') !== false, 'an empty company name is refused with a sentence');
[$ok, $m] = selfserve_start(['email' => 'abc'] + $good);
t_ok(!$ok && strpos($m, 'e-mail') !== false, 'a bad e-mail is refused');
[$ok, $m] = selfserve_start(['password' => '1234'] + $good);
t_ok(!$ok, 'a weak password is refused');
$noAgree = $good; unset($noAgree['agree']);
[$ok, $m] = selfserve_start($noAgree);
t_ok(!$ok && strpos($m, 'agree') !== false, 'the trial terms must be accepted');
[$ok, $m, $id] = selfserve_start(['website' => 'http://spam'] + $good);
t_ok($ok && $id === 0, 'a robot filling the hidden field is answered like success but nothing is created');
saas_login_index_set('taken@selfserve.test', 'someone-else');
[$ok, $m] = selfserve_start(['email' => 'taken@selfserve.test'] + $good);
t_ok(!$ok && strpos($m, 'already has a workspace') !== false, 'an e-mail that already has a workspace is sent to sign in instead');

// ---- Step 2: the code ---------------------------------------------------------
[$ok, $m, $sid] = selfserve_start($good);
t_ok($ok && $sid > 0, 'good details create a pending sign-up and e-mail a code');
$code = $codeFor($good['email']);
t_ok(strlen($code) === 6, 'the e-mail carries a 6-digit code');
$row = ops_one("SELECT * FROM selfserve_signups WHERE id=?", [$sid]);
t_ok($row['code_hash'] !== $code && $row['pass_hash'] !== $good['password'], 'neither the code nor the password is stored in plain text');

[$ok, $m] = selfserve_verify($sid, '000000' === $code ? '111111' : '000000');
t_ok(!$ok && strpos($m, 'not right') !== false, 'a wrong code is refused and says how many tries are left');
db()->prepare("UPDATE selfserve_signups SET code_expires=? WHERE id=?")->execute([date('c', time() - 60), $sid]);
[$ok, $m] = selfserve_verify($sid, $code);
t_ok(!$ok && strpos($m, 'run out') !== false, 'an expired code is refused');
[$ok, $m] = selfserve_resend($sid);
$code = $codeFor($good['email']);
t_ok($ok && strlen($code) === 6, '"Send a new code" e-mails a fresh code');

[$ok, $m, $key] = selfserve_verify($sid, $code);
t_ok($ok && $key !== '', 'the right code creates the company at once — no approval wait');
[$ok2] = selfserve_verify($sid, $code);
t_ok(!$ok2, 'using the same code again creates nothing more');
$t = saas_tenant_get($key);
t_eq($t['source'] ?? '', 'self', 'the company is recorded as having signed itself up');
t_eq($t['plan'] ?? '', 'TRIAL', 'it starts on the free trial');
t_eq($t['trial_until'] ?? '', date('Y-m-d', strtotime('+30 days')), 'its trial ends 30 days from today');
t_eq(saas_login_lookup($good['email']), $key, 'the owner can sign in with their e-mail at the one product address');
$done = ops_one("SELECT status, pass_hash, code_hash FROM selfserve_signups WHERE id=?", [$sid]);
t_ok($done['status'] === 'DONE' && $done['pass_hash'] === '' && $done['code_hash'] === '', 'the finished sign-up keeps no password or code');
$welcome = $lastMail($good['email'], 'selfserve_welcome');
t_ok(strpos((string) ops_val("SELECT body FROM email_log WHERE to_addr=? AND kind='selfserve_code' ORDER BY id DESC LIMIT 1", [$good['email']]), 'not kept') !== false,
    'the sign-up code is never kept in the e-mail log');
t_ok($welcome && strpos($welcome['body'], 'view-only') !== false && strpos($welcome['body'], '90 days') !== false,
    'a welcome e-mail explains the trial, view-only after it, and the 90-day rule');

// Rate limit: a connection that keeps starting sign-ups is slowed down.
db()->prepare("DELETE FROM selfserve_signups WHERE ip=?")->execute([$_SERVER['REMOTE_ADDR']]);
for ($i = 0; $i < SELFSERVE_STARTS_PER_HOUR; $i++) selfserve_start(['email' => "rl$i+" . getmypid() . '@selfserve.test'] + $good);
[$ok, $m] = selfserve_start(['email' => 'rl-last+' . getmypid() . '@selfserve.test'] + $good);
t_ok(!$ok && strpos($m, 'Too many') !== false, 'more than ' . SELFSERVE_STARTS_PER_HOUR . ' sign-ups an hour from one connection are refused');

// ---- Inside the new company ----------------------------------------------------
$sell = selfserve_sell_settings();
saas_enter_tenant($key);
$ready = saas_tenant_ensure_ready($key);
t_ok($ready, 'the new workspace builds itself on first entry');
$owner = ops_one("SELECT * FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1");
t_eq((string) ($owner['email'] ?? ''), $good['email'], 'the owner is the person who signed up');
t_eq((int) ($owner['must_change_pwd'] ?? 9), 0, 'they are NOT forced to change the password they just chose');
t_ok(password_verify($good['password'], (string) $owner['password_hash']), 'their own password works');
t_eq((string) setting_get('saas_billing_mode', ''), 'self', 'the workspace knows it is self-serve');
t_eq(count(saas_current_modules()), count(selfserve_all_modules()), 'the trial has every module switched on');

$today = date('Y-m-d');
$st = saas_access_state();
t_eq($st['mode'], 'trial', 'during the trial the workspace is on the trial');
t_eq($st['days_left'], 30, 'and 30 days are left');
t_ok(!saas_blocks_write('quote-save'), 'saving works during the trial');
$after = date('Y-m-d', strtotime('+31 days'));
t_eq(saas_access_state($after)['mode'], 'locked', 'the day after the trial ends the workspace is view-only');
setting_set('saas_trial_until', date('Y-m-d', strtotime('-1 day')));
t_ok(saas_blocks_write('quote-save') && saas_blocks_write('lead-save'), 'view-only: saving a record is refused');
t_ok(!saas_blocks_write('subscription-order') && !saas_blocks_write('subscription-verify') && !saas_blocks_write('logout'),
    'view-only: choosing a plan, paying and signing out still work');
$bn = saas_access_banner();
t_ok($bn && $bn['tone'] === 'error' && strpos($bn['text'], 'view-only') !== false, 'every screen says the workspace is view-only');
setting_set('saas_trial_until', $today);

// Storage limit during the trial.
setting_set('saas_storage_limit_mb', '1');
setting_set('saas_storage_cache', time() . '|' . (2 * 1048576));
t_ok(saas_storage_full(), 'a trial over its storage limit is full');
$_FILES = ['f' => ['name' => 'a.pdf', 'size' => 10]];
t_ok(saas_request_has_files(), 'an upload is recognised so it can be refused');
$_FILES = ['f' => ['name' => '', 'size' => 0]];
t_ok(!saas_request_has_files(), 'a form with an empty file box is not an upload');
$_FILES = [];
setting_set('saas_storage_limit_mb', '0');
t_ok(!saas_storage_full(), 'with no limit set, storage is never full');

// ---- Choosing a plan (owner's Q-L rule) --------------------------------------------
foreach ($sell as $sk => $sv) setting_set($sk, $sv);   // the platform owner's prices
t_set_prices();                                          // (the app has none built in)
$mods = array_slice(selfserve_all_modules(), 0, 2);
$q = saas_plan_change_quote([], 2, 'month');
t_ok(!$q['ok'], 'a plan with no module is refused');
$q = saas_plan_change_quote($mods, 2, 'month', $today);
t_ok($q['ok'] && $q['type'] === 'NEW' && $q['price'] === $q['list'] && $q['price'] > 0, 'from the trial, a plan is NEW and costs its list price');
t_eq(saas_order_claim('order_never_made', 'pay_x'), null, 'a payment for an order this workspace never made applies nothing');
saas_order_create('order_' . getmypid(), 'plan', $q['mods'], 2, 'month', $q['price'], $q);
$o = saas_order_claim('order_' . getmypid(), 'pay_1');
t_ok($o && $o['kind'] === 'plan', 'a recorded order is claimed once for its payment');
t_eq(saas_order_claim('order_' . getmypid(), 'pay_1'), null, 'a replayed payment callback applies nothing a second time');
$applied = saas_plan_change_apply(json_decode($o['quote_json'], true), 'pay_1', 'order_' . getmypid());
t_ok(!empty($applied['converted']) && (string) setting_get('saas_converted_at', '') !== '', 'the first paid plan turns the free trial into a paid company (recorded once)');
$st = saas_access_state();
t_eq($st['mode'], 'paid', 'after paying, the workspace is on its paid plan');
sort($mods);
$on = saas_current_modules(); sort($on);
t_eq($on, $mods, 'exactly the chosen modules are on; the rest are switched off');
t_eq((int) setting_get('saas_seat_limit', 0), 2, 'and exactly the chosen number of logins');

$q = saas_plan_change_quote($mods, 2, 'month', $today);
t_eq($q['type'], 'RENEW', 'the same plan again is a renewal');
t_eq($q['starts'], date('Y-m-d', strtotime($applied['expires'] . ' +1 day')), 'a renewal starts the day after the current plan ends');
$more = array_slice(selfserve_all_modules(), 0, 3);
$q = saas_plan_change_quote($more, 2, 'month', $today);
t_ok($q['type'] === 'UPGRADE' && $q['credit'] > 0 && $q['price'] < $q['list'], 'adding a module is an upgrade that starts today, with credit for unused days');
$q = saas_plan_change_quote(array_slice($mods, 0, 1), 1, 'month', $today);
t_ok($q['type'] === 'DOWNGRADE' && $q['price'] === 0, 'a smaller plan is a downgrade: nothing is charged now');
saas_plan_change_apply($q);
$on2 = saas_current_modules(); sort($on2);
t_eq($on2, $mods, 'a downgrade does not take anything away before the paid period ends');
t_ok(saas_plan_next() !== null, 'the downgrade is booked for the end of the period');
saas_apply_due_change(date('Y-m-d', strtotime($applied['expires'] . ' +1 day')));
t_ok(saas_plan_next() === null && json_decode((string) setting_get('saas_offer_plan', ''), true), 'when its day comes, the booked smaller plan is offered to be paid');

saas_leave_tenant();
if (function_exists('db_reset')) db_reset();
require $root . '/config.php';

// ---- The nightly look at every company -----------------------------------------------
$mails = [];
$mailer = function ($to, $s, $b) use (&$mails) { $mails[] = [$to, $s, $b]; return true; };
$facts = function ($last, $paid = '') { return function () use ($last, $paid) {
    return ['ok' => true, 'last_seen' => $last, 'paid_until' => $paid, 'trial_until' => '', 'modules' => [], 'seat_limit' => 0]; }; };

// A separate never-paid trial company that nobody uses.
$k2 = 'idle-' . getmypid();
saas_tenant_upsert($k2, ['company' => 'Idle Trial Co', 'owner_email' => 'idle@selfserve.test', 'plan' => 'TRIAL', 'status' => 'active',
                         'source' => 'self', 'created_at' => date('c', strtotime('-200 days'))]);
$day = fn($n) => date('Y-m-d', strtotime($today . ' +' . $n . ' days'));
$last = date('c', strtotime($today));
$only = function ($k) use (&$facts, $last) { return $facts($last); };

$run = function ($n, $reader = null) use ($mailer, $day, $facts, $last) {
    return selfserve_sweep(['today' => $day($n), 'reader' => $reader ?: $facts($last), 'mailer' => $mailer]);
};
saas_tenant_upsert($k2, ['last_active_at' => $last, 'inactive_notices' => 0, 'last_notice_at' => '']);
$r = $run(10);
t_eq(count(array_filter($mails, fn($m) => $m[0] === 'idle@selfserve.test')), 0, 'no warning before 15 days unused');
$r = $run(15);
$t2 = saas_tenant_get($k2);
t_eq((int) $t2['inactive_notices'], 1, 'after 15 days unused, the first warning is e-mailed to the company');
$warn = array_values(array_filter($mails, fn($m) => $m[0] === 'idle@selfserve.test'));
t_ok($warn && strpos($warn[0][2], 'will be deleted on') !== false, 'the warning names the day it will be deleted');
$run(20);
t_eq((int) saas_tenant_get($k2)['inactive_notices'], 1, 'no second warning before the next 15 days');
$run(30);
t_eq((int) saas_tenant_get($k2)['inactive_notices'], 2, 'after 30 days, the second warning');

// Used again → the count starts over.
$newer = date('c', strtotime($day(31)));
selfserve_sweep(['today' => $day(31), 'reader' => $facts($newer), 'mailer' => $mailer]);
t_eq((int) saas_tenant_get($k2)['inactive_notices'], 0, 'someone signing in again starts the warnings over');
saas_tenant_upsert($k2, ['last_active_at' => $last]);

// A company that has ever paid is never warned nor deleted.
$k3 = 'paid-' . getmypid();
saas_tenant_upsert($k3, ['company' => 'Paid Co', 'owner_email' => 'paid@selfserve.test', 'plan' => 'TRIAL', 'status' => 'active',
                         'source' => 'self', 'created_at' => date('c', strtotime('-400 days')), 'last_active_at' => $last]);
$mixed = function ($k) use ($facts, $last) { return $k === 'paid-' . getmypid() ? $facts($last, date('Y-m-d', strtotime('-200 days')))() : $facts($last)(); };
$mails = [];
selfserve_sweep(['today' => $day(120), 'reader' => $mixed, 'mailer' => $mailer]);
t_ok(saas_tenant_get($k3) !== null && !array_filter($mails, fn($m) => $m[0] === 'paid@selfserve.test'),
    'a company that has ever paid is never warned or deleted, however long unused');

// Deletion switched off → never deleted.
setting_set('saas_autodelete_enabled', '0');
saas_tenant_upsert($k2, ['inactive_notices' => 5, 'last_notice_at' => $day(75)]);
$run(95);
t_ok(saas_tenant_get($k2) !== null, 'with automatic deletion switched off, nothing is deleted');
setting_set('saas_autodelete_enabled', '1');

// Never warned and already past 90 days → warned now, deletion waits a full interval.
saas_tenant_upsert($k2, ['inactive_notices' => 0, 'last_notice_at' => '']);
$run(95);
t_ok(saas_tenant_get($k2) !== null && (int) saas_tenant_get($k2)['inactive_notices'] === 1,
    'a company never warned is warned first, never deleted on the spot');
$run(100);
t_ok(saas_tenant_get($k2) !== null, 'and not deleted until a full 15 days after that warning');
$mails = [];
$r = $run(110);
t_ok(saas_tenant_get($k2) === null, 'after 90 days unused and a warning 15 days old, the trial company is deleted');
t_ok((bool) ops_one("SELECT id FROM saas_deletions WHERE tenant_key=?", [$k2]), 'the deletion is kept on record for the platform owner');
t_ok((bool) array_filter($mails, fn($m) => $m[0] === 'idle@selfserve.test' && strpos($m[1], 'has been deleted') !== false),
    'the company is told it has been deleted');

// The real company from the sign-up: deleting it removes its data file too.
$route = tenant_registry()['tenants'][$key] ?? [];
$file = (string) ($route['sqlite'] ?? '');
$pr = saas_company_purge($key, 'test clean-up');
t_ok($pr['ok'] && saas_tenant_get($key) === null && saas_login_lookup($good['email']) === '', 'purging removes the company, its routing and its sign-in');
t_ok($file === '' || !is_file($file), 'and its data file');

// ---- The console builder: a company created by hand still gets a temporary password
$res = saas_company_create(['company' => 'Console Co ' . getmypid(), 'owner_email' => 'console+' . getmypid() . '@selfserve.test',
    'owner_name' => 'C Owner', 'plan' => 'STARTER', 'source' => 'console']);
t_ok($res['ok'] && strlen($res['temp_pass']) >= 8, 'a console-created company gets a temporary password to hand over');
$p = tenant_pending($res['key']);
t_ok(is_array($p) && empty($p['own_password']), 'and its owner must change it on first sign-in');
saas_company_purge($res['key'], 'test clean-up');

// ---- Clean up --------------------------------------------------------------------
saas_tenant_delete($k3);
db()->prepare("DELETE FROM saas_logins WHERE email='taken@selfserve.test'")->execute();
setting_set('selfserve_signup_enabled', '0');
unset($GLOBALS['__mail_capture']);
if ($prevSess === null) { unset($_SESSION['saas_tenant']); } else { $_SESSION['saas_tenant'] = $prevSess; }
if (function_exists('db_reset')) db_reset();
if ($hadReg && $regBak !== null) { file_put_contents($regFile, $regBak); } else { @unlink($regFile); }
require $root . '/config.php';
