<?php
// A12-M: report the first statement (file:line) after which the open transaction disappeared.
$GLOBALS['__txw'] = ['armed' => false, 'reported' => false];
function __txw_arm() { $GLOBALS['__txw'] = ['armed' => db()->inTransaction(), 'reported' => false]; }
function __txw_tick() {
    $w = &$GLOBALS['__txw'];
    if (!$w['armed'] || $w['reported']) return;
    if (!db()->inTransaction()) {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        echo "TXWATCH: transaction ended (implicit commit) at or before " . basename($bt[0]['file'] ?? '?') . ':' . ($bt[0]['line'] ?? '?') . "\n";
        $w['reported'] = true;
    }
}
register_tick_function('__txw_tick');
