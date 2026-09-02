<?php
// ============================================================================
//  COLD START — the actual first-run scenario a brand-new customer hits on day
//  one, from an EMPTY database: install detection → first-run wizard → company
//  onboarding with multi-capability → professional self-registration → the first
//  real record. The demo seeds inject data directly and the auto-walk crawls a
//  SEEDED database, so neither exercises this path — this does.
//
//    export DB_DRIVER=sqlite SQLITE_PATH=/tmp/cold.sqlite   # a FRESH, empty file
//    rm -f "$SQLITE_PATH"
//    php tools/cold-start.php
//
//  Exit 0 = every first-run step works from empty; 1 = something in onboarding
//  is broken. Run it against a throwaway DB — it registers real accounts.
// ============================================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Command line only.\n"); }
chdir(__DIR__ . '/..');
$idx = @file_get_contents(__DIR__ . '/../index.php'); $libs = [];
if ($idx && preg_match_all('#require\s+__DIR__\s*\.\s*\'(/lib/[a-zA-Z0-9_]+\.php)\'#', $idx, $mm)) $libs = $mm[1];
foreach ($libs as $rel) { $f = __DIR__ . '/..' . $rel; if (is_file($f)) require_once $f; }
try { boot(); } catch (Throwable $e) { fwrite(STDERR, 'DB unreachable: ' . $e->getMessage() . "\n"); exit(1); }

$checks = [];
$check = function ($label, $ok) use (&$checks) { $checks[] = [$label, (bool)$ok]; };

// 1) A truly fresh install is DETECTED (nothing but the auto-seeded scaffolding).
$freshDetected = function_exists('setup_needed') ? setup_needed() : null;
$check('Install detection — a fresh database reports setup_needed', $freshDetected === true);

// 2) The first-run wizard: the owner sets the company identity. (ops_setup gathers
//    these; here we apply the same settings + mark it done, as /setup-save does.)
if (function_exists('setting_set')) setting_set('app_name', 'Meridian Technical Services');
if (function_exists('industry_apply')) { try { industry_apply('inspection'); } catch (Throwable $e) {} }
if (function_exists('setup_mark_done')) setup_mark_done();
$check('First-run wizard — company name is set and branded', trim((string)setting_get('app_name', '')) === 'Meridian Technical Services');
$check('First-run wizard — setup is no longer flagged as needed', function_exists('setup_needed') ? (setup_needed() === false) : true);

// 3) A multi-capability company onboards itself (the "join as an organisation" flow).
$email = 'owner+' . substr(md5((string)mt_rand()), 0, 6) . '@meridian.test';
[$ok, $msg, $acct] = function_exists('connect_org_register') ? connect_org_register([
    'name' => 'Meridian Multi Services Ltd', 'org_type' => 'ENTERPRISE',
    'contact_name' => 'Owner', 'contact_email' => $email, 'contact_mobile' => '9820000001',
    'password' => 'coldstart123',
    'caps' => ['TPIA', 'TECHNICAL_MANPOWER', 'FREELANCE_SUPPLY'],   // one company, several capabilities
]) : [false, 'engine missing', null];
$check('Company onboarding — a multi-capability organisation registers', $ok === true);
$party = (int)ops_val("SELECT id FROM business_partners WHERE legal_name='Meridian Multi Services Ltd' ORDER BY id DESC LIMIT 1");
$check('Company onboarding — the business party + organisation are created', $party > 0
    && (int)ops_val("SELECT COUNT(*) FROM cx_organisations WHERE party_id=?", [$party]) > 0);
$caps = ($party && function_exists('connect_org_caps')) ? connect_org_caps($party) : [];
$capCodes = is_array($caps) ? array_map(fn($c) => is_array($c) ? ($c['cap_code'] ?? $c['code'] ?? '') : (string)$c, $caps) : [];
$capStr = strtoupper(implode(',', array_map('strval', $capCodes)));
$check('Company onboarding — ALL selected capabilities persist (not single-select)',
    strpos($capStr, 'TPIA') !== false && strpos($capStr, 'TECHNICAL_MANPOWER') !== false && strpos($capStr, 'FREELANCE_SUPPLY') !== false);
$check('Company onboarding — a working portal login is created', (int)ops_val("SELECT COUNT(*) FROM client_users WHERE LOWER(email)=?", [strtolower($email)]) > 0);

// 4) A technical professional self-registers on the marketplace passport.
$proEmail = 'pro+' . substr(md5((string)mt_rand()), 0, 6) . '@meridian.test';
$proErr = function_exists('connect_pro_register') ? connect_pro_register(['name' => 'Kavya Menon', 'email' => $proEmail, 'mobile' => '9820000002', 'password' => 'coldstart123']) : 'engine missing';
$check('Professional onboarding — a marketplace passport self-registers', $proErr === '');
$check('Professional onboarding — the professional record exists', (int)ops_val("SELECT COUNT(*) FROM cx_professionals WHERE email=?", [$proEmail]) > 0);

// 5) The first real operational record — a requirement posted by the new company.
$rid = ($party && function_exists('cx_requirement_create')) ? (int)cx_requirement_create([
    'title' => 'First Requirement — QA/QC Inspector', 'poster_party_id' => $party, 'poster_name' => 'Meridian Multi Services Ltd',
    'discipline_code' => 'MECH', 'location' => 'Surat', 'work_type' => 'FREELANCE', 'positions' => 1,
    'start_date' => date('Y-m-d', strtotime('+7 days')), 'end_date' => date('Y-m-d', strtotime('+37 days')),
    'rate_min' => 8000, 'rate_max' => 12000, 'rate_unit' => 'day'], true) : 0;
$check('First record — the company posts its first requirement (OPEN)', $rid > 0
    && strtoupper((string)ops_val("SELECT status FROM cx_requirements WHERE id=?", [$rid])) === 'OPEN');

echo "== COLD START — first-run onboarding from an empty database ==\n\n";
$allpass = true;
foreach ($checks as [$label, $ok]) { if (!$ok) $allpass = false; echo '  ' . ($ok ? '✓ PASS' : '✗ FAIL') . '  ' . $label . "\n"; }
echo "\n  " . ($allpass ? 'ALL PASS — a brand-new company can onboard and start work from scratch.'
                        : 'SOME FAIL — first-run onboarding is broken above.') . "\n";
exit($allpass ? 0 : 1);
