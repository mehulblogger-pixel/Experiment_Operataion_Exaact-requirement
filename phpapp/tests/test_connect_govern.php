<?php
// Connect K10 — operational governance (Part-F F1 commercial terms + F3 site
// readiness). Asserts the term-sheet saves and reports completeness, and the
// readiness checklist scores and gives a READY/HOLD verdict on the mandatory
// items.
t_section('connect governance: terms + readiness (K10)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    $rid = cx_requirement_create(['title' => 'Welding inspector — vessels'], true);

    // --- F1 commercial terms ------------------------------------------------
    t_ok(!cx_terms_complete($rid), 'a requirement with no terms is not complete');
    // Save a partial term-sheet.
    cx_terms_save($rid, ['inspection_days' => '3', 'max_hours_day' => '8', 'waiting_grace_hrs' => '2']);
    t_ok(!cx_terms_complete($rid), 'a partial term-sheet is still not complete');
    $t = cx_terms_get($rid);
    t_eq('3', (string)$t['inspection_days'], 'a saved term is stored');

    // Fill every field → complete.
    $full = [];
    foreach (array_keys(cx_terms_fields()) as $f) $full[$f] = 'set';
    cx_terms_save($rid, $full);
    t_ok(cx_terms_complete($rid), 'a fully-filled term-sheet is complete (publishable)');

    // --- F3 site readiness --------------------------------------------------
    $r0 = cx_readiness_score($rid);
    t_eq('HOLD', $r0['verdict'], 'with nothing checked the site is on HOLD');
    t_ok(!empty($r0['missing_mandatory']), 'the missing mandatory items are listed');

    // Check every item.
    foreach (array_keys(cx_readiness_items()) as $key) cx_readiness_set($rid, $key, true);
    $r1 = cx_readiness_score($rid);
    t_eq('READY', $r1['verdict'], 'with all items checked the site is READY to mobilize');
    t_eq(100, (int)$r1['score'], 'the readiness score reaches 100%');
    t_eq([], $r1['missing_mandatory'], 'nothing mandatory is missing');

    // Un-check one MANDATORY item → back to HOLD; un-checking an optional one stays READY.
    cx_readiness_set($rid, 'gate_pass', false);   // mandatory
    t_eq('HOLD', cx_readiness_score($rid)['verdict'], 'un-checking a mandatory item holds mobilization');
    cx_readiness_set($rid, 'gate_pass', true);
    cx_readiness_set($rid, 'crane_available', false);   // optional
    t_eq('READY', cx_readiness_score($rid)['verdict'], 'an optional item does not block readiness');

    // Unknown item is rejected.
    t_ok(!cx_readiness_set($rid, 'not_a_real_item', true), 'an unknown readiness item is rejected');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
