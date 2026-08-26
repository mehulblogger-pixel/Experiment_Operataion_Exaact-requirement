<?php
// Module 40 — Activity timeline. Complaints and NCRs already write to the activity spine but their
// detail screens never surfaced it. Add a reusable read-only timeline panel (act_render_timeline)
// and wire it onto the complaint + NCR detail views. Reader-only; no new write; only this entity's
// own history. First coverage of the per-entity timeline surfacing.
t_section('Module 40 — per-entity activity timeline surfacing');

$lib = file_get_contents(__DIR__ . '/../lib/activity.php');
$cd  = file_get_contents(__DIR__ . '/../views/ops/complaint_detail.php');
$nd  = file_get_contents(__DIR__ . '/../views/ops/ncr_detail.php');

t_ok(function_exists('act_render_timeline'), 'act_render_timeline() exists');
t_ok(function_exists('act_for_entity'), 'the reader it reuses (act_for_entity) exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('act_migrate')) act_migrate();

    // Log two activities against a complaint, and one against a different NCR.
    act_log('COMPLAINT', 4242, 'NOTE', 'Complaint acknowledged', ['body' => 'Told the customer we are looking into it']);
    act_log('COMPLAINT', 4242, 'SYSTEM', 'Complaint received via portal', ['auto' => 1]);
    act_log('NCR', 7, 'NOTE', 'Disposition agreed');

    $rows = act_for_entity('COMPLAINT', 4242);
    t_eq(count($rows), 2, "the complaint's own activities are read back");
    t_ok(count(act_for_entity('COMPLAINT', 9999)) === 0, 'an entity with no activity has an empty timeline (no crash)');

    // The render helper produces the panel and shows only this entity's rows.
    ob_start();
    act_render_timeline('COMPLAINT', 4242, 'History of this complaint');
    $html = ob_get_clean();
    t_ok(strpos($html, 'History of this complaint') !== false, 'the panel renders its title');
    t_ok(strpos($html, 'Complaint acknowledged') !== false, 'the panel shows the logged activity');
    t_ok(strpos($html, 'Disposition agreed') === false, "the panel shows ONLY this complaint's history, not the NCR's");

    // Empty state.
    ob_start(); act_render_timeline('NCR', 9999, 'History'); $empty = ob_get_clean();
    t_ok(strpos($empty, 'Nothing recorded yet') !== false, 'an empty timeline says so, cleanly');

    // System vs person-typed is distinguished (grey pill for auto).
    ob_start(); act_render_timeline('COMPLAINT', 4242); $h2 = ob_get_clean();
    t_ok(strpos($h2, 'p-mut') !== false && strpos($h2, 'p-ok') !== false, 'system (auto) and person-typed rows are visually distinguished');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- wiring / preservation ----
t_ok(strpos($cd, "act_render_timeline('COMPLAINT'") !== false, 'the complaint detail now surfaces its timeline');
t_ok(strpos($nd, "act_render_timeline('NCR'") !== false, 'the NCR detail now surfaces its timeline');
t_ok(strpos($lib, 'function act_log') !== false, 'the activity writer is unchanged (additive read surface only)');
t_ok(strpos($lib, 'no write form') !== false, 'the timeline panel is documented as read-only');
