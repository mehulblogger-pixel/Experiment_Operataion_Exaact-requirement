<?php
// ============================================================================
//  Public licence-server endpoint
//
//  A customer's install pulls its current licence key here (licencesync.php
//  calls  {LICENCE_SERVER}/api.php?action=licence&install=ID). No session — the
//  caller is identified by its install id, and only ever RECEIVES a key that was
//  already issued for that id on this licence server. Signing happens elsewhere;
//  this only hands back what was signed.
// ============================================================================
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$action = (string)($_GET['action'] ?? '');
if ($action !== 'licence') { http_response_code(404); echo json_encode(['error' => 'Unknown action.']); exit; }

try {
    // Load just enough of the app to read the database and the issued register.
    // Order matches the application's own bootstrap so constants resolve.
    $L = ['db','helpers','ops','lookups','licence','access','terms','security','licencekey','licenceissue'];
    foreach ($L as $m) { $f = __DIR__ . '/lib/' . $m . '.php'; if (is_file($f)) require_once $f; }

    $install = trim((string)($_GET['install'] ?? ''));
    if ($install === '') { echo json_encode(['error' => 'Missing install id.']); exit; }

    // The install may report its own state alongside the pull (the heartbeat).
    // Advisory only — recorded so the console can show which copies are alive.
    if (function_exists('licbeat_record')) {
        $reported = array_filter([
            'cust'  => $_GET['cust']  ?? null, 'state' => $_GET['state'] ?? null,
            'used'  => $_GET['used']  ?? null, 'lic'   => $_GET['lic']   ?? null,
            'host'  => $_GET['host']  ?? null, 'ver'   => $_GET['ver']   ?? null,
        ], fn($v) => $v !== null);
        if ($reported) licbeat_record($install, $reported);
    }

    $key = function_exists('lk_latest_key_for') ? lk_latest_key_for($install) : '';
    if ($key === '') { echo json_encode(['error' => 'No licence has been issued for this install id.']); exit; }

    echo json_encode(['key' => $key]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'The licence server hit an error.']);
}
