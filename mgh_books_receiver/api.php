<?php
// ============================================================================
//  MGH Books — api.php  (the endpoint the ERP connector talks to)
//
//  Thin HTTP front over lib/receiver.php. Verifies the shared token, reads the
//  request, hands it to the one dispatcher the tests also use, and answers in the
//  JSON shape the ERP expects. Nothing app-specific lives here — swap the storage
//  and auth in receiver.php for Books' own and this file is unchanged.
//
//  POST  application/json  { "action":"set|import", "kind":"...", "token":"...", "data":{...} }
//        Authorization: Bearer <token>
//  GET   ?action=status&ext_id=ERP-INV-42     Authorization: Bearer <token>
// ============================================================================

require __DIR__ . '/lib/receiver.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

function bkrecv_reply($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

// The token may arrive as a Bearer header or in the JSON body; prefer the header.
function bkrecv_bearer() {
    $h = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) $h = $_SERVER['HTTP_AUTHORIZATION'];
    elseif (function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v) if (strcasecmp($k, 'Authorization') === 0) { $h = $v; break; }
    }
    if (stripos($h, 'Bearer ') === 0) return trim(substr($h, 7));
    return '';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw    = file_get_contents('php://input') ?: '';
$body   = json_decode($raw, true);
$body   = is_array($body) ? $body : [];

// Auth: header token first, then the body token as a fallback.
$token = bkrecv_bearer();
if ($token === '') $token = (string)($body['token'] ?? '');
if (!bkrecv_auth($token)) bkrecv_reply(401, ['ok' => false, 'error' => 'unauthorized']);

// Status is a GET; set/import are POSTs carrying a data object.
if ($method === 'GET' || (($_GET['action'] ?? '') === 'status')) {
    [$code, $resp] = bkrecv_dispatch('status', '', [], (string)($_GET['ext_id'] ?? ''));
    bkrecv_reply($code, $resp);
}

$action = (string)($body['action'] ?? ($_GET['action'] ?? ''));
$kind   = (string)($body['kind'] ?? '');
$data   = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];

try {
    [$code, $resp] = bkrecv_dispatch($action, $kind, $data);
    bkrecv_reply($code, $resp);
} catch (Throwable $e) {
    bkrecv_reply(500, ['ok' => false, 'error' => 'server error']);
}
