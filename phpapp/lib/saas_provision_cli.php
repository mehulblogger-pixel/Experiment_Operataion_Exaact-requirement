<?php
// ============================================================================
//  SaaS provisioner (CLI only) — set up ONE new company's database.
//
//  The Companies console (lib/saas_tenants.php, action company_add) runs this in
//  a SEPARATE PHP process. A separate process is deliberate: booting a brand-new
//  database inside the web request that is already serving the control database
//  collides with per-process, run-once migration guards (and with the fresh
//  admin's id clashing with the operator's session). A clean child process has
//  none of that.
//
//  It reads its instructions from the environment (so nothing sensitive is on a
//  command line), boots the new database, and stamps the company's identity, its
//  owner-admin and its bought modules onto it.
//
//    SAAS_SQLITE   — path to the company's SQLite file (for a file-backed tenant)
//    or DB_DRIVER=mysql + DB_HOST/DB_NAME/DB_USER/DB_PASS (for a MySQL tenant)
//    SAAS_COMPANY, SAAS_EMAIL, SAAS_NAME, SAAS_PASS, SAAS_PLAN
//
//  Prints "OK <company>" and exits 0 on success; prints "ERR ..." and exits 1
//  otherwise. Never touches any other company's data.
// ============================================================================

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // never reachable over the web

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$root = dirname(__DIR__);

// Point the app at THIS company's store before anything connects.
$sqlite = getenv('SAAS_SQLITE');
if ($sqlite !== false && $sqlite !== '') { putenv('DB_DRIVER=sqlite'); putenv('SQLITE_PATH=' . $sqlite); }
// (For a MySQL tenant the caller exports DB_DRIVER=mysql + DB_HOST/NAME/USER/PASS,
//  which config.php already honours.)

$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'saas-provisioner';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['HTTP_HOST']       = '';          // treated as the base — no tenant routing here
if (!isset($_SESSION)) $_SESSION = [];

// Load exactly what the front controller loads, discovered from it so it never
// drifts out of step.
$idx = @file_get_contents($root . '/index.php');
if ($idx === false) { fwrite(STDERR, "ERR cannot read index.php\n"); echo "ERR cannot read index.php\n"; exit(1); }
preg_match_all("#require __DIR__ \\. '(/lib/[a-z0-9_]+\\.php)';#i", $idx, $m);
foreach ($m[1] as $rel) { if (basename($rel) === 'saas_provision_cli.php') continue; require_once $root . $rel; }

try {
    boot();   // fresh database: full schema + seed + default admin

    $company = (string) getenv('SAAS_COMPANY');
    $email   = strtolower(trim((string) getenv('SAAS_EMAIL')));
    $name    = trim((string) getenv('SAAS_NAME')) ?: 'Administrator';
    $pass    = (string) getenv('SAAS_PASS');
    $plan    = strtoupper((string) getenv('SAAS_PLAN') ?: 'RECRUITMENT');

    if ($company !== '') setting_set('app_name', substr($company, 0, 120));
    setting_set('setup_done', '1');

    // The owner becomes the company's admin; they set their own password at first
    // login (must_change_pwd = 1).
    [$fn, $ln] = array_pad(explode(' ', $name, 2), 2, '');
    $hash = password_hash($pass !== '' ? $pass : bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    db()->prepare("UPDATE users SET email=?, first_name=?, last_name=?, password_hash=?, must_change_pwd=1, pwd_changed_at=? WHERE is_superuser=1")
        ->execute([$email, $fn, $ln, $hash, date('c')]);

    // Stop the config-admin sync from reverting that password on later boots.
    try {
        $cfg = require $root . '/config.php';
        setting_set('admin_cfg_sig', md5(((string) ($cfg['admin']['user'] ?? 'admin')) . "\x00" . ((string) ($cfg['admin']['pass'] ?? ''))));
    } catch (Throwable $e) {}

    // The bought modules — switch off everything the plan does not include.
    if (function_exists('saas_apply_plan_modules')) saas_apply_plan_modules($plan);
    if (function_exists('doc_tpl_migrate')) doc_tpl_migrate();

    echo "OK " . $company . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERR " . $e->getMessage() . "\n");
    echo "ERR " . $e->getMessage() . "\n";
    exit(1);
}
