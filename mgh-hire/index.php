<?php
// =========================================================================
//  MGH Hire — front controller.
//  One entry point. The database builds itself on the first request.
// =========================================================================
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require __DIR__ . '/lib/app.php';
require __DIR__ . '/lib/layout.php';

session_boot();

// First run (or after an upgrade): build / migrate the schema.
try {
    if (!db_ready()) db_build();
    else db_build(); // idempotent: adds any new tables/stages on upgrade
} catch (Throwable $ex) {
    http_response_code(500);
    echo '<h2 style="font-family:sans-serif">Setup problem</h2>';
    echo '<p style="font-family:sans-serif;color:#555">The app could not prepare its database. '
       . 'Check <code>config.local.php</code>, then reload this page.</p>';
    if (getenv('MGH_DEBUG')) echo '<pre>' . e($ex->getMessage()) . '</pre>';
    exit;
}

csrf_check();

$p = preg_replace('/[^a-z_]/', '', strtolower(get('p', 'dashboard'))) ?: 'dashboard';

// Public routes: careers page, login / logout.
if ($p === 'careers') { require __DIR__ . '/pages/careers.php'; exit; }
if ($p === 'logout')  { logout(); redirect('?p=login'); }
if ($p === 'login')   { require __DIR__ . '/pages/login.php'; exit; }

// Everything else needs a session.
require_login();

$routes = [
    'dashboard'      => 'dashboard.php',
    'tasks'          => 'tasks.php',
    'requisitions'   => 'requisitions.php',
    'requisition'    => 'requisition.php',
    'candidates'     => 'candidates.php',
    'candidate'      => 'candidate.php',
    'pipeline'       => 'pipeline.php',
    'users'          => 'users.php',
    'billing'        => 'billing.php',
    'settings'       => 'settings.php',
];
$file = $routes[$p] ?? 'dashboard.php';
require __DIR__ . '/pages/' . $file;
