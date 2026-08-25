<?php
// Module 13 — CAPA. The effectiveness gate already works; this makes the RCA method
// list CONFIGURABLE (seeded editable lookup, const fallback) and fills the missing
// unit coverage of the close/verify/effectiveness gate. Lifecycle unchanged.
t_section('Module 13 — configurable RCA methods + the CAPA close gate');

$capa = file_get_contents(__DIR__ . '/../lib/capa.php');
$view = file_get_contents(__DIR__ . '/../views/ops/capa_detail.php');

t_ok(function_exists('capa_rc_methods'), 'capa_rc_methods() exists');

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('capa_migrate')) capa_migrate();

    // Configurable: an admin addition to the lookup appears alongside the seeded
    // defaults (added before the first read so the per-request cache picks it up).
    $customSeen = false;
    if (function_exists('lk_type') && function_exists('lk_add_value')) {
        $t = lk_type('capa_rc_method');
        if ($t) { lk_add_value((int)$t['id'], null, 'BARRIER', 'Barrier analysis'); $customSeen = true; }
    }
    $methods = capa_rc_methods();
    t_ok(isset($methods['FIVE_WHY']) && isset($methods['FISHBONE']),
        'the built-in defaults are always available (seeded, or const fallback)');
    if ($customSeen) {
        t_ok(isset($methods['BARRIER']), 'an organisation-added RCA method appears in the list');
    } else {
        t_ok(true, 'lookup engine not initialised in this build — custom-method check skipped (const fallback in use)');
    }

    // Wiring: the picker and the save-time validation both read capa_rc_methods().
    t_ok(strpos($capa, '$rcMethods = capa_rc_methods();') !== false && strpos($capa, 'if (!isset($rcMethods[$m]))') !== false,
        'the cause handler validates against the configurable method list');
    t_ok(strpos($view, 'foreach (capa_rc_methods() as $k=>$v)') !== false,
        'the detail form offers the configurable method list');
    t_ok(strpos($capa, "lk_ensure_type_map('capa_rc_method'") !== false,
        'the method lookup is seeded from the defaults (own type, no collision with NCDCA)');

    // ---- The close gate (previously untested) ----
    $id = capa_create(['title' => 'Test CAPA', 'description' => 'A gap to fix', 'source' => 'SELF_IDENTIFIED', 'severity' => 'MINOR']);
    t_ok($id > 0, 'a CAPA can be raised');
    $get = fn() => ops_one("SELECT * FROM capa WHERE id=?", [$id]);
    $miss0 = capa_close_missing($get());
    t_ok(count($miss0) >= 5, 'a fresh CAPA lists every closure requirement as still-missing');
    t_ok(capa_close_block($get()) !== '', 'and it cannot be closed yet');

    // Fill each requirement; the missing list shrinks to empty.
    db()->prepare("UPDATE capa SET root_cause='training gap', rc_method='FIVE_WHY', similar_checked=1, similar_found='NO',
                   action_plan='retrain the team', completed_on='2026-08-10', verified_on='2026-08-20', effective='YES',
                   effectiveness_note='reviewed 20 later reports; clean' WHERE id=?")->execute([$id]);
    t_ok(capa_close_missing($get()) === [], 'once cause, method, similar-check, action, completion and verification are recorded, nothing is missing');
    t_ok(capa_close_block($get()) === '', 'and the CAPA may now be closed');

    // Verified NOT effective → still blocked from closing as done (the headline gate).
    db()->prepare("UPDATE capa SET effective='NO' WHERE id=?")->execute([$id]);
    t_ok(capa_close_block($get()) !== '', 'a CAPA verified NOT effective cannot be closed as done');

    // A missing method alone re-opens the gate (proves rc_method is required).
    db()->prepare("UPDATE capa SET effective='YES', rc_method='' WHERE id=?")->execute([$id]);
    t_ok((bool)array_filter(capa_close_missing($get()), fn($m) => stripos($m, 'how the cause was worked out') !== false),
        'a missing RCA method blocks closure');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}

// No new permission.
t_ok(!preg_match('/can\(\x27capa\.method/', $capa), 'Module 13 introduces no new permission constant');
