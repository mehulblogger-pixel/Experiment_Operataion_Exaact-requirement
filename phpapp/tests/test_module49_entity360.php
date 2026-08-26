<?php
// Module 49 — Entity 360 standard. The activity spine (lib/activity.php) + act_render_timeline()
// give every record a "what happened over time" panel — but Module 40 wired it onto only complaint
// and NCR, while peers with the SAME spine data showed nothing: opportunity_detail FETCHED its
// timeline and never rendered it, and invoice_detail (which logs CREATED/ISSUED/CANCELLED/CREDIT)
// showed no history at all. Wire the SAME helper onto both. Additive; kinds already registered.
t_section('Module 49 — activity timeline extended to opportunity + invoice detail');

t_ok(function_exists('act_render_timeline'), 'the shared act_render_timeline() helper exists');
t_ok(function_exists('act_log') && function_exists('act_for_entity'), 'the activity spine read/write exist');
$actKinds = array_key_exists('OPPORTUNITY', ACT_ENTITIES) ? array_keys(ACT_ENTITIES) : array_values(ACT_ENTITIES);
t_ok(in_array('OPPORTUNITY', $actKinds, true) && in_array('INVOICE', $actKinds, true),
    'OPPORTUNITY and INVOICE are registered spine kinds (no schema change needed)');

// The two views now call the shared renderer (matching the complaint/NCR precedent).
$opp = file_get_contents(__DIR__ . '/../views/ops/opportunity_detail.php');
$inv = file_get_contents(__DIR__ . '/../views/ops/invoice_detail.php');
t_ok(strpos($opp, "act_render_timeline('OPPORTUNITY', (int)\$o['id']") !== false,
    'the opportunity detail renders its activity timeline (previously fetched then discarded)');
t_ok(strpos($inv, "act_render_timeline('INVOICE', (int)\$inv['id']") !== false,
    'the invoice detail renders its activity timeline');

// End-to-end: an activity written to the spine comes back through the shared renderer.
if (!function_exists('view')) { function view($tpl, $data = []) { $GLOBALS['__nv'] = $data; } }
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ops_ensure_schema')) ops_ensure_schema();
    if (function_exists('act_migrate')) act_migrate();

    act_log('OPPORTUNITY', 4242, 'NOTE', 'Client asked for revised pricing', ['auto' => 0]);
    $rows = act_for_entity('OPPORTUNITY', 4242);
    t_ok(count($rows) >= 1, 'an activity written to an OPPORTUNITY is retrievable by act_for_entity');

    ob_start();
    act_render_timeline('OPPORTUNITY', 4242, 'Activity');
    $html = ob_get_clean();
    t_ok(strpos($html, 'Activity') !== false, 'the rendered panel carries the title');
    t_ok(strpos($html, 'Client asked for revised pricing') !== false, 'the rendered panel shows the activity row');

    // A record with no activity renders the empty-state, not a crash.
    ob_start();
    act_render_timeline('INVOICE', 999999, 'Activity');
    $empty = ob_get_clean();
    t_ok(strpos($empty, 'Nothing recorded yet') !== false, 'an entity with no history shows a safe empty state');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// ---- preservation ----
$cmp = file_get_contents(__DIR__ . '/../views/ops/complaint_detail.php');
t_ok(strpos($cmp, "act_render_timeline('COMPLAINT'") !== false, 'the Module-40 complaint timeline is preserved');
t_ok(strpos($opp, 'How it moved') !== false, 'the existing opportunity stage-move history is preserved (additive, not replaced)');
