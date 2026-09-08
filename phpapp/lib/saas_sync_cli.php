<?php
// ============================================================================
//  SaaS sync (CLI only) — push a company's entitlement into its OWN database.
//
//  When the Companies console changes a company's plan, modules or seats, the
//  change is recorded in the control directory; this worker writes it into the
//  company's live store so it actually takes effect there — the modules it may
//  use (modules_off) and how many logins it may hold (saas_seat_limit).
//
//  Runs in a separate process, like the provisioner, so it never disturbs the
//  request that launched it. It does NOT boot — the database already exists — it
//  only updates a couple of settings.
//
//    SAAS_SQLITE  — the company's SQLite file, OR DB_DRIVER=mysql + DB_HOST/NAME/USER/PASS
//    SAAS_PLAN    — the plan (drives which modules are switched off)
//    SAAS_SEAT_LIMIT — total logins allowed (plan base + purchased seats)
// ============================================================================

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);
$sqlite = getenv('SAAS_SQLITE');
if ($sqlite !== false && $sqlite !== '') { putenv('DB_DRIVER=sqlite'); putenv('SQLITE_PATH=' . $sqlite); }

$_SERVER['HTTP_HOST'] = ''; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_USER_AGENT'] = 'saas-sync';
if (!isset($_SESSION)) $_SESSION = [];

$idx = @file_get_contents($root . '/index.php');
if ($idx === false) { echo "ERR cannot read index.php\n"; exit(1); }
preg_match_all("#require __DIR__ \\. '(/lib/[a-z0-9_]+\\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) { if (basename($rel) === 'saas_sync_cli.php') continue; require_once $root . $rel; }

try {
    $plan  = strtoupper((string) getenv('SAAS_PLAN') ?: 'RECRUITMENT');
    $limit = max(0, (int) getenv('SAAS_SEAT_LIMIT'));
    if (function_exists('saas_apply_plan_modules')) saas_apply_plan_modules($plan);   // modules_off + product_package
    if (function_exists('setting_set')) setting_set('saas_seat_limit', (string) $limit);
    echo "OK sync plan=$plan seats=$limit\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERR " . $e->getMessage() . "\n");
    echo "ERR " . $e->getMessage() . "\n";
    exit(1);
}
