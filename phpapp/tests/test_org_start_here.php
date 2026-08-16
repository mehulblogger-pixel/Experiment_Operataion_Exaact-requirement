<?php
// The "Start here" org-setup checklist must only link to pages that exist.
// Regression: two steps pointed at /m/designation and /m/sbu, but those are
// lookup LISTS (reached at /lookup?key=…), not /m/ record masters — so the
// generic /m/<entity> handler returned "record or page does not exist". This
// asserts every step's link resolves: a /lookup?key=X must be a real lookup
// type, and a /m/Y must be a registered record master.
t_section('org "Start here" checklist links all resolve');

$steps   = org_start_here();
$masters = function_exists('ops_masters') ? ops_masters() : [];

t_ok(count($steps) >= 4, 'the checklist has its steps');

foreach ($steps as $st) {
    $h = (string)$st['href'];
    $t = (string)$st['title'];
    if (preg_match('#^/lookup\?key=([a-z_]+)#', $h, $m)) {
        t_ok(lk_type($m[1]) !== null, "step \"$t\" → /lookup?key={$m[1]} resolves (the lookup type exists)");
    } elseif (preg_match('#^/m/([a-z-]+)#', $h, $m)) {
        t_ok(isset($masters[$m[1]]), "step \"$t\" → /m/{$m[1]} is a real record master");
    } else {
        t_ok($h !== '', "step \"$t\" has a non-empty link");
    }
}

// The specific pair from the bug report, pinned.
$find = function ($needle) use ($steps) {
    foreach ($steps as $s) if (strpos((string)$s['href'], $needle) !== false) return $s;
    return null;
};
t_ok($find('key=designation') !== null && lk_type('designation') !== null,
     'the designation step opens the designation lookup list');
t_ok($find('key=sbu') !== null && lk_type('sbu') !== null,
     'the business-unit step opens the sbu lookup list');
t_ok($find('/m/designation') === null && $find('/m/sbu') === null,
     'no step points at the dead /m/designation or /m/sbu route');
