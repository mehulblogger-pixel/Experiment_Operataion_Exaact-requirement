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
