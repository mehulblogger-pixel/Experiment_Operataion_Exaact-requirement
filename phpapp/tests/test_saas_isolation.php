<?php
// ============================================================================
//  SaaS tenant isolation + exec-free provisioning.
//
//  Two guarantees a paying multi-company platform must never break:
//
//   1) A company's request must NEVER be pointed at the control database. If a
//      company's own database is not wired up, config.php must FLAG it (so the
//      "workspace being set up" page shows) — never silently fall through to
//      the owner's control database and leak its data. This regressed once and
//      showed a brand-new company the full control dashboard.
//
//   2) A new company must get its OWN database, owner login and plan WITHOUT
//      PHP exec() — managed hosting (cPanel/mPanel) disables exec(), so the old
//      separate-process provisioner did nothing there. The exec-free first-boot
//      stamp builds the company's database and stamps its owner the first time
//      the workspace is opened.
//
//  The stamp half runs in a clean CHILD process (as a company's very first
//  request is a clean process in production): a child has untouched per-process
//  migration guards, so it builds the full schema, and it cannot pollute this
//  test process's connection or settings cache.
// ============================================================================

t_section('SaaS isolation — config never silently falls back to the control database');

$root    = dirname(__DIR__);
$regFile = $root . '/tenants.php';
$hadReg  = is_file($regFile);
$regBak  = $hadReg ? file_get_contents($regFile) : null;
$tSqlite = sys_get_temp_dir() . '/mgh_iso_' . getmypid() . '_' . substr(md5($root), 0, 6) . '.sqlite';
@unlink($tSqlite);

// A cloud registry with a BROKEN company (no database wired) and a GOOD one
// (its own SQLite file + the owner details stashed at creation).
$reg = [
    'base_domain' => 'ops.example.com',
    'aliases'     => [],
    'tenants'     => [
        'brokenco' => ['company' => 'Broken Co', 'status' => 'active'],   // NO sqlite, NO db
        'goodco'   => [
            'company' => 'Good Co', 'status' => 'active', 'sqlite' => $tSqlite,
            'pending' => [
                'owner_email' => 'owner@goodco.test', 'owner_name' => 'Owner One',
                'pass_hash'   => password_hash('Temp#123', PASSWORD_DEFAULT),
                'plan'        => 'RECRUITMENT', 'seat_limit' => 3, 'app_name' => 'Good Co',
            ],
        ],
    ],
];
file_put_contents($regFile, "<?php return " . var_export($reg, true) . ";\n");

$prevSess = $_SESSION['saas_tenant'] ?? null;

// A company with no database wired must be FLAGGED, never pointed at control.
$_SESSION['saas_tenant'] = 'brokenco';
$cfgBroken = require $root . '/config.php';   // plain require re-runs resolution
t_eq((string) $cfgBroken['tenant']['error'], 'unconfigured',
    'a company with no database wired is flagged "unconfigured" (no silent fallback to control)');
t_eq((string) $cfgBroken['tenant']['key'], 'brokenco', 'the flagged company is identified for the setup page');

// A properly wired company resolves to ITS OWN database, with no error.
$_SESSION['saas_tenant'] = 'goodco';
$cfgGood = require $root . '/config.php';
t_eq((string) $cfgGood['tenant']['error'], '', 'a properly wired company resolves with no error');
t_eq((string) $cfgGood['sqlite_path'], $tSqlite, 'the company points at its OWN database file, not the control database');

// Restore this process to the control database before anything else runs.
if ($prevSess === null) { unset($_SESSION['saas_tenant']); } else { $_SESSION['saas_tenant'] = $prevSess; }

// ---------------------------------------------------------------------------
t_section('SaaS isolation — exec-free first-boot stamp builds an isolated owner login');

// Before provisioning, the owner login exists NOWHERE (least of all in control).
$leakBefore = (int) ops_val("SELECT COUNT(*) FROM users WHERE email='owner@goodco.test'");
t_eq($leakBefore, 0, 'the new company owner does not exist in the control database');

$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
$canExec  = function_exists('exec') && !in_array('exec', $disabled, true);

if ($canExec) {
    // A clean child process — exactly what a company's first web request is in
    // production. It boots the company's own (empty) SQLite, then applies the
    // exec-free first-boot stamp, and reports what landed in that database.
    $runner = sys_get_temp_dir() . '/mgh_iso_runner_' . getmypid() . '.php';
    $code = '<?php error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);'
        . '$root=getenv("APP_ROOT");'
        . '$_SERVER["REMOTE_ADDR"]="127.0.0.1";$_SERVER["HTTP_USER_AGENT"]="iso";$_SERVER["REQUEST_URI"]="/";$_SERVER["HTTP_HOST"]="";'
        . 'if(!isset($_SESSION))$_SESSION=[];'
        . '$idx=file_get_contents($root."/index.php");'
        . 'preg_match_all("#require __DIR__ \\\\. \x27(/lib/[a-z0-9_]+\\\\.php)\x27;#i",$idx,$mm);'
        . 'foreach($mm[1] as $rel){require_once $root.$rel;}'
        . 'boot();'                                       // full schema on the fresh company DB (clean guards)
        . '$applied=saas_tenant_apply_bootstrap("goodco");'
        . '$o=ops_one("SELECT * FROM users WHERE is_superuser=1");'
        . 'echo json_encode(["applied"=>$applied,'
        . '"email"=>(string)($o["email"]??""),'
        . '"pw_ok"=>password_verify("Temp#123",(string)($o["password_hash"]??"")),'
        . '"must_change"=>(int)($o["must_change_pwd"]??0),'
        . '"provisioned"=>(string)setting_get("saas_provisioned",""),'
        . '"onboarding"=>(string)setting_get("saas_onboarding_pending",""),'
        . '"seat_limit"=>(int)setting_get("saas_seat_limit",0),'
        . '"modules_off"=>(string)setting_get("modules_off",""),'
        . '"pending_cleared"=>(tenant_pending("goodco")===null)]);';
    file_put_contents($runner, $code);

    $php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $env = 'APP_ROOT=' . escapeshellarg($root)
         . ' DB_DRIVER=sqlite SQLITE_PATH=' . escapeshellarg($tSqlite);
    $out = []; $rc = 1;
    @exec($env . ' ' . escapeshellarg($php) . ' ' . escapeshellarg($runner) . ' 2>&1', $out, $rc);
    @unlink($runner);
    $res = json_decode((string) end($out), true);

    t_ok(is_array($res), 'the company\'s first-boot provisioning ran in a clean process (no exec on the app path)');
    if (is_array($res)) {
        t_ok(!empty($res['applied']), 'the first-boot stamp applied to the company database');
        t_eq((string) $res['email'], 'owner@goodco.test', 'the owner email is stamped onto the company admin');
        t_ok(!empty($res['pw_ok']), 'the owner can sign in with the temporary password handed over at creation');
        t_eq((int) $res['must_change'], 1, 'the owner is forced to set their own password on first login');
        t_eq((string) $res['provisioned'], '1', 'the company is marked provisioned so the stamp never runs twice');
        t_eq((string) $res['onboarding'], '1', 'the owner is sent through company onboarding on first login');
        t_eq((int) $res['seat_limit'], 3, 'the Recruitment plan seat limit (3) is applied to the company');
        t_ok(strpos((string) $res['modules_off'], 'operations') !== false
          && strpos((string) $res['modules_off'], 'hr') === false,
            'the Recruitment plan turns Operations off and People & hiring on for the company');
        t_ok(!empty($res['pending_cleared']), 'the stashed owner details are cleared from the registry once applied');
    }

    // The tenant SQLite really is a separate file with its own tables.
    t_ok(is_file($tSqlite) && filesize($tSqlite) > 0, 'the company has its OWN database file');

    // ISOLATION: the owner written into the company database is STILL absent
    // from the control database (this process never left the control store).
    $leakAfter = (int) ops_val("SELECT COUNT(*) FROM users WHERE email='owner@goodco.test'");
    t_eq($leakAfter, 0, 'the company owner login lives ONLY in the company database — never in control (isolation holds)');
} else {
    t_ok(true, 'exec() is unavailable in this test runner — the child-process stamp check is skipped here');
}

// ---------------------------------------------------------------------------
t_section('SaaS isolation — routing rebuilds itself from the database after an upload');

// The control database is the durable source of truth; tenants.php is only a
// cache kept out of every upload. Prove that if the file is wiped, cloud mode
// and every company come back automatically from the database.
if (function_exists('tenant_registry_heal')) {
    // Make sure this process is resolved to the CONTROL install (heal refuses
    // inside a workspace). The config-safety section above left the tenant
    // descriptor pointed at a company; re-resolve with no company in session.
    unset($_SESSION['saas_tenant']);
    require $root . '/config.php';
    // Register a company IN THE DATABASE (directory + saved base domain + its
    // stored route), exactly as adding a company now does.
    setting_set('saas_base_domain', 'ops.example.com');
    saas_tenant_upsert('healco', ['company' => 'Heal Co', 'plan' => 'RECRUITMENT', 'status' => 'active']);
    saas_tenant_upsert('healco', ['route_json' => json_encode(['sqlite' => $tSqlite])]);
    saas_tenant_upsert('healco', ['pending_json' => json_encode(['owner_email' => 'owner@heal.test', 'plan' => 'RECRUITMENT'])]);

    // Simulate an upload that wiped the routing file.
    @unlink($regFile);
    $regGone = tenant_registry();
    t_eq((string) ($regGone['base_domain'] ?? ''), '', 'after an upload wipes it, the routing file has no base domain');

    // Heal from the database.
    $healed = tenant_registry_heal();
    t_ok($healed === true, 'the routing file is rebuilt from the database');
    $regNew = tenant_registry();
    t_eq((string) ($regNew['base_domain'] ?? ''), 'ops.example.com', 'cloud mode is restored (base domain comes back)');
    t_ok(isset($regNew['tenants']['healco']), 'the company is routable again');
    t_eq((string) ($regNew['tenants']['healco']['sqlite'] ?? ''), $tSqlite, 'the company points back at its own stored database');
    t_eq((string) ($regNew['tenants']['healco']['company'] ?? ''), 'Heal Co', 'the company name is restored');
    t_eq((string) ($regNew['tenants']['healco']['pending']['owner_email'] ?? ''), 'owner@heal.test', 'the owner\'s first-login details are restored too (owner can still sign in by email)');

    // Idempotent: a second heal with nothing missing changes nothing.
    t_ok(tenant_registry_heal() === false, 'a heal with nothing out of step makes no change');

    // Recovery when there is NO saved base domain either (cloud was turned on by
    // an older version): the base domain is adopted from the current request host
    // so routing still comes back with no manual step.
    setting_set('saas_base_domain', '');          // as if it was never saved
    @unlink($regFile);                             // upload wiped the routing file again
    $origHost = $_SERVER['HTTP_HOST'] ?? null;
    $_SERVER['HTTP_HOST'] = 'ops.example.com';     // the site's own address
    $healed2 = tenant_registry_heal();
    t_ok($healed2 === true, 'with no saved base domain, routing still recovers from the site address');
    $reg2 = tenant_registry();
    t_eq((string) ($reg2['base_domain'] ?? ''), 'ops.example.com', 'the base domain is adopted from the site address');
    t_eq((string) setting_get('saas_base_domain', ''), 'ops.example.com', 'and saved durably so it never needs adopting again');
    t_ok(isset($reg2['tenants']['healco']), 'the company is routable again after host-based recovery');
    if ($origHost === null) { unset($_SERVER['HTTP_HOST']); } else { $_SERVER['HTTP_HOST'] = $origHost; }

    // Clean up the directory row + saved base domain we added.
    if (function_exists('saas_tenant_delete')) saas_tenant_delete('healco');
    setting_set('saas_base_domain', '');
}

// ---------------------------------------------------------------------------
// Cleanup: remove the throwaway registry + database, restore any real one, and
// return this process cleanly to the control database.
@unlink($tSqlite);
if ($hadReg && $regBak !== null) { file_put_contents($regFile, $regBak); }
else { @unlink($regFile); }
if (function_exists('db_reset')) db_reset();
require $root . '/config.php';   // re-resolve to base now that the test registry is gone
t_ok(true, 'isolation test cleaned up (throwaway registry and database removed)');
