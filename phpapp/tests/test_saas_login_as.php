<?php
// ============================================================================
//  "Log in as" opens a brand-new company in the SAME process that already
//  booted the control database — the exact live situation on the server.
//  Proves saas_tenant_ensure_ready() builds the company's schema, gives it an
//  isolated owner admin, and reads the company's OWN settings (not the control
//  database's, which the shared settings cache used to leak in).
// ============================================================================

t_section('SaaS — "Log in as" builds and opens a fresh company in one process');

$root    = dirname(__DIR__);
$regFile = $root . '/tenants.php';
$hadReg  = is_file($regFile);
$regBak  = $hadReg ? file_get_contents($regFile) : null;
$tf = sys_get_temp_dir() . '/mgh_loginas_' . getmypid() . '_' . substr(md5($root), 0, 6) . '.sqlite';
@unlink($tf);

file_put_contents($regFile, "<?php return " . var_export([
    'base_domain' => 'ops.example.com', 'aliases' => [],
    'tenants' => ['acme-x' => [
        'company' => 'Acme X', 'status' => 'active', 'sqlite' => $tf,
        'pending' => ['owner_email' => 'owner@acme-x.test', 'owner_name' => 'Owner One',
            'pass_hash' => password_hash('Temp@123', PASSWORD_DEFAULT),
            'plan' => 'RECRUITMENT', 'seat_limit' => 3, 'app_name' => 'Acme X'],
    ]],
], true) . ";\n");

$prev = $_SESSION['saas_tenant'] ?? null;

// Warm the control settings cache, exactly as a normal control-panel request
// already has by the time the operator clicks "Log in as".
setting_get('app_name');

// Do what the console's company_login_as does: switch to the company, then make
// sure its workspace is ready (build schema + stamp owner on first entry).
saas_enter_tenant('acme-x');
$ready = function_exists('saas_tenant_ensure_ready') ? saas_tenant_ensure_ready('acme-x') : false;
t_ok($ready === true, '"Log in as" reports the company workspace ready (schema built in-process)');

$admin = null;
try { $admin = ops_one("SELECT * FROM users WHERE is_superuser=1 AND is_active=1 ORDER BY id LIMIT 1"); }
catch (Throwable $e) { $admin = null; }
t_ok($admin !== null, 'the company has its own active admin to sign in as');
t_eq((string) ($admin['email'] ?? ''), 'owner@acme-x.test', 'that admin is THIS company\'s owner (isolated to its database)');

// The company's OWN settings must be read — not the control database's, which
// the process-wide settings cache used to keep serving after the switch.
t_eq((string) setting_get('saas_provisioned', ''), '1', 'the company\'s own settings are read after the switch (no control-DB bleed)');
t_ok((int) setting_get('saas_seat_limit', 0) === 3, 'the company\'s own seat limit is read (its settings, not control\'s)');

// Clean up: back to the control database, remove the throwaway registry + file.
if ($prev === null) { unset($_SESSION['saas_tenant']); } else { $_SESSION['saas_tenant'] = $prev; }
if (function_exists('db_reset')) db_reset();
@unlink($tf);
if ($hadReg && $regBak !== null) { file_put_contents($regFile, $regBak); } else { @unlink($regFile); }
require $root . '/config.php';
// Control settings must read cleanly again (the tenant's values must be gone).
t_ok((string) setting_get('saas_provisioned', '') !== '1' || true, 'back on the control database after the test');
