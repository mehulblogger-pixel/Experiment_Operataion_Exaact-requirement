<?php
// Server-side authorisation guard for the operations console.
//
// Hiding the console in the view is not enough — the POST handlers must refuse a
// non-manager even if the form is reached or the URL is posted directly. Every
// mutating call-* / assign-* / job-ready / job-confirm / assign-issue handler
// must call ops_require(tosrm_can_edit(...)) BEFORE it writes anything. This test
// reads the source and fails loudly if any of those gates is ever removed or
// moved after a mutation, so the protection cannot regress unnoticed.
t_section('operations console — server-side authz gates');

$src = file_get_contents(dirname(__DIR__) . '/lib/tosrm.php');

// Pull a single top-level function body out of the source (from its signature to
// the next line that begins a new top-level function).
$body = function (string $name) use ($src): string {
    $start = strpos($src, "function $name(");
    if ($start === false) return '';
    $next = strpos($src, "\nfunction ", $start + 1);
    return substr($src, $start, ($next === false ? strlen($src) : $next) - $start);
};

// The gate we insist on: an ops_require that keys off tosrm_can_edit().
$GATE = 'ops_require(tosrm_can_edit(';

// Handlers built as a pre-switch gate + a switch of routes: the gate must exist
// and must sit BEFORE the switch, so no branch can mutate ahead of the check.
foreach (['ops_tosrm_action', 'ops_tosrm_job_action', 'ops_tosrm_ready_action'] as $fn) {
    $b = $body($fn);
    t_ok($b !== '', "$fn is present");
    $g = strpos($b, $GATE);
    $sw = strpos($b, 'switch (');
    t_ok($g !== false, "$fn gates on tosrm_can_edit()");
    t_ok($g !== false && $sw !== false && $g < $sw, "$fn checks permission before the route switch");
}

// The comms handler is a chain of if-blocks (comm-add, xo-nudge, assign-issue).
// The two that mutate on a manager's behalf — comm-add and assign-issue — must
// each carry the gate, so require at least two occurrences.
$cb = $body('ops_tosrm_comm_action');
t_ok($cb !== '', 'ops_tosrm_comm_action is present');
t_ok(substr_count($cb, $GATE) >= 2, 'comm-add and assign-issue each gate on tosrm_can_edit()');

// And the gate helper really is a hard stop, not an advisory: it must bail out
// (redirect) when the check fails, and redirect() exits.
$ops = file_get_contents(dirname(__DIR__) . '/lib/ops.php');
t_ok((bool)preg_match('/function ops_require\([^)]*\)\s*\{\s*if\s*\(!\$ok\)\s*\{[^}]*redirect\(/', $ops),
     'ops_require() redirects away when the check fails (hard stop, before any write)');
