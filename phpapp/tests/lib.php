<?php
// Tiny zero-dependency assertion + reporting helpers (no PHPUnit, no Composer).
$GLOBALS['__t'] = ['pass' => 0, 'fail' => 0, 'fails' => []];

function t_section($name) { echo "\n== $name ==\n"; }

function t_ok($cond, $msg) {
    $g = &$GLOBALS['__t'];
    if ($cond) { $g['pass']++; echo "  ok    $msg\n"; }
    else       { $g['fail']++; $g['fails'][] = $msg; echo "  FAIL  $msg\n"; }
    return (bool)$cond;
}

function t_eq($got, $want, $msg) {
    $ok = $got === $want;
    if (!$ok) $msg .= '  (want ' . var_export($want, true) . ', got ' . var_export($got, true) . ')';
    return t_ok($ok, $msg);
}

// A block that must not throw. Turns an exception into a clean FAIL, not a crash
// that stops the whole run.
function t_nothrow($msg, callable $fn) {
    try { $fn(); return t_ok(true, $msg); }
    catch (Throwable $e) { return t_ok(false, $msg . '  (threw: ' . $e->getMessage() . ')'); }
}
