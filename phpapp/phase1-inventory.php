<?php
// ============================================================================
//  EXAACT — PHASE 1 · STEP 1
//  READ-ONLY TENANT ENTITLEMENT INVENTORY  (standalone; safe on production)
//
//  WHAT IT DOES
//  Prints, for every hosted workspace, what it can use today and what it would
//  be able to use if the entitlement ceiling became default-deny.
//
//  WHAT IT DOES NOT DO
//   • It does NOT boot the application.
//   • It does NOT enter a workspace (saas_enter_tenant is never called).
//   • It does NOT run migrations.
//   • It does NOT write to the control database or to any workspace database.
//   • It changes NO application behaviour. Nothing else in the app loads it.
//
//  Why that matters: the application's own boot chain WRITES entitlement —
//  saas_entitlement_ensure() (lib/db.php) stamps saas_entitled_modules onto any
//  provisioned workspace that has none. Booting would therefore change the very
//  state we are trying to measure. This tool uses raw SELECT-only connections;
//  every query passes through a guard that refuses anything but a read.
//
//  ── HOW TO RUN ─────────────────────────────────────────────────────────────
//  A) Command line (preferred — e.g. cPanel → Cron Jobs, run once):
//         php /home/USER/public_html/phase1-inventory.php
//     JSON instead:
//         php /home/USER/public_html/phase1-inventory.php --json
//
//  B) Browser (when no command line is available):
//     1. Create a file next to this one called   phase1-inventory.key
//        containing one line: a long random password you invent.
//     2. Visit   https://YOUR-DOMAIN/phase1-inventory.php?key=THAT-PASSWORD
//        (add &json=1 for JSON)
//     3. DELETE phase1-inventory.key when you are finished.
//     Without that key file the browser route refuses to run.
//
//  Send the whole output back for review. It contains no passwords.
//  Delete this file from the server once the inventory has been supplied.
// ============================================================================

$IS_CLI = (PHP_SAPI === 'cli');

// ---- Gate the browser route behind a key file the operator creates ---------
if (!$IS_CLI) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    $keyFile = __DIR__ . '/phase1-inventory.key';
    $expect  = is_file($keyFile) ? trim((string) @file_get_contents($keyFile)) : '';
    $given   = (string) ($_GET['key'] ?? '');
    if ($expect === '' || $given === '' || !hash_equals($expect, $given)) {
        http_response_code(404);
        echo "Not available.\n";
        exit;
    }
}

// ---- Force control-install resolution: never resolve into a workspace ------
$_SESSION = [];                       // ignore any remembered workspace
$_SERVER['HTTP_HOST'] = '';           // treated as the base domain => control DB
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

require_once __DIR__ . '/lib/phase1_inventory.php';   // engine (pure; no side effects)
require_once __DIR__ . '/lib/licence.php';            // PRODUCT_MODULES only
$CFG = require __DIR__ . '/config.php';               // reads config; writes nothing

$wantJson = $IS_CLI
    ? in_array('--json', $argv ?? [], true)
    : (($_GET['json'] ?? '') !== '');

// ---- Open the CONTROL database (read-only usage) --------------------------
$report = ['generated_at' => date('c'), 'tenants' => [], 'notes' => []];
try {
    $d = $CFG['db'];
    if (($d['driver'] ?? '') === 'sqlite') {
        $ctl = new PDO('sqlite:' . $CFG['sqlite_path']);
        $report['control_engine'] = 'sqlite';
    } else {
        $ctl = new PDO("mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'],
                       [PDO::ATTR_TIMEOUT => 10]);
        $report['control_engine'] = 'mysql';
    }
    $ctl->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $ctl->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo "Could not open the control database: " . $e->getMessage() . "\n";
    exit(1);
}

// ---- The routing registry, if present (fallback for older workspaces) -----
$registry = [];
$regFile = __DIR__ . '/tenants.php';
if (is_file($regFile)) {
    $r = @require $regFile;
    if (is_array($r) && is_array($r['tenants'] ?? null)) $registry = $r['tenants'];
}

// ---- List the workspaces ---------------------------------------------------
$tenants = [];
try {
    $tenants = p1_ro_query($ctl, "SELECT * FROM saas_tenants ORDER BY company, tenant_key")->fetchAll();
} catch (Throwable $e) {
    $report['notes'][] = 'No saas_tenants table in the control database (' . $e->getMessage()
                       . ') — this install has no hosted workspaces.';
}

if (!$tenants) {
    $report['notes'][] = 'No hosted workspaces found. Default-deny would affect nothing here.';
}

// ---- Inspect each workspace's OWN database (SELECT only) ------------------
foreach ($tenants as $t) {
    $key   = (string) ($t['tenant_key'] ?? '');
    $route = p1_resolve_route((string) ($t['route_json'] ?? ''), $registry[$key] ?? null);

    if ($route['kind'] === 'none') {
        $report['tenants'][] = p1_build_row($t, [], $route['label'], 'workspace database is not wired up');
        continue;
    }
    // Never let PDO create a missing SQLite file — that would be a write.
    if ($route['kind'] === 'sqlite' && !is_file((string) ($route['path'] ?? ''))) {
        $report['tenants'][] = p1_build_row($t, [], $route['label'], 'workspace data file not found (not created — this tool never writes)');
        continue;
    }
    try {
        $tp = new PDO($route['dsn'], $route['user'], $route['pass'], [PDO::ATTR_TIMEOUT => 10]);
        $tp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $tp->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $settings = p1_read_settings($tp);                       // SELECT only
        $report['tenants'][] = p1_build_row($t, $settings, $route['label'], '');
        $tp = null;
    } catch (Throwable $e) {
        $report['tenants'][] = p1_build_row($t, [], $route['label'], $e->getMessage());
    }
}

$report['summary'] = p1_summarise($report['tenants']);

// ---- Output (screen only; no file is written) ------------------------------
echo $wantJson
    ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
    : p1_render_text($report);
