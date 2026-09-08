<?php
// SaaS control plane — the cross-company directory that single-URL login and the
// super-admin console build on. Proves the table exists, a company can be added
// and updated, seats stack, the Books seat formula holds, and email -> company
// lookup works. All additive: a single-company install just never uses it.
t_section('SaaS control plane — company directory & per-seat math');

t_ok(function_exists('saas_tenants_migrate'), 'the control-plane migration is loaded');
t_ok(function_exists('saas_tenant_upsert'), 'the directory write helper exists');

// The boot chain created the tables.
$hasTable = true;
try { ops_val("SELECT COUNT(*) FROM saas_tenants"); } catch (Throwable $e) { $hasTable = false; }
t_ok($hasTable, 'the saas_tenants directory table is created by the boot chain');

// Add a company (as the console / provisioning would).
saas_tenant_upsert('asme', [
    'company' => 'Asme Pharmaceutical Private Limited',
    'owner_name' => 'Asme Admin', 'owner_email' => 'admin@asmehr.com',
    'plan' => 'RECRUITMENT', 'status' => 'active',
]);
$t = saas_tenant_get('asme');
t_ok($t !== null, 'the company is stored and read back');
t_eq((string) $t['company'], 'Asme Pharmaceutical Private Limited', 'the business name round-trips');
t_eq((string) $t['status'], 'active', 'a new company starts active');

// Recruitment plan grants the admin + hr modules and 3 base logins.
t_eq(saas_plan_logins('RECRUITMENT'), 3, 'the Recruitment plan includes 3 base logins');
saas_tenant_set_plan('asme', 'RECRUITMENT');
$mods = saas_tenant_modules('asme');
t_ok(in_array('hr', $mods, true) && in_array('admin', $mods, true), 'the plan grants only admin + hr (recruitment-only)');
t_ok(!in_array('sales', $mods, true), 'the plan does NOT grant sales');

// Seat math mirrors Books: base logins + purchased seats.
t_eq(saas_tenant_seat_limit('asme'), 3, 'with no extra seats the limit is the plan base (3)');
saas_tenant_add_seats('asme', 14);            // 3 base + 14 = 17 for Asme
t_eq(saas_tenant_seat_limit('asme'), 17, 'purchased seats add to the base (3 + 14 = 17)');
saas_tenant_add_seats('asme', 5);             // seats STACK across purchases
t_eq(saas_tenant_seat_limit('asme'), 22, 'a second purchase stacks (17 + 5 = 22)');
saas_tenant_add_seats('asme', -2);            // and can be reduced
t_eq(saas_tenant_seat_limit('asme'), 20, 'seats can be reduced (22 - 2 = 20)');

// Suspend / reactivate.
saas_tenant_set_status('asme', 'suspended');
t_eq((string) saas_tenant_get('asme')['status'], 'suspended', 'a company can be suspended');
saas_tenant_set_status('asme', 'active');
t_eq((string) saas_tenant_get('asme')['status'], 'active', 'and reactivated');

// Email -> company index for single-URL login.
saas_login_index_set('admin@asmehr.com', 'asme');
saas_login_index_set('recruiter1@asmehr.com', 'asme');
t_eq(saas_login_lookup('admin@asmehr.com'), 'asme', 'an email resolves to its company');
t_eq(saas_login_lookup('recruiter1@asmehr.com'), 'asme', 'a staff email resolves to the same company');
t_eq(saas_login_lookup('nobody@nowhere.com'), '', 'an unknown email resolves to nothing (no leak)');

// A partial update never wipes the other fields.
saas_tenant_upsert('asme', ['plan_expiry' => '2027-03-31']);
$t2 = saas_tenant_get('asme');
t_eq((string) $t2['company'], 'Asme Pharmaceutical Private Limited', 'a partial update leaves the company name intact');
t_eq((int) $t2['extra_user_seats'], 17, 'a partial update leaves purchased seats intact');

// The whole platform, not just recruitment: any plan / module mix is a company.
t_section('SaaS control plane — whole platform (all modules, not just recruitment)');
saas_tenant_upsert('acme-ops', ['company' => 'Acme Inspections', 'plan' => 'STARTER']);
saas_tenant_set_plan('acme-ops', 'STARTER');
t_ok(in_array('operations', saas_tenant_modules('acme-ops'), true), 'a STARTER company gets Operations');
t_ok(!in_array('hr', saas_tenant_modules('acme-ops'), true), 'a STARTER company does NOT get recruitment');

saas_tenant_upsert('acme-ent', ['company' => 'Acme Group', 'plan' => 'ENTERPRISE']);
saas_tenant_set_plan('acme-ent', 'ENTERPRISE');
$em = saas_tenant_modules('acme-ent');
foreach (['operations', 'sales', 'hr', 'money', 'reporting'] as $m)
    t_ok(in_array($m, $em, true), "an ENTERPRISE company gets $m");
t_eq(saas_tenant_seat_limit('acme-ent'), 9, 'ENTERPRISE includes 9 base logins');

// Switch helpers: choose / leave a company within a request (single-URL login).
t_section('SaaS control plane — choose a company at login (session switch)');
t_ok(function_exists('saas_enter_tenant') && function_exists('db_reset'), 'the login switch helpers exist');
saas_enter_tenant('demo-co');
t_eq(saas_current_tenant(), 'demo-co', 'entering a company records it for the request');
$stillWorks = true; try { ops_val("SELECT COUNT(*) FROM users"); } catch (Throwable $e) { $stillWorks = false; }
t_ok($stillWorks, 'the database still answers after a connection reset');
saas_leave_tenant();
t_eq(saas_current_tenant(), '', 'leaving clears the chosen company (back to the control DB)');

// Regression: leaving when NOT in a company must be a clean no-op — it must not
// reconnect (a second connection to the same store deadlocks SQLite).
saas_leave_tenant();   // already on the control DB
$afterLeave = true; try { ops_val("SELECT COUNT(*) FROM users"); } catch (Throwable $e) { $afterLeave = false; }
t_ok($afterLeave, 'leaving when already on the control DB is a safe no-op (no deadlock)');

// The Companies console helpers.
t_section('SaaS control plane — super-admin Companies console');
t_ok(function_exists('ops_saas_admin'), 'the Companies console handler exists');
$plans = saas_console_plans();
t_ok(isset($plans['RECRUITMENT']) && (int)$plans['RECRUITMENT']['base'] === 3, 'the console offers the Recruitment plan (3 base logins)');
t_ok(isset($plans['ENTERPRISE']) && count($plans['ENTERPRISE']['mods']) >= 5, 'the console offers Enterprise (all modules)');
$list = saas_console_companies();
$asme = null; foreach ($list as $r) if (($r['tenant_key'] ?? '') === 'asme') $asme = $r;
t_ok($asme !== null, 'the console lists a company from the directory');
t_ok(isset($asme['seat_limit']) && isset($asme['mods_list']), 'each listed company carries its seat limit and module list');

// Add-a-company helpers.
t_section('SaaS control plane — add a company (provisioning helpers)');
t_ok(function_exists('saas_slug'), 'the workspace-key slug helper exists');
t_eq(saas_slug('Delta Traders Pvt Ltd'), 'delta-traders-pvt-ltd', 'a company name becomes a clean workspace key');
t_eq(saas_slug('  Acme & Co.  '), 'acme-co', 'punctuation and spaces collapse to a single hyphen');
t_ok(is_file(dirname(__DIR__) . '/lib/saas_provision_cli.php'), 'the CLI provisioner script is shipped');

// saas_apply_plan_modules switches off exactly what the plan does not include.
// Applied to THIS test database, then restored so nothing leaks to later tests.
$origOff = function_exists('setting_get') ? (string) setting_get('modules_off', '') : '';
saas_apply_plan_modules('RECRUITMENT');
$off = (string) setting_get('modules_off', '');
t_ok(strpos($off, 'operations') !== false && strpos($off, 'sales') !== false && strpos($off, 'reporting') !== false,
    'the Recruitment plan switches off operations, sales and reporting');
t_ok(strpos($off, 'hr') === false, 'the Recruitment plan keeps People & hiring on');
saas_apply_plan_modules('PRO');
$off2 = (string) setting_get('modules_off', '');
t_ok(strpos($off2, 'reporting') !== false && strpos($off2, 'sales') === false,
    'the PRO plan keeps sales on and only switches off reporting');
setting_set('modules_off', $origOff);
if (function_exists('licence_disabled')) licence_disabled(true);   // restore for later tests

// Per-seat enforcement (Increment 4).
t_section('SaaS control plane — per-seat enforcement');
t_ok(function_exists('saas_seat_block'), 'the seat-cap check exists');
t_ok(function_exists('saas_push_to_tenant'), 'the push-to-live helper exists');
t_ok(is_file(dirname(__DIR__) . '/lib/saas_sync_cli.php'), 'the sync worker is shipped');
$origLimit = (string) setting_get('saas_seat_limit', '');
setting_set('saas_seat_limit', '0');
t_eq(saas_seat_block(), '', 'no seat limit set means unlimited (no block)');
$active = (int) ops_val("SELECT COUNT(*) FROM users WHERE is_active=1");
setting_set('saas_seat_limit', (string) ($active + 1));
t_eq(saas_seat_block(), '', 'below the limit a new login is allowed');
setting_set('saas_seat_limit', (string) max(1, $active));
t_ok(saas_seat_block() !== '', 'at the limit a new login is blocked');
t_ok(strpos(saas_seat_block(), 'seat') !== false, 'the block message tells them to buy seats');
setting_set('saas_seat_limit', $origLimit);   // MUST restore — a stray limit would block later tests' user creation

// À-la-carte pricing (plan builder).
t_section('SaaS control plane — à-la-carte pricing');
t_ok(function_exists('saas_price_book') && function_exists('saas_company_quote'), 'the price book and quote helpers exist');
$pb = saas_price_book();
t_ok(isset($pb['seat']['month']) && isset($pb['modules']['operations']), 'the price book has a seat price and per-module prices');
t_ok(!isset($pb['modules']['admin']), 'the core Administration module is never priced');
$q = saas_company_quote(['operations', 'sales'], 3, 'month');
$expect = $pb['modules']['operations']['month'] + $pb['modules']['sales']['month'] + 3 * $pb['seat']['month'];
t_eq($q['total'], $expect, 'a monthly quote = chosen modules + seats × seat price');
t_eq(count($q['lines']), 3, 'the quote has one line per module plus a seats line');
$qy = saas_company_quote(['hr'], 1, 'year');
t_eq($qy['total'], $pb['modules']['hr']['year'] + $pb['seat']['year'], 'a yearly quote uses the yearly prices');
$qEmpty = saas_company_quote([], 0, 'month');
t_eq($qEmpty['total'], 0, 'no modules and no seats costs nothing');

// Custom (à-la-carte) module apply — the push path respects the exact set bought.
t_ok(function_exists('saas_apply_modules_list'), 'the custom-modules apply helper exists');
$origOff2 = (string) setting_get('modules_off', '');
saas_apply_modules_list(['hr']);
$off3 = (string) setting_get('modules_off', '');
t_ok(strpos($off3, 'operations') !== false && strpos($off3, 'hr') === false, 'a custom list of just hr switches off everything except hr (+core admin)');
t_eq((string) setting_get('product_package', ''), 'CUSTOM', 'a custom module set marks the package CUSTOM');
setting_set('modules_off', $origOff2);
if (function_exists('licence_disabled')) licence_disabled(true);

// Self-onboarding — a freshly provisioned company completes its own company
// profile on first login. The provisioner flags it; the setup wizard fires even
// though the name was pre-filled; finishing setup clears the flag.
t_section('SaaS control plane — a new company self-onboards on first login');
t_ok(function_exists('setup_needed') && function_exists('setup_mark_done'), 'the setup-wizard helpers exist');
$origDone = (string) setting_get('setup_done', '');
$origOnb  = (string) setting_get('saas_onboarding_pending', '');
setting_set('setup_done', '');                       // as a fresh tenant would be
setting_set('saas_onboarding_pending', '1');         // as the provisioner stamps it
t_ok(setup_needed() === true, 'a provisioned company is sent through onboarding even with its name pre-filled');
setup_mark_done();                                   // owner finishes onboarding
t_eq((string) setting_get('setup_done', ''), '1', 'finishing onboarding marks setup done');
t_eq((string) setting_get('saas_onboarding_pending', ''), '', 'finishing onboarding clears the pending flag');
t_ok(setup_needed() === false, 'onboarding is not shown again once complete');
setting_set('setup_done', $origDone);                // restore for later tests
setting_set('saas_onboarding_pending', $origOnb);

// One-click database creation on a VPS — the console can build a client's own
// MySQL database. We prove the naming/derivation and the credential detection;
// the live CREATE DATABASE is exercised against the real server at rehearsal.
t_section('SaaS control plane — one-click database creation (VPS)');
t_ok(function_exists('saas_db_names_for') && function_exists('saas_mysql_provision_db'), 'the auto-create helpers exist');
t_eq(saas_db_ident('Acme & Co.'), 'acme_co', 'a company name becomes a safe MySQL identifier fragment');
t_eq(saas_db_ident('   '), 'co', 'an empty name still yields a safe fragment');
$n1 = saas_db_names_for('asme-pharma');
t_eq($n1['name'], 'asme_pharma', 'the database name is derived from the workspace key');
t_eq($n1['user'], 'asme_pharma', 'the database user is derived from the workspace key');
$n2 = saas_db_names_for('asme', 'acct');
t_eq($n2['name'], 'acct_asme', 'an account prefix is prepended to the database name');
$long = saas_db_names_for(str_repeat('workspace-', 6));   // very long key
t_ok(strlen($long['name']) <= 64, 'the database name is capped at 64 characters (MySQL limit)');
t_ok(strlen($long['user']) <= 32, 'the database user is capped at 32 characters (MySQL limit)');

// Credential detection: absent by default, present when configured (env fallback).
$origGlobal = $GLOBALS['SAAS_DB_ADMIN'] ?? null; unset($GLOBALS['SAAS_DB_ADMIN']);
foreach (['SAAS_DB_ADMIN_USER','SAAS_DB_ADMIN_HOST','SAAS_DB_ADMIN_PASS','SAAS_DB_ADMIN_PREFIX'] as $ev) putenv($ev);
t_ok(saas_db_admin_config() === null && saas_can_autocreate_db() === false, 'without a credential the server cannot auto-create (safe default: manual entry)');
putenv('SAAS_DB_ADMIN_USER=root'); putenv('SAAS_DB_ADMIN_HOST=localhost');
$adm = saas_db_admin_config();
t_ok(is_array($adm) && $adm['user'] === 'root' && $adm['host'] === 'localhost', 'a configured credential is read back');
t_ok(saas_can_autocreate_db() === true, 'with a credential the console offers one-click database creation');
foreach (['SAAS_DB_ADMIN_USER','SAAS_DB_ADMIN_HOST','SAAS_DB_ADMIN_PASS','SAAS_DB_ADMIN_PREFIX'] as $ev) putenv($ev);
if ($origGlobal !== null) $GLOBALS['SAAS_DB_ADMIN'] = $origGlobal;   // restore

// Customer self-service checkout — a company buys extra modules / seats itself,
// its workspace unlocks immediately, and what it paid for becomes a floor the
// provider's own pushes can never silently revoke. All mutates this test DB, so
// every touched setting is snapshotted and restored.
t_section('SaaS control plane — customer self-service subscription (à la carte)');
t_ok(function_exists('saas_selfservice_apply') && function_exists('saas_subscription'), 'the self-service helpers exist');
$snap = [];
foreach (['modules_off','saas_seat_limit','saas_seat_floor','saas_paid_modules','billing_paid_until','product_package'] as $k)
    $snap[$k] = (string) setting_get($k, '');

// Start the company as recruitment-only (admin + hr) with a 5-seat plan.
saas_apply_modules_list(['hr']);
setting_set('saas_paid_modules', '');            // no prior self-service
setting_set('saas_seat_floor', '');
setting_set('saas_seat_limit', '5');
$before = saas_subscription();
t_ok(in_array('hr', $before['modules'], true) && !in_array('sales', $before['modules'], true), 'before: the company has hr but not sales');
t_ok(in_array('sales', $before['addable'], true), 'sales is offered as an addable module');
t_eq((int) $before['seat_limit'], 5, 'before: the plan covers 5 seats');

// Buy the Sales module + 2 more seats, monthly.
$r = saas_selfservice_apply(['sales'], 2, 'month', 'pay_TEST', 'order_TEST');
$after = saas_subscription();
t_ok(in_array('sales', $after['modules'], true), 'after paying, Sales is switched on immediately');
t_ok(in_array('hr', $after['modules'], true), 'the module it already had stays on');
t_eq((int) $after['seat_limit'], 7, 'the seat cap rises by the seats bought (5 + 2 = 7)');
t_eq(saas_paid_seat_floor(), 7, 'the paid seats become a floor (7)');
t_ok(in_array('sales', saas_paid_modules(), true), 'Sales is recorded as a paid module (a floor)');
t_ok($after['paid_until'] >= date('Y-m-d'), 'the subscription is now active into the future');

// The floor holds against a provider push: applying the RECRUITMENT preset must
// NOT revoke the paid Sales module, and must not drop the paid seat floor.
saas_apply_plan_modules('RECRUITMENT');
$off = (string) setting_get('modules_off', '');
t_ok(strpos($off, 'sales') === false, 'a later provider push does NOT revoke the customer-paid Sales module');
t_ok(strpos($off, 'operations') !== false, 'the push still switches off what was never bought (operations)');
t_eq(saas_paid_seat_floor(), 7, 'the paid seat floor survives a provider push');

// An à-la-carte provider push also respects the floor.
saas_apply_modules_list(['hr']);
t_ok(strpos((string) setting_get('modules_off', ''), 'sales') === false, 'even a bare hr-only push keeps the paid Sales module on');

// Razorpay signature verification: a correct signature passes, a forged one fails.
$origSecret = (string) setting_get('rzp_key_secret', '');
setting_set('rzp_key_secret', 'test_secret_key');
$good = hash_hmac('sha256', 'order_1|pay_1', 'test_secret_key');
t_ok(rzp_verify_signature('order_1', 'pay_1', $good) === true, 'a genuine Razorpay signature verifies');
t_ok(rzp_verify_signature('order_1', 'pay_1', 'deadbeef') === false, 'a forged signature is rejected (no unpaid unlock)');
setting_set('rzp_key_secret', $origSecret);

// The purchase is recorded in the billing ledger with the module note.
$hist = billing_history(5);
$found = false; foreach ($hist as $h) if (strpos((string) ($h['note'] ?? ''), 'sales') !== false) $found = true;
t_ok($found, 'the self-service purchase is written to the billing history with what was bought');

foreach ($snap as $k => $v) setting_set($k, $v);   // restore every touched setting
if (function_exists('licence_disabled')) licence_disabled(true);
