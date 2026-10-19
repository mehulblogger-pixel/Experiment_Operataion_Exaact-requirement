<?php
// One HTTP-like request in its own process (redirect() calls exit).
// argv[1] = JSON {uid, route, method, get, post}. Prints one JSON line on exit.
$in = json_decode($argv[1] ?? '{}', true) ?: [];
require __DIR__ . '/boot.php';
$GLOBALS['__out'] = ['flash' => [], 'exception' => '', 'rendered' => 0];
register_shutdown_function(function () {
    $buf = ''; while (ob_get_level() > 0) $buf .= ob_get_clean();
    $o = $GLOBALS['__out'];
    $o['flash'] = array_map(fn($f) => $f['tag'] . ': ' . $f['text'], $_SESSION['flash'] ?? []);
    $o["rendered"] = strlen($buf); $o["body"] = mb_substr(trim(preg_replace("/\\s+/", " ", strip_tags($buf))), 0, 300);
    if (preg_match('/class="[^"]*(?:alert-error|error)[^"]*"[^>]*>(.{0,300})/s', $buf, $m)) $o['page_error'] = trim(strip_tags($m[1]));
    $e = error_get_last(); if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) $o['fatal'] = $e['message'] . ' @' . $e['file'] . ':' . $e['line'];
    fwrite(STDOUT, "\n@@OUT@@" . json_encode($o) . "\n");
});
// Stand-in for index.php's view(): record which screen and any error it was handed,
// without rendering templates (layout needs the full front controller).
if (!function_exists('view')) { function view($name, $vars = []) {
    $GLOBALS['__out']['view'] = $name;
    if (isset($vars['error']) && $vars['error'] !== '') $GLOBALS['__out']['view_error'] = (string)$vars['error'];
    foreach (['call', 'job', 'pf', 'prefill'] as $k) if (isset($vars[$k]) && is_array($vars[$k]))
        $GLOBALS['__out']['vars'][$k] = array_intersect_key($vars[$k], array_flip(['client_id','quotation_id','quote_line_id','contract_number','sbu','ibo_office_id','po_id','po_line_item_id','billable_rate','billable_basis','billable_qty','billable_value','vendor_id','activity_id','coordinator_id','_prefill_note','inspection_type','invoice_value','mandays']));
    echo "[view $name]"; } }
as_user((int)($in['uid'] ?? 0));
$_GET = $in['get'] ?? []; $_POST = $in['post'] ?? [];
$method = $in['method'] ?? 'GET'; $_SERVER['REQUEST_METHOD'] = $method;
ob_start();
try { ops_dispatch((string)$in['route'], $method); }
catch (Throwable $e) { $GLOBALS['__out']['exception'] = get_class($e) . ': ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine(); }
