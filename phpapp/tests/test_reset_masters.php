<?php
// "Clear records" must actually keep master data cleared. The bug: boot()
// re-seeds the master lists (lk_seed) and the offices (ops_seed) whenever those
// tables are empty — which is exactly the state a clear leaves them in — so the
// delete looked as though it had done nothing. A deliberate clear now sets a
// flag that stops the re-seed; forms fall back to their built-in defaults.
//
// The live-table parts run inside a transaction that is rolled back, so this
// test cannot disturb the shared test database the rest of the suite uses.
t_section('clearing master data stays cleared (#reset)');

$own = !db()->inTransaction();
if ($own) db()->beginTransaction();
try {
    // --- Master lists ------------------------------------------------------
    $before = (int)ops_val("SELECT COUNT(*) FROM lookup_types");
    t_ok($before > 0, 'the master lists are seeded to begin with');

    // What the Clear-records button does for the "masters" group: wipe the
    // tables and mark them cleared on purpose.
    db()->exec("DELETE FROM lookup_values");
    db()->exec("DELETE FROM lookup_types");
    setting_set('masters_seeded', '1');

    // The re-seed that runs on every boot must now be a no-op.
    lk_seed();
    t_eq((int)ops_val("SELECT COUNT(*) FROM lookup_types"), 0, 'lk_seed does not refill the lists once they are cleared on purpose');
    lk_ensure_type_map('some_new_key', 'Some list', ['A' => 'Apple'], 'Test');
    t_eq((int)ops_val("SELECT COUNT(*) FROM lookup_types"), 0, 'on-demand list creation is also suppressed while everything is cleared');

    // A dropdown still works — it falls back to the built-in constant.
    $opts = lk_options_or('client_type', CLIENT_TYPES);
    t_ok(!empty($opts) && $opts === CLIENT_TYPES, 'a dropdown falls back to its built-in defaults with the list empty (app still usable)');

    // Clearing the flag lets a fresh seed run again.
    setting_set('masters_seeded', '');
    lk_seed();
    t_ok((int)ops_val("SELECT COUNT(*) FROM lookup_types") > 0, 'clearing the flag lets the lists seed again');

    // --- Offices -----------------------------------------------------------
    $offBefore = (int)ops_val("SELECT COUNT(*) FROM offices");
    t_ok($offBefore > 0, 'the offices are seeded to begin with');

    db()->exec("DELETE FROM offices");
    setting_set('offices_seeded', '1');
    ops_seed();
    t_eq((int)ops_val("SELECT COUNT(*) FROM offices"), 0, 'ops_seed does not refill the offices once they are cleared on purpose');

    setting_set('offices_seeded', '');
    ops_seed();
    t_ok((int)ops_val("SELECT COUNT(*) FROM offices") > 0, 'clearing the flag lets the offices seed again');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();   // restore the shared DB exactly
}

// --- The wiring in reset_run sets those flags (source check, non-destructive)
$src = (string)file_get_contents(__DIR__ . '/../lib/reset.php');
t_ok(strpos($src, "setting_set('masters_seeded', '1')") !== false, 'a masters clear sets the masters_seeded flag');
t_ok(strpos($src, "setting_set('offices_seeded', '1')") !== false, 'a people/offices clear sets the offices_seeded flag');
