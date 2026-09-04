<?php
// Connect K12 — the Operations Advisor. Asserts the deterministic verdict:
// HIGH risk with an action when a mandatory readiness item is missing, MEDIUM
// when only the terms are incomplete, and LOW once everything is in order.
t_section('connect operations advisor (K12)');

// tiny helper: does any action mention the needle (case-insensitive)?
if (!function_exists('collect_has')) {
    function collect_has($arr, $needle) {
        foreach ((array)$arr as $s) if (stripos((string)$s, $needle) !== false) return true;
        return false;
    }
}

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $rid = cx_requirement_create(['title' => 'Welding inspector — vessels', 'start_date' => date('Y-m-d', strtotime('+7 days'))], true);
    $req = cx_requirement_get($rid);

    // Nothing set yet → mandatory readiness missing → HIGH, with actions.
    $a0 = connect_advisor_for_requirement($req);
    t_eq('HIGH', $a0['risk'], 'an unprepared engagement is HIGH delay risk');
    t_ok(!empty($a0['actions']), 'the advisor lists concrete actions');
    t_ok($a0['readiness_pct'] < 100, 'readiness is below 100% when items are missing');

    // Tick all readiness items, but leave terms incomplete → MEDIUM.
    foreach (array_keys(cx_readiness_items()) as $k) cx_readiness_set($rid, $k, true);
    $a1 = connect_advisor_for_requirement($req);
    t_eq('MEDIUM', $a1['risk'], 'ready site but incomplete terms is MEDIUM risk');
    t_ok(collect_has($a1['actions'], 'commercial'), 'the advisor asks to agree commercial terms');

    // Complete the terms → LOW, no actions.
    $full = [];
    foreach (array_keys(cx_terms_fields()) as $f) $full[$f] = 'set';
    cx_terms_save($rid, $full);
    $a2 = connect_advisor_for_requirement($req);
    t_eq('LOW', $a2['risk'], 'ready site + complete terms is LOW risk');
    t_eq([], $a2['actions'], 'no actions remain when everything is in order');
    t_eq(100, (int)$a2['readiness_pct'], 'readiness is 100%');
}
finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
