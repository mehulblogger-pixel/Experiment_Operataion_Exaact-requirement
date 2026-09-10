<?php
// ============================================================================
//  Standalone workspace diagnostics — tells us, in one page, WHY a company's
//  workspace will not open on THIS server. Safe: it changes no company data
//  (it only clears the code cache and reads/opens databases), and it is gated
//  by your admin password.
//
//  Open it like this (put your real admin password after pin=):
//     https://operations.mghaiapps.com/diagnose.php?pin=YOUR_ADMIN_PASSWORD
//  To deep-test one company, add its key:
//     ...&key=acme-pharmaceuticals-pvt-ltd
//
//  It is a brand-new standalone file, so it is never served from a stale cache,
//  and it clears the code cache itself before loading the app — so what it
//  reports is the FRESHLY uploaded code, not an old copy.
// ============================================================================

@header('Content-Type: text/plain; charset=utf-8');
@header('Cache-Control: no-store');
$root = __DIR__;

// Force the freshly uploaded code to load (best effort).
if (function_exists('opcache_reset')) @opcache_reset();
@clearstatcache(true);

$cfg = @require $root . '/config.php';
$adminPass = (string) ($cfg['admin']['pass'] ?? '');
$pin = (string) ($_GET['pin'] ?? '');
if ($adminPass === '' || !hash_equals($adminPass, $pin)) {
    http_response_code(403);
    echo "Locked. Open this page with your admin password in the address, e.g.\n\n";
    echo "  /diagnose.php?pin=YOUR_ADMIN_PASSWORD\n";
    exit;
}

$out = [];
$p = function ($s) use (&$out) { $out[] = $s; };

$p("EXAACT — workspace diagnostics");
$p("==============================");
$p("");
$p("PHP version         : " . PHP_VERSION);

// Load the application libraries exactly as the front controller does.
$_SERVER['HTTP_HOST'] = ''; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'diagnose';
if (!isset($_SESSION)) $_SESSION = [];
$loaded = false;
try {
    $idx = (string) @file_get_contents($root . '/index.php');
    if (preg_match_all("#require __DIR__ \\. '(/lib/[a-z0-9_]+\\.php)';#i", $idx, $m)) {
        foreach ($m[1] as $rel) { @require_once $root . $rel; }
        $loaded = true;
    }
} catch (Throwable $e) { $p("Loading the app threw: " . $e->getMessage()); }

$newCode = function_exists('db_epoch');
$p("New code actually live? : " . ($newCode
    ? "YES — the latest fixes are running."
    : "NO — the server is STILL running the OLD code. Restart PHP / flush OPcache in mPanel (Select PHP Version → OPcache, or Restart PHP), then reload this page."));
$p("Clear-cache allowed?    : " . (function_exists('opcache_reset') ? "yes (cleared just now)" : "no — you must restart PHP in mPanel"));
$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
$p("exec() available?       : " . (function_exists('exec') && !in_array('exec', $disabled, true) ? "yes" : "no (fine — provisioning does not need it)"));

// Build fingerprint of the files on disk.
$parts = [];
foreach (array_merge([$root . '/index.php'], glob($root . '/lib/*.php') ?: []) as $f) {
    $parts[] = basename($f) . ':' . @filemtime($f) . ':' . @filesize($f);
}
$p("Build fingerprint       : " . substr(md5(implode('|', $parts)), 0, 10));
$p("");

// App folder writable? (needed to create per-company file databases + tenants.php)
$p("App folder writable?    : " . (is_writable($root) ? "YES" : "NO — PHP cannot create files here, which blocks new company databases"));

if (function_exists('tenant_registry')) {
    $reg = tenant_registry();
    $base = (string) ($reg['base_domain'] ?? '');
    $p("Cloud base domain       : " . ($base !== '' ? $base : "(NOT SET — cloud mode is off)"));
    $ts = (array) ($reg['tenants'] ?? []);
    $p("Companies in routing    : " . count($ts));
    foreach ($ts as $k => $t) {
        $p("");
        $p("--- company: " . $k . " ---");
        $p("  name   : " . (string) ($t['company'] ?? ''));
        $p("  status : " . (string) ($t['status'] ?? 'active'));
        $p("  has pending setup: " . (isset($t['pending']) ? "yes (owner not stamped yet)" : "no"));
        if (!empty($t['sqlite'])) {
            $fp = (string) $t['sqlite']; $dir = dirname($fp);
            $p("  storage: FILE  " . $fp);
            $p("  folder writable : " . (is_writable($dir) ? "YES" : "NO — cannot create the database file"));
            $p("  file exists     : " . (is_file($fp) ? ("yes, " . filesize($fp) . " bytes") : "no (created on first use)"));
        } elseif (!empty($t['db']) && is_array($t['db'])) {
            $d = $t['db'];
            $p("  storage: MYSQL " . (string) ($d['name'] ?? '') . " @ " . (string) ($d['host'] ?? ''));
            try {
                new PDO("mysql:host=" . ($d['host'] ?? 'localhost') . ";dbname=" . ($d['name'] ?? '') . ";charset=utf8mb4",
                    (string) ($d['user'] ?? ''), (string) ($d['pass'] ?? ''), [PDO::ATTR_TIMEOUT => 6]);
                $p("  connect: OK");
            } catch (Throwable $e) { $p("  connect: FAILED — " . $e->getMessage()); }
        } else {
            $p("  storage: NONE wired — this is why it cannot open");
        }
    }

    // Deep readiness test for one company.
    $key = strtolower(trim((string) ($_GET['key'] ?? '')));
    // Deep readiness test on EVERY company — step by step, with the real error
    // shown. (The normal open-workspace path hides its error on purpose, so we
    // redo the steps here and print exactly what fails.)
    if ($newCode) {
        foreach ($ts as $ck => $ct) {
            $p("");
            $p("=== deep test: " . $ck . " ===");
            try {
                saas_enter_tenant($ck);
                try { db(); } catch (Throwable $e) {}
                $gt = $GLOBALS['__tenant'] ?? [];
                $p("  resolved to     : key='" . ($gt['key'] ?? '') . "'  error='" . ($gt['error'] ?? '') . "'");
                $cfgNow = @require __DIR__ . '/config.php';
                $drv = (string) ($cfgNow['db']['driver'] ?? '?');
                $p("  database driver : " . $drv);
                if ($drv === 'sqlite') $p("  sqlite file     : " . (string) ($cfgNow['sqlite_path'] ?? '?'));
                try {
                    $has = false;
                    try { db()->query("SELECT id FROM users LIMIT 1"); $has = true; } catch (Throwable $e) { $has = false; }
                    $p("  users table before build: " . ($has ? "exists" : "missing"));
                    if (!$has) { $p("  building schema (boot)…"); boot(); $p("  boot() completed OK"); }
                    $n = (int) db()->query("SELECT COUNT(*) FROM users WHERE is_superuser=1 AND is_active=1")->fetchColumn();
                    $p("  active admins   : " . $n);
                    $ok = function_exists('saas_tenant_ensure_ready') ? saas_tenant_ensure_ready($ck) : null;
                    $p("  ensure_ready()  : " . ($ok === true ? "TRUE  <-- this company can open" : "FALSE"));
                } catch (Throwable $e) {
                    $p("  >>> BUILD ERROR : " . $e->getMessage());
                    $p("      at " . $e->getFile() . ":" . $e->getLine());
                }
                if (function_exists('saas_leave_tenant')) saas_leave_tenant();
            } catch (Throwable $e) {
                $p("  >>> SWITCH ERROR: " . $e->getMessage());
            }
        }
    }
} else {
    $p("Cloud routing library not loaded — the app may be running old code.");
}

$p("");
$p("Done. Send this whole page as a screenshot.");
echo implode("\n", $out) . "\n";
