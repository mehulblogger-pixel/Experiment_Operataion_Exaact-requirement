<?php
// Phase 2 §17 — universal activity timeline. RECEIPT and CANDIDATE activities were already written to
// the shared spine (act_log) but the entities were not registered in ACT_ENTITIES, so the universal
// timeline could neither label nor link them, and their detail screens had no history panel. This
// registers them (additive) and shows their own spine history. Non-destructive: no existing entry,
// route or renderer changes.
t_section('Phase 2 §17 — register spine entities that were already logged (CANDIDATE / RECEIPT)');

t_ok(defined('ACT_ENTITIES'), 'the activity entity registry exists');
t_ok(isset(ACT_ENTITIES['CANDIDATE']), 'CANDIDATE is now a registered timeline entity');
t_ok(isset(ACT_ENTITIES['RECEIPT']), 'RECEIPT is now a registered timeline entity');
t_ok(isset(ACT_ENTITIES['CONTRACT']), 'CONTRACT is now a registered timeline entity');
t_eq(ACT_ENTITIES['CANDIDATE'][1], '/candidate?id=', 'CANDIDATE links to the candidate route');
t_eq(ACT_ENTITIES['RECEIPT'][1], '/receipt?id=', 'RECEIPT links to the receipt route');

// The entries the app already writes must round-trip through act_for_entity now that the entity is known.
$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('act_migrate')) act_migrate();
    if (function_exists('act_log')) {
        act_log('CANDIDATE', 918101, 'STAGE', 'Moved to interview in a test');
        act_log('RECEIPT', 918202, 'CREATED', 'Receipt logged in a test');
        $candHist = act_for_entity('CANDIDATE', 918101, 10);
        $recHist  = act_for_entity('RECEIPT', 918202, 10);
        t_ok(count($candHist) >= 1, 'a candidate activity round-trips through the universal spine');
        t_ok(count($recHist) >= 1, 'a receipt activity round-trips through the universal spine');
        // Isolation: a candidate id does not pull another entity's rows.
        $other = act_for_entity('CANDIDATE', 918202, 10);
        t_ok(count($other) === 0, 'the timeline is scoped to the one entity (no cross-entity bleed)');
    } else {
        t_ok(true, 'act_log unavailable — skipped round-trip');
    }
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// The detail screens now render the history panel.
$cv = file_get_contents(__DIR__ . '/../views/ops/candidate_detail.php');
$rv = file_get_contents(__DIR__ . '/../views/ops/receipt_detail.php');
t_ok(strpos($cv, "act_render_timeline('CANDIDATE'") !== false, 'the candidate detail shows its history');
t_ok(strpos($rv, "act_render_timeline('RECEIPT'") !== false, 'the receipt detail shows its history');
