<?php
// Phase 3 §49 — the uniform Entity-360 shell. One consistent "whole story" view for any registered
// entity, composing the cross-cutting engines already built (tasks §26, activity §17, quality §39,
// party §23/24). It reads through those engines and adds no data. Fail-closed for unknown/unauthorised.
t_section('Phase 3 §49 — uniform Entity-360 shell');

t_ok(function_exists('entity_360_load') && function_exists('entity_360_render_panels') && function_exists('ops_entity_360'),
     'the entity-360 helpers exist');
$idx = file_get_contents(__DIR__ . '/../index.php');
t_ok(strpos($idx, "require __DIR__ . '/lib/entity360.php'") !== false, 'the entity360 lib is loaded by the front controller');
$ops = file_get_contents(__DIR__ . '/../lib/ops.php');
t_ok(strpos($ops, "case \$route === 'entity-360'") !== false, 'the /entity-360 route is dispatched');
$ncrv = file_get_contents(__DIR__ . '/../views/ops/ncr_detail.php');
t_ok(strpos($ncrv, "entity_360_link('NCR'") !== false, 'the NCR detail links to its 360 view');

// The registry declares, for each kind, which panels apply — quality only for the quality entities,
// party only for a person, and tasks + history for all.
$reg = entity_360_registry();
foreach (['JOB', 'NCR', 'CAPA', 'COMPLAINT', 'CANDIDATE'] as $k)
    t_ok(isset($reg[$k]), "the registry includes $k");
t_ok(in_array('quality', $reg['NCR'][2], true) && !in_array('quality', $reg['JOB'][2], true),
     'quality applies to an NCR but not to a job');
t_ok(in_array('party', $reg['CANDIDATE'][2], true) && !in_array('party', $reg['NCR'][2], true),
     'party applies to a candidate but not to an NCR');
foreach ($reg as $k => $r) t_ok(in_array('tasks', $r[2], true) && in_array('history', $r[2], true), "$k gets the common tasks + history panels");

$own = !db()->inTransaction(); if ($own) db()->beginTransaction();
try {
    if (function_exists('ncr_migrate')) ncr_migrate();
    $pdo = db();
    $pdo->prepare("INSERT INTO nonconformities (ref, title, status) VALUES ('NCR-360','Weld porosity','OPEN')")->execute();
    $nid = (int)$pdo->lastInsertId();

    // A known kind + real id loads, with a title and the NCR panel set.
    $e = entity_360_load('NCR', $nid);
    t_ok($e !== null, 'a real NCR loads in the 360 shell');
    t_eq($e['title'], 'NCR-360', 'the entity title is resolved');
    t_eq($e['back'], '/ncr-item?id=' . $nid, 'the back-link points at the record');
    t_ok($e['panels'] === $reg['NCR'][2], 'the loaded panels match the registry');

    // Fail-closed: an unknown kind, a zero id, and a non-existent record all return null.
    t_ok(entity_360_load('WIDGET', $nid) === null, 'an unregistered kind is refused');
    t_ok(entity_360_load('NCR', 0) === null, 'a zero id is refused');
    t_ok(entity_360_load('NCR', 999999) === null, 'a non-existent record is refused');

    // The panel renderer runs the applicable helpers without error and emits something.
    ob_start(); entity_360_render_panels('NCR', $nid, $reg['NCR'][2]); $html = ob_get_clean();
    t_ok(is_string($html), 'the panels render without error');
} finally {
    if ($own && db()->inTransaction()) db()->rollBack();
}
